<?php
/** Fechas con la precisión que realmente se conoce: día o mes. */
function validarFechaCapitulo(string $dia, string $mes = '', ?string $hoy = null): array
{
    $dia = trim($dia);
    $mes = trim($mes);
    $hoy = $hoy ?? (new DateTimeImmutable('today', new DateTimeZone('America/Bogota')))->format('Y-m-d');
    $error = fn($mensaje) => ['ok'=>false, 'mensaje'=>$mensaje];
    if ($dia !== '' && $mes !== '') return $error('Elige una fecha completa o un mes y año, no ambos.');
    if ($mes !== '') {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $mes, $m)
            || (int) $m[1] < 1900 || !checkdate((int) $m[2], 1, (int) $m[1])) {
            return $error('Revisa el mes y el año de publicación.');
        }
        if ($mes > substr($hoy, 0, 7)) return $error('El mes de publicación no puede estar en el futuro.');
    }
    if ($dia !== '') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dia, $m)
            || (int) $m[1] < 1900 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $error('Esa fecha no existe. Revisa el día, el mes y el año.');
        }
        if ($dia > $hoy) return $error('La fecha no puede estar en el futuro.');
    }
    return ['ok'=>true, 'fecha'=>$dia !== '' ? $dia : null, 'mes'=>$mes !== '' ? $mes : null];
}

function mostrarFechaCapitulo(?string $dia, ?string $mes = null, bool $espacios = false): string
{
    $separador = $espacios ? ' / ' : '/';
    if ($dia) return implode($separador, array_reverse(explode('-', $dia)));
    if ($mes) return implode($separador, array_reverse(explode('-', $mes)));
    return '';
}
