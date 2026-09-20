<?php
/**
 * verificar_controlador.php - Prueba que la logica optimizada de
 * procesar_capitulo.php funciona sobre la base REAL (no un mock):
 *   1. inserta un capitulo + N paginas con el nuevo metodo por lotes
 *   2. comprueba que quedaron todas y en orden
 *   3. comprueba que la UNIQUE (capitulo_id, orden) rechaza duplicados
 *   4. comprueba el ROLLBACK ante un fallo a mitad de carga
 *   5. limpia todo lo que creo
 */
require_once __DIR__ . '/../../modelo/conexion.php';

// mysqli lanza excepciones por defecto en PHP 8; aqui queremos inspeccionar
// el error nosotros mismos, no que aborte el script.
mysqli_report(MYSQLI_REPORT_OFF);

$cx = $conexion;
$ok = 0; $fail = 0;

function chequeo(string $desc, bool $cond, string $extra = '') {
    global $ok, $fail;
    if ($cond) { $ok++;  echo "  [OK]   $desc" . ($extra ? " -> $extra" : "") . "\n"; }
    else       { $fail++; echo "  [FALLA] $desc" . ($extra ? " -> $extra" : "") . "\n"; }
}

echo "\n=== VERIFICACION DEL CONTROLADOR OPTIMIZADO ===\n\n";

// comic real existente
$comic = $cx->query("SELECT id FROM comics ORDER BY id LIMIT 1")->fetch_assoc();
$comic_id = (int)$comic['id'];

// --- 1. insertar capitulo de prueba ---
$st = $cx->prepare("INSERT INTO capitulos (comic_id, titulo, numero, fecha_publicacion)
                    VALUES (?,?,?,?)");
$titulo = 'ZZZ_PRUEBA_BENCHMARK';
$numero = '#TEST-' . time();
$fecha  = '2026-09-20';
$st->bind_param('isss', $comic_id, $titulo, $numero, $fecha);
$st->execute();
$cap_id = $cx->insert_id;
$st->close();
chequeo('Capitulo de prueba creado', $cap_id > 0, "id=$cap_id");

// --- 2. insercion por lotes (misma logica del controlador) ---
const LOTE = 100;
$N = 220;
$filas = [];
for ($i = 1; $i <= $N; $i++) {
    $filas[] = ['url' => "paginas/cap_$cap_id/" . sprintf('%04d', $i) . '.jpg',
                'orden' => $i, 'es_doble' => ($i % 17 === 0) ? 1 : 0];
}

$t0 = microtime(true);
$cx->begin_transaction();
try {
    for ($i = 0; $i < count($filas); $i += LOTE) {
        $lote = array_slice($filas, $i, LOTE);
        $tuplas = [];
        foreach ($lote as $f) {
            $u = $cx->real_escape_string($f['url']);
            $tuplas[] = "($cap_id,'$u',{$f['orden']},{$f['es_doble']})";
        }
        if (!$cx->query("INSERT INTO paginas (capitulo_id,imagen_url,orden,es_doble)
                         VALUES " . implode(',', $tuplas))) {
            throw new RuntimeException($cx->error);
        }
    }
    $cx->commit();
} catch (Throwable $e) {
    $cx->rollback();
    echo "  ERROR: " . $e->getMessage() . "\n";
}
$ms = round((microtime(true) - $t0) * 1000, 2);

$n = (int)$cx->query("SELECT COUNT(*) c FROM paginas WHERE capitulo_id=$cap_id")
              ->fetch_assoc()['c'];
chequeo("Se insertaron $N paginas", $n === $N, "$n filas en {$ms} ms");

// --- 3. orden correcto ---
$r = $cx->query("SELECT orden FROM paginas WHERE capitulo_id=$cap_id ORDER BY orden");
$esperado = 1; $ordenado = true;
while ($f = $r->fetch_assoc()) {
    if ((int)$f['orden'] !== $esperado++) { $ordenado = false; break; }
}
chequeo('Las paginas quedaron en orden 1..220', $ordenado);

// --- 4. UNIQUE rechaza orden duplicado ---
$dup = false; $dup_err = '';
try {
    $dup = $cx->query("INSERT INTO paginas (capitulo_id,imagen_url,orden,es_doble)
                       VALUES ($cap_id,'paginas/dup.jpg',5,0)");
} catch (Throwable $e) { $dup = false; $dup_err = 'errno=' . $cx->errno; }
chequeo('UNIQUE (capitulo_id, orden) rechaza duplicado', $dup === false, $dup_err);

// --- 5. CHECK rechaza orden <= 0 ---
$neg = false; $neg_err = '';
try {
    $neg = $cx->query("INSERT INTO paginas (capitulo_id,imagen_url,orden,es_doble)
                       VALUES ($cap_id,'paginas/neg.jpg',0,0)");
} catch (Throwable $e) { $neg = false; $neg_err = 'errno=' . $cx->errno; }
chequeo('CHECK (orden > 0) rechaza orden cero', $neg === false, $neg_err);

// --- 6. ROLLBACK deja la tabla intacta ---
$antes = (int)$cx->query("SELECT COUNT(*) c FROM paginas WHERE capitulo_id=$cap_id")
                  ->fetch_assoc()['c'];
$cx->begin_transaction();
$cx->query("INSERT INTO paginas (capitulo_id,imagen_url,orden,es_doble)
            VALUES ($cap_id,'paginas/tmp1.jpg',9001,0)");
try {
    $cx->query("INSERT INTO paginas (capitulo_id,imagen_url,orden,es_doble)
                VALUES ($cap_id,'paginas/tmp2.jpg',5,0)");  // choca con UNIQUE
} catch (Throwable $e) { /* esperado */ }
$cx->rollback();
$despues = (int)$cx->query("SELECT COUNT(*) c FROM paginas WHERE capitulo_id=$cap_id")
                    ->fetch_assoc()['c'];
chequeo('ROLLBACK deshizo la carga parcial', $antes === $despues,
        "antes=$antes despues=$despues");

// --- 7. FK en cascada: borrar capitulo borra paginas ---
$cx->query("DELETE FROM capitulos WHERE id=$cap_id");
$restantes = (int)$cx->query("SELECT COUNT(*) c FROM paginas WHERE capitulo_id=$cap_id")
                      ->fetch_assoc()['c'];
chequeo('ON DELETE CASCADE elimino las paginas huerfanas', $restantes === 0,
        "quedan $restantes");

echo "\n  Resultado: $ok correctas, $fail fallidas\n\n";
exit($fail === 0 ? 0 : 1);
