<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403); exit('Acceso reservado al administrador.');
}
require_once __DIR__ . '/imagen_portada.php';

if (!empty($_POST["btnmodificar"])) {
    if (
        !empty($_POST["id"]) && !empty($_POST["titulo"]) && !empty($_POST["clasificacion"]) && !empty($_POST["editorial"]) && !empty($_POST["tipo"]) &&
        !empty($_POST["descripcion"]) && !empty($_POST["capitulos"]) && !empty($_POST["anio"]) && !empty($_POST["genero"])
    ) {


        include_once("modelo/conexion.php");

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
            $portada = '';

            // Crear directorio si no existe
            if (!is_dir("uploads")) {
                mkdir("uploads", 0777, true);
            }

            // Subir nueva portada
            try { $portada = guardarPortadaSubida($_FILES['portada']); }
            catch (Throwable $e) { header('Location: crud.php?msg=upload_error'); exit; }
            if ($portada !== '') {
                // Actualizar con nueva portada
                $stmt = $conexion->prepare('UPDATE comics SET titulo=?,clasificacion=?,descripcion=?,capitulos=?,anio=?,genero=?,portada=?,editorial=?,tipo=? WHERE id=?');
                $stmt->bind_param('ssssissssi',$titulo,$clasificacion,$descripcion,$capitulos,$anio,$genero,$portada,$editorial,$tipo,$id);
                $sql = $stmt->execute();
                $stmt->close();

            } else {
                echo "Error al subir la imagen.";
                exit;
            }
        } else {
            $stmt = $conexion->prepare('UPDATE comics SET titulo=?,clasificacion=?,descripcion=?,capitulos=?,anio=?,genero=?,editorial=?,tipo=? WHERE id=?');
            $stmt->bind_param('ssssisssi',$titulo,$clasificacion,$descripcion,$capitulos,$anio,$genero,$editorial,$tipo,$id);
            $sql = $stmt->execute();
            $stmt->close();
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
