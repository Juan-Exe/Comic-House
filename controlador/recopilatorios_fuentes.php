<?php
namespace ComicHouse\Tomos;

/** Solo tablas de recopilatorios con título, contenido y año de serie explícitos. */
function recopilarTitulos(string $texto, string $obra, int $anio): array {
    if ($anio < 1900 || (int) campoPlantilla($texto, 'startyr') !== $anio
        || normalizarObra(limpiarWiki(campoPlantilla($texto, 'title'))) !== normalizarObra($obra)) return [];
    $salida = [];
    preg_match_all('/\{\|[^\n]*\n(.*?)\n\|\}/s', $texto, $tablas);
    foreach ($tablas[1] as $tabla) {
        $filas = preg_split('/\n\|-\s*\n/', $tabla);
        $cabecera = array_shift($filas);
        // No interpretar tablas de personajes, fechas o adaptaciones como recopilatorios.
        if (!preg_match('/!\s*Title\s*(?:\n|!!)/i', $cabecera)
            || !preg_match('/!.*Collected (?:Material|issues)/i', $cabecera)) continue;
        foreach ($filas as $fila) {
            if (preg_match('/(?:rowspan|colspan)\s*=/i', $fila)) continue;
            $celdas = preg_split('/\n[!|](?![!|])/', "\n" . trim($fila));
            $celdas = array_values(array_filter(array_map('trim', $celdas), fn($c) => $c !== ''));
            if (count($celdas) < 5 || !preg_match('/^[1-9]\d{0,2}$/D', $celdas[0])) continue;
            $nombre = limpiarWiki($celdas[1]);
            if (!preg_match('/^' . preg_quote($obra, '/') . '\s*[–—:-]\s*(.+)$/ui', $nombre, $m)) continue;
            $titulo = trim($m[1]);
            $contenido = limpiarWiki($celdas[4]);
            // Un único rango explícito de esta obra; no convertir extras o huecos en un rango.
            if (!preg_match('/^' . preg_quote($obra, '/') . '\s+#([1-9]\d{0,3})\s*[–—-]\s*([1-9]\d{0,3})\s*$/ui', $contenido, $r)) continue;
            $desde = (int) $r[1]; $hasta = (int) $r[2];
            if ($hasta < $desde || $hasta > 9999 || $titulo === '' || mb_strlen($titulo) > 200) continue;
            $salida[] = ['titulo'=>$titulo, 'desde'=>$desde, 'hasta'=>$hasta];
        }
    }
    return $salida;
}

function buscarRecopilatorios(string $obra, int $anio): array {
    $pagina = $obra . ' (comics)';
    $url = 'https://en.wikipedia.org/w/api.php?' . http_build_query(['action'=>'query',
        'titles'=>$pagina,'prop'=>'revisions','rvprop'=>'content','rvslots'=>'main','format'=>'json']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>4,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXFILESIZE=>2*1024*1024,
        CURLOPT_USERAGENT=>'ComicHouse/1.0 (collected edition titles; local academic project)']);
    $txt = curl_exec($ch); $http = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $j = $http === 200 && is_string($txt) ? json_decode($txt,true) : null;
    $paginas = $j['query']['pages'] ?? []; $p = reset($paginas);
    $texto = $p['revisions'][0]['slots']['main']['*'] ?? '';
    $lista = recopilarTitulos($texto,$obra,$anio);
    foreach ($lista as &$c) {
        $c['url'] = 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ','_',$pagina)) . '#Collected_editions';
        $c['fuente'] = 'Wikipedia · recopilatorios';
    }
    return $lista;
}

/** No modifica el título individual ni asigna un recopilatorio ambiguo. */
function completarRecopilatorios(array $numeros, array $colecciones): array {
    foreach ($numeros as &$n) {
        if (($n['formato'] ?? '') !== 'grapa' || trim($n['titulo'] ?? '') !== '' || trim($n['arco'] ?? '') !== '') continue;
        $coinciden = array_values(array_filter($colecciones, fn($c) => $c['desde'] <= $n['desde'] && $c['hasta'] >= $n['hasta']));
        if (count($coinciden) === 1) $n['recopilatorio'] = $coinciden[0];
    }
    return $numeros;
}
