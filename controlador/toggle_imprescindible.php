<?php
session_start();
include("../modelo/conexion.php");

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['ok' => false]);
    exit();
}

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['ok' => false]); exit(); }

$actual = $conexion->query("SELECT imprescindible FROM comics WHERE id = $id")->fetch_assoc();
if (!$actual) { echo json_encode(['ok' => false]); exit(); }

$nuevo = $actual['imprescindible'] ? 0 : 1;

// Verificar límite de 4 si se va a activar
if ($nuevo === 1) {
    $total = $conexion->query("SELECT COUNT(*) as n FROM comics WHERE imprescindible = 1")->fetch_assoc()['n'];
    if ($total >= 4) {
        echo json_encode(['ok' => true, 'imprescindible' => 0, 'total' => $total + 1]);
        exit();
    }
}

$conexion->query("UPDATE comics SET imprescindible = $nuevo WHERE id = $id");
$total = $conexion->query("SELECT COUNT(*) as n FROM comics WHERE imprescindible = 1")->fetch_assoc()['n'];

echo json_encode(['ok' => true, 'imprescindible' => $nuevo, 'total' => $total]);
