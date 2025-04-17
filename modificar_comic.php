<?php

include "modelo/conexion.php";


$id = $_GET["id"];

$sql = $conexion->query(" select * from comics where id =$id ")

    ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body>
    <div class="container-fluid row">
        <form class="col-4 p-3 m-auto" enctype="multipart/form-data" method="POST">
            <h3 class="text-center text-secondary">Modificar comic</h3>
            <input type="hidden" name="id" value="<?= $_GET["id"] ?>">
            <?php
            include "controlador/modificar.php";
            while ($datos = $sql->fetch_object()) { ?>
                <div class="mb-3">
                    <label class="form-label">Titulo</label>
                    <input type="text" class="form-control" name="titulo" value="<?= $datos->titulo ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Editorial</label>
                    <input type="text" class="form-control" name="editorial" value="<?= $datos->editorial ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo de cómic</label>
                    <select class="form-control" name="tipo">
                        <option value="Comic" <?= $datos->tipo == 'Comic' ? 'selected' : '' ?>>Cómic</option>
                        <option value="Manga" <?= $datos->tipo == 'Manga' ? 'selected' : '' ?>>Manga</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Clasificacion 13+</label>
                    <input type="text" class="form-control" name="clasificacion" value="<?= $datos->clasificacion ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion"
                        rows="5"><?= htmlspecialchars($datos->descripcion) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Capitulos (1 - 123)</label>
                    <input type="text" class="form-control" name="capitulos" value="<?= $datos->capitulos ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Año</label>
                    <input type="number" class="form-control" name="anio" value="<?= $datos->anio ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Genero</label>
                    <input type="text" class="form-control" name="genero" value="<?= $datos->genero ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Portada actual</label>
                    <?php if (!empty($datos->portada)): ?>
                        <br>
                        <img src="uploads/<?= htmlspecialchars($datos->portada) ?>" alt="Portada del cómic" width="150"
                            height="200" class="img-thumbnail">
                    <?php else: ?>
                        <p class="text-muted">No hay portada disponible.</p>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Subir nueva portada</label>
                    <input type="file" class="form-control" accept="image/*" name="portada">
                </div>
            <?php }
            ?>
            <button type="submit" class="btn btn-primary" name="btnmodificar" value="ok">Modificar</button>
        </form>
</body>

</html>