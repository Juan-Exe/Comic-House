"""Sprint 5: revisión y subida real de doce archivos con fallo y respuesta perdida.

Ejecutar desde el repositorio: python tests/lote_capitulos.py
Docker y Playwright/Edge. Crea y elimina exclusivamente su cómic y sus páginas.
"""
import json
import os
from pathlib import Path
import re
import shutil
import tempfile
import uuid

from playwright.sync_api import sync_playwright, expect
from tomos_ui import BASE, php, elegir_obra, elegir_opcion
from subida_capitulo import png, cbz, cbr

CUENTA = 0


def comprobar(condicion, mensaje):
    global CUENTA
    if not condicion:
        raise AssertionError(mensaje)
    CUENTA += 1
    print('OK:', mensaje, flush=True)


def revisado(page):
    try:
        page.wait_for_function("""() => document.querySelectorAll('.lote-fila').length > 0 &&
            !document.getElementById('lote-resumen').textContent.includes('Comprobando') &&
            [...document.querySelectorAll('.lote-estado')].every(e => e.textContent !== 'Comprobando')""", timeout=10000)
    except Exception:
        print('Revisión pendiente:', page.locator('#lote-resumen').inner_text(),
              page.locator('.lote-estado').all_text_contents(), flush=True)
        raise


def fila(page, nombre):
    return page.locator('.lote-fila').filter(has=page.locator('.lote-nombre', has_text=re.compile('^' + re.escape(nombre) + '$')))


