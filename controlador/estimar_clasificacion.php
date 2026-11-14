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

/**
 * Los generos del formulario, EXACTAMENTE como estan en la constante
 * GENEROS de crud.php, tildes incluidas. El <select> compara por valor
 * literal: «Superheroes» sin tilde no selecciona nada.
 *
 * El modelo solo puede responder con uno de estos. Si inventa otro
 * ("Western", "Drama psicologico") se descarta en vez de adaptarlo:
 * adaptar a ojo es justo donde se cuelan los errores.
 */
const GENEROS = [
    'Superhéroes', 'Fantasía oscura', 'Ciencia ficción', 'Fantasía',
    'Acción', 'Aventura', 'Terror', 'Misterio', 'Drama', 'Comedia',
    'Romance', 'Histórico', 'Deportes', 'Slice of life', 'Cyberpunk',
    'Post-apocalíptico', 'Sobrenatural', 'Thriller',
];

/**
 * Devuelve el genero de la lista que corresponde al texto dado, o ''
 * si no es ninguno.
 *
 * Compara sin tildes ni mayusculas porque el modelo acierta la palabra
 * y falla el acento mucho mas a menudo de lo que falla el concepto. Lo
 * que se devuelve es SIEMPRE la forma exacta de GENEROS: el <option>
 * del formulario compara por valor literal y «Ciencia ficcion» sin
 * tilde no seleccionaria nada.
 */
function normalizarGenero(string $texto): string {
    $plano = function (string $s): string {
        $s = mb_strtolower(trim($s));
        return strtr($s, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        ]);
    };
    $buscado = $plano($texto);
    if ($buscado === '') {
        return '';
    }
    foreach (GENEROS as $g) {
        if ($plano($g) === $buscado) {
            return $g;
        }
    }
    return '';
}

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

    // Se pide SOLO las etiquetas y el motivo, para que no haya margen
    // de redaccion libre y la respuesta sea facil de validar.
    //
    // Clasificacion y genero se piden en la MISMA llamada: son la misma
    // lectura de la misma sinopsis, y separarlas costaria otra peticion
    // (~1.6 s) sin mejorar el resultado.
    $sistema = 'Clasificas cómics y mangas a partir de su sinopsis. '
        . 'Devuelves DOS cosas:' . "\n"
        . '1) "clasificacion": edad recomendada en esta escala exacta: '
        . 'ATP (todo público), +13, +16, +18.' . "\n"
        . '2) "genero": UNO de esta lista exacta, copiado literalmente '
        . 'con sus tildes: ' . implode(', ', GENEROS) . '.' . "\n"
        . 'Respondes SOLO un JSON: {"clasificacion":"...","motivo":"razón '
        . 'breve en español","genero":"...","motivo_genero":"razón muy breve"}. '
        . 'Si la descripción no da indicios para un campo, déjalo en "". '
        . 'No inventes un género que no esté en la lista. '
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
        $gen  = trim($r['genero'] ?? '');

        // Validacion: solo se acepta una etiqueta de la escala. Si el modelo
        // devuelve cualquier otra cosa, se descarta en vez de adaptarla.
        if (!in_array($clas, ESCALA, true)) {
            $clas = '';
        }

        // Lo mismo con el genero, pero comparando sin tildes ni mayusculas:
        // el modelo suele acertar la palabra y fallar el acento
        // («Ciencia ficcion» por «Ciencia ficción»). Se devuelve SIEMPRE
        // la forma exacta de la lista, que es la que el <select> entiende.
        if ($gen !== '') {
            $gen = normalizarGenero($gen);
        }

        // Si no se pudo validar ninguno de los dos, este modelo no
        // sirvio: se prueba el siguiente.
        if ($clas === '' && $gen === '') {
            continue;
        }

        // Nombre legible, sin el prefijo del proveedor
        $nombre = preg_replace('#^[a-z-]+/#', '', $modelo);

        return [
            'clasificacion' => $clas,
            'origen'        => 'estimado',
            'fuente'        => $nombre,
            'motivo'        => trim($r['motivo'] ?? 'estimado a partir de la sinopsis'),
            'genero'        => $gen,
            'genero_motivo' => trim($r['motivo_genero'] ?? ''),
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

// Se recibe el genero que las fuentes anteriores ya resolvieron. Si
// viene, no hace falta estimarlo.
$generoYa = normalizarGenero($_POST['genero'] ?? $_GET['genero'] ?? '');

// Capa 2 (la 1, MangaDex, la resuelve buscar_clasificacion.php antes)
$r = porWikipedia($titulo, $tipo);

// Capa 3. Se llama al modelo cuando falta la clasificacion O el
// genero: Wikipedia resuelve la primera pero NUNCA el segundo, y antes
// su acierto dejaba el genero sin estimar para siempre.
if ($r === null || $generoYa === '') {
    $m = porModelo($titulo, $desc, $tipo);

    if ($r === null) {
        $r = $m;                      // el modelo aporta ambas cosas
    } elseif ($m !== null && ($m['genero'] ?? '') !== '') {
        // Wikipedia ya dio una clasificacion VERIFICADA: esa manda.
        // Del modelo se toma solo el genero, que nadie mas da.
        $r['genero']        = $m['genero'];
        $r['genero_motivo'] = $m['genero_motivo'] ?? '';
        $r['genero_fuente'] = $m['fuente'];
    }
}

if ($r === null) {
    responder([
        'ok' => false,
        'aviso' => 'Ninguna fuente pudo determinar la clasificación. Márcala tú.',
    ]);
}

// El genero SIEMPRE es una estimacion: ninguna fuente de las tres lo
// publica como dato. Se marca como tal para que no se confunda con la
// clasificacion cuando esa venga verificada de Wikipedia.
$r['genero']        = $r['genero'] ?? '';
$r['genero_motivo'] = $r['genero_motivo'] ?? '';
$r['genero_fuente'] = $r['genero_fuente'] ?? $r['fuente'];
$r['genero_origen'] = 'estimado';

$verificado = $r['origen'] === 'verificado';

responder([
    'ok'    => true,
    'datos' => $r,
    'aviso' => $verificado
        ? "Según {$r['fuente']}: {$r['motivo']}. Confírmalo."
        : "Estimado por {$r['fuente']} a partir de la sinopsis: {$r['motivo']}. "
          . "No es un dato verificado: revísalo.",
]);
