"""Reemplazos reales con CBZ/CBR y fixtures propios. Docker y Playwright/Edge.

python tests/reemplazo_capitulo.py. Conserva obras/archivos del usuario.
"""
from concurrent.futures import ThreadPoolExecutor
import hashlib
import json
import os
import re
from pathlib import Path
import shutil
import tempfile
import urllib.request
import uuid

from playwright.sync_api import sync_playwright, expect
from tomos_ui import BASE, php
from subida_capitulo import png, cbz, cbr

CUENTA = 0


def comprobar(condicion, texto):
    global CUENTA
    if not condicion:
        raise AssertionError(texto)
    CUENTA += 1
    print('OK:',texto,flush=True)


def originales(comic=0):
    return php("require '/var/www/html/modelo/conexion.php'; echo json_encode(["
        "$conexion->query('SELECT * FROM comics WHERE id<>" + str(comic) + " ORDER BY id')->fetch_all(MYSQLI_ASSOC),"
        "$conexion->query('SELECT * FROM capitulos WHERE comic_id<>" + str(comic) + " ORDER BY id')->fetch_all(MYSQLI_ASSOC),"
        "$conexion->query('SELECT p.* FROM paginas p JOIN capitulos c ON c.id=p.capitulo_id WHERE c.comic_id<>" + str(comic) + " ORDER BY p.id')->fetch_all(MYSQLI_ASSOC)]);")


