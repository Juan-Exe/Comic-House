<?php
session_start();
require_once __DIR__ . '/controlador/miniaturas.php';
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'toggle') {
        $id       = (int)$_POST['id'];
        $destacado = (int)$_POST['destacado'];
        $estado   = $conexion->real_escape_string($_POST['estado_publicacion']);
        $img_slider  = '';
        $logo_slider = '';

        if (!empty($_FILES['imagen_slider']['name'])) {
            $ext = pathinfo($_FILES['imagen_slider']['name'], PATHINFO_EXTENSION);
            $img_slider = 'slider_' . $id . '_bg.' . $ext;
            move_uploaded_file($_FILES['imagen_slider']['tmp_name'], "uploads/" . $img_slider);
        }
        if (!empty($_FILES['logo_slider']['name'])) {
            $ext = pathinfo($_FILES['logo_slider']['name'], PATHINFO_EXTENSION);
            $logo_slider = 'slider_' . $id . '_logo.' . $ext;
            move_uploaded_file($_FILES['logo_slider']['tmp_name'], "uploads/" . $logo_slider);
        }

        if ($img_slider && $logo_slider) {
            $conexion->query("UPDATE comics SET destacado=$destacado, estado_publicacion='$estado', imagen_slider='$img_slider', logo_slider='$logo_slider' WHERE id=$id");
        } elseif ($img_slider) {
            $conexion->query("UPDATE comics SET destacado=$destacado, estado_publicacion='$estado', imagen_slider='$img_slider' WHERE id=$id");
        } elseif ($logo_slider) {
            $conexion->query("UPDATE comics SET destacado=$destacado, estado_publicacion='$estado', logo_slider='$logo_slider' WHERE id=$id");
        } else {
            $conexion->query("UPDATE comics SET destacado=$destacado, estado_publicacion='$estado' WHERE id=$id");
        }
    }
    header("Location: destacar.php?ok=1"); exit();
}

