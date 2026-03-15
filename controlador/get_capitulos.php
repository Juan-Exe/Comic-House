<?php
session_start();
include("../modelo/conexion.php");

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    exit();
}

$comic_id = intval($_GET['comic_id'] ?? 0);
if (!$comic_id) { echo json_encode([]); exit(); }

$caps = $conexion->query("SELECT capitulos.*, comics.titulo AS comic_titulo
                          FROM capitulos
                          JOIN comics ON capitulos.comic_id = comics.id
                          WHERE capitulos.comic_id = $comic_id
                          ORDER BY capitulos.numero ASC");

$resultado = [];
while ($cap = $caps->fetch_assoc()) {
    // Primeras 10 páginas
    $pags = $conexion->query("SELECT imagen_url FROM paginas WHERE capitulo_id = {$cap['id']} ORDER BY orden ASC LIMIT 11");
    $imagenes = [];
    while ($img = $pags->fetch_assoc()) {
        $imagenes[] = $img['imagen_url'];
    }
    $cap['imagenes'] = $imagenes;
    $resultado[] = $cap;
}

header('Content-Type: application/json');
echo json_encode($resultado);
