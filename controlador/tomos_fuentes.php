<?php
/** Fuentes y parsers de entregas. No escribe en la base de datos. */
namespace ComicHouse\Tomos;

use DateTime;

/** Comparación de identidad, sin guiones, espacios ni acentos. */
function normalizarObra(string $titulo): string {
    $titulo = strtr(mb_strtolower(trim($titulo)), [
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n',
    ]);
    return preg_replace('/[^a-z0-9]+/', '', $titulo);
}

/** Cambiar la versión evita reutilizar resultados del parser anterior. */
function claveCache(array $obra): string {
    return 'tomos_v7_' . hash('sha256', json_encode($obra, JSON_UNESCAPED_UNICODE));
}

/** Nunca compara números de ediciones distintas. La persona puede elegir una. */
function elegirEdicion(array $ediciones, int $anio, string $editorial, string $elegida = ''): ?array {
    if ($elegida !== '') {
        foreach ($ediciones as $edicion) {
            if ($edicion['id'] === $elegida) return $edicion;
        }
        return null;
    }
    $candidatas = $ediciones;
    if ($anio > 0) {
        $exactas = array_values(array_filter($candidatas, fn($e) => $e['anio'] === $anio));
        $candidatas = $exactas ?: array_values(array_filter($candidatas,
            fn($e) => $e['anio'] > 0 && abs($e['anio'] - $anio) === 1));
    }
    if (count($candidatas) > 1 && $editorial !== '') {
        $misma = array_values(array_filter($candidatas, fn($e) =>
            normalizarObra($e['editorial'] ?? '') === normalizarObra($editorial)));
        if ($misma) $candidatas = $misma;
    }
    return count($candidatas) === 1 ? $candidatas[0] : null;
}

/** Identidad de serie y número se conservan por separado desde la búsqueda. */
function nombreDeSerie(string $pagina, string $titulo): ?array {
    if (!preg_match('/^(.+?)\s+Vol\s+(\d+)(?:\s+(\d{1,4}))?$/i', $pagina, $m)
        || normalizarObra($m[1]) !== normalizarObra($titulo)) return null;
    return ['serie' => $m[1] . ' Vol ' . $m[2], 'numero' => isset($m[3]) ? (int) $m[3] : null];
}

/** Fecha real si existe el día; mes/año se conserva aparte sin inventar el día 1. */
function fechaDeNumero(string $texto): array {
    foreach (['ReleaseDate', 'OnSaleDate', 'release date'] as $campo) {
        $fecha = aFechaISO(campoPlantilla($texto, $campo));
        if ($fecha !== '') return ['fecha' => $fecha, 'fecha_mes' => substr($fecha, 0, 7)];
    }
    $anio = campoPlantilla($texto, 'Year');
    $mes = campoPlantilla($texto, 'Month');
    $numeroMes = ctype_digit($mes) ? (int) $mes : 0;
    if (!$numeroMes) {
        $meses = ['january','february','march','april','may','june','july','august',
                  'september','october','november','december'];
        foreach ($meses as $i => $nombre) {
            if ($mes !== '' && in_array(strtolower($mes), [$nombre, substr($nombre, 0, 3)], true)) {
                $numeroMes = $i + 1;
                break;
            }
        }
    }
    $valido = preg_match('/^\d{4}$/', $anio) && (int) $anio >= 1900
        && (int) $anio <= (int) date('Y') + 1 && $numeroMes >= 1 && $numeroMes <= 12;
    $mensual = $valido ? sprintf('%04d-%02d', $anio, $numeroMes) : '';
    $dia = campoPlantilla($texto, 'Day');
    $completa = $valido && ctype_digit($dia) && checkdate($numeroMes, (int) $dia, (int) $anio)
        ? sprintf('%s-%02d', $mensual, (int) $dia) : '';
    return ['fecha' => $completa, 'fecha_mes' => $mensual];
}

/** Lee muchas fichas en una petición, manteniendo su título como clave. */
function fichasWiki(string $wiki, array $titulos): array {
    $salida = [];
    foreach (array_chunk(array_values(array_unique($titulos)), 40) as $lote) {
        $j = pedirWiki($wiki, ['action'=>'query', 'prop'=>'revisions', 'rvprop'=>'content',
            'rvslots'=>'main', 'titles'=>implode('|', $lote)]);
        foreach ($j['query']['pages'] ?? [] as $p) {
            $txt = $p['revisions'][0]['slots']['main']['*'] ?? '';
            if ($txt !== '') $salida[$p['title']] = $txt;
        }
    }
    return $salida;
}

