<?php
/**
 * Propone entregas sin mezclar series: tomos con rangos, grapas o tomo único.
 * Las fuentes son orientativas; el administrador confirma antes de guardar.
 */
namespace ComicHouse\Tomos;

require_once __DIR__ . '/tomos_fuentes.php';
require_once __DIR__ . '/recopilatorios_fuentes.php';
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);
const CACHE_DIAS_TOMOS = 7;

function responder(array $respuesta): void {
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// Este endpoint no necesita abrir una conexión a la base.
(function () {
    $archivo = dirname(__DIR__) . '/.env';
    if (!is_readable($archivo)) return;
    foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        if ($clave !== '' && getenv($clave) === false) putenv($clave . '=' . trim(trim($valor), "\"'"));
    }
})();

$obra = [
    'titulo'=>trim($_GET['titulo'] ?? ''),
    'editorial'=>trim($_GET['editorial'] ?? ''),
    'anio'=>(int) ($_GET['anio'] ?? 0),
    'tipo'=>trim($_GET['tipo'] ?? 'Comic'),
    'estado'=>trim($_GET['estado'] ?? ''),
    'edicion'=>trim($_GET['edicion'] ?? ''),
];
if (mb_strlen($obra['titulo']) < 2 || mb_strlen($obra['titulo']) > 255
    || mb_strlen($obra['editorial']) > 100 || mb_strlen($obra['edicion']) > 300) {
    responder(['ok'=>false, 'aviso'=>'Revisa el título de la obra.']);
}
$dirCache = dirname(__DIR__) . '/cache/wiki';
if (!is_dir($dirCache)) @mkdir($dirCache, 0775, true);
$archivoCache = $dirCache . '/' . claveCache($obra) . '.json';
function leerCache(string $archivo): ?array {
    if (!is_readable($archivo) || time() - filemtime($archivo) >= CACHE_DIAS_TOMOS * 86400) return null;
    $r = json_decode(file_get_contents($archivo), true);
    return is_array($r) && !empty($r['ok']) ? $r : null;
}
if ($guardado = leerCache($archivoCache)) {
    $guardado['cacheado'] = true;
    responder($guardado);
}

$tomos = [];
$fuente = '';
$ediciones = [];
$edicion = null;
$sinSeleccion = $obra;
$sinSeleccion['edicion'] = '';
$previa = leerCache($dirCache . '/' . claveCache($sinSeleccion) . '.json');
// Reutiliza las opciones ya consultadas al elegir edición.
if ($obra['edicion'] !== '' && !empty($previa['datos']['ediciones'])) {
    $ediciones = $previa['datos']['ediciones'];
} else {
    foreach (wikisDe($obra['titulo'], $obra['editorial']) as $wiki) {
        if ($obra['tipo'] === 'Manga') {
            foreach (['Releases (Manga)', 'Releases', 'Volumes', "List of {$obra['titulo']} volumes", 'Manga'] as $pagina) {
                $tomos = tomosDeVolinfo(wikitexto($wiki, $pagina));
                if ($tomos) {
                    $fuente = "$wiki.fandom.com · $pagina";
                    break 2;
                }
            }
        } else {
            if ($obra['estado'] === 'Tomo unico') {
                $tomos = tomoUnicoWiki($wiki, $obra['titulo'], $obra['anio']);
                if ($tomos) { $fuente = "$wiki.fandom.com"; break; }
            }
            $ediciones = edicionesWiki($wiki, $obra['titulo']);
            if ($ediciones) break;
        }
    }
    if (!$tomos && !$ediciones && $obra['tipo'] !== 'Manga') {
        $ediciones = edicionesComicVine($obra['titulo']);
    }
}

if ($ediciones) {
    $edicion = elegirEdicion($ediciones, $obra['anio'], $obra['editorial'], $obra['edicion']);
    if ($edicion === null) {
        $respuesta = ['ok'=>true, 'datos'=>['tomos'=>[], 'ediciones'=>$ediciones,
            'requiere_edicion'=>true, 'total'=>0],
            'aviso'=>'La edición no se puede identificar con los datos del catálogo. Elige la que vas a subir.'];
        @file_put_contents($archivoCache, json_encode($respuesta, JSON_UNESCAPED_UNICODE), LOCK_EX);
        responder($respuesta);
    }
    $fuente = $edicion['fuente'];
    $tomos = isset($edicion['volume_id'])
        ? numerosComicVine($edicion, $obra['estado']) : numerosDeEdicion($edicion);
}
if (!$tomos) {
    responder(['ok'=>false, 'aviso'=>'No se encontró una lista utilizable de esta obra. Puedes completar los datos a mano.']);
}

// Complemento autorizado: nombre del recopilatorio, separado del título individual.
if (array_filter($tomos, fn($t) => $t['formato'] === 'grapa' && trim($t['titulo'] ?? '') === '' && trim($t['arco'] ?? '') === '')) {
    $serie = preg_replace('/\s+Vol\s+\d+$/i', '', $edicion['serie'] ?? $obra['titulo']);
    $tomos = completarRecopilatorios($tomos, buscarRecopilatorios($serie, $edicion['anio'] ?? $obra['anio']));
}

// Traducción opcional: nunca inventa títulos donde la fuente no publica uno.
$distintos = [];
foreach ($tomos as $t) {
    foreach ([$t['titulo'] ?? '', $t['arco'] ?? '', $t['recopilatorio']['titulo'] ?? ''] as $titulo) {
        if ($titulo !== '') $distintos[$titulo] = true;
    }
}
$traducciones = [];
foreach (array_chunk(array_keys($distintos), 35) as $lote) {
    $traducciones += traducirArcos($lote);
}
foreach ($tomos as &$t) {
    $t['titulo_original'] = $t['titulo'] ?? '';
    if (isset($t['recopilatorio'])) {
        $original = $t['recopilatorio']['titulo'];
        $t['recopilatorio']['titulo_original'] = $original;
        $t['recopilatorio']['titulo'] = $traducciones[$original] ?? $original;
        $t['recopilatorio']['traducido'] = $t['recopilatorio']['titulo'] !== $original;
    }
    foreach (['titulo', 'arco'] as $campo) {
        $original = $t[$campo] ?? '';
        $t[$campo] = isset($traducciones[$original]) ? trim($traducciones[$original]) : $original;
    }
    $t['fecha_mes'] = $t['fecha_mes'] ?? ($t['fecha'] !== '' ? substr($t['fecha'], 0, 7) : '');
}
unset($t);

$respuesta = ['ok'=>true, 'datos'=>['tomos'=>$tomos, 'fuente'=>$fuente,
    'total'=>count($tomos), 'edicion'=>$edicion, 'ediciones'=>$ediciones,
    'requiere_edicion'=>false],
    'aviso'=>'Entregas sugeridas por ' . $fuente . '. Revisa la edición, la numeración y los datos antes de guardar.'];
@file_put_contents($archivoCache, json_encode($respuesta, JSON_UNESCAPED_UNICODE), LOCK_EX);
responder($respuesta);
