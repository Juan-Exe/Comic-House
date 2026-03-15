<?php
session_start();
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$comics = $conexion->query("SELECT id, titulo, portada FROM comics ORDER BY titulo ASC");
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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Montserrat, sans-serif;
            background: #1c1c1e;
            color: #f0f0f0;
            min-height: 100vh;
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
        .topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; justify-self: start; }
        .topbar-brand img { height: 48px; }
        .topbar-title {
            justify-self: center;
            color: #fff; font-size: 14px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase; opacity: .9;
        }
        .topbar-back {
            justify-self: end;
            padding: 7px 16px; border-radius: 6px; font-size: 12px; font-weight: 700;
            font-family: Montserrat, sans-serif; text-decoration: none;
            display: flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.15); color: #fff;
            border: 1px solid rgba(255,255,255,.35); transition: opacity .2s;
        }
        .topbar-back:hover { opacity: .8; }

        /* MAIN */
        .main-wrapper {
            max-width: 720px;
            margin: 40px auto;
            padding: 0 20px 60px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* CARD */
        .card {
            background: #242426;
            border: 1px solid #303033;
            border-radius: 14px;
            overflow: hidden;
        }
        .card-header {
            background: #2a2a2c;
            border-bottom: 1px solid #303033;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header i { color: #ef4444; font-size: 17px; }
        .card-header h2 {
            font-size: 13px; font-weight: 700;
            color: #fff; text-transform: uppercase; letter-spacing: 1.5px;
        }
        .card-body { padding: 24px; }

        /* FORM */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .field-full { grid-column: 1 / -1; }

        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label {
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px; color: #888;
        }
        .field input,
        .field select {
            width: 100%;
            background: #1c1c1e;
            border: 1px solid #3a3a3c;
            border-radius: 7px;
            color: #f0f0f0;
            padding: 9px 12px;
            font-size: 13px;
            font-family: Montserrat, sans-serif;
            transition: border-color .2s;
            outline: none;
        }
        .field input:focus,
        .field select:focus { border-color: #ef4444; }
        .field select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }
        .field select option { background: #242426; }
        .field input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
            cursor: pointer;
        }

        /* Upload de imágenes */
        .upload-area {
            width: 100%;
            border: 2px dashed #3a3a3c;
            border-radius: 8px;
            background: #1c1c1e;
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
            text-align: center;
        }
        .upload-area:hover { border-color: #ef4444; background: #2a1515; }
        .upload-area.has-files { border-color: #ef4444; border-style: solid; background: rgba(239,68,68,.06); }
        .upload-area i { font-size: 28px; color: #555; pointer-events: none; }
        .upload-area .up-label {
            font-size: 11px; color: #666; font-weight: 600;
            text-transform: uppercase; letter-spacing: .8px;
            pointer-events: none;
        }
        .upload-area .up-count {
            font-size: 12px; color: #22c55e; font-weight: 700;
            display: none;
        }
        .upload-area input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }

        /* Catálogo selector */
        .comic-picker-label {
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px; color: #888;
            margin-bottom: 10px; display: block;
        }
        .comic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
        }
        .comic-card {
            background: #1c1c1e;
            border: 2px solid #3a3a3c;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: border-color .2s, transform .15s;
            position: relative;
        }
        .comic-card:hover { border-color: #ef4444; transform: translateY(-2px); }
        .comic-card.selected { border-color: #ef4444; }
        .comic-card.selected::after {
            content: '\f26b';
            font-family: 'Bootstrap Icons';
            position: absolute; top: 5px; right: 6px;
            background: #ef4444; color: #fff;
            font-size: 12px; width: 22px; height: 22px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .comic-card img {
            width: 100%; aspect-ratio: 2/3;
            object-fit: cover; display: block;
        }
        .comic-card-no-img {
            width: 100%; aspect-ratio: 2/3;
            background: #2a2a2c;
            display: flex; align-items: center; justify-content: center;
            color: #444; font-size: 24px;
        }
        .comic-card-title {
            padding: 6px 7px;
            font-size: 10px; font-weight: 700;
            color: #ccc; line-height: 1.3;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* Panel seleccionado */
        .selected-comic-bar {
            display: none;
            align-items: center;
            gap: 14px;
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .selected-comic-bar.visible { display: flex; }
        .selected-comic-bar img {
            width: 36px; height: 50px;
            object-fit: cover; border-radius: 4px;
        }
        .selected-comic-bar-info { flex: 1; }
        .selected-comic-bar-name { font-size: 13px; font-weight: 700; color: #fff; }
        .selected-comic-bar-sub { font-size: 11px; color: #888; margin-top: 2px; }
        .btn-change {
            font-size: 11px; font-weight: 700; color: #ef4444;
            background: none; border: none; cursor: pointer;
            font-family: Montserrat, sans-serif;
            padding: 4px 8px; border-radius: 4px;
            transition: background .2s;
        }
        .btn-change:hover { background: rgba(239,68,68,.15); }

        /* Campo fecha personalizado */
        .date-input-wrap {
            position: relative;
        }
        .date-input-wrap input[type="text"] {
            padding-left: 36px;
        }
        .date-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            font-size: 14px;
            pointer-events: none;
        }
        .date-input-wrap:focus-within .date-icon { color: #ef4444; }
        #fecha-display.date-ok { color: #22c55e; }

        /* Botones */
        .form-actions {
            display: flex; gap: 12px;
            margin-top: 20px; padding-top: 20px;
            border-top: 1px solid #303033;
        }
        .btn-submit {
            flex: 1; padding: 12px;
            background: #ef4444; color: #fff;
            border: none; border-radius: 7px;
            font-size: 13px; font-weight: 700;
            font-family: Montserrat, sans-serif;
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; gap: 7px; transition: background .2s;
        }
        .btn-submit:hover { background: #cc2222; }

        /* Overlay de progreso */
        .progress-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.75);
            z-index: 200;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        .progress-overlay.visible { display: flex; }
        .progress-box {
            background: #242426;
            border: 1px solid #303033;
            border-radius: 14px;
            padding: 32px 40px;
            width: 420px;
            max-width: 90vw;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .progress-title {
            font-size: 13px; font-weight: 700;
            color: #fff; text-transform: uppercase;
            letter-spacing: 1.5px;
            display: flex; align-items: center; gap: 10px;
        }
        .progress-title i { color: #ef4444; font-size: 16px; }
        .progress-filename {
            font-size: 11px; color: #666;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .progress-bar-track {
            width: 100%;
            height: 6px;
            background: #3a3a3c;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: #ef4444;
            border-radius: 3px;
            transition: width .2s ease;
        }
        .progress-bar-fill.processing {
            background: linear-gradient(90deg, #ef4444 0%, #f87171 50%, #ef4444 100%);
            background-size: 200% 100%;
            animation: shimmer 1.2s infinite linear;
            width: 100% !important;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .progress-pct {
            font-size: 12px; font-weight: 700; color: #ef4444;
            text-align: right;
        }
        .progress-status {
            font-size: 11px; color: #888; text-align: center;
        }

        /* Toast */
        .toast-ok {
            background: #16a34a; color: #fff;
            padding: 10px 18px; border-radius: 8px;
            font-size: 13px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .toast-err {
            background: #dc2626; color: #fff;
            padding: 10px 18px; border-radius: 8px;
            font-size: 13px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
        }

        /* Lista de capítulos */
        .cap-list { display: flex; flex-direction: column; gap: 10px; }
        .cap-item {
            background: #2a2a2c;
            border: 1px solid #303033;
            border-radius: 10px;
            padding: 14px 18px;
        }
        .cap-item-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .cap-badge {
            background: #ef4444; color: #fff;
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 4px;
            text-transform: uppercase; letter-spacing: .5px;
            white-space: nowrap;
        }
        .cap-title { font-size: 14px; font-weight: 700; }
        .cap-meta { font-size: 11px; color: #666; margin-top: 2px; }
        .cap-comic { font-size: 12px; color: #aaa; margin-left: auto; }
        .btn-edit {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 5px;
            font-size: 11px; font-weight: 700; font-family: Montserrat, sans-serif;
            text-decoration: none;
            background: rgba(239,68,68,.15); color: #ef4444;
            border: 1px solid rgba(239,68,68,.3);
            transition: background .2s;
        }
        .btn-edit:hover { background: #ef4444; color: #fff; }
        .cap-pages {
            display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px;
        }
        .cap-pages img {
            width: 54px; height: 76px;
            object-fit: cover; border-radius: 4px;
            border: 1px solid #3a3a3c;
        }
        .empty { color: #555; font-size: 13px; text-align: center; padding: 30px 0; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="index.php" class="topbar-brand">
        <img src="Imagenes/Logo-Comic-Huse.png" alt="Comic House">
    </a>
    <span class="topbar-title">Subir capítulo</span>
    <a href="dashboard.php" class="topbar-back">
        <i class="bi bi-arrow-left"></i> Dashboard
    </a>
</div>

<div class="main-wrapper">

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="toast-ok" id="toast-ok">
        <i class="bi bi-check-circle"></i> Capítulo subido correctamente.
    </div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-upload"></i>
            <h2>Subir nuevo capítulo</h2>
        </div>
        <div class="card-body">
            <!-- PASO 1: Elegir cómic -->
            <div id="step-picker">
                <span class="comic-picker-label">Selecciona el cómic</span>
                <div class="comic-grid">
                    <?php while ($comic = $comics->fetch_assoc()): ?>
                    <div class="comic-card"
                         data-id="<?= $comic['id'] ?>"
                         data-titulo="<?= htmlspecialchars($comic['titulo'], ENT_QUOTES) ?>"
                         data-portada="<?= !empty($comic['portada']) ? 'uploads/' . htmlspecialchars($comic['portada']) : '' ?>"
                         onclick="selectComic(this)">
                        <?php if (!empty($comic['portada'])): ?>
                            <img src="uploads/<?= htmlspecialchars($comic['portada']) ?>" alt="<?= htmlspecialchars($comic['titulo']) ?>">
                        <?php else: ?>
                            <div class="comic-card-no-img"><i class="bi bi-book"></i></div>
                        <?php endif; ?>
                        <div class="comic-card-title"><?= htmlspecialchars($comic['titulo']) ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- PASO 2: Formulario (oculto hasta elegir cómic) -->
            <form action="controlador/procesar_capitulo.php" method="POST" enctype="multipart/form-data" id="form-capitulo" style="display:none;">
                <input type="hidden" name="comic_id" id="input-comic-id">

                <!-- Bar del cómic seleccionado -->
                <div class="selected-comic-bar visible" id="selected-bar">
                    <img src="" alt="" id="sel-portada">
                    <div class="selected-comic-bar-info">
                        <div class="selected-comic-bar-name" id="sel-nombre"></div>
                        <div class="selected-comic-bar-sub">Cómic seleccionado</div>
                    </div>
                    <button type="button" class="btn-change" onclick="backToPicker()">
                        <i class="bi bi-arrow-left"></i> Cambiar
                    </button>
                </div>

                <div class="form-grid">
                    <div class="field field-full">
                        <label>Título del capítulo</label>
                        <input type="text" name="titulo" placeholder="Ej. El eclipse" required>
                    </div>
                    <div class="field">
                        <label>Número</label>
                        <input type="text" name="numero" placeholder="Ej. 1" required>
                    </div>
                    <div class="field">
                        <label>Fecha de publicación <span style="color:#555;font-weight:400;text-transform:none">(opcional)</span></label>
                        <div class="date-input-wrap">
                            <input type="text" id="fecha-display" placeholder="dd / mm / aaaa"
                                maxlength="14" autocomplete="off" oninput="maskFecha(this)">
                            <i class="bi bi-calendar3 date-icon"></i>
                            <input type="hidden" name="fecha_publicacion" id="fecha-hidden">
                        </div>
                    </div>
                    <div class="field field-full">
                        <label>Archivo del capítulo <span style="color:#555;font-weight:400;text-transform:none">(CBR o CBZ)</span></label>
                        <label class="upload-area" id="upload-area">
                            <input type="file" name="cbr_file" accept=".cbr,.cbz" required onchange="handleUpload(this)">
                            <i class="bi bi-file-zip" id="up-icon"></i>
                            <span class="up-label" id="up-label">Elegir archivo .cbr / .cbz</span>
                            <span class="up-count" id="up-count"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-cloud-upload"></i> Subir capítulo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LISTA DE CAPÍTULOS (se muestra al seleccionar cómic) -->
    <div class="card" id="card-capitulos" style="display:none;">
        <div class="card-header">
            <i class="bi bi-collection"></i>
            <h2>Capítulos de <span id="cap-comic-nombre"></span></h2>
        </div>
        <div class="card-body">
            <div class="cap-list" id="cap-list">
                <p class="empty">Cargando...</p>
            </div>
        </div>
    </div>

</div>

<!-- OVERLAY PROGRESO -->
<div class="progress-overlay" id="progress-overlay">
    <div class="progress-box">
        <div class="progress-title">
            <i class="bi bi-cloud-upload"></i>
            <span id="prog-titulo">Subiendo capítulo...</span>
        </div>
        <div class="progress-filename" id="prog-filename"></div>
        <div class="progress-bar-track">
            <div class="progress-bar-fill" id="prog-fill"></div>
        </div>
        <div class="progress-pct" id="prog-pct">0%</div>
        <div class="progress-status" id="prog-status">Enviando archivo...</div>
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

    document.getElementById('step-picker').style.display = 'none';
    document.getElementById('form-capitulo').style.display = 'block';

    // Cargar capítulos del cómic
    cargarCapitulos(card.dataset.id, card.dataset.titulo);
}

function backToPicker() {
    document.getElementById('form-capitulo').style.display = 'none';
    document.getElementById('card-capitulos').style.display = 'none';
    document.getElementById('step-picker').style.display = 'block';
    document.querySelectorAll('.comic-card').forEach(c => c.classList.remove('selected'));
}

function cargarCapitulos(comicId, comicNombre) {
    const card = document.getElementById('card-capitulos');
    const list = document.getElementById('cap-list');
    const titulo = document.getElementById('cap-comic-nombre');

    titulo.textContent = comicNombre;
    card.style.display = 'block';
    list.innerHTML = '<p class="empty" style="padding:20px 0;">Cargando...</p>';

    fetch('controlador/get_capitulos.php?comic_id=' + comicId)
        .then(r => r.json())
        .then(caps => {
            if (caps.length === 0) {
                list.innerHTML = '<p class="empty">Este cómic no tiene capítulos aún.</p>';
                return;
            }
            list.innerHTML = caps.map(cap => {
                const fecha = cap.fecha_publicacion
                    ? new Date(cap.fecha_publicacion).toLocaleDateString('es', {day:'2-digit', month:'2-digit', year:'numeric'})
                    : 'Sin fecha';
                const imgs = cap.imagenes.slice(0, 10).map(img =>
                    `<img src="${img}" alt="Página">`
                ).join('');
                const extra = cap.imagenes.length > 10
                    ? `<div style="width:54px;height:76px;background:#1c1c1e;border:1px solid #3a3a3c;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#666;font-weight:700;">+${cap.imagenes.length - 10}</div>`
                    : '';
                const pages = cap.imagenes.length > 0
                    ? `<div class="cap-pages">${imgs}${extra}</div>` : '';
                return `
                <div class="cap-item">
                    <div class="cap-item-header">
                        <span class="cap-badge">Cap. ${cap.numero}</span>
                        <div>
                            <div class="cap-title">${cap.titulo}</div>
                            <div class="cap-meta">${fecha}</div>
                        </div>
                        <a href="editar_capitulo.php?id=${cap.id}" class="btn-edit" style="margin-left:auto;">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                    </div>
                    ${pages}
                </div>`;
            }).join('');
        })
        .catch(() => {
            list.innerHTML = '<p class="empty">Error al cargar capítulos.</p>';
        });
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

function handleUpload(input) {
    const area  = document.getElementById('upload-area');
    const icon  = document.getElementById('up-icon');
    const label = document.getElementById('up-label');
    const count = document.getElementById('up-count');
    if (input.files && input.files.length > 0) {
        area.classList.add('has-files');
        icon.style.color = '#ef4444';
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
                const msg = res.error === 'duplicado'
                    ? 'Ya existe un capítulo con ese número para este cómic.'
                    : 'Error al subir el capítulo. Intenta de nuevo.';
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
    const wrapper = document.querySelector('.main-wrapper');
    const err = document.createElement('div');
    err.className = 'toast-err';
    err.innerHTML = '<i class="bi bi-x-circle"></i> ' + msg;
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
