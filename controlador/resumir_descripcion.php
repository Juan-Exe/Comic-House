<?php
/**
 * resumir_descripcion.php - Resumen generativo de la sinopsis.
 *
 * EL PROBLEMA
 * ComicVine devuelve descripciones de longitud muy desigual. La
 * traduccion es literal, asi que lo que viene largo sigue largo: la de
 * Watchmen son 634 caracteres frente a los ~320 del resto del catalogo,
 * y en la ficha ocupa el doble de lineas. En movil el recorte de CSS la
 * corta a mitad de frase.
 *
 * LA SOLUCION
 * Pedirle a un modelo que RESUMA en vez de traducir palabra por
 * palabra. Un resumen de 450 caracteres bien escrito informa mas que
 * 634 cortados.
 *
 * ESTO ES UNA ESTIMACION, NO UN DATO. El texto lo redacta un modelo y
 * puede equivocarse u omitir algo importante. Por eso:
 *   - la interfaz lo marca en ambar y exige revision
 *   - el original SIEMPRE viaja de vuelta, para poder recuperarlo
 *   - si algo falla, se devuelve el original sin tocar
 *
 * Si no hay clave configurada, el sistema se comporta como antes: se
 * guarda la traduccion literal. Una mejora opcional no puede volverse
 * un requisito para registrar.
 */

header('Content-Type: application/json; charset=utf-8');
@set_time_limit(40);

// El .env no se carga solo: aqui no hace falta la base de datos, asi
// que se lee el archivo directamente para que getenv() encuentre la
// clave. Mismo criterio que en estimar_clasificacion.php.
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

/** Longitud maxima del resumen. Por debajo de esto no se toca nada. */
const LIMITE = 450;

/**
 * Margen de tolerancia al validar. El modelo cuenta caracteres de forma
 * aproximada, asi que se le pide 450 y se acepta hasta 500; por encima
 * se descarta. Recortar a mitad de frase seria repetir el problema que
 * este endpoint existe para resolver.
 */
const LIMITE_DURO = 500;

