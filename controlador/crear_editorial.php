<?php
/**
 * crear_editorial.php - Alta de una editorial desde el registro de comics.
 *
 * Cuando la fuente dice que una obra la publico una editorial que no
 * esta en el catalogo, la UNICA salida valida es crearla. Sustituirla
 * por otra de la lista guardaria un dato falso: una editorial que no
 * publico esa obra.
 *
 * Responde JSON. Solo administradores.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'aviso' => 'Sin permiso']);
    exit();
}

require_once __DIR__ . '/../modelo/conexion.php';

$nombre = trim($_POST['nombre'] ?? '');

if ($nombre === '') {
    echo json_encode(['ok' => false, 'aviso' => 'Falta el nombre'], JSON_UNESCAPED_UNICODE);
    exit();
}
if (mb_strlen($nombre) > 100) {
    echo json_encode(['ok' => false, 'aviso' => 'Nombre demasiado largo'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Puede existir ya con otra forma de escritura ("DC" vs "dc  ").
// Se devuelve la existente en vez de crear un duplicado.
$stmt = $conexion->prepare(
    "SELECT id, nombre FROM editoriales WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1"
);
$stmt->bind_param('s', $nombre);
$stmt->execute();
$ya = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($ya) {
    echo json_encode([
        'ok' => true, 'id' => (int) $ya['id'], 'nombre' => $ya['nombre'],
        'aviso' => 'Esa editorial ya existía en el catálogo',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// La columna imagen es NOT NULL en el esquema: se deja vacia y el
// administrador sube el logo despues desde la pantalla de editoriales.
$stmt = $conexion->prepare("INSERT INTO editoriales (nombre, imagen) VALUES (?, '')");
$stmt->bind_param('s', $nombre);

try {
    $stmt->execute();
    $id = $conexion->insert_id;
    $stmt->close();
    echo json_encode([
        'ok' => true, 'id' => $id, 'nombre' => $nombre,
        'aviso' => 'Editorial creada. Puedes subirle el logo más tarde.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $stmt->close();
    echo json_encode([
        'ok' => false,
        'aviso' => 'No se pudo crear la editorial',
    ], JSON_UNESCAPED_UNICODE);
}
