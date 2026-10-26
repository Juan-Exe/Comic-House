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
    <link rel="stylesheet" href="admin/admin.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Montserrat, sans-serif; background: var(--papel); color: var(--texto); min-height: 100vh; }

        /* MAIN */
        .edit-wrapper { max-width: 620px; margin: 40px auto; padding: 0 20px 60px; }

        /* Sin caja: el contenido se separa con una línea, como en el
           resto del panel. */
        .edit-cab {
            display: flex;
            align-items: baseline;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--linea);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .edit-cab h2 { font-size: var(--t-titulo); font-weight: 700; }
        .edit-cab span { font-size: var(--t-nota); color: var(--texto-medio); }

        /* FORM */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field-full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-size: var(--t-nota); font-weight: 600; color: var(--texto-medio); }
        .field input, .field select {
            width: 100%; background: var(--papel); border: 1px solid var(--linea);
            border-radius: 7px; color: var(--texto); padding: 9px 12px;
            font-size: var(--t-nota); font-family: Montserrat, sans-serif;
            transition: border-color .2s; outline: none;
        }
        .field input:focus, .field select:focus { border-color: var(--rojo); }
        .field select {
            appearance: none; -webkit-appearance: none;
            padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .field select option { background: var(--estante); }

        /* Campo fecha */
        .date-input-wrap { position: relative; }
        .date-input-wrap input[type="text"] { padding-left: 36px; }
        .date-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--texto-bajo); font-size: var(--t-base); pointer-events: none; }
        .date-input-wrap:focus-within .date-icon { color: var(--rojo); }
        #fecha-display.date-ok { color: var(--texto); }

        /* Acciones */
        .form-actions {
            display: flex; gap: 12px;
            margin-top: 20px; padding-top: 20px;
            border-top: 1px solid var(--linea);
        }
        .btn-guardar {
            flex: 1; padding: 12px; background: var(--rojo); color: #fff;
            border: none; border-radius: 7px; font-size: var(--t-nota); font-weight: 700;
            font-family: Montserrat, sans-serif; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: background .2s;
        }
        .btn-guardar:hover { background: var(--rojo); }
        .btn-cancelar {
            padding: 12px 24px; background: transparent; color: var(--texto-medio);
            border: 1px solid var(--linea); border-radius: 7px; font-size: var(--t-nota);
            font-weight: 700; font-family: Montserrat, sans-serif;
            text-decoration: none; display: flex; align-items: center; gap: 7px;
            transition: border-color .2s, color .2s;
        }
        .btn-cancelar:hover { border-color: var(--texto-medio); color: var(--texto-medio); }
    </style>
</head>
<body>

<?php
$titulo = 'Editar capítulo';
$volver = ['subir_capitulo.php', 'Subir capítulo'];
require __DIR__ . '/admin/cabecera.php';
?>

<div class="edit-wrapper">
    <div class="edit-cab">
        <h2><?= htmlspecialchars($capitulo['titulo']) ?></h2>
        <span>Capítulo #<?= $capitulo['id'] ?></span>
    </div>
    <div>
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
                        <label>Fecha de publicación <span style="color:var(--texto-bajo);font-weight:400;text-transform:none">(opcional)</span></label>
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