def probar(page,comic,sesion):
    repo = Path(__file__).resolve().parents[1]
    endpoint = BASE + '/controlador/reemplazar_capitulo.php'
    errores = []
    page.on('pageerror',lambda e:errores.append(str(e)))
    inicial = cbz([('1.png',png()),('2.png',png(2,1))])
    nuevo = cbz([('10.png',png()),('02.png',png(3,1)),('01.png',png())])
    rar = cbr([('001.png',png(4,1))])
    ids = []
    for numero in [1,2]:
        r=page.request.post(BASE + '/controlador/procesar_capitulo.php',multipart={
            'comic_id':str(comic),'titulo':'Capítulo original ' + str(numero),'formato':'grapa',
            'numero_desde':str(numero),'fecha_publicacion_mes':'2024-05','subida_clave':uuid.uuid4().hex,
            'cbr_file':{'name':'cap' + str(numero) + '.cbz','mimeType':'application/zip','buffer':inicial}}).json()
        assert r.get('ok'),r
        ids.append(r['capitulo_id'])
    cap=ids[0]

    def estado(id=cap):
        return json.loads(php("require '/var/www/html/modelo/conexion.php'; require '/var/www/html/controlador/reemplazo_capitulo.php'; echo json_encode(estadoReemplazo($conexion," + str(id) + "));") )

    def archivos():
        return {p.relative_to(repo).as_posix():hashlib.sha256(p.read_bytes()).hexdigest()
            for id in ids for carpeta in (repo/'paginas').glob('cap_' + str(id) + '*')
            if carpeta.is_dir() and re.fullmatch('cap_' + str(id) + r'(?:_r_[a-f0-9]{32})?',carpeta.name)
            for p in carpeta.rglob('*') if p.is_file()}

    page.goto(BASE + '/editar_capitulo.php?id=' + str(cap),wait_until='domcontentloaded')
    csrf=page.locator('#form-reemplazo [name=csrf]').input_value()
    def reemplazar(datos=nuevo,version=None,clave=None,**extra):
        return page.request.post(endpoint,multipart={'capitulo_id':str(cap),'csrf':csrf,
            'confirmar_reemplazo':'1','version':version or estado()['version'],'clave':clave or uuid.uuid4().hex,
            'cbr_file':{'name':'cap01.cbr' if datos == rar else 'cap01.cbz','mimeType':'application/octet-stream','buffer':datos},**extra}).json()

    antes=estado(); otros=estado(ids[1]); ficheros=archivos()
    comprobar(not reemplazar(confirmar_reemplazo='0').get('ok') and estado() == antes,
        'Sin confirmación explícita no se reemplaza el capítulo')
    comprobar(not reemplazar(csrf='incorrecto').get('ok') and estado() == antes,
        'El reemplazo exige el token de la sesión de la página')
    anonimo=page.context.browser.new_context()
    try:
        comprobar(anonimo.request.post(endpoint,form={'confirmar_reemplazo':'1'}).status == 403,
            'El reemplazo exige rol administrador')
    finally:
        anonimo.close()
    for contenido in [b'roto',cbz([('notas.txt',b'sin imagenes')]),cbz([('pagina.png',b'imagen falsa')])]:
        comprobar(reemplazar(contenido).get('error') == 'archivo_invalido' and estado() == antes and archivos() == ficheros,
            'Un archivo inválido conserva filas y bytes de todas las páginas anteriores')

    # Error después del DELETE y al insertar las nuevas: rollback completo.
    trigger='test_reemplazo_' + uuid.uuid4().hex
    try:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"CREATE TRIGGER " + trigger
            + " BEFORE INSERT ON paginas FOR EACH ROW BEGIN IF NEW.capitulo_id=" + str(cap)
            + " THEN SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1000,MESSAGE_TEXT='Prueba temporal'; END IF; END\");")
        comprobar(not reemplazar().get('ok') and estado() == antes and archivos() == ficheros,
            'Un fallo al insertar páginas restaura el capítulo y las páginas antiguas sin dejar archivos nuevos')
    finally:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query('DROP TRIGGER IF EXISTS " + trigger + "');")

    clave=uuid.uuid4().hex
    viejos=','.join(str(p['id']) for p in antes['paginas'])
    try:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"CREATE TRIGGER " + trigger
            + " BEFORE INSERT ON paginas FOR EACH ROW BEGIN IF NEW.capitulo_id=" + str(cap)
            + " AND EXISTS(SELECT 1 FROM paginas WHERE id IN (" + viejos + ")) THEN SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1000,MESSAGE_TEXT='No se retiraron todas'; END IF; END\");")
        r=reemplazar(version=antes['version'],clave=clave)
        comprobar(r.get('ok') and r['capitulo_id'] == cap and r['paginas'] == 3,
            'El CBZ reemplaza el mismo capítulo después de retirar todas las filas anteriores')
    finally:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query('DROP TRIGGER IF EXISTS " + trigger + "');")
    despues=estado()
    comprobar(len(despues['paginas']) == 3 and [int(p['orden']) for p in despues['paginas']] == [1,2,3]
        and [int(p['es_doble']) for p in despues['paginas']] == [0,1,0],
        'Las nuevas páginas conservan el orden natural y las páginas dobles sin mezclar ediciones')
    campos=['id','comic_id','titulo','numero','formato','numero_desde','numero_hasta','arco','fecha_publicacion','fecha_publicacion_mes']
    comprobar(all(antes['capitulo'][c] == despues['capitulo'][c] for c in campos)
        and despues['capitulo']['subida_clave'] is None,
        'Se conserva identidad y metadatos del capítulo y se invalida el recibo de su archivo anterior')
    comprobar(all(not (repo/p['imagen_url']).exists() for p in antes['paginas'])
        and all(page.request.get(BASE + '/' + p['imagen_url']).ok for p in despues['paginas']),
        'Los archivos anteriores se retiran y los nuevos se pueden leer por HTTP')
    comprobar(estado(ids[1]) == otros,'Otro capítulo del mismo cómic conserva sus páginas y datos')
    comprobar(reemplazar(version=antes['version'],clave=clave).get('ya_reemplazado') and estado() == despues,
        'Repetir el intento tras una respuesta perdida devuelve el recibo sin cambiar páginas ni ids')
    comprobar(reemplazar(rar,version=antes['version'],clave=clave).get('error') == 'conflicto' and estado() == despues,
        'La misma clave con otro archivo se rechaza')
    comprobar(reemplazar(version=antes['version']).get('error') == 'conflicto' and estado() == despues,
        'Una pestaña con la versión anterior no puede sobrescribir un reemplazo reciente')
    comprobar(reemplazar(version=antes['version'],clave=clave,capitulo_id=str(ids[1])).get('error') == 'conflicto'
        and estado(ids[1]) == otros, 'Un recibo no se puede reutilizar para reemplazar otro capítulo')
    r=reemplazar(rar)
    comprobar(r.get('ok') and len(estado()['paginas']) == 1 and int(estado()['paginas'][0]['es_doble']) == 1,
        'Un CBR real sustituye un CBZ y retira las páginas sobrantes')
    comprobar(reemplazar(version=antes['version'],clave=clave).get('error') == 'conflicto',
        'Un recibo antiguo no vuelve a publicar una edición que ya fue reemplazada después')
    anterior=estado()
    php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"UPDATE capitulos SET titulo='Título guardado después' WHERE id=" + str(cap) + "\");")
    actualizado=estado()
    comprobar(reemplazar(version=anterior['version']).get('error') == 'conflicto' and estado() == actualizado,
        'Cambiar los metadatos también invalida un reemplazo preparado desde una pestaña antigua')

    # Dos clientes compiten por la misma revisión; también se prueba el mismo intento.
    def concurrente(clave,version):
        boundary='reemplazo' + uuid.uuid4().hex
        partes=[]
        for k,v in {'capitulo_id':str(cap),'version':version,'clave':clave,'csrf':csrf,'confirmar_reemplazo':'1'}.items():
            partes.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+v+'\r\n').encode())
        partes.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="cbr_file"; filename="cap01.cbz"\r\nContent-Type: application/zip\r\n\r\n').encode()+nuevo+b'\r\n')
        partes.append(('--'+boundary+'--\r\n').encode())
        req=urllib.request.Request(endpoint,data=b''.join(partes),headers={'Cookie':'PHPSESSID='+sesion,
            'Content-Type':'multipart/form-data; boundary='+boundary})
        with urllib.request.urlopen(req,timeout=30) as res:
            return json.load(res)
    version=estado()['version']; clave=uuid.uuid4().hex
    with ThreadPoolExecutor(max_workers=2) as pool:
        respuestas=list(pool.map(lambda _:concurrente(clave,version),range(2)))
    comprobar(all(r.get('ok') for r in respuestas) and sum(bool(r.get('ya_reemplazado')) for r in respuestas) == 1,
        'Dos peticiones simultáneas con la misma clave publican una sola edición')
    version=estado()['version']
    with ThreadPoolExecutor(max_workers=2) as pool:
        respuestas=list(pool.map(lambda _:concurrente(uuid.uuid4().hex,version),range(2)))
    comprobar(sum(bool(r.get('ok')) for r in respuestas) == 1 and sum(r.get('error') == 'conflicto' for r in respuestas) == 1,
        'Dos reemplazos distintos de la misma revisión no se pisan: uno se completa y el otro debe revisar')

    # Fallo al guardar el recibo: también revierte las nuevas páginas y el hash.
    antes=estado(); ficheros=archivos()
    try:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"CREATE TRIGGER " + trigger
            + " BEFORE INSERT ON reemplazos_capitulo FOR EACH ROW BEGIN IF NEW.capitulo_id=" + str(cap)
            + " THEN SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1000,MESSAGE_TEXT='Prueba recibo'; END IF; END\");")
        comprobar(not reemplazar().get('ok') and estado() == antes and archivos() == ficheros,
            'Un fallo al guardar el recibo también restaura la edición anterior completa')
    finally:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query('DROP TRIGGER IF EXISTS " + trigger + "');")

    # Nombres de otra obra conservan la confirmación expresa de HU-B3.
    antes=estado()
    sospechoso={'name':'Watchmen.cbz','mimeType':'application/zip','buffer':nuevo}
    comprobar(reemplazar(cbr_file=sospechoso).get('error') == 'posible_obra_distinta' and estado() == antes,
        'Reemplazar un archivo cuyo nombre menciona otra obra conserva el aviso de identidad')
    comprobar(reemplazar(cbr_file=sospechoso,confirmar_obra='1').get('ok'),
        'El administrador puede confirmar que el nombre del archivo es legítimo')
    # No se retira un archivo que aún referencia otro capítulo.
    compartida=estado()['paginas'][0]['imagen_url']
    php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"INSERT INTO paginas(capitulo_id,imagen_url,orden,es_doble) VALUES("
        + str(ids[1]) + ",'" + compartida + "',3,0)\");")
    comprobar(reemplazar(rar).get('ok') and (repo/compartida).is_file()
        and page.request.get(BASE + '/' + compartida).ok,
        'La limpieza conserva las imágenes que todavía usa otro capítulo')
    comprobar(php("require '/var/www/html/modelo/conexion.php'; require '/var/www/html/controlador/reemplazo_capitulo.php'; "
        "$r=['id'=>0,'pendientes'=>json_encode(['paginas/../../archivo-ajeno.png'])]; echo limpiarReemplazo($conexion,$r)?'incorrecto':'rechazado';") == 'rechazado',
        'La limpieza rechaza rutas que salen de paginas/')
    antes=estado()

    # Interfaz real: revisar, cancelar y confirmar; no envía metadatos sin guardar.
    page.goto(BASE + '/editar_capitulo.php?id=' + str(cap),wait_until='domcontentloaded')
    page.locator('input[name=titulo]').fill('Cambio manual sin guardar')
    page.locator('#archivo-reemplazo').set_input_files({'name':'cap01.cbr','mimeType':'application/octet-stream','buffer':rar})
    page.locator('#form-reemplazo button[type=submit]').click()
    expect(page.locator('#dialogo-reemplazo')).to_be_visible()
    comprobar('cap01.cbr' in page.locator('#reemplazo-resumen').inner_text()
        and 'sustituirán' in page.locator('#reemplazo-resumen').inner_text(),
        'La confirmación identifica el capítulo, el archivo nuevo y las páginas que se sustituyen')
    page.locator('#cancelar-reemplazo').click()
    comprobar(estado() == antes and page.locator('input[name=titulo]').input_value() == 'Cambio manual sin guardar',
        'Cancelar no cambia archivos ni pierde los cambios manuales del formulario')
    page.locator('#archivo-reemplazo').set_input_files(sospechoso)
    page.locator('#form-reemplazo button[type=submit]').click()
    page.locator('#confirmar-reemplazo').click()
    expect(page.locator('#reemplazo-resumen')).to_contain_text('sospecha por el nombre')
    expect(page.locator('#dialogo-reemplazo')).to_be_visible()
    comprobar(estado() == antes,'La interfaz pide confirmar un nombre de otra obra antes de reemplazar páginas')
    page.locator('#cancelar-reemplazo').click()
    comprobar(estado() == antes and page.locator('#archivo-reemplazo').evaluate('(e)=>e.files[0].name') == 'Watchmen.cbz',
        'Cancelar la sospecha conserva el archivo elegido y la edición guardada')
    page.locator('#archivo-reemplazo').set_input_files({'name':'cap01.cbr','mimeType':'application/octet-stream','buffer':rar})
    page.set_viewport_size({'width':390,'height':844})
    page.locator('#form-reemplazo button[type=submit]').click()
    comprobar(page.locator('#dialogo-reemplazo').bounding_box()['width'] <= 390
        and page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'La sección y la confirmación caben en móvil')
    foto=Path(tempfile.gettempdir())/'comic-house-context'/'reemplazo-movil.png'
    foto.parent.mkdir(exist_ok=True); page.screenshot(path=str(foto))
    # La primera respuesta se pierde después de que el servidor confirma el cambio.
    perdidas=[]
    def perder_primera(route):
        respuesta=route.fetch()
        if not perdidas:
            perdidas.append(respuesta.json())
            route.abort()
        else:
            route.fulfill(response=respuesta)
    page.route('**/controlador/reemplazar_capitulo.php',perder_primera)
    page.locator('#confirmar-reemplazo').click()
    expect(page.locator('#reemplazo-estado')).to_contain_text('No se recibió la respuesta')
    tras_perdida=estado()
    comprobar(perdidas[0].get('ok') and len(tras_perdida['paginas']) == 1,
        'Una respuesta perdida deja el reemplazo completo y permite reintentar desde la misma pantalla')
    page.locator('#form-reemplazo button[type=submit]').click()
    page.locator('#confirmar-reemplazo').click()
    page.wait_for_url('**/editar_capitulo.php?id=*&reemplazado=1')
    comprobar(estado() == tras_perdida,'El reintento de la interfaz recupera el recibo sin volver a reemplazar páginas')
    comprobar(len(estado()['paginas']) == 1 and estado()['capitulo']['titulo'] == antes['capitulo']['titulo'],
        'Confirmar reemplaza las páginas conservando los metadatos guardados')
    comprobar(not errores,'Sin errores JavaScript: ' + str(errores))
    comprobar(php("require '/var/www/html/modelo/conexion.php'; echo $conexion->query('SELECT COUNT(*) FROM reemplazos_capitulo WHERE capitulo_id="
        + str(cap) + " AND pendientes IS NOT NULL')->fetch_row()[0];") == '0', 'No quedan archivos pendientes de retirar tras los reemplazos')


