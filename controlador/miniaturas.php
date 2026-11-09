<?php
/**
 * miniaturas.php - Versiones reducidas de las portadas.
 *
 * Las portadas se suben a 900x1400 y se muestran a menos de 200 px de
 * ancho. Dejar que el navegador haga esa reducción (5,5 : 1 en un solo
 * paso) produce bordes dentados, porque el filtro rápido del escalado
 * descarta píxeles en vez de promediarlos.
 *
 * Aquí se genera la miniatura en el servidor con remuestreo bicúbico,
 * que es lo que hace un editor de imagen al redimensionar. El
 * resultado es nítido y además pesa una fracción del original.
 */

/** Anchos que se generan de cada portada, en píxeles. */
const ANCHOS_MINIATURA = [400, 800];

/** Dónde viven las miniaturas, dentro de uploads/. */
const DIR_MINIATURAS = 'miniaturas';

/**
 * Devuelve la ruta de la miniatura de un ancho dado, generándola si
 * todavía no existe.
 *
 * Si la imagen original no se puede leer o ya es más pequeña que el
 * ancho pedido, devuelve la ruta original: vale más servir el archivo
 * de siempre que romper la página.
 */
function miniatura(string $archivo, int $ancho = 400): string
{
    $base = dirname(__DIR__);
    $origen = $base . '/uploads/' . $archivo;

    if ($archivo === '' || !is_file($origen)) {
        return 'uploads/' . $archivo;
    }

    $info = @getimagesize($origen);
    if (!$info || $info[0] <= $ancho) {
        return 'uploads/' . $archivo;      // ya es bastante pequeña
    }

    $dir = $base . '/uploads/' . DIR_MINIATURAS;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return 'uploads/' . $archivo;
    }

    $nombre = $ancho . '_' . pathinfo($archivo, PATHINFO_FILENAME) . '.jpg';
    $destino = $dir . '/' . $nombre;
    $ruta = 'uploads/' . DIR_MINIATURAS . '/' . $nombre;

    // Se rehace si el original cambió después de la miniatura.
    if (is_file($destino) && filemtime($destino) >= filemtime($origen)) {
        return $ruta;
    }

    return generarMiniatura($origen, $destino, $info, $ancho)
        ? $ruta
        : 'uploads/' . $archivo;
}

/**
 * Crea la miniatura. Devuelve false si el formato no se soporta o la
 * imagen no se puede abrir, para que quien llame use el original.
 */
function generarMiniatura(string $origen, string $destino, array $info, int $ancho): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;                      // sin GD
    }

    switch ($info[2]) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($origen); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($origen);  break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($origen); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($origen);  break;
        default: return false;
    }
    if (!$img) {
        return false;
    }

    $alto = (int) round($info[1] * ($ancho / $info[0]));
    $mini = imagecreatetruecolor($ancho, $alto);

    // Las portadas pueden tener transparencia (PNG, WebP); al pasar a
    // JPEG se pone fondo blanco en vez de negro.
    $blanco = imagecolorallocate($mini, 255, 255, 255);
    imagefilledrectangle($mini, 0, 0, $ancho, $alto, $blanco);

    // imagecopyresampled promedia los píxeles; imagecopyresized no, y
    // ese es justo el dentado que se quiere evitar.
    imagecopyresampled($mini, $img, 0, 0, 0, 0, $ancho, $alto, $info[0], $info[1]);

    $ok = imagejpeg($mini, $destino, 88);

    imagedestroy($mini);
    imagedestroy($img);

    return (bool) $ok;
}

/**
 * Atributo `srcset` con los anchos disponibles, para que el navegador
 * elija según la densidad de la pantalla.
 */
function miniaturaSrcset(string $archivo): string
{
    $partes = [];
    foreach (ANCHOS_MINIATURA as $w) {
        $r = miniatura($archivo, $w);
        if (strpos($r, DIR_MINIATURAS) !== false) {
            $partes[] = $r . ' ' . $w . 'w';
        }
    }
    return implode(', ', $partes);
}
