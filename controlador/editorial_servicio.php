<?php
namespace ComicHouse\Editoriales;
require_once __DIR__ . '/validar_editorial.php';
require_once __DIR__ . '/logo_editorial.php';

function consultarEditorial(\mysqli $conexion, string $nombre): array
{
    $nombre = \normalizarEditorial($nombre);
    if ($nombre === '' || \clave_editorial($nombre) === '' || mb_strlen($nombre) > 100) throw new \RuntimeException('Escribe un nombre de editorial de hasta 100 caracteres.');
    $revision = \revisarEditorial($conexion, $nombre);
    $sugerida = null;
    if ($revision['estado'] !== 'ok') {
        $st = $conexion->prepare('SELECT id,nombre,imagen FROM editoriales WHERE nombre=?');
        $st->bind_param('s', $revision['sugerencia']);
        $st->execute();
        $sugerida = $st->get_result()->fetch_assoc();
        $st->close();
        if ($sugerida) $sugerida['id'] = (int) $sugerida['id'];
    }
    return ['estado'=>$revision['estado'] === 'duplicada' ? 'existente' :
            ($revision['estado'] === 'sugerencia' && \nombreCanonicoEditorial($nombre) === $nombre ? 'parecida' : 'nueva'),
        'nombre'=>\nombreCanonicoEditorial($nombre), 'editorial'=>$revision['estado'] === 'duplicada' ? $sugerida : null,
        'sugerencia'=>$sugerida ?: ['nombre'=>$revision['sugerencia']]];
}

function altaEditorial(\mysqli $conexion, array $datos, array $archivo = []): array
{
    $revision = consultarEditorial($conexion, $datos['nombre'] ?? '');
    if ($revision['estado'] === 'existente') return ['ok'=>true,'reutilizada'=>true] + $revision['editorial'];
    if ($revision['estado'] === 'parecida' && ($datos['confirmar'] ?? '') !== '1') {
        return ['ok'=>false,'error'=>'parecida','aviso'=>'Hay una editorial parecida. Revisa si es la misma antes de crear otra.'] + $revision;
    }
    $png = prepararLogo($archivo, trim($datos['logo_url'] ?? ''));
    if ($png === null && ($datos['exigir_logo'] ?? '') === '1') throw new \RuntimeException('Elige un logo de la fuente o sube una imagen.');
    $imagen = '';
    $bloqueo = false;
    try {
        $bloqueo = (int) $conexion->query("SELECT GET_LOCK('comic_house_editoriales',10)")->fetch_row()[0] === 1;
        if (!$bloqueo) throw new \RuntimeException('Otra operación está guardando una editorial. Vuelve a intentarlo.');
        $actual = consultarEditorial($conexion, $revision['nombre']);
        if ($actual['estado'] === 'existente') return ['ok'=>true,'reutilizada'=>true] + $actual['editorial'];
        if ($actual['estado'] === 'parecida' && ($datos['confirmar'] ?? '') !== '1') {
            return ['ok'=>false,'error'=>'parecida','aviso'=>'Se registró una editorial parecida. Revisa el nombre.'] + $actual;
        }
        if ($png !== null) $imagen = guardarLogo($png);
        $st = $conexion->prepare("INSERT INTO editoriales(nombre,imagen,destacada) VALUES(?,?,0)");
        $st->bind_param('ss', $revision['nombre'], $imagen);
        $st->execute();
        $id = $conexion->insert_id;
        $st->close();
        if (!$id) throw new \RuntimeException('No se pudo guardar la editorial.');
        return ['ok'=>true,'reutilizada'=>false,'id'=>$id,'nombre'=>$revision['nombre'],'imagen'=>$imagen];
    } catch (\Throwable $e) {
        if ($imagen !== '') retirarLogoNuevo($imagen);
        throw $e;
    } finally {
        if ($bloqueo) $conexion->query("SELECT RELEASE_LOCK('comic_house_editoriales')");
    }
}
