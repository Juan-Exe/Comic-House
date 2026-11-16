<?php
/** Regresiones de identidad de edición y precisión de fechas. Sin red ni BD. */
namespace ComicHouse\Tomos;
require_once dirname(__DIR__) . '/controlador/tomos_fuentes.php';
require_once dirname(__DIR__) . '/controlador/recopilatorios_fuentes.php';

$correctas = 0;
function comprobar(bool $condicion, string $caso): void {
    global $correctas;
    if (!$condicion) throw new \RuntimeException('FALLO: ' . $caso);
    ++$correctas;
    echo "OK: $caso\n";
}

$ediciones = [
    ['id'=>'wiki:marvel:Ultimate Spider-Man Vol 1', 'serie'=>'Ultimate Spider-Man Vol 1', 'anio'=>2000, 'editorial'=>'Marvel Comics'],
    ['id'=>'wiki:marvel:Ultimate Spider-Man Vol 2', 'serie'=>'Ultimate Spider-Man Vol 2', 'anio'=>2009, 'editorial'=>'Marvel Comics'],
    ['id'=>'wiki:marvel:Ultimate Spider-Man Vol 3', 'serie'=>'Ultimate Spider-Man Vol 3', 'anio'=>2024, 'editorial'=>'Marvel Comics'],
];
comprobar(elegirEdicion($ediciones, 2000, 'Marvel Comics')['id'] === $ediciones[0]['id'], 'La serie de 2000 conserva su identidad');
comprobar(elegirEdicion($ediciones, 2024, 'Marvel Comics')['id'] === $ediciones[2]['id'], 'El relanzamiento de 2024 es una edición distinta');
comprobar(elegirEdicion($ediciones, 1980, 'Marvel Comics') === null, 'El año incorrecto del catálogo no fuerza una edición cercana');
comprobar(elegirEdicion($ediciones, 0, 'Marvel Comics') === null, 'Sin año no se mezclan varias series');
comprobar(elegirEdicion($ediciones, 1980, '', $ediciones[0]['id'])['anio'] === 2000, 'La elección explícita resuelve un año de catálogo incorrecto');
comprobar(elegirEdicion($ediciones, 2000, '', 'cv:999') === null, 'No se acepta una edición que no pertenece a los resultados');
comprobar(elegirEdicion([$ediciones[2]], 2025, '')['anio'] === 2024, 'Tolera un año de diferencia cuando solo una edición coincide');
$mismoAnio = [
    ['id'=>'cv:1', 'anio'=>2018, 'editorial'=>'Titan Comics'],
    ['id'=>'cv:2', 'anio'=>2018, 'editorial'=>'Urban Comics'],
];
comprobar(elegirEdicion($mismoAnio, 2018, 'Norma Editorial') === null, 'No sustituye Norma por otra editorial para decidir la edición');
comprobar(elegirEdicion($mismoAnio, 2018, 'Titan Comics')['id'] === 'cv:1', 'La editorial desambigua dos ediciones del mismo año');
comprobar(nombreDeSerie('Ultimate Spider-Man Vol 1 1', 'Ultimate Spiderman')['serie'] === 'Ultimate Spider-Man Vol 1', 'Tolerancia al guion sin perder la serie');
comprobar(nombreDeSerie('Ultimate Spider-Man Vol 3 1', 'Ultimate Spiderman')['serie'] !== nombreDeSerie('Ultimate Spider-Man Vol 1 1', 'Ultimate Spiderman')['serie'], 'El número 1 de dos series nunca comparte identidad');
comprobar(nombreDeSerie('Ultimate Spider-Man Omnibus Vol 1 1', 'Ultimate Spiderman') === null, 'No mezcla recopilatorios con la serie solicitada');
comprobar(nombreDeSerie('Otro título Vol 1 1', 'Ultimate Spiderman') === null, 'Rechaza otra obra encontrada por el buscador');

$ficha = "{{Comic Template\n| Month = 12\n| Year = 2024\n| StoryTitle1 = [[Obra: Arco|El arco]], parte uno\n}}";
comprobar(limpiarWiki(campoPlantilla($ficha, 'StoryTitle1')) === 'El arco, parte uno', 'Conserva el texto visible de los enlaces con separador');
comprobar(fechaDeNumero($ficha) === ['fecha'=>'', 'fecha_mes'=>'2024-12'], 'Un mes numérico no inventa el día 1');
comprobar(fechaDeNumero("| Month = March\n| Year = 2012")['fecha_mes'] === '2012-03', 'Interpreta meses escritos');
comprobar(fechaDeNumero("| Month = 13\n| Year = 2024")['fecha_mes'] === '', 'Rechaza un mes inexistente');
comprobar(fechaDeNumero("| Day = 22\n| Month = January\n| Year = 2003") ===
    ['fecha'=>'2003-01-22', 'fecha_mes'=>'2003-01'], 'Lee el día publicado en la plantilla de una grapa');
comprobar(fechaDeNumero("| Day = 19\n| Month = February\n| Year = 2003")['fecha'] === '2003-02-19',
    'Conserva la fecha propia del segundo número');
comprobar(fechaDeNumero("| Day = 30\n| Month = February\n| Year = 2003") ===
    ['fecha'=>'', 'fecha_mes'=>'2003-02'], 'Un día imposible conserva solo el mes conocido');
comprobar(fechaDeNumero("| ReleaseDate = September 7, 2000\n| Month = October\n| Year = 2000")['fecha'] === '2000-09-07', 'Prefiere el día publicado por la fuente a la fecha de portada');
comprobar(aFechaISO('2024-02-30') === '', 'No normaliza una fecha inexistente como si fuera válida');
comprobar(aFechaISO('29 February 2024') === '2024-02-29', 'Acepta una fecha bisiesta real');

