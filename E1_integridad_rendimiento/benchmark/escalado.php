<?php
/**
 * escalado.php - Con indice vs sin indice al crecer el volumen.
 * Responde: "que pasaria si el volumen crece".
 */
require_once __DIR__ . '/../../modelo/conexion.php';

function cron(mysqli $cx, string $sql, int $n = 15): float {
    $cx->query($sql)->free();
    $t = microtime(true);
    for ($i = 0; $i < $n; $i++) $cx->query($sql)->free();
    return round(((microtime(true) - $t) * 1000) / $n, 3);
}

$cx = $conexion;
$cx->query("DROP TABLE IF EXISTS esc_bench");
$cx->query("CREATE TABLE esc_bench (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capitulo_id INT UNSIGNED NOT NULL,
    imagen_url VARCHAR(255) NOT NULL,
    orden INT UNSIGNED NOT NULL,
    es_doble TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_cap_orden (capitulo_id, orden)
) ENGINE=InnoDB");

$SIN = "SELECT id, imagen_url, orden FROM esc_bench IGNORE INDEX (idx_cap_orden)
        WHERE capitulo_id = 1 ORDER BY orden";
$CON = "SELECT id, imagen_url, orden FROM esc_bench
        WHERE capitulo_id = 1 ORDER BY orden";

echo "\n==========================================================================\n";
echo "  ESCALADO · lectura de un capitulo (220 paginas) al crecer el catalogo\n";
echo "==========================================================================\n\n";
printf("  %-10s %-11s %13s %13s %10s\n",
       'CAPITULOS', 'FILAS', 'SIN INDICE', 'CON INDICE', 'FACTOR');
echo "  " . str_repeat('-', 64) . "\n";

$PAGS = 220;
$acum = 0;
foreach ([10, 100, 500, 2000, 4000] as $caps) {
    $cx->begin_transaction();
    $buf = [];
    for ($c = $acum + 1; $c <= $caps; $c++) {
        for ($p = 1; $p <= $PAGS; $p++) {
            $buf[] = "($c,'paginas/cap_$c/$p.jpg',$p,0)";
            if (count($buf) >= 1000) {
                $cx->query("INSERT INTO esc_bench (capitulo_id,imagen_url,orden,es_doble)
                            VALUES " . implode(',', $buf));
                $buf = [];
            }
        }
    }
    if ($buf) {
        $cx->query("INSERT INTO esc_bench (capitulo_id,imagen_url,orden,es_doble)
                    VALUES " . implode(',', $buf));
    }
    $cx->commit();
    $acum = $caps;
    $cx->query("ANALYZE TABLE esc_bench");

    $filas = (int)$cx->query("SELECT COUNT(*) c FROM esc_bench")->fetch_assoc()['c'];
    $ts = cron($cx, $SIN);
    $tc = cron($cx, $CON);
    printf("  %-10s %-11s %11.3f ms %11.3f ms %9.0fx\n",
        number_format($caps), number_format($filas), $ts, $tc, $ts / max($tc, 0.001));
}
echo "  " . str_repeat('-', 64) . "\n\n";
echo "  SIN indice el tiempo crece con la tabla (O(n)).\n";
echo "  CON indice se mantiene practicamente constante (O(log n)).\n\n";

$cx->query("DROP TABLE IF EXISTS esc_bench");
