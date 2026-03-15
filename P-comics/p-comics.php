<?php
session_start();
include("../modelo/conexion.php");

// Géneros por tipo para el navbar
$generosComic = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'Comic' ORDER BY genero");
$generosManga = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'manga' ORDER BY genero");

// Parámetros de filtro
$tipo      = isset($_GET['tipo'])     ? $conexion->real_escape_string($_GET['tipo'])     : "";
$genero    = isset($_GET['genero'])   ? $conexion->real_escape_string($_GET['genero'])   : "";
$editorial = isset($_GET['editorial'])? $conexion->real_escape_string($_GET['editorial']): "";

// Construir query
$sql = "SELECT * FROM comics WHERE 1=1";
if (!empty($tipo))      $sql .= " AND tipo = '$tipo'";
if (!empty($genero))    $sql .= " AND genero = '$genero'";
if (!empty($editorial)) $sql .= " AND editorial = '$editorial'";
$sql .= " ORDER BY titulo ASC";

$datos = $conexion->query($sql);

// Título dinámico
if (!empty($editorial))     $titulo = "Catálogo de " . htmlspecialchars($editorial);
elseif (!empty($genero))    $titulo = htmlspecialchars($genero);
elseif ($tipo === 'manga')  $titulo = "Catálogo de Mangas";
elseif ($tipo === 'Comic')  $titulo = "Catálogo de Comics";
else                        $titulo = "Catálogo";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House - <?= $titulo ?></title>
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="../ico.ico">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/script.js"></script>
</head>
<body>

<header>
    <div class="search_bar">
        <div class="busqueda-ctn">
            <input class="barra_de_busqueda" type="search" id="buscar" placeholder="¿Buscas algún cómic o manga?"
                onkeyup="consulta_buscador($('#buscar').val());">
            <button class="search_button"><i class="bi bi-search"></i></button>
        </div>
        <div class="card_busqueda" id="card_busqueda" style="opacity: 0; visibility: hidden;">
            <div class="card shadow-sm p-2">
                <div class="container m-0 p-0" id="resultados_busqueda_nav"></div>
            </div>
        </div>
    </div>

    <nav class="navbar">
        <div class="logo-m">
            <a href="../index.php">
                <img src="../Imagenes/Logo-Comic-Huse.png" alt="">
            </a>
        </div>

        <div class="sidebar-container">
            <input type="checkbox" id="sidebar-active">
            <label for="sidebar-active" class="open-sidebar-button">
                <svg xmlns="http://www.w3.org/2000/svg" height="32px" viewBox="0 -960 960 960" width="32px" fill="#e3e3e3">
                    <path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z" />
                </svg>
            </label>
            <div class="links-container">
                <label for="sidebar-active" class="close-sidebar-button">
                    <svg xmlns="http://www.w3.org/2000/svg" height="32px" viewBox="0 -960 960 960" width="32px" fill="#e3e3e3">
                        <path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                    </svg>
                </label>
                <ul class="menu flex space-x-6 relative">
                    <li class="uah relative group">
                        <a href="p-comics.php?tipo=Comic" class="dropdown-toggle hover:text-gray-200">Comics <span>▾</span></a>
                        <ul class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                            <?php
                            $generosComic->data_seek(0);
                            while ($gen = $generosComic->fetch_object()): ?>
                                <li>
                                    <a href="p-comics.php?tipo=Comic&genero=<?= urlencode($gen->genero) ?>" class="block px-4 py-2 hover:bg-red-700">
                                        <?= htmlspecialchars($gen->genero) ?>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </li>
                    <li class="relative group">
                        <a href="p-comics.php?tipo=manga" class="dropdown-toggle hover:text-gray-200">Mangas <span>▾</span></a>
                        <ul class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                            <?php
                            $generosManga->data_seek(0);
                            while ($gen = $generosManga->fetch_object()): ?>
                                <li>
                                    <a href="p-comics.php?tipo=manga&genero=<?= urlencode($gen->genero) ?>" class="block px-4 py-2 hover:bg-red-700">
                                        <?= htmlspecialchars($gen->genero) ?>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </li>
                    <li><a href="../Biblioteca/index.php" class="hover:text-gray-200">Mi biblioteca</a></li>
                </ul>
                <div class="navbar-user relative group list-none user cursor-pointer">
                    <?php if (isset($_SESSION['usuario'])): ?>
                        <a href="#" class="dropdown-toggle hover:text-gray-200 flex items-center">
                            <img src="../Imagenes/pngegg.png" alt="Usuario" class="w-6 h-6 inline">
                            <span class="ml-2"><?= htmlspecialchars($_SESSION['usuario']) ?> ▾</span>
                        </a>
                        <ul class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                <li><a href="../dashboard.php" class="block px-4 py-2 hover:bg-red-700">Panel de Admin</a></li>
                            <?php endif; ?>
                            <li><a href="../logout.php" class="block px-4 py-2 hover:bg-red-700">Cerrar sesión</a></li>
                        </ul>
                    <?php else: ?>
                        <a href="../Login/login.php" class="flex items-center hover:text-gray-200">
                            <img src="../Imagenes/pngegg.png" alt="Usuario" class="w-6 h-6 inline">
                            <span class="ml-2">Ingresar</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggles = document.querySelectorAll('.dropdown-toggle');
        toggles.forEach(toggle => {
            toggle.addEventListener('click', function (e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const submenu = this.nextElementSibling;
                    if (submenu && submenu.classList.contains('submenu')) {
                        submenu.classList.toggle('open');
                    }
                }
            });
        });
    });
</script>

<div class="Comics-titulo-2">
    <div class="titulo-comics-ctn-2">
        <h2><?= $titulo ?></h2>
    </div>
</div>

<div class="Comics-base-2">
    <div class="Comics-container-2">
        <?php if ($datos->num_rows === 0): ?>
            <p style="color:#888; padding: 20px;">No se encontraron resultados.</p>
        <?php else: ?>
            <?php while ($comic = $datos->fetch_object()): ?>
                <div class="Comics-btn-2">
                    <button class="Comics-btns-2" onclick="location.href='../Comics/comics.php?id=<?= $comic->id ?>'"
                        style="background-image: url('../uploads/<?= htmlspecialchars($comic->portada) ?>');">
                    </button>
                    <div class="Comics-info-2">
                        <p class="title-c-2"><?= htmlspecialchars($comic->titulo) ?></p>
                        <p class="description-c-2">
                            <span><?= htmlspecialchars($comic->clasificacion) ?></span>
                            n°.1 - <?= htmlspecialchars($comic->capitulos) ?>
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
