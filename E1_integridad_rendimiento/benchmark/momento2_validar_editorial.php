<?php
/**
 * momento2_validar_editorial.php - Comprueba la deteccion de nombres
 * de editorial mal escritos.
 *
 * Ejecutar:
 *   docker exec comic_house_web php \
 *     /var/www/html/E1_integridad_rendimiento/benchmark/momento2_validar_editorial.php
 */

require_once dirname(__DIR__, 2) . '/modelo/conexion.php';
require_once dirname(__DIR__, 2) . '/controlador/validar_editorial.php';

$ok = 0; $fallos = 0;

function caso(mysqli $cx, string $entrada, string $esperado, string $sug = ''): void
{
    global $ok, $fallos;
    $d = revisarEditorial($cx, $entrada);
    $bien = $d['estado'] === $esperado
            && ($sug === '' || $d['sugerencia'] === $sug);
    $bien ? $ok++ : $fallos++;

    printf("%s %-22s -> %-11s %s\n",
        $bien ? '[OK]   ' : '[FALLO]',
        "'$entrada'",
        $d['estado'],
        $d['estado'] === 'ok' ? '' : '(«' . $d['sugerencia'] . '»)');
}

echo "Editoriales registradas: ";
echo implode(', ', array_column(
    $conexion->query("SELECT nombre FROM editoriales ORDER BY nombre")
             ->fetch_all(MYSQLI_ASSOC), 'nombre')) . "\n\n";

echo "=== Ya registradas, escritas de otra forma ===\n";
caso($conexion, 'DC', 'duplicada');
caso($conexion, '  dc  ', 'duplicada');
caso($conexion, 'MARVEL COMICS', 'duplicada');

echo "\n=== Errores de escritura (debe sugerir) ===\n";
caso($conexion, 'Marvle Comics', 'sugerencia');
caso($conexion, 'Dark Hose', 'sugerencia');
caso($conexion, 'Imagen Comics', 'sugerencia');
caso($conexion, 'Norma Editoria', 'sugerencia');

echo "\n=== Variante conocida -> propone el nombre canonico ===\n";
caso($conexion, 'Panini', 'sugerencia', 'Panini Comics');
caso($conexion, 'idw', 'sugerencia', 'IDW Publishing');
caso($conexion, 'boom studios', 'sugerencia', 'Boom! Studios');

echo "\n=== Transposiciones: dos letras cambiadas de orden ===\n";
// Levenshtein cobra 2 por una transposicion, igual que por dos errores
// distintos. Son el error de tecleo mas comun, asi que se detectan aparte.
caso($conexion, 'Marvle', 'sugerencia', 'Marvel Comics');
caso($conexion, 'Veritgo', 'sugerencia', 'Vertigo');
caso($conexion, 'Ivrae', 'sugerencia', 'Ivrea');

echo "\n=== Editorial legitima no catalogada (debe aceptarse) ===\n";
caso($conexion, 'Editorial Hidra', 'ok');
caso($conexion, 'Astiberri', 'ok');
// Nombres cortos y reales que NO deben confundirse entre si.
caso($conexion, 'Kodansha', 'ok');
caso($conexion, 'Tebeos', 'ok');

echo "\n" . str_repeat('-', 56) . "\n";
printf("Resultado: %d correctas, %d fallidas\n", $ok, $fallos);
echo str_repeat('-', 56) . "\n";

exit($fallos === 0 ? 0 : 1);
