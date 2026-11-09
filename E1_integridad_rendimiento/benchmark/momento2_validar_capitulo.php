<?php
/**
 * momento2_validar_capitulo.php - Comprueba las reglas de numeración
 * de capítulos.
 *
 * Ejecutar:
 *   docker exec comic_house_web php \
 *     /var/www/html/E1_integridad_rendimiento/benchmark/momento2_validar_capitulo.php
 */

require_once dirname(__DIR__, 2) . '/modelo/conexion.php';
require_once dirname(__DIR__, 2) . '/controlador/validar_capitulo.php';

$ok = 0; $fallos = 0;

function caso(string $etiqueta, $obtenido, $esperado): void
{
    global $ok, $fallos;
    $bien = $obtenido === $esperado;
    $bien ? $ok++ : $fallos++;
    printf("%s %-46s -> %s\n",
        $bien ? '[OK]   ' : '[FALLO]',
        $etiqueta,
        var_export($obtenido, true));
}

function dictamen(mysqli $cx, int $comic, string $tipo, string $fmt,
                  string $d, string $h = ''): string
{
    return revisarCapitulo($cx, $comic, $tipo, $fmt, $d, $h)['estado'];
}

echo "=== El número de capítulo solo admite dígitos ===\n";
caso("'12'      -> 12",        numeroDeCapitulo('12'), 12);
caso("'1100'    -> 1100",      numeroDeCapitulo('1100'), 1100);
caso("'cap 3'   -> rechazado", numeroDeCapitulo('cap 3'), null);
caso("'3 '      -> 3 (se recorta)", numeroDeCapitulo('3 '), 3);
caso("'1 2'     -> rechazado", numeroDeCapitulo('1 2'), null);
caso("'#4'      -> rechazado", numeroDeCapitulo('#4'), null);
caso("''        -> rechazado", numeroDeCapitulo(''), null);

echo "\n=== Ceros a la izquierda: '007' no es un capítulo nuevo ===\n";
caso("'007'     -> rechazado", numeroDeCapitulo('007'), null);
caso("'0'       -> rechazado", numeroDeCapitulo('0'), null);
caso("'10'      -> 10 (el cero interior sí vale)", numeroDeCapitulo('10'), 10);

echo "\n=== Tope de cuatro dígitos (hay obras de más de mil) ===\n";
caso("'9999'    -> 9999",      numeroDeCapitulo('9999'), 9999);
caso("'10000'   -> rechazado", numeroDeCapitulo('10000'), null);

// Berserk es manga (id 1); Absolute Batman es cómic (id 3).
echo "\n=== Cada tipo de obra admite sus formatos ===\n";
caso('Manga con rango',  dictamen($conexion, 1, 'Manga', 'rango', '20', '24'), 'ok');
caso('Manga con tomo',   dictamen($conexion, 1, 'Manga', 'tomo', ''), 'error');
caso('Cómic con tomo',   dictamen($conexion, 3, 'Comic', 'tomo', ''), 'ok');
caso('Cómic con grapa',  dictamen($conexion, 3, 'Comic', 'grapa', '40'), 'aviso');

echo "\n=== Un rango coherente ===\n";
caso('Rango 24 a 20 (invertido)', dictamen($conexion, 1, 'Manga', 'rango', '24', '20'), 'error');
caso('Rango 20 a 20 (es grapa)',  dictamen($conexion, 1, 'Manga', 'rango', '20', '20'), 'error');

echo "\n=== No se puede subir dos veces lo mismo ===\n";
// Berserk ya tiene #1-#3, #4-#5 y #6-#9 subidos.
caso('Repetir el #2 (ya está en #1-#3)', dictamen($conexion, 1, 'Manga', 'grapa', '2'), 'error');
caso('Rango 3 a 7 (pisa dos entregas)',  dictamen($conexion, 1, 'Manga', 'rango', '3', '7'), 'error');
caso('Rango 10 a 14 (libre)',            dictamen($conexion, 1, 'Manga', 'rango', '10', '14'), 'ok');

echo "\n=== Avisa si supera lo que la obra declara ===\n";
$declarados = capitulosDeclarados($conexion, 1);
echo "    (Berserk declara " . var_export($declarados, true) . " capítulos)\n";
caso('Capítulo muy por encima del total', dictamen($conexion, 1, 'Manga', 'grapa', '9000'), 'aviso');

echo "\n=== La etiqueta se compone sola ===\n";
caso("rango 1-3  -> '#1 - #3'", etiquetaCapitulo('rango', 1, 3), '#1 - #3');
caso("grapa 7    -> '#7'",      etiquetaCapitulo('grapa', 7, 7), '#7');
caso("tomo       -> 'Tomo único'", etiquetaCapitulo('tomo', 1, 1), 'Tomo único');

echo "\n" . str_repeat('-', 62) . "\n";
printf("Resultado: %d correctas, %d fallidas\n", $ok, $fallos);
echo str_repeat('-', 62) . "\n";

exit($fallos === 0 ? 0 : 1);
