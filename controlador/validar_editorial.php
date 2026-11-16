<?php
/**
 * validar_editorial.php - Detecta editoriales mal escritas antes de
 * guardarlas.
 *
 * El problema: si alguien registra "Marvle" o "marvel comics ", entra
 * como una editorial NUEVA y el catalogo queda partido en dos entradas
 * que son la misma empresa. La clave foranea garantiza que la editorial
 * exista, pero no que sea la que la persona queria.
 *
 * La comprobacion es LOCAL y determinista: se contrasta contra un
 * catalogo de editoriales conocidas y contra las ya registradas, usando
 * distancia de Levenshtein. No consulta servicios externos, de modo que
 * el resultado es siempre el mismo y no depende de que haya conexion.
 */

/**
 * Editoriales reales del sector, con las formas en que suele
 * escribirse cada una. Es la "autoridad" contra la que se compara.
 *
 * El valor es el nombre canonico; las claves del array interno son
 * variantes aceptadas que se corrigen sin preguntar.
 */
const CATALOGO_EDITORIALES = [
    'Marvel Comics'     => ['marvel', 'marvel comics', 'marvel comic'],
    'DC'                => ['dc', 'dc comics'],
    'Image Comics'      => ['image', 'image comics'],
    'Dark Horse'        => ['dark horse', 'dark horse comics', 'darkhorse'],
    'Norma Editorial'   => ['norma', 'norma editorial'],
    'Young Animal'      => ['young animal', 'younganimal'],
    'Panini Comics'     => ['panini', 'panini comics'],
    'IDW Publishing'    => ['idw', 'idw publishing'],
    'Boom! Studios'     => ['boom', 'boom studios', 'boom! studios'],
    'Dynamite'          => ['dynamite', 'dynamite entertainment'],
    'Vertigo'           => ['vertigo', 'dc vertigo'],
    'Shueisha'          => ['shueisha'],
    'Kodansha'          => ['kodansha'],
    'Shogakukan'        => ['shogakukan'],
    'Square Enix'       => ['square enix', 'squareenix'],
    'Planeta Comic'     => ['planeta', 'planeta comic', 'planeta de agostini'],
    'ECC Ediciones'     => ['ecc', 'ecc ediciones'],
    'Ivrea'             => ['ivrea', 'editorial ivrea'],
];

/**
 * Deja el nombre en su forma canonica de escritura: sin espacios
 * sobrantes, sin espacios dobles internos.
 *
 * Esto es NORMALIZAR, no corregir: no cambia el valor, solo su formato,
 * asi que es seguro aplicarlo sin preguntar.
 */
function normalizarEditorial(string $nombre): string
{
    $nombre = trim($nombre);
    return (string) preg_replace('/\s+/u', ' ', $nombre);
}

/**
 * Reduce un nombre a una forma comparable: minusculas, sin acentos y
 * sin signos. Sirve para que "Boom! Studios" y "boom studios" se
 * reconozcan como el mismo texto.
 */
function clave_editorial(string $nombre): string
{
    $n = mb_strtolower(normalizarEditorial($nombre), 'UTF-8');
    $n = strtr($n, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o',
        'ù' => 'u', 'û' => 'u', 'ç' => 'c',
    ]);
    return (string) preg_replace('/[^\p{L}\p{N} ]/u', '', $n);
}

/** Un alias conocido identifica la misma editorial; una semejanza no lo hace. */
function nombreCanonicoEditorial(string $nombre): string
{
    $nombre = normalizarEditorial($nombre);
    $clave = clave_editorial($nombre);
    foreach (CATALOGO_EDITORIALES as $canonico => $variantes) {
        foreach (array_merge([$canonico], $variantes) as $variante) {
            if ($clave === clave_editorial($variante)) return $canonico;
        }
    }
    return $nombre;
}

/**
 * ¿Son dos textos iguales salvo por dos letras contiguas intercambiadas?
 *
 * Levenshtein cobra 2 por una transposicion ("marvle" dista 2 de
 * "marvel"), igual que por dos errores independientes. Pero cambiar el
 * orden de dos teclas es el error de escritura mas frecuente, asi que
 * se reconoce aparte en vez de subir el umbral y aceptar de paso
 * nombres que si son distintos.
 */
