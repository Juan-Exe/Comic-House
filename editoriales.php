<?php
session_start();
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php"); exit();
}

$error = ''; $msg = '';

// Registrar editorial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear') {
        $nombre = trim($conexion->real_escape_string($_POST['nombre']));
        $imagen = '';

        if (!empty($_FILES['imagen']['name'])) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = 'editorial_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], "uploads/" . $imagen);
        }

        if ($nombre && $imagen) {
            $conexion->query("INSERT INTO editoriales (nombre, imagen) VALUES ('$nombre','$imagen')");
            $msg = 'Editorial registrada correctamente.';
        } else {
            $error = 'Completa todos los campos e imagen.';
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        $id = (int)$_POST['id'];
        $conexion->query("DELETE FROM editoriales WHERE id=$id");
        $msg = 'Editorial eliminada.';
    }
    header("Location: editoriales.php?" . ($msg ? 'ok=1' : 'err=1')); exit();
}

$editoriales = $conexion->query("SELECT * FROM editoriales ORDER BY nombre ASC");
$msg  = isset($_GET['ok'])  ? 'Operación realizada correctamente.' : '';
$error = isset($_GET['err']) ? 'Ocurrió un error.' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Editoriales</title>
    <link rel="icon" href="ico.ico">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Montserrat, sans-serif; background: #1c1c1e; color: #f0f0f0; min-height: 100vh; }

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

        .main { max-width: 900px; margin: 0 auto; padding: 32px 20px; display: grid; grid-template-columns: 320px 1fr; gap: 28px; align-items: start; }

        .toast-ok { background: #16a34a; color: #fff; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 18px; display: inline-flex; align-items: center; gap: 8px; }
        .toast-err { background: #dc2626; color: #fff; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 18px; display: inline-flex; align-items: center; gap: 8px; }

        /* FORM */
        .form-card { background: #242426; border: 1px solid #303033; border-radius: 12px; padding: 22px 20px; }
        .form-card h2 { font-size: 12px; font-weight: 700; color: #ef4444; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 18px; }
        .field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
        .field label { font-size: 11px; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: .6px; }
        .field input[type=text] {
            background: #1c1c1e; border: 1px solid #3a3a3d; border-radius: 7px;
            color: #f0f0f0; font-family: Montserrat, sans-serif; font-size: 13px;
            padding: 9px 12px; width: 100%;
        }
        .field input[type=text]:focus { outline: none; border-color: #ef4444; }
        .field small { font-size: 11px; color: #666; }

        .upload-btn {
            position: relative;
            background: #1c1c1e;
            border: 2px dashed #3a3a3d;
            border-radius: 10px;
            padding: 16px 12px;
            cursor: pointer;
            text-align: center;
            transition: border-color .2s, background .2s;
            width: 100%;
            display: block;
        }
        .upload-btn:hover { border-color: #ef4444; background: rgba(239,68,68,.05); }
        .upload-btn.has-file { border-color: #ef4444; border-style: solid; background: rgba(239,68,68,.08); }
        .upload-btn input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-btn i { font-size: 24px; color: #ef4444; display: block; margin-bottom: 6px; }
        .upload-btn .up-label { font-size: 11px; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: .5px; }
        .upload-btn .up-chosen { font-size: 11px; color: #22c55e; margin-top: 5px; display: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .preview-thumb { width: 100%; height: 60px; object-fit: contain; border-radius: 6px; margin-bottom: 6px; display: none; background: #fff; padding: 4px; }
        .btn-submit { width: 100%; padding: 10px; background: #ef4444; color: #fff; border: none; border-radius: 8px; font-family: Montserrat, sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity .2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { opacity: .85; }

        /* LIST */
        .list-section h2 { font-size: 12px; font-weight: 700; color: #aaa; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 16px; }
        .ed-list { display: flex; flex-direction: column; gap: 10px; }
        .ed-item { background: #242426; border: 1px solid #303033; border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; gap: 14px; }
        .ed-logo { width: 64px; height: 40px; object-fit: contain; background: #fff; border-radius: 6px; padding: 4px; }
        .ed-info { flex: 1; }
        .ed-nombre { font-size: 14px; font-weight: 700; }
        .ed-slug { font-size: 11px; color: #666; margin-top: 2px; }
        .btn-del { background: rgba(239,68,68,.15); color: #ef4444; border: 1px solid rgba(239,68,68,.3); border-radius: 6px; padding: 5px 10px; font-size: 12px; font-weight: 700; font-family: Montserrat, sans-serif; cursor: pointer; transition: background .2s; }
        .btn-del:hover { background: #ef4444; color: #fff; }
        .empty { color: #555; font-size: 13px; text-align: center; padding: 30px; }
    </style>
</head>
<body>

<div class="topbar">
    <a href="dashboard.php" class="topbar-brand">
        <img src="Imagenes/Logo-Comic-Huse.png" alt="Comic House">
    </a>
    <span class="topbar-title">Editoriales</span>
    <div class="topbar-actions">
        <a href="dashboard.php" class="btn-top"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
</div>

<div style="max-width:900px;margin:0 auto;padding:20px 20px 0">
    <?php if ($msg):  ?><div class="toast-ok"><i class="bi bi-check-circle"></i> <?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="toast-err"><i class="bi bi-x-circle"></i> <?= $error ?></div><?php endif; ?>
</div>

<div class="main">
    <!-- FORM -->
    <div class="form-card">
        <h2><i class="bi bi-plus-circle"></i> Registrar editorial</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear">
            <div class="field">
                <label>Nombre</label>
                <input type="text" name="nombre" placeholder="Ej. Marvel Comics" required>
            </div>

            <div class="field">
                <label>Imagen del logo</label>
                <label class="upload-btn" id="lbl-editorial-img">
                    <input type="file" name="imagen" accept="image/*" required
                        onchange="prevEditorial(this)">
                    <img id="prev-editorial" class="preview-thumb" src="">
                    <i class="bi bi-image"></i>
                    <span class="up-label">Elegir imagen</span>
                    <span class="up-chosen" id="chosen-editorial"></span>
                </label>
            </div>
            <button type="submit" class="btn-submit"><i class="bi bi-check-lg"></i> Registrar editorial</button>
        </form>
    </div>

    <!-- LIST -->
    <div class="list-section">
        <h2>Editoriales registradas</h2>
        <div class="ed-list">
            <?php if ($editoriales->num_rows === 0): ?>
                <p class="empty">No hay editoriales registradas aún.</p>
            <?php else: ?>
                <?php while ($ed = $editoriales->fetch_assoc()): ?>
                <div class="ed-item">
                    <img src="uploads/<?= htmlspecialchars($ed['imagen']) ?>" alt="<?= htmlspecialchars($ed['nombre']) ?>" class="ed-logo">
                    <div class="ed-info">
                        <p class="ed-nombre"><?= htmlspecialchars($ed['nombre']) ?></p>
                    </div>
                    <form method="POST" onsubmit="return confirm('¿Eliminar esta editorial?')">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $ed['id'] ?>">
                        <button type="submit" class="btn-del"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-dismiss toasts
document.querySelectorAll('.toast-ok, .toast-err').forEach(t => {
    setTimeout(() => {
        t.style.transition = 'opacity 0.5s ease';
        t.style.opacity = '0';
        setTimeout(() => t.remove(), 500);
    }, 3000);
});

function prevEditorial(input) {
    const lbl = document.getElementById('lbl-editorial-img');
    const prev = document.getElementById('prev-editorial');
    const chosen = document.getElementById('chosen-editorial');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { prev.src = e.target.result; prev.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
        lbl.classList.add('has-file');
        chosen.textContent = input.files[0].name;
        chosen.style.display = 'block';
    }
}
</script>
</body>
</html>
