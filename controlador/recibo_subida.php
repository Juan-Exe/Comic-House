<?php
require_once __DIR__ . '/validar_capitulo.php';

/** Los datos del intento son inmutables: editar una fila genera otra clave. */
function datosReciboSubida(array $entrada): array
{
    $formato = trim($entrada['formato'] ?? 'grapa');
    $desde = $formato === 'tomo' ? 1 : numeroDeCapitulo($entrada['numero_desde'] ?? '');
    return [
        'comic_id' => (int) ($entrada['comic_id'] ?? 0),
        'titulo' => trim($entrada['titulo'] ?? ''),
        'formato' => $formato,
        'numero_desde' => $desde,
        'numero_hasta' => $formato === 'rango' ? numeroDeCapitulo($entrada['numero_hasta'] ?? '') : $desde,
        'arco' => trim($entrada['arco'] ?? ''),
        'fecha_publicacion' => trim($entrada['fecha_publicacion'] ?? ''),
        'fecha_publicacion_mes' => trim($entrada['fecha_publicacion_mes'] ?? ''),
    ];
}

/** Un recibo solo confirma esta obra, estos datos y este intento. */
function buscarReciboSubida(mysqli $conexion, string $clave, array $entrada): ?array
{
    if ($clave === '') return null;
    $st = $conexion->prepare('SELECT c.*, (SELECT COUNT(*) FROM paginas p WHERE p.capitulo_id=c.id) AS paginas
        FROM capitulos c WHERE c.subida_clave=?');
    $st->bind_param('s', $clave);
    $st->execute();
    $fila = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$fila) return null;
    $fila['coincide'] = true;
    foreach (datosReciboSubida($entrada) as $campo => $valor) {
        if ((string) $fila[$campo] !== (string) $valor) $fila['coincide'] = false;
    }
    return $fila;
}
