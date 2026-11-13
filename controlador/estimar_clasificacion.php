<?php
/**
 * estimar_clasificacion.php - Clasificacion por edad en tres capas.
 *
 * EL PROBLEMA
 * ComicVine no trae clasificacion (comprobado en sus 20 recursos).
 * MangaDex si, pero solo acierta en manga. Y para comics occidentales
 * no existe un dato publico estandarizado: Marvel usa "Rated T+", DC
 * usa "Teen", Image no marca nada, y ninguna base lo recopila.
 *
 * LA SOLUCION: tres fuentes en orden, de mas fiable a menos.
 *   1. MangaDex          contentRating verificado por anio  -> VERIFICADO
 *   2. Wikipedia         demografia editorial con contexto  -> VERIFICADO
 *   3. Modelo de lenguaje a partir de la descripcion        -> ESTIMADO
 *
 * Se detiene en la primera que responde. El origen viaja en la
 * respuesta para que la interfaz pueda distinguir un dato verificado
 * de una estimacion: NO es lo mismo y no debe presentarse igual.
 *
 * La capa 3 solo actua si hay una clave de IA configurada. Sin ella el
 * sistema se comporta como antes: sugiere lo verificable y nada mas.
 *
 * En ningun caso se guarda sin que el administrador confirme.
 */

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(45);

// El .env no se carga solo: conexion.php lo hace, pero aqui no hace
// falta la base de datos. Se lee el archivo directamente para que
// getenv() encuentre GROQ_API_KEY.
(function () {
    $f = dirname(__DIR__) . '/.env';
    if (!is_readable($f)) {
        return;
    }
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $linea, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
        }
    }
})();

const ESCALA = ['ATP', '+13', '+16', '+18'];

function responder(array $r): void {
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit();
}

/** Peticion HTTP breve y uniforme. */
function traer(string $url, array $opts = []): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'ComicHouse/1.0 (proyecto academico)',
    ]);
    $c = curl_exec($ch);
    $h = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c === false || $h !== 200) ? null : $c;
}

// =================================================================
// CAPA 2 · Wikipedia
// Busca la demografia editorial japonesa y los sellos de madurez.
// SIEMPRE con contexto: buscar "teen" suelto da falsos positivos
// ("Teen Team" es un grupo de personajes de Invincible), y "explicit"
// aparece en "explicitly addressed" hablando de etnia en Saga.
// =================================================================
function porWikipedia(string $titulo, string $tipo): ?array {
    $candidatos = $tipo === 'Manga'
        ? ["$titulo (manga)", $titulo]
        : ["$titulo (comics)", "$titulo (comic book)", $titulo];

    foreach ($candidatos as $pagina) {
        $url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query', 'format' => 'json', 'prop' => 'extracts',
            'explaintext' => 1, 'titles' => $pagina, 'redirects' => 1,
        ]);
        $cuerpo = traer($url, [CURLOPT_TIMEOUT => 15]);
        if ($cuerpo === null) {
            continue;
        }
        $j = json_decode($cuerpo, true);
        $paginas = $j['query']['pages'] ?? [];
        $pg = reset($paginas);
        $texto = $pg['extract'] ?? '';
        if (mb_strlen($texto) < 400) {
            continue;   // desambiguacion o pagina vacia
        }

        // Patrones exigentes: la palabra clave debe ir acompanada.
        $reglas = [
            ['/\b(seinen|josei)\s+manga/i',              '+18', 'demografía adulta (%s)'],
            ['/\bfor\s+mature\s+readers?\b/i',           '+18', 'marcado «for mature readers»'],
            ['/\badults?\s+only\b/i',                    '+18', 'marcado «adults only»'],
            ['/\brated\s+M(ature)?\b/i',                 '+18', 'rating editorial «Rated M»'],
            ['/\bmature\s+(audience|content)\b/i',       '+18', 'descrito para público adulto'],
            ['/\brated\s+T\+/i',                         '+16', 'rating editorial «Rated T+»'],
            ['/\brated\s+T(een)?\b/i',                   '+13', 'rating editorial «Rated T»'],
            ['/\b(shounen|shonen)\s+manga/i',            '+13', 'demografía juvenil (%s)'],
            ['/\b(shojo|shoujo)\s+manga/i',              '+13', 'demografía juvenil (%s)'],
            ['/\ball[- ]ages\s+(comic|manga|series)/i',  'ATP', 'marcado «all ages»'],
        ];

        foreach ($reglas as [$re, $clas, $motivo]) {
            if (preg_match($re, $texto, $m)) {
                $det = str_contains($motivo, '%s')
                    ? sprintf($motivo, trim($m[0]))
                    : $motivo;
                return [
                    'clasificacion' => $clas,
                    'origen'        => 'verificado',
                    'fuente'        => 'Wikipedia',
                    'motivo'        => $det,
                ];
            }
        }
    }
    return null;
}

