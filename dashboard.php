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
$total_paginas = $conexion->query("SELECT COUNT(*) as n FROM paginas")->fetch_object()->n;

// Lo último que se subió: da un punto de partida al abrir el panel,
// en vez de solo contadores que no llevan a ninguna parte.
$recientes = $conexion->query(
    "SELECT c.titulo, c.portada, k.numero, k.id,
            (SELECT COUNT(*) FROM paginas p WHERE p.capitulo_id = k.id) AS paginas
       FROM capitulos k
       JOIN comics c ON c.id = k.comic_id
      ORDER BY k.id DESC
      LIMIT 4"
);
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
    <link rel="stylesheet" href="admin/admin.css">
    <style>
        /* Propio del panel de inicio. El resto viene de admin/admin.css. */

        .saludo { margin-bottom: 34px; }
        .saludo h1 { font-size: var(--t-grande); font-weight: 800; letter-spacing: -.6px; }
        .saludo h1 b { color: var(--rojo); font-weight: 800; }
        .saludo p { color: var(--texto-medio); margin-top: 2px; }

        /* Cifras del catálogo: una fila sobria, sin tarjeta cada una.
           Son un dato de contexto, no el contenido de la pantalla. */
        .cifras {
            display: flex;
            flex-wrap: wrap;
            gap: 46px;
            padding-bottom: 26px;
            border-bottom: 1px solid var(--linea);
            margin-bottom: 38px;
        }
        .cifra b {
            display: block;
            font-size: 32px;
            font-weight: 800;
            line-height: 1.1;
        }
        .cifra span { font-size: var(--t-nota); color: var(--texto-medio); }

        /* Accesos: lista de enlaces con icono, no una rejilla de cajas.
           El primero es el que más se usa y va destacado. */
        .accesos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 10px;
            margin-bottom: 38px;
        }
        .acceso {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 0;
            border-bottom: 2px solid var(--linea);
            color: var(--texto);
            text-decoration: none;
            transition: var(--paso);
        }
        .acceso:hover { border-bottom-color: var(--rojo); }
        .acceso i { font-size: 19px; color: var(--texto-medio); flex-shrink: 0; transition: var(--paso); }
        .acceso:hover i { color: var(--rojo); }
        .acceso b { font-weight: 600; }

        /* Lo último subido */
        .ultimos { display: flex; flex-direction: column; }
        .ultimo {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 11px 2px;
            border-bottom: 1px solid var(--linea);
        }
        .ultimo:last-child { border-bottom: none; }
        .ultimo img {
            width: 38px; height: 54px;
            object-fit: cover;
            border-radius: 3px;
            flex-shrink: 0;
        }
        .ultimo-centro { flex: 1; min-width: 0; }
        .ultimo-centro b { font-weight: 600; display: block; }
        .ultimo-centro span { font-size: var(--t-nota); color: var(--texto-medio); }

    </style>
</head>
<body>

<?php
$titulo = 'Administración';
require __DIR__ . '/admin/cabecera.php';
?>

</div>

<main class="pagina">

    <div class="saludo">
        <h1>Hola, <b><?= htmlspecialchars($usuario) ?></b></h1>
        <p>Esto es lo que hay en el catálogo ahora mismo.</p>
    </div>

    <div class="cifras">
        <div class="cifra">
            <b><?= $total_comics ?></b>
            <span>cómics</span>
        </div>
        <div class="cifra">
            <b><?= $total_caps ?></b>
            <span>capítulos</span>
        </div>
        <div class="cifra">
            <b><?= number_format($total_paginas, 0, ',', '.') ?></b>
            <span>páginas</span>
        </div>
        <div class="cifra">
            <b><?= $total_usuarios ?></b>
            <span>usuarios</span>
        </div>
    </div>

    <div class="seccion">
        <h2>Gestionar</h2>
    </div>

    <nav class="accesos">
        <a href="subir_capitulo.php" class="acceso">
            <i class="bi bi-upload"></i>
            <b>Subir capítulo</b>
        </a>
        <a href="crud.php" class="acceso">
            <i class="bi bi-collection"></i>
            <b>Cómics del catálogo</b>
        </a>
        <a href="destacar.php" class="acceso">
            <i class="bi bi-star"></i>
            <b>Destacados del slider</b>
        </a>
        <a href="editoriales.php" class="acceso">
            <i class="bi bi-buildings"></i>
            <b>Editoriales</b>
        </a>
    </nav>

    <?php if ($recientes->num_rows > 0): ?>
        <div class="seccion">
            <h2>Subido últimamente</h2>
        </div>

        <div class="ultimos">
            <?php while ($r = $recientes->fetch_assoc()): ?>
                <div class="ultimo">
                    <img src="uploads/<?= htmlspecialchars($r['portada']) ?>"
                         alt="Portada de <?= htmlspecialchars($r['titulo']) ?>">
                    <div class="ultimo-centro">
                        <b><?= htmlspecialchars($r['titulo']) ?></b>
                        <span>
                            Capítulo <?= htmlspecialchars($r['numero']) ?>,
                            <?= (int) $r['paginas'] ?> páginas
                        </span>
                    </div>
                    <a href="Lector/lector.php?capitulo_id=<?= (int) $r['id'] ?>"
                       class="btn btn-plano">Abrir</a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>


</main>

</body>
</html>
