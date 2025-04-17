<?php

if (!empty($_POST["btnregistrar"])) {
    if (
        !empty($_POST["titulo"]) && !empty($_POST["clasificacion"]) && !empty($_POST["editorial"]) && !empty($_POST["tipo"]) &&
        !empty($_POST["descripcion"]) && !empty($_POST["capitulos"]) && !empty($_POST["anio"]) && !empty($_POST["genero"])
    ) {


        $titulo = $_POST["titulo"];
        $editorial = $_POST["editorial"];
        $tipo = $_POST["tipo"];
        $clasificacion = $_POST["clasificacion"];
        $descripcion = $_POST["descripcion"];
        $capitulos = $_POST["capitulos"];
        $anio = $_POST["anio"];
        $genero = $_POST["genero"];

        $portada = $_FILES["portada"]["name"];
        $ruta_destino = "uploads/" . basename($portada);


        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        if (move_uploaded_file($_FILES["portada"]["tmp_name"], $ruta_destino)) {
            include("modelo/conexion.php");

            $sql = $conexion->query("INSERT INTO comics (titulo, clasificacion, descripcion, capitulos, anio, genero, portada, editorial, tipo) 
                            VALUES ('$titulo', '$clasificacion', '$descripcion', '$capitulos', '$anio', '$genero', '$portada', '$editorial', '$tipo')");



            if ($sql) {
                echo "Comic registrado correctamente";
            } else {
                echo "Error al registrar el comic";
            }
        } else {
            echo "Error al subir la imagen.";
        }

    } else {
        echo "Alguno de los campos está vacío";
    } ?>

    <script>
        history.replaceState(nul, nul, location.pathname)
    </script>

<?php }

?>