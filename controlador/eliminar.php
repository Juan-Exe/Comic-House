<?php

if (!empty($_GET["id"])) {
    $id = $_GET["id"];
    $sql = $conexion->query(" delete from comics where id=$id ");
    if ($sql==1) {
        echo "Comic eliminado";
    }else{
        echo "error al eliminar";
    }
}

?>