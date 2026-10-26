<?php
session_start();
require_once __DIR__ . '/controlador/miniaturas.php';
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$comics = $conexion->query(
    "SELECT id, titulo, portada, tipo, capitulos
       FROM comics ORDER BY titulo ASC"
);
$auto_comic_id = intval($_GET['comic_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Subir Capítulo</title>
    <link rel="icon" href="ico.ico">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="admin/admin.css">
    <style>
        /* Propio de esta pantalla: el selector de cómic, la zona de
           archivo y el progreso. Lo demás viene de admin/admin.css. */

        /* ---- Paso 1: elegir el cómic ----
           Rejilla de portadas: aquí la portada ES el botón. */
        .rejilla-comics {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(124px, 1fr));
            gap: 22px 18px;
        }
        .comic-card {
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
            text-align: left;
            font-family: Montserrat, sans-serif;
            transition: var(--paso);
        }
        .comic-card img,
        .comic-card-no-img {
            width: 100%;
            aspect-ratio: 2 / 3;
            object-fit: cover;
            border-radius: 3px;
            background: var(--estante-alto);
            transition: var(--paso);
        }
        .comic-card-no-img {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--texto-bajo);
            font-size: 24px;
        }
        .comic-card:hover img,
        .comic-card:hover .comic-card-no-img { box-shadow: 0 0 0 2px var(--rojo); }
        .comic-card-title {
            margin-top: 8px;
            font-size: var(--t-nota);
            font-weight: 600;
            color: var(--texto-medio);
            line-height: 1.35;
        }
        .comic-card:hover .comic-card-title { color: var(--texto); }

        /* ---- Paso 2: el cómic elegido, en cabecera ---- */
        .elegido {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--linea);
            margin-bottom: 26px;
        }
        .elegido img {
            width: 54px;
            aspect-ratio: 2 / 3;
            object-fit: cover;
            border-radius: 3px;
        }
        .elegido-info { flex: 1; min-width: 0; }
        .elegido-nombre { font-size: 17px; font-weight: 700; }
        .elegido-sub { font-size: var(--t-nota); color: var(--texto-medio); }

        /* ---- Formulario ----
           Esta pantalla ensancha su columna: con 300 px los campos de
           numeración se apilaban y la fecha quedaba cortada. */
        .dos-columnas { grid-template-columns: minmax(0, 470px) 1fr; gap: 42px; }

        .rejilla-campos {
            display: grid;
            grid-template-columns: 1fr minmax(170px, auto);
            gap: 0 20px;
        }
        .campo-ancho { grid-column: 1 / -1; }
        .campo .suave { color: var(--texto-bajo); font-weight: 400; }

        /* La numeración ocupa su propia fila y reparte el espacio entre
           los campos que estén visibles en cada formato. */
        .fila-numeracion {
            grid-column: 1 / -1;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        /* El select conserva el ancho que necesita su texto; los
           números se reparten el resto. */
        .fila-numeracion .campo { flex: 1 1 100px; min-width: 0; }
        .fila-numeracion .campo:first-child { flex: 0 0 182px; }
        .fila-numeracion .campo.oculto { display: none; }
        /* El select muestra el texto completo: «Número suelto» se
           cortaba a «Número suelt…». */
        /* Los tres campos de numeración caben en una fila. */
        .fila-numeracion { gap: 12px; }

        .fecha-caja { position: relative; }
        .fecha-caja .bi-calendar3 {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--texto-bajo);
            pointer-events: none;
        }

        /* Zona de archivo: ancha, es la acción central de la pantalla */
        .zona-archivo {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 150px;
            padding: 24px;
            background: var(--papel);
            border: 1.5px dashed var(--linea);
            border-radius: var(--r);
            cursor: pointer;
            color: var(--texto-medio);
            text-align: center;
            transition: var(--paso);
        }
        .zona-archivo:hover { border-color: var(--rojo); color: var(--texto); }
        .zona-archivo input[type=file] { display: none; }
        .zona-archivo i { font-size: 28px; }
        .zona-archivo.cargado { border-style: solid; border-color: var(--rojo); }
        #up-count { font-size: var(--t-nota); color: var(--texto-bajo); }

        /* ---- Capítulos ya subidos ---- */
        /* ---- Capítulos del cómic ----
           Cada capítulo se despliega y muestra sus páginas dentro, sin
           saltar a otra pantalla. */
        .cap-list { display: flex; flex-direction: column; }
        .cap-item {
            border-bottom: 1px solid var(--linea);
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
        }
        .cap-item:last-child { border-bottom: none; }
        .cap-item .cap-paginas { grid-column: 1 / -1; }

        .cap-cab {
            display: flex;
            align-items: center;
            gap: 13px;
            width: 100%;
            padding: 14px 2px;
            background: none;
            border: none;
            color: var(--texto);
            font-family: Montserrat, sans-serif;
            font-size: var(--t-base);
            text-align: left;
            cursor: pointer;
            transition: var(--paso);
        }
        .cap-cab:hover { color: var(--rojo); }
        .cap-flecha {
            color: var(--texto-bajo);
            font-size: 13px;
            transition: transform var(--paso);
            flex-shrink: 0;
        }
        .cap-item.abierto .cap-flecha { transform: rotate(90deg); color: var(--rojo); }
        .cap-num {
            flex-shrink: 0;
            min-width: 54px;
            color: var(--texto-medio);
            font-weight: 700;
            font-size: var(--t-nota);
        }
        .cap-centro { flex: 1; min-width: 0; }
        .cap-titulo { font-weight: 600; line-height: 1.3; display: block; }
        .cap-meta { font-size: var(--t-nota); color: var(--texto-medio); display: block; }
        .cap-editar {
            flex-shrink: 0;
            color: var(--texto-bajo);
            font-size: 14px;
            padding: 5px;
            text-decoration: none;
            transition: var(--paso);
        }
        .cap-editar:hover { color: var(--rojo); }

        /* Páginas del capítulo: rejilla de miniaturas con proporción
           fija, para que ninguna deforme la fila. */
        .cap-paginas {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(76px, 1fr));
            gap: 8px;
            padding: 4px 2px 18px 30px;
        }
        .cap-item.abierto .cap-paginas { display: grid; }
        .cap-pagina {
            position: relative;
            aspect-ratio: 2 / 3;
            border-radius: 3px;
            overflow: hidden;
            background: var(--estante-alto);
            cursor: zoom-in;
            border: none;
            padding: 0;
        }
        .cap-pagina img { width: 100%; height: 100%; object-fit: cover; }
        .cap-pagina span {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 2px;
            background: rgba(0,0,0,.72);
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
        }
        .cap-pagina:hover { outline: 2px solid var(--rojo); }

        /* Visor a pantalla completa de una página */
        .visor {
            position: fixed;
            inset: 0;
            background: rgba(8, 8, 10, .95);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 600;
            padding: 30px;
        }
        .visor.abierto { display: flex; }
        .visor img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .visor-cerrar {
            position: absolute;
            top: 18px; right: 22px;
            background: none;
            border: none;
            color: #fff;
            font-size: 26px;
            cursor: pointer;
            line-height: 1;
        }

        /* ---- Progreso de subida ---- */
        .progress-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 10, 12, .88);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 500;
            padding: 20px;
        }
        .progress-overlay.visible { display: flex; }
        .progress-box { width: 100%; max-width: 420px; text-align: center; }
        .progress-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 7px;
        }
        .progress-title i { color: var(--rojo); }
        .progress-filename { font-size: var(--t-nota); color: var(--texto-medio); margin-bottom: 20px; }
        .progress-bar-track {
            height: 4px;
            background: var(--estante-alto);
            border-radius: var(--r-pil);
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            width: 0;
            background: var(--rojo);
            border-radius: var(--r-pil);
            transition: width .25s ease;
        }
        .progress-pct { font-size: 26px; font-weight: 800; margin-top: 14px; }
        .progress-status { font-size: var(--t-nota); color: var(--texto-medio); margin-top: 3px; }

        @media (max-width: 760px) {
            .rejilla-campos { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php
$titulo = 'Subir capítulo';
require __DIR__ . '/admin/cabecera.php';
?>

<main class="pagina">

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="aviso aviso-ok" id="toast-ok">
            <i class="bi bi-check-circle-fill"></i>
            <span>Capítulo subido correctamente.</span>
        </div>
    <?php endif; ?>

    <!-- PASO 1 · elegir el cómic -->
    <div id="step-picker">
        <p class="intro">Elige a qué cómic pertenece el capítulo. El archivo
           puede ser .cbr o .cbz.</p>

        <div class="seccion">
            <h2>Catálogo</h2>
            <span class="cuenta"><?= $comics->num_rows ?> cómics</span>
        </div>

        <div class="rejilla-comics">
            <?php while ($comic = $comics->fetch_assoc()): ?>
                <button type="button" class="comic-card"
                     data-id="<?= $comic['id'] ?>"
                     data-titulo="<?= htmlspecialchars($comic['titulo'], ENT_QUOTES) ?>"
                     data-portada="<?= !empty($comic['portada']) ? 'uploads/' . htmlspecialchars($comic['portada']) : '' ?>"
                     data-tipo="<?= htmlspecialchars($comic['tipo'], ENT_QUOTES) ?>"
                     data-capitulos="<?= htmlspecialchars($comic['capitulos'], ENT_QUOTES) ?>"
                     onclick="selectComic(this)">
                    <?php if (!empty($comic['portada'])): ?>
                        <img src="<?= htmlspecialchars(miniatura($comic['portada'], 400)) ?>"
                             srcset="<?= htmlspecialchars(miniaturaSrcset($comic['portada'])) ?>"
                             sizes="(max-width: 640px) 40vw, 165px"
                             alt="<?= htmlspecialchars($comic['titulo']) ?>">
                    <?php else: ?>
                        <div class="comic-card-no-img"><i class="bi bi-book"></i></div>
                    <?php endif; ?>
                    <div class="comic-card-title"><?= htmlspecialchars($comic['titulo']) ?></div>
                </button>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- PASO 2 · datos del capítulo -->
    <div class="dos-columnas" id="paso-datos" style="display:none">

        <form action="controlador/procesar_capitulo.php" method="POST"
              enctype="multipart/form-data" id="form-capitulo">
            <input type="hidden" name="comic_id" id="input-comic-id">

            <div class="elegido" id="selected-bar">
                <img src="" alt="" id="sel-portada">
                <div class="elegido-info">
                    <div class="elegido-nombre" id="sel-nombre"></div>
                    <div class="elegido-sub">Cómic elegido</div>
                </div>
                <button type="button" class="btn btn-plano" onclick="backToPicker()">
                    <i class="bi bi-arrow-left"></i> Cambiar
                </button>
            </div>

            <div class="rejilla-campos">
                <div class="campo">
                    <label for="cap-titulo">Título del capítulo</label>
                    <input type="text" id="cap-titulo" name="titulo"
                           placeholder="Ej. El eclipse" required>
                </div>

                <div class="campo">
                    <label for="fecha-display">Fecha de publicación <span class="suave">(opcional)</span></label>
                    <div class="fecha-caja">
                        <input type="text" id="fecha-display" placeholder="dd / mm / aaaa"
                               maxlength="14" autocomplete="off" oninput="maskFecha(this)">
                        <i class="bi bi-calendar3"></i>
                        <input type="hidden" name="fecha_publicacion" id="fecha-hidden">
                    </div>
                </div>

                <!-- La numeración cambia según la obra: un manga agrupa
                     capítulos en arcos y un cómic publica grapas sueltas
                     o recopilatorios. -->
                <div class="fila-numeracion">
                    <div class="campo">
                        <label for="cap-formato">Numeración</label>
                        <select id="cap-formato" name="formato" onchange="ajustarFormato()">
                            <option value="grapa">Número suelto</option>
                            <option value="rango">Varios capítulos</option>
                            <option value="tomo">Recopilatorio</option>
                        </select>
                    </div>

                    <div class="campo" id="campo-desde">
                        <label for="cap-desde">Capítulo</label>
                        <input type="text" id="cap-desde" name="numero_desde"
                               inputmode="numeric" placeholder="Ej. 1" required>
                    </div>

                    <div class="campo oculto" id="campo-hasta">
                        <label for="cap-hasta">Hasta el capítulo</label>
                        <input type="text" id="cap-hasta" name="numero_hasta"
                               inputmode="numeric" placeholder="Ej. 3">
                    </div>
                </div>

                <div class="campo campo-ancho oculto" id="campo-arco">
                    <label for="cap-arco">Arco <span class="suave">(opcional)</span></label>
                    <input type="text" id="cap-arco" name="arco"
                           placeholder="Ej. Arco del Espadachín Negro">
                </div>

                <div class="campo campo-ancho">
                    <label for="archivo-cap">Archivo del capítulo</label>
                    <label class="zona-archivo" id="upload-area">
                        <input type="file" id="archivo-cap" name="cbr_file" accept=".cbr,.cbz"
                               required onchange="handleUpload(this)">
                        <i class="bi bi-file-zip" id="up-icon"></i>
                        <span id="up-label">Elegir un archivo .cbr o .cbz</span>
                        <span id="up-count"></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-rojo" style="width:100%;margin-top:6px">
                <i class="bi bi-cloud-upload"></i> Subir capítulo
            </button>
        </form>

        <!-- Capítulos que ya tiene este cómic -->
        <div id="card-capitulos">
            <div class="seccion">
                <h2>Ya subidos</h2>
            </div>
            <div class="cap-list" id="cap-list">
                <p class="empty">Cargando…</p>
            </div>
            <span class="oculto" id="cap-comic-nombre"></span>
        </div>
    </div>

</main>

<!-- Progreso de la subida -->
<div class="progress-overlay" id="progress-overlay">
    <div class="progress-box">
        <div class="progress-title">
            <i class="bi bi-cloud-upload"></i>
            <span id="prog-titulo">Subiendo capítulo</span>
        </div>
        <div class="progress-filename" id="prog-filename"></div>
        <div class="progress-bar-track">
            <div class="progress-bar-fill" id="prog-fill"></div>
        </div>
        <div class="progress-pct" id="prog-pct">0%</div>
        <div class="progress-status" id="prog-status">Enviando archivo…</div>
    </div>
</div>

<script>
function selectComic(card) {
    document.querySelectorAll('.comic-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');

    document.getElementById('input-comic-id').value = card.dataset.id;
    document.getElementById('sel-nombre').textContent = card.dataset.titulo;

    const portadaEl = document.getElementById('sel-portada');
    if (card.dataset.portada) {
        portadaEl.src = card.dataset.portada;
        portadaEl.style.display = 'block';
    } else {
        portadaEl.style.display = 'none';
    }

    // El tipo manda sobre los formatos de numeración disponibles.
    window.__obra = {
        tipo: card.dataset.tipo || 'Comic',
        capitulos: card.dataset.capitulos || ''
    };
    ajustarFormato();

    document.getElementById('step-picker').style.display = 'none';
    document.getElementById('paso-datos').style.display = 'grid';

    // Cargar capítulos del cómic
    cargarCapitulos(card.dataset.id, card.dataset.titulo);
}

function backToPicker() {
    document.getElementById('paso-datos').style.display = 'none';
    document.getElementById('step-picker').style.display = 'block';
    document.querySelectorAll('.comic-card').forEach(c => c.classList.remove('selected'));
}

function cargarCapitulos(comicId, comicNombre) {
    const list = document.getElementById('cap-list');
    const titulo = document.getElementById('cap-comic-nombre');

    titulo.textContent = comicNombre;
    list.innerHTML = '<p class="empty">Cargando…</p>';

    fetch('controlador/get_capitulos.php?comic_id=' + comicId)
        .then(r => r.json())
        .then(caps => {
            if (caps.length === 0) {
                list.innerHTML = '<p class="empty">Este cómic todavía no tiene capítulos.</p>';
                return;
            }
            list.innerHTML = caps.map((cap, i) => {
                const fecha = cap.fecha_publicacion
                    ? new Date(cap.fecha_publicacion).toLocaleDateString('es',
                        { day: '2-digit', month: '2-digit', year: 'numeric' })
                    : 'Sin fecha';
                const n = cap.total_paginas || cap.imagenes.length;

                // Las páginas se montan al desplegar, no antes: un
                // capítulo puede tener más de doscientas.
                return `
                <div class="cap-item" data-caps="${i}">
                    <button type="button" class="cap-cab" onclick="alternarCapitulo(${i})">
                        <i class="bi bi-chevron-right cap-flecha"></i>
                        <span class="cap-num">${cap.numero}</span>
                        <span class="cap-centro">
                            <span class="cap-titulo">${cap.titulo}</span>
                            <span class="cap-meta">${n} página${n === 1 ? '' : 's'} · ${fecha}</span>
                        </span>
                    </button>
                    <a href="editar_capitulo.php?id=${cap.id}" class="cap-editar"
                       title="Editar los datos del capítulo" onclick="event.stopPropagation()">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <div class="cap-paginas" id="paginas-${i}"></div>
                </div>`;
            }).join('');

            // Se guardan las páginas para montarlas al desplegar.
            window.__capitulos = caps;
        })
        .catch(() => {
            list.innerHTML = '<p class="empty">No se pudieron cargar los capítulos.</p>';
        });
}

/* Despliega un capítulo y pinta sus páginas la primera vez. */
function alternarCapitulo(i) {
    const item = document.querySelector(`.cap-item[data-caps="${i}"]`);
    if (!item) return;

    const caja = document.getElementById('paginas-' + i);
    if (caja && !caja.dataset.montado) {
        const cap = (window.__capitulos || [])[i];
        caja.innerHTML = (cap?.imagenes || []).map((img, p) => `
            <button type="button" class="cap-pagina" onclick="verPagina('${img}')">
                <img loading="lazy" src="${img}" alt="Página ${p + 1}">
                <span>${p + 1}</span>
            </button>`).join('') ||
            '<p class="empty">Este capítulo no tiene páginas.</p>';
        caja.dataset.montado = '1';
    }
    item.classList.toggle('abierto');
}

/* Abre una página a pantalla completa, sin salir de la pantalla. */
function verPagina(src) {
    let visor = document.getElementById('visor');
    if (!visor) {
        visor = document.createElement('div');
        visor.id = 'visor';
        visor.className = 'visor';
        visor.innerHTML = '<button type="button" class="visor-cerrar">&times;</button><img alt="">';
        document.body.appendChild(visor);
        const cerrar = () => visor.classList.remove('abierto');
        visor.addEventListener('click', cerrar);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') cerrar();
        });
    }
    visor.querySelector('img').src = src;
    visor.classList.add('abierto');
}


