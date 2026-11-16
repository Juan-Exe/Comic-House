<?php
require_once __DIR__ . '/logo_editorial.php';

/** Portadas manuales decodificadas y publicadas como PNG con nombre propio. */
function guardarPortadaSubida(array $archivo): string {
    $png=ComicHouse\Editoriales\prepararLogo($archivo);
    if ($png === null) throw new RuntimeException('Falta la portada.');
    $dir=dirname(__DIR__) . '/uploads/';
    if (!is_dir($dir) && !mkdir($dir,0755)) throw new RuntimeException('No se pudo guardar la portada.');
    $nombre='portada_' . bin2hex(random_bytes(16)) . '.png';
    $ruta=$dir . $nombre;
    $f=fopen($ruta,'xb');
    if (!$f) throw new RuntimeException('No se pudo guardar la portada.');
    try {
        if (fwrite($f,$png) !== strlen($png)) throw new RuntimeException('No se pudo guardar la portada completa.');
    } catch (Throwable $e) { fclose($f); @unlink($ruta); throw $e; }
    fclose($f);
    return $nombre;
}
