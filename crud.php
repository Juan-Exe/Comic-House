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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body>
    <script>
        function eliminar() {
            var respuesta = confirm("Estas seguro que deseas eliminar?")
            return respuesta
        }
    </script>


    <h1 class="text-center p-3">Admin</h1>

    <?php
    $msgs = [
        'ok'           => ['color' => '#198754', 'texto' => '✓ Cómic registrado correctamente'],
        'error'        => ['color' => '#dc3545', 'texto' => '✕ Error al registrar el cómic'],
        'upload_error' => ['color' => '#dc3545', 'texto' => '✕ Error al subir la imagen'],
        'campos_vacios'=> ['color' => '#ffc107', 'texto' => '⚠ Alguno de los campos está vacío'],
    ];
    $msg = $_GET['msg'] ?? '';
    if (isset($msgs[$msg])): $m = $msgs[$msg];
    ?>
    <div id="toast-crud" style="
        position:fixed; top:24px; right:24px; z-index:9999;
        background:#1a1a1a; color:#fff; padding:14px 20px;
        border-radius:8px; border-left:4px solid <?= $m['color'] ?>;
        font-family:sans-serif; font-size:14px; font-weight:500;
        box-shadow:0 4px 20px rgba(0,0,0,0.35);
        animation: toastIn .3s ease;">
        <?= $m['texto'] ?>
    </div>
    <style>
        @keyframes toastIn { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
        .toast-hide { animation: toastOut .4s ease forwards !important; }
        @keyframes toastOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(40px)} }
    </style>
    <script>
        setTimeout(() => {
            const t = document.getElementById('toast-crud');
            if (t) { t.classList.add('toast-hide'); setTimeout(() => t.remove(), 400); }
        }, 3500);
    </script>
    <?php endif; ?>

    <div class="container-fluid row">
        <form class="col-4 p-3" enctype="multipart/form-data" method="POST">
            <h3 class="text-center text-secondary">Registro de comic</h3>
            <div class="mb-3">
                <label class="form-label">Titulo</label>
                <input type="text" class="form-control" name="titulo">
            </div>
            <div class="mb-3">
                <label class="form-label">Editorial</label>
                <input type="text" class="form-control" name="editorial" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Clasificacion 13+</label>
                <input type="text" class="form-control" name="clasificacion">
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea class="form-control" name="descripcion" rows="5"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Capitulos (1 - 123)</label>
                <input type="text" class="form-control" name="capitulos">
            </div>
            <div class="mb-3">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="anio">
            </div>
            <div class="mb-3">
                <label class="form-label">Genero</label>
                <input type="text" class="form-control" name="genero">
            </div>
            <div class="mb-3">
                <label class="form-label">Tipo de cómic</label>
                <select class="form-control" name="tipo">
                    <option value="Comic">Cómic</option>
                    <option value="Manga">Manga</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Portada</label>
                <input type="file" class="form-control" accept="image/*" name="portada">
            </div>
            <button type="submit" class="btn btn-primary" name="btnregistrar" value="ok">Registrar</button>
            <div class="container mt-3 mb-3">
                <div class="d-flex justify-content-end gap-2">
                    <a href="subir_capitulo.php" class="btn btn-secondary">Ir a Subir Capítulo</a>
                    <a href="logout.php" class="btn btn-outline-danger">Cerrar Sesión</a>
                </div>
            </div>
        </form>


        <div class="col-8 p-4">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Título</th>
                        <th scope="col">Editorial</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Clasificación</th>
                        <th scope="col">Descripción</th>
                        <th scope="col">Capítulos</th>
                        <th scope="col">Año</th>
                        <th scope="col">Género</th>
                        <th scope="col">Portada</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($datos = $comics->fetch_object()) { ?>
                        <tr>
                            <th><?= $datos->id ?></th>
                            <td><?= $datos->titulo ?></td>
                            <td><?= $datos->editorial ?></td>
                            <td><?= $datos->tipo ?></td>
                            <td><?= $datos->clasificacion ?></td>
                            <td><?= $datos->descripcion ?></td>
                            <td><?= $datos->capitulos ?></td>
                            <td><?= $datos->anio ?></td>
                            <td><?= $datos->genero ?></td>
                            <td>
                                <img src="uploads/<?= $datos->portada ?>" width="150" alt="Portada">
                            </td>
                            <td>
                                <a href="modificar_comic.php?id=<?= $datos->id ?>" class="btn btn-warning btn-sm">Editar</a>
                                <a onclick="return eliminar()" href="crud.php?id=<?= $datos->id ?>"
                                    class="btn btn-danger btn-sm">Eliminar</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>

</html>