function responder(array $r): void {
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Heuristica de idioma. No pretende ser un detector: solo descarta el
 * caso real que se da cuando el modelo ignora la instruccion y responde
 * en ingles. Se mira la proporcion de palabras funcionales, que son las
 * que de verdad distinguen un idioma de otro.
 */
function pareceEspanol(string $t): bool {
    $t = ' ' . mb_strtolower($t) . ' ';
    $es = 0;
    foreach ([' el ', ' la ', ' los ', ' las ', ' de ', ' que ', ' una ',
              ' por ', ' con ', ' para ', ' su ', ' en ', ' del ', ' se '] as $p) {
        $es += substr_count($t, $p);
    }
    $en = 0;
    foreach ([' the ', ' of ', ' and ', ' is ', ' that ', ' with ',
              ' this ', ' from ', ' are ', ' was ', ' his ', ' their '] as $p) {
        $en += substr_count($t, $p);
    }
    return $es > $en;
}

/**
 * Pide el resumen al modelo. Devuelve null si no se pudo obtener uno
 * valido: el que llama decide que hacer, y siempre es "usar el
 * original".
 */
function resumir(string $texto, string $titulo): ?array {
    $clave = getenv('GROQ_API_KEY') ?: getenv('OPENAI_API_KEY') ?: '';
    if ($clave === '') {
        return null;
    }

    $esGroq = (bool) getenv('GROQ_API_KEY');
    $url    = $esGroq
        ? 'https://api.groq.com/openai/v1/chat/completions'
        : 'https://api.openai.com/v1/chat/completions';
    // El catalogo de Groq cambia sin aviso, por eso hay lista de
    // reserva. Mismo criterio y mismo orden que en la clasificacion.
    $modelos = $esGroq
        ? ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b']
        : ['gpt-4o-mini'];

    // Se le pide conservar lo CONCRETO (autor, año, premio, personaje)
    // porque es justo lo que un resumen flojo tiende a cambiar por
    // generalidades del tipo "una historia sobre heroes".
    $sistema = 'Resumes sinopsis de cómics para la ficha de un catálogo. '
        . 'Reglas estrictas:' . "\n"
        . '1) Escribes en ESPAÑOL, en prosa corrida, UN SOLO párrafo.' . "\n"
        . '2) MÁXIMO ' . LIMITE . ' caracteres. Es un límite duro.' . "\n"
        . '3) Conservas los datos concretos que identifican la obra: '
        . 'autor, año, premios, personajes, editorial. NO los sustituyas '
        . 'por generalidades.' . "\n"
        . '4) No añades nada que no esté en el texto original.' . "\n"
        . '5) No empiezas con «Este cómic» ni con el título: vas directo '
        . 'al contenido.' . "\n"
        . 'Respondes SOLO el resumen, sin comillas ni explicaciones.';

    foreach ($modelos as $modelo) {
        $payload = json_encode([
            'model'       => $modelo,
            'temperature' => 0.2,   // algo de holgura: es redaccion, no una etiqueta
            'max_tokens'  => 400,
            'messages'    => [
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user',   'content' => "Título: $titulo\n\nSinopsis:\n$texto"],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $clave,
            ],
            CURLOPT_USERAGENT      => 'ComicHouse/1.0 (proyecto academico)',
        ]);
        $cuerpo = curl_exec($ch);
        $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($cuerpo === false || $http !== 200) {
            continue;   // modelo retirado o sin acceso: se prueba el siguiente
        }

        $j = json_decode($cuerpo, true);
        $r = trim($j['choices'][0]['message']['content'] ?? '');
        if ($r === '') {
            continue;
        }

        // El modelo a veces envuelve la respuesta en comillas pese a
        // pedirle que no lo haga.
        $r = trim($r, " \t\n\r\0\x0B\"«»");

        // --- Validacion. Si no la pasa, se descarta: mejor el original
        //     literal que un resumen malo presentado como mejora. ---
        $n = mb_strlen($r);

        if ($n > LIMITE_DURO) {
            continue;   // se paso del limite
        }
        if ($n >= mb_strlen($texto)) {
            continue;   // no resumio nada
        }
        if ($n < 80) {
            continue;   // se quedo en nada util
        }
        if (!pareceEspanol($r)) {
            continue;   // respondio en ingles
        }

        return [
            'resumen' => $r,
            'fuente'  => preg_replace('#^[a-z-]+/#', '', $modelo),
            'largo'   => $n,
        ];
    }

    return null;
}

// =================================================================
$texto  = trim($_POST['descripcion'] ?? $_GET['descripcion'] ?? '');
$titulo = trim($_POST['titulo'] ?? $_GET['titulo'] ?? '');

if ($texto === '') {
    responder(['ok' => false, 'aviso' => 'Falta la descripción']);
}

$largoOriginal = mb_strlen($texto);

// Si ya cabe, no se resume: gastar una llamada para no mejorar nada
// solo anadiria espera (CA-01.5).
if ($largoOriginal <= LIMITE) {
    responder([
        'ok'     => true,
        'datos'  => [
            'resumido'    => false,
            'descripcion' => $texto,
            'original'    => $texto,
            'largo'       => $largoOriginal,
        ],
        'aviso'  => '',
    ]);
}

$r = resumir($texto, $titulo);

if ($r === null) {
    // Sin clave, con la API caida o con una respuesta que no paso la
    // validacion: se devuelve el original intacto. El registro sigue.
    responder([
        'ok'    => true,
        'datos' => [
            'resumido'    => false,
            'descripcion' => $texto,
            'original'    => $texto,
            'largo'       => $largoOriginal,
        ],
        'aviso' => 'No se pudo resumir: se usa la descripción completa.',
    ]);
}

responder([
    'ok'    => true,
    'datos' => [
        'resumido'    => true,
        'descripcion' => $r['resumen'],
        // El original viaja SIEMPRE, para poder recuperarlo desde la
        // interfaz sin volver a pedir nada (principio 2.2).
        'original'    => $texto,
        'largo'       => $r['largo'],
        'largo_antes' => $largoOriginal,
        'fuente'      => $r['fuente'],
        'origen'      => 'estimado',
    ],
    'aviso' => "Resumen generado por {$r['fuente']}: de {$largoOriginal} a "
             . "{$r['largo']} caracteres. No es el texto original: revísalo.",
]);
