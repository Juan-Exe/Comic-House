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
$url = 'https://comicvine.gamespot.com/api/search/?' . http_build_query([
    'api_key'   => $apiKey,
    'format'    => 'json',
    'query'     => $titulo,
    'resources' => 'volume',          // un "volume" es la serie completa
    'limit'     => 8,
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
$respuesta = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errCurl   = curl_error($ch);
curl_close($ch);

if ($respuesta === false || $httpCode !== 200) {
    responder(false, [], 'No se pudo consultar la fuente de datos'
        . ($errCurl !== '' ? ": $errCurl" : " (HTTP $httpCode)")
        . '. Registra los datos a mano.');
}

$json = json_decode($respuesta, true);

if (!is_array($json) || ($json['status_code'] ?? 0) != 1) {
    $detalle = $json['error'] ?? 'respuesta inesperada';
    responder(false, [], "La fuente de datos respondió: $detalle");
}

$resultados = $json['results'] ?? [];
if (!$resultados) {
    responder(false, [], 'No se encontró ningún cómic con ese título. Regístralo a mano.');
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
