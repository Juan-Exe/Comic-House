<?php
require_once __DIR__ . '/archivo_capitulo.php';
require_once __DIR__ . '/validar_capitulo.php';

/** Metadatos y páginas forman la versión que el administrador está revisando. */
function estadoReemplazo(mysqli $conexion, int $id, bool $bloquear = false): ?array {
    $st = $conexion->prepare('SELECT * FROM capitulos WHERE id=?' . ($bloquear ? ' FOR UPDATE' : ''));
    $st->bind_param('i',$id); $st->execute(); $cap = $st->get_result()->fetch_assoc(); $st->close();
    if (!$cap) return null;
    $st = $conexion->prepare('SELECT * FROM paginas WHERE capitulo_id=? ORDER BY orden,id');
    $st->bind_param('i',$id); $st->execute(); $paginas = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    return ['capitulo'=>$cap,'paginas'=>$paginas,'version'=>hash('sha256',json_encode([$cap,$paginas],JSON_UNESCAPED_UNICODE))];
}

/** Retira únicamente imágenes antiguas sin referencias, dentro de paginas/. */
function limpiarReemplazo(mysqli $conexion, array $recibo): bool {
    $pendientes = json_decode($recibo['pendientes'] ?? '[]',true) ?: [];
    $faltan = [];
    $raiz = realpath(dirname(__DIR__) . '/paginas');
    foreach ($pendientes as $url) {
        if (!$raiz || !is_string($url) || !str_starts_with($url,'paginas/')
            || str_contains($url,'\\') || preg_match('#(?:^|/)\.\.(?:/|$)#',$url)) { $faltan[]=$url; continue; }
        $ruta = dirname(__DIR__) . '/' . $url;
        if (!file_exists($ruta)) continue;
        $real = realpath($ruta);
        if (!$real || is_link($ruta) || !is_file($real) || !str_starts_with($real,$raiz . DIRECTORY_SEPARATOR)) { $faltan[]=$url; continue; }
        $st = $conexion->prepare('SELECT COUNT(*) FROM paginas WHERE imagen_url=?');
        $st->bind_param('s',$url); $st->execute(); $referencias=(int)$st->get_result()->fetch_row()[0]; $st->close();
        if ($referencias > 0) continue; // Una imagen compartida sigue en uso.
        if (!@unlink($real) && file_exists($real)) { $faltan[]=$url; continue; }
        $carpeta=dirname($real);
        if ($carpeta !== $raiz && str_starts_with($carpeta,$raiz . DIRECTORY_SEPARATOR)) @rmdir($carpeta);
    }
    $json = $faltan ? json_encode($faltan,JSON_UNESCAPED_UNICODE) : null;
    $st=$conexion->prepare('UPDATE reemplazos_capitulo SET pendientes=? WHERE id=?');
    $st->bind_param('si',$json,$recibo['id']); $st->execute(); $st->close();
    return !$faltan;
}

function reciboReemplazo(mysqli $conexion, string $clave): ?array {
    $st=$conexion->prepare('SELECT * FROM reemplazos_capitulo WHERE clave=?');
    $st->bind_param('s',$clave); $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
    return $r ?: null;
}

function limpiarReemplazosPendientes(mysqli $conexion, int $id): void {
    try {
        $filas=$conexion->query('SELECT * FROM reemplazos_capitulo WHERE capitulo_id=' . $id . ' AND pendientes IS NOT NULL')->fetch_all(MYSQLI_ASSOC);
        foreach ($filas as $r) if (!limpiarReemplazo($conexion,$r)) error_log('Reemplazo: quedan archivos por retirar, recibo ' . $r['id']);
    } catch (Throwable $e) { error_log('Limpieza tras reemplazo: ' . $e->getMessage()); }
}

final class ReemplazoEnConflicto extends RuntimeException {}
final class ReemplazoNombreDistinto extends RuntimeException {}

/** Devuelve un recibo repetible sin sobrescribir una revisión más reciente. */
function comprobarReciboReemplazo(array $recibo, int $id, string $version, string $sha, array $estado): array {
    if ((int)$recibo['capitulo_id'] !== $id || !hash_equals($recibo['version_anterior'],$version)
        || !hash_equals($recibo['archivo_sha256'],$sha)) throw new ReemplazoEnConflicto('Este intento corresponde a otro archivo o revisión. Vuelve a cargar el capítulo.');
    if (!hash_equals($recibo['version_nueva'],$estado['version'])) throw new ReemplazoEnConflicto('El reemplazo se completó, pero el capítulo cambió después. Recarga para ver la versión actual.');
    return ['ok'=>true,'ya_reemplazado'=>true,'capitulo_id'=>$id,'comic_id'=>(int)$estado['capitulo']['comic_id'],'paginas'=>(int)$recibo['paginas']];
}