$comics = $conexion->query("SELECT * FROM comics ORDER BY destacado DESC, titulo ASC");
$msg = isset($_GET['ok']) ? 'Cambios guardados correctamente.' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Destacar cómics</title>
    <link rel="icon" href="ico.ico">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="admin/admin.css">
    <style>
        /* Propio de esta pantalla: el conmutador y el par de subidas.
           Todo lo demás viene de admin/admin.css. */

        /* Conmutador de destacado */
        .palanca { display: inline-flex; align-items: center; gap: 9px; cursor: pointer; }
        .palanca input { display: none; }
        .palanca-pista {
            width: 40px; height: 22px;
            background: var(--estante-alto);
            border: 1px solid var(--linea);
            border-radius: var(--r-pil);
            position: relative;
            transition: var(--paso);
            flex-shrink: 0;
        }
        .palanca-pista::after {
            content: "";
            position: absolute;
            top: 2px; left: 2px;
            width: 16px; height: 16px;
            background: var(--texto-bajo);
            border-radius: 50%;
            transition: var(--paso);
        }
        .palanca input:checked + .palanca-pista {
            background: var(--rojo);
            border-color: var(--rojo);
        }
        .palanca input:checked + .palanca-pista::after {
            transform: translateX(18px);
            background: #fff;
        }
        .palanca input:focus-visible + .palanca-pista {
            outline: 2px solid var(--rojo);
            outline-offset: 2px;
        }
        .palanca-texto { font-size: var(--t-nota); font-weight: 600; }

        /* Par de subidas: fondo y logo del slider */
        .par-subidas { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .mini-subir {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 10px 6px;
            min-height: 66px;
            background: var(--papel);
            border: 1.5px dashed var(--linea);
            border-radius: var(--r);
            color: var(--texto-bajo);
            font-size: var(--t-nota);
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: var(--paso);
            position: relative;
            overflow: hidden;
        }
        .mini-subir:hover { border-color: var(--rojo); color: var(--texto); }
        .mini-subir input { display: none; }
        .mini-subir i { font-size: 15px; }
        .mini-subir.lista { border-style: solid; border-color: var(--rojo-linea); color: var(--texto-medio); }
        /* La imagen se ve ENTERA y centrada; el hueco que deja a los
           lados lo rellena una copia suya desenfocada, en vez de
           quedar en negro. Es el mismo recurso del lector. */
        .mini-subir .ambiente {
            position: absolute;
            inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            filter: blur(14px) saturate(1.6) brightness(.5);
            transform: scale(1.3);
            z-index: 0;
        }
        /* El fondo del slider llena la miniatura: es apaisado y no deja
           hueco. */
        .mini-subir .cubre {
            position: absolute;
            inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            opacity: .55;
            z-index: 0;
        }
        .mini-subir .real {
            position: relative;
            z-index: 1;
            max-width: 100%;
            max-height: 52px;
            object-fit: contain;
        }
        /* Con imagen cargada, el texto se aparta para no taparla. */
        .mini-subir.lista i,
        .mini-subir.lista span:not(.etiqueta) { display: none; }
        .mini-subir .etiqueta {
            position: absolute;
            bottom: 3px; left: 0; right: 0;
            z-index: 2;
            font-size: 9.5px;
            color: rgba(255,255,255,.82);
            text-shadow: 0 1px 3px rgba(0,0,0,.9);
        }
        .mini-subir span { position: relative; z-index: 1; }

        /* La portada se ve ENTERA y centrada; el espacio que deja
           arriba y abajo lo rellena una copia suya desenfocada, en vez
           de quedar en negro. */
        .ficha-portada {
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            isolation: isolate;
        }
        /* La luz es la MISMA imagen estirada a todo el alto de la
           columna, sin recortar: así cada zona de la portada continúa
           hacia arriba y hacia abajo con su propio color (el cielo de
           Batman arriba, la entrada abajo) en vez de mostrar un trozo
           suelto de la imagen. */
        .ficha-portada .luz {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: fill;
            filter: blur(24px) saturate(1.45) brightness(.8);
            z-index: -1;
        }
        /* Un velo suave en los extremos, para que la luz se apague
           hacia el borde de la fila sin cortarse. */
        .ficha-portada::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom,
                        rgba(0,0,0,.4) 0%, rgba(0,0,0,0) 22%,
                        rgba(0,0,0,0) 78%, rgba(0,0,0,.4) 100%);
            pointer-events: none;
            z-index: 0;
        }
        /* La portada, entera y centrada en la columna. El alto lo
           marca su proporción, no el de la caja, de modo que arriba y
           abajo queda el hueco que llena la luz.

           Sus bordes superior e inferior se desvanecen con una máscara,
           así la imagen no termina en un corte seco sino que se funde
           con su propio difuminado. */
        .ficha-portada .obra {
            position: relative;
            width: 100%;
            height: auto;
            max-height: 100%;
            object-fit: contain;
            align-self: center;
            z-index: 1;
            -webkit-mask-image: linear-gradient(to bottom,
                        transparent 0%, #000 13%, #000 87%, transparent 100%);
            mask-image: linear-gradient(to bottom,
                        transparent 0%, #000 13%, #000 87%, transparent 100%);
        }

        /* Marca de destacado sobre la portada */
        .en-portada {
            position: absolute;
            z-index: 2;          /* sobre la portada y su luz */
            top: 8px; left: 8px;
            background: var(--rojo);
            color: #fff;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: var(--r-pil);
            display: flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body>

<?php
$titulo = 'Destacados';
require __DIR__ . '/admin/cabecera.php';
?>

</div>

<main class="pagina">

    <p class="intro">
        Los cómics activos aparecen en el carrusel de la portada. Cada uno
        necesita una imagen de fondo y un logo para mostrarse bien.
    </p>

    <?php if ($msg): ?>
        <div class="aviso aviso-ok" id="aviso">
            <i class="bi bi-check-circle-fill"></i><span><?= $msg ?></span>
        </div>
    <?php endif; ?>

    <div class="seccion">
        <h2>Catálogo</h2>
        <span class="cuenta"><?= $comics->num_rows ?> cómics</span>
    </div>

    <div class="fichas">
        <?php while ($c = $comics->fetch_assoc()):
            $activo = (int) $c['destacado'] === 1;
            $id = (int) $c['id'];
        ?>
        <article class="ficha <?= $activo ? 'activa' : '' ?>">

            <div class="ficha-portada">
                <?php $mini = miniatura($c['portada'], 400); ?>
                <img class="luz" aria-hidden="true" alt="" src="<?= htmlspecialchars($mini) ?>">
                <img class="obra" src="<?= htmlspecialchars($mini) ?>"
                     alt="Portada de <?= htmlspecialchars($c['titulo']) ?>">
                <?php if ($activo): ?>
                    <span class="en-portada"><i class="bi bi-star-fill"></i> En portada</span>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="ficha-cuerpo">
                <input type="hidden" name="accion" value="toggle">
                <input type="hidden" name="id" value="<?= $id ?>">

                <div>
                    <p class="ficha-titulo"><?= htmlspecialchars($c['titulo']) ?></p>
                    <p class="ficha-dato">
                        <?= htmlspecialchars($c['editorial']) ?>, <?= htmlspecialchars($c['anio']) ?>
                    </p>
                </div>

                <label class="palanca">
                    <input type="checkbox" name="destacado" value="1" <?= $activo ? 'checked' : '' ?>>
                    <span class="palanca-pista"></span>
                    <span class="palanca-texto">Mostrar en el slider</span>
                </label>

                <div class="campo" style="margin:0">
                    <label for="estado-<?= $id ?>">Estado</label>
                    <select name="estado_publicacion" id="estado-<?= $id ?>">
                        <option value="En publicacion" <?= $c['estado_publicacion'] === 'En publicacion' ? 'selected' : '' ?>>En publicación</option>
                        <option value="Publicacion Terminada" <?= $c['estado_publicacion'] === 'Publicacion Terminada' ? 'selected' : '' ?>>Terminado</option>
                    </select>
                </div>

                <div class="par-subidas">
                    <label class="mini-subir <?= $c['imagen_slider'] ? 'lista' : '' ?>" id="bg-<?= $id ?>">
                        <input type="file" name="imagen_slider" accept="image/*"
                               onchange="verImagen(this, 'bg-<?= $id ?>', 'Fondo')">
                        <?php if ($c['imagen_slider']): ?>
                            <img class="cubre" alt="Fondo actual del slider"
                                 src="uploads/<?= htmlspecialchars($c['imagen_slider']) ?>">
                            <span class="etiqueta">Fondo</span>
                        <?php else: ?>
                            <i class="bi bi-image"></i>
                            <span>Fondo</span>
                        <?php endif; ?>
                    </label>

                    <label class="mini-subir <?= $c['logo_slider'] ? 'lista' : '' ?>" id="logo-<?= $id ?>">
                        <input type="file" name="logo_slider" accept="image/*"
                               onchange="verImagen(this, 'logo-<?= $id ?>', 'Logo')">
                        <?php if ($c['logo_slider']): ?>
                            <img class="ambiente" aria-hidden="true" alt=""
                                 src="uploads/<?= htmlspecialchars($c['logo_slider']) ?>">
                            <img class="real" alt="Logo actual del slider"
                                 src="uploads/<?= htmlspecialchars($c['logo_slider']) ?>">
                            <span class="etiqueta">Logo</span>
                        <?php else: ?>
                            <i class="bi bi-fonts"></i>
                            <span>Logo</span>
                        <?php endif; ?>
                    </label>
                </div>

                <button type="submit" class="btn btn-rojo" style="width:100%">
                    Guardar cambios
                </button>
            </form>
        </article>
        <?php endwhile; ?>
    </div>
</main>

<script>
// El aviso se retira solo
const aviso = document.getElementById('aviso');
if (aviso) {
    setTimeout(() => {
        aviso.style.transition = 'opacity .4s';
        aviso.style.opacity = '0';
        setTimeout(() => aviso.remove(), 400);
    }, 3500);
}

// Vista previa al elegir una imagen. Se montan las dos capas: la copia
// borrosa de fondo y la imagen entera encima.
function verImagen(input, cajaId, etiqueta) {
    if (!input.files || !input.files[0]) return;
    const caja = document.getElementById(cajaId);
    const lector = new FileReader();

    lector.onload = e => {
        caja.querySelectorAll('img, i, span').forEach(n => n.remove());

        const fondo = document.createElement('img');
        fondo.className = 'ambiente';
        fondo.setAttribute('aria-hidden', 'true');
        fondo.alt = '';
        fondo.src = e.target.result;

        const real = document.createElement('img');
        real.className = 'real';
        real.alt = etiqueta + ' elegido';
        real.src = e.target.result;

        const pie = document.createElement('span');
        pie.className = 'etiqueta';
        pie.textContent = etiqueta;

        caja.append(fondo, real, pie);
        caja.classList.add('lista');
    };
    lector.readAsDataURL(input.files[0]);
}
</script>

</body>
</html>
