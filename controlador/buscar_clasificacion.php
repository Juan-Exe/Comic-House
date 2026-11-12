<?php
/**
 * buscar_clasificacion.php - Fuente secundaria para los datos que
 * ComicVine no trae: clasificacion por edad y genero.
 *
 * POR QUE EXISTE
 * ComicVine no tiene clasificacion por edad en ninguno de sus 20
 * recursos (comprobado). MangaDex si: devuelve contentRating y tags
 * de genero. Pero solo es fiable para manga: al buscar comics
 * occidentales devuelve obras distintas con el mismo titulo.
 *
 * COMO SE EVITA EL ERROR
 * Verificacion cruzada por anio. Si MangaDex devuelve un "Berserk" de
 * 2018 y ComicVine dice 1989, no es la misma obra y se descarta. Sin
 * esa comprobacion estariamos adivinando.
 *
 * Es determinista: consulta, compara y mapea. No interpreta ni genera.
 * La clasificacion se SUGIERE con su origen visible, nunca se
 * preselecciona: sigue siendo el campo donde un error es grave.
 */

header('Content-Type: application/json; charset=utf-8');

/** Equivalencia de escalas. MangaDex usa 4 niveles; Comic House otros 4. */
const MAPA_CLASIFICACION = [
    'safe'         => 'ATP',
    'suggestive'   => '+13',
    'erotica'      => '+18',
    'pornographic' => '+18',
];

/**
 * Tags de MangaDex (grupo "genre") equivalentes a los generos del
 * catalogo. Los nombres de la derecha coinciden EXACTO con la
 * constante GENEROS del formulario, tildes incluidas.
 */
const MAPA_GENERO = [
    'Horror'          => 'Terror',
    'Psychological'   => 'Misterio',
    'Mystery'         => 'Misterio',
    'Sci-Fi'          => 'Ciencia ficción',
    'Fantasy'         => 'Fantasía',
    'Adventure'       => 'Aventura',
    'Action'          => 'Acción',
    'Drama'           => 'Drama',
    'Comedy'          => 'Comedia',
    'Romance'         => 'Romance',
    'Historical'      => 'Histórico',
    'Sports'          => 'Deportes',
    'Slice of Life'   => 'Slice of life',
    'Thriller'        => 'Thriller',
    'Supernatural'    => 'Sobrenatural',
    'Superhero'       => 'Superhéroes',
    'Crime'           => 'Misterio',
    'Tragedy'         => 'Drama',
];

/** Un genero concreto dice mas que uno generico: se ordenan por utilidad. */
const PRIORIDAD_GENERO = ['Terror', 'Ciencia ficción', 'Superhéroes',
    'Fantasía', 'Misterio', 'Sobrenatural', 'Thriller', 'Histórico',
    'Deportes', 'Romance', 'Comedia', 'Drama', 'Aventura', 'Acción'];

function responder(bool $ok, array $datos = [], string $aviso = ''): void {
    echo json_encode(['ok' => $ok, 'datos' => $datos, 'aviso' => $aviso],
        JSON_UNESCAPED_UNICODE);
    exit();
}

$titulo = trim($_GET['titulo'] ?? '');
$anio   = (int) ($_GET['anio'] ?? 0);

if (mb_strlen($titulo) < 3) {
    responder(false, [], 'Título demasiado corto');
}

// ---------------------------------------------------------------
// Consulta
// ---------------------------------------------------------------
$url = 'https://api.mangadex.org/manga?' . http_build_query([
    'title'            => $titulo,
    'limit'            => 5,
    'order[relevance]' => 'desc',
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
    responder(false, [], 'La fuente secundaria no respondió');
}

$j = json_decode($cuerpo, true);
$obras = $j['data'] ?? [];
if (!$obras) {
    responder(false, [], 'Sin datos en la fuente secundaria');
}

/** Devuelve el titulo en el idioma que haya, con preferencia por el ingles. */
function tituloDe(array $attrs): string {
    $t = $attrs['title'] ?? [];
    foreach (['en', 'ja-ro', 'ja'] as $k) {
        if (!empty($t[$k])) {
            return $t[$k];
        }
    }
    return (string) (reset($t) ?: '');
}

/** ¿Coinciden los titulos, ignorando mayusculas y signos? */
function mismoTitulo(string $a, string $b): bool {
    $norm = function (string $x): string {
        $x = mb_strtolower($x);
        $x = preg_replace('/[^a-z0-9áéíóúñü ]/u', '', $x);
        return trim(preg_replace('/\s+/', ' ', $x));
    };
    return $norm($a) === $norm($b);
}

// ---------------------------------------------------------------
// Verificacion cruzada: el anio debe coincidir con el de ComicVine.
// Es lo que distingue el Berserk de 1989 del "Boushoku no Berserk"
// de 2018. Sin anio de referencia no se arriesga ninguna sugerencia.
// ---------------------------------------------------------------
if ($anio <= 0) {
    responder(false, [], 'Sin año de referencia: no se puede verificar la obra');
}

$elegida = null;
$motivo  = '';

foreach ($obras as $o) {
    $a = $o['attributes'] ?? [];
    $t = tituloDe($a);
    $y = (int) ($a['year'] ?? 0);

    if ($y === 0 || !mismoTitulo($t, $titulo)) {
        continue;
    }
    // Se admite un ano de diferencia: las fuentes discrepan a veces
    // entre la primera publicacion y la edicion recopilada.
    if (abs($y - $anio) <= 1) {
        $elegida = $a;
        $motivo  = "título y año ({$y}) coinciden";
        break;
    }
}

if (!$elegida) {
    responder(false, [], 'La obra encontrada no coincide en año: se descarta');
}

// ---------------------------------------------------------------
// Mapeo
// ---------------------------------------------------------------
$rating = $elegida['contentRating'] ?? '';
$clasificacion = MAPA_CLASIFICACION[$rating] ?? '';

$generos = [];
foreach ($elegida['tags'] ?? [] as $t) {
    // Solo el grupo "genre": los de "theme" y "content" describen
    // elementos de la trama, no el genero de la obra.
    if (($t['attributes']['group'] ?? '') !== 'genre') {
        continue;
    }
    $nombre = $t['attributes']['name']['en'] ?? '';
    if (isset(MAPA_GENERO[$nombre])) {
        $generos[] = MAPA_GENERO[$nombre];
    }
}
$generos = array_values(array_unique($generos));

// El mas especifico primero
usort($generos, function ($x, $y) {
    $px = array_search($x, PRIORIDAD_GENERO, true);
    $py = array_search($y, PRIORIDAD_GENERO, true);
    return ($px === false ? 99 : $px) <=> ($py === false ? 99 : $py);
});

if ($clasificacion === '' && !$generos) {
    responder(false, [], 'La obra coincide pero no trae datos utilizables');
}

responder(true, [
    'clasificacion'      => $clasificacion,
    'clasificacion_cruda'=> $rating,
    'genero'             => $generos[0] ?? '',
    'generos'            => $generos,
    'verificado_por'     => $motivo,
], "Datos complementados desde MangaDex · {$motivo}");
