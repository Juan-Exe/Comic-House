<?php
include("../modelo/conexion.php");

$id = $_POST['id'];
$comic_id = $_POST['comic_id'];
$titulo = $_POST['titulo'];
$numero = $_POST['numero'];
$fecha_publicacion = $_POST['fecha_publicacion'];

// Actualizar datos básicos del capítulo
$conexion->query("UPDATE capitulos SET 
    comic_id = '$comic_id', 
    titulo = '$titulo', 
    numero = '$numero', 
    fecha_publicacion = '$fecha_publicacion' 
    WHERE id = $id");

// Si se suben nuevas páginas
if (!empty($_FILES['paginas']['name'][0])) {
    // 1. Eliminar páginas anteriores del servidor y DB
    $paginas = $conexion->query("SELECT imagen_url FROM paginas WHERE capitulo_id = $id");
    while ($fila = $paginas->fetch_assoc()) {
        $ruta = "../paginas/" . $fila['imagen_url'];
        if (file_exists($ruta)) {
            unlink($ruta); // borra del servidor
        }
    }
    $conexion->query("DELETE FROM paginas WHERE capitulo_id = $id");

    // Asegurarse de que la carpeta exista
    $directorio = __DIR__ . "/../paginas/";
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    // 2. Guardar nuevas páginas
    foreach ($_FILES['paginas']['tmp_name'] as $key => $tmp_name) {
        $nombre_original = $_FILES['paginas']['name'][$key];
        $ruta_temp = $_FILES['paginas']['tmp_name'][$key];
        $nombre_archivo = time() . "_" . basename($nombre_original); // evita duplicados
        $destino = $directorio . $nombre_archivo;

        if (move_uploaded_file($ruta_temp, $destino)) {
            $conexion->query("INSERT INTO paginas (capitulo_id, imagen_url) VALUES ($id, '$nombre_archivo')");
        }
    }
}

header("Location: ../subir_capitulo.php");
exit;
?>
