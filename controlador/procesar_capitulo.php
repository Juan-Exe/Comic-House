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

require_once __DIR__ . '/validar_capitulo.php';

$comic_id = intval($_POST['comic_id']);
$titulo   = trim($_POST['titulo'] ?? '');
$fecha    = trim($_POST['fecha_publicacion'] ?? '');
$formato  = trim($_POST['formato'] ?? 'grapa');
$arco     = trim($_POST['arco'] ?? '');

header('Content-Type: application/json');

// El tipo de obra decide qué numeración tiene sentido.
$obra = $conexion->prepare("SELECT tipo FROM comics WHERE id = ?");
$obra->bind_param('i', $comic_id);
$obra->execute();
$obra->bind_result($tipo_obra);
$existe = $obra->fetch();
$obra->close();

if (!$existe) {
    echo json_encode(['ok' => false, 'error' => 'comic_inexistente']);
    exit();
}

// La numeración se valida antes de tocar nada: dígitos, sin ceros a
// la izquierda, rango coherente y sin pisar lo ya subido.
$dictamen = revisarCapitulo(
    $conexion, $comic_id, $tipo_obra, $formato,
    $_POST['numero_desde'] ?? '', $_POST['numero_hasta'] ?? ''
);

if ($dictamen['estado'] === 'error') {
    echo json_encode(['ok' => false, 'error' => 'numeracion',
                      'mensaje' => $dictamen['mensaje']]);
    exit();
}

$desde = $dictamen['desde'];
$hasta = $dictamen['hasta'];
$numero = etiquetaCapitulo($formato, $desde, $hasta);

// Verificar archivo subido
if (empty($_FILES['cbr_file']['tmp_name'])) {
    echo json_encode(['ok' => false, 'error' => 'sin_archivo']);
    exit();
}

$archivo_tmp  = $_FILES['cbr_file']['tmp_name'];
$archivo_nombre = strtolower($_FILES['cbr_file']['name']);
$extension    = pathinfo($archivo_nombre, PATHINFO_EXTENSION);

// Insertar capítulo (sentencia preparada: evita inyección SQL)
$stmt_cap = $conexion->prepare(
    "INSERT INTO capitulos (comic_id, titulo, numero, fecha_publicacion,
                            formato, numero_desde, numero_hasta, arco)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$fecha_val = ($fecha !== '') ? $fecha : null;
$arco_val  = ($arco !== '') ? $arco : null;
$stmt_cap->bind_param("issssiis", $comic_id, $titulo, $numero, $fecha_val,
                      $formato, $desde, $hasta, $arco_val);
$stmt_cap->execute();
$capitulo_id = $conexion->insert_id;
$stmt_cap->close();

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

// ---------------------------------------------------------------------
// Guardar páginas en BD.
//
// OPTIMIZACIÓN (ver E1_integridad_rendimiento/benchmark/):
// antes se ejecutaba un INSERT por página dentro del bucle. Con un
// capítulo de 220 páginas eso son 220 viajes a la base, cada uno con su
// propio commit implícito (autocommit). Ahora se agrupan en lotes de 100
// filas dentro de una sola transacción: ~54x más rápido medido.
// La transacción además da atomicidad: si una página falla, no queda un
// capítulo a medio cargar.
// ---------------------------------------------------------------------
const PAGINAS_POR_LOTE = 100;

$filas = [];
foreach ($imagenes as $idx => $img) {
    $ruta_fisica = $paginas_dir . $img['nombre'];
    $es_doble    = 0;

    $size = @getimagesize($ruta_fisica);
    if ($size && $size[0] > $size[1]) {  // ancho > alto = página horizontal/doble
        $es_doble = 1;
    }

    $filas[] = [
        'url'      => "paginas/cap_{$capitulo_id}/" . $img['nombre'],
        'orden'    => $idx + 1,
        'es_doble' => $es_doble,
    ];
}

$conexion->begin_transaction();
try {
    $total = count($filas);
    for ($i = 0; $i < $total; $i += PAGINAS_POR_LOTE) {
        $lote   = array_slice($filas, $i, PAGINAS_POR_LOTE);
        $tuplas = [];
        foreach ($lote as $f) {
            $url      = $conexion->real_escape_string($f['url']);
            $tuplas[] = "({$capitulo_id}, '{$url}', {$f['orden']}, {$f['es_doble']})";
        }
        $sql = "INSERT INTO paginas (capitulo_id, imagen_url, orden, es_doble) VALUES "
             . implode(',', $tuplas);
        if (!$conexion->query($sql)) {
            throw new RuntimeException($conexion->error);
        }
    }
    $conexion->commit();
} catch (Throwable $e) {
    // Atomicidad: sin páginas no debe quedar el capítulo registrado
    $conexion->rollback();
    $del = $conexion->prepare("DELETE FROM capitulos WHERE id = ?");
    $del->bind_param("i", $capitulo_id);
    $del->execute();
    $del->close();

    echo json_encode(['ok' => false, 'error' => 'fallo_insercion_paginas']);
    exit();
}

echo json_encode(['ok' => true, 'comic_id' => $comic_id, 'paginas' => count($filas)]);
exit();
