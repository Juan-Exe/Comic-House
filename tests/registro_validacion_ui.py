"""Reproduce el bloqueo tras «Usar estos datos», sin registrar obras ni descargar portadas.

python tests/registro_validacion_ui.py (Docker y Playwright/Edge).
Las respuestas de fuentes se simulan; el formulario y su validación son los reales.
"""
import json
import os
from pathlib import Path
import uuid

from playwright.sync_api import sync_playwright, expect
from tomos_ui import BASE, php

CUENTA = 0


def comprobar(condicion, mensaje):
    global CUENTA
    if not condicion:
        raise AssertionError(mensaje)
    CUENTA += 1
    print('OK:', mensaje, flush=True)


def estado_bd():
    return php("require '/var/www/html/modelo/conexion.php'; echo json_encode($conexion->query("
        "'SELECT (SELECT COUNT(*) FROM comics) comics,(SELECT COUNT(*) FROM capitulos) capitulos,"
        "(SELECT COUNT(*) FROM paginas) paginas')->fetch_assoc());")


def probar(page):
    errores = []
    page.on('pageerror', lambda e: errores.append(str(e)))
    referencia = json.loads(php("require '/var/www/html/modelo/conexion.php'; echo json_encode(['editorial'=>"
        "$conexion->query(\"SELECT id,nombre FROM editoriales WHERE nombre='Image Comics'\")->fetch_assoc(),"
        "'portada'=>$conexion->query(\"SELECT portada FROM comics WHERE portada<>'' LIMIT 1\")->fetch_assoc()['portada']]);"))
    candidato = {'titulo': 'Invincible', 'anio': 2003, 'editorial': referencia['editorial']['nombre'],
        'editorial_id': int(referencia['editorial']['id']), 'editorial_valor': referencia['editorial']['nombre'],
        'descripcion': 'Un joven superhéroe descubre sus poderes y aprende a proteger a los demás.',
        'estado': 'Publicacion Terminada', 'capitulo_desde': 1, 'capitulo_hasta': 145,
        'portada_url': BASE + '/uploads/' + referencia['portada'], 'fuente': 'Fuente de prueba'}
    page.route('**/controlador/buscar_metadatos.php?*', lambda r: r.fulfill(
        json={'ok': True, 'datos': {'candidatos': [candidato]}}))
    page.route('**/controlador/buscar_clasificacion.php?*', lambda r: r.fulfill(
        json={'ok': True, 'datos': {'clasificacion': '+16', 'genero': 'Superhéroes',
            'verificado_por': 'Fuente de prueba'}}))
    page.route('**/controlador/estimar_clasificacion.php', lambda r: r.fulfill(json={'ok': False}))
    page.route('**/controlador/traducir.php', lambda r: r.fulfill(json={'ok': False}))
    page.route('**/controlador/descargar_portada.php', lambda r: r.fulfill(
        json={'ok': True, 'archivo': referencia['portada']}))
    # Una prueba fallida tampoco puede enviar un alta por accidente.
    altas = []
    page.route('**/crud.php', lambda r: (altas.append(r.request.method), r.abort())
        if r.request.method == 'POST' else r.continue_())

    def confirmar():
        page.goto(BASE + '/crud.php', wait_until='domcontentloaded')
        page.locator('#f-titulo').fill('Invincible')
        page.locator('.meta-item').click()
        expect(page.locator('#modal-meta')).to_have_class('modal-overlay active')
        expect(page.locator('#meta-clasif .meta-op.activa')).to_have_attribute('data-valor', '+16')
        page.locator('#meta-usar').click()
        expect(page.locator('#modal-meta')).not_to_have_class('modal-overlay active')

    confirmar()
    comprobar(page.locator('#f-editorial').input_value() == 'Image Comics'
              and page.locator('#f-clasificacion').input_value() == '+16'
              and page.locator('#f-tipo').input_value() == 'Comic'
              and page.locator('#f-genero-busca').input_value() == 'Superhéroes'
              and page.locator('#f-anio').input_value() == '2003'
              and bool(page.locator('#f-descripcion').input_value()),
              'Confirmar el candidato rellena los siete campos obligatorios del formulario')
    try:
        expect(page.locator('#btn-registrar')).to_be_enabled(timeout=1500)
    except AssertionError:
        print('Aviso mostrado:', page.locator('#aviso-faltan').inner_text(), flush=True)
        raise
    comprobar(page.locator('#aviso-faltan').is_hidden(),
              'El aviso desaparece inmediatamente sin tocar otro campo después del modal')
    comprobar('campo-falta' not in (page.locator('#f-editorial').get_attribute('class') or ''),
              'La editorial elegida deja de estar marcada como vacía')
    datos = page.locator('#form-registro').evaluate('(f)=>Object.fromEntries(new FormData(f))')
    comprobar(datos['editorial'] == 'Image Comics' and datos['clasificacion'] == '+16'
              and datos['tipo'] == 'Comic' and datos['anio'] == '2003'
              and datos['portada_descargada'] == referencia['portada'],
              'Los valores confirmados también están presentes en los datos que se enviarían')
    page.locator('#f-descripcion').fill('   ')
    expect(page.locator('#btn-registrar')).to_be_disabled()
    comprobar(page.locator('#aviso-faltan').inner_text().strip() == 'Falta la descripción.',
              'Borrar un campo obligatorio sigue bloqueando y señala solo el dato que falta')
    page.locator('#f-descripcion').fill('Sinopsis corregida a mano.')
    expect(page.locator('#btn-registrar')).to_be_enabled()
    page.locator('#g-clasificacion [data-valor="+18"]').click()
    comprobar(page.locator('#f-clasificacion').input_value() == '+18',
              'La clasificación se puede cambiar manualmente después de confirmar')

    candidato['editorial_id'] = 0
    confirmar()
    expect(page.locator('#btn-registrar')).to_be_disabled()
    comprobar(page.locator('#aviso-faltan').inner_text().strip() == 'Falta la editorial.',
              'Una editorial no resuelta mantiene el bloqueo con el motivo correcto')
    page.locator('#f-editorial').select_option('Image Comics')
    expect(page.locator('#btn-registrar')).to_be_enabled()
    comprobar(page.locator('#aviso-faltan').is_hidden(),
              'Seleccionar manualmente la editorial que falta permite continuar')
    comprobar(not altas and not errores, 'Sin altas accidentales ni errores JavaScript: ' + str(errores))


if __name__ == '__main__':
    sesion = 'registro-validacion-' + uuid.uuid4().hex
    antes = estado_bd()
    php(f"session_id('{sesion}'); session_start(); $_SESSION=['usuario'=>'Prueba local de registro','rol'=>'admin']; session_write_close();")
    try:
        with sync_playwright() as p:
            candidatos = [os.environ.get('COMIC_HOUSE_BROWSER', ''),
                r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
                r'C:\Program Files\Google\Chrome\Application\chrome.exe']
            ejecutable = next((c for c in candidatos if c and Path(c).is_file()), None)
            browser = p.chromium.launch(headless=True, **({'executable_path': ejecutable} if ejecutable else {}))
            try:
                contexto = browser.new_context(viewport={'width': 1440, 'height': 1000})
                contexto.add_cookies([{'name': 'PHPSESSID', 'value': sesion, 'url': BASE}])
                probar(contexto.new_page())
            finally:
                browser.close()
        comprobar(estado_bd() == antes, 'El catálogo se conserva intacto durante la prueba')
        print(f'\n{CUENTA} comprobaciones de registro correctas.')
    finally:
        php(f"session_id('{sesion}'); session_start(); session_destroy();")
