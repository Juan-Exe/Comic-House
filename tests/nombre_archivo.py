"""Regresión de avisos por nombre: no guarda obras, capítulos ni páginas.

python tests/nombre_archivo.py (Docker, Playwright/Edge y catálogo local).
Los comprimidos son inválidos a propósito: la API real debe rechazarlos antes
de escribir, incluso cuando el nombre corresponde al destino.
"""
import json
import os
from pathlib import Path
import uuid

from playwright.sync_api import sync_playwright, expect
from tomos_ui import BASE, php, elegir_obra

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
        "$conexion->query('SELECT * FROM capitulos ORDER BY id')->fetch_all(MYSQLI_ASSOC),"
        "$conexion->query('SELECT COUNT(*) FROM paginas')->fetch_row()]);")


def probar(page):
    obras = json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query("
        "\"SELECT id,titulo FROM comics WHERE titulo IN ('Invincible','Berserk','Watchmen','Saga')\")->fetch_all(MYSQLI_ASSOC));"))
    ids = {o['titulo']:int(o['id']) for o in obras}
    assert len(ids) == 4
    errores = []
    page.on('pageerror', lambda e:errores.append(str(e)))
    archivo = b'Archivo de prueba invalido; no puede crear paginas.'
    for nombre, destino, aviso in [
        ('Invencible #1-2.cbr','Invincible',False),
        ('Invincible #1-2.cbr','Invincible',False),
        ('ÍNVENCÍBLE #1-2.cbr','Invincible',False),
        ('Invincibl #1.cbz','Invincible',False),
        ('Incincible #1.cbz','Invincible',False),
        ('Xnvinciblx #1.cbz','Invincible',True),
        ('Sago #1.cbz','Saga',True),
        ('Invencible #1-2.cbr','Berserk',True),
        ('Watchmen #1.cbz','Invincible',True),
        ('Watchmen Invencible #1.cbz','Invincible',True),
        ('Comic totalmente diferente #1.cbz','Invincible',True),
        ('cap01.cbz','Invincible',False),
    ]:
        datos = {'titulo':'Prueba de nombre sin guardar','formato':'grapa','numero_desde':'9999',
            'numero_hasta':'','arco':'','fecha_publicacion':'','fecha_publicacion_mes':''}
        revision = page.request.post(BASE + '/controlador/revisar_lote_capitulos.php',data={
            'comic_id':ids[destino],'archivos':[{'id':'prueba','clave':uuid.uuid4().hex,'nombre':nombre,
                'tamano':len(archivo),'datos':datos}]}).json()
        sospecha = any(a['tipo'] == 'posible_obra_distinta' for a in revision['archivos'][0]['avisos'])
        comprobar(sospecha == aviso, 'Revisión del lote: ' + nombre + ' → ' + destino + (' avisa' if aviso else ' sin falso aviso'))
        respuesta = page.request.post(BASE + '/controlador/procesar_capitulo.php',multipart={
            **datos,'comic_id':str(ids[destino]),'confirmar_rango':'1',
            'cbr_file':{'name':nombre,'mimeType':'application/octet-stream','buffer':archivo}}).json()
        comprobar(respuesta.get('error') == ('posible_obra_distinta' if aviso else 'archivo_invalido'),
            'Subida individual: ' + nombre + ' → ' + destino + ' conserva la validación del archivo')
        if nombre == 'Watchmen Invencible #1.cbz':
            comprobar(respuesta.get('obra') == 'Watchmen',
                'La coincidencia exacta con otra obra prevalece sobre una palabra parecida al destino')

    # Reproduce la pantalla reportada con los mismos nombre y destino.
    page.route('**/controlador/buscar_tomos_wiki.php?*',lambda r:r.fulfill(json={'ok':False}))
    page.goto(BASE + '/subir_capitulo.php',wait_until='domcontentloaded')
    elegir_obra(page,ids['Invincible'])
    page.locator('#archivo-cap').set_input_files({'name':'Invencible #1-2.cbr',
        'mimeType':'application/octet-stream','buffer':archivo})
    page.locator('#cap-titulo').fill('Título revisado a mano')
    with page.expect_response('**/controlador/procesar_capitulo.php') as pendiente:
        page.locator('#form-capitulo button[type="submit"]').click()
    comprobar(pendiente.value.json().get('error') == 'archivo_invalido',
        'En la pantalla real Invencible para Invincible llega a validar el CBR sin pedir confirmación de obra')
    expect(page.locator('#modal-obra')).not_to_be_visible()
    comprobar(page.locator('#cap-titulo').input_value() == 'Título revisado a mano'
        and page.locator('#cap-desde').input_value() == '1' and page.locator('#cap-hasta').input_value() == '2',
        'El intento conserva el título manual y el rango 1–2')

    elegir_obra(page,ids['Watchmen'])
    page.locator('#archivo-cap').set_input_files({'name':'Invencible #1-2.cbr',
        'mimeType':'application/octet-stream','buffer':archivo})
    page.locator('#cap-titulo').fill('Otra prueba sin guardar')
    page.locator('#form-capitulo button[type="submit"]').click()
    expect(page.locator('#modal-obra')).to_be_visible()
    comprobar(page.locator('#modal-aviso-titulo').inner_text() == 'Comprueba el cómic',
        'El mismo archivo elegido para Watchmen sí pide comprobar la obra')
    page.locator('#obra-cancelar').click()
    expect(page.locator('#modal-obra')).not_to_be_visible()
    comprobar(not errores,'Sin errores JavaScript: ' + str(errores))


if __name__ == '__main__':
    sesion = 'nombre-archivo-' + uuid.uuid4().hex
    antes = instantanea()
    php(f"session_id('{sesion}'); session_start(); $_SESSION=['usuario'=>'Prueba local de nombres','rol'=>'admin']; session_write_close();")
    try:
        with sync_playwright() as p:
            candidatos = [os.environ.get('COMIC_HOUSE_BROWSER',''),
                r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
                r'C:\Program Files\Google\Chrome\Application\chrome.exe']
            ejecutable = next((c for c in candidatos if c and Path(c).is_file()),None)
            browser = p.chromium.launch(headless=True,**({'executable_path':ejecutable} if ejecutable else {}))
            try:
                context = browser.new_context(viewport={'width':1440,'height':1000})
                context.add_cookies([{'name':'PHPSESSID','value':sesion,'url':BASE}])
                probar(context.new_page())
            finally:
                browser.close()
        comprobar(instantanea() == antes,'Las obras, capítulos y páginas del usuario permanecen intactos')
        print(f'\n{CUENTA} comprobaciones de nombre correctas.')
    finally:
        php(f"session_id('{sesion}'); session_start(); session_destroy();")