function es_transposicion(string $a, string $b): bool
{
    if (strlen($a) !== strlen($b) || $a === $b) {
        return false;
    }
    $dif = [];
    for ($i = 0, $n = strlen($a); $i < $n; $i++) {
        if ($a[$i] !== $b[$i]) {
            $dif[] = $i;
            if (count($dif) > 2) {
                return false;
            }
        }
    }
    return count($dif) === 2
        && $dif[1] === $dif[0] + 1
        && $a[$dif[0]] === $b[$dif[1]]
        && $a[$dif[1]] === $b[$dif[0]];
}

/**
 * Busca el nombre mas parecido dentro de una lista de candidatos.
 *
 * Devuelve [nombre, distancia] o null si ninguno se acerca lo
 * suficiente. El umbral crece con el largo del nombre: en una palabra
 * corta, dos letras de diferencia ya son otra editorial.
 */
function mas_parecida(string $nombre, array $candidatos): ?array
{
    $clave = clave_editorial($nombre);
    $largo = strlen($clave);
    if ($largo === 0) {
        return null;
    }

    // Hasta 8 caracteres se tolera 1 error; por encima, 2. Las
    // transposiciones se admiten siempre, por frecuentes.
    $umbral = $largo <= 8 ? 1 : 2;

    $mejor = null;
    foreach ($candidatos as $candidato) {
        $clave_c = clave_editorial($candidato);
        $d = levenshtein($clave, $clave_c);

        if ($d === 0) {
            return [$candidato, 0];
        }
        // Una transposicion se trata como un unico error.
        if (es_transposicion($clave, $clave_c)) {
            $d = 1;
        }
        if ($d <= $umbral && ($mejor === null || $d < $mejor[1])) {
            $mejor = [$candidato, $d];
        }
    }
    return $mejor;
}

/**
 * Dictamina si un nombre de editorial puede guardarse tal cual.
 *
 * Devuelve un array con:
 *   estado = 'ok'          -> no se parece a nada, se guarda
 *            'duplicada'   -> ya esta registrada (quiza con otra grafia)
 *            'sugerencia'  -> se parece a una conocida; hay que confirmar
 *   sugerencia -> el nombre canonico propuesto
 */
function revisarEditorial(mysqli $conexion, string $nombre): array
{
    $nombre = normalizarEditorial($nombre);
    $clave = clave_editorial($nombre);

    // 1. ¿Ya existe en la base, aunque este escrita distinto?
    $registradas = [];
    $res = $conexion->query("SELECT nombre FROM editoriales");
    while ($fila = $res->fetch_assoc()) {
        $registradas[] = $fila['nombre'];
    }

    foreach ($registradas as $ya) {
        if (clave_editorial(nombreCanonicoEditorial($ya)) === clave_editorial(nombreCanonicoEditorial($nombre))) {
            return ['estado' => 'duplicada', 'sugerencia' => $ya];
        }
    }

    $parecida = mas_parecida($nombre, $registradas);
    if ($parecida !== null) {
        return ['estado' => 'sugerencia', 'sugerencia' => $parecida[0]];
    }

    // 2. ¿Coincide con una variante conocida del catalogo?
    foreach (CATALOGO_EDITORIALES as $canonico => $variantes) {
        foreach ($variantes as $variante) {
            if (clave_editorial($variante) === $clave) {
                // Es la misma editorial escrita de otra forma: se propone
                // el nombre canonico en lugar del que se escribio.
                return $canonico === $nombre
                    ? ['estado' => 'ok', 'sugerencia' => $nombre]
                    : ['estado' => 'sugerencia', 'sugerencia' => $canonico];
            }
        }
    }

    // 3. ¿Se parece a alguna conocida, con un error de escritura?
    $conocidas = array_keys(CATALOGO_EDITORIALES);
    foreach (CATALOGO_EDITORIALES as $canonico => $variantes) {
        foreach ($variantes as $variante) {
            $conocidas[] = $variante;
        }
    }

    $parecida = mas_parecida($nombre, $conocidas);
    if ($parecida !== null) {
        // Se devuelve siempre el nombre canonico, no la variante.
        $canonico = $parecida[0];
        foreach (CATALOGO_EDITORIALES as $nom => $variantes) {
            if ($nom === $canonico || in_array($canonico, $variantes, true)) {
                $canonico = $nom;
                break;
            }
        }
        return ['estado' => 'sugerencia', 'sugerencia' => $canonico];
    }

    // 4. No se parece a nada conocido: puede ser una editorial legitima
    //    que no esta en el catalogo, asi que se acepta.
    return ['estado' => 'ok', 'sugerencia' => $nombre];
}
