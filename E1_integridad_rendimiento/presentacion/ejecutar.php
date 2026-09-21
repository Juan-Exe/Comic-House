<?php
/**
 * ejecutar.php - Backend de la presentacion.
 *
 * Ejecuta acciones REALES y devuelve el resultado como JSON.
 *
 * NOTA DE ARQUITECTURA: esta pagina corre DENTRO del contenedor comic_house_web,
 * que no tiene los binarios `docker` ni `git` (esta aislado del Windows
 * anfitrion). Por eso:
 *   - el SQL se ejecuta con mysqli conectando a `db` por red;
 *   - los benchmarks son scripts PHP y se incluyen en este mismo proceso;
 *   - el `git diff` se lee de diff_controlador.txt, generado antes con git.
 *
 * SEGURIDAD: el navegador solo manda una CLAVE. La accion asociada esta
 * fijada aqui en el servidor, asi que la pagina no puede ejecutar nada
 * que no este en esta lista.
 */

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(900);
@ini_set('memory_limit', '512M');
mysqli_report(MYSQLI_REPORT_OFF);   // queremos inspeccionar errores, no abortar

$AQUI  = __DIR__;
$E1    = dirname($AQUI);
$BENCH = $E1 . '/benchmark';

require_once dirname($E1) . '/modelo/conexion.php';   // $conexion

/** Ejecuta SQL y lo devuelve como tabla de texto alineada. */
function tabla_sql(mysqli $cx, string $sql): array {
    $res = $cx->query($sql);
    if ($res === false) {
        return ['texto' => 'ERROR ' . $cx->errno . ': ' . $cx->error, 'error' => true];
    }
    if ($res === true) {
        return ['texto' => 'OK (' . $cx->affected_rows . ' filas afectadas)', 'error' => false];
    }

    $filas = $res->fetch_all(MYSQLI_ASSOC);
    if (!$filas) {
        return ['texto' => '(sin resultados)', 'error' => false];
    }

    $cols = array_keys($filas[0]);
    $ancho = [];
    foreach ($cols as $c) {
        $ancho[$c] = mb_strlen($c);
        foreach ($filas as $f) {
            $ancho[$c] = max($ancho[$c], mb_strlen((string)($f[$c] ?? 'NULL')));
        }
    }

    $linea = function (array $vals) use ($cols, $ancho) {
        $p = [];
        foreach ($cols as $c) {
            $v = (string)($vals[$c] ?? 'NULL');
            $p[] = $v . str_repeat(' ', $ancho[$c] - mb_strlen($v));
        }
        return '  ' . implode('  |  ', $p);
    };

    $sep = '  ' . implode('--+--', array_map(fn($c) => str_repeat('-', $ancho[$c]), $cols));
    $out = [$linea(array_combine($cols, $cols)), $sep];
    foreach ($filas as $f) {
        $out[] = $linea($f);
    }
    $out[] = '';
    $out[] = '  (' . count($filas) . ' filas)';
    return ['texto' => implode("\n", $out), 'error' => false];
}

/** EXPLAIN en formato vertical, como el \G de la consola. */
function explain_vertical(mysqli $cx, string $sql): array {
    $res = $cx->query('EXPLAIN ' . $sql);
    if ($res === false) {
        return ['texto' => 'ERROR: ' . $cx->error, 'error' => true];
    }
    $out = [];
    $n = 0;
    while ($f = $res->fetch_assoc()) {
        $n++;
        $out[] = "*************************** $n. row ***************************";
        foreach ($f as $k => $v) {
            $out[] = sprintf('  %14s: %s', $k, $v ?? 'NULL');
        }
    }
    return ['texto' => implode("\n", $out), 'error' => false];
}

/**
 * Corre un script PHP como SUBPROCESO.
 *
 * No se usa include(): los scripts hacen require_once del mismo
 * conexion.php que esta pagina ya cargo, asi que $conexion quedaria null
 * dentro de ellos. Un proceso aparte los ejecuta tal cual, ademas de
 * aislar sus exit() y su consumo de memoria.
 */