$manga = "{{volinfo\n| volume = 3\n| arcs = [[Black Swordsman Arc]], [[Golden Age Arc]]\n| ja pub = 25 October 1991\n| episodes = {{e|0-6}} {{e|0-7}} {{e|0-8}} {{e|0-9}}\n}}";
$tomos = tomosDeVolinfo($manga);
comprobar(count($tomos) === 1 && $tomos[0]['desde'] === 6 && $tomos[0]['hasta'] === 9, 'Conserva el rango del tomo 3 de Berserk');
comprobar($tomos[0]['fecha'] === '1991-10-25' && $tomos[0]['formato'] === 'rango', 'No convierte el tomo de manga en una grapa');
$reinicio = "{{volinfo\n| volume = 4\n| episodes = {{e|0-10}} {{e|0-14}}\n}}\n"
    . "{{volinfo\n| volume = 5\n| episodes = {{e|0-15}} {{e|0-16}} {{e|1}} {{e|2}} {{e|6}}\n}}\n"
    . "{{volinfo\n| volume = 6\n| episodes = {{e|7}} {{e|16}}\n}}";
$tomosReinicio = tomosDeVolinfo($reinicio);
comprobar($tomosReinicio[1]['desde'] === 15 && $tomosReinicio[1]['hasta'] === 22, 'El tomo mixto conserva prólogo y numeración nueva');
comprobar($tomosReinicio[2]['desde'] === 23 && $tomosReinicio[2]['hasta'] === 32, 'El reinicio no solapa el tomo siguiente con los ya subidos');
comprobar($tomosReinicio[1]['numeracion_normalizada'] && str_contains($tomosReinicio[1]['numeracion_fuente'], '0-15'), 'Conserva la notación original junto a la numeración continua');

$obra = ['titulo'=>'Ultimate Spiderman', 'anio'=>2000, 'editorial'=>'Marvel', 'edicion'=>''];
foreach (['anio'=>2024, 'editorial'=>'Otra', 'edicion'=>'wiki:marvel:Ultimate Spider-Man Vol 3'] as $campo=>$valor) {
    comprobar(claveCache($obra) !== claveCache(array_replace($obra, [$campo=>$valor])), 'La caché distingue ' . $campo);
}
$tabla = "| title = Ejemplo\n| startyr = 2003\n" . <<<'WIKI'
{| class="wikitable"
|-
! Volume
! Title
! ISBN
! Release date
! Collected Material
|-
! 1
| '''''Ejemplo – Asuntos de familia'''''
| {{ISBN|123}}
| 2004-04-14
| ''Ejemplo'' #1–4
|-
! 2
| '''''Ejemplo – Siguiente colección'''''
| {{ISBN|456}}
| 2005-01-01
| ''Ejemplo'' #5–8
|}
WIKI;
$colecciones = recopilarTitulos($tabla,'Ejemplo',2003);
comprobar(count($colecciones) === 2 && $colecciones[0]['titulo'] === 'Asuntos de familia'
    && $colecciones[0]['desde'] === 1 && $colecciones[0]['hasta'] === 4, 'Lee nombres y rangos explícitos de recopilatorios');
comprobar(recopilarTitulos($tabla,'Ejemplo',2024) === [], 'No aplica recopilatorios de otra encarnación de la serie');
comprobar(recopilarTitulos($tabla,'Otra obra',2003) === [], 'No aplica una tabla de otra obra');
comprobar(recopilarTitulos($tabla,'Ejemplo',0) === [], 'No infiere identidad sin año de serie');
comprobar(recopilarTitulos(str_replace('Collected Material','Characters',$tabla),'Ejemplo',2003) === [], 'Exige cabecera de contenido recopilado');
comprobar(count(recopilarTitulos(str_replace('#1–4','#1, #3–4',$tabla),'Ejemplo',2003)) === 1,
    'No convierte números discontinuos en un rango completo');
comprobar(count(recopilarTitulos(str_replace('#1–4','#4–1',$tabla),'Ejemplo',2003)) === 1, 'Rechaza rangos invertidos');
comprobar(count(recopilarTitulos(str_replace("! 1\n|", "! 1\n| rowspan=2 |",$tabla),'Ejemplo',2003)) === 1,
    'No reutiliza celdas combinadas como nombres de otras colecciones');
$numeros = [['formato'=>'grapa','desde'=>1,'hasta'=>1,'titulo'=>'','arco'=>''],
    ['formato'=>'grapa','desde'=>2,'hasta'=>2,'titulo'=>'Título propio','arco'=>''],
    ['formato'=>'rango','desde'=>1,'hasta'=>4,'titulo'=>'','arco'=>''],
    ['formato'=>'grapa','desde'=>9,'hasta'=>9,'titulo'=>'','arco'=>'']];
$completos = completarRecopilatorios($numeros,$colecciones);
comprobar($completos[0]['titulo'] === '' && $completos[0]['recopilatorio']['titulo'] === 'Asuntos de familia',
    'Guarda el recopilatorio separado del título individual ausente');
comprobar(!isset($completos[1]['recopilatorio']), 'Respeta un título individual ya publicado');
comprobar(!isset($completos[2]['recopilatorio']), 'No altera tomos ni rangos de manga');
comprobar(!isset($completos[3]['recopilatorio']), 'Un número fuera de los rangos sigue sin recopilatorio');
comprobar(!isset(completarRecopilatorios($numeros,[$colecciones[0],$colecciones[0]])[0]['recopilatorio']),
    'No elige entre recopilatorios ambiguos');
echo "\n$correctas comprobaciones correctas.\n";