// =================================================================
// CAPA 3 · Modelo de lenguaje
// Ultimo recurso, solo si hay clave. Devuelve una ESTIMACION, no un
// dato: la interfaz debe mostrarla como tal y exigir confirmacion.
// =================================================================
function porModelo(string $titulo, string $descripcion, string $tipo): ?array {
    $clave = getenv('GROQ_API_KEY') ?: getenv('OPENAI_API_KEY') ?: '';
    if ($clave === '' || $descripcion === '') {
        return null;
    }

    $esGroq = (bool) getenv('GROQ_API_KEY');
    $url    = $esGroq
        ? 'https://api.groq.com/openai/v1/chat/completions'
        : 'https://api.openai.com/v1/chat/completions';
    // El catalogo de Groq cambia: los modelos se retiran sin aviso y la
    // peticion falla con model_not_found. Por eso se prueban varios en
    // orden. Comprobado que qwen devuelve el JSON pedido; los gpt-oss
    // responden 200 con el contenido vacio, asi que van despues.
    $modelos = $esGroq
        ? ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b']
        : ['gpt-4o-mini'];

    // Se pide SOLO la etiqueta y el motivo, para que no haya margen de
    // redaccion libre y la respuesta sea facil de validar.
    $sistema = 'Clasificas cómics y mangas por edad recomendada usando esta '
        . 'escala exacta: ATP (todo público), +13, +16, +18. Respondes SOLO '
        . 'un JSON: {"clasificacion":"...","motivo":"razón breve en español"}. '
        . 'Si la descripción no da indicios suficientes, responde '
        . '{"clasificacion":"","motivo":"sin indicios"}. '
        . 'Ante la duda elige la clasificación MÁS restrictiva: es control parental.';

    foreach ($modelos as $modelo) {
        $payload = json_encode([
            'model'       => $modelo,
            'temperature' => 0,        // determinista en lo posible
            'max_tokens'  => 200,
            'messages'    => [
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user',   'content' => "Tipo: $tipo\nTítulo: $titulo\n"
                                                . "Sinopsis: " . mb_substr($descripcion, 0, 900)],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $cuerpo = traer($url, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT    => 25,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $clave,
            ],
        ]);
        if ($cuerpo === null) {
            continue;   // modelo retirado o sin acceso: se prueba el siguiente
        }

        $j = json_decode($cuerpo, true);
        $texto = trim($j['choices'][0]['message']['content'] ?? '');
        if ($texto === '') {
            continue;   // respondio vacio: se prueba el siguiente
        }
        // El modelo a veces envuelve el JSON en ```
        if (preg_match('/\{.*\}/s', $texto, $m)) {
            $texto = $m[0];
        }
        $r = json_decode($texto, true);
        $clas = trim($r['clasificacion'] ?? '');

        // Validacion: solo se acepta una etiqueta de la escala. Si el modelo
        // devuelve cualquier otra cosa, se descarta en vez de adaptarla.
        if (!in_array($clas, ESCALA, true)) {
            continue;
        }

        // Nombre legible, sin el prefijo del proveedor
        $nombre = preg_replace('#^[a-z-]+/#', '', $modelo);

        return [
            'clasificacion' => $clas,
            'origen'        => 'estimado',
            'fuente'        => $nombre,
            'motivo'        => trim($r['motivo'] ?? 'estimado a partir de la sinopsis'),
        ];
    }

    return null;
}

// =================================================================
$titulo = trim($_POST['titulo'] ?? $_GET['titulo'] ?? '');
$desc   = trim($_POST['descripcion'] ?? $_GET['descripcion'] ?? '');
$tipo   = trim($_POST['tipo'] ?? $_GET['tipo'] ?? 'Comic');

if (mb_strlen($titulo) < 2) {
    responder(['ok' => false, 'aviso' => 'Falta el título']);
}

// Capa 2 (la 1, MangaDex, la resuelve buscar_clasificacion.php antes)
$r = porWikipedia($titulo, $tipo);

// Capa 3
if ($r === null) {
    $r = porModelo($titulo, $desc, $tipo);
}

if ($r === null) {
    responder([
        'ok' => false,
        'aviso' => 'Ninguna fuente pudo determinar la clasificación. Márcala tú.',
    ]);
}

$verificado = $r['origen'] === 'verificado';

responder([
    'ok'    => true,
    'datos' => $r,
    'aviso' => $verificado
        ? "Según {$r['fuente']}: {$r['motivo']}. Confírmalo."
        : "Estimado por {$r['fuente']} a partir de la sinopsis: {$r['motivo']}. "
          . "No es un dato verificado: revísalo.",
]);
