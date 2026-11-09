<?php
/**
 * validar_capitulo.php - Reglas de numeración de capítulos.
 *
 * Un manga y un cómic no se numeran igual: el manga agrupa capítulos
 * en arcos ("Arco del Espadachín Negro, #1 - #3") y el cómic publica
 * grapas sueltas ("#1") o recopilatorios ("Tomo Único"). Con el número
 * guardado como texto libre no se podía comprobar nada; aquí se valida
 * la numeración antes de que llegue a la base.
 *
 * La base impone las mismas reglas por su cuenta (ver
 * benchmark/momento2_formatos_capitulo.sql). Esta capa existe para
 * explicarle a la persona qué pasó, no para sustituirla.
 */

/** Formatos admitidos y qué significa cada uno. */
const FORMATOS_CAPITULO = [
    'grapa' => 'Número suelto',
    'rango' => 'Varios capítulos en una entrega',
    'tomo'  => 'Recopilatorio',
];

/** Los formatos que tienen sentido para cada tipo de obra. */
const FORMATOS_POR_TIPO = [
    'Manga' => ['rango', 'grapa'],   // suele agrupar por arco
    'Comic' => ['grapa', 'rango', 'tomo'],
];

/**
 * Comprueba que un número de capítulo sea eso: un número.
 *
 * Devuelve el entero, o null si el texto no sirve. Se rechazan las
 * letras, los espacios interiores y los ceros a la izquierda: '007' y
 * '7' son el mismo capítulo, y admitir ambos crearía duplicados que
 * nadie vería hasta tenerlos en el catálogo.
 */
function numeroDeCapitulo(string $texto): ?int
{
    $texto = trim($texto);

    if ($texto === '' || !preg_match('/^[0-9]+$/', $texto)) {
        return null;                       // letras, espacios o signos
    }
    if (strlen($texto) > 1 && $texto[0] === '0') {
        return null;                       // 007: un cero de más
    }

    $n = (int) $texto;
    return ($n >= 1 && $n <= 9999) ? $n : null;
}

/**
 * Cuántos capítulos declara tener una obra.
 *
 * El campo es texto libre y en el catálogo aparece sobre todo como un
 * rango ('1 - 380'), así que el total es el ÚLTIMO número, no el
 * primero. Devuelve null si no hay ninguno.
 */
function capitulosDeclarados(mysqli $conexion, int $comic_id): ?int
{
    $st = $conexion->prepare("SELECT capitulos FROM comics WHERE id = ?");
    $st->bind_param('i', $comic_id);
    $st->execute();
    $st->bind_result($texto);
    $hay = $st->fetch();
    $st->close();

    if (!$hay || !preg_match_all('/[0-9]+/', (string) $texto, $m)) {
        return null;
    }
    $n = (int) end($m[0]);
    return $n > 0 ? $n : null;
}

/**
 * Revisa la numeración de un capítulo antes de guardarlo.
 *
 * Devuelve un array con:
 *   estado = 'ok'       -> se puede guardar
 *            'error'    -> no se puede: la base lo rechazaría
 *            'aviso'    -> es raro, pero puede ser legítimo
 *   mensaje -> qué decirle a la persona
 *   desde / hasta -> la numeración ya resuelta
 */
function revisarCapitulo(
    mysqli $conexion,
    int $comic_id,
    string $tipo,
    string $formato,
    string $desde_txt,
    string $hasta_txt = ''
): array {
    $err = fn($m) => ['estado' => 'error', 'mensaje' => $m,
                      'desde' => null, 'hasta' => null];

    if (!isset(FORMATOS_CAPITULO[$formato])) {
        return $err('Formato de numeración no reconocido.');
    }

    $permitidos = FORMATOS_POR_TIPO[$tipo] ?? FORMATOS_POR_TIPO['Comic'];
    if (!in_array($formato, $permitidos, true)) {
        return $err(sprintf('Un %s no se numera como «%s».',
                            mb_strtolower($tipo), FORMATOS_CAPITULO[$formato]));
    }

    // Un recopilatorio no lleva número de capítulo.
    if ($formato === 'tomo') {
        return ['estado' => 'ok', 'mensaje' => '', 'desde' => 1, 'hasta' => 1];
    }

    $desde = numeroDeCapitulo($desde_txt);
    if ($desde === null) {
        return $err('El número de capítulo solo admite dígitos, del 1 al 9999, '
                  . 'y sin ceros delante.');
    }

    $hasta = $desde;
    if ($formato === 'rango') {
        $hasta = numeroDeCapitulo($hasta_txt);
        if ($hasta === null) {
            return $err('El capítulo final solo admite dígitos, del 1 al 9999, '
                      . 'y sin ceros delante.');
        }
        if ($hasta < $desde) {
            return $err("El rango termina antes de empezar: $desde a $hasta.");
        }
        if ($hasta === $desde) {
            return $err('Un rango de un solo capítulo es una grapa: '
                      . 'elige ese formato.');
        }
    }

    // ¿Se solapa con algo que ya está subido?
    $st = $conexion->prepare(
        "SELECT numero FROM capitulos
          WHERE comic_id = ? AND numero_desde <= ? AND numero_hasta >= ?
          LIMIT 1"
    );
    $st->bind_param('iii', $comic_id, $hasta, $desde);
    $st->execute();
    $st->bind_result($choca);
    $hay = $st->fetch();
    $st->close();

    if ($hay) {
        return $err('Esos capítulos ya están en «' . $choca . '».');
    }

    // ¿Supera lo que la obra declara tener? Es un aviso, no un error:
    // una serie en publicación puede crecer más allá de lo registrado.
    $total = capitulosDeclarados($conexion, $comic_id);
    if ($total !== null && $hasta > $total) {
        return [
            'estado' => 'aviso',
            'mensaje' => "La obra tiene $total capítulos registrados y estás "
                       . "subiendo hasta el $hasta.",
            'desde' => $desde, 'hasta' => $hasta,
        ];
    }

    return ['estado' => 'ok', 'mensaje' => '',
            'desde' => $desde, 'hasta' => $hasta];
}

/**
 * Compone el texto que se muestra, a partir de la numeración.
 *
 * Antes este texto lo escribía la persona a mano y por eso convivían
 * '#1 - #3', '#1' y 'Tomo Unico' sin criterio común.
 */
function etiquetaCapitulo(string $formato, int $desde, int $hasta): string
{
    if ($formato === 'tomo')  return 'Tomo único';
    if ($formato === 'rango') return "#$desde - #$hasta";
    return "#$desde";
}