def probar(page, comic):
    errores_js = []
    page.on('pageerror', lambda e: errores_js.append(str(e)))
    endpoint = BASE + '/controlador/procesar_capitulo.php'
    archivos = {n: (cbr([('001.png', png())]) if n == 6 else cbz([('001.png', png())])) for n in range(1, 13)}
    fuente = {'ok': True, 'datos': {'fuente': 'Fuente de prueba', 'ediciones': [], 'requiere_edicion': False,
        'tomos': [{'tomo': n, 'desde': n, 'hasta': n, 'formato': 'grapa', 'titulo': 'Entrega ' + str(n), 'arco': '',
                   'fecha': '2025-02-20' if n == 2 else '', 'fecha_mes': '' if n == 2 else f'2025-{n:02d}'}
                  for n in range(1, 13)]}}
    page.route('**/controlador/buscar_tomos_wiki.php?*', lambda r: r.fulfill(json=fuente))
    page.route('**/controlador/buscar_capitulos_wiki.php?*', lambda r: r.fulfill(json={'ok': False}))
    peticiones = []

    def registrar(request):
        if request.url == endpoint:
            m = re.search(rb'name="numero_desde"\r\n\r\n(\d+)', request.post_data_buffer or b'')
            peticiones.append(int(m[1]) if m else None)
    page.on('request', registrar)
    perdida = {'hecha': False}

    def perder_respuesta(route):
        m = re.search(rb'name="numero_desde"\r\n\r\n(\d+)', route.request.post_data_buffer or b'')
        if m and int(m[1]) == 7 and not perdida['hecha']:
            respuesta = route.fetch()
            comprobar(respuesta.json().get('ok'), 'El servidor guarda el archivo 7 antes de perder su respuesta')
            perdida['hecha'] = True
            route.abort('connectionreset')
        else:
            route.continue_()
    page.route('**/controlador/procesar_capitulo.php', perder_respuesta)

    def registros():
        return json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query('SELECT c.*, "
            "(SELECT COUNT(*) FROM paginas p WHERE p.capitulo_id=c.id) AS paginas FROM capitulos c WHERE comic_id="
            + str(comic) + " ORDER BY numero_desde')->fetch_all(MYSQLI_ASSOC));"))

    page.goto(BASE + '/subir_capitulo.php', wait_until='domcontentloaded')
    elegir_obra(page, comic)
    page.wait_for_function("__tomosWiki?.fuente === 'Fuente de prueba'")
    seleccion = []
    for n in range(1, 13):
        nombre = 'chapter.cbz' if n == 3 else f'cap{n:02d}.' + ('cbr' if n == 6 else 'cbz')
        seleccion.append({'name': nombre, 'mimeType': 'application/octet-stream',
                          'buffer': b'corrupto' if n == 4 else archivos[n]})
    seleccion.append({'name': 'cap02 copia.cbz', 'mimeType': 'application/zip', 'buffer': archivos[2]})
    page.locator('#archivo-cap').set_input_files(seleccion)
    expect(page.locator('.lote-fila')).to_have_count(13)
    revisado(page)
    comprobar(not peticiones and not registros(), 'Elegir y revisar trece archivos no sube ni registra ninguno')
    comprobar('Capítulo 1' in fila(page, 'cap01.cbz').inner_text()
              and 'Entrega 1' in fila(page, 'cap01.cbz').inner_text()
              and '01/2025' in fila(page, 'cap01.cbz').inner_text(),
              'Cada archivo muestra número deducido, título y fecha con la precisión de la fuente')
    comprobar('Se solapa' in fila(page, 'cap02 copia.cbz').inner_text()
              and 'Se solapa' in fila(page, 'cap02.cbz').inner_text(),
              'Detecta rangos solapados dentro del lote antes de enviar los archivos')
    comprobar('Escribe el título' in fila(page, 'chapter.cbz').inner_text(),
              'No inventa un número o título cuando el nombre no permite deducirlo')
    fila(page, 'cap02 copia.cbz').get_by_role('button', name='Descartar cap02 copia.cbz').click()
    expect(page.locator('.lote-fila')).to_have_count(12)
    fila(page, 'chapter.cbz').locator('.lote-elegir').click()
    page.locator('#cap-desde').fill('3')
    revisado(page)
    comprobar('Entrega 3' in fila(page, 'chapter.cbz').inner_text(),
              'Corregir el número aprovecha el título y la fecha sin quitar el resto')
    fila(page, 'cap02.cbz').locator('.lote-elegir').click()
    page.locator('#cap-titulo').fill('Título manual conservado')
    elegir_opcion(page, '#fecha-precision', 'mes')
    page.locator('#fecha-display').fill('07 / 2020')
    fila(page, 'cap05.cbz').locator('.lote-elegir').click()
    elegir_opcion(page, '#fecha-precision', 'dia')
    page.locator('#fecha-display').fill('31 / 02 / 2025')
    revisado(page)
    comprobar('Esa fecha no existe' in fila(page, 'cap05.cbz').inner_text()
              and page.locator('#subir-capitulos').is_enabled(),
              'Una fecha inválida marca solo su fila y permite subir las otras entregas listas')
    elegir_opcion(page, '#fecha-precision', 'mes')
    page.locator('#fecha-display').fill('05 / 2025')
    fila(page, 'cap02.cbz').locator('.lote-elegir').click()
    comprobar(page.locator('#cap-titulo').input_value() == 'Título manual conservado'
              and page.locator('#fecha-display').input_value() == '07 / 2020',
              'Cambiar de fila conserva el título y la fecha escritos a mano')
    page.evaluate('cargarTomosWiki(window.__obra.titulo)')
    revisado(page)
    comprobar(page.locator('#cap-titulo').input_value() == 'Título manual conservado'
              and page.locator('#fecha-display').input_value() == '07 / 2020',
              'Una respuesta posterior de la fuente respeta las correcciones del lote')
    expect(page.locator('#subir-capitulos')).to_contain_text('Subir 12 archivos')
    imagen = Path(tempfile.gettempdir()) / 'comic-house-context' / 'lote-revision.png'
    imagen.parent.mkdir(exist_ok=True)
    page.screenshot(path=str(imagen), full_page=True)
    page.locator('#subir-capitulos').click()
    try:
        expect(page.locator('.lote-fila.subido')).to_have_count(11, timeout=20000)
    except AssertionError:
        print('Estado del lote:', page.locator('#lote-resumen').inner_text(), flush=True)
        print('Fila 7:', fila(page, 'cap07.cbz').inner_text(), flush=True)
        raise
    expect(page.locator('#progress-overlay')).not_to_be_visible()
    revisado(page)
    comprobar(len(registros()) == 11 and all(int(c['paginas']) == 1 for c in registros()),
              'Un archivo corrupto no revierte las once entregas válidas del lote')
    comprobar('Error' in fila(page, 'cap04.cbz').inner_text()
              and 'CBZ' in fila(page, 'cap04.cbz').inner_text(),
              'La fila fallida explica el error y conserva sus datos para corregirlos')
    comprobar(perdida['hecha'] and peticiones.count(7) == 1
              and 'Subido' in fila(page, 'cap07.cbz').inner_text(),
              'El recibo recupera la respuesta perdida sin volver a enviar el archivo 7')
    guardado = next(c for c in registros() if int(c['numero_desde']) == 2)
    comprobar(guardado['titulo'] == 'Título manual conservado'
              and guardado['fecha_publicacion_mes'] == '2020-07' and guardado['fecha_publicacion'] is None,
              'La subida múltiple guarda los datos manuales y el mes sin inventar un día')
    comprobar(len(peticiones) == 12 and peticiones == list(range(1, 13)),
              'Envía una petición por archivo, en orden, y continúa después del fallo')

    # Recargar conserva el manifiesto y consulta recibos, sin guardar archivos en sessionStorage.
    page.goto(BASE + '/subir_capitulo.php?comic_id=' + str(comic), wait_until='domcontentloaded')
    expect(page.locator('.lote-fila')).to_have_count(12)
    expect(page.locator('.lote-fila.subido')).to_have_count(11)
    revisado(page)
    comprobar('Vuelve a elegir' in fila(page, 'cap04.cbz').inner_text() and len(peticiones) == 12,
              'Después de recargar recupera el lote y solo pide el archivo pendiente')
    fila(page, 'cap04.cbz').locator('.lote-elegir').click()
    page.locator('#lote-archivo-nuevo').set_input_files({'name': 'cap04.cbz',
        'mimeType': 'application/zip', 'buffer': archivos[4]})
    revisado(page)
    expect(page.locator('#subir-capitulos')).to_contain_text('Continuar con 1 archivo')
    page.locator('#subir-capitulos').click()
    expect(page.locator('.lote-fila.subido')).to_have_count(12)
    expect(page.locator('#progress-overlay')).not_to_be_visible()
    revisado(page)
    comprobar(len(registros()) == 12 and peticiones == list(range(1, 13)) + [4],
              'Reanudar sube únicamente el archivo corregido sin duplicar los otros once')
    comprobar(all(int(c['paginas']) == 1 for c in registros()), 'Cada entrega termina con exactamente una página')

    # El recibo exige el mismo contenido y los mismos datos, no solo reutilizar una clave.
    guardado = next(c for c in registros() if int(c['numero_desde']) == 4)
    datos = {k: str(guardado[k] or '') for k in ['titulo', 'formato', 'numero_desde', 'numero_hasta', 'arco',
        'fecha_publicacion', 'fecha_publicacion_mes', 'subida_clave']}
    datos['comic_id'] = str(comic)
    def repetir(contenido, extra=None):
        return page.request.post(endpoint, multipart={**datos, **(extra or {}),
            'cbr_file': {'name': 'cap04.cbz', 'mimeType': 'application/zip', 'buffer': contenido}}).json()
    respuesta = repetir(archivos[4])
    comprobar(respuesta.get('ok') and respuesta.get('ya_subido') and len(registros()) == 12,
              'Repetir el mismo intento devuelve su recibo sin crear filas ni páginas')
    comprobar(repetir(cbz([('001.png', png(2, 1))])).get('error') == 'intento',
              'La misma clave con otro contenido se rechaza aunque el nombre coincida')
    comprobar(repetir(archivos[4], {'titulo': 'Otro título'}).get('error') == 'intento',
              'La misma clave no permite sustituir los datos de un capítulo guardado')

    # Nuevo archivo fuera de rango y con nombre sospechoso: avisos visibles ANTES de enviarlo.
    page.locator('#archivo-cap').set_input_files({'name': 'Watchmen 13.cbz', 'mimeType': 'application/zip', 'buffer': archivos[1]})
    revisado(page)
    fila(page, 'Watchmen 13.cbz').locator('.lote-elegir').click()
    page.locator('#cap-titulo').fill('Entrega adicional')
    revisado(page)
    texto = fila(page, 'Watchmen 13.cbz').inner_text()
    comprobar('rango publicado' in texto and 'sospecha por el nombre' in texto,
              'Cada fila muestra los avisos de rango y de obra antes de subir')
    antes = len(peticiones)
    page.locator('#subir-capitulos').click()
    expect(page.locator('#modal-obra')).to_be_visible()
    page.locator('#obra-cancelar').click()
    expect(page.locator('#modal-obra')).not_to_be_visible()
    revisado(page)
    comprobar(len(peticiones) == antes and len(registros()) == 12,
              'Cancelar un aviso pausa el lote antes de enviar ese archivo')

    page.set_viewport_size({'width': 390, 'height': 844})
    fila(page, 'Watchmen 13.cbz').scroll_into_view_if_needed()
    comprobar(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),
              'La lista, sus estados y el editor caben en una pantalla móvil')
    imagen = Path(tempfile.gettempdir()) / 'comic-house-context' / 'lote-movil.png'
    page.screenshot(path=str(imagen), full_page=True)
    comprobar(not errores_js, 'Sin errores JavaScript en revisión, subida, reanudación y recarga: ' + str(errores_js))

    page.locator('#lote-cerrar').click()
    comprobar(page.locator('#lote-panel').is_hidden() and page.locator('#input-comic-id').input_value() == str(comic)
              and page.locator('#cap-titulo').input_value() == '',
              'Vaciar la lista conserva la obra elegida y deja limpio el formulario individual')
    # Una obra manga agrupa capítulos: el tomo 5 no es el capítulo 5.
    php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"UPDATE comics SET tipo='Manga',capitulos='1 - 20',capitulo_hasta=20 WHERE id=" + str(comic) + "\");")
    fuente['datos']['tomos'] = [
        {'tomo': 5, 'desde': 13, 'hasta': 14, 'formato': 'rango', 'titulo': 'Arco quinto', 'arco': 'Arco quinto', 'fecha': '', 'fecha_mes': '2020-05'},
        {'tomo': 6, 'desde': 15, 'hasta': 16, 'formato': 'rango', 'titulo': 'Arco sexto', 'arco': 'Arco sexto', 'fecha': '2020-06-01', 'fecha_mes': ''}]
    page.goto(BASE + '/subir_capitulo.php?comic_id=' + str(comic), wait_until='domcontentloaded')
    page.wait_for_function("__tomosWiki?.tomos[0]?.desde === 13")
    page.locator('#archivo-cap').set_input_files([
        {'name': 'tomo05.cbz', 'mimeType': 'application/zip', 'buffer': archivos[1]},
        {'name': 'cap15-16.cbr', 'mimeType': 'application/octet-stream', 'buffer': archivos[6]}])
    revisado(page)
    comprobar('Capítulos 13–14' in fila(page, 'tomo05.cbz').inner_text()
              and page.locator('#cap-formato').input_value() == 'rango'
              and 'Capítulos 15–16' in fila(page, 'cap15-16.cbr').inner_text(),
              'Deduce el rango publicado del tomo de manga y respeta el rango explícito del otro archivo')
    page.locator('#subir-capitulos').click()
    expect(page.locator('.lote-fila.subido')).to_have_count(2)
    expect(page.locator('#progress-overlay')).not_to_be_visible()
    revisado(page)
    nuevos = [c for c in registros() if int(c['numero_desde']) >= 13]
    comprobar([(int(c['numero_desde']), int(c['numero_hasta']), c['formato']) for c in nuevos]
              == [(13, 14, 'rango'), (15, 16, 'rango')],
              'CBZ y CBR de manga guardan dos rangos independientes con sus páginas')
    caps = page.request.get(BASE + '/controlador/get_capitulos.php?comic_id=' + str(comic)).json()
    comprobar([int(c['numero_desde']) for c in caps] == list(range(1, 14)) + [15],
              'Ya subidos devuelve el orden numérico: 1, 2… 10, 11 y los rangos posteriores')
    entrada = {'comic_id': comic, 'archivos': [{'id': uuid.uuid4().hex, 'clave': uuid.uuid4().hex,
        'nombre': 'cap01.cbz', 'tamano': len(archivos[1]), 'datos': {'titulo': 'Recopilatorio', 'formato': 'tomo',
        'numero_desde': '', 'numero_hasta': '', 'arco': '', 'fecha_publicacion': '', 'fecha_publicacion_mes': ''}}]}
    respuesta = page.request.post(BASE + '/controlador/revisar_lote_capitulos.php', data=entrada).json()
    comprobar(respuesta['archivos'][0]['estado'] == 'revisar',
              'La revisión del servidor rechaza un formato que no corresponde al tipo manga')
    php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"UPDATE comics SET tipo='Comic' WHERE id=" + str(comic) + "\");")
    respuesta = page.request.post(BASE + '/controlador/revisar_lote_capitulos.php', data=entrada).json()
    comprobar(any('ya están' in e for e in respuesta['archivos'][0]['errores']),
              'Los recopilatorios también comprueban duplicados antes de subir el archivo')
    malformada = page.request.post(BASE + '/controlador/revisar_lote_capitulos.php',
        data={'comic_id': comic, 'archivos': [{'datos': 'incorrecto'}]})
    comprobar(malformada.status == 400 and not malformada.json()['ok'],
              'Una revisión malformada devuelve JSON de error sin ejecutar escrituras')
    anonimo = page.context.browser.new_context()
    try:
        respuesta = anonimo.request.post(BASE + '/controlador/revisar_lote_capitulos.php', data=entrada)
        comprobar(respuesta.status == 403 and not respuesta.json()['ok'],
                  'La revisión del lote exige una sesión de administrador')
    finally:
        anonimo.close()
    comprobar(not errores_js, 'El lote de manga también termina sin errores JavaScript')


