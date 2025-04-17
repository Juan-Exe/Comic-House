<?php include("modelo/conexion.php");
session_start();
$destacados = $conexion->query("SELECT * FROM comics ORDER BY id ASC LIMIT 5");
$catalogo = $conexion->query("SELECT * FROM comics ORDER BY id ASC LIMIT 100 OFFSET 5");
$generos = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'manga'");
$generosn = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'Comic'");


?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House</title>
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="ico.ico">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


    <script src="js/script.js"></script>
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
                <img src="Imagenes/Logo-Comic-Huse.png" alt="">
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
                            <a href="P-comics/p-comics.php" class="dropdown-toggle hover:text-gray-200">Comics
                                <span>▾</span></a>
                            <ul
                                class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                                <?php while ($gen = $generosn->fetch_object()) { ?>
                                    <li>
                                        <a href="P-comics/p-comics.php?genero=<?= urlencode($gen->genero) ?>"
                                            class="block px-4 py-2 hover:bg-red-700">
                                            <?= htmlspecialchars($gen->genero) ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </li>
                        <li class="relative group">
                            <a href="P-Mangas/mangas.php" class="dropdown-toggle hover:text-gray-200">Mangas
                                <span>▾</span></a>
                            <ul
                                class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                                <?php while ($gen = $generos->fetch_object()) { ?>
                                    <li>
                                        <a href="P-Mangas/mangas.php?genero=<?= urlencode($gen->genero) ?>"
                                            class="block px-4 py-2 hover:bg-red-700">
                                            <?= htmlspecialchars($gen->genero) ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </li>
                        <li><a href="Biblioteca/index.php" class="hover:text-gray-200">Mi biblioteca</a></li>
                        <li class="relative group list-none user cursor-pointer">
                            <?php if (isset($_SESSION['usuario'])): ?>
                                <a href="#" class="dropdown-toggle hover:text-gray-200 flex items-center">
                                    <img src="Imagenes/pngegg.png" alt="Usuario" class="w-6 h-6 inline">
                                    <span class="ml-2"><?php echo htmlspecialchars($_SESSION['usuario']); ?> ▾</span>
                                </a>
                                <ul
                                    class="submenu absolute bg-red-600 text-white invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                                    <li>
                                        <a href="logout.php" class="block px-4 py-2 hover:bg-red-700">Cerrar sesión</a>
                                    </li>
                                </ul>
                            <?php else: ?>
                                <a href="Login/login.php" class="flex items-center hover:text-gray-200">
                                    <img src="Imagenes/pngegg.png" alt="Usuario" class="w-6 h-6 inline">
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






    <div class="carousel-container">
        <div class="swiper mySwiper">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <a href="Comics/comics.php?id=13" class="slide-link">
                        <div class="sly-content"
                            style="background-image: url('https://images8.alphacoders.com/418/thumb-1920-418849.jpg');">
                            <img src="Logos/House_of_M_Vol_2_Logo.png" alt="Logo" class="logo">
                            <div class="info-box">
                                <p class="Title">Publicacion Terminada</p>
                                <p class="description"><span>+13</span>2005 · crossover ficcional</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="swiper-slide">
                    <a href="Comics/comics.php?id=7" class="slide-link">
                        <div class="sly-content"
                            style="background-image: url('https://images7.alphacoders.com/666/thumb-1920-666343.jpg');">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/f/f1/Berserk_anime_logo.png"
                                alt="Logo" class="logo">
                            <div class="info-box">
                                <p class="Title">Publicandose...</p>
                                <p class="description"><span>+18</span>1989 · Fantasia oscura</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="swiper-slide">
                    <a href="Comics/comics.php?id=11" class="slide-link">
                        <div class="sly-content"
                            style="background-image: url('https://es.gizmodo.com/app/uploads/2019/11/ciplqnpuxlqxvbeg5eev.jpg');">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/f/f3/Dragon_Ball_anime_logo.png"
                                alt="Logo" class="logo">
                            <div class="info-box">
                                <p class="Title">Publicacion Terminada</p>
                                <p class="description"><span>+13</span>1984 · accion, fantasia</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>

    <style>
        .slide-link {
            display: block;

            text-decoration: none;
        }


        .carousel-container {
            width: 100%;
            height: 630px;
            display: flex;
            justify-content: center;
            align-items: center;
        }


        .swiper-slide {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .sly-content {
            width: 1170px;
            height: 496px;
            border: 3px solid #CE4646;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding-left: 20px;
            position: relative;
            overflow: hidden;
            background-position: center;
            background-size: cover;
        }

        .sly-content::before {
            content: "";
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: linear-gradient(to left, rgba(0, 0, 0, 0) 30%, rgba(0, 0, 0, 0.8) 100%);
        }


        .info-box {
            position: relative;
            top: 150px;
            right: 200px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 20px;
            color: white;
        }

        .box-tex {
            position: relative;
            background-color: #CE4646;
            border-radius: 4px;
            padding: 1px 5px;
            top: -15px;
            left: 3px;
            font-size: 10px;
        }

        .descri {
            position: relative;
            top: -32px;
            left: 35px;
            font-size: 10px;
        }

        .logo {
            position: relative;
            width: 256px;
            height: auto;
            margin-bottom: 10px;
            left: 65px;
        }


        .Title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 15px;

        }

        .description {
            font-size: 13px;
        }

        .description span {
            text-align: left;
            font-size: 15px;
            background: #CE4646;
            border-radius: 3px;
            padding: 5px;
            margin-right: 10px;
            color: #FFF;
            font-weight: 700;
        }


        .swiper-button-prev,
        .swiper-button-next {
            color: #CE4646;
            padding: 10px;
            transition: background-color 0.3s, transform 0.3s;
            position: absolute;
            top: 50%;
            transform: translateZ(-50%);
            z-index: 5;
        }

        .swiper-button-prev {
            left: 70px;
        }

        .swiper-button-next {
            right: 70px;
        }

        .swiper-button-prev:hover,
        .swiper-button-next:hover {
            transform: scale(1.5);
        }

        .degradado-izquierdo,
        .degradado-derecho {
            position: absolute;
            top: 252px;
            width: 40px;
            height: 496px;
            z-index: 2;
            pointer-events: none;
        }



        .degradado-izquierdo {
            left: 0;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.8), transparent);
        }

        .degradado-derecho {
            right: 0;
            background: linear-gradient(to left, rgba(0, 0, 0, 0.8), transparent);
        }



        @media (max-width: 768px) {

            .slide-link {
                display: block;

                text-decoration: none;
            }


            .carousel-container {
                width: 100%;
                height: 430px;
                display: flex;
                justify-content: center;
                align-items: center;
            }


            .swiper-slide {
                display: flex;
                justify-content: center;
                align-items: center;
                position: relative;
            }

            .sly-content {
                width: 360px;
                height: 400px;
                border: 3px solid #CE4646;
                border-radius: 10px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                position: relative;


            }


            .sly-content::before {
                content: "";
                position: absolute;
                width: 100%;
                height: 100%;
                top: 0;
                left: 0;
                background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 30%, rgba(0, 0, 0, 0.8) 100%);
            }


            .info-box {
                position: absolute;
                bottom: 20px;
                left: 0;
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: flex-end;
                color: white;
                z-index: 2;
                text-align: center;
                padding: 10px;
            }


            .box-tex {
                position: relative;
                background-color: #CE4646;
                border-radius: 4px;
                padding: 1px 5px;
                top: -15px;
                left: 3px;
                font-size: 10px;
            }

            .descri {
                position: relative;

                font-size: 10px;
            }

            .logo {
                width: 150px;
                height: auto;
                margin-bottom: 0;
                left: 0;
            }


            .Title {
                font-size: 15px;
                font-weight: bold;
                margin-top: 0;
                margin-bottom: 15px;

            }

            .description {
                text-align: center;
                font-size: 12px;
            }

            .description span {
                text-align: center;
                font-size: 10px;
                background: #CE4646;
                border-radius: 3px;
                padding: 5px;
                margin-right: 10px;
                color: #FFF;
                font-weight: 700;
            }



            .swiper-button-next,
            .swiper-button-prev {
                display: none !important;
            }

            
    </style>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>

        var swiper = new Swiper(".mySwiper", {
            slidesPerView: 1,
            spaceBetween: 10,
            loop: true,
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            allowTouchMove: true
        });


    </script>



    <div class="titulo">
        <h2 class="editoriales">EDITORIALES DESTACADAS</h2>
    </div>

    <div class="editoriales-btn">
        <div class="editoriales-container">
            <div class="editoriales-btns">
                <button class="editorial-btnss" onclick="location.href='Marvel/index.php'"></button>
            </div>
            <div class="editoriales-btns">
                <button class="editorial-btnss" onclick="location.href='Dc/index.php'"></button>
            </div>
            <div class="editoriales-btns">
                <button class="editorial-btnss" onclick="location.href='Image/index.php'"></button>
            </div>
            <div class="editoriales-btns">
                <button class="editorial-btnss" onclick="location.href='Shonen/index.php'"></button>
            </div>
            <div class="editoriales-btns">
                <button class="editorial-btnss" onclick="location.href='Young Animal/index.php'"></button>
            </div>
        </div>
    </div>

    <div class="Comics-titulo">
        <div class="titulo-comics-ctn">
            <h2>Comics Imprecindibles</h2>
        </div>
    </div>

    <div class="Comics-base">
    <div class="Comics-slider-wrapper">
        <div class="Comics-container">
            <?php while ($datos = $destacados->fetch_object()) { ?>
                <div class="Comics-btn">
                    <button class="Comics-btns" onclick="location.href='Comics/comics.php?id=<?= $datos->id ?>'"
                        style="background-image: url('uploads/<?= $datos->portada ?>');">
                    </button>
                    <div class="Comics-info">
                        <p class="title-c"><?= htmlspecialchars($datos->titulo) ?></p>
                        <p class="description-c"><span><?= htmlspecialchars($datos->clasificacion) ?></span>
                            n°.<?= htmlspecialchars($datos->capitulos) ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>




    <div class="Comics-titulo-2">
        <div class="titulo-comics-ctn-2">
            <h2>Nuestro Catalogo: Comics - Manga</h2>
        </div>
    </div>

    <div class="Comics-base-2">
        <div class="Comics-container-2">
            <?php while ($datos = $catalogo->fetch_object()) { ?>
                <div class="Comics-btn-2">
                    <button class="Comics-btns-2" onclick="location.href='Comics/comics.php?id=<?= $datos->id ?>'"
                        style="background-image: url('uploads/<?= $datos->portada ?>');">
                    </button>
                    <div class="Comics-info-2">
                        <p class="title-c-2"><?= htmlspecialchars($datos->titulo) ?></p>
                        <p class="description-c-2"><span><?= htmlspecialchars($datos->clasificacion) ?></span>
                            n°.<?= htmlspecialchars($datos->capitulos) ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>


    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2024 Comic House. Todos los derechos reservados.</p>
            <nav>
                <a href="#">Términos y Condiciones</a>
                <a href="#">Política de Privacidad</a>
                <a href="#">Contacto</a>
            </nav>
        </div>
    </footer>




</body>

</html>