function reemplazarArchivoCapitulo(mysqli $conexion, int $id, string $version, string $clave, array $archivo, bool $confirmarObra = false): array {
    if (!preg_match('/^[a-f0-9]{32}$/D',$clave) || !preg_match('/^[a-f0-9]{64}$/D',$version)) throw new ReemplazoEnConflicto('Recarga el capítulo antes de reemplazarlo.');
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) throw new ArchivoCapituloInvalido('Elige un CBR o CBZ completo para reemplazar las páginas.');
    if (filesize($archivo['tmp_name']) > 512*1024*1024) throw new ArchivoCapituloInvalido('El archivo supera los 512 MB admitidos.');
    $sha=hash_file('sha256',$archivo['tmp_name']);
    // Estado y recibo pertenecen a la misma instantánea aunque otro cliente
    // confirme un reemplazo entre las consultas.
    $conexion->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    try {
        $estado=estadoReemplazo($conexion,$id);
        $recibo=reciboReemplazo($conexion,$clave);
    } finally { $conexion->commit(); }
    if (!$estado) throw new ReemplazoEnConflicto('El capítulo ya no existe.');
    if ($recibo) {
        $respuesta=comprobarReciboReemplazo($recibo,$id,$version,$sha,$estado);
        limpiarReemplazosPendientes($conexion,$id);
        return $respuesta;
    }
    if (!hash_equals($estado['version'],$version)) throw new ReemplazoEnConflicto('El capítulo cambió desde que abriste la página. Recarga y revisa sus datos.');
    if (!$confirmarObra && archivoPareceDeOtraObra($conexion,(int)$estado['capitulo']['comic_id'],$archivo['name'])['sospecha']) {
        throw new ReemplazoNombreDistinto('El nombre «' . $archivo['name'] . '» puede pertenecer a otra obra. Comprueba que es el archivo correcto para este capítulo.');
    }
    $preparado=null; $destino=null; $confirmado=false; $transaccion=false;
    register_shutdown_function(static function () use (&$preparado,&$destino,&$confirmado) {
        if ($preparado) borrarTemporalCapitulo($preparado['directorio']);
        if ($destino && !$confirmado) borrarTemporalCapitulo($destino);
    });
    try {
        $preparado=prepararArchivoCapitulo($archivo['tmp_name'],$archivo['name']);
        $conexion->begin_transaction(); $transaccion=true;
        $estado=estadoReemplazo($conexion,$id,true);
        if (!$estado) throw new ReemplazoEnConflicto('El capítulo ya no existe.');
        // Dos reintentos simultáneos con la misma clave reciben el mismo resultado.
        $recibo=reciboReemplazo($conexion,$clave);
        if ($recibo) {
            $respuesta=comprobarReciboReemplazo($recibo,$id,$version,$sha,$estado);
            $conexion->rollback(); $transaccion=false;
            limpiarReemplazosPendientes($conexion,$id);
            return $respuesta;
        }
        if (!hash_equals($estado['version'],$version)) throw new ReemplazoEnConflicto('El capítulo cambió mientras se procesaba el archivo. Recarga y revisa la versión actual.');
        $carpeta='cap_' . $id . '_r_' . bin2hex(random_bytes(16));
        $ruta=dirname(__DIR__) . '/paginas/' . $carpeta;
        if (!mkdir($ruta,0755)) throw new RuntimeException('No se pudieron preparar las páginas nuevas.');
        $destino=$ruta;
        $filas=[];
        foreach ($preparado['paginas'] as $i=>$pagina) {
            $ruta=$destino . '/' . $pagina['nombre'];
            if (!rename($pagina['ruta'],$ruta) || !chmod($ruta,0644)) throw new RuntimeException('No se pudieron guardar todas las páginas nuevas.');
            $url=$conexion->real_escape_string('paginas/' . $carpeta . '/' . $pagina['nombre']);
            $orden=$i+1;
            $filas[]="($id,'$url',$orden,{$pagina['es_doble']})";
        }
        // Primero desaparecen TODAS las filas antiguas, luego se insertan las nuevas.
        $st=$conexion->prepare('DELETE FROM paginas WHERE capitulo_id=?');
        $st->bind_param('i',$id); $st->execute(); $st->close();
        foreach (array_chunk($filas,100) as $lote) $conexion->query('INSERT INTO paginas(capitulo_id,imagen_url,orden,es_doble) VALUES ' . implode(',',$lote));
        $st=$conexion->prepare('UPDATE capitulos SET archivo_sha256=?,subida_clave=NULL WHERE id=?');
        $st->bind_param('si',$sha,$id); $st->execute(); $st->close();
        $nueva=estadoReemplazo($conexion,$id);
        $pendientes=json_encode(array_column($estado['paginas'],'imagen_url'),JSON_UNESCAPED_UNICODE);
        $cantidad=count($filas);
        $st=$conexion->prepare('INSERT INTO reemplazos_capitulo(clave,capitulo_id,version_anterior,version_nueva,archivo_sha256,paginas,pendientes) VALUES(?,?,?,?,?,?,?)');
        $st->bind_param('sisssis',$clave,$id,$version,$nueva['version'],$sha,$cantidad,$pendientes); $st->execute(); $st->close();
        $conexion->commit(); $transaccion=false; $confirmado=true;
        // Los archivos antiguos solo se retiran después del commit; un rollback los conserva.
        limpiarReemplazosPendientes($conexion,$id);
        return ['ok'=>true,'capitulo_id'=>$id,'comic_id'=>(int)$estado['capitulo']['comic_id'],'paginas'=>$cantidad];
    } finally {
        if ($transaccion) $conexion->rollback();
        if ($preparado) borrarTemporalCapitulo($preparado['directorio']);
        if ($destino && !$confirmado) borrarTemporalCapitulo($destino);
    }
}
