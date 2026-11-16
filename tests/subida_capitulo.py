"""Sprint 4: subidas reales con ZIP/RAR mínimos y registros temporales.

Ejecutar: python tests/subida_capitulo.py (Docker y Playwright).
Incluye un trigger temporal, limitado al cómic de prueba, para provocar rollback.
"""
import base64
import io
import json
import os
from pathlib import Path
import shutil
import struct
import uuid
import zipfile
import zlib

from playwright.sync_api import sync_playwright, expect
from tomos_ui import BASE, php, elegir_obra

CUENTA = 0


def comprobar(condicion, mensaje):
    global CUENTA
    if not condicion:
        raise AssertionError(mensaje)
    CUENTA += 1
    print('OK:', mensaje, flush=True)


def png(ancho=1, alto=1):
    def chunk(tipo, contenido):
        return struct.pack('>I', len(contenido)) + tipo + contenido + struct.pack('>I', zlib.crc32(tipo + contenido))
    return (b'\x89PNG\r\n\x1a\n'
            + chunk(b'IHDR', struct.pack('>IIBBBBB', ancho, alto, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress((b'\0' + b'\xff\0\0' * ancho) * alto)) + chunk(b'IEND', b''))


def cbz(entradas):
    salida = io.BytesIO()
    with zipfile.ZipFile(salida, 'w', compression=zipfile.ZIP_STORED) as archivo:
        for nombre, contenido in entradas:
            archivo.writestr(nombre, contenido)
    return salida.getvalue()


def cbr(entradas):
    # RAR 4, método STORE (0x30), cabeceras y datos con sus CRC reales.
    def cabecera(tipo, flags, cuerpo):
        datos = struct.pack('<BHH', tipo, flags, 7 + len(cuerpo)) + cuerpo
        return struct.pack('<H', zlib.crc32(datos) & 0xffff) + datos
    salida = b'Rar!\x1a\x07\x00' + cabecera(0x73, 0, b'\0' * 6)
    for nombre, contenido in entradas:
        nombre = nombre.encode('ascii')
        cuerpo = struct.pack('<IIBIIBBHI', len(contenido), len(contenido), 3,
                             zlib.crc32(contenido), 0, 20, 0x30, len(nombre), 0o100644) + nombre
        salida += cabecera(0x74, 0x8000, cuerpo) + contenido
    return salida + cabecera(0x7b, 0, b'')


def probar(page, comic):
    endpoint = BASE + '/controlador/procesar_capitulo.php'
    paginas = (Path(__file__).resolve().parents[1] / 'paginas').resolve()
    iniciales = set(paginas.iterdir())
    temporales = php("echo json_encode(glob(sys_get_temp_dir().'/comic-capitulo-*')); ")

    def estado():
        return json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query("
            "'SELECT c.numero_desde, p.imagen_url, p.orden, p.es_doble FROM capitulos c LEFT JOIN paginas p ON p.capitulo_id=c.id "
            f"WHERE c.comic_id={comic} ORDER BY c.numero_desde,p.orden')->fetch_all(MYSQLI_ASSOC));"))

    def subir(datos, nombre='cap01.cbz', numero=1, **campos):
        return page.request.post(endpoint, multipart={'comic_id': str(comic), 'titulo': 'Capítulo de prueba',
            'formato': 'grapa', 'numero_desde': str(numero), **campos,
            'cbr_file': {'name': nombre, 'mimeType': 'application/octet-stream', 'buffer': datos}}).json()

    imagen = png()
    buenos = cbz([('sub/pag10.PNG', imagen), ('./sub/pag2.png', png(2, 1)), ('carpeta.png/', b''), ('ComicInfo.xml', b'<ComicInfo/>'),
                   ('__MACOSX/._001.png', b'metadata')])
    casos = [
        ('texto con extensión CBZ', b'no es un zip', 'cap01.cbz'),
        ('extensión no admitida', buenos, 'cap01.zip'),
        ('ZIP renombrado como CBR', buenos, 'cap01.cbr'),
        ('ZIP truncado', buenos[:-15], 'cap01.cbz'),
        ('ZIP sin imágenes', cbz([('ComicInfo.xml', b'<x/>')]), 'cap01.cbz'),
        ('ZIP vacío', cbz([]), 'cap01.cbz'),
        ('página que no es imagen', cbz([('001.jpg', b'texto')]), 'cap01.cbz'),
        ('imagen con cabecera pero sin datos válidos', cbz([('001.png', base64.b64decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))]), 'cap01.cbz'),
        ('una página válida y otra dañada', cbz([('001.png', imagen), ('002.png', b'mal')]), 'cap01.cbz'),
        ('RAR truncado', cbr([('001.png', imagen)])[:40], 'cap01.cbr'),
        ('RAR sin imágenes', cbr([('nota.txt', b'texto')]), 'cap01.cbr'),
        ('RAR con imagen falsa', cbr([('001.png', b'falso')]), 'cap01.cbr'),
    ]
    # La estructura ZIP sigue íntegra pero el CRC de los datos ya no coincide.
    corrupto = bytearray(cbz([('001.png', imagen)]))
    corrupto[corrupto.index(imagen) + 45] ^= 0xff
    casos.append(('CRC de ZIP incorrecto', bytes(corrupto), 'cap01.cbz'))
    rar_crc = bytearray(cbr([('001.png', imagen)]))
    # Corrompe el CRC declarado de la imagen, conservando el CRC de la cabecera.
    inicio_cabecera = 20
    rar_crc[inicio_cabecera + 16] ^= 0xff
    largo = struct.unpack_from('<H', rar_crc, inicio_cabecera + 5)[0]
    struct.pack_into('<H', rar_crc, inicio_cabecera,
                     zlib.crc32(rar_crc[inicio_cabecera + 2:inicio_cabecera + largo]) & 0xffff)
    casos.append(('CRC de RAR incorrecto con imagen válida', bytes(rar_crc), 'cap01.cbr'))
    for descripcion, contenido, nombre in casos:
        respuesta = subir(contenido, nombre)
        comprobar(respuesta.get('error') == 'archivo_invalido' and bool(respuesta.get('mensaje')),
                  'Rechaza ' + descripcion + ' con explicación')
        comprobar(not estado() and set(paginas.iterdir()) == iniciales,
                  'Sin registros ni carpetas tras ' + descripcion)

    # Orden natural del nombre original, carpetas, extensiones mayúsculas y página doble.
    respuesta = subir(buenos)
    filas = estado()
    comprobar(respuesta.get('ok') and respuesta['paginas'] == 2 and len(filas) == 2,
              'CBZ válido publica solo sus dos imágenes')
    comprobar([int(f['es_doble']) for f in filas] == [1, 0] and [int(f['orden']) for f in filas] == [1, 2],
              'Conserva orden natural y detección de páginas dobles')
    comprobar(all(page.request.get(BASE + '/' + f['imagen_url']).ok for f in filas),
              'Las páginas guardadas se pueden leer por HTTP')
    antes = estado()
    comprobar(not subir(buenos).get('ok') and estado() == antes,
              'Reintentar un capítulo completo no duplica ni reemplaza sus páginas')
    rar = cbr([('sub/010.png', png(2, 1)), ('sub/002.png', imagen)])
    respuesta = subir(rar, 'cap02.cbr', 2)
    comprobar(respuesta.get('ok') and respuesta['paginas'] == 2,
              'CBR RAR real se extrae y publica con dos páginas')
    comprobar([int(f['es_doble']) for f in estado() if int(f['numero_desde']) == 2] == [0, 1],
              'El CBR conserva el orden natural de las páginas')

    antes = estado()
    carpetas = set(paginas.iterdir())
    aviso = subir(buenos, numero=3)
    comprobar(aviso.get('error') == 'fuera_rango' and '1–2' in aviso['mensaje'] and estado() == antes,
              'Fuera del rango avisa antes de crear registros')
    comprobar(subir(buenos, numero=3, confirmar_rango='0').get('error') == 'fuera_rango',
              'Una confirmación falsa no permite continuar')
    comprobar(subir(b'roto', numero=3, confirmar_rango='1').get('error') == 'archivo_invalido'
              and estado() == antes and set(paginas.iterdir()) == carpetas,
              'Confirmar el rango no omite la validación del archivo')

    # UI real: revisión sin perder los datos; confirmaciones de rango y de nombre consecutivas.
    page.route('**/controlador/buscar_tomos_wiki.php?*', lambda route: route.fulfill(
        json={'ok': False, 'error': 'sin_fuente'}))
    page.goto(BASE + '/subir_capitulo.php', wait_until='domcontentloaded')
    elegir_obra(page, comic)
    page.locator('[name="titulo"]').fill('Prueba de confirmaciones')
    page.locator('[name="numero_desde"]').fill('3')
    page.locator('[name="cbr_file"]').set_input_files({'name': 'Watchmen.cbz',
        'mimeType': 'application/zip', 'buffer': buenos})
    page.locator('#form-capitulo button[type="submit"]').click()
    expect(page.locator('#modal-obra')).to_be_visible()
    comprobar('numeración' in page.locator('#modal-aviso-titulo').inner_text(),
              'El modal de rango tiene texto específico y el progreso está oculto')
    expect(page.locator('#progress-overlay')).not_to_be_visible()
    page.keyboard.press('Escape')
    expect(page.locator('#modal-obra')).not_to_be_visible()
    comprobar(estado() == antes and page.locator('[name="numero_desde"]').input_value() == '3'
              and page.locator('[name="cbr_file"]').evaluate('(e)=>e.files.length') == 1,
              'Cancelar conserva el formulario y no sube el capítulo')
    page.locator('#form-capitulo button[type="submit"]').click()
    expect(page.locator('#modal-obra')).to_be_visible()
    page.locator('#obra-seguir').click()
    expect(page.locator('#modal-aviso-titulo')).to_have_text('Comprueba el cómic')
    page.locator('#obra-seguir').click()
    page.wait_for_url('**/subir_capitulo.php?comic_id=*')
    comprobar(any(int(f['numero_desde']) == 3 for f in estado()),
              'Las dos confirmaciones permiten subir sin repetir el aviso anterior')

    # Fallo después de extraer y crear la carpeta: también debe revertir el capítulo.
    trigger = 'test_subida_' + uuid.uuid4().hex
    antes = estado()
    carpetas = set(paginas.iterdir())
    try:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"CREATE TRIGGER " + trigger
            + " BEFORE INSERT ON paginas FOR EACH ROW BEGIN IF (SELECT comic_id FROM capitulos WHERE id=NEW.capitulo_id)="
            + str(comic) + " THEN SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO=1000, MESSAGE_TEXT='Fallo temporal de prueba'; END IF; END\");")
        respuesta = subir(buenos, numero=4, confirmar_rango='1')
        comprobar(respuesta.get('error') == 'fallo_insercion_paginas' and estado() == antes
                  and set(paginas.iterdir()) == carpetas,
                  'Un fallo al insertar páginas revierte capítulo, páginas y carpeta')
    finally:
        php("require '/var/www/html/modelo/conexion.php'; $conexion->query('DROP TRIGGER IF EXISTS " + trigger + "');")
    comprobar(subir(buenos, numero=4, confirmar_rango='1').get('ok'),
              'Se puede reintentar tras el fallo sin dejar duplicados')
    # Un rango abierto no significa que la obra solo publique el capítulo 1.
    php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"UPDATE comics SET capitulos='1 - en emisión',estado_publicacion='En publicacion',capitulo_hasta=NULL WHERE id=" + str(comic) + "\");")
    comprobar(subir(b'roto', numero=5).get('error') == 'archivo_invalido',
              'Un rango abierto no inventa un límite publicado')
    php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"UPDATE comics SET capitulos='10 - 12',capitulo_desde=10,capitulo_hasta=12 WHERE id=" + str(comic) + "\");")
    comprobar(subir(buenos, numero=5).get('error') == 'fuera_rango',
              'También avisa cuando el capítulo está por debajo del inicio publicado')
    comprobar(php("echo json_encode(glob(sys_get_temp_dir().'/comic-capitulo-*')); ") == temporales,
              'No quedan directorios temporales después de éxitos y rechazos')


