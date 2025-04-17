<?php
include("../modelo/conexion.php");
session_start();

// Establecer localización en español para fechas
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain.1252', 'spanish');

// Validar que exista el ID y sea numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Cómic no encontrado.");
}

$id = $_GET['id'];

$primerCapitulo = $conexion->query("SELECT id FROM capitulos WHERE comic_id = $id ORDER BY numero ASC LIMIT 1")->fetch_assoc();
$ultimoCapitulo = $conexion->query("SELECT id FROM capitulos WHERE comic_id = $id ORDER BY numero DESC LIMIT 1")->fetch_assoc();

// Obtener la información del cómic
$stmt = $conexion->prepare("SELECT * FROM comics WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Cómic no encontrado.");
}

$comic = $result->fetch_assoc();
$stmt->close();

// Obtener los capítulos del cómic
$capitulos = $conexion->query("SELECT * FROM capitulos WHERE comic_id = $id ORDER BY CAST(numero AS UNSIGNED) ASC");

// Obtener géneros para el menú
$generos = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'manga'");
$generosn = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'Comic'");
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <link rel="icon" href="../ico.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($comic['titulo']) ?></title>
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="slider.css">
    <link rel="stylesheet" href="comics.css">

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

            <div class="card_busqueda" id="card_busqueda" style="opacity; 0;">
                <div class="card shadow-sm p-2">

                    <div class="container m-0 p-0" id="resultados_busqueda_nav">
                    </div>

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
                    <svg xmlns="http://www.w3.org/2000/svg" height="32px" viewBox="0 -960 960 960" width="32px"
                        fill="#e3e3e3">
                        <path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z" />
                    </svg>
                </label>

                <div class="links-container">
                    <label for="sidebar-active" class="close-sidebar-button">
                        <svg xmlns="http://www.w3.org/2000/svg" height="32px" viewBox="0 -960 960 960" width="32px"
                            fill="#e3e3e3">
                            <path
                                d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                        </svg>
                    </label>
                    <ul class="menu flex space-x-6 relative">
                        <li class="uah relative group">
                            <a href="../P-comics/p-comics.php" class="dropdown-toggle hover:text-gray-200">Comics
                                <span>▾</span></a>
                            <ul
                                class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                                <?php while ($gen = $generosn->fetch_object()) { ?>
                                    <li>
                                        <a href="../P-comics/p-comics.php?genero=<?= urlencode($gen->genero) ?>"
                                            class="block px-4 py-2 hover:bg-red-700">
                                            <?= htmlspecialchars($gen->genero) ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </li>
                        <li class="relative group">
                            <a href="../P-Mangas/mangas.php" class="dropdown-toggle hover:text-gray-200">Mangas
                                <span>▾</span></a>
                            <ul
                                class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                                <?php while ($gen = $generos->fetch_object()) { ?>
                                    <li>
                                        <a href="../P-Mangas/mangas.php?genero=<?= urlencode($gen->genero) ?>"
                                            class="block px-4 py-2 hover:bg-red-700">
                                            <?= htmlspecialchars($gen->genero) ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </li>
                        <li><a href="../Biblioteca/index.php" class="hover:text-gray-200">Mi biblioteca</a></li>
                        <li class="relative group list-none user cursor-pointer">
                            <?php if (isset($_SESSION['usuario'])): ?>
                                <a href="#" class="dropdown-toggle hover:text-gray-200 flex items-center">
                                    <img src="../Imagenes/pngegg.png" alt="Usuario" class="w-6 h-6 inline">
                                    <span class="ml-2"><?php echo htmlspecialchars($_SESSION['usuario']); ?> ▾</span>
                                </a>
                                <ul
                                    class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                                    <li>
                                        <a href="../logout.php" class="block px-4 py-2 hover:bg-red-700">Cerrar sesión</a>
                                    </li>
                                </ul>
                            <?php else: ?>
                                <a href="../Login/login.php" class="flex items-center hover:text-gray-200">
                                    <img src="../Imagenes/pngegg.png" alt="Usuario" class="w-6 h-6 inline">
                                    <span class="ml-2">Ingresar</span>
                                </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggles = document.querySelectorAll('.dropdown-toggle');

            toggles.forEach(toggle => {
                toggle.addEventListener('click', function (e) {
                    // Solo en móviles: max-width 768px
                    if (window.innerWidth <= 768) {
                        e.preventDefault(); // evita redirección
                        const submenu = this.nextElementSibling;
                        if (submenu && submenu.classList.contains('submenu')) {
                            submenu.classList.toggle('open');
                        }
                    }
                });
            });
        });
    </script>



    <div class="inf-base">
        <div class="inf-container">
            <div class="img-container"
                style="background-image: url('../uploads/<?= htmlspecialchars($comic['portada']) ?>');">
            </div>
            <div class="info">
                <h2><?= htmlspecialchars($comic['anio']) ?> · <?= htmlspecialchars($comic['genero']) ?></h2>
                <h1><?= htmlspecialchars($comic['titulo']) ?>
                    <span><?= htmlspecialchars($comic['clasificacion']) ?></span>
                </h1>
                <div class="descripcion-container">
                    <p class="descripcion-corta">
                        <?= htmlspecialchars($comic['descripcion']) ?>
                        <span class="btn-ver-mas"
                            onclick="mostrarDescripcion('<?= htmlspecialchars(addslashes($comic['descripcion'])) ?>')">
                            Más</span>
                    </p>
                </div>

                <div class="button-div">
                    <button onclick="location.href='../Lector/lector.php?capitulo_id=<?= $primerCapitulo['id'] ?>'">Leer
                        Primero</button>
                    <button onclick="location.href='../Lector/lector.php?capitulo_id=<?= $ultimoCapitulo['id'] ?>'">Leer
                        Último</button>

                    <button class="btn-add" onclick="agregarComic(<?= $comic['id'] ?>)">+</button>
                </div>
            </div>
        </div>
    </div>

    <div class="capitulos-base">
        <div class="capitulos-container">
            <h2>Capítulos</h2>
            <div class="linea"></div>
            <div class="enlaces">
                <?php while ($cap = $capitulos->fetch_assoc()): ?>
                    <a href="../Lector/lector.php?capitulo_id=<?= $cap['id'] ?>" data-capitulo="<?= $cap['id'] ?>">
                        <span><?= htmlspecialchars($comic['titulo']) ?>: <?= $cap['titulo'] ?>     <?= $cap['numero'] ?></span>
                        <span class="fecha">
                            <?= strftime("%e %B %Y", strtotime($cap['fecha_publicacion'])) ?>
                        </span>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>


    <div id="popup-msg"
        class="fixed bottom-4 right-4 bg-red-600 text-white px-4 py-2 rounded shadow-lg transition-opacity duration-300 opacity-0 pointer-events-none z-50">
        <span id="popup-text"></span>
    </div>

    <div id="modalDescripcion" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <p id="descripcionCompleta"></p>
        </div>
    </div>



