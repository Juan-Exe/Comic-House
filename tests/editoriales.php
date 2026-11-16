<?php
/** Reglas de identidad y validación de logos. Sin red ni escrituras. */
require_once dirname(__DIR__) . '/controlador/editorial_servicio.php';
use function ComicHouse\Editoriales\urlLogoPermitida;
use function ComicHouse\Editoriales\pngLogo;
use function ComicHouse\Editoriales\ficheroLogoInfobox;

$correctas = 0;
function comprobar(bool $ok, string $mensaje): void {
    global $correctas;
    if (!$ok) throw new RuntimeException($mensaje);
    $correctas++; echo "OK: $mensaje\n";
}
function rechazado(callable $accion): bool {
    try { $accion(); return false; } catch (Throwable $e) { return true; }
}

comprobar(normalizarEditorial("  Image   Comics \n") === 'Image Comics', 'Recorta y compacta espacios sin cambiar la empresa');
comprobar(clave_editorial('ÉDITORÍAL ÑANDÚ') === clave_editorial('editorial nandu'), 'Compara sin mayúsculas ni tildes');
comprobar(clave_editorial('講談社') !== clave_editorial('小学館'), 'Los nombres no latinos no se convierten en una clave vacía');
foreach (['dc comics'=>'DC', 'IMAGE'=>'Image Comics', 'darkhorse'=>'Dark Horse', 'marvel comic'=>'Marvel Comics'] as $alias=>$canonico) {
    comprobar(nombreCanonicoEditorial($alias) === $canonico, "Reconoce el alias $alias");
}
comprobar(nombreCanonicoEditorial('Marvle') === 'Marvle', 'Una semejanza no cambia el nombre automáticamente');
comprobar(mas_parecida('Marvle', ['Marvel','Image']) === ['Marvel',1], 'Detecta la transposición como sugerencia');

foreach (['https://upload.wikimedia.org/a.png','https://thumb.wikimedia.org/a.svg.png?width=500'] as $url) {
    comprobar(urlLogoPermitida($url), 'Admite el servidor de imágenes de Wikimedia: ' . parse_url($url,PHP_URL_HOST));
}
foreach (['http://upload.wikimedia.org/a.png','https://upload.wikimedia.org.evil.test/a.png',
    'https://evilupload.wikimedia.org/a.png','https://example.com/a.png','file:///etc/passwd',
    'https://upload.wikimedia.org:444/a.png','https://user@upload.wikimedia.org/a.png',
    'https://upload.wikimedia.org/a.png#fragment','http://127.0.0.1/a.png'] as $url) {
    comprobar(!urlLogoPermitida($url), 'Rechaza un origen o dirección no permitidos: ' . $url);
}
comprobar(ficheroLogoInfobox("| image = Kodansha_head_office.jpg\n| logo = Kōdansha logo.svg") === 'Kōdansha logo.svg',
    'Elige el campo logo y no la foto de la sede');
comprobar(ficheroLogoInfobox("| logo = [[File:Company logo.svg|200px]]") === 'Company logo.svg', 'Lee el nombre del logo dentro de un enlace');
comprobar(ficheroLogoInfobox("| image = Foto.jpg\n| logo = \n| caption = oficina") === null, 'No propone una foto si el logo está vacío');
comprobar(ficheroLogoInfobox('| logo = {{nombre generado}}') === null, 'Una plantilla no resuelta no se usa como nombre de archivo');
$imagen = imagecreatetruecolor(3,2);
imagefill($imagen,0,0,imagecolorallocate($imagen,255,0,0));
ob_start(); imagepng($imagen); $png = ob_get_clean(); imagedestroy($imagen);
comprobar(getimagesizefromstring(pngLogo($png))[2] === IMAGETYPE_PNG, 'Decodifica y vuelve a codificar una imagen real');
$cabeceraGrande = substr_replace($png, pack('NN',4001,4001),16,8);
comprobar(rechazado(fn()=>pngLogo($cabeceraGrande)), 'Rechaza más de 16 megapíxeles antes de decodificar la imagen');
foreach (['','<html>no es un logo</html>','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='),
    str_repeat('a',5*1024*1024+1)] as $datos) {
    comprobar(rechazado(fn()=>pngLogo($datos)), 'Rechaza una imagen vacía, falsa, dañada o demasiado grande');
}
comprobar(rechazado(fn()=>ComicHouse\Editoriales\descargarLogo('https://example.com/a.png')),
    'La descarga también impide un origen externo antes de abrir la conexión');
echo "\n$correctas comprobaciones de editoriales correctas.\n";
