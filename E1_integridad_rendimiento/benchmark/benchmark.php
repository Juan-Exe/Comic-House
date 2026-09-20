<?php
/**
 * benchmark.php - Evidencia de rendimiento ANTES vs DESPUES
 *
 * Mide la insercion de paginas de un capitulo, que es la operacion mas
 * costosa de Comic House: al subir un CBR de 220 paginas el controlador
 * actual ejecuta 220 INSERT individuales en autocommit.
 *
 * ESCENARIO A (antes)   : INSERT por pagina, autocommit, sin indice
 * ESCENARIO B (despues) : prepared statement + transaccion + bulk insert
 *
 * Uso: php benchmark.php [n_paginas] [n_repeticiones]
 */

require_once __DIR__ . '/../../modelo/conexion.php';

$N_PAGINAS = isset($argv[1]) ? (int)$argv[1] : 220;
$REPES     = isset($argv[2]) ? (int)$argv[2] : 3;

function ms(float $t0): float {
    return round((microtime(true) - $t0) * 1000, 2);
}

function linea(string $c = '-'): void {
    echo str_repeat($c, 72) . "\n";
}

function banner(string $t): void {
    echo "\n"; linea('='); echo "  $t\n"; linea('=');
}

// ---------------------------------------------------------------
// Preparar entorno aislado: tablas _bench que imitan el modelo real
// ---------------------------------------------------------------
$conexion->query("DROP TABLE IF EXISTS paginas_bench");
$conexion->query("DROP TABLE IF EXISTS capitulos_bench");

