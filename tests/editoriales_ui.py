"""Sprint 6: APIs y navegador con editoriales temporales y PNG reales.

python tests/editoriales_ui.py. Requiere Docker y Playwright/Edge.
Consulta el logo real de Kodansha; las propuestas para nombres de fixture se simulan.
No registra cómics ni modifica editoriales del usuario. Limpia sus filas y logos.
"""
import base64
from concurrent.futures import ThreadPoolExecutor
import json
import os
from pathlib import Path
import tempfile
import urllib.parse
import urllib.request
import uuid

from playwright.sync_api import sync_playwright, expect
from tomos_ui import BASE, php
from subida_capitulo import png

CUENTA = 0
PREFIJO = 'ZZZ_S6_' + uuid.uuid4().hex[:12]


def comprobar(condicion, mensaje):
    global CUENTA
    if not condicion:
        raise AssertionError(mensaje)
    CUENTA += 1
    print('OK:', mensaje, flush=True)


def nueva():
    return PREFIJO + '_' + uuid.uuid4().hex[:12]


def filas():
    return json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query("
        "\"SELECT id,nombre,imagen FROM editoriales WHERE LEFT(nombre," + str(len(PREFIJO)) + ")='" + PREFIJO + "'\")->fetch_all(MYSQLI_ASSOC));"))


def originales():
    return php("require '/var/www/html/modelo/conexion.php'; echo json_encode(["
        "$conexion->query(\"SELECT * FROM editoriales WHERE LEFT(nombre," + str(len(PREFIJO)) + ")<>'" + PREFIJO + "' ORDER BY id\")->fetch_all(MYSQLI_ASSOC),"
        "$conexion->query('SELECT COUNT(*) FROM comics')->fetch_row()]);")


