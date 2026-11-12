<?php
/**
 * buscar_metadatos.php - Autocompletado de metadatos al registrar un comic.
 *
 * Recibe un titulo por GET y consulta la API de ComicVine para traer
 * portada, anio, editorial, descripcion y numero de entregas. Devuelve
 * JSON; el formulario de crud.php lo usa para precargar los campos.
 *
 * ESTO ES UNA REGLA, NO UN MODELO: con el mismo titulo la API devuelve
 * siempre lo mismo. No interpreta ni genera texto, solo trae datos de
 * una fuente verificable y los mapea a columnas. Si la API no responde
 * o no encuentra nada, los campos quedan vacios y se llenan a mano:
 * nunca se inventa un valor para rellenar el hueco.
 *
 * El administrador SIEMPRE revisa y confirma antes de guardar.
 *
 * Configuracion: COMICVINE_API_KEY en el archivo .env
 * La clave se obtiene gratis en https://comicvine.gamespot.com/api/
 */

require_once __DIR__ . '/../modelo/conexion.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Consulta la API y devuelve los resultados crudos.
 * Se usa tanto para la busqueda principal como para el reintento.
 */
function consultarApi(string $query, string $apiKey, int $limite = 8): array {
    $url = 'https://comicvine.gamespot.com/api/search/?' . http_build_query([
        'api_key'    => $apiKey,
        'format'     => 'json',
        'query'      => $query,
        'resources'  => 'volume',      // un "volume" es la serie completa
        'limit'      => $limite,
        'field_list' => 'id,name,start_year,publisher,image,deck,description,count_of_issues',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_FOLLOWLOCATION => true,
        // ComicVine rechaza las peticiones sin User-Agent identificable.
        CURLOPT_USERAGENT      => 'ComicHouse/1.0 (proyecto academico)',
    ]);
    $cuerpo = curl_exec($ch);
    $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    return ['cuerpo' => $cuerpo, 'http' => $http, 'error' => $err];
}

/**
 * Cuando no hay resultados, casi siempre es un error de tipeo.
 * Se reintenta con el titulo recortado y se compara lo que vuelve
 * contra lo escrito: si se parece lo suficiente, se sugiere.
 *
 * Es una comparacion de texto, no un modelo: no interpreta el
 * significado, solo mide cuantas letras hay que cambiar.
 */
function buscarParecido(string $titulo, string $apiKey): string {
    // Con menos de 5 letras cualquier cosa "se parece" y la sugerencia
    // seria ruido en vez de ayuda.
    if (mb_strlen($titulo) < 5) {
        return '';
    }

    // El endpoint /search exige el titulo exacto, pero /volumes con
    // filter=name: si busca por prefijo ("invinc" encuentra Invincible).
    // Como el typo puede estar en medio ("invicible" le falta una n),
    // se prueba quitando una letra cada vez hasta dar con un prefijo
    // que exista. Es comparacion de texto, no interpretacion.
    // El typo puede estar al principio ("invicible" falla ya en la
    // quinta letra), asi que se prueba de largo a corto empezando por
    // un prefijo ya recortado: saltarse las variantes largas ahorra
    // peticiones que de todos modos no encontrarian nada.
    $largo = mb_strlen($titulo);
    $desde = min($largo, max(5, (int) ceil($largo * 0.6)));
    $prefijos = [];
    for ($i = $desde; $i >= 4; $i--) {
        $prefijos[] = mb_substr($titulo, 0, $i);
    }

    $buscado = mb_strtolower($titulo);
    // Se admite cambiar hasta un tercio de las letras: cubre el typo
    // tipico sin sugerir un titulo que no tiene nada que ver.
    $tolerancia = max(2, (int) floor(mb_strlen($titulo) / 3));

    $mejor = '';
    $menorDistancia = PHP_INT_MAX;
    $consultas = 0;

    foreach ($prefijos as $pref) {
        // Tope de peticiones: no conviene castigar la API por un typo.
        if ($consultas >= 4 || $mejor !== '') {
            break;
        }
        $consultas++;

        $url = 'https://comicvine.gamespot.com/api/volumes/?' . http_build_query([
            'api_key'    => $apiKey,
            'format'     => 'json',
            'filter'     => 'name:' . $pref,
            'limit'      => 10,
            'field_list' => 'name',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'ComicHouse/1.0 (proyecto academico)',
        ]);
        $cuerpo = curl_exec($ch);
        $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($cuerpo === false || $http !== 200) {
            continue;
        }
        $j = json_decode($cuerpo, true);
        if (!is_array($j) || ($j['status_code'] ?? 0) != 1) {
            continue;
        }

        foreach (($j['results'] ?? []) as $v) {
            $nombre = trim($v['name'] ?? '');
            if ($nombre === '') {
                continue;
            }
            $d = levenshtein($buscado, mb_strtolower($nombre));
            if ($d < $menorDistancia && $d <= $tolerancia) {
                $menorDistancia = $d;
                $mejor = $nombre;
            }
        }
    }

    return $mejor;
}

