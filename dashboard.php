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
            background: #1a1a1a;
            color: #f0f0f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* TOPBAR */
        .topbar {
            background: #ef4444;
            height: 72px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-brand {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; justify-self: start;
        }
        .topbar-brand img { height: 48px; }
        .topbar-title {
            justify-self: center;
            color: #fff; font-size: 14px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase; opacity: .9;
        }
        .topbar-actions {
            justify-self: end; display: flex; gap: 10px;
        }
        .btn-top {
            padding: 7px 16px; border-radius: 6px; font-size: 12px; font-weight: 700;
            font-family: Montserrat, sans-serif; text-decoration: none;
            display: flex; align-items: center; gap: 6px; transition: opacity .2s;
        }
        .btn-top:hover { opacity: .8; }
        .btn-top-ghost {
            background: rgba(255,255,255,.15); color: #fff;
            border: 1px solid rgba(255,255,255,.35);
        }

        /* MAIN */
        .dash-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 24px;
            gap: 24px;
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
            display: grid;
            grid-template-columns: repeat(3, 140px);
            gap: 12px;
            justify-content: center;
        }
        .stat-card {
            background: #242426;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 16px 24px;
            text-align: center;
            width: 140px;
        }
        .stat-card i {
            font-size: 22px;
            color: #ef4444;
            margin-bottom: 6px;
            display: block;
        }
        .stat-num {
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }
        .stat-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #666;
            margin-top: 4px;
        }

        /* ACTIONS */
        .dash-actions {
            display: grid;
            grid-template-columns: repeat(5, 140px);
            gap: 12px;
            justify-content: center;
        }
        .action-card {
            background: #242426;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 18px 10px;
            text-align: center;
            text-decoration: none;
            color: #fff;
            transition: border-color .2s, transform .2s;
            width: 140px;
            height: 110px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .action-card:hover {
            border-color: #ef4444;
            transform: translateY(-2px);
            color: #fff;
        }
        .action-card i {
            font-size: 24px;
            color: #ef4444;
        }
        .action-card span {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            line-height: 1.3;
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
    </a>
    <span class="topbar-title">Panel de administración</span>
    <div class="topbar-actions">
        <a href="index.php" class="btn-top btn-top-ghost">
            <i class="bi bi-house"></i> Ver sitio
        </a>
    </div>
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
        <a href="index.php" class="action-card">
            <i class="bi bi-house"></i>
            <span>Ingresar al sitio</span>
        </a>
        <a href="crud.php" class="action-card">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>Registrar y gestionar cómics</span>
        </a>
        <a href="destacar.php" class="action-card">
            <i class="bi bi-star"></i>
            <span>Destacar cómics</span>
        </a>
        <a href="editoriales.php" class="action-card">
            <i class="bi bi-building"></i>
            <span>Editoriales</span>
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