def probar(page, sesion, logo):
    errores_js = []
    page.on('pageerror', lambda e: errores_js.append(str(e)))
    api = BASE + '/controlador/crear_editorial.php'
    busqueda = BASE + '/controlador/buscar_logo_editorial.php'
    directorio = (Path(__file__).resolve().parents[1] / 'uploads').resolve()
    imagen = png(5, 3)

    def crear(nombre, contenido=None, **extra):
        datos = {'nombre': nombre, **extra}
        if contenido is not None:
            datos['imagen'] = {'name': 'logo.png', 'mimeType': 'image/png', 'buffer': contenido}
            return page.request.post(api, multipart=datos).json()
        return page.request.post(api, form=datos).json()

    nombre = nueva()
    alta = crear(nombre, imagen, exigir_logo='1')
    comprobar(alta.get('ok') and not alta.get('reutilizada') and alta['imagen'].endswith('.png'),
              'Crea una editorial con imagen validada y nombre de archivo único')
    comprobar(page.request.get(BASE + '/uploads/' + alta['imagen']).ok, 'El logo guardado se puede leer por HTTP')
    repetir = crear('  ' + nombre.lower() + '  ', imagen)
    comprobar(repetir.get('reutilizada') and repetir['id'] == alta['id'] and repetir['imagen'] == alta['imagen'],
              'Reutiliza mayúsculas y espacios sin reemplazar el logo existente')
    acento = nueva() + '_Éditions'
    ac = crear(acento)
    dup = crear(acento.replace('É', 'e').upper())
    comprobar(ac.get('ok') and dup.get('reutilizada') and dup['id'] == ac['id'],
              'Reutiliza la misma editorial aunque cambien las tildes y mayúsculas')
    dc = crear('DC Comics')
    comprobar(dc.get('reutilizada') and dc['nombre'] == 'DC', 'DC Comics reutiliza el registro DC mediante su alias conocido')
    cerca = nombre[:-1] + ('z' if nombre[-1] != 'z' else 'x')
    cantidad = len(filas())
    advertencia = crear(cerca)
    comprobar(advertencia.get('error') == 'parecida' and len(filas()) == cantidad,
              'Una editorial parecida pide revisión antes de insertar')
    confirmada = crear(cerca, confirmar='1')
    comprobar(confirmada.get('ok') and not confirmada.get('reutilizada'),
              'Una editorial distinta confirmada expresamente puede crearse')
    sin_logo = crear(nueva(), exigir_logo='1')
    comprobar(not sin_logo.get('ok'), 'El catálogo de editoriales exige logo para una nueva entrada')

    # Los formularios clásicos comparten validación con el asistente JavaScript.
    nativas_antes = filas()
    respuesta = page.request.post(BASE + '/editoriales.php', form={'accion':'crear','nombre':nombre.upper()}, max_redirects=0)
    comprobar('ok=reutilizada' in respuesta.headers.get('location','') and filas() == nativas_antes,
              'El formulario clásico también reutiliza la misma editorial')
    respuesta = page.request.post(BASE + '/editoriales.php', multipart={'accion':'editar','id':str(alta['id']),
        'nombre':nombre,'imagen':{'name':'falso.png','mimeType':'image/png','buffer':b'No soy un PNG'}}, max_redirects=0)
    comprobar('err=logo' in respuesta.headers.get('location','') and filas() == nativas_antes,
              'Editar rechaza un falso logo y conserva la imagen anterior')
    respuesta = page.request.post(BASE + '/editoriales.php', form={'accion':'editar','id':str(alta['id']),
        'nombre':'DC Comics'}, max_redirects=0)
    comprobar('err=duplicada' in respuesta.headers.get('location','') and filas() == nativas_antes,
              'Editar no permite convertir otra editorial en un duplicado de DC')
    respuesta = page.request.post(BASE + '/editoriales.php', form={'accion':'editar','id':str(alta['id']),
        'nombre':nombre.lower()}, max_redirects=0)
    editada = next(f for f in filas() if int(f['id']) == alta['id'])
    comprobar('ok=editada' in respuesta.headers.get('location','') and editada['nombre'] == nombre.lower()
              and editada['imagen'] == alta['imagen'], 'Editar la grafía propia conserva el id y el logo')

    antes_archivos = set(directorio.iterdir())
    cantidad = len(filas())
    for contenido, descripcion in [(b'no es una imagen', 'imagen falsa'), (b'', 'archivo vacío'),
        (base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='), 'imagen dañada'),
        (b'a' * (5 * 1024 * 1024 + 1), 'imagen mayor de 5 MB')]:
        resultado = crear(nueva(), contenido)
        comprobar(not resultado.get('ok') and len(filas()) == cantidad and set(directorio.iterdir()) == antes_archivos,
                  'Rechaza ' + descripcion + ' sin dejar filas ni archivos')
    for url in ['https://example.com/logo.png','http://127.0.0.1/logo.png','https://upload.wikimedia.org.evil.test/logo.png']:
        resultado = crear(nueva(), logo_url=url)
        comprobar(not resultado.get('ok') and len(filas()) == cantidad and set(directorio.iterdir()) == antes_archivos,
                  'No descarga ni guarda un logo desde ' + url)
    remota = crear(nueva(), logo_url=logo['url'], exigir_logo='1')
    comprobar(remota.get('ok') and page.request.get(BASE + '/uploads/' + remota['imagen']).headers.get('content-type', '').startswith('image/png'),
              'Descarga el PNG del logo SVG de Wikimedia y lo guarda como imagen real')

    # Dos peticiones concurrentes con la misma identidad no crean dos filas.
    concurrente = nueva()
    def enviar(nombre):
        req = urllib.request.Request(api, data=urllib.parse.urlencode({'nombre': nombre}).encode(),
            headers={'Cookie': 'PHPSESSID=' + sesion})
        with urllib.request.urlopen(req, timeout=20) as r:
            return json.load(r)
    with ThreadPoolExecutor(max_workers=2) as pool:
        respuestas = list(pool.map(enviar, [concurrente, concurrente.upper()]))
    comprobar(all(r.get('ok') for r in respuestas) and respuestas[0]['id'] == respuestas[1]['id']
              and sum(f['nombre'].lower() == concurrente.lower() for f in filas()) == 1,
              'Dos creaciones simultáneas terminan con el mismo id y una sola fila')

    # Provoca un fallo después de preparar el logo: la imagen nueva debe retirarse.
    trigger = 'test_editorial_' + uuid.uuid4().hex
    fallo = nueva()
    cantidad = len(filas()); antes_archivos = set(directorio.iterdir())
    try:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"CREATE TRIGGER " + trigger
            + " BEFORE INSERT ON editoriales FOR EACH ROW BEGIN IF NEW.nombre='" + fallo
            + "' THEN SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1000,MESSAGE_TEXT='Prueba temporal'; END IF; END\");")
        resultado = crear(fallo, imagen)
        comprobar(not resultado.get('ok') and len(filas()) == cantidad and set(directorio.iterdir()) == antes_archivos,
                  'Un fallo SQL retira el logo preparado y no deja una editorial parcial')
    finally:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query('DROP TRIGGER IF EXISTS " + trigger + "');")

    anonimo = page.context.browser.new_context()
    try:
        comprobar(anonimo.request.get(busqueda + '?nombre=Kodansha').status == 403
                  and anonimo.request.post(api, form={'nombre': nueva()}).status == 403,
                  'Búsqueda y creación exigen una sesión de administrador')
    finally:
        anonimo.close()

    # Propuestas controladas para los nombres temporales, manteniendo guardado real.
    manual_nombre = nueva()
    def responder_logo(route):
        n = urllib.parse.parse_qs(urllib.parse.urlsplit(route.request.url).query).get('nombre',[''])[0]
        if n.startswith(PREFIJO):
            route.fulfill(json={'ok':True,'estado':'nueva','nombre':n,'editorial':None,
                'logos':[] if n == manual_nombre else [logo]})
        else:
            route.continue_()
    page.route('**/controlador/buscar_logo_editorial.php?*', responder_logo)
    page.route(logo['url'], lambda route: route.fulfill(body=imagen, content_type='image/png'))
    page.goto(BASE + '/editoriales.php', wait_until='domcontentloaded')
    editorial_web = nueva()
    page.locator('#nombre-nuevo').fill(editorial_web)
    expect(page.locator('#alta-editorial .ed-propuesta')).to_be_enabled()
    antes = len(filas())
    comprobar(len(filas()) == antes and page.locator('#alta-editorial .ed-propuesta img').is_visible(),
              'Buscar muestra la vista previa antes de guardar la editorial')
    page.locator('#alta-editorial .ed-propuesta').click()
    expect(page.locator('#alta-editorial .ed-propuesta')).to_have_attribute('aria-pressed','true')
    page.locator('#alta-editorial button[type="submit"]').click()
    page.wait_for_url('**/editoriales.php?ok=creada')
    comprobar(any(f['nombre'] == editorial_web and f['imagen'] for f in filas()),
              'El catálogo guarda el logo que el administrador seleccionó en la vista previa')

    # Fallo de fuente y subida manual; conservar nombre e imagen tras errores.
    page.locator('#nombre-nuevo').fill(manual_nombre)
    expect(page.locator('#alta-editorial .ed-estado')).to_contain_text('No se encontró')
    page.locator('#logo-nuevo').set_input_files({'name':'otro.png','mimeType':'image/png','buffer':imagen})
    page.locator('#alta-editorial button[type="submit"]').click()
    page.wait_for_url('**/editoriales.php?ok=creada')
    comprobar(any(f['nombre'] == manual_nombre and f['imagen'] for f in filas()),
              'Si no se encuentra logo, la subida manual permite completar la editorial')

    # Crear desde el registro sin perder datos; el valor del select es el nombre.
    page.goto(BASE + '/crud.php', wait_until='domcontentloaded')
    page.route('**/controlador/buscar_metadatos.php?*', lambda r:r.fulfill(json={'ok':False}))
    page.locator('#f-titulo').fill('Cómic manual de prueba sin guardar')
    page.locator('#f-descripcion').fill('Sinopsis escrita antes de crear la editorial.')
    page.locator('#f-anio').fill('2020')
    page.locator('#g-tipo [data-valor="Comic"]').click()
    page.locator('#g-clasificacion [data-valor="+18"]').click()
    page.locator('#f-genero-busca').fill('Aventura')
    page.locator('#registro-crear-editorial').click()
    expect(page.locator('dialog.editorial-dialogo')).to_be_visible()
    registrar = nueva()
    page.locator('#ed-dialogo-nombre').fill(registrar)
    expect(page.locator('dialog .ed-propuesta')).to_be_enabled()
    page.locator('dialog .ed-propuesta').click()
    ruta = Path(tempfile.gettempdir()) / 'comic-house-context' / 'editorial-fuente.png'
    ruta.parent.mkdir(exist_ok=True)
    page.screenshot(path=str(ruta))
    page.locator('dialog button[type="submit"]').click()
    expect(page.locator('dialog.editorial-dialogo')).to_have_count(0)
    comprobar(page.locator('#f-editorial').input_value() == registrar
              and page.locator('#f-titulo').input_value() == 'Cómic manual de prueba sin guardar'
              and page.locator('#f-descripcion').input_value() == 'Sinopsis escrita antes de crear la editorial.'
              and page.locator('#f-clasificacion').input_value() == '+18',
              'Crear desde el registro selecciona el nombre correcto y conserva los campos manuales sin recargar')
    comprobar(page.locator('#btn-registrar').is_enabled(),
              'La creación actualiza la validación y permite registrar el cómic completo')
    page.locator('#registro-crear-editorial').click()
    page.locator('#ed-dialogo-nombre').fill(nueva())
    expect(page.locator('dialog .ed-propuesta')).to_be_enabled()
    page.locator('dialog .ed-propuesta').click()
    with page.expect_file_chooser() as selector:
        page.locator('dialog .ed-descartar').click()
    selector.value.set_files({'name':'manual.png','mimeType':'image/png','buffer':imagen})
    comprobar(page.locator('dialog .ed-propuesta').get_attribute('aria-pressed') == 'false'
              and page.locator('dialog .ed-manual-previa').is_visible(),
              'Descartar una propuesta permite revisar otra imagen subida a mano')
    page.keyboard.press('Escape')
    expect(page.locator('dialog.editorial-dialogo')).to_have_count(0)
    comprobar(page.locator('#f-editorial').input_value() == registrar,
              'Cancelar otra creación no altera la editorial ya seleccionada')

    # HU-A2: editorial real conocida por la fuente, desde el modal de metadatos.
    portada = php("require '/var/www/html/modelo/conexion.php'; echo $conexion->query(\"SELECT portada FROM comics WHERE portada<>'' LIMIT 1\")->fetch_row()[0];")
    candidato = {'titulo':'Obra de prueba sin registrar','anio':2020,'editorial':nueva(),
        'editorial_id':0,'descripcion':'Una aventura de prueba con sinopsis publicada.',
        'estado':'Publicacion Terminada','capitulo_desde':1,'capitulo_hasta':4,
        'portada_url':BASE + '/uploads/' + portada,'fuente':'Fuente controlada'}
    page.unroute('**/controlador/buscar_metadatos.php?*')
    page.route('**/controlador/buscar_metadatos.php?*', lambda r:r.fulfill(json={'ok':True,'datos':{'candidatos':[candidato]}}))
    page.route('**/controlador/buscar_clasificacion.php?*', lambda r:r.fulfill(json={'ok':False}))
    page.route('**/controlador/estimar_clasificacion.php', lambda r:r.fulfill(json={'ok':False}))
    page.route('**/controlador/traducir.php', lambda r:r.fulfill(json={'ok':False}))
    page.route('**/controlador/descargar_portada.php', lambda r:r.fulfill(json={'ok':True,'archivo':portada}))
    altas = []
    page.route('**/crud.php', lambda r:(altas.append(r.request.method),r.abort()) if r.request.method == 'POST' else r.continue_())

    def abrir_candidato():
        page.goto(BASE + '/crud.php', wait_until='domcontentloaded')
        page.locator('#f-titulo').fill(candidato['titulo'])
        page.locator('.meta-item').click()
        expect(page.locator('#modal-meta')).to_have_class('modal-overlay active')
        page.locator('#meta-clasif [data-valor="+18"]').click()
        page.locator('#meta-tipo [data-valor="Comic"]').click()
        page.locator('#meta-genero').select_option(label='Aventura')
        page.locator('#meta-crear-edit').click()
        expect(page.locator('dialog.editorial-dialogo')).to_be_visible()

    abrir_candidato()
    comprobar(page.locator('#ed-dialogo-nombre').input_value() == candidato['editorial']
              and page.locator('#ed-dialogo-nombre').evaluate('(e)=>e.readOnly')
              and page.locator('dialog select').count() == 0,
              'La fuente ofrece crear su editorial real sin un selector de editoriales ajenas')
    page.keyboard.press('Escape')
    expect(page.locator('dialog.editorial-dialogo')).to_have_count(0)
    comprobar(page.locator('#modal-meta').get_attribute('class') == 'modal-overlay active',
              'Cancelar la editorial conserva abierto el candidato de la fuente')
    page.locator('#meta-crear-edit').click()
    expect(page.locator('dialog .ed-propuesta')).to_be_enabled()
    page.locator('dialog .ed-propuesta').click()
    page.locator('dialog button[type="submit"]').click()
    expect(page.locator('dialog.editorial-dialogo')).to_have_count(0)
    comprobar(page.locator('#f-editorial').input_value() == candidato['editorial']
              and page.locator('#meta-crear-edit').count() == 0
              and page.locator('#meta-clasif .activa').get_attribute('data-valor') == '+18'
              and page.locator('#meta-genero').input_value() == 'Aventura',
              'Crear desde la fuente actualiza el candidato y conserva género y clasificación elegidos')
    page.locator('#meta-usar').click()
    expect(page.locator('#btn-registrar')).to_be_enabled()
    comprobar(page.locator('#f-editorial').input_value() == candidato['editorial']
              and page.locator('#f-clasificacion').input_value() == '+18',
              'Confirmar los metadatos mantiene la nueva editorial seleccionada sin recargar')

    # Fuente sin editorial: permite escribirla y crearla sin inventar un logo.
    candidato['editorial'] = ''
    abrir_candidato()
    comprobar(page.locator('#ed-dialogo-nombre').input_value() == ''
              and not page.locator('#ed-dialogo-nombre').evaluate('(e)=>e.readOnly'),
              'Si la fuente no trae editorial, el nombre se puede escribir manualmente')
    sin_fuente = nueva()
    page.locator('#ed-dialogo-nombre').fill(sin_fuente)
    page.locator('dialog button[type="submit"]').click()
    expect(page.locator('dialog.editorial-dialogo')).to_have_count(0)
    comprobar(page.locator('#f-editorial').input_value() == sin_fuente
              and any(f['nombre'] == sin_fuente and not f['imagen'] for f in filas()),
              'Desde el registro se puede añadir el nombre sin logo y completarlo después')
    page.locator('#meta-usar').click()

    # La advertencia parecida es revisable también en la interfaz.
    page.unroute('**/controlador/buscar_logo_editorial.php?*')
    page.locator('#registro-crear-editorial').click()
    page.locator('#ed-dialogo-nombre').fill('DC Comicss')
    expect(page.locator('dialog .ed-parecida')).to_be_visible()
    cantidad = len(filas())
    page.locator('dialog .ed-parecida button').first.click()
    expect(page.locator('dialog .ed-estado')).to_contain_text('ya está registrada')
    page.locator('dialog button[type="submit"]').click()
    expect(page.locator('dialog.editorial-dialogo')).to_have_count(0)
    comprobar(page.locator('#f-editorial').input_value() == 'DC' and len(filas()) == cantidad,
              'Revisar la editorial parecida permite reutilizar la existente sin crear duplicados')
    comprobar(not altas, 'Las pruebas del asistente no registran cómics por accidente')
    page.set_viewport_size({'width':390,'height':844})
    page.locator('#registro-crear-editorial').click()
    comprobar(page.evaluate('document.documentElement.scrollWidth <= innerWidth')
              and page.locator('dialog.editorial-dialogo').bounding_box()['width'] <= 390,
              'El diálogo y sus controles caben en una pantalla móvil')
    ruta = Path(tempfile.gettempdir()) / 'comic-house-context' / 'editorial-movil.png'
    ruta.parent.mkdir(exist_ok=True)
    page.screenshot(path=str(ruta))
    page.keyboard.press('Escape')
    comprobar(not errores_js, 'Sin errores JavaScript: ' + str(errores_js))


