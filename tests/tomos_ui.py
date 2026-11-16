"""Pruebas en navegador con registros y un CBZ de una página temporales.

Se eliminan los fixtures y su carpeta al terminar, también si falla una prueba.

Requiere Docker con Comic House y Python con playwright. Usa Edge o Chrome local.
Ejecutar desde el repositorio: python tests/tomos_ui.py
"""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import uuid
import sys
import base64
import io
import shutil
import zipfile

from playwright.sync_api import sync_playwright, expect


BASE = "http://localhost:8080"
sys.stdout.reconfigure(encoding="utf-8")
SESSION = "tomos-test-" + uuid.uuid4().hex
PHP = ["docker", "exec", "--user", "www-data", "-i", "comic_house_web", "php"]
CORRECTAS = 0


def php(code):
    return subprocess.run(PHP, input="<?php " + code, text=True,
                          encoding="utf-8", capture_output=True, check=True).stdout


def comprobar(condicion, descripcion):
    global CORRECTAS
    if not condicion:
        raise AssertionError(descripcion)
    CORRECTAS += 1
    print("OK:", descripcion, flush=True)


def abrir_lista(page):
    if page.locator("#tomos-cab").get_attribute("aria-expanded") == "false":
        page.locator("#tomos-cab").click()


def elegir_obra(page, comic_id):
    if page.locator("#step-picker").is_hidden():
        page.locator("#selected-bar button").click()
    page.locator(f'.comic-card[data-id="{comic_id}"]').click()


def elegir_opcion(page, selector, valor):
    origen = page.locator(selector)
    texto = origen.evaluate("(s, v) => Array.from(s.options).find(o => o.value === v).textContent", valor)
    origen.locator('..').get_by_role('combobox').click()
    page.get_by_role('option', name=texto, exact=True).click()


def probar_edicion_mes(page):
    nombre = 'ZZZ_FECHA_TEST_' + uuid.uuid4().hex
    paginas = (Path(__file__).resolve().parents[1] / 'paginas').resolve()
    anteriores = {p.resolve() for p in paginas.iterdir() if p.is_dir()}
    ids = json.loads(php("require '/var/www/html/modelo/conexion.php'; $conexion->begin_transaction();"
        " $conexion->query(\"INSERT INTO comics (titulo,clasificacion,descripcion,capitulos,anio,genero,portada,editorial,editorial_id,tipo,estado_publicacion,capitulo_desde,capitulo_hasta)"
        " SELECT '" + nombre + "',clasificacion,'Prueba temporal','1 - 2',2025,genero,portada,editorial,editorial_id,'Comic','Publicacion Terminada',1,2 FROM comics ORDER BY id LIMIT 1\");"
        " $comic=$conexion->insert_id;"
        " $conexion->query(\"INSERT INTO capitulos (comic_id,titulo,numero,fecha_publicacion_mes,formato,numero_desde,numero_hasta)"
        " VALUES ($comic,'Prueba temporal','#1','2012-05','grapa',1,1)\");"
        " $capitulo=$conexion->insert_id; $conexion->commit(); echo json_encode(['comic'=>$comic,'capitulo'=>$capitulo]);"))
    try:
        archivo = io.BytesIO()
        with zipfile.ZipFile(archivo, 'w') as zip_cbz:
            zip_cbz.writestr('001.png', base64.b64decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC'))
        respuesta = page.request.post(BASE + '/controlador/procesar_capitulo.php', multipart={
            'comic_id':str(ids['comic']), 'titulo':'Prueba CBZ mensual', 'formato':'grapa',
            'numero_desde':'2', 'fecha_publicacion_mes':'2012-09',
            'cbr_file':{'name':'cap02.cbz', 'mimeType':'application/zip', 'buffer':archivo.getvalue()}}).json()
        subidos = page.request.get(BASE + '/controlador/get_capitulos.php?comic_id=' + str(ids['comic'])).json()
        subido = next((c for c in subidos if int(c['numero_desde']) == 2), None)
        comprobar(respuesta.get('ok') and subido is not None and
                  subido['fecha_publicacion'] is None and subido['fecha_publicacion_mes'] == '2012-09' and
                  subido['total_paginas'] == 1,
                  'La subida real de un CBZ conserva mes y año con una página válida')
        url = BASE + '/editar_capitulo.php?id=' + str(ids['capitulo'])
        page.goto(url, wait_until='domcontentloaded')
        comprobar(page.locator('#fecha-precision').input_value() == 'mes' and
                  page.locator('#fecha-display').input_value() == '05 / 2012',
                  'Editar recupera la fecha mensual guardada sin añadir un día')
        elegir_opcion(page, '#fecha-precision', 'dia')
        page.locator('#fecha-display').fill('03 / 05 / 2012')
        page.locator('form[action="controlador/actualizar_capitulo.php"] button[type="submit"]').click()
        page.wait_for_url('**/subir_capitulo.php')
        datos = page.request.get(BASE + '/controlador/get_capitulos.php?comic_id=' + str(ids['comic'])).json()[0]
        comprobar(datos['fecha_publicacion'] == '2012-05-03' and datos['fecha_publicacion_mes'] is None,
                  'Guardar una fecha completa sustituye la precisión mensual')
        page.goto(url, wait_until='domcontentloaded')
        elegir_opcion(page, '#fecha-precision', 'mes')
        page.locator('#fecha-display').fill('07 / 2012')
        page.locator('form[action="controlador/actualizar_capitulo.php"] button[type="submit"]').click()
        page.wait_for_url('**/subir_capitulo.php')
        datos = page.request.get(BASE + '/controlador/get_capitulos.php?comic_id=' + str(ids['comic'])).json()[0]
        comprobar(datos['fecha_publicacion'] is None and datos['fecha_publicacion_mes'] == '2012-07',
                  'Guardar mes y año conserva DATE nulo en el controlador real')
        page.goto(BASE + '/Comics/comics.php?id=' + str(ids['comic']), wait_until='domcontentloaded')
        comprobar(page.locator('.enlaces .fecha').first.inner_text().strip() == '07/2012',
                  'La ficha pública muestra el mes guardado sin un día ficticio')
    finally:
        capitulos = json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query('SELECT id FROM capitulos WHERE comic_id=" + str(ids['comic']) + "')->fetch_all(MYSQLI_ASSOC));"))
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"DELETE FROM comics WHERE id="
            + str(ids['comic']) + " AND titulo='" + nombre + "'\");")
        for capitulo in capitulos:
            destino = paginas / ('cap_' + str(int(capitulo['id'])))
            # Solo carpetas nuevas, de capítulos propiedad de este fixture.
            if destino.exists() and destino.resolve() not in anteriores:
                if destino.is_symlink() or destino.resolve().parent != paginas:
                    raise AssertionError('La carpeta temporal no pertenece a paginas/')
                shutil.rmtree(destino)


