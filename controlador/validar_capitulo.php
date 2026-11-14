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

/**
 * ¿El archivo parece de OTRA obra del catálogo?
 *
 * LO QUE ESTO NO ES: una verificación. Un .cbr es un comprimido de
 * imágenes y no lleva dentro el nombre de la obra, así que el sistema
 * NO PUEDE saber que «cap01.cbr» es de Watchmen y no de Berserk.
 *
 * Lo único comprobable es el nombre del archivo. Caso real que motivó
 * esto: se subió «Invencible #1-2.cbr» a Berserk. Solo se detuvo
 * porque esos números ya estaban ocupados; con números libres habría
 * entrado sin una palabra.
 *
 * Por eso devuelve una SOSPECHA, no un error. El administrador puede
 * tener razón y el archivo llamarse como sea.
 *
 * @return array{sospecha: bool, obra: string}
 */
function archivoPareceDeOtraObra(
    mysqli $conexion,
    int $comic_id,
    string $nombreArchivo
): array {
    $nada = ['sospecha' => false, 'obra' => ''];

    // Se compara sin extensión, sin acentos y sin signos: «Invencible
    // #1-2.cbr» y «invencible» tienen que parecerse.
    $plano = function (string $s): string {
        $s = mb_strtolower($s);
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return preg_replace('/[^a-z0-9]+/', ' ', $s);
    };

    $archivo = $plano(pathinfo($nombreArchivo, PATHINFO_FILENAME));
    if (trim($archivo) === '') {
        return $nada;
    }

    $res = $conexion->query("SELECT id, titulo FROM comics");
    if (!$res) {
        return $nada;
    }

    $destino = '';
    $otra    = '';

    while ($c = $res->fetch_assoc()) {
        $t = trim($plano($c['titulo']));
        // Títulos muy cortos generarían coincidencias por casualidad.
        if (mb_strlen($t) < 4) {
            continue;
        }
        // Se busca el título como palabra completa dentro del nombre.
        if (!preg_match('/\b' . preg_quote($t, '/') . '\b/', $archivo)) {
            continue;
        }
        if ((int) $c['id'] === $comic_id) {
            $destino = $c['titulo'];   // menciona el cómic correcto
        } elseif ($otra === '') {
            $otra = $c['titulo'];
        }
    }
    $res->free();

    // Si el nombre menciona el cómic de destino, no hay nada que avisar
    // aunque mencione otro de paso («Berserk vs Invincible.cbr»).
    if ($destino !== '') {
        return $nada;
    }

    if ($otra !== '') {
        return ['sospecha' => true, 'obra' => $otra];
    }

    // SEGUNDA SEÑAL, que no depende del catálogo.
    //
    // Comparar solo contra los títulos registrados deja fuera el caso
    // mas probable: subir una obra que AUN NO ESTA en el catalogo. El
    // caso real fue «Invencible #1-2.cbr» a Berserk, y no salto el aviso
    // porque «Invencible» no es ninguno de los 7 titulos guardados.
    //
    // Asi que si el nombre trae palabras de texto y NINGUNA aparece en
    // el titulo de destino, tambien se avisa. Nombres neutros como
    // «cap01.cbr» o «001.cbz» no disparan nada, porque no tienen
    // palabras que contrastar.
    $st = $conexion->prepare("SELECT titulo FROM comics WHERE id = ?");
    $st->bind_param('i', $comic_id);
    $st->execute();
    $st->bind_result($tituloDestino);
    $hay = $st->fetch();
    $st->close();
    if (!$hay) {
        return $nada;
    }

    $palabrasDestino = array_filter(explode(' ', trim($plano($tituloDestino))),
        fn($w) => mb_strlen($w) >= 4);

    // Palabras del archivo que son texto, no numeracion ni formato.
    //
    // Se separan letras de digitos antes de mirar: «cap01» es una sola
    // palabra de cinco caracteres y pasaba por nombre de obra, cuando en
    // realidad es «cap» + «01», puro ruido.
    $soloTexto = preg_replace('/(\d+)/', ' $1 ', $archivo);
    $ruido = ['cbr', 'cbz', 'cap', 'caps', 'capitulo', 'capitulos', 'chapter',
              'chapters', 'vol', 'volume', 'volumen', 'tomo', 'parte', 'part',
              'issue', 'scan', 'scans', 'esp', 'eng', 'español', 'espanol'];
    $palabrasArchivo = array_filter(explode(' ', trim($soloTexto)), function ($w) use ($ruido) {
        return mb_strlen($w) >= 4 && !in_array($w, $ruido, true) && !ctype_digit($w);
    });

    if (!$palabrasArchivo || !$palabrasDestino) {
        return $nada;   // sin nada que contrastar, no se opina
    }

    foreach ($palabrasArchivo as $w) {
        foreach ($palabrasDestino as $d) {
            if ($w === $d || str_contains($d, $w) || str_contains($w, $d)) {
                return $nada;   // alguna palabra coincide: parece correcto
            }
        }
    }

    // Ninguna palabra del nombre aparece en el titulo de destino.
    return ['sospecha' => true, 'obra' => ''];
}
