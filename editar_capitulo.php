<?php
session_start();
include_once("modelo/conexion.php");
require_once __DIR__ . '/controlador/fecha_capitulo.php';
require_once __DIR__ . '/controlador/reemplazo_capitulo.php';
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

$conexion->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
$estadoReemplazo = estadoReemplazo($conexion,$id);
$conexion->commit();
if (!$estadoReemplazo) { header('Location: subir_capitulo.php'); exit(); }
$capitulo = $estadoReemplazo['capitulo'];
// La fecha y la confirmación corresponden a la misma revisión del capítulo.
$fecha_display = mostrarFechaCapitulo($capitulo['fecha_publicacion'], $capitulo['fecha_publicacion_mes'] ?? null, true);
$csrfReemplazo = $_SESSION['reemplazo_csrf'] ??= bin2hex(random_bytes(32));
$nombreObra = $conexion->query('SELECT titulo FROM comics WHERE id=' . (int)$capitulo['comic_id'])->fetch_assoc()['titulo'];
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
    <link rel="stylesheet" href="admin/controles.css">
    <link rel="stylesheet" href="admin/reemplazo-capitulo.css">
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
    <?php if (isset($_GET['error_fecha'])): ?>
        <p class="aviso aviso-error" role="alert">No se guardaron los cambios. Revisa la fecha de publicación.</p>
    <?php endif; ?>
    <div class="edit-cab">
        <h2><?= htmlspecialchars($capitulo['titulo']) ?></h2>
        <span>Capítulo #<?= $capitulo['id'] ?></span>
    </div>
    <?php if (isset($_GET['reemplazado'])): ?>
        <p class="aviso" role="status">Archivo reemplazado. Las páginas nuevas ya están disponibles.</p>
    <?php endif; ?>
    <div>
            <form action="controlador/actualizar_capitulo.php" method="POST">
                <input type="hidden" name="id" value="<?= $capitulo['id'] ?>">
                <input type="hidden" name="comic_id" value="<?= $capitulo['comic_id'] ?>">

                <div class="form-grid">
                    <div class="field field-full">
                        <label>Cómic</label>
                        <select name="comic_id_sel" data-personalizado aria-label="Cómic">
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
                        <div class="fecha-control">
                        <div class="date-input-wrap">
                            <input type="text" id="fecha-display" placeholder="dd / mm / aaaa"
                                maxlength="14" autocomplete="off" oninput="maskFecha(this)"
                                value="<?= htmlspecialchars($fecha_display) ?>">
                            <input type="hidden" name="fecha_publicacion" id="fecha-hidden"
                                value="<?= htmlspecialchars($capitulo['fecha_publicacion'] ?? '') ?>">
                            <input type="hidden" name="fecha_publicacion_mes" id="fecha-mes-hidden"
                                value="<?= htmlspecialchars($capitulo['fecha_publicacion_mes'] ?? '') ?>">
                        </div>
                        <div class="fecha-precision">
                            <select id="fecha-precision" data-personalizado data-menu-ancho="180" data-menu-alinear="derecha"
                                    aria-label="Precisión de la fecha" onchange="ajustarPrecisionFecha()">
                                <option value="dia" data-resumen="Completa">Día, mes y año</option>
                                <option value="mes" <?= !empty($capitulo['fecha_publicacion_mes']) ? 'selected' : '' ?>>Mes y año</option>
                            </select>
                        </div>
                        </div>
                        <small id="fecha-aviso" hidden></small>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="subir_capitulo.php" class="btn-cancelar">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </a>
                    <button type="submit" class="btn-guardar">
                        <i class="bi bi-check-lg" aria-hidden="true"></i> Guardar cambios
                    </button>
                </div>
            </form>
            <section class="reemplazo-seccion" aria-labelledby="reemplazo-titulo">
                <h3 id="reemplazo-titulo">Reemplazar archivo del capítulo</h3>
                <p>El nuevo CBR o CBZ sustituirá las <?= count($estadoReemplazo['paginas']) ?> páginas actuales.
                    Se conservarán el título, la numeración y la fecha guardados.
                    Guarda primero los cambios que hayas hecho en el formulario.</p>
                <form id="form-reemplazo" method="POST" action="controlador/reemplazar_capitulo.php" enctype="multipart/form-data" data-version="<?= htmlspecialchars($estadoReemplazo['version']) ?>"
                    data-capitulo="<?= $id ?>" data-paginas="<?= count($estadoReemplazo['paginas']) ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfReemplazo) ?>">
                    <label for="archivo-reemplazo">Nuevo archivo .cbr o .cbz</label>
                    <input type="file" id="archivo-reemplazo" name="cbr_file" accept=".cbr,.cbz" required>
                    <p id="reemplazo-estado" role="status" aria-live="polite"></p>
                    <button type="submit" class="btn-guardar"><i class="bi bi-arrow-repeat"></i> Revisar reemplazo</button>
                </form>
                <dialog id="dialogo-reemplazo" aria-labelledby="confirmar-reemplazo-titulo">
                    <h3 id="confirmar-reemplazo-titulo">Confirmar reemplazo</h3>
                    <p><strong><?= htmlspecialchars($nombreObra) ?> · <?= htmlspecialchars($capitulo['numero']) ?></strong></p>
                    <p id="reemplazo-resumen"></p>
                    <p>El archivo sustituirá todas las páginas de este capítulo. Si no se puede completar, se conservarán las anteriores.</p>
                    <div class="reemplazo-acciones">
                        <button type="button" id="cancelar-reemplazo">Volver</button>
                        <button type="button" id="confirmar-reemplazo">Reemplazar páginas</button>
                    </div>
                </dialog>
            </section>
        </div>
    </div>
</div>

<script src="js/select-personalizado.js"></script>
<script src="js/fechas-capitulo.js"></script>
<script src="js/reemplazo-capitulo.js"></script>

</body>
</html>
