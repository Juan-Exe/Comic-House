<?php
session_start();
@ini_set('upload_max_filesize', '512M');
@ini_set('post_max_size', '512M');
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300');
include("../modelo/conexion.php");

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/validar_capitulo.php';
require_once __DIR__ . '/fecha_capitulo.php';
require_once __DIR__ . '/archivo_capitulo.php';
require_once __DIR__ . '/recibo_subida.php';

$comic_id = intval($_POST['comic_id'] ?? 0);
$titulo   = trim($_POST['titulo'] ?? '');
$fecha    = trim($_POST['fecha_publicacion'] ?? '');
$fecha_mes = trim($_POST['fecha_publicacion_mes'] ?? '');
$formato  = trim($_POST['formato'] ?? 'grapa');
$arco     = trim($_POST['arco'] ?? '');

header('Content-Type: application/json');
$clave = trim($_POST['subida_clave'] ?? '');
if ($clave !== '' && !preg_match('/^[a-f0-9]{32}$/D', $clave)) {
    echo json_encode(['ok' => false, 'error' => 'intento', 'mensaje' => 'El identificador del intento no es válido.']);
    exit();
}
if ($titulo === '' || mb_strlen($titulo) > 255) {
    echo json_encode(['ok' => false, 'error' => 'titulo', 'mensaje' => 'Escribe un título de hasta 255 caracteres.']);
    exit();
}

// El tipo de obra decide qué numeración tiene sentido.
$obra = $conexion->prepare("SELECT tipo FROM comics WHERE id = ?");
$obra->bind_param('i', $comic_id);
$obra->execute();
$obra->bind_result($tipo_obra);
$existe = $obra->fetch();
$obra->close();

if (!$existe) {
    echo json_encode(['ok' => false, 'error' => 'comic_inexistente']);
    exit();
}

// La numeración se valida antes de tocar nada: dígitos, sin ceros a
// la izquierda, rango coherente y sin pisar lo ya subido.
// La fecha es opcional, pero si viene tiene que ser una fecha REAL.
//
// No se validaba nada: iba de $_POST directa al INSERT. El navegador
// tampoco ayudaba, porque new Date('2000-02-30') no falla en
// JavaScript, se desborda al 1 de marzo. Aqui se comprueba que el dia
// que se escribio es el mismo que queda despues de interpretarlo.
$validacionFecha = validarFechaCapitulo($fecha, $fecha_mes);
if (!$validacionFecha['ok']) {
    echo json_encode(['ok'=>false, 'error'=>'fecha', 'mensaje'=>$validacionFecha['mensaje']], JSON_UNESCAPED_UNICODE);
    exit();
}

