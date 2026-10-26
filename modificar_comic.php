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
$editoriales = $conexion->query("SELECT * FROM editoriales ORDER BY nombre ASC");
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
    <link rel="stylesheet" href="admin/admin.css">
    <style>
        /* Propio de esta pantalla: la columna de portada. El resto
           viene de admin/admin.css. */

        /* La portada manda: va a la izquierda, grande, y el formulario
           la acompaña. Antes era una miniatura perdida al final. */
        .editar-comic {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 44px;
            align-items: start;
        }

        .portada-col { position: sticky; top: 90px; }

        .portada-actual {
            width: 100%;
            aspect-ratio: 2 / 3;
            object-fit: cover;
            border-radius: var(--r);
            background: var(--estante-alto);
        }
        .portada-vacia {
            width: 100%;
            aspect-ratio: 2 / 3;
            border: 1.5px dashed var(--linea);
            border-radius: var(--r);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--texto-bajo);
            font-size: var(--t-nota);
        }

        /* Cambiar la portada se pide sobre la propia portada, no en un
           campo aparte: es la misma cosa. */
        .portada-cambiar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            width: 100%;
            margin-top: 10px;
            padding: 9px;
            background: var(--estante-alto);
            border: 1px solid var(--linea);
            border-radius: var(--r);
            color: var(--texto-medio);
            font-size: var(--t-nota);
            font-weight: 600;
            cursor: pointer;
            transition: var(--paso);
        }
        .portada-cambiar:hover { border-color: var(--rojo); color: var(--texto); }
        .portada-cambiar input[type=file] { display: none; }

        .rejilla-campos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 22px;
        }
        .campo-ancho { grid-column: 1 / -1; }

        .acciones {
            display: flex;
            gap: 10px;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 1px solid var(--linea);
        }

        @media (max-width: 860px) {
            .editar-comic { grid-template-columns: 1fr; gap: 28px; }
            .portada-col { position: static; max-width: 200px; }
            .rejilla-campos { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php
$titulo = 'Editar cómic';
$volver = ['crud.php', 'Catálogo'];
require __DIR__ . '/admin/cabecera.php';
?>

<main class="pagina">

    <div class="encabezado">
        <h1><?= htmlspecialchars($datos->titulo) ?></h1>
        <p>Cómic #<?= $datos->id ?> del catálogo.</p>
    </div>

    <form enctype="multipart/form-data" method="POST" class="editar-comic">
        <input type="hidden" name="id" value="<?= $datos->id ?>">

        <div class="portada-col">
            <?php if (!empty($datos->portada)): ?>
                <img src="uploads/<?= htmlspecialchars($datos->portada) ?>"
                     alt="Portada de <?= htmlspecialchars($datos->titulo) ?>"
                     class="portada-actual" id="portada-preview">
            <?php else: ?>
                <div class="portada-vacia" id="portada-vacia">Sin portada</div>
                <img src="" alt="" class="portada-actual" id="portada-preview" style="display:none">
            <?php endif; ?>

            <label class="portada-cambiar" id="portada-btn">
                <input type="file" name="portada" accept="image/*" id="portada-input">
                <i class="bi bi-arrow-repeat" id="portada-icon"></i>
                <span id="portada-label">Cambiar portada</span>
            </label>
        </div>

        <div>
            <div class="rejilla-campos">
                <div class="campo campo-ancho">
                    <label for="f-titulo">Título</label>
                    <input type="text" id="f-titulo" name="titulo"
                           value="<?= htmlspecialchars($datos->titulo) ?>">
                </div>

                <div class="campo">
                    <label for="f-editorial">Editorial</label>
                    <select name="editorial" id="f-editorial">
                        <option value="">— Seleccionar editorial —</option>
                        <?php while ($ed = $editoriales->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($ed['nombre']) ?>"
                                <?= $datos->editorial === $ed['nombre'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ed['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="f-tipo">Tipo</label>
                    <select name="tipo" id="f-tipo">
                        <option value="Comic" <?= $datos->tipo === 'Comic' ? 'selected' : '' ?>>Cómic</option>
                        <option value="Manga" <?= $datos->tipo === 'Manga' ? 'selected' : '' ?>>Manga</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="f-clasificacion">Clasificación</label>
                    <input type="text" id="f-clasificacion" name="clasificacion"
                           value="<?= htmlspecialchars($datos->clasificacion) ?>">
                </div>

                <div class="campo">
                    <label for="f-anio">Año</label>
                    <input type="number" id="f-anio" name="anio"
                           value="<?= htmlspecialchars($datos->anio) ?>">
                </div>

                <div class="campo">
                    <label for="f-capitulos">Capítulos</label>
                    <input type="text" id="f-capitulos" name="capitulos"
                           value="<?= htmlspecialchars($datos->capitulos) ?>">
                </div>

                <div class="campo">
                    <label for="f-genero">Género</label>
                    <input type="text" id="f-genero" name="genero"
                           value="<?= htmlspecialchars($datos->genero) ?>">
                </div>

                <div class="campo campo-ancho">
                    <label for="f-descripcion">Descripción</label>
                    <textarea name="descripcion" id="f-descripcion" rows="5"><?= htmlspecialchars($datos->descripcion) ?></textarea>
                </div>
            </div>

            <div class="acciones">
                <button type="submit" name="btnmodificar" value="ok" class="btn btn-rojo">
                    <i class="bi bi-check-lg"></i> Guardar cambios
                </button>
                <a href="crud.php" class="btn btn-plano">Cancelar</a>
            </div>
        </div>
    </form>

</main>

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
        const vacia   = document.getElementById('portada-vacia');
        const label   = document.getElementById('portada-label');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
        if (vacia) vacia.style.display = 'none';
        label.textContent = file.name;
    });
</script>
</body>
</html>