</body>

<script>
    function mostrarDescripcion(descripcion) {
        document.getElementById('descripcionCompleta').innerText = descripcion;
        document.getElementById('modalDescripcion').style.display = 'block';
    }

    function cerrarModal() {
        document.getElementById('modalDescripcion').style.display = 'none';
    }

    // Cierra el modal si el usuario hace clic fuera de él
    window.onclick = function (event) {
        let modal = document.getElementById('modalDescripcion');
        if (event.target === modal) {
            cerrarModal();
        }
    }
</script>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function agregarComic(comicId) {
        $.post("../controlador/agregar_a_biblioteca.php", { comic_id: comicId }, function (respuesta) {
            let mensaje = '';
            switch (respuesta) {
                case "no_logueado":
                    mensaje = "Debes iniciar sesión para agregar cómics a tu biblioteca.";
                    break;
                case "ya_existe":
                    mensaje = "Este cómic ya está en tu biblioteca.";
                    break;
                case "agregado":
                    mensaje = "¡Cómic agregado con éxito!";
                    break;
                default:
                    mensaje = "Ocurrió un error al agregar el cómic.";
            }
            mostrarPopup(mensaje);
        });
    }

    function mostrarPopup(mensaje) {
        const popup = document.getElementById("popup-msg");
        const text = document.getElementById("popup-text");

        text.textContent = mensaje;
        popup.classList.remove("opacity-0", "pointer-events-none");
        popup.classList.add("opacity-100");

        // Ocultar después de 3 segundos
        setTimeout(() => {
            popup.classList.remove("opacity-100");
            popup.classList.add("opacity-0", "pointer-events-none");
        }, 3000);
    }

</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const links = document.querySelectorAll(".enlaces a");

        links.forEach(link => {
            const capituloId = link.getAttribute("data-capitulo");

            // Si el capítulo ya fue visto, añade clase "leido"
            if (localStorage.getItem("leido_" + capituloId)) {
                link.classList.add("leido");
            }

            // Al hacer clic, márcalo como leído
            link.addEventListener("click", () => {
                localStorage.setItem("leido_" + capituloId, "true");
            });
        });
    });
</script>



</html>