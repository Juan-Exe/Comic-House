<?php
/**
 * buscar_tomos_wiki.php - Tomos de una obra, con arco, fecha y rango.
 *
 * EL PROBLEMA QUE RESUELVE
 *
 * Los capitulos no se suben de uno en uno: se suben TOMOS que agrupan
 * un rango de episodios. Cada tomo tiene su arco y su fecha, y todo eso
 * se tecleaba a mano mirando el wiki.
 *
 * En Berserk, por ejemplo:
 *   tomo 1  episodios 1 - 3  Arco del Espadachin Negro   26/11/1990
 *   tomo 2  episodios 4 - 5  Arco del Espadachin Negro   26/02/1991
 *   tomo 3  episodios 6 - 9  Espadachin Negro + Edad de Oro  25/10/1991
 *
 * DE DONDE SALE
 *
 * De la pagina de lanzamientos del wiki, que usa la plantilla
 * {{volinfo}} con los campos en texto plano:
 *
 *   {{volinfo
 *   | volume   = 3
 *   | arcs     = [[Black Swordsman Arc]], [[Golden Age Arc]]
 *   | ja pub   = 25 October 1991
 *   | episodes = * 0-06. {{e|0-6}} ...
 *   }}
 *
 * Comprobado: devuelve los 43 tomos de Berserk con sus rangos y fechas,
 * y coincide con los tres capitulos ya cargados en el catalogo.
 *
 * ES UNA SUGERENCIA. Los wikis los escribe la comunidad: pueden tener
 * errores, o numerar distinto a la edicion que se esta subiendo. El
 * administrador revisa y confirma; aqui no se guarda nada.
 */

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(30);

const CACHE_DIAS_TOMOS = 7;

function responder(array $r): void {
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit();
}

function pedirWiki(string $wiki, array $params): ?array {
    $url = "https://{$wiki}.fandom.com/api.php?" . http_build_query($params + ['format' => 'json']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'ComicHouse/1.0 (proyecto academico)',
    ]);
    $cuerpo = curl_exec($ch);
    $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($cuerpo === false || $http !== 200) {
        return null;
    }
    $j = json_decode($cuerpo, true);
    return is_array($j) ? $j : null;
}

/** Nombres de wiki probables para una obra. */
function wikisDe(string $titulo): array {
    $base = mb_strtolower(trim($titulo));
    $base = strtr($base, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base), '-');

    $lista = [$base];
    if (str_contains($titulo, ':')) {
        $corto = mb_strtolower(trim(explode(':', $titulo)[0]));
        $lista[] = trim(preg_replace('/[^a-z0-9]+/', '-', $corto), '-');
    }
    $lista[] = str_replace('-', '', $base);
    return array_values(array_unique(array_filter($lista)));
}

/** Texto de una pagina, o '' si no existe. */
function wikitexto(string $wiki, string $pagina): string {
    $j = pedirWiki($wiki, [
        'action' => 'query', 'prop' => 'revisions', 'rvprop' => 'content',
        'rvslots' => 'main', 'titles' => $pagina, 'redirects' => 1,
    ]);
    $pgs = $j['query']['pages'] ?? [];
    $pg  = reset($pgs);
    return $pg['revisions'][0]['slots']['main']['*'] ?? '';
}

/** Un campo de dentro de un bloque de plantilla. */
function campoPlantilla(string $bloque, string $nombre): string {
    if (preg_match('/\|\s*' . preg_quote($nombre, '/') . '\s*=\s*([^\n|]*)/i', $bloque, $m)) {
        return trim($m[1]);
    }
    return '';
}

/** Quita el marcado de wiki: [[Enlace|texto]] -> texto. */
function limpiarWiki(string $t): string {
    $t = preg_replace('/\[\[[^\]|]*\|([^\]]*)\]\]/', '$1', $t);
    $t = preg_replace('/\[\[([^\]]*)\]\]/', '$1', $t);
    $t = preg_replace('/\{\{[^}]*\}\}/', '', $t);
    $t = preg_replace("/'{2,}/", '', $t);
    return trim($t);
}

/**
 * «26 November 1990» -> «1990-11-26».
 * Devuelve '' si no se reconoce: mejor sin fecha que con una inventada.
 */
