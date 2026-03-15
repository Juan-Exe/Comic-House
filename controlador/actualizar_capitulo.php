<?php
session_start();
include("../modelo/conexion.php");

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$id       = intval($_POST['id']);
$comic_id = intval($_POST['comic_id_sel'] ?? $_POST['comic_id']);
$titulo   = $conexion->real_escape_string(trim($_POST['titulo']));
$numero   = $conexion->real_escape_string(trim($_POST['numero']));
$fecha    = $conexion->real_escape_string(trim($_POST['fecha_publicacion']));

$conexion->query("UPDATE capitulos SET
    comic_id = '$comic_id',
    titulo = '$titulo',
    numero = '$numero',
    fecha_publicacion = " . ($fecha ? "'$fecha'" : "NULL") . "
    WHERE id = $id");

header("Location: ../subir_capitulo.php");
exit();
