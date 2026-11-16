<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403); echo json_encode(['ok'=>false,'mensaje'=>'Vuelve a iniciar sesión como administrador.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['confirmar_reemplazo'] ?? '') !== '1') {
    http_response_code(400); echo json_encode(['ok'=>false,'mensaje'=>'Confirma expresamente el reemplazo de las páginas.']); exit;
}
if (!is_string($_POST['csrf'] ?? null) || !isset($_SESSION['reemplazo_csrf'])
    || !hash_equals($_SESSION['reemplazo_csrf'],$_POST['csrf'])) {
    http_response_code(403); echo json_encode(['ok'=>false,'mensaje'=>'La sesión de esta página caducó. Recarga el capítulo.']); exit;
}
session_write_close();
@set_time_limit(300);
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/reemplazo_capitulo.php';
try {
    echo json_encode(reemplazarArchivoCapitulo($conexion,(int)($_POST['capitulo_id'] ?? 0),
        $_POST['version'] ?? '',$_POST['clave'] ?? '',$_FILES['cbr_file'] ?? [],($_POST['confirmar_obra'] ?? '') === '1'),JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conflicto=$e instanceof ReemplazoEnConflicto;
    $invalido=$e instanceof ArchivoCapituloInvalido;
    $nombre=$e instanceof ReemplazoNombreDistinto;
    if (!$conflicto && !$invalido && !$nombre) error_log('Reemplazo de capítulo: ' . $e->getMessage());
    echo json_encode(['ok'=>false,'error'=>$nombre?'posible_obra_distinta':($conflicto?'conflicto':($invalido?'archivo_invalido':'reemplazo')),
        'mensaje'=>$conflicto || $invalido || $nombre ? $e->getMessage() : 'No se pudo completar el reemplazo. Se conservaron las páginas anteriores; puedes reintentarlo.'],JSON_UNESCAPED_UNICODE);
}