function aFechaISO(string $txt): string {
    $txt = limpiarWiki($txt);
    if ($txt === '') {
        return '';
    }
    $d = DateTime::createFromFormat('!j F Y', $txt)
      ?: DateTime::createFromFormat('!F j, Y', $txt)
      ?: DateTime::createFromFormat('!Y-m-d', $txt);
    if (!$d) {
        return '';
    }
    $anio = (int) $d->format('Y');
    return ($anio >= 1900 && $anio <= (int) date('Y') + 1) ? $d->format('Y-m-d') : '';
}

/**
 * Numero de episodio, teniendo en cuenta que la numeracion puede
 * REINICIARSE dentro de la misma obra.
 *
 * Berserk marca con «0-» los episodios del prologo: 0-1 ... 0-16, y
 * despues vuelve a empezar en 1. El tomo 5 contiene «0-15, 0-16, 1, 2,
 * 3...», asi que mezclar ambas series daba el rango absurdo «1 - 16».
 *
 * Se devuelve el numero junto con su serie, para no compararlos entre si.
 *
 * @return array{serie: string, n: int}|null
 */
function episodioConSerie(string $cru): ?array {
    $cru = trim($cru);
    if ($cru === '') {
        return null;
    }
    // «0-15» -> serie «0», numero 15.  «12» -> serie '', numero 12.
    if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $cru, $m)) {
        return ['serie' => $m[1], 'n' => (int) $m[2]];
    }
    if (preg_match('/^(\d+)$/', $cru, $m)) {
        return ['serie' => '', 'n' => (int) $m[1]];
    }
    return null;
}

/**
 * Lee los bloques {{volinfo}} de la pagina de lanzamientos.
 * Es el formato que usan los wikis de manga para listar sus tomos.
 */
function tomosDeVolinfo(string $texto): array {
    if (!preg_match_all('/\{\{\s*volinfo(.*?)\n\}\}/is', $texto, $m)) {
        return [];
    }

    $tomos = [];
    foreach ($m[1] as $bloque) {
        $num = (int) campoPlantilla($bloque, 'volume');
        if ($num <= 0) {
            continue;
        }

        // Los episodios aparecen como {{e|0-1}}, {{e|12}}...
        preg_match_all('/\{\{\s*e\s*\|\s*([0-9\-]+)\s*\}\}/i', $bloque, $eps);

        // Se agrupan por serie: un tomo que cierra el prologo y abre la
        // numeracion normal tiene episodios de dos series distintas.
        $porSerie = [];
        foreach ($eps[1] ?? [] as $cru) {
            $e = episodioConSerie($cru);
            if ($e) {
                $porSerie[$e['serie']][] = $e['n'];
            }
        }
        if (!$porSerie) {
            continue;
        }

        // Se queda la serie con mas episodios en este tomo: es la que
        // de verdad lo representa. En el tomo 5 de Berserk son los
        // catorce episodios de la numeracion nueva, no los dos del
        // prologo que lo cierran.
        uasort($porSerie, fn($a, $b) => count($b) <=> count($a));
        $numeros = reset($porSerie);

        // La fecha japonesa es la de publicacion original.
        $fecha = aFechaISO(campoPlantilla($bloque, 'ja pub'));
        if ($fecha === '') {
            $fecha = aFechaISO(campoPlantilla($bloque, 'en pub'));
        }

        $tomos[] = [
            'tomo'   => $num,
            'desde'  => min($numeros),
            'hasta'  => max($numeros),
            'arco'   => limpiarWiki(campoPlantilla($bloque, 'arcs')),
            'fecha'  => $fecha,
            'total'  => count($numeros),
        ];
    }

    usort($tomos, fn($a, $b) => $a['tomo'] <=> $b['tomo']);
    return $tomos;
}

/**
 * Traduce los nombres de arco al español con el modelo.
 *
 * Los wikis los publican en inglés («Golden Age Arc») y el catálogo
 * está en español («Arco de la Edad de Oro»). Se traducen TODOS en una
 * sola llamada: son pocos y repetidos, y hacer una por tomo gastaría
 * cuarenta peticiones para cuatro arcos distintos.
 *
 * Si no hay clave o el modelo falla, se devuelven en inglés sin más: es
 * una mejora, no un requisito.
 */
