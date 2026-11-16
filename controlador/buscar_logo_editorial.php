<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'aviso'=>'Vuelve a iniciar sesión como administrador.']);
    exit;
}
session_write_close();
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/editorial_servicio.php';
try {
    $revision = ComicHouse\Editoriales\consultarEditorial($conexion, trim($_GET['nombre'] ?? ''));
    $logos = [];
    if ($revision['estado'] === 'nueva' || ($revision['estado'] === 'parecida' && ($_GET['confirmar'] ?? '') === '1')) {
        $dir = dirname(__DIR__) . '/cache/wiki';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $cache = $dir . '/logos_v1_' . hash('sha256', $revision['nombre']) . '.json';
        $guardado = is_readable($cache) && time() - filemtime($cache) < 86400 ? json_decode(file_get_contents($cache), true) : null;
        if (is_array($guardado)) $logos = $guardado;
        else {
            $logos = ComicHouse\Editoriales\buscarLogos($revision['nombre']);
            @file_put_contents($cache, json_encode($logos, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }
    echo json_encode(['ok'=>true,'logos'=>$logos] + $revision, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'aviso'=>'No se pudo consultar el logo. Puedes subir una imagen manualmente.'], JSON_UNESCAPED_UNICODE);
}
