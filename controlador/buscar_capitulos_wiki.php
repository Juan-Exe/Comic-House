<?php
/**
 * buscar_capitulos_wiki.php - Titulos de capitulos desde Fandom.
 *
 * LO QUE SE COMPROBO ANTES DE ESCRIBIR ESTO
 *
 * Fandom expone la API de MediaWiki sin clave. Pero cada wiki la
 * mantiene su propia comunidad y organiza las cosas a su manera: no
 * existe un esquema comun.
 *
 *   watchmen.fandom.com        Category:Issues   -> 0 miembros
 *   kimetsu-no-yaiba.fandom    Category:Chapters -> 12 miembros
 *
 * Por eso NO se busca por categoria. Se prueban varias estrategias en
 * orden y se acepta la primera que devuelva algo coherente:
 *
 *   1. Pagina indice     «Chapters in <obra>» con secciones por capitulo
 *   2. Categoria         Category:Chapters / Issues / Volumes
 *
 * En Watchmen la primera encuentra los 12 titulos reales («Absent
 * Friends», «Fearful Symmetry»...). En Demon Slayer funciona la segunda.
 *
 * LO QUE ESTO ES: una SUGERENCIA. Los wikis los escribe cualquiera y
 * pueden tener errores, numeraciones distintas a la edicion que se esta
 * subiendo, o titulos en otro idioma. El administrador revisa y
 * confirma: nunca se guarda nada desde aqui.
 */

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(30);

function responder(array $r): void {
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit();
}

