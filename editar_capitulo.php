<?php
include("modelo/conexion.php");

if (!isset($_GET['id'])) {
    echo "ID de capítulo no especificado.";
    exit;
}

$id = intval($_GET['id']);

// Obtener capítulo
$capitulo = $conexion->query("SELECT * FROM capitulos WHERE id = $id")->fetch_assoc();
if (!$capitulo) {
    echo "Capítulo no encontrado.";
    exit;
}

// Obtener todos los cómics para el select
$comics = $conexion->query("SELECT id, titulo FROM comics");
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Editar Capítulo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-warning text-white">
                <h4>Editar Capítulo</h4>
            </div>
            <div class="card-body">
                <form action="controlador/actualizar_capitulo.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $capitulo['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Comic</label>
                        <select name="comic_id" class="form-select" required>
                            <?php while ($comic = $comics->fetch_assoc()): ?>
                                <option value="<?= $comic['id'] ?>" <?= ($comic['id'] == $capitulo['comic_id']) ? 'selected' : '' ?>>
                                    <?= $comic['titulo'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?= $capitulo['titulo'] ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Número</label>
                        <input type="text" name="numero" class="form-control" value="<?= $capitulo['numero'] ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Fecha de publicación</label>
                        <input type="date" name="fecha_publicacion" class="form-control"
                            value="<?= $capitulo['fecha_publicacion'] ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="subir_capitulo.php" class="btn btn-secondary">Cancelar</a>

                </form>
            </div>
        </div>
    </div>

</body>

</html>