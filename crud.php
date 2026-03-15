<?php
session_start();
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include("controlador/eliminar.php");
include("controlador/registro_comcis.php");
$comics = $conexion->query("SELECT * FROM comics ORDER BY id DESC");
$total  = $conexion->query("SELECT COUNT(*) as n FROM comics")->fetch_object()->n;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Admin</title>
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

        /* ── TOPBAR ── */
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
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            justify-self: start;
        }
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

        .topbar-actions {
            justify-self: end;
            display: flex;
            gap: 10px;
        }
        .btn-top {
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity .2s;
        }
        .btn-top:hover { opacity: .8; }
        .btn-top-ghost {
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,.35);
        }
        .btn-top-dark {
            background: rgba(0,0,0,.25);
            color: #fff;
            border: 1px solid rgba(255,255,255,.2);
        }

        /* ── LAYOUT ── */
        .admin-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            min-height: calc(100vh - 72px);
        }

        /* ── SIDEBAR ── */
        .sidebar-form {
            background: #242426;
            border-right: 1px solid #303033;
            padding: 24px 20px;
            overflow-y: auto;
        }
        .sidebar-form h2 {
            font-size: 11px;
            font-weight: 700;
            color: #ef4444;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #303033;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .field { margin-bottom: 13px; }
        .field label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 5px;
        }
        .field input,
        .field textarea,
        .field select {
            width: 100%;
            background: #1c1c1e;
            border: 1px solid #3a3a3c;
            border-radius: 6px;
            color: #f0f0f0;
            padding: 8px 11px;
            font-size: 13px;
            font-family: Montserrat, sans-serif;
            transition: border-color .2s;
            outline: none;
        }
        .field input:focus,
        .field textarea:focus,
        .field select:focus { border-color: #ef4444; }
        .field textarea { resize: none; min-height: 80px; overflow: hidden; }
        .field select option { background: #242426; }

        /* Portada upload personalizado */
        .portada-upload { position: relative; }
        .portada-btn {
            width: 100%;
            height: 130px;
            border: 2px dashed #3a3a3c;
            border-radius: 8px;
            background: #1c1c1e;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center;
            gap: 8px;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            overflow: hidden;
            position: relative;
            font-size: unset;
            font-weight: unset;
            text-transform: unset;
            letter-spacing: unset;
            color: unset;
            margin-bottom: 0;
        }
        .portada-btn:hover { border-color: #ef4444; background: #2a1515; }
        .portada-btn i { font-size: 28px; color: #555; pointer-events: none; }
        .portada-btn span { font-size: 11px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; pointer-events: none; }
        .portada-btn input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
            width: 100%; height: 100%;
        }
        .portada-preview {
            position: absolute; inset: 0;
            object-fit: cover;
            width: 100%; height: 100%;
            border-radius: 6px;
            display: none;
        }
        .portada-preview.visible { display: block; }
        .portada-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,.5);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        .portada-btn:hover .portada-overlay { display: flex; }
        .portada-overlay span { color: #fff; font-size: 11px; font-weight: 700; text-transform: uppercase; }

        .btn-registrar {
            width: 100%;
            padding: 11px;
            background: #ef4444;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            cursor: pointer;
            letter-spacing: .5px;
            transition: background .2s;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .btn-registrar:hover { background: #cc2222; }

        /* ── TABLE PANEL ── */
        .table-panel {
            padding: 24px;
            overflow-x: auto;
            background: #1c1c1e;
        }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .panel-header h2 {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .comic-count {
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .5px;
        }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { border-bottom: 2px solid #ef4444; }
        thead th {
            padding: 10px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid #2a2a2c; transition: background .15s; }
        tbody tr:hover { background: #242426; }
        tbody td { padding: 10px; vertical-align: middle; color: #ccc; }

        .portada-thumb {
            width: 46px;
            height: 64px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #3a3a3c;
            display: block;
        }
        .badge-tipo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .badge-comic { background: rgba(239,68,68,.15); color: #ef4444; border: 1px solid rgba(239,68,68,.3); }
        .badge-manga { background: rgba(96,165,250,.15); color: #60a5fa; border: 1px solid rgba(96,165,250,.3); }

        .desc-cell {
            max-width: 170px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #666;
            font-size: 12px;
        }
        .actions-cell { display: flex; gap: 6px; white-space: nowrap; }
        .btn-action {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: opacity .2s;
            cursor: pointer;
            border: none;
        }
        .btn-action:hover { opacity: .75; }
        .btn-edit   { background: rgba(245,158,11,.12); color: #f59e0b; border: 1px solid rgba(245,158,11,.3); }
        .btn-delete { background: rgba(239,68,68,.12);  color: #ef4444; border: 1px solid rgba(239,68,68,.3); }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.75); z-index: 9998;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #242426;
            border: 1px solid #3a3a3c;
            border-radius: 12px;
            padding: 32px 36px;
            width: 360px;
            text-align: center;
        }
        .modal-icon { font-size: 44px; color: #ef4444; margin-bottom: 14px; }
        .modal-box h3 { font-size: 17px; margin-bottom: 8px; color: #fff; }
        .modal-box p  { font-size: 13px; color: #888; margin-bottom: 26px; line-height: 1.5; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .btn-confirm-del {
            padding: 9px 24px; background: #ef4444; color: #fff;
            border: none; border-radius: 6px; font-weight: 700;
            font-family: Montserrat, sans-serif; cursor: pointer;
            text-decoration: none; font-size: 13px;
        }
        .btn-cancel-m {
            padding: 9px 24px; background: #1c1c1e; color: #888;
            border: 1px solid #3a3a3c; border-radius: 6px; font-weight: 700;
            font-family: Montserrat, sans-serif; cursor: pointer; font-size: 13px;
        }

        /* ── TOAST ── */
        .toast-notif {
            position: fixed; top: 88px; right: 24px;
            background: #242426; color: #fff;
            padding: 13px 18px; border-radius: 8px;
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; font-weight: 600;
            box-shadow: 0 4px 24px rgba(0,0,0,.6);
            z-index: 9999; animation: toastIn .3s ease;
        }
        .toast-notif.ok    { border-left: 4px solid #22c55e; }
        .toast-notif.error { border-left: 4px solid #ef4444; }
        .toast-notif.warn  { border-left: 4px solid #f59e0b; }
        .toast-hide { animation: toastOut .4s ease forwards !important; }
        @keyframes toastIn  { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
        @keyframes toastOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(40px)} }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <a href="index.php" class="topbar-brand">
        <img src="Imagenes/Logo-Comic-Huse.png" alt="Comic House">
    </a>
    <span class="topbar-title">Panel de administración</span>
    <div class="topbar-actions">
        <a href="subir_capitulo.php" class="btn-top btn-top-ghost">
            <i class="bi bi-upload"></i> Subir capítulo
        </a>
        <a href="logout.php" class="btn-top btn-top-dark">
            <i class="bi bi-box-arrow-right"></i> Salir
        </a>
    </div>
</div>

<!-- TOAST -->
<?php
$msgs = [
    'ok'            => ['tipo' => 'ok',    'texto' => 'Cómic registrado correctamente'],
    'error'         => ['tipo' => 'error', 'texto' => 'Error al registrar el cómic'],
    'upload_error'  => ['tipo' => 'error', 'texto' => 'Error al subir la imagen'],
    'campos_vacios' => ['tipo' => 'warn',  'texto' => 'Todos los campos son obligatorios'],
];
$msg = $_GET['msg'] ?? '';
if (isset($msgs[$msg])): $m = $msgs[$msg];
?>
<div id="toast-crud" class="toast-notif <?= $m['tipo'] ?>">
    <?php if ($m['tipo'] === 'ok'): ?>
        <i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:16px"></i>
    <?php elseif ($m['tipo'] === 'warn'): ?>
        <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;font-size:16px"></i>
    <?php else: ?>
        <i class="bi bi-x-circle-fill" style="color:#ef4444;font-size:16px"></i>
    <?php endif; ?>
    <?= htmlspecialchars($m['texto']) ?>
</div>
<script>
    setTimeout(() => {
        const t = document.getElementById('toast-crud');
        if (t) { t.classList.add('toast-hide'); setTimeout(() => t.remove(), 400); }
    }, 3500);
</script>
<?php endif; ?>

<!-- MODAL ELIMINAR -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-trash3-fill"></i></div>
        <h3>¿Eliminar cómic?</h3>
        <p>Esta acción no se puede deshacer. El cómic y sus datos serán eliminados permanentemente.</p>
        <div class="modal-actions">
            <button class="btn-cancel-m" onclick="cerrarModal()">Cancelar</button>
            <a id="btn-confirm-del" href="#" class="btn-confirm-del">Sí, eliminar</a>
        </div>
    </div>
</div>

<!-- LAYOUT -->
<div class="admin-layout">

    <!-- FORMULARIO -->
    <aside class="sidebar-form">
        <h2><i class="bi bi-plus-circle-fill"></i> Registrar cómic</h2>
        <form enctype="multipart/form-data" method="POST">
            <div class="field">
                <label>Título</label>
                <input type="text" name="titulo" placeholder="Ej. House of M">
            </div>
            <div class="field">
                <label>Editorial</label>
                <input type="text" name="editorial" placeholder="Ej. Marvel">
            </div>
            <div class="field">
                <label>Clasificación</label>
                <input type="text" name="clasificacion" placeholder="Ej. +13">
            </div>
            <div class="field">
                <label>Descripción</label>
                <textarea name="descripcion" placeholder="Sinopsis del cómic..."></textarea>
            </div>
            <div class="field">
                <label>Capítulos</label>
                <input type="text" name="capitulos" placeholder="Ej. 380">
            </div>
            <div class="field">
                <label>Año</label>
                <input type="number" name="anio" placeholder="Ej. 2005">
            </div>
            <div class="field">
                <label>Género</label>
                <input type="text" name="genero" placeholder="Ej. Superhéroes">
            </div>
            <div class="field">
                <label>Tipo</label>
                <select name="tipo">
                    <option value="Comic">Cómic</option>
                    <option value="Manga">Manga</option>
                </select>
            </div>
            <div class="field">
                <label>Portada</label>
                <div class="portada-upload">
                    <label class="portada-btn" id="portada-btn">
                        <img class="portada-preview" id="portada-preview" src="" alt="preview">
                        <i class="bi bi-image" id="portada-icon"></i>
                        <span id="portada-label">Elegir imagen</span>
                        <input type="file" name="portada" accept="image/*" id="portada-input">
                        <div class="portada-overlay"><span><i class="bi bi-arrow-repeat"></i> Cambiar</span></div>
                    </label>
                </div>
            </div>
            <button type="submit" name="btnregistrar" value="ok" class="btn-registrar">
                <i class="bi bi-plus-lg"></i> Registrar cómic
            </button>
        </form>
    </aside>

    <!-- TABLA -->
    <main class="table-panel">
        <div class="panel-header">
            <h2><i class="bi bi-collection"></i> Catálogo</h2>
            <span class="comic-count"><?= $total ?> títulos</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Portada</th>
                    <th>Título</th>
                    <th>Editorial</th>
                    <th>Tipo</th>
                    <th>Clasif.</th>
                    <th>Caps.</th>
                    <th>Año</th>
                    <th>Género</th>
                    <th>Descripción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($datos = $comics->fetch_object()): ?>
                <tr>
                    <td style="color:#444;font-size:11px"><?= $datos->id ?></td>
                    <td>
                        <img src="uploads/<?= htmlspecialchars($datos->portada) ?>"
                             class="portada-thumb" alt="portada">
                    </td>
                    <td style="font-weight:700;color:#fff"><?= htmlspecialchars($datos->titulo) ?></td>
                    <td><?= htmlspecialchars($datos->editorial) ?></td>
                    <td>
                        <span class="badge-tipo <?= strtolower($datos->tipo) === 'manga' ? 'badge-manga' : 'badge-comic' ?>">
                            <?= htmlspecialchars($datos->tipo) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($datos->clasificacion) ?></td>
                    <td><?= htmlspecialchars($datos->capitulos) ?></td>
                    <td><?= htmlspecialchars($datos->anio) ?></td>
                    <td><?= htmlspecialchars($datos->genero) ?></td>
                    <td class="desc-cell" title="<?= htmlspecialchars($datos->descripcion) ?>">
                        <?= htmlspecialchars($datos->descripcion) ?>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <a href="modificar_comic.php?id=<?= $datos->id ?>" class="btn-action btn-edit">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            <button class="btn-action btn-delete"
                                onclick="abrirModal('crud.php?id=<?= $datos->id ?>')">
                                <i class="bi bi-trash3"></i> Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
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

    // Preview portada
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

    // Modal eliminar
    function abrirModal(url) {
        document.getElementById('btn-confirm-del').href = url;
        document.getElementById('modal-eliminar').classList.add('active');
    }
    function cerrarModal() {
        document.getElementById('modal-eliminar').classList.remove('active');
    }
    document.getElementById('modal-eliminar').addEventListener('click', function (e) {
        if (e.target === this) cerrarModal();
    });
</script>
</body>
</html>
