<?php
session_start();
require("../modelo/conexion.php");

if (!isset($_SESSION['usuario'])) {
    echo "no_logueado";
    exit();
}

$usuario = $_SESSION['usuario'];
$comic_id = $_POST['comic_id'];

$check = $conexion->prepare("SELECT * FROM biblioteca_usuarios WHERE usuario = ? AND comic_id = ?");
$check->bind_param("si", $usuario, $comic_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "ya_existe";
} else {
    $insert = $conexion->prepare("INSERT INTO biblioteca_usuarios (usuario, comic_id) VALUES (?, ?)");
    $insert->bind_param("si", $usuario, $comic_id);
    if ($insert->execute()) {
        echo "agregado";
    } else {
        echo "error";
    }
}
?>
