<?php
namespace ComicHouse\Editoriales;

const MAX_LOGO_BYTES = 5 * 1024 * 1024;
const MAX_LOGO_PIXELES = 16000000;

/** Las imágenes propuestas siempre se descargan desde el servidor de ficheros. */
function urlLogoPermitida(string $url): bool
{
    $p = parse_url($url);
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && ($p['scheme'] ?? '') === 'https'
        && in_array(strtolower($p['host'] ?? ''), ['upload.wikimedia.org','thumb.wikimedia.org'], true)
        && !isset($p['user']) && !isset($p['pass']) && !isset($p['fragment'])
        && (!isset($p['port']) || $p['port'] === 443)
        && !preg_match('/[\x00-\x20\x7f]/', $url);
}

/** Limita bytes durante la transferencia y valida cada salto de redirección. */
function descargarLogo(string $url): string
{
    for ($salto = 0; $salto < 4; $salto++) {
        if (!urlLogoPermitida($url)) throw new \RuntimeException('El logo debe proceder de Wikimedia.');
        $datos = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'ComicHouse/1.0 (editorial logo review; local academic project)',
            CURLOPT_WRITEFUNCTION => static function ($ch, $trozo) use (&$datos) {
                if (strlen($datos) + strlen($trozo) > MAX_LOGO_BYTES) return 0;
                $datos .= $trozo;
                return strlen($trozo);
            }]);
        $ok = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redireccion = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        if ($http >= 300 && $http < 400 && $redireccion) { $url = $redireccion; continue; }
        if ($ok === false || $http !== 200 || $datos === '') {
            throw new \RuntimeException('No se pudo descargar el logo o supera 5 MB. Puedes subir una imagen manualmente.');
        }
        return $datos;
    }
    throw new \RuntimeException('No se pudo resolver la dirección del logo. Sube una imagen manualmente.');
}

/** Decodifica y vuelve a codificar: no guarda HTML, scripts ni SVG crudos. */
function pngLogo(string $datos): string
{
    if ($datos === '' || strlen($datos) > MAX_LOGO_BYTES) throw new \RuntimeException('El logo admite hasta 5 MB.');
    $info = @getimagesizefromstring($datos);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        throw new \RuntimeException('Elige una imagen PNG, JPG, WebP o GIF válida. Los logos SVG de Wikimedia se proponen como PNG.');
    }
    if ($info[0] * $info[1] > MAX_LOGO_PIXELES) throw new \RuntimeException('El logo supera los 16 megapíxeles permitidos.');
    $imagen = @imagecreatefromstring($datos);
    if (!$imagen) throw new \RuntimeException('La imagen del logo está dañada.');
    try {
        imagesavealpha($imagen, true);
        ob_start();
        imagepng($imagen);
        $png = ob_get_clean();
    } finally { imagedestroy($imagen); }
    if (!$png || strlen($png) > MAX_LOGO_BYTES) throw new \RuntimeException('La imagen procesada supera 5 MB. Usa un logo más pequeño.');
    return $png;
}

function prepararLogo(array $archivo, string $url = ''): ?string
{
    $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_NO_FILE) {
        if ($error !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) throw new \RuntimeException('No se recibió el logo completo.');
        if (filesize($archivo['tmp_name']) > MAX_LOGO_BYTES) throw new \RuntimeException('El logo admite hasta 5 MB.');
        return pngLogo(file_get_contents($archivo['tmp_name']));
    }
    return $url !== '' ? pngLogo(descargarLogo($url)) : null;
}

function guardarLogo(string $png): string
{
    $dir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($dir) && !mkdir($dir, 0755)) throw new \RuntimeException('No se pudo preparar la carpeta de logos.');
    $nombre = 'editorial_' . bin2hex(random_bytes(16)) . '.png';
    $ruta = $dir . $nombre;
    $f = fopen($ruta, 'xb');
    if (!$f) throw new \RuntimeException('No se pudo guardar el logo.');
    try {
        $restante = $png;
        while ($restante !== '') {
            $escritos = fwrite($f, $restante);
            if (!$escritos) throw new \RuntimeException('No se pudo guardar el logo completo.');
            $restante = substr($restante, $escritos);
        }
    } catch (\Throwable $e) { fclose($f); @unlink($ruta); throw $e; }
    fclose($f);
    return $nombre;
}

function retirarLogoNuevo(string $nombre): void
{
    if (preg_match('/^editorial_[a-f0-9]{32}\.png$/D', $nombre)) @unlink(dirname(__DIR__) . '/uploads/' . $nombre);
}

