<?php

if (!empty($_POST["btnmodificar"])) {
    if (
        !empty($_POST["id"]) && !empty($_POST["titulo"]) && !empty($_POST["clasificacion"]) && !empty($_POST["editorial"]) && !empty($_POST["tipo"]) &&
        !empty($_POST["descripcion"]) && !empty($_POST["capitulos"]) && !empty($_POST["anio"]) && !empty($_POST["genero"])
    ) {


        include("modelo/conexion.php");

        $id = $_POST["id"];
        $titulo = $_POST["titulo"];
        $editorial = $_POST["editorial"];
        $tipo = $_POST["tipo"];
        $clasificacion = $_POST["clasificacion"];
        $descripcion = $_POST["descripcion"];
        $capitulos = $_POST["capitulos"];
        $anio = $_POST["anio"];
        $genero = $_POST["genero"];
        // Manejo de la portada
        if (!empty($_FILES["portada"]["name"])) {
            $portada = $_FILES["portada"]["name"];
            $ruta_destino = "uploads/" . basename($portada);

            // Crear directorio si no existe
            if (!is_dir("uploads")) {
                mkdir("uploads", 0777, true);
            }

            // Subir nueva portada
            if (move_uploaded_file($_FILES["portada"]["tmp_name"], $ruta_destino)) {
                // Actualizar con nueva portada
                $sql = $conexion->query("UPDATE comics SET titulo='$titulo', clasificacion='$clasificacion', 
                                    descripcion='$descripcion', capitulos='$capitulos', anio='$anio', 
                                    genero='$genero', portada='$portada', editorial='$editorial', tipo='$tipo' WHERE id='$id'");

            } else {
                echo "Error al subir la imagen.";
                exit;
            }
        } else {
            $sql = $conexion->query("UPDATE comics SET titulo='$titulo', clasificacion='$clasificacion', 
                                    descripcion='$descripcion', capitulos='$capitulos', anio='$anio', 
                                    genero='$genero', editorial='$editorial', tipo='$tipo' WHERE id='$id'");
        }

        if ($sql == 1) {
            header("location:crud.php");
        } else {
            echo "Error al modificar el comic";
        }

    } else {
        echo "Uno o más campos están vacíos";
    }
}

?>