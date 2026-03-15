<?php
session_start();
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: subir_capitulo.php");
    exit();
}

$id = intval($_GET['id']);
$capitulo = $conexion->query("SELECT * FROM capitulos WHERE id = $id")->fetch_assoc();
if (!$capitulo) {
    header("Location: subir_capitulo.php");
    exit();
}

$comics = $conexion->query("SELECT id, titulo FROM comics ORDER BY titulo ASC");

// Formatear fecha para mostrar dd / mm / aaaa
$fecha_display = '';
if (!empty($capitulo['fecha_publicacion'])) {
    $fecha_display = date('d / m / Y', strtotime($capitulo['fecha_publicacion']));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Editar Capítulo</title>
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
        .topbar-title { justify-self: center; color: #fff; font-size: 14px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; opacity: .9; }
        .topbar-back {
            justify-self: end; padding: 7px 16px; border-radius: 6px;
            font-size: 12px; font-weight: 700; font-family: Montserrat, sans-serif;
            text-decoration: none; display: flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.15); color: #fff;
            border: 1px solid rgba(255,255,255,.35); transition: opacity .2s;
        }
        .topbar-back:hover { opacity: .8; }

        /* MAIN */
        .edit-wrapper { max-width: 620px; margin: 40px auto; padding: 0 20px 60px; }

        .card {
            background: #242426; border: 1px solid #303033;
            border-radius: 14px; overflow: hidden;
        }
        .card-header {
            background: #2a2a2c; border-bottom: 1px solid #303033;
            padding: 16px 24px; display: flex; align-items: center; gap: 10px;
        }
        .card-header i { color: #ef4444; font-size: 17px; }
        .card-header h2 { font-size: 13px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1.5px; }
        .card-header span { margin-left: auto; font-size: 12px; color: #555; }
        .card-body { padding: 24px; }

        /* FORM */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field-full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; }
        .field input, .field select {
            width: 100%; background: #1c1c1e; border: 1px solid #3a3a3c;
            border-radius: 7px; color: #f0f0f0; padding: 9px 12px;
            font-size: 13px; font-family: Montserrat, sans-serif;
            transition: border-color .2s; outline: none;
        }
        .field input:focus, .field select:focus { border-color: #ef4444; }
        .field select {
            appearance: none; -webkit-appearance: none;
            padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .field select option { background: #242426; }

        /* Campo fecha */
        .date-input-wrap { position: relative; }
        .date-input-wrap input[type="text"] { padding-left: 36px; }
        .date-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #555; font-size: 14px; pointer-events: none; }
        .date-input-wrap:focus-within .date-icon { color: #ef4444; }
        #fecha-display.date-ok { color: #22c55e; }

        /* Acciones */
        .form-actions {
            display: flex; gap: 12px;
            margin-top: 20px; padding-top: 20px;
            border-top: 1px solid #303033;
        }
        .btn-guardar {
            flex: 1; padding: 12px; background: #ef4444; color: #fff;
            border: none; border-radius: 7px; font-size: 13px; font-weight: 700;
            font-family: Montserrat, sans-serif; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: background .2s;
        }
        .btn-guardar:hover { background: #cc2222; }
        .btn-cancelar {
            padding: 12px 24px; background: transparent; color: #888;
            border: 1px solid #3a3a3c; border-radius: 7px; font-size: 13px;
            font-weight: 700; font-family: Montserrat, sans-serif;
            text-decoration: none; display: flex; align-items: center; gap: 7px;
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
    <span class="topbar-title">Editar capítulo</span>
    <a href="subir_capitulo.php" class="topbar-back">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="edit-wrapper">
    <div class="card">
        <div class="card-header">
            <i class="bi bi-pencil-square"></i>
            <h2>Modificar capítulo</h2>
            <span>#<?= $capitulo['id'] ?> — <?= htmlspecialchars($capitulo['titulo']) ?></span>
        </div>
        <div class="card-body">
            <form action="controlador/actualizar_capitulo.php" method="POST">
                <input type="hidden" name="id" value="<?= $capitulo['id'] ?>">
                <input type="hidden" name="comic_id" value="<?= $capitulo['comic_id'] ?>">

                <div class="form-grid">
                    <div class="field field-full">
                        <label>Cómic</label>
                        <select name="comic_id_sel">
                            <?php while ($comic = $comics->fetch_assoc()): ?>
                                <option value="<?= $comic['id'] ?>" <?= $comic['id'] == $capitulo['comic_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comic['titulo']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="field field-full">
                        <label>Título del capítulo</label>
                        <input type="text" name="titulo" value="<?= htmlspecialchars($capitulo['titulo']) ?>" required>
                    </div>

                    <div class="field">
                        <label>Número</label>
                        <input type="text" name="numero" value="<?= htmlspecialchars($capitulo['numero']) ?>" required>
                    </div>

                    <div class="field">
                        <label>Fecha de publicación <span style="color:#555;font-weight:400;text-transform:none">(opcional)</span></label>
                        <div class="date-input-wrap">
                            <input type="text" id="fecha-display" placeholder="dd / mm / aaaa"
                                maxlength="14" autocomplete="off" oninput="maskFecha(this)"
                                value="<?= htmlspecialchars($fecha_display) ?>">
                            <i class="bi bi-calendar3 date-icon"></i>
                            <input type="hidden" name="fecha_publicacion" id="fecha-hidden"
                                value="<?= htmlspecialchars($capitulo['fecha_publicacion'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="subir_capitulo.php" class="btn-cancelar">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </a>
                    <button type="submit" class="btn-guardar">
                        <i class="bi bi-check-lg"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function maskFecha(input) {
    let val = input.value.replace(/\D/g, '');
    let out = '';
    if (val.length > 0) out += val.substring(0, 2);
    if (val.length >= 3) out += ' / ' + val.substring(2, 4);
    if (val.length >= 5) out += ' / ' + val.substring(4, 8);
    input.value = out;

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

// Si la fecha ya viene pre-cargada, marcarla como válida
document.addEventListener('DOMContentLoaded', () => {
    const disp = document.getElementById('fecha-display');
    if (disp.value.trim()) disp.classList.add('date-ok');
});
</script>
</body>
</html>