function traducirArcos(array $arcos): array {
    $clave = getenv('GROQ_API_KEY') ?: '';
    if ($clave === '' || !$arcos) {
        return [];
    }

    $lista = implode("\n", $arcos);
    $sistema = 'Traduces nombres de arcos argumentales de manga y cómic al '
        . 'español de España. Recibes una lista, uno por línea. Respondes '
        . 'SOLO un JSON: {"original 1":"traducción 1","original 2":"..."}. '
        . 'Usa la forma «Arco de…» o «Arco del…» según corresponda. '
        . 'Ejemplos: "Golden Age Arc" → "Arco de la Edad de Oro"; '
        . '"Black Swordsman Arc" → "Arco del Espadachín Negro". '
        . 'Conserva los nombres propios que no se traduzcan.';

    foreach (['qwen/qwen3.8-27b', 'openai/gpt-oss-120b'] as $modelo) {
        $payload = json_encode([
            'model'       => $modelo,
            'temperature' => 0,
            'max_tokens'  => 700,
            'messages'    => [
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user',   'content' => $lista],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $clave,
            ],
        ]);
        $cuerpo = curl_exec($ch);
        $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($cuerpo === false || $http !== 200) {
            continue;
        }
        $j = json_decode($cuerpo, true);
        $txt = trim($j['choices'][0]['message']['content'] ?? '');
        if (preg_match('/\{.*\}/s', $txt, $m)) {
            $txt = $m[0];
        }
        $mapa = json_decode($txt, true);
        if (is_array($mapa) && $mapa) {
            return $mapa;
        }
    }
    return [];
}

// El .env no se carga solo: aquí no hace falta la base de datos.
(function () {
    $f = dirname(__DIR__) . '/.env';
    if (!is_readable($f)) {
        return;
    }
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $l, 2);
        $k = trim($k);
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=" . trim($v));
        }
    }
})();

// =================================================================
$titulo = trim($_GET['titulo'] ?? $_POST['titulo'] ?? '');
if (mb_strlen($titulo) < 2) {
    responder(['ok' => false, 'aviso' => 'Falta el título de la obra']);
}

// Cache: recorrer el wiki cuesta varias peticiones y la lista de tomos
// de una obra no cambia de un dia para otro.
$dirCache = dirname(__DIR__) . '/cache/wiki';
$archivo  = $dirCache . '/tomos_' . preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($titulo)) . '.json';

if (is_readable($archivo) && (time() - filemtime($archivo)) < CACHE_DIAS_TOMOS * 86400) {
    $guardado = json_decode(file_get_contents($archivo), true);
    if (is_array($guardado) && !empty($guardado['datos']['tomos'])) {
        $guardado['cacheado'] = true;
        responder($guardado);
    }
}

$tomos  = [];
$fuente = '';

foreach (wikisDe($titulo) as $wiki) {
    // Las paginas de lanzamientos se llaman de varias formas segun el wiki.
    foreach (['Releases (Manga)', 'Releases', 'Volumes',
              "List of $titulo volumes", 'Manga'] as $pagina) {
        $txt = wikitexto($wiki, $pagina);
        if (mb_strlen($txt) < 300) {
            continue;
        }
        $tomos = tomosDeVolinfo($txt);
        if (count($tomos) >= 2) {
            $fuente = "$wiki.fandom.com · $pagina";
            break 2;
        }
    }
}

if (!$tomos) {
    responder([
        'ok' => false,
        'aviso' => 'No se encontró la lista de tomos de esta obra en Fandom. '
                 . 'Rellena los datos a mano.',
    ]);
}

// Los arcos vienen en inglés. Se traducen los DISTINTOS en una sola
// llamada: Berserk tiene 43 tomos pero solo 5 arcos.
$distintos = [];
foreach ($tomos as $t) {
    foreach (explode(',', $t['arco']) as $a) {
        $a = trim($a);
        if ($a !== '' && !in_array($a, $distintos, true)) {
            $distintos[] = $a;
        }
    }
}

$traduccion = traducirArcos($distintos);

if ($traduccion) {
    foreach ($tomos as &$t) {
        $partes = [];
        foreach (explode(',', $t['arco']) as $a) {
            $a = trim($a);
            if ($a === '') {
                continue;
            }
            $partes[] = $traduccion[$a] ?? $a;
        }
        $t['arco'] = implode(', ', $partes);
    }
    unset($t);
}

$respuesta = [
    'ok'    => true,
    'datos' => [
        'tomos'  => $tomos,
        'fuente' => $fuente,
        'total'  => count($tomos),
    ],
    'aviso' => 'Tomos sugeridos por ' . $fuente . '. Los escribe la comunidad '
             . 'del wiki: revísalos antes de guardar.',
];

if (!is_dir($dirCache)) {
    @mkdir($dirCache, 0775, true);
}
@file_put_contents($archivo, json_encode($respuesta, JSON_UNESCAPED_UNICODE));

responder($respuesta);
