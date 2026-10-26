<?php
/**
 * Compone «Obra: Capítulo» sin repetir el nombre de la obra.
 *
 * Algunos capítulos ya traen el título dentro ('Saga: Capítulo Uno'),
 * y concatenarlo a ciegas producía «Saga: Saga: Capítulo Uno».
 */
function tituloCapitulo(string $obra, string $capitulo): string
{
    $obra = trim($obra);
    $capitulo = trim(rtrim(trim($capitulo), ':,'));

    if ($capitulo === '') {
        return $obra;
    }
    // ¿El capítulo ya empieza por el nombre de la obra?
    if (stripos($capitulo, $obra) === 0) {
        $resto = trim(ltrim(substr($capitulo, strlen($obra)), " :-–"));
        return $resto === '' ? $obra : $obra . ': ' . $resto;
    }
    return $obra . ': ' . $capitulo;
}

// La página se arma con cada visita: sin esto el navegador reutiliza
// una copia antigua y los cambios del lector no llegan hasta vaciar
// la caché a mano.
header('Cache-Control: no-store, must-revalidate');

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
  <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>" />
  <link rel="stylesheet" href="fonts.css" />
  <link rel="icon" href="../ico.ico">
</head>

<body>

<!-- Capa de luz de ambiente, detrás de todo el contenido. -->
<div id="ambiente" aria-hidden="true"></div>


  <div class="header">
    <div class="left-section">
      <div class="logo-m">
        <a href="../index.php">
          <img src="../Imagenes/Logo-Comic-Huse.png" alt="">
        </a>
      </div>
      <h1><?= htmlspecialchars(tituloCapitulo($capitulo['comic_titulo'], $capitulo['capitulo_titulo'])) ?></h1>
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

    <!-- Columna derecha del grid: interruptor del modo ambiente. -->
    <div class="right-section">
      <button type="button" class="btn-ambiente" id="btn-ambiente"
              aria-pressed="true" title="Luz de ambiente del fondo">
        <i class="bi bi-lightbulb"></i>
        <span>Ambiente</span>
      </button>
    </div>
  </div>

  <div class="head-r" id="responsive-header">
    <div class="head-r--cont">
      <a href="../index.php" class="back-arrow">←</a>
      <h1><?= htmlspecialchars(tituloCapitulo($capitulo['comic_titulo'], $capitulo['capitulo_titulo'])) ?></h1>
    </div>
  </div>


  <div class="coso"></div>

  <div class="comic-container">
    <div class="comic" id="comic-pages">
      <?php while ($pagina = $paginas->fetch_assoc()): ?>
        <?php
        $url      = htmlspecialchars($pagina['imagen_url']);
        $es_doble = !empty($pagina['es_doble']);
        ?>
        <?php if ($es_doble): ?>
          <div class="pagina-franja">
            <div class="comic-page-wrapper">
              <img loading="lazy" class="comic-page doble" src="../<?= $url ?>" alt="Página del cómic" />
            </div>
          </div>
        <?php else: ?>
          <div class="pagina-franja">
            <img loading="lazy" class="comic-page" src="../<?= $url ?>" alt="Página del cómic" />
          </div>
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

<script>
/* Ambient light.

   Una sola capa fija detrás de todo. Cada página visible deja en ella
   una mancha con su propia imagen, muy desenfocada y con los bordes
   desvanecidos; al solaparse, los colores de páginas contiguas se
   mezclan de verdad en lugar de tocarse por una costura.

   La mancha sigue a su página al hacer scroll, así que la luz baja con
   el cómic y va transicionando de un color al siguiente. */