/** Peticiones de metadatos solo a APIs fijas, nunca a un host aportado por el cliente. */
function pedirWikimedia(string $servidor, array $parametros): ?array
{
    if (!in_array($servidor, ['en.wikipedia.org', 'es.wikipedia.org', 'commons.wikimedia.org', 'www.wikidata.org'], true)) return null;
    $ch = curl_init('https://' . $servidor . '/w/api.php?' . http_build_query($parametros + ['format'=>'json']));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8, CURLOPT_CONNECTTIMEOUT=>4,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXFILESIZE=>2*1024*1024,
        CURLOPT_USERAGENT=>'ComicHouse/1.0 (editorial logo review; local academic project)']);
    $texto = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http === 200 && is_string($texto) ? json_decode($texto, true) : null;
}

function ficheroLogoInfobox(string $texto): ?string
{
    // pageimages puede ser la sede de la empresa: solo leer campos de logo.
    if (!preg_match('/^\|\s*(?:logo|company_logo|logotipo)\s*=\s*([^\r\n]+)/im', $texto, $m)) return null;
    $nombre = trim($m[1]);
    if (str_contains($nombre, '{{')) return null;
    $nombre = preg_replace('/^\[\[(?:File|Image|Archivo|Imagen):/i', '', $nombre);
    $nombre = preg_split('/\||\]\]/', $nombre)[0];
    return preg_match('/\.(svg|png|jpg|jpeg|webp|gif)$/i', $nombre) ? trim($nombre) : null;
}

function buscarLogos(string $nombre): array
{
    $canonico = \nombreCanonicoEditorial($nombre);
    $busquedas = [$canonico, $canonico . ' (publisher)', $canonico . ' (publishing company)'];
    foreach (\CATALOGO_EDITORIALES[$canonico] ?? [] as $alias) $busquedas[] = ucwords($alias);
    foreach (['en.wikipedia.org','es.wikipedia.org'] as $servidor) {
        $j = pedirWikimedia($servidor, ['action'=>'query', 'titles'=>implode('|', array_unique($busquedas)),
            'redirects'=>1, 'prop'=>'pageprops|revisions', 'rvprop'=>'content', 'rvslots'=>'main']);
        foreach ($j['query']['pages'] ?? [] as $pagina) {
            if (isset($pagina['missing']) || isset($pagina['pageprops']['disambiguation'])) continue;
            $titulo = preg_replace('/\s*\((?:publisher|publishing company|company|editorial|empresa)\)\s*$/i', '', $pagina['title']);
            if (\clave_editorial(\nombreCanonicoEditorial($titulo)) !== \clave_editorial($canonico)) continue;
            $archivos = [];
            $entidad = $pagina['pageprops']['wikibase_item'] ?? '';
            if (preg_match('/^Q[0-9]+$/D', $entidad)) {
                $wd = pedirWikimedia('www.wikidata.org', ['action'=>'wbgetentities','ids'=>$entidad,'props'=>'claims']);
                $claims = $wd['entities'][$entidad]['claims']['P154'] ?? [];
                $preferidas = array_filter($claims, fn($c) => ($c['rank'] ?? '') === 'preferred');
                foreach ($preferidas ?: $claims as $c) {
                    $valor = $c['mainsnak']['datavalue']['value'] ?? null;
                    if (($c['rank'] ?? '') !== 'deprecated' && is_string($valor)) $archivos[] = $valor;
                }
            }
            $infobox = ficheroLogoInfobox($pagina['revisions'][0]['slots']['main']['*'] ?? '');
            if ($infobox) $archivos[] = $infobox;
            if (!$archivos) continue;
            $logos = [];
            $info = pedirWikimedia($servidor, ['action'=>'query','prop'=>'imageinfo',
                'titles'=>implode('|', array_map(fn($f) => 'File:' . $f, array_slice(array_unique($archivos), 0, 4))),
                'iiprop'=>'url|mime','iiurlwidth'=>480]);
            foreach ($info['query']['pages'] ?? [] as $p) {
                $ii = $p['imageinfo'][0] ?? [];
                $url = $ii['thumburl'] ?? $ii['url'] ?? '';
                if (!urlLogoPermitida($url)) continue;
                if (($ii['mime'] ?? '') === 'image/svg+xml' && empty($ii['thumburl'])) continue;
                $logos[] = ['titulo'=>$p['title'], 'url'=>$url,
                    'fuente'=>'https://' . $servidor . '/wiki/' . rawurlencode(str_replace(' ', '_', $pagina['title']))];
            }
            if ($logos) return $logos;
        }
    }
    return [];
}
