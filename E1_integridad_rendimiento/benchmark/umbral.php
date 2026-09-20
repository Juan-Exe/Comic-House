<?php
/**
 * umbral.php - ¿A partir de que volumen empieza a servir el indice?
 *
 * Hallazgo del proyecto: con 884 paginas MariaDB IGNORA el indice
 * (type=ALL + filesort) porque un capitulo es ~25% de la tabla y leerla
 * entera sale mas barato. Este script busca el punto donde eso cambia.
 */
require_once __DIR__ . '/../../modelo/conexion.php';

function cron(mysqli $cx, string $sql, int $n = 15): float {
    $cx->query($sql)->free();                       // calentamiento
    $t = microtime(true);
    for ($i = 0; $i < $n; $i++) $cx->query($sql)->free();
    return round(((microtime(true) - $t) * 1000) / $n, 3);
}

function plan(mysqli $cx, string $sql): string {
    $f = $cx->query("EXPLAIN $sql")->fetch_assoc();
    return sprintf('type=%-5s key=%-22s rows=%-8s %s',
        $f['type'], $f['key'] ?? 'NULL',
        number_format((int)$f['rows']), $f['Extra'] ?? '');
}

$cx = $conexion;
$cx->query("DROP TABLE IF EXISTS umbral_bench");
$cx->query("CREATE TABLE umbral_bench (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capitulo_id INT UNSIGNED NOT NULL,
    imagen_url VARCHAR(255) NOT NULL,
    orden INT UNSIGNED NOT NULL,
    es_doble TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_cap_orden (capitulo_id, orden)
) ENGINE=InnoDB");

$SQL = "SELECT id, imagen_url, orden FROM umbral_bench
        WHERE capitulo_id = 1 ORDER BY orden";

echo "\n";
echo "===========================================================================\n";
echo "  UMBRAL · cuando el optimizador decide usar el indice\n";
echo "  Consulta: paginas del capitulo 1, en orden\n";
echo "===========================================================================\n\n";
printf("  %-12s %-9s %-10s  %s\n", 'CAPITULOS', 'FILAS', 'TIEMPO ms', 'PLAN ELEGIDO');
echo "  " . str_repeat('-', 105) . "\n";

// Cada capitulo tiene ~220 paginas (media real del proyecto)
$PAGS = 220;
$acumulado = 0;

foreach ([4, 10, 50, 200, 1000, 4000] as $n_caps) {
    // insertar capitulos hasta llegar a $n_caps
    $cx->begin_transaction();
    $buf = [];
    for ($c = $acumulado + 1; $c <= $n_caps; $c++) {
        for ($p = 1; $p <= $PAGS; $p++) {
            $buf[] = "($c,'paginas/cap_$c/$p.jpg',$p,0)";
            if (count($buf) >= 1000) {
                $cx->query("INSERT INTO umbral_bench (capitulo_id,imagen_url,orden,es_doble)
                            VALUES " . implode(',', $buf));
                $buf = [];
            }
        }
    }
    if ($buf) {
        $cx->query("INSERT INTO umbral_bench (capitulo_id,imagen_url,orden,es_doble)
                    VALUES " . implode(',', $buf));
    }
    $cx->commit();
    $acumulado = $n_caps;

    $cx->query("ANALYZE TABLE umbral_bench");
    $filas = (int)$cx->query("SELECT COUNT(*) c FROM umbral_bench")->fetch_assoc()['c'];
    $pct   = round($PAGS * 100 / $filas, 1);

    printf("  %-12s %-9s %-10.3f  %s\n",
        number_format($n_caps) . " ({$pct}%)",
        number_format($filas),
        cron($cx, $SQL),
        plan($cx, $SQL));
}

echo "\n";
echo "  Lectura: mientras un capitulo represente una fraccion GRANDE de la\n";
echo "  tabla, el escaneo completo gana. Al crecer el catalogo esa fraccion\n";
echo "  se reduce y el indice pasa a ser la unica opcion razonable.\n\n";

$cx->query("DROP TABLE IF EXISTS umbral_bench");