/** Respuesta uniforme para que el frontend no tenga que adivinar. */
function responder(bool $ok, array $datos = [], string $aviso = ''): void {
    echo json_encode([
        'ok'    => $ok,
        'aviso' => $aviso,
        'datos' => $datos,
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$titulo = trim($_GET['titulo'] ?? '');

if (mb_strlen($titulo) < 3) {
    responder(false, [], 'Escribe al menos 3 caracteres del título.');
}

$apiKey = getenv('COMICVINE_API_KEY') ?: '';
if ($apiKey === '') {
    responder(false, [], 'Falta configurar COMICVINE_API_KEY en el archivo .env');
}

// ---------------------------------------------------------------
// Consulta a ComicVine
// ---------------------------------------------------------------
$r = consultarApi($titulo, $apiKey);

if ($r['cuerpo'] === false || $r['http'] !== 200) {
    responder(false, [], 'No se pudo consultar la fuente de datos'
        . ($r['error'] !== '' ? ": {$r['error']}" : " (HTTP {$r['http']})")
        . '. Registra los datos a mano.');
}

$json = json_decode($r['cuerpo'], true);

if (!is_array($json) || ($json['status_code'] ?? 0) != 1) {
    $detalle = $json['error'] ?? 'respuesta inesperada';
    responder(false, [], "La fuente de datos respondió: $detalle");
}

$resultados = $json['results'] ?? [];

// Sin resultados: casi siempre es un error de tipeo ("invicible").
// Se reintenta quitando la ultima palabra incompleta y, si aun asi no
// hay nada, se busca un titulo parecido entre los que si existen.
if (!$resultados) {
    $sugerencia = buscarParecido($titulo, $apiKey);
    if ($sugerencia !== '') {
        responder(false, ['sugerencia' => $sugerencia],
            "No se encontró «{$titulo}». ¿Querías decir «{$sugerencia}»?");
    }
    responder(false, [],
        "No se encontró ningún cómic llamado «{$titulo}». Revisa cómo se escribe o regístralo a mano.");
}

// ---------------------------------------------------------------
// Editoriales que ya existen en el catalogo, para mapear la que llega
// ---------------------------------------------------------------
$editoriales = [];
$r = $conexion->query("SELECT id, nombre FROM editoriales");
while ($e = $r->fetch_assoc()) {
    $editoriales[mb_strtolower(trim($e['nombre']))] = $e;
}

/**
 * Busca la editorial en el catalogo local.
 * Compara por nombre normalizado y, si no hay coincidencia exacta,
 * por contencion ("DC Comics" encuentra "DC"). Si no la halla,
 * devuelve el nombre crudo para que el admin decida: no se crea sola.
 */
function mapearEditorial(string $nombre, array $catalogo): array {
    $clave = mb_strtolower(trim($nombre));
    if ($clave === '') {
        return ['id' => null, 'nombre' => '', 'en_catalogo' => false];
    }
    if (isset($catalogo[$clave])) {
        return ['id' => $catalogo[$clave]['id'],
                'nombre' => $catalogo[$clave]['nombre'], 'en_catalogo' => true];
    }
    foreach ($catalogo as $k => $e) {
        if (str_contains($clave, $k) || str_contains($k, $clave)) {
            return ['id' => $e['id'], 'nombre' => $e['nombre'], 'en_catalogo' => true];
        }
    }
    return ['id' => null, 'nombre' => $nombre, 'en_catalogo' => false];
}

/** Limpia el HTML que ComicVine incluye en las descripciones. */
function limpiarTexto(?string $html, int $max = 600): string {
    if (!$html) return '';
    // Los bloques se cierran sin espacio: "<p>uno</p><p>dos</p>" quedaria
    // como "unodos". Se separan antes de quitar las etiquetas.
    $html = preg_replace('#</(p|div|li|h[1-6]|br)\s*>#i', ' ', $html);
    $html = preg_replace('#<br\s*/?>#i', ' ', $html);
    $txt = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = trim(preg_replace('/\s+/u', ' ', $txt));
    if (mb_strlen($txt) > $max) {
        $corte = mb_substr($txt, 0, $max);
        $ultimo = mb_strrpos($corte, '. ');
        $txt = $ultimo !== false ? mb_substr($corte, 0, $ultimo + 1) : $corte . '…';
    }
    return $txt;
}

// Titulos ya registrados, para avisar de duplicados antes de guardar
$yaRegistrados = [];
$r = $conexion->query("SELECT titulo FROM comics");
while ($c = $r->fetch_assoc()) {
    $yaRegistrados[mb_strtolower(trim($c['titulo']))] = true;
}

$ANIO_ACTUAL = (int) date('Y');
$candidatos = [];

foreach ($resultados as $v) {
    $nombre = trim($v['name'] ?? '');
    if ($nombre === '') continue;

    $anio = (int) ($v['start_year'] ?? 0);
    // El esquema exige un anio valido y no futuro.
    if ($anio < 1900 || $anio > $ANIO_ACTUAL) $anio = null;

    $edit = mapearEditorial($v['publisher']['name'] ?? '', $editoriales);

    // deck es el resumen corto; description el largo con HTML.
    $desc = limpiarTexto($v['deck'] ?? '') ?: limpiarTexto($v['description'] ?? '');

    $nIssues = (int) ($v['count_of_issues'] ?? 0);

    $candidatos[] = [
        'titulo'      => $nombre,
        'anio'        => $anio,
        'editorial'   => $edit['nombre'],
        'editorial_id'=> $edit['id'],
        'editorial_en_catalogo' => $edit['en_catalogo'],
        'descripcion' => $desc,
        'portada_url' => $v['image']['medium_url'] ?? ($v['image']['original_url'] ?? ''),
        'capitulo_desde' => $nIssues > 0 ? 1 : null,
        'capitulo_hasta' => $nIssues > 0 ? $nIssues : null,
        'ya_registrado'  => isset($yaRegistrados[mb_strtolower($nombre)]),
        'coincide_exacto'=> mb_strtolower($nombre) === mb_strtolower($titulo),
    ];
}

if (!$candidatos) {
    responder(false, [], 'Los resultados no traían datos utilizables. Regístralo a mano.');
}

// Si uno coincide exactamente con lo escrito, va primero.
usort($candidatos, fn($a, $b) => ($b['coincide_exacto'] <=> $a['coincide_exacto']));

$exacto = $candidatos[0]['coincide_exacto'];

responder(true, [
    'candidatos'   => $candidatos,
    'coincidencia' => $exacto ? 'exacta' : 'parcial',
], $exacto
    ? 'Coincidencia exacta encontrada. Revisa los datos antes de guardar.'
    : 'No hubo coincidencia exacta: elige cuál de los resultados corresponde.');
