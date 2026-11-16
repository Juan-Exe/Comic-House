<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Vuelve a iniciar sesión como administrador.']);
    exit;
}
session_write_close();
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/validar_capitulo.php';
require_once __DIR__ . '/fecha_capitulo.php';
require_once __DIR__ . '/recibo_subida.php';

$entrada = json_decode(file_get_contents('php://input'), true);
$filas = $entrada['archivos'] ?? null;
$comic = (int) ($entrada['comic_id'] ?? 0);
if (!is_array($filas) || count($filas) > 200) {
    echo json_encode(['ok' => false, 'mensaje' => 'Selecciona como máximo 200 archivos por lote.']);
    exit;
}
$st = $conexion->prepare('SELECT tipo FROM comics WHERE id=?');
$st->bind_param('i', $comic);
$st->execute();
$st->bind_result($tipo);
$existe = $st->fetch();
$st->close();
if (!$existe) {
    echo json_encode(['ok' => false, 'mensaje' => 'El cómic elegido ya no está en el catálogo.']);
    exit;
}
$resultado = [];
foreach ($filas as $i => $fila) {
    if (!is_array($fila) || !is_array($fila['datos'] ?? null)
        || !is_string($fila['id'] ?? null) || !is_string($fila['clave'] ?? null)
        || !is_string($fila['nombre'] ?? null)
        || array_filter($fila['datos'], fn($valor) => !is_string($valor))) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Los datos del lote no son válidos. Revisa las entregas.']);
        exit;
    }
    $datos = ($fila['datos'] ?? []) + ['comic_id' => $comic];
    $datos['comic_id'] = $comic;
    $clave = $fila['clave'] ?? '';
    $errores = [];
    $avisos = [];
    if (!preg_match('/^[a-f0-9]{32}$/D', $clave)) $errores[] = 'No se pudo identificar este intento. Vuelve a elegir el archivo.';
    if (trim($datos['titulo'] ?? '') === '') $errores[] = 'Escribe el título del capítulo.';
    if (mb_strlen(trim($datos['titulo'] ?? '')) > 255) $errores[] = 'El título admite hasta 255 caracteres.';
    $fecha = validarFechaCapitulo($datos['fecha_publicacion'] ?? '', $datos['fecha_publicacion_mes'] ?? '');
    if (!$fecha['ok']) $errores[] = $fecha['mensaje'];
    $recibo = $errores ? null : buscarReciboSubida($conexion, $clave, $datos);
    if ($recibo && $recibo['coincide']) {
        $resultado[] = ['id' => $fila['id'], 'estado' => 'subido', 'errores' => [], 'avisos' => [],
            'capitulo_id' => (int) $recibo['id'], 'paginas' => (int) $recibo['paginas']];
        continue;
    }
    if ($recibo) $errores[] = 'Este intento ya guardó otros datos. Revisa la entrega.';
    $dictamen = revisarCapitulo($conexion, $comic, $tipo, $datos['formato'] ?? 'grapa',
        $datos['numero_desde'] ?? '', $datos['numero_hasta'] ?? '');
    if ($dictamen['estado'] === 'error') $errores[] = $dictamen['mensaje'];
    if ($dictamen['estado'] === 'aviso') $avisos[] = ['tipo' => 'fuera_rango', 'mensaje' => $dictamen['mensaje']];
    $sospecha = archivoPareceDeOtraObra($conexion, $comic, $fila['nombre'] ?? '');
    if ($sospecha['sospecha']) $avisos[] = ['tipo' => 'posible_obra_distinta', 'mensaje' =>
        'El nombre «' . ($fila['nombre'] ?? '') . '» puede pertenecer a otra obra. Es una sospecha por el nombre; comprueba el cómic elegido.'];
    if (!preg_match('/\.(cbr|cbz)$/i', $fila['nombre'] ?? '')) $errores[] = 'Elige un archivo .cbr o .cbz.';
    if (($fila['tamano'] ?? 0) <= 0) $errores[] = 'El archivo está vacío.';
    if (($fila['tamano'] ?? 0) > 512 * 1024 * 1024) $errores[] = 'El archivo supera los 512 MB permitidos.';
    $resultado[] = ['id' => $fila['id'], 'estado' => $errores ? 'revisar' : 'listo',
        'errores' => $errores, 'avisos' => $avisos];
}
echo json_encode(['ok' => true, 'archivos' => $resultado], JSON_UNESCAPED_UNICODE);
