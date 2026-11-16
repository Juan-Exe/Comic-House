<?php
session_start();
include("../modelo/conexion.php");
require_once __DIR__ . '/fecha_capitulo.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$id       = intval($_POST['id']);
$comic_id = intval($_POST['comic_id_sel'] ?? $_POST['comic_id']);
$titulo   = trim($_POST['titulo']);
$numero   = trim($_POST['numero']);
$fecha = validarFechaCapitulo(trim($_POST['fecha_publicacion'] ?? ''), trim($_POST['fecha_publicacion_mes'] ?? ''));
if (!$fecha['ok']) {
    header("Location: ../editar_capitulo.php?id=$id&error_fecha=1");
    exit();
}

$stmt = $conexion->prepare('UPDATE capitulos SET comic_id = ?, titulo = ?, numero = ?,
    fecha_publicacion = ?, fecha_publicacion_mes = ? WHERE id = ?');
$stmt->bind_param('issssi', $comic_id, $titulo, $numero, $fecha['fecha'], $fecha['mes'], $id);
$stmt->execute();
$stmt->close();

header("Location: ../subir_capitulo.php");
exit();
