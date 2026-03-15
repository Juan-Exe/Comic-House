<?php

function cargarEntorno($ruta)
{
	if (!is_readable($ruta)) {
		return;
	}

	$lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lineas as $linea) {
		$linea = trim($linea);
		if ($linea === '' || $linea[0] === '#') {
			continue;
		}

		$partes = explode('=', $linea, 2);
		if (count($partes) !== 2) {
			continue;
		}

		$clave = trim($partes[0]);
		$valor = trim($partes[1]);

		if ($clave === '') {
			continue;
		}

		if ($valor !== '' && (($valor[0] === '"' && substr($valor, -1) === '"') || ($valor[0] === '\'' && substr($valor, -1) === '\''))) {
			$valor = substr($valor, 1, -1);
		}

		putenv($clave . '=' . $valor);
		$_ENV[$clave] = $valor;
		$_SERVER[$clave] = $valor;
	}
}

cargarEntorno(dirname(__DIR__) . '/.env');

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);
$dbName = getenv('DB_NAME') ?: 'comic_house';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

$conexion = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if ($conexion->connect_error) {
	die('Error de conexion a la base de datos: ' . $conexion->connect_error);
}

$conexion->set_charset('utf8mb4');
?>