/** Descubre series, no una mezcla de números con el mismo título. */
function edicionesWiki(string $wiki, string $titulo): array {
    $j = pedirWiki($wiki, ['action'=>'query', 'list'=>'prefixsearch',
        'pssearch'=>"$titulo Vol", 'pslimit'=>50]);
    $series = [];
    foreach ($j['query']['prefixsearch'] ?? [] as $p) {
        $n = nombreDeSerie($p['title'] ?? '', $titulo);
        if ($n) $series[$n['serie']] = true;
    }
    if (!$series) return [];
    $titulos = [];
    foreach (array_slice(array_keys($series), 0, 10) as $serie) {
        $titulos[] = $serie;
        $titulos[] = "$serie 1";
    }
    $fichas = fichasWiki($wiki, $titulos);
    $ediciones = [];
    foreach (array_slice(array_keys($series), 0, 10) as $serie) {
        if (!isset($fichas["$serie 1"])) continue;
        $fecha = fechaDeNumero($fichas["$serie 1"]);
        $anio = (int) substr($fecha['fecha'] ?: $fecha['fecha_mes'], 0, 4);
        $editorial = limpiarWiki(campoPlantilla($fichas[$serie] ?? '', 'Publisher'));
        $ediciones[] = ['id'=>"wiki:$wiki:$serie", 'serie'=>$serie, 'anio'=>$anio,
            'editorial'=>$editorial, 'fuente'=>"$wiki.fandom.com", 'wiki'=>$wiki];
    }
    return $ediciones;
}

/** Enumera todos los números de UNA serie; allpages permite continuar sin perderlos. */
function numerosDeEdicion(array $edicion): array {
    $serie = $edicion['serie'];
    $wiki = $edicion['wiki'];
    $titulos = [];
    $continuar = [];
    do {
        $j = pedirWiki($wiki, $continuar + ['action'=>'query', 'list'=>'allpages',
            'apprefix'=>"$serie ", 'apnamespace'=>0, 'aplimit'=>500]);
        if ($j === null) return []; // no publicar una lista a medio consultar
        foreach ($j['query']['allpages'] ?? [] as $p) {
            if (preg_match('/^' . preg_quote($serie, '/') . ' (\d{1,4})$/', $p['title'], $m)
                && (int) $m[1] >= 1) $titulos[(int) $m[1]] = $p['title'];
        }
        $continuar = $j['continue'] ?? [];
    } while ($continuar && count($titulos) < 9999);
    if (!$titulos) return [];
    ksort($titulos);
    $fichas = fichasWiki($wiki, array_values($titulos));
    $salida = [];
    foreach ($titulos as $n => $pagina) {
        if (!isset($fichas[$pagina])) return [];
        $texto = $fichas[$pagina];
        $fecha = fechaDeNumero($texto);
        $titulo = limpiarWiki(campoPlantilla($texto, 'StoryTitle1'));
        $salida[] = ['tomo'=>$n, 'desde'=>$n, 'hasta'=>$n, 'total'=>1,
            'formato'=>'grapa', 'titulo'=>$titulo, 'arco'=>'',
            'url'=>'https://' . $wiki . '.fandom.com/wiki/' . rawurlencode(str_replace(' ', '_', $pagina))]
            + $fecha;
    }
    return $salida;
}

/** ComicVine es respaldo cuando el wiki no publica una lista aprovechable. */
function pedirComicVine(string $recurso, array $parametros): ?array {
    $clave = getenv('COMICVINE_API_KEY') ?: '';
    if ($clave === '') return null;
    $url = 'https://comicvine.gamespot.com/api/' . $recurso . '/?' . http_build_query(
        $parametros + ['api_key'=>$clave, 'format'=>'json']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
        CURLOPT_USERAGENT=>'ComicHouse/1.0 (proyecto academico)']);
    $cuerpo = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = $cuerpo !== false && $http === 200 ? json_decode($cuerpo, true) : null;
    return is_array($j) && ($j['status_code'] ?? 0) == 1 ? $j : null;
}

