"""Rangos de grapas y números sin título. No envía ni registra archivos.

python tests/rangos_grapas_ui.py (Docker, Playwright y el Invincible registrado).
Consulta su fuente real y comprueba combinaciones con fixtures en memoria.
"""
import json
import os
from pathlib import Path
import tempfile
import uuid

from playwright.sync_api import sync_playwright, expect
from tomos_ui import BASE, php, elegir_obra, abrir_lista, elegir_opcion
from lote_capitulos import revisado, fila

CUENTA = 0


def comprobar(condicion, mensaje):
    global CUENTA
    if not condicion:
        raise AssertionError(mensaje)
    CUENTA += 1
    print('OK:', mensaje, flush=True)


def instantanea():
    return php("require '/var/www/html/modelo/conexion.php'; echo json_encode(["
        "$conexion->query('SELECT * FROM comics ORDER BY id')->fetch_all(MYSQLI_ASSOC),"
        "$conexion->query('SELECT COUNT(*) FROM capitulos')->fetch_row(),"
        "$conexion->query('SELECT COUNT(*) FROM paginas')->fetch_row()]);")


def probar(page):
    errores = []
    page.on('pageerror', lambda e: errores.append(str(e)))
    obra = json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query("
        "\"SELECT id,capitulo_desde,capitulo_hasta FROM comics WHERE titulo='Invincible' AND anio=2003 AND editorial='Image Comics'\")->fetch_assoc());"))
    comprobar(int(obra['capitulo_desde']) == 1 and int(obra['capitulo_hasta']) == 144,
              'El catálogo distingue el último número 144 de las 145 fichas de la fuente')
    candidatos = page.request.get(BASE + '/controlador/buscar_metadatos.php?titulo=Invincible').json()['datos']['candidatos']
    original = next(c for c in candidatos if c.get('recomendado'))
    comprobar(original['titulo'] == 'Invincible' and original['anio'] == 2003 and original['capitulo_hasta'] == 144,
              'El autocompletado de nuevos registros propone el último número y no la cantidad de fichas')
    subidas = []
    page.route('**/controlador/procesar_capitulo.php', lambda r: (subidas.append(r.request.url), r.abort()))
    page.route('**/controlador/get_capitulos.php?comic_id=' + str(obra['id']), lambda r: r.fulfill(json=[]))
    page.goto(BASE + '/subir_capitulo.php', wait_until='domcontentloaded')
    elegir_obra(page, obra['id'])
    page.wait_for_function("__tomosWiki?.edicion?.serie === 'Invincible Vol 1'", timeout=120000)
    abrir_lista(page)
    comprobar(page.evaluate('__tomosWiki.tomos.length') == 144 and page.locator('.fila-tomo').count() == 144,
              'La lista real contiene los 144 números, no solo los 46 que tienen título')
    coleccion = page.evaluate('__tomosWiki.tomos[0].recopilatorio')
    comprobar(coleccion['titulo_original'] == 'Family Matters' and coleccion['desde'] == 1 and coleccion['hasta'] == 4,
              'La fuente real confirma Family Matters como recopilatorio de los números 1–4')
    comprobar(page.locator('.fila-tomo').first.get_attribute('data-d') == '1'
              and coleccion['titulo'] in page.locator('.fila-tomo').first.inner_text()
              and 'Recopilatorio · sin título individual' in page.locator('.fila-tomo').first.inner_text(),
              'La lista empieza en el 1 y distingue el nombre del recopilatorio del título individual')
    page.locator('#archivo-cap').set_input_files({'name': 'Invencible #1-2.cbr',
        'mimeType': 'application/octet-stream', 'buffer': b'archivo sin enviar'})
    comprobar(page.locator('#cap-desde').input_value() == '1' and page.locator('#cap-hasta').input_value() == '2'
              and page.locator('#cap-formato').input_value() == 'rango',
              'El archivo #1-2 conserva sus dos números y no se convierte en un tomo completo')
    comprobar(page.locator('#cap-titulo').input_value() == coleccion['titulo'] + ' · números 1–2'
              and 'Nombre del recopilatorio; no es un título individual' in page.locator('#tomo-pista').inner_text(),
              'Propone el nombre confirmado del recopilatorio e indica su origen sin ampliar el rango')
    comprobar(coleccion['titulo'] == coleccion['titulo_original']
              or ('Original: Family Matters' in page.locator('#tomo-pista').inner_text()
                  and 'traducción orientativa' in page.locator('#tomo-pista').inner_text()),
              'La traducción del nombre se distingue del original publicado')
    comprobar(page.locator('#fecha-hidden').input_value() == '2003-01-22'
              and page.locator('#fecha-mes-hidden').input_value() == ''
              and 'fecha del primero (#1)' in page.locator('#tomo-pista').inner_text(),
              'Propone la fecha real del primer número e indica de cuál procede')
    comprobar(page.locator('.fila-tomo.seleccionada').count() == 2,
              'Los dos números incluidos se marcan en la lista')
    imagen = Path(tempfile.gettempdir()) / 'comic-house-context' / 'invincible-rango.png'
    imagen.parent.mkdir(exist_ok=True)
    page.screenshot(path=str(imagen), full_page=True)
    page.locator('#cap-titulo').fill('Título corregido a mano')
    elegir_opcion(page, '#fecha-precision', 'mes')
    page.locator('#fecha-display').fill('06 / 2002')
    page.locator('#cap-desde').fill('3')
    page.locator('#cap-hasta').fill('4')
    comprobar(page.locator('#cap-titulo').input_value() == 'Título corregido a mano'
              and page.locator('#fecha-mes-hidden').input_value() == '2002-06',
              'Cambiar el rango conserva el título y la fecha escritos a mano')
    page.locator('#selected-bar button').click()
    elegir_obra(page, obra['id'])
    page.wait_for_function("__tomosWiki?.edicion?.serie === 'Invincible Vol 1'")
    abrir_lista(page)
    page.locator('.fila-tomo[data-d="1"]').click()
    comprobar(page.locator('#cap-formato').input_value() == 'grapa'
              and page.locator('#cap-desde').input_value() == '1'
              and page.locator('#cap-hasta').input_value() == ''
              and page.locator('#cap-titulo').input_value() == coleccion['titulo'] + ' · número 1',
              'Un archivo de una sola grapa conserva el número individual')

    # El lote utiliza la misma resolución, incluyendo fechas completas sin duplicar precisión.
    page.locator('#cap-titulo').fill('')
    page.locator('#cap-desde').fill('')
    page.locator('#archivo-cap').set_input_files([
        {'name': 'cap01-02.cbr', 'mimeType': 'application/octet-stream', 'buffer': b'fixture sin subir'},
        {'name': 'cap03-04.cbz', 'mimeType': 'application/octet-stream', 'buffer': b'fixture sin subir'}])
    revisado(page)
    comprobar(coleccion['titulo'] + ' · números 1–2' in fila(page, 'cap01-02.cbr').inner_text()
              and coleccion['titulo'] + ' · números 3–4' in fila(page, 'cap03-04.cbz').inner_text(),
              'La cola reconoce los rangos de uno o varios archivos con la misma regla')
    comprobar('Nombre del recopilatorio' in fila(page, 'cap01-02.cbr').inner_text()
              and 'Fecha del primer número' in fila(page, 'cap01-02.cbr').inner_text(),
              'La cola conserva las aclaraciones del nombre y la fecha propuestos')
    comprobar(page.locator('.lote-estado', has_text='Listo').count() == 2,
              'Las fechas completas se envían como día, sin rellenar a la vez el mes independiente')

    def resolver(tomos, desde, hasta, requiere=False):
        return page.evaluate("""({tomos,desde,hasta,requiere}) => {
            const anterior=__tomosWiki;
            try { __tomosWiki={tomos,requiere_edicion:requiere}; return tomoDe(desde,hasta); }
            finally { __tomosWiki=anterior; }
        }""", {'tomos': tomos, 'desde': desde, 'hasta': hasta, 'requiere': requiere})

    grapas = [{'tomo': n, 'desde': n, 'hasta': n, 'formato': 'grapa', 'titulo': titulo,
               'arco': '', 'fecha': '2003-01-22' if n == 1 else '', 'fecha_mes': '2003-02' if n == 2 else ''}
              for n, titulo in [(1, 'Primera historia'), (2, 'Segunda historia')]]
    propuesta = resolver(grapas, 1, 2)
    comprobar(propuesta['titulo'] == 'Primera historia · Segunda historia' and not propuesta.get('titulo_generado')
              and propuesta['fecha'] == '2003-01-22',
              'Cuando hay títulos reales combina ambos y conserva la fecha del primero')
    grapas[1]['titulo'] = 'Primera historia'
    comprobar(resolver(grapas, 1, 2)['titulo'] == 'Primera historia',
              'Un título común no se repite dos veces en el nombre del archivo')
    sin_titulo = [{**g, 'titulo':'', 'recopilatorio':{'titulo':'Asuntos de familia','desde':1,'hasta':4}} for g in grapas]
    propuesta = resolver(sin_titulo,1,2)
    comprobar(propuesta['titulo'] == 'Asuntos de familia · números 1–2'
              and len(propuesta['recopilatorios_usados']) == 1 and not propuesta.get('titulo_generado'),
              'El recopilatorio común no se repite al combinar números sin título')
    mezcla = [sin_titulo[0], {**grapas[1], 'titulo':'Historia publicada', 'recopilatorio':sin_titulo[1]['recopilatorio']}]
    comprobar('Historia publicada' in resolver(mezcla,1,2)['titulo']
              and resolver(mezcla,2,None)['titulo'] == 'Historia publicada',
              'El título individual conserva su prioridad incluso cuando existe un recopilatorio')
    sin_titulo[1]['recopilatorio'] = {'titulo':'Otra colección','desde':2,'hasta':5}
    comprobar(resolver(sin_titulo,1,2)['titulo'] == 'Asuntos de familia · Otra colección · números 1–2',
              'Un archivo que cruza recopilatorios conserva ambos nombres y su rango propio')
    sin_titulo[1].pop('recopilatorio')
    comprobar(resolver(sin_titulo,1,2).get('titulo_generado'),
              'Si falta el título y el recopilatorio de algún número conserva el nombre descriptivo marcado')
    extensa = [{**g, 'titulo':'', 'recopilatorio':{'titulo':str(g['desde']) * 150,'desde':1,'hasta':4}} for g in grapas]
    comprobar(resolver(extensa,1,2).get('titulo_generado') and not resolver(extensa,1,2).get('recopilatorios_usados'),
              'Un nombre de recopilatorios demasiado largo vuelve a una propuesta descriptiva identificada')
    largas = [{**grapas[0], 'titulo': 'A' * 150}, {**grapas[1], 'titulo': 'B' * 150}]
    propuesta = resolver(largas, 1, 2)
    comprobar(len(propuesta['titulo']) <= 255 and propuesta.get('titulo_generado'),
              'Una combinación demasiado larga propone un nombre marcado sin exceder el límite del título')
    comprobar(resolver(grapas[:1], 1, 2) is None, 'No fabrica una combinación cuando falta un número en la fuente')
    comprobar(resolver(grapas, 2, 1) is None, 'No resuelve un rango invertido')
    comprobar(resolver(grapas, 1, 2, True) is None, 'No mezcla entregas mientras está pendiente elegir la edición')
    tomo = [{'tomo': 5, 'desde': 15, 'hasta': 22, 'formato': 'rango', 'titulo': 'Arco del manga', 'arco': 'Arco del manga'}]
    comprobar(resolver(tomo, 15, 22)['titulo'] == 'Arco del manga'
              and not resolver(tomo, 15, 22).get('combinada'),
              'Los tomos de manga mantienen su título y rango originales')
    comprobar(resolver(tomo, 15, 20) is None,
              'No atribuye el título de un tomo entero a un subconjunto sin verificar')
    page.set_viewport_size({'width': 390, 'height': 844})
    comprobar(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),
              'Las filas nuevas y la leyenda caben en móvil')
    comprobar(not subidas and not errores, 'Sin enviar archivos ni errores JavaScript: ' + str(errores))


if __name__ == '__main__':
    antes = instantanea()
    sesion = 'rangos-test-' + uuid.uuid4().hex
    php(f"session_id('{sesion}'); session_start(); $_SESSION=['usuario'=>'Prueba local de rangos','rol'=>'admin']; session_write_close();")
    try:
        with sync_playwright() as p:
            candidatos = [os.environ.get('COMIC_HOUSE_BROWSER', ''),
                r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
                r'C:\Program Files\Google\Chrome\Application\chrome.exe']
            ejecutable = next((c for c in candidatos if c and Path(c).is_file()), None)
            browser = p.chromium.launch(headless=True, **({'executable_path': ejecutable} if ejecutable else {}))
            try:
                contexto = browser.new_context(viewport={'width': 1440, 'height': 1000}, timezone_id='America/Bogota')
                contexto.add_cookies([{'name': 'PHPSESSID', 'value': sesion, 'url': BASE}])
                probar(contexto.new_page())
            finally:
                browser.close()
        comprobar(instantanea() == antes, 'La prueba no modifica obras, capítulos ni páginas')
        print(f'\n{CUENTA} comprobaciones de rangos correctas.')
    finally:
        php(f"session_id('{sesion}'); session_start(); session_destroy();")
