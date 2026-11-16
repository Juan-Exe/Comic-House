<?php
require_once dirname(__DIR__) . '/controlador/fecha_capitulo.php';
$correctas = 0;
function comprobarFecha(bool $condicion, string $caso): void {
    global $correctas;
    if (!$condicion) throw new RuntimeException('FALLO: ' . $caso);
    ++$correctas;
    echo "OK: $caso\n";
}
$hoy = '2026-11-15';
$mensual = validarFechaCapitulo('', '2025-01', $hoy);
comprobarFecha($mensual === ['ok'=>true, 'fecha'=>null, 'mes'=>'2025-01'], 'Mes conocido sin inventar día');
comprobarFecha(validarFechaCapitulo('2024-02-29', '', $hoy)['ok'], 'Fecha bisiesta completa');
comprobarFecha(validarFechaCapitulo('', '', $hoy)['ok'], 'Fecha opcional vacía');
comprobarFecha(!validarFechaCapitulo('2025-01-01', '2025-01', $hoy)['ok'], 'Dos precisiones a la vez se rechazan');
foreach (['2025-13', '2025-00', '1899-12', '2025-1', '01/2025', '2026-12'] as $mes) {
    comprobarFecha(!validarFechaCapitulo('', $mes, $hoy)['ok'], 'Mes rechazado: ' . $mes);
}
foreach (['2025-02-29', '2024-02-30', '2026-11-16'] as $dia) {
    comprobarFecha(!validarFechaCapitulo($dia, '', $hoy)['ok'], 'Día rechazado: ' . $dia);
}
comprobarFecha(validarFechaCapitulo('', '2026-11', $hoy)['ok'], 'El mes actual es válido sin exigir el día');
comprobarFecha(mostrarFechaCapitulo(null, '2025-01') === '01/2025', 'La ficha muestra únicamente la precisión almacenada');
comprobarFecha(mostrarFechaCapitulo('2024-02-29') === '29/02/2024', 'Las fechas completas mantienen su formato');

if (in_array('--bd', $argv, true)) {
    require dirname(__DIR__) . '/modelo/conexion.php';
    $original = $conexion->query('SELECT id,fecha_publicacion,fecha_publicacion_mes FROM capitulos ORDER BY id LIMIT 1')->fetch_assoc();
    if (!$original) throw new RuntimeException('La comprobación de BD necesita un capítulo existente.');
    $conexion->begin_transaction();
    try {
        $id = (int) $original['id'];
        $st = $conexion->prepare('UPDATE capitulos SET fecha_publicacion = ?, fecha_publicacion_mes = ? WHERE id = ?');
        $dia = null;
        $mes = '2025-01';
        $st->bind_param('ssi', $dia, $mes, $id);
        $st->execute();
        $guardado = $conexion->query('SELECT fecha_publicacion,fecha_publicacion_mes FROM capitulos WHERE id=' . $id)->fetch_assoc();
        comprobarFecha($guardado === ['fecha_publicacion'=>null, 'fecha_publicacion_mes'=>'2025-01'], 'MariaDB conserva el mes con DATE nulo');
        $dia = '2025-01-01';
        try { $st->execute(); $rechazo = false; }
        catch (mysqli_sql_exception $e) { $rechazo = $e->getCode() === 4025; }
        comprobarFecha($rechazo, 'CHECK rechaza guardar día y mes simultáneamente');
        $dia = null;
        $mes = '2025-13';
        try { $st->execute(); $rechazo = false; }
        catch (mysqli_sql_exception $e) { $rechazo = $e->getCode() === 4025; }
        comprobarFecha($rechazo, 'CHECK rechaza un mes imposible');
        $st->close();
    } finally {
        $conexion->rollback();
    }
    $final = $conexion->query('SELECT id,fecha_publicacion,fecha_publicacion_mes FROM capitulos WHERE id=' . (int) $original['id'])->fetch_assoc();
    comprobarFecha($original === $final, 'Rollback conserva el capítulo original intacto');
}
echo "\n$correctas comprobaciones de fechas correctas.\n";
