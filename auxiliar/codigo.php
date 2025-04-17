<?php

include("../modelo/conexion.php");

if ($_POST["dato"] == "busca" && !empty($_POST["busqueda"])) {

    $busqueda = mysqli_real_escape_string($conexion, $_POST["busqueda"]);
    $key = explode(' ', $busqueda);

    $sql = "SELECT * FROM comics WHERE titulo LIKE '%$busqueda%'";

    foreach ($key as $palabra) {
        if (!empty($palabra)) {
            $sql .= " OR titulo LIKE '%$palabra%'";
        }
    }

    $row_sql = mysqli_query($conexion, $sql);

    echo '<table class="col-12 m-0 p-0"><tbody>';

    while ($row = mysqli_fetch_assoc($row_sql)) {
        $comicUrl = "/Sexto_Semestre/Comics-House/Comics/comics.php?id=" . htmlspecialchars($row["id"]);
        $imageUrl = "/Sexto_Semestre/Comics-House/uploads/" . htmlspecialchars($row["portada"]);

        echo "<tr data-id='" . htmlspecialchars($row["id"]) . "' onclick=\"window.location.href='$comicUrl'\" 
                 style=\"display: flex; align-items: center; padding: 10px; cursor: pointer;\">
                <td style=\"width: 60px; height: 80px; flex-shrink: 0;\">
                    <img src=\"$imageUrl\" 
                         alt=\"Portada\" 
                         style=\"width: 100%; height: 100%; object-fit: cover; border-radius: 5px;\">
                </td>
                <td style=\"padding-left: 15px;\">
                    <strong style=\"display: block;\">" . htmlspecialchars($row["titulo"]) . "</strong>
                    <span style=\"font-size: 14px; color: #666;\">Clasificación: " . htmlspecialchars($row["clasificacion"]) . "</span>
                </td>
            </tr>";
    }

    echo '</tbody></table>';
}

?>
