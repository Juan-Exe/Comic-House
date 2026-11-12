<?php
/**
 * eliminar.php - Borrado de un comic del catalogo.
 *
 * Antes hacia echo del resultado, que se imprimia como texto suelto
 * encima del layout ("Comic eliminado" sobre la barra roja). Ahora
 * redirige con ?msg=, que es el mecanismo que ya usa el resto del CRUD
 * y que crud.php convierte en un toast.
 *
 * Las paginas y capitulos del comic se borran solos por las claves
 * foraneas ON DELETE CASCADE declaradas en el esquema.
 */

if (!empty($_GET["id"])) {

    $id = (int) $_GET["id"];

    // Sentencia preparada: el id viene de la URL y no debe concatenarse.
    $stmt = $conexion->prepare("DELETE FROM comics WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
        $stmt->execute();
        $borradas = $stmt->affected_rows;
        $stmt->close();

        if ($borradas > 0) {
            header("Location: crud.php?msg=eliminado");
        } else {
            // El id no existe: puede ser un enlace viejo o un doble clic
            // sobre "eliminar" cuando el comic ya se habia borrado.
            header("Location: crud.php?msg=no_encontrado");
        }
    } catch (Throwable $e) {
        $stmt->close();
        header("Location: crud.php?msg=error_eliminar");
    }

    exit();
}