function maskFecha(input) {
    // Eliminar todo lo que no sea dígito
    let val = input.value.replace(/\D/g, '');
    let out = '';
    if (val.length > 0) out += val.substring(0, 2);
    if (val.length >= 3) out += ' / ' + val.substring(2, 4);
    if (val.length >= 5) out += ' / ' + val.substring(4, 8);
    input.value = out;

    // Validar y alimentar el hidden
    const hidden = document.getElementById('fecha-hidden');
    if (val.length === 8) {
        const dd = val.substring(0, 2);
        const mm = val.substring(2, 4);
        const yyyy = val.substring(4, 8);
        const fecha = new Date(`${yyyy}-${mm}-${dd}`);
        if (!isNaN(fecha.getTime())) {
            hidden.value = `${yyyy}-${mm}-${dd}`;
            input.classList.add('date-ok');
        } else {
            hidden.value = '';
            input.classList.remove('date-ok');
        }
    } else {
        hidden.value = '';
        input.classList.remove('date-ok');
    }
}

/* ---- Numeración según el tipo de obra ----
   Un manga agrupa capítulos en arcos; un cómic publica grapas sueltas
   o recopilatorios. El formulario muestra solo lo que tiene sentido
   para la obra elegida. */
function ajustarFormato() {
    const obra = window.__obra || { tipo: 'Comic' };
    const esManga = obra.tipo === 'Manga';
    const sel = document.getElementById('cap-formato');
    const fmt = sel.value;

    // Un recopilatorio no tiene sentido en un manga.
    const opcionTomo = sel.querySelector('option[value="tomo"]');
    if (opcionTomo) opcionTomo.hidden = esManga;
    if (esManga && fmt === 'tomo') { sel.value = 'rango'; }

    // En un manga lo habitual es agrupar: se propone el rango.
    if (esManga && !sel.dataset.tocado) sel.value = 'rango';

    const actual = sel.value;
    const desde = document.getElementById('campo-desde');
    const hasta = document.getElementById('campo-hasta');
    const arco  = document.getElementById('campo-arco');

    desde.classList.toggle('oculto', actual === 'tomo');
    hasta.classList.toggle('oculto', actual !== 'rango');
    arco.classList.toggle('oculto', !esManga);

    document.getElementById('cap-desde').required = actual !== 'tomo';
    document.getElementById('cap-hasta').required = actual === 'rango';

    const etiqueta = document.querySelector('#campo-desde label');
    if (etiqueta) {
        etiqueta.textContent = actual === 'rango' ? 'Desde el capítulo' : 'Capítulo';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('cap-formato');
    if (sel) sel.addEventListener('change', () => { sel.dataset.tocado = '1'; });

    /* El número de capítulo solo admite dígitos: se filtra al escribir
       para que el error no llegue al envío. */
    for (const id of ['cap-desde', 'cap-hasta']) {
        const campo = document.getElementById(id);
        if (!campo) continue;
        campo.addEventListener('input', () => {
            let v = campo.value.replace(/[^0-9]/g, '');
            v = v.replace(/^0+(?=[0-9])/, '');   // '007' -> '7'
            if (v.length > 4) v = v.slice(0, 4); // tope de cuatro dígitos
            if (campo.value !== v) campo.value = v;
        });
    }
});

