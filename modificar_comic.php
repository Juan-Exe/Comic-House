<?php
session_start();
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include("controlador/modificar.php");

$id  = intval($_GET["id"] ?? 0);
$sql = $conexion->query("SELECT * FROM comics WHERE id = $id");
$datos = $sql->fetch_object();
if (!$datos) {
    header("Location: crud.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Editar</title>
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
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: .9;
        }
        .topbar-back {
            justify-self: end;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,.35);
            transition: opacity .2s;
        }
        .topbar-back:hover { opacity: .8; }

        /* MAIN */
        .edit-wrapper {
            max-width: 680px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }

        .edit-card {
            background: #242426;
            border: 1px solid #303033;
            border-radius: 14px;
            overflow: hidden;
        }

        .edit-card-header {
            background: #2a2a2c;
            border-bottom: 1px solid #303033;
            padding: 18px 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .edit-card-header i { color: #ef4444; font-size: 18px; }
        .edit-card-header h2 {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .edit-card-header span {
            margin-left: auto;
            font-size: 12px;
            color: #666;
        }

        .edit-body { padding: 28px; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .field-full { grid-column: 1 / -1; }

        .field label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 6px;
        }
        .field input,
        .field textarea,
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
        .field textarea:focus,
        .field select:focus { border-color: #ef4444; }
        .field textarea { resize: none; min-height: 100px; overflow: hidden; }
        .field select option { background: #242426; }

        /* Portada */
        .portada-section {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 16px;
            align-items: start;
        }
        .portada-current {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .portada-current span {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
        }
        .portada-current img {
            width: 140px;
            height: 196px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #3a3a3c;
        }
        .portada-upload-col { display: flex; flex-direction: column; gap: 8px; }
        .portada-upload-col span {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
        }

        /* Botón portada centrado */
        .portada-btn {
            width: 100%;
            height: 196px;
            border: 2px dashed #3a3a3c;
            border-radius: 8px;
            background: #1c1c1e;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            overflow: hidden;
            position: relative;
            text-align: center;
        }
        .portada-btn:hover { border-color: #ef4444; background: #2a1515; }
        .portada-btn .btn-icon { font-size: 28px; color: #555; pointer-events: none; }
        .portada-btn .btn-label {
            font-size: 11px; color: #666; font-weight: 600;
            text-transform: uppercase; letter-spacing: .8px;
            pointer-events: none;
        }
        .portada-btn input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .portada-preview {
            position: absolute; inset: 0;
            object-fit: cover; width: 100%; height: 100%;
            border-radius: 6px; display: none;
        }
        .portada-preview.visible { display: block; }
        .portada-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,.55);
            display: none; align-items: center; justify-content: center;
            border-radius: 6px;
        }
        .portada-btn:hover .portada-overlay { display: flex; }
        .portada-overlay span { color: #fff; font-size: 11px; font-weight: 700; text-transform: uppercase; }

        /* Botones acción */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #303033;
        }
        .btn-guardar {
            flex: 1;
            padding: 12px;
            background: #ef4444;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: background .2s;
        }
        .btn-guardar:hover { background: #cc2222; }
        .btn-cancelar {
            padding: 12px 24px;
            background: transparent;
            color: #888;
            border: 1px solid #3a3a3c;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: border-color .2s, color .2s;
        }
        .btn-cancelar:hover { border-color: #888; color: #ccc; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="index.php" class="topbar-brand">
        <img src="Imagenes/Logo-Comic-Huse.png" alt="Comic House">
    </a>
    <span class="topbar-title">Editar cómic</span>
    <a href="crud.php" class="topbar-back">
        <i class="bi bi-arrow-left"></i> Volver al catálogo
    </a>
</div>

<div class="edit-wrapper">
    <div class="edit-card">
        <div class="edit-card-header">
            <i class="bi bi-pencil-square"></i>
            <h2>Modificar información</h2>
            <span>#<?= $datos->id ?> — <?= htmlspecialchars($datos->titulo) ?></span>
        </div>
        <div class="edit-body">
            <form enctype="multipart/form-data" method="POST">
                <input type="hidden" name="id" value="<?= $datos->id ?>">
                <div class="form-grid">

                    <div class="field">
                        <label>Título</label>
                        <input type="text" name="titulo" value="<?= htmlspecialchars($datos->titulo) ?>">
                    </div>
                    <div class="field">
                        <label>Editorial</label>
                        <input type="text" name="editorial" value="<?= htmlspecialchars($datos->editorial) ?>">
                    </div>
                    <div class="field">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="Comic" <?= $datos->tipo === 'Comic' ? 'selected' : '' ?>>Cómic</option>
                            <option value="Manga" <?= $datos->tipo === 'Manga' ? 'selected' : '' ?>>Manga</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Clasificación</label>
                        <input type="text" name="clasificacion" value="<?= htmlspecialchars($datos->clasificacion) ?>">
                    </div>
                    <div class="field">
                        <label>Capítulos</label>
                        <input type="text" name="capitulos" value="<?= htmlspecialchars($datos->capitulos) ?>">
                    </div>
                    <div class="field">
                        <label>Año</label>
                        <input type="number" name="anio" value="<?= htmlspecialchars($datos->anio) ?>">
                    </div>
                    <div class="field field-full">
                        <label>Género</label>
                        <input type="text" name="genero" value="<?= htmlspecialchars($datos->genero) ?>">
                    </div>
                    <div class="field field-full">
                        <label>Descripción</label>
                        <textarea name="descripcion"><?= htmlspecialchars($datos->descripcion) ?></textarea>
                    </div>

                    <!-- PORTADA -->
                    <div class="portada-section">
                        <div class="portada-current">
                            <span>Portada actual</span>
                            <?php if (!empty($datos->portada)): ?>
                                <img src="uploads/<?= htmlspecialchars($datos->portada) ?>" alt="Portada">
                            <?php else: ?>
                                <div style="width:140px;height:196px;background:#1c1c1e;border:1px dashed #3a3a3c;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#555;font-size:12px;">Sin portada</div>
                            <?php endif; ?>
                        </div>
                        <div class="portada-upload-col">
                            <span>Nueva portada <span style="color:#555;font-weight:400;text-transform:none">(opcional)</span></span>
                            <label class="portada-btn" id="portada-btn">
                                <img class="portada-preview" id="portada-preview" src="" alt="">
                                <i class="bi bi-image btn-icon" id="portada-icon"></i>
                                <span class="btn-label" id="portada-label">Elegir imagen</span>
                                <input type="file" name="portada" accept="image/*" id="portada-input">
                                <div class="portada-overlay"><span><i class="bi bi-arrow-repeat"></i> Cambiar</span></div>
                            </label>
                        </div>
                    </div>

                </div>

                <div class="form-actions">
                    <a href="crud.php" class="btn-cancelar">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </a>
                    <button type="submit" name="btnmodificar" value="ok" class="btn-guardar">
                        <i class="bi bi-check-lg"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Auto-resize textareas
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    }
    document.querySelectorAll('textarea').forEach(t => {
        autoResize(t);
        t.addEventListener('input', () => autoResize(t));
    });

    document.getElementById('portada-input').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const preview = document.getElementById('portada-preview');
        const icon    = document.getElementById('portada-icon');
        const label   = document.getElementById('portada-label');
        preview.src = URL.createObjectURL(file);
        preview.classList.add('visible');
        icon.style.display  = 'none';
        label.style.display = 'none';
    });
</script>
</body>
</html>
