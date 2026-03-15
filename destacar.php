<?php
session_start();
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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Montserrat, sans-serif; background: #1c1c1e; color: #f0f0f0; min-height: 100vh; }

        /* TOPBAR */
        .topbar {
            background: #ef4444; height: 72px;
            display: grid; grid-template-columns: 1fr auto 1fr;
            align-items: center; padding: 0 28px;
            position: sticky; top: 0; z-index: 100;
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; justify-self: start; }
        .topbar-brand img { height: 48px; }
        .topbar-title { justify-self: center; color: #fff; font-size: 14px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
        .topbar-actions { justify-self: end; }
        .btn-top { padding: 7px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; font-family: Montserrat, sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: opacity .2s; background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.35); }
        .btn-top:hover { opacity: .8; }

        /* MAIN */
        .main { max-width: 1100px; margin: 0 auto; padding: 28px 40px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .page-title { font-size: 18px; font-weight: 800; }
        .page-sub { color: #666; font-size: 12px; margin-top: 4px; }

        .toast { background: #16a34a; color: #fff; padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; display: inline-flex; align-items: center; gap: 8px; }

        /* GRID */
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 280px));
            gap: 24px;
            justify-content: center;
        }

        /* CARD */
        .comic-card {
            background: #242426;
            border: 2px solid #303033;
            border-radius: 14px;
            overflow: hidden;
            transition: border-color .2s, transform .2s;
            display: flex;
            flex-direction: column;
        }
        .comic-card:hover { transform: translateY(-2px); }
        .comic-card.is-destacado { border-color: #ef4444; }

        /* PORTADA grande */
        .card-cover {
            width: 100%;
            aspect-ratio: 2/3;
            background-size: cover;
            background-position: center top;
            position: relative;
            flex-shrink: 0;
        }
        .card-cover-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,.85) 0%, transparent 50%);
        }
        .card-cover-info {
            position: absolute; bottom: 12px; left: 12px; right: 12px;
        }
        .card-title {
            font-size: 15px; font-weight: 800;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            color: #fff; text-shadow: 0 1px 4px rgba(0,0,0,.8);
        }
        .card-meta { font-size: 11px; color: #ccc; margin-top: 3px; }
        .badge-destacado {
            position: absolute; top: 10px; right: 10px;
            background: #ef4444; color: #fff;
            font-size: 10px; font-weight: 700; letter-spacing: .8px;
            text-transform: uppercase; padding: 4px 10px; border-radius: 20px;
            display: flex; align-items: center; gap: 4px;
        }

        /* FORM dentro de la card */
        .card-form { padding: 14px 14px 16px; display: flex; flex-direction: column; gap: 10px; flex: 1; }

        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            background: #1c1c1e; border-radius: 8px; padding: 10px 12px;
        }
        .toggle-label { font-size: 12px; font-weight: 700; }

        .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider-toggle {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background: #3a3a3d; border-radius: 24px; transition: .3s;
        }
        .slider-toggle:before {
            content: ""; position: absolute; height: 18px; width: 18px;
            left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .3s;
        }
        .toggle-switch input:checked + .slider-toggle { background: #ef4444; }
        .toggle-switch input:checked + .slider-toggle:before { transform: translateX(20px); }

        .field-label {
            font-size: 10px; font-weight: 700; color: #777;
            text-transform: uppercase; letter-spacing: .7px; margin-bottom: 4px;
        }

        select.styled {
            background: #1c1c1e; border: 1px solid #3a3a3d; border-radius: 7px;
            color: #f0f0f0; font-family: Montserrat, sans-serif; font-size: 12px;
            padding: 8px 32px 8px 10px; width: 100%; cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23aaa' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        select.styled:focus { outline: none; border-color: #ef4444; }

        /* Botones de imagen personalizados */
        .upload-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

        .upload-btn {
            position: relative;
            background: #1c1c1e;
            border: 2px dashed #3a3a3d;
            border-radius: 10px;
            padding: 12px 8px;
            cursor: pointer;
            text-align: center;
            transition: border-color .2s, background .2s;
            overflow: hidden;
        }
        .upload-btn:hover { border-color: #ef4444; background: rgba(239,68,68,.05); }
        .upload-btn.has-file { border-color: #ef4444; border-style: solid; background: rgba(239,68,68,.08); }

        .upload-btn input[type=file] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .upload-btn i { font-size: 20px; color: #ef4444; display: block; margin-bottom: 5px; }
        .upload-btn .up-label { font-size: 10px; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: .5px; line-height: 1.3; }
        .upload-btn .up-current { font-size: 10px; color: #ef4444; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .upload-btn .up-chosen { font-size: 10px; color: #22c55e; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: none; }

        /* Preview miniatura */
        .upload-btn .preview-thumb {
            width: 100%; height: 50px;
            object-fit: cover; border-radius: 6px;
            margin-bottom: 5px; display: none;
        }

        .btn-save {
            width: 100%; padding: 10px;
            background: #ef4444; color: #fff; border: none; border-radius: 8px;
            font-family: Montserrat, sans-serif; font-size: 12px; font-weight: 700;
            cursor: pointer; transition: opacity .2s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            margin-top: auto;
        }
        .btn-save:hover { opacity: .85; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="dashboard.php" class="topbar-brand">
        <img src="Imagenes/Logo-Comic-Huse.png" alt="Comic House">
    </a>
    <span class="topbar-title">Destacar cómics</span>
    <div class="topbar-actions">
        <a href="dashboard.php" class="btn-top"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
</div>

<div class="main">
    <div class="page-header">
        <div>
            <p class="page-title">Gestionar cómics destacados</p>
            <p class="page-sub">Activa el toggle · sube imagen de fondo y logo · el slider se actualiza automáticamente</p>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="toast"><i class="bi bi-check-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <div class="catalog-grid">
        <?php while ($c = $comics->fetch_assoc()): ?>
        <div class="comic-card <?= $c['destacado'] ? 'is-destacado' : '' ?>">

            <!-- PORTADA GRANDE -->
            <div class="card-cover" style="background-image: url('uploads/<?= htmlspecialchars($c['portada']) ?>');">
                <div class="card-cover-overlay"></div>
                <div class="card-cover-info">
                    <p class="card-title"><?= htmlspecialchars($c['titulo']) ?></p>
                    <p class="card-meta"><?= htmlspecialchars($c['editorial']) ?> · <?= htmlspecialchars($c['anio']) ?></p>
                </div>
                <?php if ($c['destacado']): ?>
                    <span class="badge-destacado"><i class="bi bi-star-fill"></i> Destacado</span>
                <?php endif; ?>
            </div>

            <!-- FORM -->
            <div class="card-form">
                <form method="POST" enctype="multipart/form-data" style="display:contents">
                    <input type="hidden" name="accion" value="toggle">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">

                    <!-- Toggle -->
                    <div class="toggle-row">
                        <span class="toggle-label">Destacar en slider</span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="destacado" value="1" <?= $c['destacado'] ? 'checked' : '' ?>>
                            <span class="slider-toggle"></span>
                        </label>
                    </div>

                    <!-- Estado -->
                    <div>
                        <p class="field-label">Estado de publicación</p>
                        <select name="estado_publicacion" class="styled">
                            <option value="En publicacion" <?= $c['estado_publicacion'] === 'En publicacion' ? 'selected' : '' ?>>En publicación</option>
                            <option value="Publicacion Terminada" <?= $c['estado_publicacion'] === 'Publicacion Terminada' ? 'selected' : '' ?>>Publicación Terminada</option>
                        </select>
                    </div>

                    <!-- Upload pair -->
                    <div>
                        <p class="field-label">Imágenes para el slider</p>
                        <div class="upload-pair">

                            <!-- Fondo -->
                            <label class="upload-btn <?= $c['imagen_slider'] ? 'has-file' : '' ?>" id="lbl-bg-<?= $c['id'] ?>">
                                <input type="file" name="imagen_slider" accept="image/*"
                                    onchange="previewUpload(this, 'lbl-bg-<?= $c['id'] ?>', 'prev-bg-<?= $c['id'] ?>')">
                                <img id="prev-bg-<?= $c['id'] ?>" class="preview-thumb" src="">
                                <i class="bi bi-image"></i>
                                <span class="up-label">Fondo</span>
                                <?php if ($c['imagen_slider']): ?>
                                    <span class="up-current"><i class="bi bi-check-circle-fill"></i> Cargado</span>
                                <?php endif; ?>
                                <span class="up-chosen" id="chosen-bg-<?= $c['id'] ?>"></span>
                            </label>

                            <!-- Logo -->
                            <label class="upload-btn <?= $c['logo_slider'] ? 'has-file' : '' ?>" id="lbl-logo-<?= $c['id'] ?>">
                                <input type="file" name="logo_slider" accept="image/*"
                                    onchange="previewUpload(this, 'lbl-logo-<?= $c['id'] ?>', 'prev-logo-<?= $c['id'] ?>')">
                                <img id="prev-logo-<?= $c['id'] ?>" class="preview-thumb" src="">
                                <i class="bi bi-type-bold"></i>
                                <span class="up-label">Logo</span>
                                <?php if ($c['logo_slider']): ?>
                                    <span class="up-current"><i class="bi bi-check-circle-fill"></i> Cargado</span>
                                <?php endif; ?>
                                <span class="up-chosen" id="chosen-logo-<?= $c['id'] ?>"></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Guardar</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
function previewUpload(input, lblId, prevId) {
    const lbl = document.getElementById(lblId);
    const prev = document.getElementById(prevId);
    const chosenId = lblId.replace('lbl-', 'chosen-');
    const chosen = document.getElementById(chosenId);

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = e => {
            prev.src = e.target.result;
            prev.style.display = 'block';
        };
        reader.readAsDataURL(file);
        lbl.classList.add('has-file');
        if (chosen) { chosen.textContent = file.name; chosen.style.display = 'block'; }
        // ocultar "Cargado" anterior
        const cur = lbl.querySelector('.up-current');
        if (cur) cur.style.display = 'none';
    }
}
</script>

</body>
</html>
