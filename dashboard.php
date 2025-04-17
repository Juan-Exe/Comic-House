<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$usuario = $_SESSION['usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="text-center">
        <h2>Hola, <?= htmlspecialchars($usuario) ?> 👋 ¿Qué quieres hacer?</h2>
        <div class="mt-4">
            <a href="crud.php" class="btn btn-primary btn-lg me-3">Ir al CRUD</a>
            <a href="subir_capitulo.php" class="btn btn-success btn-lg">Subir Capítulos</a>
        </div>
        <div class="mt-3">
            <a href="logout.php" class="btn btn-outline-danger">Cerrar Sesión</a>
        </div>
    </div>
</div>

</body>
</html>
