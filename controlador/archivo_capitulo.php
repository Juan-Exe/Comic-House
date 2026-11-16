<?php
/** Extracción temporal: ningún archivo del comprimido se publica sin validarlo. */
final class ArchivoCapituloInvalido extends RuntimeException {}

function borrarTemporalCapitulo(string $directorio): void
{
    if (!is_dir($directorio) || is_link($directorio)) return;
    foreach (new FilesystemIterator($directorio) as $entrada) {
        if ($entrada->isDir() && !$entrada->isLink()) {
            borrarTemporalCapitulo($entrada->getPathname());
        } else {
            @unlink($entrada->getPathname());
        }
    }
    @rmdir($directorio);
}

function esPaginaCapitulo(string $nombre): bool
{
    if (str_ends_with($nombre, '/') || str_ends_with($nombre, '\\')) return false;
    $partes = explode('/', str_replace('\\', '/', $nombre));
    foreach ($partes as $parte) {
        if ($parte === '.' || $parte === '') continue;
        if (str_starts_with($parte, '.') || $parte === '__MACOSX') return false;
    }
    return in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)),
        ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}

/** Revisa también la decodificación: una cabecera de imagen no basta. */
function comprobarPaginaCapitulo(string $ruta): array
{
    $tamano = filesize($ruta);
    if (!$tamano || $tamano > 64 * 1024 * 1024) {
        throw new ArchivoCapituloInvalido('Una página está vacía o supera los 64 MB admitidos.');
    }
    $info = @getimagesize($ruta);
    $extensiones = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($extensiones[$info[2]])) {
        throw new ArchivoCapituloInvalido('El archivo contiene una página dañada o que no es una imagen válida.');
    }
    // Evita agotar los 512 MB de PHP al decodificar una sola página.
    if ($info[0] * $info[1] > 40000000) {
        throw new ArchivoCapituloInvalido('Una página supera los 40 megapíxeles admitidos.');
    }
    $imagen = @imagecreatefromstring(file_get_contents($ruta));
    if ($imagen === false) {
        throw new ArchivoCapituloInvalido('El archivo contiene una imagen dañada. Vuelve a obtener el CBR o CBZ.');
    }
    imagedestroy($imagen);
    return ['extension' => $extensiones[$info[2]], 'es_doble' => (int) ($info[0] > $info[1])];
}

/** @return array{directorio:string, paginas:array} El llamador debe limpiar el temporal. */
function prepararArchivoCapitulo(string $archivo, string $nombre): array
{
    $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $firma = file_get_contents($archivo, false, null, 0, 8);
    $zipValido = str_starts_with($firma, "PK\x03\x04") || str_starts_with($firma, "PK\x05\x06");
    $rarValido = str_starts_with($firma, "Rar!\x1a\x07\x00") || $firma === "Rar!\x1a\x07\x01\x00";
    if (!in_array($extension, ['cbz', 'cbr'], true) ||
        ($extension === 'cbz' && !$zipValido) || ($extension === 'cbr' && !$rarValido)) {
        throw new ArchivoCapituloInvalido('Elige un CBZ (ZIP) o CBR (RAR) válido. Cambiar la extensión no convierte el archivo.');
    }
    $directorio = sys_get_temp_dir() . '/comic-capitulo-' . bin2hex(random_bytes(16));
    if (!mkdir($directorio, 0700)) throw new RuntimeException('No se pudo preparar la subida.');
    register_shutdown_function(static function () use ($directorio) {
        borrarTemporalCapitulo($directorio);
    });
    try {
        $candidatas = [];
        if ($extension === 'cbz') {
            $zip = new ZipArchive();
            if ($zip->open($archivo, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
                throw new ArchivoCapituloInvalido('El CBZ está dañado, incompleto o protegido. No se guardó el capítulo.');
            }
            try {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (!$stat || !esPaginaCapitulo($stat['name'])) continue;
                    if ($stat['size'] > 64 * 1024 * 1024) {
                        throw new ArchivoCapituloInvalido('Una página supera los 64 MB admitidos.');
                    }
                    $contenido = $zip->getFromIndex($i);
                    if ($contenido === false || strlen($contenido) !== $stat['size'] ||
                        sprintf('%u', crc32($contenido)) !== sprintf('%u', $stat['crc'])) {
                        throw new ArchivoCapituloInvalido('El CBZ contiene una página dañada o protegida. No se guardó el capítulo.');
                    }
                    // No se usan rutas del ZIP como destinos en el sistema de archivos.
                    $ruta = $directorio . '/zip-' . $i;
                    if (file_put_contents($ruta, $contenido) !== strlen($contenido)) {
                        throw new RuntimeException('No se pudieron preparar las páginas.');
                    }
                    unset($contenido);
                    $origen = preg_replace('#^(?:\./)+#', '', str_replace('\\', '/', $stat['name']));
                    $candidatas[] = ['origen' => $origen, 'ruta' => $ruta];
                }
            } finally {
                $zip->close();
            }
        } else {
            $extraido = $directorio . '/rar';
            mkdir($extraido, 0700);
            $log = $directorio . '/extraccion.log';
            // UnRAR comunica los errores de CRC que unrar-free 0.3.1 puede omitir.
            // Sin shell, sin contraseñas interactivas ni sobrescrituras.
            $proceso = proc_open(['unrar', 'x', '-inul', '-p-', '-o-', '-ai', '-cfg-',
                $archivo, $extraido . '/'], [0 => ['file', '/dev/null', 'r'],
                1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes);
            if (!is_resource($proceso)) throw new RuntimeException('No se pudo abrir el CBR.');
            if (proc_close($proceso) !== 0) {
                throw new ArchivoCapituloInvalido('El CBR está dañado, incompleto o protegido. No se guardó el capítulo.');
            }
            $entradas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $extraido, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($entradas as $entrada) {
                if ($entrada->isLink() || (!$entrada->isFile() && !$entrada->isDir())) {
                    throw new ArchivoCapituloInvalido('El CBR contiene una entrada que no se puede leer como página.');
                }
                $relativa = substr($entrada->getPathname(), strlen($extraido) + 1);
                if ($entrada->isFile() && esPaginaCapitulo($relativa)) {
                    $candidatas[] = ['origen' => $relativa, 'ruta' => $entrada->getPathname()];
                }
            }
        }
        if (!$candidatas) {
            throw new ArchivoCapituloInvalido('El archivo no contiene páginas de imagen válidas (JPG, PNG, GIF o WebP).');
        }
        usort($candidatas, fn($a, $b) => strnatcasecmp($a['origen'], $b['origen']));
        $paginas = [];
        foreach ($candidatas as $i => $candidata) {
            $info = comprobarPaginaCapitulo($candidata['ruta']);
            $paginas[] = ['ruta' => $candidata['ruta'],
                'nombre' => sprintf('%05d', $i + 1) . '.' . $info['extension'],
                'es_doble' => $info['es_doble']];
        }
        return ['directorio' => $directorio, 'paginas' => $paginas];
    } catch (Throwable $e) {
        borrarTemporalCapitulo($directorio);
        throw $e;
    }
}
