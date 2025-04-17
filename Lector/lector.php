<?php
include("../modelo/conexion.php");
if ($conexion->connect_error) {
  die("Error de conexión: " . $conexion->connect_error);
}

if (!isset($_GET['capitulo_id'])) {
  die("Error: No se recibió el ID del capítulo.");
}

$capitulo_id = intval($_GET['capitulo_id']);

$consulta_capitulo = "
    SELECT c.titulo AS comic_titulo, 
           cap.titulo AS capitulo_titulo, 
           cap.numero AS capitulo_numero,
           cap.comic_id AS comic_id
    FROM capitulos cap
    JOIN comics c ON cap.comic_id = c.id
    WHERE cap.id = $capitulo_id
";

$resultado_capitulo = $conexion->query($consulta_capitulo);

if (!$resultado_capitulo || $resultado_capitulo->num_rows === 0) {
  die("Capítulo no encontrado.");
}

$capitulo = $resultado_capitulo->fetch_assoc();
$comic_id = $capitulo['comic_id'];

$consulta_paginas = "
    SELECT * FROM paginas 
    WHERE capitulo_id = $capitulo_id 
    ORDER BY id ASC
";

$paginas = $conexion->query($consulta_paginas);

if (!$paginas) {
  die("Error al obtener páginas: " . $conexion->error);
}

$capitulo_anterior = $conexion->query("
    SELECT id FROM capitulos 
    WHERE id < $capitulo_id AND comic_id = $comic_id 
    ORDER BY id DESC 
    LIMIT 1
");

$capitulo_siguiente = $conexion->query("
    SELECT id FROM capitulos 
    WHERE id > $capitulo_id AND comic_id = $comic_id 
    ORDER BY id ASC 
    LIMIT 1
");

$id_anterior = $capitulo_anterior->num_rows > 0 ? $capitulo_anterior->fetch_assoc()['id'] : null;
$id_siguiente = $capitulo_siguiente->num_rows > 0 ? $capitulo_siguiente->fetch_assoc()['id'] : null;

$total_paginas = $paginas->num_rows;
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($capitulo['comic_titulo']) ?> - Capítulo
    <?= htmlspecialchars($capitulo['capitulo_numero']) ?>
  </title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="fonts.css" />
  <link rel="icon" href="../ico.ico">
</head>

<body>

  <div class="header">
    <div class="left-section">
      <div class="logo-m">
        <a href="../index.php">
          <img src="../Imagenes/Logo-Comic-Huse.png" alt="">
        </a>
      </div>
      <h1><?= htmlspecialchars($capitulo['comic_titulo']) ?>: <?= htmlspecialchars($capitulo['capitulo_titulo']) ?></h1>
    </div>

    <div class="controls">
      <?php if ($id_anterior): ?>
        <a href="lector.php?capitulo_id=<?= $id_anterior ?>"><button class="buttons">◀</button></a>
      <?php else: ?>
        <button class="buttons no-arrow">◀</button>
      <?php endif; ?>

      <span id="page-indicator"><?= htmlspecialchars($capitulo['capitulo_numero']) ?></span>

      <?php if ($id_siguiente): ?>
        <a href="lector.php?capitulo_id=<?= $id_siguiente ?>"><button class="buttons">▶</button></a>
      <?php else: ?>
        <button class="buttons no-arrow">▶</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="head-r" id="responsive-header">
    <div class="head-r--cont">
      <a href="../index.php" class="back-arrow">←</a>
      <h1><?= htmlspecialchars($capitulo['comic_titulo']) ?>: <?= htmlspecialchars($capitulo['capitulo_titulo']) ?></h1>
    </div>
  </div>


  <div class="coso"></div>

  <div class="comic-container">
    <div class="comic" id="comic-pages">
      <?php while ($pagina = $paginas->fetch_assoc()): ?>
        <?php
        $url = htmlspecialchars($pagina['imagen_url']);
        $nombre_archivo = basename($url);
        $es_doble = preg_match('/_\d+_\d+_\d+\./', $nombre_archivo);
        $clase_extra = $es_doble ? ' doble' : '';
        ?>

        <?php if ($es_doble): ?>
          <div class="comic-page-wrapper">
            <img loading="lazy" class="comic-page<?= $clase_extra ?>" src="../<?= $url ?>" alt="Página del cómic" />
          </div>
        <?php else: ?>
          <img loading="lazy" class="comic-page" src="../<?= $url ?>" alt="Página del cómic" />
        <?php endif; ?>
      <?php endwhile; ?>

    </div>
  </div>

  <div class="foot" id="responsive-footer">
    <div class="foot-cont">

      <?php if ($id_anterior): ?>
        <a href="lector.php?capitulo_id=<?= $id_anterior ?>"><button class="buttons">◀</button></a>
      <?php else: ?>
        <button class="buttons no-arrow">◀</button>
      <?php endif; ?>

      <span id="page-indicator"><?= htmlspecialchars($capitulo['capitulo_numero']) ?></span>

      <?php if ($id_siguiente): ?>
        <a href="lector.php?capitulo_id=<?= $id_siguiente ?>"><button class="buttons">▶</button></a>
      <?php else: ?>
        <button class="buttons no-arrow">▶</button>
      <?php endif; ?>

    </div>
  </div>

  <script>
    let lastScrollTop = 0;
    const header = document.querySelector('.header');
    const delta = 5; // Tolerancia mínima para detectar el scroll
    let didScroll;

    window.addEventListener('scroll', function () {
      didScroll = true;
    });

    setInterval(function () {
      if (didScroll) {
        hasScrolled();
        didScroll = false;
      }
    }, 100);

    function hasScrolled() {
      const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

      if (Math.abs(lastScrollTop - currentScroll) <= delta) {
        return;
      }

      if (currentScroll > lastScrollTop) {

        header.classList.add('hide');
      } else {

        header.classList.remove('hide');
      }

      lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    }
  </script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const header = document.getElementById("responsive-header");
        const footer = document.getElementById("responsive-footer");

        let visible = true;

        const comicContainer = document.querySelector('.comic-container');

        comicContainer.addEventListener('click', () => {
            visible = !visible;
            header.classList.toggle('hidden', !visible);
            footer.classList.toggle('hidden', !visible);
        });

        // Opcional: prevenir propagación si se hace clic en header/footer
        header.addEventListener('click', e => e.stopPropagation());
        footer.addEventListener('click', e => e.stopPropagation());
    });
</script>





</body>

</html>