function edicionesComicVine(string $titulo): array {
    $j = pedirComicVine('search', ['query'=>$titulo, 'resources'=>'volume', 'limit'=>30,
        'field_list'=>'id,name,start_year,publisher,count_of_issues']);
    $salida = [];
    foreach ($j['results'] ?? [] as $v) {
        if (normalizarObra($v['name'] ?? '') !== normalizarObra($titulo)) continue;
        $salida[] = ['id'=>'cv:' . (int) $v['id'], 'serie'=>$v['name'],
            'anio'=>(int) $v['start_year'], 'editorial'=>$v['publisher']['name'] ?? '',
            'fuente'=>'ComicVine', 'volume_id'=>(int) $v['id'], 'total'=>(int) $v['count_of_issues']];
    }
    return $salida;
}

function numerosComicVine(array $edicion, string $estado): array {
    $salida = [];
    $offset = 0;
    do {
        $j = pedirComicVine('issues', ['filter'=>'volume:' . $edicion['volume_id'],
            'field_list'=>'issue_number,name,cover_date,store_date,site_detail_url',
            'limit'=>100, 'offset'=>$offset, 'sort'=>'issue_number:asc']);
        if ($j === null) return [];
        foreach ($j['results'] ?? [] as $numero) {
            $cru = (string) ($numero['issue_number'] ?? '');
            // La base actual solo admite enteros positivos: no renumerar #0 o #1/2.
            if (!preg_match('/^[1-9]\d{0,3}$/', $cru)) continue;
            $n = (int) $cru;
            $fecha = aFechaISO($numero['store_date'] ?? '');
            $mes = $numero['cover_date'] ?? '';
            $mes = preg_match('/^\d{4}-\d{2}-\d{2}$/', $mes) ? substr($mes, 0, 7) : '';
            $salida[$n] = ['tomo'=>$n, 'desde'=>$n, 'hasta'=>$n, 'total'=>1,
                'formato'=>$estado === 'Tomo unico' && $edicion['total'] === 1 ? 'tomo' : 'grapa',
                'titulo'=>trim($numero['name'] ?? ''), 'arco'=>'',
                'fecha'=>$fecha, 'fecha_mes'=>$mes, 'url'=>$numero['site_detail_url'] ?? ''];
        }
        $leidos = count($j['results'] ?? []);
        $offset += $leidos;
    } while ($leidos > 0 && $offset < min(10000, (int) ($j['number_of_total_results'] ?? 0)));
    ksort($salida);
    return array_values($salida);
}