(function () {
    const capa = document.getElementById('ambiente');
    if (!capa) return;

    const paginas = [...document.querySelectorAll('.comic-page')];
    const manchas = new Map();      // página -> su mancha en la capa

    /* ---- Interruptor ----
       A quien le moleste el efecto puede apagarlo, y la decisión se
       recuerda entre visitas. */
    const CLAVE = 'comichouse:ambiente';
    const boton = document.getElementById('btn-ambiente');
    // Apagado de entrada: la página abre en negro y es el lector quien
    // decide encender la luz, no al revés.
    let encendido = localStorage.getItem(CLAVE) === 'on';

    function aplicar() {
        capa.classList.toggle('apagado', !encendido);
        if (boton) {
            boton.classList.toggle('apagado', !encendido);
            boton.setAttribute('aria-pressed', String(encendido));
            boton.querySelector('i').className = encendido
                ? 'bi bi-lightbulb' : 'bi bi-lightbulb-off';
            boton.title = encendido
                ? 'Apagar la luz de ambiente' : 'Encender la luz de ambiente';
        }
    }

    if (boton) {
        let limpieza = null;

        boton.addEventListener('click', () => {
            encendido = !encendido;
            try { localStorage.setItem(CLAVE, encendido ? 'on' : 'off'); } catch (e) {}
            aplicar();

            clearTimeout(limpieza);
            if (encendido) {
                colocar();              // vuelve a pintar y entra con fundido
            } else {
                // Se espera a que la capa termine de desvanecerse para
                // soltar las manchas; borrarlas antes cortaría la salida.
                limpieza = setTimeout(() => {
                    for (const m of manchas.values()) m.remove();
                    manchas.clear();
                }, 600);
            }
        });
    }
    aplicar();

    function crearMancha(img) {
        const m = document.createElement('img');
        m.className = 'mancha';
        m.setAttribute('aria-hidden', 'true');
        m.alt = '';
        m.src = img.currentSrc || img.src;
        capa.appendChild(m);
        requestAnimationFrame(() => m.classList.add('visible'));
        return m;
    }

    function colocar() {
        // Apagado: no se crean manchas nuevas. Las que haya se borran
        // al terminar el fundido, no antes, para que la salida se vea.
        if (!encendido) return;

        const alto = window.innerHeight;
        // Margen generoso: una página fuera de pantalla todavía tiñe
        // los bordes, que es lo que hace continua la transición.
        const margen = alto * 0.8;

        for (const img of paginas) {
            const caja = img.getBoundingClientRect();
            const visible = caja.bottom > -margen && caja.top < alto + margen;

            if (visible) {
                if (!manchas.has(img) && img.complete && img.naturalWidth) {
                    manchas.set(img, crearMancha(img));
                }
                const m = manchas.get(img);
                if (m) {
                    // La mancha se extiende más allá de la página para
                    // que su color alcance al de la página vecina.
                    const extra = alto * 0.45;
                    m.style.top = (caja.top - extra) + 'px';
                    m.style.height = (caja.height + extra * 2) + 'px';
                }
            } else if (manchas.has(img)) {
                manchas.get(img).remove();
                manchas.delete(img);
            }
        }
    }

    let pendiente = false;
    function alDesplazar() {
        if (pendiente) return;
        pendiente = true;
        requestAnimationFrame(() => { colocar(); pendiente = false; });
    }

    window.addEventListener('scroll', alDesplazar, { passive: true });
    window.addEventListener('resize', alDesplazar);

    // Las páginas llegan con carga diferida: cada una se incorpora al
    // terminar de cargar.
    for (const img of paginas) {
        if (!img.complete) img.addEventListener('load', alDesplazar, { once: true });
    }
    colocar();
})();
</script>

<script>
/* Desplazamiento suave con Lenis.

   Sustituye a una interpolación propia que no llegaba a sentirse: el
   navegador aplica su propio desplazamiento por hardware antes de que
   un script pueda intervenir, y Lenis lo resuelve tomando el control
   del scroll en lugar de pelearse con él.

   Los ajustes son los mismos del portafolio del autor, donde ya está
   calibrado a su gusto. */
(function () {
    if (typeof Lenis === 'undefined') return;   // sin red, scroll normal

    /* Con «reducir movimiento» activado en el sistema, el suavizado no
       se apaga: en un lector de cómics el desplazamiento continuo es
       la forma de leer, no un adorno. Lo que sí se hace es acortar la
       animación, para que acompañe al gesto sin prolongarlo. */
    const menosMovimiento =
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;


    const lenis = new Lenis({
        duration: menosMovimiento ? 0.6 : 1.55,
        easing: t => 1 - Math.pow(1 - t, 4),
        smoothWheel: true,
        smoothTouch: false,
        wheelMultiplier: 0.85,
        touchMultiplier: 1.1
    });

    function raf(tiempo) {
        lenis.raf(tiempo);
        requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

})();
</script>

</body>

</html>