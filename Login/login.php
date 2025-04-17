<?php
include("../modelo/conexion.php");

$destacados = $conexion->query("SELECT * FROM comics ORDER BY id ASC LIMIT 5");
$catalogo = $conexion->query("SELECT * FROM comics ORDER BY id ASC LIMIT 100 OFFSET 5");
$generos = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'manga'");
$generosn = $conexion->query("SELECT DISTINCT genero FROM comics WHERE tipo = 'Comic'");

session_start(); // Iniciar sesión

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["Email"]);
    $contraseña = trim($_POST["Contraseña"]);

    if (!empty($email) && !empty($contraseña)) {
        // Incluimos el campo 'rol' en la consulta
        $stmt = $conexion->prepare("SELECT id, Email, Contraseña, usuario, rol FROM usuarios WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $email, $hashed_password, $nombre, $rol);
            $stmt->fetch();

            if (password_verify($contraseña, $hashed_password)) {
                // Guardar la sesión del usuario con su rol
                $_SESSION["user_id"] = $id;
                $_SESSION["user_email"] = $email;
                $_SESSION["usuario"] = $nombre;
                $_SESSION["rol"] = $rol;

                $stmt->close();

                // Redirigir según el rol
                if ($rol === 'admin') {
                    header("Location: ../dashboard.php");
                } else {
                    header("Location: ../index.php");
                }
                exit();
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
        $stmt->close();
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House - Login</title>
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="slider.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" href="../ico.ico">


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


    <div class="Login-ctn">
    
    <h1>INGRESAR</h1>

        <form method="post">
            <div class="Login">
                <div class="Login-itm">


                    

                    <div class="email">
                        <label>Direccion de email</label>
                        <input class="ema" type="email" name="Email" placeholder="Nombre@gmail.com">
                    </div>

                    <div class="contraseña">
                        <label>Contraseña</label>
                        <input class="contra" type="password" name="Contraseña" placeholder="Contraseña">
                    </div>

                    <div class="siguiente">
                        <button class="sig" type="submit">Siguente</button>
                    </div>

                    <div class="lab">
                        <label>Si no portas con una cuenta: <a class="reg" href="../Registro/registro.php">Registrate
                                Aquí</a></label>
                    </div>

                </div>



                <?php if (!empty($error)): ?>
                    <p style="color: red;"><?= $error ?></p>
                <?php endif; ?>

        </form>
    </div>
    </div>
</body>

</html>