/** Peticion breve a un wiki. Devuelve null si no responde bien. */
function pedir(string $wiki, array $params): ?array {
    $url = "https://{$wiki}.fandom.com/api.php?" . http_build_query($params + [
        'format' => 'json',
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
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

/** «XII» -> 12. Los wikis numeran los capitulos en romano a menudo. */
function deRomano(string $r): int {
    $v = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100,
          'D' => 500, 'M' => 1000];
    $r = strtoupper($r);
    $n = 0;
    $len = strlen($r);
    for ($i = 0; $i < $len; $i++) {
        if (!isset($v[$r[$i]])) {
            return 0;
        }
        $act = $v[$r[$i]];
        $sig = ($i + 1 < $len && isset($v[$r[$i + 1]])) ? $v[$r[$i + 1]] : 0;
        $n += ($sig > $act) ? -$act : $act;
    }
    return $n;
}

/**
 * Nombre del wiki a partir del titulo de la obra.
 * «Demon Slayer: Kimetsu no Yaiba» -> «demon-slayer-kimetsu-no-yaiba»
 */
function candidatosWiki(string $titulo): array {
    $base = mb_strtolower(trim($titulo));
    $base = strtr($base, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
    $base = preg_replace('/[^a-z0-9]+/', '-', $base);
    $base = trim($base, '-');

    $lista = [$base];

    // Sin la parte tras los dos puntos: «demon-slayer» ademas del largo.
    if (str_contains($titulo, ':')) {
        $corto = mb_strtolower(trim(explode(':', $titulo)[0]));
        $corto = preg_replace('/[^a-z0-9]+/', '-', $corto);
        $lista[] = trim($corto, '-');
    }
    // Sin guiones: muchos wikis son «onepiece», no «one-piece».
    $lista[] = str_replace('-', '', $base);

    return array_values(array_unique(array_filter($lista)));
}

/**
 * ESTRATEGIA 1 · pagina indice.
 * Busca «Chapters in <obra>» y lee sus secciones:
 *   ==[[Chapter I: At Midnight, All the Agents...]]==
 */
function porIndice(string $wiki, string $titulo): array {
    foreach (["Chapters in $titulo", "List of chapters", "Chapters"] as $pagina) {
        $j = pedir($wiki, [
            'action' => 'query', 'prop' => 'revisions',
            'rvprop' => 'content', 'rvslots' => 'main',
            'titles' => $pagina, 'redirects' => 1,
        ]);
        $pgs = $j['query']['pages'] ?? [];
        $pg  = reset($pgs);
        $txt = $pg['revisions'][0]['slots']['main']['*'] ?? '';
        if (mb_strlen($txt) < 300) {
            continue;
        }

        $caps = [];

        // «==[[Chapter I: Titulo]]==» o «==[[Chapter 1: Titulo]]==»
        if (preg_match_all(
            '/==\s*\[\[\s*(?:Chapter|Issue)\s+([IVXLCDM]+|\d{1,4})\s*:\s*([^\]|]+?)\s*(?:\|[^\]]*)?\]\]\s*==/iu',
            $txt, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $n = ctype_digit($x[1]) ? (int) $x[1] : deRomano($x[1]);
                if ($n > 0 && $n <= 9999) {
                    $caps[$n] = trim($x[2]);
                }
            }
        }

        if (count($caps) >= 2) {
            ksort($caps);
            return $caps;
        }
    }
    return [];
}

/**
 * ESTRATEGIA 2 · categoria.
 * Los wikis de manga suelen tener Category:Chapters con paginas
 * «Chapter 1», «Chapter 2»... El titulo real esta en la propia pagina,
 * asi que aqui solo se obtiene la numeracion y el nombre que usa el wiki.
 */
function porCategoria(string $wiki): array {
    // Los nombres cambian de un wiki a otro: Berserk llama «Episode» a
    // lo que Watchmen llama «Chapter», y reparte sus episodios en
    // categorias por arco en vez de una sola lista.
    $categorias = ['Chapters', 'Issues', 'Episodes', 'Volumes'];
    $pendientes = $categorias;
    $vistas     = [];
    $caps       = [];

    while ($pendientes) {
        $cat = array_shift($pendientes);
        if (isset($vistas[$cat])) {
            continue;
        }
        $vistas[$cat] = true;

        $j = pedir($wiki, [
            'action' => 'query', 'list' => 'categorymembers',
            'cmtitle' => "Category:$cat", 'cmlimit' => 500,
        ]);
        $miembros = $j['query']['categorymembers'] ?? [];

        foreach ($miembros as $m) {
            $t = $m['title'] ?? '';

            // Una subcategoria («Category:Golden Age Arc Episodes») se
            // visita tambien: ahi es donde estan los episodios.
            if (str_starts_with($t, 'Category:')) {
                $sub = substr($t, 9);
                // Como mucho 6 categorias por obra: Berserk reparte sus
                // episodios en 5 arcos y recorrerlas todas costaba 18 s.
                if (!isset($vistas[$sub]) && count($vistas) < 6) {
                    $pendientes[] = $sub;
                }
                continue;
            }

            // «Chapter 12», «Chapter 12: Titulo», «Episode 94 (Manga)»
            if (preg_match(
                '/^(?:Chapter|Issue|Episode)\s+(\d{1,4})(?:\s*:\s*([^(]+))?(?:\s*\([^)]*\))?$/iu',
                $t, $x)) {
                $n = (int) $x[1];
                if ($n > 0) {
                    $caps[$n] = trim($x[2] ?? '');
                }
            }
        }

        // Con una lista razonable ya se para: recorrer doce categorias
        // por obra seria lento y no anade mucho.
        if (count($caps) >= 2 && !$pendientes) {
            break;
        }
    }

    if (count($caps) >= 2) {
        ksort($caps);
        return $caps;
    }
    return [];
}

// =================================================================
$titulo = trim($_GET['titulo'] ?? $_POST['titulo'] ?? '');
if (mb_strlen($titulo) < 2) {
    responder(['ok' => false, 'aviso' => 'Falta el título de la obra']);
}

// CACHE EN DISCO.
//
// Una obra con los episodios repartidos en categorias por arco (Berserk)
// obliga a varias peticiones encadenadas y tarda ~17 s. La lista de
// capitulos de una obra terminada no cambia de un dia para otro, asi
// que se guarda y la segunda consulta es instantanea.
$dirCache = dirname(__DIR__) . '/cache/wiki';
$clave    = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($titulo));
$archivo  = "$dirCache/$clave.json";
const CACHE_DIAS = 7;

if (is_readable($archivo) && (time() - filemtime($archivo)) < CACHE_DIAS * 86400) {
    $guardado = json_decode(file_get_contents($archivo), true);
    if (is_array($guardado) && !empty($guardado['datos']['capitulos'])) {
        $guardado['cacheado'] = true;
        responder($guardado);
    }
}

$caps  = [];
$fuente = '';

foreach (candidatosWiki($titulo) as $wiki) {
    $caps = porIndice($wiki, $titulo);
    if ($caps) {
        $fuente = "$wiki.fandom.com";
        break;
    }
    $caps = porCategoria($wiki);
    if ($caps) {
        $fuente = "$wiki.fandom.com";
        break;
    }
}

if (!$caps) {
    responder([
        'ok' => false,
        'aviso' => 'No se encontró una lista de capítulos para esta obra. '
                 . 'Escribe los títulos a mano.',
    ]);
}

// Una lista de numeros SIN titulos no sirve para nada: el formulario ya
// sabe contar. Berserk devolvia 386 episodios y ni uno con nombre,
// porque ese wiki guarda el titulo dentro de cada pagina y no en el
// nombre de la categoria. Se descarta antes de responder.
$conTitulo = 0;
foreach ($caps as $t) {
    if (trim($t) !== '') {
        $conTitulo++;
    }
}
if ($conTitulo === 0) {
    responder([
        'ok' => false,
        'aviso' => 'El wiki de esta obra no publica los títulos de los capítulos. '
                 . 'Escríbelos a mano.',
    ]);
}

// Se devuelve como lista ordenada, no como mapa: el orden importa.
// Solo los que tienen titulo: los demas no aportan nada.
$salida = [];
foreach ($caps as $n => $t) {
    if (trim($t) !== '') {
        $salida[] = ['numero' => $n, 'titulo' => trim($t)];
    }
}

$respuesta = [
    'ok'    => true,
    'datos' => [
        'capitulos' => $salida,
        'fuente'    => $fuente,
        'total'     => count($salida),
    ],
    'aviso' => 'Títulos sugeridos por ' . $fuente . '. Los escribe la comunidad '
             . 'del wiki: revísalos antes de guardar.',
];

// Se guarda para la proxima vez. Si no se puede escribir, da igual:
// el resultado ya esta listo y solo se pierde la ventaja de la cache.
if (!is_dir($dirCache)) {
    @mkdir($dirCache, 0775, true);
}
@file_put_contents($archivo, json_encode($respuesta, JSON_UNESCAPED_UNICODE));

responder($respuesta);
