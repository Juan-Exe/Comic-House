<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'aviso'=>'Sin permiso. Vuelve a iniciar sesión.']);
    exit;
}
session_write_close();
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/editorial_servicio.php';
try {
    $resultado = ComicHouse\Editoriales\altaEditorial($conexion, $_POST, $_FILES['imagen'] ?? []);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($e instanceof mysqli_sql_exception) error_log('Editorial: ' . $e->getMessage());
    echo json_encode(['ok'=>false,'aviso'=>$e instanceof mysqli_sql_exception
        ? 'No se pudo guardar la editorial. Puedes reintentarlo.' : $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