if __name__ == '__main__':
    sesion = 'subida-test-' + uuid.uuid4().hex
    titulo = 'ZZZ_SUBIDA_TEST_' + uuid.uuid4().hex
    raiz = (Path(__file__).resolve().parents[1] / 'paginas').resolve()
    originales = set(raiz.iterdir())
    php(f"session_id('{sesion}'); session_start(); $_SESSION=['usuario'=>'Prueba local de subida','rol'=>'admin']; session_write_close();")
    comic = None
    try:
        comic = int(php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"INSERT INTO comics "
            "(titulo,clasificacion,descripcion,capitulos,anio,genero,portada,editorial,editorial_id,tipo,estado_publicacion,capitulo_desde,capitulo_hasta) "
            "SELECT '" + titulo + "',clasificacion,'Prueba temporal','1 - 2',2025,genero,portada,editorial,editorial_id,'Comic','Publicacion Terminada',1,2 "
            "FROM comics ORDER BY id LIMIT 1\"); echo $conexion->insert_id;"))
        with sync_playwright() as p:
            candidatos = [os.environ.get('COMIC_HOUSE_BROWSER', ''),
                r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
                r'C:\Program Files\Google\Chrome\Application\chrome.exe']
            ejecutable = next((c for c in candidatos if c and Path(c).is_file()), None)
            browser = p.chromium.launch(headless=True, **({'executable_path': ejecutable} if ejecutable else {}))
            try:
                context = browser.new_context(viewport={'width': 1440, 'height': 1000})
                context.add_cookies([{'name': 'PHPSESSID', 'value': sesion, 'url': BASE}])
                probar(context.new_page(), comic)
            finally:
                browser.close()
        print(f'\n{CUENTA} comprobaciones de subida correctas.')
    finally:
        if comic:
            ids = json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query('SELECT id FROM capitulos WHERE comic_id=" + str(comic) + "')->fetch_all(MYSQLI_ASSOC));"))
            php("require '/var/www/html/modelo/conexion.php'; $conexion->query(\"DELETE FROM comics WHERE id=" + str(comic) + " AND titulo='" + titulo + "'\");")
            for fila in ids:
                carpeta = raiz / ('cap_' + str(int(fila['id'])))
                if carpeta.exists() and carpeta not in originales:
                    if carpeta.is_symlink() or carpeta.resolve().parent != raiz:
                        raise AssertionError('Carpeta de prueba fuera de paginas/')
                    shutil.rmtree(carpeta)
        php(f"session_id('{sesion}'); session_start(); session_destroy();")
