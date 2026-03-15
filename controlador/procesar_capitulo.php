<?php
session_start();
@ini_set('upload_max_filesize', '512M');
@ini_set('post_max_size', '512M');
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300');
include("../modelo/conexion.php");

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$comic_id = intval($_POST['comic_id']);
$titulo   = $conexion->real_escape_string(trim($_POST['titulo']));
$numero   = $conexion->real_escape_string(trim($_POST['numero']));
$fecha    = $conexion->real_escape_string(trim($_POST['fecha_publicacion']));

header('Content-Type: application/json');

// Verificar duplicado
$verificar = $conexion->prepare("SELECT id FROM capitulos WHERE comic_id = ? AND numero = ?");
$verificar->bind_param("is", $comic_id, $numero);
$verificar->execute();
$verificar->store_result();
if ($verificar->num_rows > 0) {
    $verificar->close();
    echo json_encode(['ok' => false, 'error' => 'duplicado']);
    exit();
}
$verificar->close();

// Verificar archivo subido
if (empty($_FILES['cbr_file']['tmp_name'])) {
    echo json_encode(['ok' => false, 'error' => 'sin_archivo']);
    exit();
}

$archivo_tmp  = $_FILES['cbr_file']['tmp_name'];
$archivo_nombre = strtolower($_FILES['cbr_file']['name']);
$extension    = pathinfo($archivo_nombre, PATHINFO_EXTENSION);

// Insertar capítulo
$conexion->query("INSERT INTO capitulos (comic_id, titulo, numero, fecha_publicacion)
                  VALUES ('$comic_id', '$titulo', '$numero', " . ($fecha ? "'$fecha'" : "NULL") . ")");
$capitulo_id = $conexion->insert_id;

// Carpeta destino de páginas
$paginas_dir = dirname(__DIR__) . "/paginas/cap_{$capitulo_id}/";
if (!is_dir($paginas_dir)) {
    mkdir($paginas_dir, 0777, true);
}

$imagenes = [];

if ($extension === 'cbz') {
    // CBZ = ZIP
    $zip = new ZipArchive();
    if ($zip->open($archivo_tmp) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $ext_entry = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            // Solo archivos de imagen, ignorar carpetas y thumbs
            if (in_array($ext_entry, ['jpg','jpeg','png','webp','gif']) && strpos(basename($entry), '.') !== 0) {
                $contenido = $zip->getFromIndex($i);
                $nombre_final = sprintf('%04d', $i) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($entry));
                $ruta_fisica = $paginas_dir . $nombre_final;
                file_put_contents($ruta_fisica, $contenido);
                $imagenes[] = ['nombre' => $nombre_final, 'orden' => $i];
            }
        }
        $zip->close();
    }

} elseif ($extension === 'cbr') {
    // CBR = RAR — usar unrar-free del sistema
    $archivo_tmp_safe = escapeshellarg($archivo_tmp);
    $paginas_dir_safe = escapeshellarg($paginas_dir);

    // Intentar con unrar-free (comando: unrar-free o unrar)
    $cmds = ["unrar-free e -y $archivo_tmp_safe $paginas_dir_safe", "unrar e -y $archivo_tmp_safe $paginas_dir_safe"];
    foreach ($cmds as $cmd) {
        exec($cmd . " 2>&1", $output, $retval);
        if ($retval === 0) break;
    }

    // Recoger imágenes extraídas
    $archivos = glob($paginas_dir . "*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG}", GLOB_BRACE);
    natsort($archivos);
    $archivos = array_values($archivos);
    foreach ($archivos as $i => $ruta) {
        $imagenes[] = ['nombre' => basename($ruta), 'orden' => $i];
    }
}

// Ordenar por nombre natural (ej. 001.jpg, 002.jpg...)
usort($imagenes, fn($a, $b) => strnatcasecmp($a['nombre'], $b['nombre']));

// Guardar en BD con detección de página doble por dimensiones
foreach ($imagenes as $idx => $img) {
    $ruta_web    = $conexion->real_escape_string("paginas/cap_{$capitulo_id}/" . $img['nombre']);
    $ruta_fisica = $paginas_dir . $img['nombre'];
    $orden       = $idx + 1;
    $es_doble    = 0;

    $size = @getimagesize($ruta_fisica);
    if ($size && $size[0] > $size[1]) {  // ancho > alto = página horizontal/doble
        $es_doble = 1;
    }

    $conexion->query("INSERT INTO paginas (capitulo_id, imagen_url, orden, es_doble)
                      VALUES ('$capitulo_id', '$ruta_web', '$orden', '$es_doble')");
}

echo json_encode(['ok' => true, 'comic_id' => $comic_id]);
exit();
