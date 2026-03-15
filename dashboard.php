<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include_once("modelo/conexion.php");
$usuario = $_SESSION['usuario'];
$total_comics  = $conexion->query("SELECT COUNT(*) as n FROM comics")->fetch_object()->n;
$total_caps    = $conexion->query("SELECT COUNT(*) as n FROM capitulos")->fetch_object()->n;
$total_usuarios= $conexion->query("SELECT COUNT(*) as n FROM usuarios")->fetch_object()->n;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Dashboard</title>
    <link rel="icon" href="ico.ico">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Montserrat, sans-serif;
            background: #111;
            color: #f0f0f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* TOPBAR */
        .topbar {
            background: #ef4444;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
        }
        .topbar-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .topbar-brand img { height: 38px; }
        .topbar-brand span {
            color: #fff; font-size: 13px; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase; opacity: .85;
        }
        .btn-top {
            padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 600;
            font-family: Montserrat, sans-serif; text-decoration: none;
            display: flex; align-items: center; gap: 6px; transition: opacity .2s;
            background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.3);
        }
        .btn-top:hover { opacity: .8; }

        /* MAIN */
        .dash-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            gap: 40px;
        }

        .welcome {
            text-align: center;
        }
        .welcome h1 {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
        }
        .welcome h1 span { color: #ef4444; }
        .welcome p { color: #888; font-size: 14px; }

        /* STATS */
        .stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .stat-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 24px 32px;
            text-align: center;
            min-width: 140px;
        }
        .stat-card i {
            font-size: 28px;
            color: #ef4444;
            margin-bottom: 10px;
            display: block;
        }
        .stat-num {
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }
        .stat-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #666;
            margin-top: 4px;
        }

        /* ACTIONS */
        .dash-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .action-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 28px 36px;
            text-align: center;
            text-decoration: none;
            color: #fff;
            transition: border-color .2s, transform .2s;
            min-width: 180px;
        }
        .action-card:hover {
            border-color: #ef4444;
            transform: translateY(-3px);
            color: #fff;
        }
        .action-card i {
            font-size: 32px;
            color: #ef4444;
            display: block;
            margin-bottom: 12px;
        }
        .action-card span {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
        }

        .btn-logout {
            padding: 9px 22px;
            background: transparent;
            color: #ef4444;
            border: 1px solid #ef4444;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background .2s, color .2s;
        }
        .btn-logout:hover { background: #ef4444; color: #fff; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="index.php" class="topbar-brand">
        <img src="Imagenes/Logo-Comic-Huse.png" alt="Comic House">
        <span>Panel de administración</span>
    </a>
    <a href="index.php" class="btn-top">
        <i class="bi bi-house"></i> Ver sitio
    </a>
</div>

<div class="dash-main">

    <div class="welcome">
        <h1>Hola, <span><?= htmlspecialchars($usuario) ?></span></h1>
        <p>¿Qué quieres gestionar hoy?</p>
    </div>

    <div class="stats">
        <div class="stat-card">
            <i class="bi bi-book-half"></i>
            <div class="stat-num"><?= $total_comics ?></div>
            <div class="stat-label">Cómics</div>
        </div>
        <div class="stat-card">
            <i class="bi bi-collection"></i>
            <div class="stat-num"><?= $total_caps ?></div>
            <div class="stat-label">Capítulos</div>
        </div>
        <div class="stat-card">
            <i class="bi bi-people"></i>
            <div class="stat-num"><?= $total_usuarios ?></div>
            <div class="stat-label">Usuarios</div>
        </div>
    </div>

    <div class="dash-actions">
        <a href="crud.php" class="action-card">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>Gestionar cómics</span>
        </a>
        <a href="subir_capitulo.php" class="action-card">
            <i class="bi bi-upload"></i>
            <span>Subir capítulo</span>
        </a>
    </div>

    <a href="logout.php" class="btn-logout">
        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
    </a>

</div>

</body>
</html>