if __name__ == '__main__':
    sesion='reemplazo-test-'+uuid.uuid4().hex
    titulo='ZZZ_REEMPLAZO_'+uuid.uuid4().hex
    antes=originales()
    repo=Path(__file__).resolve().parents[1]; raiz=(repo/'paginas').resolve()
    carpetas_antes=set(raiz.iterdir()); comic=None; capitulos=[]
    php(f"session_id('{sesion}'); session_start(); $_SESSION=['usuario'=>'Prueba local de reemplazo','rol'=>'admin']; session_write_close();")
    try:
        comic=int(php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"INSERT INTO comics(titulo,clasificacion,descripcion,capitulos,anio,genero,portada,editorial,editorial_id,tipo,estado_publicacion,capitulo_desde,capitulo_hasta) SELECT '"
            + titulo + "',clasificacion,'Prueba temporal','1 - 12',2024,genero,portada,editorial,editorial_id,'Comic','Publicacion Terminada',1,12 FROM comics ORDER BY id LIMIT 1\"); echo $conexion->insert_id;"))
        with sync_playwright() as p:
            candidatos=[os.environ.get('COMIC_HOUSE_BROWSER',''),r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',r'C:\Program Files\Google\Chrome\Application\chrome.exe']
            ejecutable=next((c for c in candidatos if c and Path(c).is_file()),None)
            browser=p.chromium.launch(headless=True,**({'executable_path':ejecutable} if ejecutable else {}))
            try:
                context=browser.new_context(viewport={'width':1440,'height':1000})
                context.add_cookies([{'name':'PHPSESSID','value':sesion,'url':BASE}])
                probar(context.new_page(),comic,sesion)
            finally:
                browser.close()
        comprobar(originales(comic) == antes,'Todas las obras, capítulos y páginas del usuario permanecen intactos')
        print(f'\n{CUENTA} comprobaciones de reemplazo correctas.')
    finally:
        if comic:
            capitulos=json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query('SELECT id FROM capitulos WHERE comic_id=" + str(comic) + "')->fetch_all(MYSQLI_ASSOC));"))
            php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"DELETE FROM comics WHERE id=" + str(comic) + " AND titulo='" + titulo + "'\");")
            for fila in capitulos:
                for carpeta in raiz.glob('cap_' + str(fila['id']) + '*'):
                    if carpeta not in carpetas_antes and re.fullmatch('cap_' + str(fila['id']) + r'(?:_r_[a-f0-9]{32})?',carpeta.name):
                        if carpeta.is_symlink() or carpeta.resolve().parent != raiz:
                            raise AssertionError('Carpeta de prueba fuera de paginas/')
                        shutil.rmtree(carpeta)
        php(f"session_id('{sesion}'); session_start(); session_destroy();")
