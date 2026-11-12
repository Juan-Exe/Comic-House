<?php
/**
 * descargar_portada.php - Trae la portada de la fuente al servidor (HU-07).
 *
 * La API devuelve una URL de ComicVine. Guardarla tal cual dejaria el
 * catalogo dependiendo de un servidor ajeno: si cambia la direccion o
 * el sitio cae, las portadas desaparecen. Por eso se descarga a
 * uploads/, igual que si se hubiera subido a mano.
 *
 * Si la descarga falla, se responde con un aviso y el formulario deja
 * activa la subida manual (CA-07.4). Nunca se guarda una ruta rota.
 *
 * Solo administradores.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'aviso' => 'Sin permiso']);
    exit();
}

const MAX_BYTES = 5 * 1024 * 1024;   // 5 MB: una portada normal pesa ~200 KB
const DIR_DESTINO = __DIR__ . '/../uploads/';

function responder(bool $ok, string $archivo = '', string $aviso = ''): void {
    echo json_encode(['ok' => $ok, 'archivo' => $archivo, 'aviso' => $aviso],
        JSON_UNESCAPED_UNICODE);
    exit();
}

$url    = trim($_POST['url'] ?? '');
$titulo = trim($_POST['titulo'] ?? 'portada');

if ($url === '') {
    responder(false, '', 'No se indicó ninguna imagen');
}

// Solo https y solo del dominio de la fuente: evita que esta ruta se
// use para descargar cualquier cosa de internet al servidor.
$partes = parse_url($url);
if (($partes['scheme'] ?? '') !== 'https'
    || !str_ends_with($partes['host'] ?? '', 'comicvine.gamespot.com')) {
    responder(false, '', 'Origen de imagen no permitido');
}

// ---------------------------------------------------------------
// Descarga
// ---------------------------------------------------------------
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_USERAGENT      => 'ComicHouse/1.0 (proyecto academico)',
]);
$datos = curl_exec($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($datos === false || $http !== 200) {
    responder(false, '', 'No se pudo descargar la portada'
        . ($error !== '' ? ": $error" : " (HTTP $http)")
        . '. Súbela a mano.');
}

if (strlen($datos) > MAX_BYTES) {
    responder(false, '', 'La portada pesa demasiado. Súbela a mano.');
}

// ---------------------------------------------------------------
// Validar que sea una imagen de verdad, no solo que lo diga la URL
// ---------------------------------------------------------------
$info = @getimagesizefromstring($datos);
if ($info === false) {
    responder(false, '', 'El archivo descargado no es una imagen válida');
}

$extensiones = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
    IMAGETYPE_GIF  => 'gif',
];
if (!isset($extensiones[$info[2]])) {
    responder(false, '', 'Formato de imagen no admitido');
}
$ext = $extensiones[$info[2]];

// ---------------------------------------------------------------
// Nombre sin colisiones, legible y seguro
// ---------------------------------------------------------------
$base = mb_strtolower(trim($titulo));
$base = preg_replace('/[^a-z0-9]+/u', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $base) ?: 'portada');
$base = trim($base, '_');
if ($base === '') {
    $base = 'portada';
}
$base = mb_substr($base, 0, 40);

$archivo = $base . '_' . time() . '.' . $ext;
$destino = DIR_DESTINO . $archivo;

if (!is_dir(DIR_DESTINO) && !mkdir(DIR_DESTINO, 0777, true)) {
    responder(false, '', 'No se pudo preparar la carpeta de portadas');
}

if (file_put_contents($destino, $datos) === false) {
    responder(false, '', 'No se pudo guardar la portada en el servidor');
}

responder(true, $archivo, 'Portada descargada al servidor');