function handleUpload(input) {
    const area  = document.getElementById('upload-area');
    const icon  = document.getElementById('up-icon');
    const label = document.getElementById('up-label');
    const count = document.getElementById('up-count');
    if (input.files && input.files.length > 0) {
        area.classList.add('cargado');
        icon.style.color = 'var(--rojo)';
        label.style.display = 'none';
        count.style.display = 'block';
        count.textContent = input.files[0].name;
    }
}

// Interceptar submit del formulario con XHR + barra de progreso
document.getElementById('form-capitulo').addEventListener('submit', function(e) {
    e.preventDefault();

    const form    = this;
    const comicId = document.getElementById('input-comic-id').value;
    const file    = document.querySelector('input[name="cbr_file"]').files[0];

    if (!comicId) return;

    const overlay  = document.getElementById('progress-overlay');
    const fill     = document.getElementById('prog-fill');
    const pct      = document.getElementById('prog-pct');
    const status   = document.getElementById('prog-status');
    const filename = document.getElementById('prog-filename');

    filename.textContent = file ? file.name : '';
    overlay.classList.add('visible');

    const xhr = new XMLHttpRequest();
    const fd  = new FormData(form);

    // Fase 1: progreso de upload (red)
    xhr.upload.addEventListener('progress', function(ev) {
        if (!ev.lengthComputable) return;
        const p = Math.round((ev.loaded / ev.total) * 100);
        fill.style.width = p + '%';
        pct.textContent  = p + '%';
        if (p < 100) {
            status.textContent = 'Enviando archivo... ' + p + '%';
        } else {
            // Archivo ya en servidor, ahora extrae imágenes
            fill.classList.add('processing');
            pct.textContent  = '';
            status.textContent = 'Procesando páginas del cómic...';
        }
    });

    // Fase 2: respuesta del servidor
    xhr.addEventListener('load', function() {
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.ok) {
                status.textContent = '¡Capítulo subido! Recargando...';
                fill.classList.remove('processing');
                fill.style.width = '100%';
                pct.textContent  = '100%';
                setTimeout(() => {
                    // Recargar la misma página pasando el comic_id para re-seleccionarlo
                    window.location.href = 'subir_capitulo.php?comic_id=' + comicId;
                }, 800);
            } else {
                overlay.classList.remove('visible');
                // El servidor explica qué regla frenó la subida; se
                // muestra tal cual en vez de un error genérico.
                let msg;
                if (res.error === 'numeracion') {
                    msg = res.mensaje || 'La numeración no es válida.';
                } else if (res.error === 'duplicado') {
                    msg = 'Ya existe un capítulo con ese número para este cómic.';
                } else if (res.error === 'comic_inexistente') {
                    msg = 'El cómic elegido ya no está en el catálogo.';
                } else {
                    msg = 'Error al subir el capítulo. Intenta de nuevo.';
                }
                showError(msg);
            }
        } catch(err) {
            overlay.classList.remove('visible');
            showError('Error inesperado al procesar la respuesta.');
        }
    });

    xhr.addEventListener('error', function() {
        overlay.classList.remove('visible');
        showError('Error de red. Verifica tu conexión.');
    });

    xhr.open('POST', 'controlador/procesar_capitulo.php');
    xhr.send(fd);
});

// Auto-seleccionar cómic si viene ?comic_id= en la URL
(function() {
    const autoId = <?= $auto_comic_id ?>;
    if (!autoId) return;
    const card = document.querySelector(`.comic-card[data-id="${autoId}"]`);
    if (card) selectComic(card);
})();

function showError(msg) {
    const wrapper = document.querySelector('.pagina');
    const err = document.createElement('div');
    err.className = 'aviso aviso-error';
    err.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i><span>' + msg + '</span>';
    wrapper.prepend(err);
    setTimeout(() => {
        err.style.transition = 'opacity 0.5s';
        err.style.opacity = '0';
        setTimeout(() => err.remove(), 500);
    }, 4000);
}
</script>
</body>
</html>