$conexion->query("
CREATE TABLE capitulos_bench (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comic_id INT UNSIGNED NOT NULL,
    titulo   VARCHAR(255) NOT NULL,
    numero   VARCHAR(50)  NOT NULL
) ENGINE=InnoDB");

// Tabla SIN indice en capitulo_id: replica el estado actual del proyecto
$conexion->query("
CREATE TABLE paginas_bench (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capitulo_id INT UNSIGNED NOT NULL,
    imagen_url  VARCHAR(255) NOT NULL,
    orden       INT UNSIGNED NULL,
    es_doble    TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB");

$conexion->query("INSERT INTO capitulos_bench (comic_id, titulo, numero)
                  VALUES (1, 'Capitulo de prueba', '#1')");
$CAP_ID = $conexion->insert_id;

// Datos sinteticos identicos para ambos escenarios
$paginas = [];
for ($i = 1; $i <= $N_PAGINAS; $i++) {
    $paginas[] = [
        'url'   => sprintf('paginas/cap_%d/%04d.jpg', $CAP_ID, $i),
        'orden' => $i,
        'doble' => ($i % 17 === 0) ? 1 : 0,
    ];
}

banner("BENCHMARK · Insercion de paginas de un capitulo");
echo "  Paginas por capitulo : $N_PAGINAS\n";
echo "  Repeticiones         : $REPES\n";
echo "  Motor                : " . $conexion->get_server_info() . "\n";

// ---------------------------------------------------------------
// ESCENARIO A · codigo actual
// ---------------------------------------------------------------
function escenario_a(mysqli $cx, int $cap, array $paginas): float {
    $cx->query("DELETE FROM paginas_bench");
    $t0 = microtime(true);

    // Replica exacta de controlador/procesar_capitulo.php lineas 100-113
    foreach ($paginas as $p) {
        $url = $cx->real_escape_string($p['url']);
        $cx->query("INSERT INTO paginas_bench (capitulo_id, imagen_url, orden, es_doble)
                    VALUES ('$cap', '$url', '{$p['orden']}', '{$p['doble']}')");
    }
    return ms($t0);
}

// ---------------------------------------------------------------
// ESCENARIO B · optimizado
// ---------------------------------------------------------------
function escenario_b(mysqli $cx, int $cap, array $paginas): float {
    $cx->query("DELETE FROM paginas_bench");
    $t0 = microtime(true);

    $cx->begin_transaction();

    // Bulk insert en lotes de 100 filas
    $LOTE = 100;
    $total = count($paginas);
    for ($i = 0; $i < $total; $i += $LOTE) {
        $trozo = array_slice($paginas, $i, $LOTE);
        $valores = [];
        foreach ($trozo as $p) {
            $url = $cx->real_escape_string($p['url']);
            $valores[] = "($cap,'$url',{$p['orden']},{$p['doble']})";
        }
        $cx->query("INSERT INTO paginas_bench (capitulo_id, imagen_url, orden, es_doble)
                    VALUES " . implode(',', $valores));
    }

    $cx->commit();
    return ms($t0);
}

// ---------------------------------------------------------------
// ESCENARIO B2 · prepared statement + transaccion (sin bulk)
// ---------------------------------------------------------------
function escenario_b2(mysqli $cx, int $cap, array $paginas): float {
    $cx->query("DELETE FROM paginas_bench");
    $t0 = microtime(true);

    $cx->begin_transaction();
    $st = $cx->prepare("INSERT INTO paginas_bench (capitulo_id, imagen_url, orden, es_doble)
                        VALUES (?,?,?,?)");
    foreach ($paginas as $p) {
        $st->bind_param('isii', $cap, $p['url'], $p['orden'], $p['doble']);
        $st->execute();
    }
    $st->close();
    $cx->commit();
    return ms($t0);
}

// ---------------------------------------------------------------
// Ejecutar
// ---------------------------------------------------------------
$res = ['A' => [], 'B2' => [], 'B' => []];

for ($r = 1; $r <= $REPES; $r++) {
    $res['A'][]  = escenario_a($conexion, $CAP_ID, $paginas);
    $res['B2'][] = escenario_b2($conexion, $CAP_ID, $paginas);
    $res['B'][]  = escenario_b($conexion, $CAP_ID, $paginas);
    echo "  repeticion $r ... ok\n";
}

function media(array $a): float { return round(array_sum($a) / count($a), 2); }
function mejor(array $a): float { return round(min($a), 2); }

$mA  = media($res['A']);
$mB2 = media($res['B2']);
$mB  = media($res['B']);

banner("RESULTADOS · tiempo de insercion de $N_PAGINAS paginas");
printf("  %-42s %10s %10s\n", 'ESCENARIO', 'MEDIA ms', 'MEJOR ms');
linea();
printf("  %-42s %10.2f %10.2f\n", 'A · INSERT por pagina (codigo actual)', $mA, mejor($res['A']));
printf("  %-42s %10.2f %10.2f\n", 'B2 · prepared + transaccion', $mB2, mejor($res['B2']));
printf("  %-42s %10.2f %10.2f\n", 'B · bulk insert + transaccion', $mB, mejor($res['B']));
linea();
printf("  Factor de mejora B2 vs A : %.1fx mas rapido\n", $mA / max($mB2, 0.01));
printf("  Factor de mejora B  vs A : %.1fx mas rapido\n", $mA / max($mB, 0.01));
printf("  Tiempo ahorrado (B)      : %.0f ms por capitulo\n", $mA - $mB);

// ---------------------------------------------------------------
// Parte 2 · EXPLAIN de la consulta de lectura del lector
// ---------------------------------------------------------------
banner("EXPLAIN · consulta del lector (paginas de un capitulo en orden)");

// Poblar con volumen realista
$conexion->query("DELETE FROM paginas_bench");
$conexion->query("ALTER TABLE paginas_bench DROP INDEX IF EXISTS idx_cap_orden");

echo "  Generando volumen de prueba...\n";
$conexion->begin_transaction();
$LOTE = 500;
$buffer = [];
$TOTAL_VOL = 250000;
for ($i = 1; $i <= $TOTAL_VOL; $i++) {
    $cap = ($i % 5000) + 1;
    $ord = ($i % 240) + 1;
    $buffer[] = "($cap,'paginas/cap_$cap/$i.jpg',$ord,0)";
    if (count($buffer) >= $LOTE) {
        $conexion->query("INSERT INTO paginas_bench (capitulo_id, imagen_url, orden, es_doble)
                          VALUES " . implode(',', $buffer));
        $buffer = [];
    }
}
if ($buffer) {
    $conexion->query("INSERT INTO paginas_bench (capitulo_id, imagen_url, orden, es_doble)
                      VALUES " . implode(',', $buffer));
}
$conexion->commit();

$filas = $conexion->query("SELECT COUNT(*) c FROM paginas_bench")->fetch_assoc()['c'];
echo "  Filas en paginas_bench: " . number_format($filas) . "\n";

$CONSULTA = "SELECT id, imagen_url, orden, es_doble
             FROM paginas_bench
             WHERE capitulo_id = 3512
             ORDER BY orden";

function explicar(mysqli $cx, string $sql, string $etiqueta): void {
    echo "\n  --- EXPLAIN $etiqueta ---\n";
    $r = $cx->query("EXPLAIN $sql");
    while ($f = $r->fetch_assoc()) {
        printf("    type=%-6s key=%-18s rows=%-9s Extra=%s\n",
            $f['type'], $f['key'] ?? 'NULL', number_format((int)$f['rows']),
            $f['Extra'] ?? '');
    }
}

function cronometrar(mysqli $cx, string $sql, int $veces = 20): float {
    // calentamiento
    $cx->query($sql)->free();
    $t0 = microtime(true);
    for ($i = 0; $i < $veces; $i++) {
        $cx->query($sql)->free();
    }
    return round(((microtime(true) - $t0) * 1000) / $veces, 3);
}

explicar($conexion, $CONSULTA, 'ANTES del indice');
$t_antes = cronometrar($conexion, $CONSULTA);
printf("    tiempo medio: %.3f ms\n", $t_antes);

echo "\n  Creando indice compuesto...\n";
$conexion->query("CREATE INDEX idx_cap_orden ON paginas_bench(capitulo_id, orden)");

explicar($conexion, $CONSULTA, 'DESPUES del indice');
$t_despues = cronometrar($conexion, $CONSULTA);
printf("    tiempo medio: %.3f ms\n", $t_despues);

banner("FACTOR DE MEJORA · consulta de lectura");
printf("  Antes del indice  : %8.3f ms\n", $t_antes);
printf("  Despues del indice: %8.3f ms\n", $t_despues);
printf("  Factor            : %8.1fx mas rapido\n", $t_antes / max($t_despues, 0.001));

// ---------------------------------------------------------------
// Parte 3 · proyeccion de crecimiento
// ---------------------------------------------------------------
banner("PROYECCION · que pasa si el volumen crece");
echo "  Se reconstruye la tabla a cada tamano real (no se filtra por id,\n";
echo "  porque eso falsearia el costo del escaneo).\n\n";
printf("  %-16s %14s %14s %12s\n", 'FILAS', 'SIN INDICE ms', 'CON INDICE ms', 'FACTOR');
linea();

foreach ([50000, 100000, 250000] as $tam) {
    // reconstruir la tabla con exactamente $tam filas
    $conexion->query("DELETE FROM paginas_bench");
    $conexion->begin_transaction();
    $buffer = [];
    for ($i = 1; $i <= $tam; $i++) {
        $cap = ($i % 5000) + 1;
        $ord = ($i % 240) + 1;
        $buffer[] = "($cap,'paginas/cap_$cap/$i.jpg',$ord,0)";
        if (count($buffer) >= 500) {
            $conexion->query("INSERT INTO paginas_bench (capitulo_id, imagen_url, orden, es_doble)
                              VALUES " . implode(',', $buffer));
            $buffer = [];
        }
    }
    if ($buffer) {
        $conexion->query("INSERT INTO paginas_bench (capitulo_id, imagen_url, orden, es_doble)
                          VALUES " . implode(',', $buffer));
    }
    $conexion->commit();
    $conexion->query("ANALYZE TABLE paginas_bench");

    $sql_sin = "SELECT id, imagen_url, orden FROM paginas_bench IGNORE INDEX (idx_cap_orden)
                WHERE capitulo_id = 3512 ORDER BY orden";
    $sql_con = "SELECT id, imagen_url, orden FROM paginas_bench
                WHERE capitulo_id = 3512 ORDER BY orden";
    $ts = cronometrar($conexion, $sql_sin, 10);
    $tc = cronometrar($conexion, $sql_con, 10);
    printf("  %-16s %14.3f %14.3f %11.1fx\n",
        number_format($tam), $ts, $tc, $ts / max($tc, 0.001));
}
linea();

echo "\n";
echo "  Limpieza de tablas de prueba...\n";
$conexion->query("DROP TABLE IF EXISTS paginas_bench");
$conexion->query("DROP TABLE IF EXISTS capitulos_bench");
echo "  Listo.\n\n";
