<?php
/**
 * traducir.php - Traduccion EN -> ES de las descripciones (HU-06).
 *
 * ComicVine devuelve las sinopsis en ingles y el catalogo esta en
 * espanol. Esto lo traduce con MyMemory, que es determinista: el mismo
 * texto devuelve siempre la misma traduccion.
 *
 * NO es un modelo generativo: no redacta ni resume, solo traduce. Si
 * el servicio falla, se devuelve el texto original con un aviso, nunca
 * una version inventada (CA-06.3).
 *
 * Limite verificado del servicio: 500 caracteres por peticion, por eso
 * los textos largos se parten por frases (CA-06.2).
 */

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(60);

const LIMITE_BLOQUE = 450;   // margen bajo los 500 del servicio
const MAX_BLOQUES   = 4;     // tope: no traducir sinopsis enormes

/**
 * Parte el texto en bloques que no superen el limite, cortando por
 * frases para que la traduccion no pierda el sentido a mitad.
 */
function partirEnFrases(string $texto, int $limite): array {
    if (mb_strlen($texto) <= $limite) {
        return [$texto];
    }

    // Separar tras punto, interrogacion o exclamacion seguidos de espacio.
    $frases = preg_split('/(?<=[.!?])\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
    $bloques = [];
    $actual = '';

    foreach ($frases as $f) {
        // Una sola frase ya mas larga que el limite: se corta a lo bruto.
        if (mb_strlen($f) > $limite) {
            if ($actual !== '') {
                $bloques[] = $actual;
                $actual = '';
            }
            foreach (mb_str_split($f, $limite) as $trozo) {
                $bloques[] = $trozo;
            }
            continue;
        }
        if (mb_strlen($actual) + mb_strlen($f) + 1 > $limite) {
            $bloques[] = $actual;
            $actual = $f;
        } else {
            $actual = $actual === '' ? $f : $actual . ' ' . $f;
        }
    }
    if ($actual !== '') {
        $bloques[] = $actual;
    }
    return $bloques;
}

/** Traduce un bloque. Devuelve null si el servicio no responde bien. */
function traducirBloque(string $texto): ?string {
    $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
        'q'        => $texto,
        'langpair' => 'en|es',
    ]);

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
    if (!is_array($j) || (int) ($j['responseStatus'] ?? 0) !== 200) {
        return null;
    }
    $t = trim($j['responseData']['translatedText'] ?? '');

    // El servicio a veces devuelve el aviso de error como si fuera la
    // traduccion. Si viene en mayusculas y menciona un limite, no sirve.
    if ($t === '' || preg_match('/QUERY LENGTH LIMIT|INVALID/i', $t)) {
        return null;
    }
    return $t;
}

/** ¿El texto ya parece espanol? Evita traducir lo que no hace falta. */
function pareceEspanol(string $t): bool {
    $marcas = [' el ', ' la ', ' los ', ' las ', ' que ', ' de ', ' un ',
               ' una ', ' con ', ' por ', ' para ', ' su ', ' es '];
    $bajo = ' ' . mb_strtolower($t) . ' ';
    $n = 0;
    foreach ($marcas as $m) {
        if (str_contains($bajo, $m)) {
            $n++;
        }
    }
    return $n >= 4;
}

// ---------------------------------------------------------------
$texto = trim($_POST['texto'] ?? $_GET['texto'] ?? '');

if ($texto === '') {
    echo json_encode(['ok' => false, 'texto' => '', 'aviso' => 'Sin texto'],
        JSON_UNESCAPED_UNICODE);
    exit();
}

if (pareceEspanol($texto)) {
    echo json_encode([
        'ok' => true, 'texto' => $texto, 'traducido' => false,
        'aviso' => 'El texto ya estaba en español',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$bloques = partirEnFrases($texto, LIMITE_BLOQUE);

if (count($bloques) > MAX_BLOQUES) {
    $bloques = array_slice($bloques, 0, MAX_BLOQUES);
}

$partes = [];
$fallos = 0;

foreach ($bloques as $b) {
    $t = traducirBloque($b);
    if ($t === null) {
        $fallos++;
        $partes[] = $b;          // se conserva el original de ese bloque
    } else {
        $partes[] = $t;
    }
}

$resultado = trim(implode(' ', $partes));

// Todos los bloques fallaron: se devuelve el original con aviso, nunca
// una traduccion inventada.
if ($fallos === count($bloques)) {
    echo json_encode([
        'ok' => true, 'texto' => $texto, 'traducido' => false,
        'aviso' => 'No se pudo traducir. Queda el texto original: puedes reescribirlo.',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

echo json_encode([
    'ok'        => true,
    'texto'     => $resultado,
    'traducido' => true,
    'aviso'     => $fallos > 0
        ? 'Traducida en parte: revisa el texto antes de guardar.'
        : 'Descripción traducida al español. Revísala antes de guardar.',
], JSON_UNESCAPED_UNICODE);