function correr_script(string $ruta, array $args = []): array {
    if (!is_file($ruta)) {
        return ['texto' => 'No se encontró: ' . basename($ruta), 'error' => true];
    }
    $cmd = 'php ' . escapeshellarg($ruta);
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg((string)$a);
    }
    $salida = [];
    $cod = 0;
    exec($cmd . ' 2>&1', $salida, $cod);
    $txt = implode("\n", $salida);
    return ['texto' => $txt !== '' ? $txt : '(sin salida)', 'error' => $cod !== 0];
}

// -------------------------------------------------------------------
// Catalogo de acciones permitidas
// -------------------------------------------------------------------
$ACCIONES = [

    'estado' => [
        'titulo' => 'Estado del entorno',
        'muestra' => 'SELECT VERSION(), conexión a la base',
        'run' => function (mysqli $cx) {
            $v = $cx->query('SELECT VERSION() v')->fetch_assoc()['v'];
            $t = $cx->query('SHOW TABLES')->fetch_all();
            $txt  = "  Servidor de base de datos : MariaDB $v\n";
            $txt .= "  Conexión                  : OK (host \"db\", puerto 3306)\n";
            $txt .= "  Servidor web              : PHP " . PHP_VERSION . " + Apache\n";
            $txt .= "  Tablas en comic_house     : " . count($t) . "\n\n";
            foreach ($t as $fila) { $txt .= "    · " . $fila[0] . "\n"; }
            return ['texto' => $txt, 'error' => false];
        },
    ],

    'conteo' => [
        'titulo' => 'Volumen real del catálogo',
        'muestra' => 'SELECT COUNT(*) FROM comics, capitulos, paginas',
        'run' => fn(mysqli $cx) => tabla_sql($cx,
            'SELECT (SELECT COUNT(*) FROM comics) AS comics,
                    (SELECT COUNT(*) FROM capitulos) AS capitulos,
                    (SELECT COUNT(*) FROM paginas) AS paginas'),
    ],

    'diff' => [
        'titulo' => 'El cambio: antes vs después',
        'muestra' => 'git diff 4d1fee7 1875069 -- controlador/procesar_capitulo.php',
        'tipo' => 'diff',
        'run' => function () use ($AQUI) {
            $f = $AQUI . '/diff_controlador.txt';
            return is_file($f)
                ? ['texto' => file_get_contents($f), 'error' => false]
                : ['texto' => 'Falta diff_controlador.txt. Generar con:'
                            . "\n  git diff 4d1fee7 1875069 -- controlador/procesar_capitulo.php"
                            . "\n    > E1_integridad_rendimiento/presentacion/diff_controlador.txt",
                   'error' => true];
        },
    ],

    'codigo_antes' => [
        'titulo' => 'El cuello de botella original',
        'muestra' => 'procesar_capitulo.ANTES.php · líneas 99-113',
        'tipo' => 'php',
        'run' => function () use ($BENCH) {
            $f = $BENCH . '/antes/procesar_capitulo.ANTES.php';
            if (!is_file($f)) return ['texto' => 'No se encontró el archivo original', 'error' => true];
            $l = file($f);
            $trozo = array_slice($l, 98, 15);
            $out = '';
            foreach ($trozo as $i => $linea) {
                $out .= sprintf("%4d  %s", 99 + $i, $linea);
            }
            return ['texto' => $out, 'error' => false];
        },
    ],

    'restricciones' => [
        'titulo' => 'Índices sobre la tabla paginas',
        'muestra' => "SELECT INDEX_NAME FROM information_schema.STATISTICS",
        'run' => fn(mysqli $cx) => tabla_sql($cx,
            "SELECT DISTINCT INDEX_NAME AS indice, NON_UNIQUE AS no_unico
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA='comic_house' AND TABLE_NAME='paginas'"),
    ],

    'fk' => [
        'titulo' => 'Integridad referencial',
        'muestra' => 'SELECT … FROM information_schema.KEY_COLUMN_USAGE',
        'run' => fn(mysqli $cx) => tabla_sql($cx,
            "SELECT CONSTRAINT_NAME AS restriccion, TABLE_NAME AS tabla,
                    REFERENCED_TABLE_NAME AS referencia
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA='comic_house' AND REFERENCED_TABLE_NAME IS NOT NULL"),
    ],

    'rechazo' => [
        'titulo' => 'La base rechaza datos inválidos',
        'muestra' => "INSERT INTO paginas … VALUES (1, '…', -5, 0)",
        'espera_error' => true,
        'run' => function (mysqli $cx) {
            $sql = "INSERT INTO paginas (capitulo_id, imagen_url, orden, es_doble)
                    VALUES (1, 'prueba/invalida.jpg', -5, 0)";
            $txt = "  Intentando insertar una página con orden = -5 …\n\n";
            try {
                $ok = $cx->query($sql);
            } catch (Throwable $e) {
                $ok = false;
            }
            if ($ok === false) {
                $txt .= "  ✅ LA BASE RECHAZÓ EL REGISTRO\n\n";
                $txt .= "     errno {$cx->errno}: {$cx->error}\n\n";
                if ($cx->errno === 1264) {
                    $txt .= "  Lo detuvo el TIPO de la columna: orden es INT UNSIGNED,\n";
                    $txt .= "  así que un negativo es imposible de representar. La\n";
                    $txt .= "  restricción CHECK (orden > 0) es la segunda barrera.\n\n";
                    $txt .= "  Dos defensas distintas para la misma regla del negocio.";
                } else {
                    $txt .= "  Lo detuvo una restricción declarada en el esquema,\n";
                    $txt .= "  sin depender de que el código PHP valide bien.";
                }
                return ['texto' => $txt, 'error' => true];   // error = exito aqui
            }
            // No deberia pasar; si pasa, limpiamos
            $cx->query("DELETE FROM paginas WHERE imagen_url='prueba/invalida.jpg'");
            $txt .= "  ⚠️ El dato inválido ENTRÓ. Falta la restricción.";
            return ['texto' => $txt, 'error' => false];
        },
    ],

    'explain_antes' => [
        'titulo' => 'Plan de consulta actual',
        'muestra' => 'EXPLAIN SELECT … WHERE capitulo_id=1 ORDER BY orden',
        'run' => fn(mysqli $cx) => explain_vertical($cx,
            'SELECT id, imagen_url, orden, es_doble FROM paginas
             WHERE capitulo_id=1 ORDER BY orden'),
    ],

    'explain_forzado' => [
        'titulo' => 'El mismo plan, forzando el índice',
        'muestra' => 'EXPLAIN … FORCE INDEX (idx_paginas_cap_orden)',
        'run' => fn(mysqli $cx) => explain_vertical($cx,
            'SELECT id, imagen_url, orden FROM paginas
             FORCE INDEX (idx_paginas_cap_orden)
             WHERE capitulo_id=1 ORDER BY orden'),
    ],

    'proporcion' => [
        'titulo' => 'Por qué el motor ignora el índice',
        'muestra' => 'SELECT capitulo_id, COUNT(*), % del total',
        'run' => fn(mysqli $cx) => tabla_sql($cx,
            "SELECT capitulo_id, COUNT(*) AS paginas,
                    CONCAT(ROUND(COUNT(*)*100/(SELECT COUNT(*) FROM paginas),1),' %') AS pct_tabla
             FROM paginas GROUP BY capitulo_id ORDER BY paginas DESC LIMIT 4"),
    ],

    'benchmark' => [
        'titulo' => 'Inserción: antes vs después',
        'muestra' => 'benchmark.php · 220 páginas × 3 repeticiones',
        'lento' => true,
        'run' => fn() => correr_script($BENCH . '/benchmark.php', ['220', '3']),
    ],

    'escalado' => [
        'titulo' => 'Qué pasa si el volumen crece',
        'muestra' => 'escalado.php · hasta 880.000 filas',
        'lento' => true,
        'run' => fn() => correr_script($BENCH . '/escalado.php'),
    ],

    'umbral' => [
        'titulo' => 'Cuándo empieza a servir el índice',
        'muestra' => 'umbral.php',
        'lento' => true,
        'run' => fn() => correr_script($BENCH . '/umbral.php'),
    ],

    'verificar' => [
        'titulo' => 'Batería de integridad (7 pruebas)',
        'muestra' => 'verificar_controlador.php',
        'run' => fn() => correr_script($BENCH . '/verificar_controlador.php'),
    ],

    'consulta1' => [
        'titulo' => 'Obras por editorial',
        'muestra' => 'SELECT editorial, COUNT(*) … GROUP BY',
        // NOTA: en la base real editorial_id esta NULL y la relacion se
        // guarda por NOMBRE de texto. Agrupamos por ese campo, que es el
        // dato que la aplicacion usa hoy. (Ver diapositiva: es justo la
        // deuda tecnica que se propone corregir.)
        'run' => fn(mysqli $cx) => tabla_sql($cx,
            "SELECT editorial, COUNT(*) AS obras,
                    GROUP_CONCAT(clasificacion ORDER BY titulo SEPARATOR ', ') AS clasificaciones
             FROM comics GROUP BY editorial ORDER BY obras DESC, editorial"),
    ],

    'fk_huerfana' => [
        'titulo' => 'La relación que falta',
        'muestra' => 'SELECT titulo, editorial, editorial_id FROM comics',
        'run' => function (mysqli $cx) {
            $r = tabla_sql($cx,
                "SELECT id, titulo, editorial, IFNULL(editorial_id,'NULL') AS editorial_id
                 FROM comics ORDER BY id");
            $r['texto'] .= "\n\n  HALLAZGO: editorial_id está NULL en todas las obras.\n"
                        .  "  La relación comics → editoriales se guarda por NOMBRE de\n"
                        .  "  texto, no por clave foránea. Un cambio de nombre en la\n"
                        .  "  editorial rompería la relación en silencio.";
            return $r;
        },
    ],

    'consulta2' => [
        'titulo' => 'Páginas por capítulo',
        'muestra' => 'SELECT co.titulo, c.numero, COUNT(p.id) …',
        'run' => fn(mysqli $cx) => tabla_sql($cx,
            "SELECT co.titulo AS obra, c.numero, COUNT(p.id) AS paginas
             FROM capitulos c
             JOIN comics co ON co.id = c.comic_id
             LEFT JOIN paginas p ON p.capitulo_id = c.id
             GROUP BY c.id ORDER BY paginas DESC"),
    ],

    'consulta3' => [
        'titulo' => 'Destacados aptos para menores',
        'muestra' => "SELECT … WHERE destacado=1 AND clasificacion <> '+18'",
        'run' => fn(mysqli $cx) => tabla_sql($cx,
            "SELECT titulo, clasificacion, anio, tipo FROM comics
             WHERE destacado = 1 AND clasificacion <> '+18' ORDER BY anio DESC"),
    ],

    'sucio' => [
        'titulo' => 'El dataset distorsionado',
        'muestra' => 'dataset_sucio/defectos_inyectados.csv',
        'tipo' => 'csv',
        'run' => function () use ($E1) {
            $f = $E1 . '/dataset_sucio/defectos_inyectados.csv';
            if (!is_file($f)) return ['texto' => 'No se encontró el CSV', 'error' => true];
            $fh = fopen($f, 'r');
            $out = [];
            $n = -1;
            while (($r = fgetcsv($fh)) !== false) {
                $n++;
                $out[] = sprintf('  %-12s %-20s %-28s %s',
                    $r[0] ?? '', $r[1] ?? '', $r[2] ?? '', $r[3] ?? '');
                if ($n === 0) $out[] = '  ' . str_repeat('-', 78);
            }
            fclose($fh);
            $out[] = '';
            $out[] = "  ($n defectos inyectados)";
            return ['texto' => implode("\n", $out), 'error' => false];
        },
    ],

    'tabla_sucia' => [
        'titulo' => 'El dataset sucio, fila por fila',
        'muestra' => 'dataset_sucio/comics_sucio.csv',
        'run' => function () use ($E1) {
            $f = $E1 . '/dataset_sucio/comics_sucio.csv';
            if (!is_file($f)) return ['texto' => 'No se encontró el CSV', 'error' => true];

            $fh = fopen($f, 'r');
            $cab = fgetcsv($fh);
            $i = array_flip($cab);
            $out = [];
            $out[] = sprintf('  %-4s %-26s %-7s %-6s %-22s %-4s %s',
                'id', 'titulo', 'clasif', 'anio', 'estado_publicacion', 'ed', 'dest');
            $out[] = '  ' . str_repeat('-', 84);

            $CL = ['ATP', '+13', '+16', '+18'];
            $ES = ['En publicacion', 'Publicacion Terminada'];
            $vistos = [];
            $titulos = [];

            while (($r = fgetcsv($fh)) !== false) {
                if (count($r) === 1 && $r[0] === null) continue;
                $id  = $r[$i['id']];
                $tit = trim((string)$r[$i['titulo']]);
                $mal = [];
                if (isset($vistos[$id])) $mal[] = 'PK repetida';
                $vistos[$id] = true;
                $k = mb_strtolower($tit);
                if ($tit !== '' && isset($titulos[$k])) $mal[] = 'título repetido';
                if ($tit !== '') $titulos[$k] = true;
                if ((int)$id <= 0) $mal[] = 'id negativo';
                if ($tit === '') $mal[] = 'sin título';
                $anio = (int)$r[$i['anio']];
                if ($anio < 1900 || $anio > 2026) $mal[] = 'año futuro';
                if (!in_array($r[$i['clasificacion']], $CL, true)) $mal[] = 'clasificación';
                if (!in_array($r[$i['estado_publicacion']], $ES, true)) $mal[] = 'estado';
                if (!in_array($r[$i['editorial_id']], ['1','2','3','4','5'], true)) $mal[] = 'FK';
                if (!in_array($r[$i['destacado']], ['0','1'], true)) $mal[] = 'booleano';

                $out[] = sprintf('  %-4s %-26s %-7s %-6s %-22s %-4s %-4s %s',
                    $id, ($tit === '' ? '(VACÍO)' : mb_substr($tit, 0, 26)),
                    $r[$i['clasificacion']], $r[$i['anio']],
                    mb_substr($r[$i['estado_publicacion']], 0, 22),
                    $r[$i['editorial_id']], $r[$i['destacado']],
                    $mal ? '  ✗ ' . implode(', ', $mal) : '  ✓ correcta');
            }
            fclose($fh);
            $out[] = '';
            $out[] = '  Las filas marcadas ✓ son las que sobreviven a la limpieza.';
            return ['texto' => implode("\n", $out), 'error' => false];
        },
    ],

    'limpio' => [
        'titulo' => 'Resultado de la limpieza',
        'muestra' => 'dataset_limpio/*.csv',
        'run' => function () use ($E1) {
            // OJO: no se cuenta con count(file(...)): la columna descripcion
            // contiene saltos de linea dentro de comillas, asi que una fila
            // CSV puede ocupar varias lineas del archivo. Hay que parsear.
            $leer = function (string $ruta): array {
                $filas = [];
                $fh = fopen($ruta, 'r');
                $cab = fgetcsv($fh);
                while (($f = fgetcsv($fh)) !== false) {
                    if (count($f) === 1 && $f[0] === null) continue;
                    $filas[] = array_combine($cab, array_pad(
                        array_slice($f, 0, count($cab)), count($cab), ''));
                }
                fclose($fh);
                return $filas;
            };

            $vf = $E1 . '/dataset_limpio/comics_limpio.csv';
            $rf = $E1 . '/dataset_limpio/comics_rechazados.csv';
            $sf = $E1 . '/dataset_sucio/comics_sucio.csv';
            if (!is_file($vf)) return ['texto' => 'Falta el dataset limpio', 'error' => true];

            $validas    = $leer($vf);
            $rechazadas = $leer($rf);
            $sucias     = is_file($sf) ? $leer($sf) : [];

            $ini = count($sucias);
            $nv  = count($validas);
            $nr  = count($rechazadas);
            $dup = $ini - $nv - $nr;      // lo que se aparto por unicidad

            $motivos = [
                '2'  => 'año 2099 · fecha futura',
                '9'  => 'estado «En publicacionn» · typo',
                '10' => 'clasificación «18+» · fuera de dominio',
                '11' => 'editorial 999 · no existe',
                '6'  => 'título vacío · NOT NULL',
                '7'  => 'destacado = 5 · no es booleano',
                '-4' => 'id negativo · imposible en INT UNSIGNED',
            ];

            $txt  = "  RECONCILIACIÓN\n\n";
            $txt .= sprintf("    %2d filas en el dataset sucio\n", $ini);
            $txt .= sprintf("   - %2d apartadas por duplicado (PK y título)\n", $dup);
            $txt .= sprintf("   - %2d rechazadas por reglas de dominio\n", $nr);
            $txt .= "     " . str_repeat('-', 34) . "\n";
            $txt .= sprintf("   = %2d obras válidas\n\n", $nv);

            $txt .= "  VÁLIDAS ($nv)\n";
            foreach ($validas as $f) {
                $txt .= sprintf("    id=%-4s %s\n", $f['id'], $f['titulo']);
            }

            $txt .= "\n  RECHAZADAS ($nr) · con su motivo\n";
            foreach ($rechazadas as $f) {
                $t = trim((string)$f['titulo']);
                $txt .= sprintf("    id=%-4s %-22s %s\n", $f['id'],
                        $t === '' ? '(sin título)' : $t,
                        $motivos[$f['id']] ?? '');
            }

            $txt .= "\n  Ninguna se borró: quedan en comics_rechazados.csv\n";
            $txt .= "  para poder auditar qué se descartó y por qué.";
            return ['texto' => $txt, 'error' => false];
        },
    ],
];

// -------------------------------------------------------------------
$clave = $_GET['cmd'] ?? '';

if ($clave === 'lista') {
    $out = [];
    foreach ($ACCIONES as $k => $v) {
        $out[$k] = ['titulo' => $v['titulo'], 'muestra' => $v['muestra'],
                    'lento' => $v['lento'] ?? false];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($ACCIONES[$clave])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción no permitida: ' . $clave]);
    exit;
}

$def = $ACCIONES[$clave];
$t0 = microtime(true);

try {
    $r = ($def['run'])($conexion);
} catch (Throwable $e) {
    $r = ['texto' => 'Excepción: ' . $e->getMessage(), 'error' => true];
}

$ms = round((microtime(true) - $t0) * 1000, 1);

echo json_encode([
    'ok'      => true,
    'clave'   => $clave,
    'titulo'  => $def['titulo'],
    'muestra' => $def['muestra'],
    'tipo'    => $def['tipo'] ?? 'texto',
    'salida'  => $r['texto'],
    'codigo'  => !empty($r['error']) ? 1 : 0,
    'ms'      => $ms,
    'esperaba_error' => $def['espera_error'] ?? false,
], JSON_UNESCAPED_UNICODE);
