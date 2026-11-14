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
        'field_list' => 'id,name,start_year,publisher,image,deck,description,count_of_issues,last_issue',
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

// Titulos ya registrados. Se guarda tambien anio y editorial: el
// catalogo tiene UNIQUE por titulo, asi que cualquier edicion con ese
// nombre choca, pero el aviso debe decir CUAL se tiene ya registrada.
$yaRegistrados = [];
$r = $conexion->query("SELECT titulo, anio, editorial FROM comics");
while ($c = $r->fetch_assoc()) {
    $yaRegistrados[mb_strtolower(trim($c['titulo']))] = [
        'anio'      => $c['anio'],
        'editorial' => $c['editorial'],
    ];
}

/**
 * Editoriales que publican TRADUCCIONES, no la edicion original.
 *
 * COMPROBADO contra la API: al buscar "all star superman" las 8
 * ediciones se llaman EXACTAMENTE igual, asi que el nombre no sirve
 * para distinguirlas. Lo que las separa es quien las publica:
 *
 *    18139  DC Comics          2006  12 numeros  <- la original
 *    41739  Panini Verlag      2006   6 numeros  <- aleman
 *    78614  Planeta DeAgostini 2009   1 numero   <- espanol
 *
 * Y en "watchmen" la API devuelve PRIMERO la edicion brasilena de
 * Abril y la original de DC 1986 en TERCER lugar: el orden de la API
 * no es fiable, por eso se reordena aqui.
 *
 * No se descartan: una edicion traducida puede ser justo la que se
 * quiere registrar. Solo bajan de posicion.
 */
const EDITORIALES_TRADUCCION = [
    'panini', 'planeta deagostini', 'saldapress', 'glenat', 'glénat',
    'delcourt', 'cross cult', 'urban comics', 'ediciones zinco',
    'norma editorial', 'abril', 'europe comics', 'mangaline',
    'japonica polonica', 'elvifrance', 'ivrea', 'kodansha comics',
];

/**
 * Puntua una edicion para que la ORIGINAL salga primero.
 *
 * Tres senales, de mas a menos decisiva:
 *   1. El numero de entregas. La edicion completa tiene mas que
 *      cualquier recopilatorio o tomo suelto (145 frente a 25 en
 *      Invincible; 12 frente a 1 en All Star Superman).
 *   2. La editorial. Si traduce, la original esta en otra parte.
 *   3. El anio. La original precede siempre a sus traducciones.
 *
 * Es una REGLA, no un modelo: con los mismos datos puntua igual.
 */
function puntuarEdicion(array $c, int $maxIssues): int {
    $p = 0;

    // El titulo identico a lo escrito pesa mas que todo lo demas:
    // separa "All Star Superman" de "Man Of Steel: All-Star Superman".
    if ($c['coincide_exacto']) {
        $p += 1000;
    }

    $editorial = mb_strtolower($c['editorial_api'] ?? '');
    $traduce = false;
    foreach (EDITORIALES_TRADUCCION as $sello) {
        if (str_contains($editorial, $sello)) {
            $traduce = true;
            break;
        }
    }
    if (!$traduce) {
        $p += 300;
    }

    // Proporcion de entregas respecto a la edicion mas completa:
    // premia a la serie entera sin que un numero grande aplaste al
    // resto de senales.
    $n = (int) $c['n_issues'];
    if ($maxIssues > 0) {
        $p += (int) round(250 * ($n / $maxIssues));
    }
    // Un solo numero casi siempre es un recopilatorio o un tomo suelto.
    if ($n === 1) {
        $p -= 120;
    }

    // A igualdad de todo lo anterior, la mas antigua es la original.
    if (!empty($c['anio'])) {
        $p += max(0, 80 - ((int) $c['anio'] - 1900) / 2);
    }

    return (int) $p;
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

    // Estado de publicacion. La API no lo dice, pero se deduce: si el
    // ultimo numero publicado coincide con el total, la serie llego a
    // su final. Un solo numero es un tomo unico.
    $ultimo = (int) ($v['last_issue']['issue_number'] ?? 0);
    if ($nIssues === 1) {
        $estado = 'Tomo unico';
    } elseif ($nIssues > 0 && $ultimo > 0 && $ultimo >= $nIssues - 1) {
        $estado = 'Publicacion Terminada';
    } else {
        $estado = 'En publicacion';
    }

    $candidatos[] = [
        'titulo'      => $nombre,
        'anio'        => $anio,
        'editorial'   => $edit['nombre'],
        'editorial_id'=> $edit['id'],
        'editorial_valor' => $edit['nombre'],   // el <option> usa el nombre
        'estado'      => $estado,
        'editorial_en_catalogo' => $edit['en_catalogo'],
        'descripcion' => $desc,
        'portada_url' => $v['image']['medium_url'] ?? ($v['image']['original_url'] ?? ''),
        'capitulo_desde' => $nIssues > 0 ? 1 : null,
        'capitulo_hasta' => $nIssues > 0 ? $nIssues : null,
        'ya_registrado'  => isset($yaRegistrados[mb_strtolower($nombre)]),
        'registrado_como' => $yaRegistrados[mb_strtolower($nombre)] ?? null,
        'coincide_exacto'=> mb_strtolower($nombre) === mb_strtolower($titulo),
        // Para puntuar: la editorial TAL CUAL la da la API, porque
        // mapearEditorial() ya la normalizo contra el catalogo y se
        // perderia el sello que delata una traduccion.
        'editorial_api'  => $v['publisher']['name'] ?? '',
        'n_issues'       => $nIssues,
    ];
}

if (!$candidatos) {
    responder(false, [], 'Los resultados no traían datos utilizables. Regístralo a mano.');
}

// La API devuelve las ediciones en un orden poco fiable: con
// "watchmen" la primera es la brasilena de Abril y la original de DC
// de 1986 aparece TERCERA. Se reordena por puntuacion para que la
// edicion original quede arriba, que es la que casi siempre se busca.
$maxIssues = max(array_column($candidatos, 'n_issues')) ?: 0;

foreach ($candidatos as &$c) {
    $c['puntos'] = puntuarEdicion($c, $maxIssues);
}
unset($c);

usort($candidatos, fn($a, $b) => $b['puntos'] <=> $a['puntos']);

// La mejor puntuada se marca para que la interfaz pueda senalarla:
// sigue siendo el administrador quien elige, no se guarda nada solo.
$candidatos[0]['recomendado'] = true;

// Los campos de trabajo no viajan al navegador.
foreach ($candidatos as &$c) {
    unset($c['editorial_api'], $c['n_issues'], $c['puntos']);
}
unset($c);

$exacto = $candidatos[0]['coincide_exacto'];

responder(true, [
    'candidatos'   => $candidatos,
    'coincidencia' => $exacto ? 'exacta' : 'parcial',
], $exacto
    ? 'Coincidencia exacta encontrada. Revisa los datos antes de guardar.'
    : 'No hubo coincidencia exacta: elige cuál de los resultados corresponde.');