def probar(page):
    errores = []
    page.on("pageerror", lambda error: errores.append(str(error)))
    page.goto(BASE + "/subir_capitulo.php", wait_until="domcontentloaded")
    total = int(php("require '/var/www/html/modelo/conexion.php'; echo $conexion->query('SELECT COUNT(*) FROM comics')->fetch_row()[0];"))
    comprobar(page.locator(".comic-card").count() == total, "La pantalla carga con el catálogo actual, incluidas las obras nuevas")
    elegir_obra(page, 1)
    page.wait_for_selector(".fila-tomo", state="attached", timeout=120000)
    abrir_lista(page)
    comprobar(page.locator('.fila-tomo[data-d="1"][data-h="3"]').count() == 0,
              "Los tomos totalmente subidos no aparecen como pendientes")
    page.locator('#archivo-cap').set_input_files({"name": "Berserk #10-14.cbz",
        "mimeType": "application/zip", "buffer": b"fixture sin enviar"})
    comprobar(page.locator("#cap-desde").input_value() == "10" and
              page.locator("#cap-hasta").input_value() == "14" and
              page.locator("#fecha-hidden").input_value() == "1992-02-26",
              "Elegir el archivo encadena numeración, título y fecha sin subirlo")
    page.locator('.fila-tomo[data-d="10"][data-h="14"]').click()
    comprobar(page.locator("#cap-titulo").input_value() != "" and
              page.locator("#fecha-hidden").input_value() == "1992-02-26",
              "Un tomo rellena título, rango y fecha real")
    page.locator("#cap-titulo").fill("Mi título revisado")
    page.locator("#fecha-display").fill("03 / 03 / 1993")
    page.locator('.fila-tomo[data-d="15"][data-h="22"]').click()
    comprobar(page.locator("#cap-titulo").input_value() == "Mi título revisado" and
              page.locator("#fecha-hidden").input_value() == "1993-03-03",
              "Las propuestas respetan el título y la fecha escritos a mano")
    comprobar("0-15" in page.locator("#tomo-pista").inner_text(),
              "El reinicio de Berserk conserva visible su numeración original")

    elegir_obra(page, 7)
    expect(page.locator("#cap-titulo")).to_have_value("")
    expect(page.locator("#edicion-tomos")).to_be_visible(timeout=120000)
    comprobar(page.locator("#edicion-tomos").input_value() == "",
              "El año 1980 del catálogo requiere elegir una edición")
    elegir_opcion(page, "#edicion-tomos", "wiki:marvel:Ultimate Spider-Man Vol 1")
    page.wait_for_selector(".fila-tomo", state="attached", timeout=120000)
    abrir_lista(page)
    comprobar(page.evaluate("__tomosWiki.edicion.anio") == 2000 and
              page.evaluate("__tomosWiki.tomos.every(t => t.url.includes('Vol_1_'))"),
              "La lista de Ultimate de 2000 contiene solo esa serie")
    page.locator('.fila-tomo[data-d="2"]').click()
    comprobar(page.locator("#fecha-hidden").input_value().startswith("2000-"),
              "Al elegir un número de la serie original no aparece una fecha de 2024")
    elegir_opcion(page, "#edicion-tomos", "wiki:marvel:Ultimate Spider-Man Vol 3")
    page.wait_for_function("__tomosWiki?.edicion?.id === 'wiki:marvel:Ultimate Spider-Man Vol 3'", timeout=120000)
    comprobar(page.evaluate("__tomosWiki.edicion.anio") == 2024 and
              page.evaluate("__tomosWiki.tomos.every(t => t.url.includes('Vol_3_'))"),
              "Cambiar al relanzamiento sustituye la lista y mantiene separada la caché")

    elegir_obra(page, 2)
    page.wait_for_function("__tomosWiki?.fuente?.includes('imagecomics') && Array.isArray(window.__capitulos)", timeout=120000)
    comprobar(page.locator('.fila-tomo[data-d="3"]').count() == 1 and
              'Sin título publicado' in page.locator('.fila-tomo[data-d="3"]').inner_text(),
              'Saga muestra también los números sin título y lo indica explícitamente')
    page.locator('#cap-desde').fill('3')
    comprobar(page.locator("#cap-formato").input_value() == "grapa" and
              page.locator("#fecha-hidden").input_value() == "" and
              page.locator('#fecha-mes-hidden').input_value() == '2012-05' and
              page.locator('#fecha-display').input_value() == '05 / 2012',
              "Escribir el número de Saga aprovecha el mes publicado sin inventar día ni título")
    elegir_opcion(page, '#fecha-precision', 'dia')
    page.locator('#fecha-display').fill('30 / 02 / 2024')
    comprobar(not page.locator('#fecha-display').evaluate('(e) => e.checkValidity()'),
              'Una fecha completa imposible bloquea el envío del formulario')
    boton = page.locator('#fecha-precision').locator('..').get_by_role('combobox')
    boton.focus()
    boton.press('End')
    boton.press('Enter')
    page.locator('#fecha-display').fill('07 / 2012')
    comprobar(page.locator('#fecha-precision').input_value() == 'mes' and
              page.locator('#fecha-mes-hidden').input_value() == '2012-07',
              'El desplegable personalizado admite teclado y entrada mensual manual')

    elegir_obra(page, 5)
    expect(page.locator("#edicion-tomos")).to_be_visible(timeout=120000)
    elegir_opcion(page, "#edicion-tomos", "cv:108799")
    page.wait_for_selector(".fila-tomo", state="attached", timeout=120000)
    comprobar(page.evaluate("__tomosWiki.tomos.length") == 16 and
              page.evaluate("__tomosWiki.fuente") == "ComicVine",
              "Bloodborne ofrece el respaldo con la edición seleccionada, sin cambiar su editorial local")
    comprobar(page.locator('.fila-tomo').count() == 15 and
              'Sin título publicado' in page.locator('#lista-tomos').inner_text(),
              'Las fechas y números sin título siguen disponibles junto a los títulos publicados')

    # Cambia deliberadamente el orden de llegada e ignora abortos para comprobar
    # también los turnos, no solo el comportamiento de AbortController.
    page.evaluate("""() => {
        window.fetchOriginalPrueba = window.fetch;
        window.fetch = (url, opts = {}) => {
            const u = new URL(url, location.href);
            const id = u.searchParams.get('comic_id');
            if (u.pathname.endsWith('get_capitulos.php') && id === '2') {
                return new Promise(resolve => setTimeout(() => resolve(new Response(JSON.stringify([
                    {numero_desde:1, numero_hasta:1, titulo:'Subido', numero:'#1',
                     id:1, imagenes:[], total_paginas:0, fecha_publicacion:null}
                ]), {headers:{'Content-Type':'application/json'}})), 600));
            }
            if (u.pathname.endsWith('buscar_tomos_wiki.php')) {
                const nombre = u.searchParams.get('titulo');
                const demora = nombre === 'Watchmen' ? 700 : 30;
                return new Promise(resolve => setTimeout(() => resolve(new Response(JSON.stringify({
                    ok:true, datos:{tomos:[1,2].map(n => ({tomo:n,desde:n,hasta:n,formato:'grapa',
                        titulo:nombre+' '+n,arco:'',fecha:'',fecha_mes:''})),fuente:nombre,
                        ediciones:[],requiere_edicion:false}
                }), {headers:{'Content-Type':'application/json'}})), demora));
            }
            return window.fetchOriginalPrueba(url, opts);
        };
    }""")
    elegir_obra(page, 2)
    page.wait_for_function("__tomosWiki?.fuente === 'Saga' && window.__capitulos === null")
    comprobar(page.locator(".fila-tomo").count() == 0,
              "No calcula pendientes antes de recibir los capítulos subidos")
    page.wait_for_function("Array.isArray(window.__capitulos)")
    comprobar(page.locator('.fila-tomo[data-d="1"]').count() == 0 and
              page.locator('.fila-tomo[data-d="2"]').count() == 1,
              "Recalcula los pendientes cuando los capítulos llegan después de la fuente")
    elegir_obra(page, 133)
    elegir_obra(page, 2)
    page.wait_for_function("__tomosWiki?.fuente === 'Saga'")
    page.wait_for_timeout(900)  # mayor que la respuesta atrasada deliberada
    comprobar(page.locator("#sel-nombre").inner_text() == "Saga" and
              page.evaluate("__tomosWiki.fuente") == "Saga",
              "Una respuesta atrasada de Watchmen no pisa la obra seleccionada")
    page.evaluate("window.fetch = window.fetchOriginalPrueba")

    page.route("**/controlador/buscar_tomos_wiki.php?*", lambda route: route.fulfill(
        json={"ok": False, "aviso": "Fuente no disponible. Puedes escribir los datos a mano."}))
    elegir_obra(page, 133)
    expect(page.locator("#lista-tomos")).to_contain_text("Fuente no disponible")
    page.locator("#cap-titulo").fill("Título manual sin fuente")
    comprobar(page.locator("#cap-titulo").is_enabled() and
              page.locator("#cap-titulo").input_value() == "Título manual sin fuente",
              "Un fallo de la fuente deja el formulario manual disponible")

    valido = page.request.post(BASE + '/controlador/procesar_capitulo.php', form={
        'comic_id':'133','titulo':'Prueba sin archivo','formato':'grapa','numero_desde':'9900',
        'fecha_publicacion_mes':'2012-05'}).json()
    invalido = page.request.post(BASE + '/controlador/procesar_capitulo.php', form={
        'comic_id':'133','titulo':'Prueba sin archivo','formato':'grapa','numero_desde':'9900',
        'fecha_publicacion_mes':'2012-13'}).json()
    comprobar(valido['error'] == 'sin_archivo' and invalido['error'] == 'fecha',
              'El procesador real acepta mes válido y rechaza mes imposible antes de escribir')

    probar_edicion_mes(page)

    page.unroute("**/controlador/buscar_tomos_wiki.php?*")
    page.goto(BASE + '/subir_capitulo.php', wait_until='domcontentloaded')
    elegir_obra(page, 5)
    expect(page.locator('#edicion-tomos')).to_be_attached(timeout=120000)
    page.set_viewport_size({"width": 390, "height": 844})
    page.locator('#edicion-tomos').locator('..').get_by_role('combobox').click()
    expect(page.get_by_role('listbox')).to_be_visible()
    limites = page.get_by_role('listbox').bounding_box()
    comprobar(limites['x'] >= 0 and limites['x'] + limites['width'] <= 391 and
              limites['y'] >= 0 and limites['y'] + limites['height'] <= 845,
              'Las opciones personalizadas se mantienen dentro de la pantalla móvil')
    comprobar(page.evaluate("document.documentElement.scrollWidth <= innerWidth"),
              "La lista y el formulario caben en una pantalla móvil")
    comprobar(not errores, "Sin errores de JavaScript: " + str(errores))
    imagen = Path(tempfile.gettempdir()) / "comic-house-context" / "tomos-verificacion.png"
    imagen.parent.mkdir(exist_ok=True)
    page.screenshot(path=str(imagen))


if __name__ == "__main__":
    # Sesión efímera de pruebas: no crea usuarios ni cambia contraseñas.
    php(f"session_id('{SESSION}'); session_start(); "
        "$_SESSION=['usuario'=>'Prueba local de tomos','rol'=>'admin']; session_write_close();")
    try:
        with sync_playwright() as p:
            candidatos = [os.environ.get("COMIC_HOUSE_BROWSER", ""),
                          r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
                          r"C:\Program Files\Google\Chrome\Application\chrome.exe"]
            ejecutable = next((c for c in candidatos if c and Path(c).is_file()), None)
            browser = p.chromium.launch(headless=True, **({"executable_path": ejecutable} if ejecutable else {}))
            context = browser.new_context(viewport={"width": 1440, "height": 1000}, timezone_id="America/Bogota")
            context.add_cookies([{"name": "PHPSESSID", "value": SESSION, "url": BASE}])
            try:
                probar(context.new_page())
            finally:
                browser.close()
        print(f"\n{CORRECTAS} comprobaciones de navegador correctas.")
    finally:
        php(f"session_id('{SESSION}'); session_start(); session_destroy();")
