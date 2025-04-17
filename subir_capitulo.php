<?php
include("modelo/conexion.php");

// Cómics para el formulario
$result = $conexion->query("SELECT id, titulo FROM comics");

// Capítulos ya registrados
$capitulos = $conexion->query("SELECT capitulos.*, comics.titulo AS comic_titulo 
                               FROM capitulos 
                               JOIN comics ON capitulos.comic_id = comics.id 
                               ORDER BY capitulos.id DESC");

// Función para obtener imágenes de un capítulo desde la tabla correcta
function obtenerImagenes($conexion, $capitulo_id)
{
    $imagenes = [];
    $query = $conexion->query("SELECT imagen_url FROM paginas WHERE capitulo_id = $capitulo_id");
    while ($img = $query->fetch_assoc()) {
        $imagenes[] = $img['imagen_url']; // <- aquí el cambio
    }
    return $imagenes;
}


?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Subir Capítulo</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>¡Perfecto!</strong> El capítulo se subió correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>


    <div class="container mt-5">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Subir Nuevo Capítulo</h4>
            </div>
            <div class="card-body">
                <form action="controlador/procesar_capitulo.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="comic_id" class="form-label">Comic</label>
                        <select name="comic_id" id="comic_id" class="form-select" required>
                            <?php while ($comic = $result->fetch_assoc()): ?>
                                <option value="<?= $comic['id'] ?>"><?= $comic['titulo'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título del capítulo</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" name="numero" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="fecha" class="form-label">Fecha de publicación</label>
                        <input type="date" name="fecha_publicacion" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label for="imagenes" class="form-label">Subir imágenes (páginas)</label>
                        <input type="file" name="imagenes[]" class="form-control" multiple required>
                    </div>

                    <button type="submit" class="btn btn-success">Subir Capítulo</button>
                </form>
            </div>
        </div>
    </div>

    <?php
    session_start();
    if (!isset($_SESSION['usuario'])) {
        header("Location: index.php");
        exit();
    }
    ?>
    <div class="container mt-3 mb-3">
        <div class="d-flex justify-content-end gap-2">
            <a href="crud.php" class="btn btn-secondary">Ir al CRUD</a>
            <a href="logout.php" class="btn btn-outline-danger">Cerrar Sesión</a>
        </div>
    </div>


    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Capítulos Registrados</h5>
            </div>
            <div class="card-body">
                <?php while ($cap = $capitulos->fetch_assoc()): ?>
                    <div class="mb-4 border rounded p-3 shadow-sm">
                        <h6><strong><?= $cap['comic_titulo'] ?></strong> - Capítulo <?= $cap['numero'] ?>:
                            <?= $cap['titulo'] ?></h6>
                        <p class="text-muted mb-2">Publicado: <?= $cap['fecha_publicacion'] ?: 'Sin fecha' ?></p>

                        <!-- Botón de editar -->
                        <a href="editar_capitulo.php?id=<?= $cap['id'] ?>"
                            class="btn btn-sm btn-outline-primary mb-3">Editar</a>

                        <!-- Galería de imágenes -->
                        <div class="row">
                            <?php
                            $imagenes = obtenerImagenes($conexion, $cap['id']);
                            foreach ($imagenes as $img): ?>
                                <div class="col-md-2 mb-2">
                                    <img src="<?= $img ?>" class="img-thumbnail" alt="Página">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS (opcional para algunas funcionalidades) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>