// Una respuesta perdida no obliga a volver a crear un capítulo ya confirmado.
// El recibo exige los mismos datos Y el mismo contenido, no solo el nombre.
$recibo = buscarReciboSubida($conexion, $clave, $_POST);
if ($recibo) {
    $mismoArchivo = ($_FILES['cbr_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && hash_equals((string) $recibo['archivo_sha256'], hash_file('sha256', $_FILES['cbr_file']['tmp_name']));
    if ($recibo['coincide'] && $mismoArchivo) {
        echo json_encode(['ok' => true, 'ya_subido' => true, 'comic_id' => $comic_id,
            'capitulo_id' => (int) $recibo['id'], 'paginas' => (int) $recibo['paginas']]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'intento',
            'mensaje' => 'Este intento ya guardó otro archivo o datos distintos. Revisa la entrega.']);
    }
    exit();
}

$dictamen = revisarCapitulo(
    $conexion, $comic_id, $tipo_obra, $formato,
    $_POST['numero_desde'] ?? '', $_POST['numero_hasta'] ?? ''
);

if ($dictamen['estado'] === 'error') {
    echo json_encode(['ok' => false, 'error' => 'numeracion',
                      'mensaje' => $dictamen['mensaje']]);
    exit();
}

$desde = $dictamen['desde'];
$hasta = $dictamen['hasta'];
$numero = etiquetaCapitulo($formato, $desde, $hasta);

// Los errores de PHP al recibir el archivo tampoco pueden crear un capítulo.
$errorArchivo = $_FILES['cbr_file']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($errorArchivo !== UPLOAD_ERR_OK || empty($_FILES['cbr_file']['tmp_name'])) {
    echo json_encode(['ok' => false, 'error' => 'sin_archivo', 'mensaje' =>
        in_array($errorArchivo, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'El archivo supera el tamaño permitido por el servidor.'
            : 'No se recibió el archivo completo. Elige un CBR o CBZ y vuelve a intentarlo.'], JSON_UNESCAPED_UNICODE);
    exit();
}
if ($dictamen['estado'] === 'aviso' && ($_POST['confirmar_rango'] ?? '') !== '1') {
    echo json_encode(['ok' => false, 'error' => 'fuera_rango',
        'mensaje' => $dictamen['mensaje']], JSON_UNESCAPED_UNICODE);
    exit();
}

$archivo_tmp  = $_FILES['cbr_file']['tmp_name'];

// ¿El nombre del archivo menciona OTRA obra del catálogo?
//
// Caso real: se subió «Invencible #1-2.cbr» a Berserk. Solo se detuvo
// porque esos números ya estaban ocupados; con números libres habría
// entrado sin avisar.
//
// Es una sospecha por el NOMBRE, no una comprobación del contenido: un
// .cbr no dice de qué obra es. Por eso se pide confirmación en vez de
// bloquear — el archivo puede llamarse como sea y estar bien.
$sosp = archivoPareceDeOtraObra($conexion, $comic_id, $_FILES['cbr_file']['name']);
if ($sosp['sospecha'] && ($_POST['confirmar_obra'] ?? '') !== '1') {
    echo json_encode([
        'ok'      => false,
        'error'   => 'posible_obra_distinta',
        'obra'    => $sosp['obra'],
        // Dos mensajes: uno cuando el nombre menciona otra obra del
        // catalogo, y otro cuando simplemente no se parece al destino
        // (el caso de subir una obra que aun no esta registrada).
        'mensaje' => $sosp['obra'] !== ''
            ? 'El archivo se llama «' . $_FILES['cbr_file']['name']
              . '» y en el catálogo existe «' . $sosp['obra']
              . '». Comprueba que es el cómic correcto.'
            : 'El archivo se llama «' . $_FILES['cbr_file']['name']
              . '» y no se parece al cómic elegido. Comprueba que es el correcto.',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Validar y extraer antes de crear registros o publicar las páginas.
$preparado = null;
$paginas_dir = null;
$publicado = false;
$transaccion = false;
// También limpia si la petición termina por tiempo o por un error fatal.
register_shutdown_function(function () use (&$preparado, &$paginas_dir, &$publicado) {
    if ($preparado) borrarTemporalCapitulo($preparado['directorio']);
    if (!$publicado && $paginas_dir) borrarTemporalCapitulo($paginas_dir);
});
try {
    $preparado = prepararArchivoCapitulo($archivo_tmp, $_FILES['cbr_file']['name']);
    $sha256 = $clave !== '' ? hash_file('sha256', $archivo_tmp) : null;
    $clave_val = $clave !== '' ? $clave : null;
    $conexion->begin_transaction();
    $transaccion = true;
    $stmt = $conexion->prepare(
        "INSERT INTO capitulos (comic_id,titulo,numero,fecha_publicacion,fecha_publicacion_mes,
            formato,numero_desde,numero_hasta,arco,subida_clave,archivo_sha256) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $fecha_val = $validacionFecha['fecha'];
    $mes_val = $validacionFecha['mes'];
    $arco_val = $arco !== '' ? $arco : null;
    $stmt->bind_param('isssssiisss', $comic_id, $titulo, $numero, $fecha_val, $mes_val,
        $formato, $desde, $hasta, $arco_val, $clave_val, $sha256);
    $stmt->execute();
    $capitulo_id = $conexion->insert_id;
    $stmt->close();
    if ($capitulo_id <= 0) throw new RuntimeException('No se pudo guardar el capítulo.');

    $destino = dirname(__DIR__) . '/paginas/cap_' . $capitulo_id;
    // Nunca reutiliza ni elimina una carpeta preexistente.
    if (file_exists($destino) || !mkdir($destino, 0755)) {
        throw new RuntimeException('No se pudieron guardar las páginas.');
    }
    $paginas_dir = $destino;
    $filas = [];
    foreach ($preparado['paginas'] as $i => $pagina) {
        $ruta = $destino . '/' . $pagina['nombre'];
        if (!rename($pagina['ruta'], $ruta) || !chmod($ruta, 0644)) {
            throw new RuntimeException('No se pudieron guardar las páginas.');
        }
        $url = $conexion->real_escape_string('paginas/cap_' . $capitulo_id . '/' . $pagina['nombre']);
        $orden = $i + 1;
        $filas[] = "($capitulo_id, '$url', $orden, {$pagina['es_doble']})";
    }
    // Conserva la inserción por lotes de 100; ahora incluye también al capítulo.
    foreach (array_chunk($filas, 100) as $lote) {
        $conexion->query('INSERT INTO paginas (capitulo_id,imagen_url,orden,es_doble) VALUES '
            . implode(',', $lote));
    }
    $conexion->commit();
    $transaccion = false;
    $publicado = true;
    echo json_encode(['ok' => true, 'comic_id' => $comic_id, 'capitulo_id' => $capitulo_id, 'paginas' => count($filas)]);
} catch (Throwable $e) {
    if ($transaccion) $conexion->rollback();
    $duplicado = $e instanceof mysqli_sql_exception && in_array($e->getCode(), [1062, 1644], true);
    $invalido = $e instanceof ArchivoCapituloInvalido;
    if (!$duplicado && !$invalido) error_log('Subida de capítulo: ' . $e->getMessage());
    echo json_encode(['ok' => false,
        'error' => $invalido ? 'archivo_invalido' : ($duplicado ? 'duplicado' : 'fallo_insercion_paginas'),
        'mensaje' => $invalido ? $e->getMessage() : ($duplicado
            ? 'Ese capítulo ya está subido en esta obra.'
            : 'No se pudo guardar el capítulo completo. No se conservó ninguna página; puedes reintentarlo.'),
    ], JSON_UNESCAPED_UNICODE);
}
exit();
