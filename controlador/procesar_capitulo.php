<?php
include("../modelo/conexion.php");

// Obtener datos del formulario
$comic_id = $_POST['comic_id'];
$titulo = $_POST['titulo'];
$numero = $_POST['numero'];
$fecha = $_POST['fecha_publicacion'];

// Verificar si ya existe un capítulo con el mismo número para ese cómic
$verificar = $conexion->prepare("SELECT id FROM capitulos WHERE comic_id = ? AND numero = ?");
$verificar->bind_param("is", $comic_id, $numero);
$verificar->execute();
$verificar->store_result();

if ($verificar->num_rows > 0) {
    // Ya existe el capítulo
    $verificar->close();
    header("Location: ../index.php?error=capitulo_existente");
    exit;
}
$verificar->close();

// Insertar capítulo
$conexion->query("INSERT INTO capitulos (comic_id, titulo, numero, fecha_publicacion) 
                  VALUES ('$comic_id', '$titulo', '$numero', '$fecha')");

$capitulo_id = $conexion->insert_id;

// Ruta física: subir un nivel desde controlador/
$target_dir = dirname(__DIR__) . "/paginas/";

// Ruta web para guardar en la base de datos
$target_dir_web = "paginas/";

// Crear carpeta si no existe
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Subir imágenes y guardar en la base de datos
foreach ($_FILES['imagenes']['tmp_name'] as $index => $tmp_name) {
    $nombre_original = basename($_FILES["imagenes"]["name"][$index]);
    $nombre_final = uniqid() . "_" . $nombre_original;

    $ruta_web = $target_dir_web . $nombre_final;
    $ruta_fisica = $target_dir . $nombre_final;

    if (move_uploaded_file($tmp_name, $ruta_fisica)) {
        $orden = $index + 1;
        $conexion->query("INSERT INTO paginas (capitulo_id, imagen_url, orden) 
                          VALUES ('$capitulo_id', '$ruta_web', '$orden')");
    }
}

header("Location: ../subir_capitulo.php?success=1");
exit();

?>
