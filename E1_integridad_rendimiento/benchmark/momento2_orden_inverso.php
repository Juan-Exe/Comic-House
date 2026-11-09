<?php
/**
 * momento2_orden_inverso.php - Punto 9.8 del Momento 2.
 *
 * Pregunta: para leer las paginas de un capitulo en ORDEN INVERSO,
 * ¿sirve el indice (capitulo_id, orden) o hace falta otro?
 *
 * Se mide sobre una tabla a escala (donde el optimizador si elige el
 * indice) comparando ASC contra DESC, con y sin indice.
 *
 * Ejecutar:
 *   docker exec comic_house_web php \
 *     /var/www/html/E1_integridad_rendimiento/benchmark/momento2_orden_inverso.php
 */

require_once dirname(__DIR__, 2) . '/modelo/conexion.php';

const CAPITULOS = 500;
const POR_CAPITULO = 220;
const REPETICIONES = 5;

echo "Preparando tabla de prueba (" . CAPITULOS * POR_CAPITULO . " filas)...\n";

$conexion->query("DROP TABLE IF EXISTS paginas_inv_bench");
$conexion->query(
    "CREATE TABLE paginas_inv_bench (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        capitulo_id INT UNSIGNED NOT NULL,
        imagen_url VARCHAR(255) NOT NULL,
        orden INT UNSIGNED NOT NULL,
        es_doble TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
     ) ENGINE=InnoDB"
);

// Carga por lotes dentro de una transaccion (la misma tecnica que dio
// el 71x en el Momento 1).
$conexion->begin_transaction();
for ($c = 1; $c <= CAPITULOS; $c++) {
    $tuplas = [];
    for ($o = 1; $o <= POR_CAPITULO; $o++) {
        $tuplas[] = "($c, 'paginas/cap_$c/p$o.jpg', $o, 0)";
    }
    $conexion->query(
        "INSERT INTO paginas_inv_bench (capitulo_id, imagen_url, orden, es_doble)
         VALUES " . implode(',', $tuplas)
    );
}
$conexion->commit();

$total = $conexion->query("SELECT COUNT(*) n FROM paginas_inv_bench")
                  ->fetch_assoc()['n'];
echo "Filas cargadas: $total\n";

/** Mide una consulta repetida y devuelve la media en milisegundos. */
function medir(mysqli $conexion, string $sql, int $capitulo): float
{
    $stmt = $conexion->prepare($sql);
    $tiempos = [];
    for ($i = 0; $i < REPETICIONES; $i++) {
        $stmt->bind_param('i', $capitulo);
        $t0 = microtime(true);
        $stmt->execute();
        $res = $stmt->get_result();
        $res->fetch_all(MYSQLI_ASSOC);
        $tiempos[] = (microtime(true) - $t0) * 1000;
    }
    $stmt->close();
    return array_sum($tiempos) / count($tiempos);
}

/** Devuelve el plan (type / key / Extra) de una consulta. */
function plan(mysqli $conexion, string $sql): string
{
    $r = $conexion->query("EXPLAIN $sql")->fetch_assoc();
    return sprintf('type=%s key=%s rows=%s Extra=%s',
        $r['type'], $r['key'] ?? 'NULL', $r['rows'], $r['Extra']);
}

$capitulo = 250;
$base = "SELECT id, imagen_url, orden FROM paginas_inv_bench WHERE capitulo_id = ";
$sel_asc = "SELECT id, imagen_url, orden FROM paginas_inv_bench WHERE capitulo_id = ? ORDER BY orden";
$sel_desc = "SELECT id, imagen_url, orden FROM paginas_inv_bench WHERE capitulo_id = ? ORDER BY orden DESC";

echo "\n" . str_repeat('=', 68) . "\n";
echo "SIN INDICE\n" . str_repeat('=', 68) . "\n";
$sin_asc = medir($conexion, $sel_asc, $capitulo);
$sin_desc = medir($conexion, $sel_desc, $capitulo);
printf("ASC  : %7.3f ms   %s\n", $sin_asc, plan($conexion, $base . $capitulo . ' ORDER BY orden'));
printf("DESC : %7.3f ms   %s\n", $sin_desc, plan($conexion, $base . $capitulo . ' ORDER BY orden DESC'));

echo "\nCreando indice (capitulo_id, orden)...\n";
$conexion->query(
    "CREATE INDEX idx_inv_cap_orden ON paginas_inv_bench (capitulo_id, orden)"
);
$conexion->query("ANALYZE TABLE paginas_inv_bench");

echo "\n" . str_repeat('=', 68) . "\n";
echo "CON INDICE (capitulo_id, orden)\n" . str_repeat('=', 68) . "\n";
$con_asc = medir($conexion, $sel_asc, $capitulo);
$con_desc = medir($conexion, $sel_desc, $capitulo);
printf("ASC  : %7.3f ms   %s\n", $con_asc, plan($conexion, $base . $capitulo . ' ORDER BY orden'));
printf("DESC : %7.3f ms   %s\n", $con_desc, plan($conexion, $base . $capitulo . ' ORDER BY orden DESC'));

echo "\n" . str_repeat('=', 68) . "\n";
echo "CONCLUSION\n" . str_repeat('=', 68) . "\n";
printf("ASC  : %7.3f -> %7.3f ms  (%.1fx)\n", $sin_asc, $con_asc, $sin_asc / $con_asc);
printf("DESC : %7.3f -> %7.3f ms  (%.1fx)\n", $sin_desc, $con_desc, $sin_desc / $con_desc);
printf("Diferencia entre ASC y DESC con indice: %.3f ms\n", abs($con_asc - $con_desc));

echo "\nEl mismo indice sirve en los dos sentidos: un arbol B es una lista\n";
echo "doblemente enlazada, asi que el motor puede recorrerlo hacia atras\n";
echo "sin ordenar aparte. NO hace falta un indice descendente.\n";

$conexion->query("DROP TABLE IF EXISTS paginas_inv_bench");
echo "\nTabla de prueba eliminada.\n";