if __name__ == '__main__':
    sesion = 'editoriales-test-' + uuid.uuid4().hex
    antes = originales()
    uploads = (Path(__file__).resolve().parents[1] / 'uploads').resolve()
    archivos_antes = set(uploads.iterdir())
    php(f"session_id('{sesion}'); session_start(); $_SESSION=['usuario'=>'Prueba local de editoriales','rol'=>'admin']; session_write_close();")
    try:
        logos = json.loads(php("require '/var/www/html/controlador/editorial_servicio.php'; echo json_encode(ComicHouse\\Editoriales\\buscarLogos('Kodansha'));"))
        comprobar(bool(logos) and all('head_office' not in l['url'] for l in logos),
                  'La fuente real de Kodansha propone logos y no la foto de su sede')
        with sync_playwright() as p:
            candidatos = [os.environ.get('COMIC_HOUSE_BROWSER',''),r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
                r'C:\Program Files\Google\Chrome\Application\chrome.exe']
            ejecutable = next((c for c in candidatos if c and Path(c).is_file()),None)
            browser = p.chromium.launch(headless=True, **({'executable_path':ejecutable} if ejecutable else {}))
            try:
                context = browser.new_context(viewport={'width':1440,'height':1000})
                context.add_cookies([{'name':'PHPSESSID','value':sesion,'url':BASE}])
                probar(context.new_page(), sesion, logos[0])
            finally:
                browser.close()
        comprobar(originales() == antes, 'Las editoriales y cómics del usuario se conservan intactos')
        print(f'\n{CUENTA} comprobaciones de API y navegador correctas.')
    finally:
        temporales = filas()
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"DELETE FROM editoriales WHERE LEFT(nombre,"
            + str(len(PREFIJO)) + ")='" + PREFIJO + "'\");")
        for fila in temporales:
            nombre = fila['imagen']
            if nombre:
                ruta = uploads / nombre
                if ruta not in archivos_antes and ruta.is_file():
                    if ruta.is_symlink() or ruta.resolve().parent != uploads or not nombre.startswith('editorial_'):
                        raise AssertionError('Logo de prueba fuera de uploads/')
                    ruta.unlink()
        php(f"session_id('{sesion}'); session_start(); session_destroy();")