if __name__ == '__main__':
    sesion = 'lote-test-' + uuid.uuid4().hex
    titulo = 'ZZZ_LOTE_TEST_' + uuid.uuid4().hex
    raiz = (Path(__file__).resolve().parents[1] / 'paginas').resolve()
    originales = set(raiz.iterdir())
    php(f"session_id('{sesion}'); session_start(); $_SESSION=['usuario'=>'Prueba local de lote','rol'=>'admin']; session_write_close();")
    comic = None
    try:
        comic = int(php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"INSERT INTO comics "
            "(titulo,clasificacion,descripcion,capitulos,anio,genero,portada,editorial,editorial_id,tipo,estado_publicacion,capitulo_desde,capitulo_hasta) "
            "SELECT '" + titulo + "',clasificacion,'Prueba temporal','1 - 12',2025,genero,portada,editorial,editorial_id,'Comic','Publicacion Terminada',1,12 "
            "FROM comics ORDER BY id LIMIT 1\"); echo $conexion->insert_id;"))
        with sync_playwright() as p:
            candidatos = [os.environ.get('COMIC_HOUSE_BROWSER', ''),
                r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
                r'C:\Program Files\Google\Chrome\Application\chrome.exe']
            ejecutable = next((c for c in candidatos if c and Path(c).is_file()), None)
            browser = p.chromium.launch(headless=True, **({'executable_path': ejecutable} if ejecutable else {}))
            try:
                context = browser.new_context(viewport={'width': 1440, 'height': 1000}, timezone_id='America/Bogota')
                context.add_cookies([{'name': 'PHPSESSID', 'value': sesion, 'url': BASE}])
                probar(context.new_page(), comic)
            finally:
                browser.close()
        print(f'\n{CUENTA} comprobaciones de lotes correctas.')
    finally:
        if comic:
            ids = json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query('SELECT id FROM capitulos WHERE comic_id=" + str(comic) + "')->fetch_all(MYSQLI_ASSOC));"))
            php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"DELETE FROM comics WHERE id=" + str(comic) + " AND titulo='" + titulo + "'\");")
            for c in ids:
                carpeta = raiz / ('cap_' + str(int(c['id'])))
                if carpeta.exists() and carpeta not in originales:
                    if carpeta.is_symlink() or carpeta.resolve().parent != raiz:
                        raise AssertionError('Carpeta de prueba fuera de paginas/')
                    shutil.rmtree(carpeta)
        php(f"session_id('{sesion}'); session_start(); session_destroy();")