/** Libros de una sola entrega: conserva la fecha de la edición del catálogo. */
function tomoUnicoWiki(string $wiki, string $titulo, int $anio): array {
    $texto = wikitexto($wiki, $titulo);
    if (!preg_match('/\{\{\s*Infobox Book\b/i', $texto)) return [];
    $fechas = [];
    preg_match_all('/(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4}/i',
        campoPlantilla($texto, 'release_year'), $m);
    foreach ($m[0] as $cru) {
        $fecha = aFechaISO($cru);
        if ($fecha !== '') $fechas[] = $fecha;
    }
    $coinciden = array_values(array_filter($fechas, fn($f) => (int) substr($f, 0, 4) === $anio));
    $fecha = count($coinciden) === 1 ? $coinciden[0] : (count($fechas) === 1 ? $fechas[0] : '');
    return [['tomo'=>1, 'desde'=>1, 'hasta'=>1, 'total'=>1, 'formato'=>'tomo',
        'titulo'=>limpiarWiki(campoPlantilla($texto, 'title')), 'arco'=>'',
        'fecha'=>$fecha, 'fecha_mes'=>$fecha !== '' ? substr($fecha, 0, 7) : '',
        'url'=>'https://' . $wiki . '.fandom.com/wiki/' . rawurlencode(str_replace(' ', '_', $titulo))]];
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

/**
 * Nombres de wiki probables para una obra.
 *
 * Deducirlo del titulo NO basta, y en algunos casos da el wiki
 * equivocado: «saga.fandom.com» es el del videojuego SaGa y
 * «bloodborne.fandom.com» el del juego, no los comics.
 *
 * Por eso se anaden los wikis de EDITORIAL, que recogen el catalogo
 * entero de cada sello. Comprobado que existen y tienen las fichas.
 */
function wikisDe(string $titulo, string $editorial = ''): array {
    $norm = function (string $s): string {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
        return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    };

    $base  = $norm($titulo);
    $lista = [$base];

    if (str_contains($titulo, ':')) {
        $lista[] = $norm(explode(':', $titulo)[0]);
    }
    $lista[] = str_replace('-', '', $base);
    $sinAnio = preg_replace('/-\d+$/', '', $base);
    if ($sinAnio !== $base) $lista[] = $sinAnio;

    // Wikis de editorial, por si el de la obra no existe o es de otra
    // cosa con el mismo nombre.
    $porEditorial = [
        'image'  => 'imagecomics',
        'dc'     => 'dc',
        'marvel' => 'marvel',
        'dark horse' => 'darkhorse',
        'norma'  => 'imagecomics',   // suele editar en español lo de Image
    ];
    $ed = mb_strtolower($editorial);
    foreach ($porEditorial as $clave => $wiki) {
        if ($ed !== '' && str_contains($ed, $clave)) {
            array_unshift($lista, $wiki);
        }
    }
    // Los grandes catalogos, siempre al final como ultimo recurso.
    foreach (['imagecomics', 'dc', 'marvel', 'darkhorse'] as $w) {
        $lista[] = $w;
    }

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
    // El separador de un enlace [[pagina|texto]] pertenece al valor.
    if (preg_match('/^\|\s*' . preg_quote($nombre, '/') . '[ \t]*=[ \t]*([^\r\n]*)/im', $bloque, $m)) {
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
    $errores = DateTime::getLastErrors();
    if (!$d || ($errores && ($errores['warning_count'] || $errores['error_count']))) {
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
    usort($m[1], fn($a, $b) => (int) campoPlantilla($a, 'volume') <=> (int) campoPlantilla($b, 'volume'));

    // Una numeración que reinicia (0-1..0-16, después 1..N) necesita
    // posiciones continuas para las restricciones de la BD. Se conserva
    // también la notación de la fuente para mostrar cómo se compuso el rango.
    $maximos = [];
    foreach ($m[1] as $bloque) {
        preg_match_all('/\{\{\s*e\s*\|\s*([0-9\-]+)\s*\}\}/i', $bloque, $eps);
        foreach ($eps[1] as $cru) {
            $e = episodioConSerie($cru);
            if ($e) $maximos[$e['serie']] = max($maximos[$e['serie']] ?? 0, $e['n']);
        }
    }
    $desplazamientos = [];
    $offset = 0;
    foreach ($maximos as $serie => $maximo) {
        $desplazamientos[$serie] = $offset;
        $offset += $maximo;
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

        $numeros = [];
        $fuente = [];
        $normalizado = false;
        foreach ($porSerie as $serie => $valores) {
            foreach ($valores as $n) $numeros[] = $n + $desplazamientos[$serie];
            $prefijo = $serie !== '' ? $serie . '-' : '';
            $fuente[] = $prefijo . min($valores) . ' – ' . $prefijo . max($valores);
            $normalizado = $normalizado || $desplazamientos[$serie] > 0;
        }

        // La fecha japonesa es la de publicacion original.
        $fecha = aFechaISO(campoPlantilla($bloque, 'ja pub'));
        if ($fecha === '') {
            $fecha = aFechaISO(campoPlantilla($bloque, 'en pub'));
        }

        $tomos[] = [
            'tomo'   => $num,
            'formato' => 'rango',
            'titulo' => limpiarWiki(campoPlantilla($bloque, 'arcs')),
            'desde'  => min($numeros),
            'hasta'  => max($numeros),
            'arco'   => limpiarWiki(campoPlantilla($bloque, 'arcs')),
            'fecha'  => $fecha,
            'total'  => count($numeros),
            'numeracion_fuente' => implode(', ', $fuente),
            'numeracion_normalizada' => $normalizado,
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
    $sistema = 'Traduces títulos de capítulos y arcos argumentales de manga y cómic al '
        . 'español de España. Recibes una lista, uno por línea. Respondes '
        . 'SOLO un JSON: {"original 1":"traducción 1","original 2":"..."}. '
        . 'No añadas «Arco» si el original no es un arco. '
        . 'Ejemplos: "Golden Age Arc" → "Arco de la Edad de Oro"; '
        . '"Black Swordsman Arc" → "Arco del Espadachín Negro". '
        . 'Conserva los nombres propios que no se traduzcan.';

    foreach (['qwen/qwen3.8-27b', 'openai/gpt-oss-120b'] as $modelo) {
        $payload = json_encode([
            'model'       => $modelo,
            'temperature' => 0,
            'max_tokens'  => 3000,
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
            return array_filter($mapa, fn($v, $k) => in_array($k, $arcos, true)
                && is_string($v) && trim($v) !== '' && mb_strlen($v) <= 255, ARRAY_FILTER_USE_BOTH);
        }
    }
    return [];
}
