<?php
/**
 * momento2_operaciones.php - Demuestra las cuatro operaciones del
 * prototipo del Momento 2 contra la base real del proyecto.
 *
 *   1. Registrar un comic de una editorial
 *   2. Consultar las paginas de un capitulo en orden
 *   3. Actualizar la clasificacion de un comic
 *   4. Rechazo: editorial inexistente y titulo repetido
 *
 * Todas las consultas son parametrizadas y las credenciales se leen
 * del entorno (.env), nunca del codigo.
 *
 * Ejecutar:
 *   docker exec comic_house_web php \
 *     /var/www/html/E1_integridad_rendimiento/benchmark/momento2_operaciones.php
 */

require_once dirname(__DIR__, 2) . '/modelo/conexion.php';

$ok = 0;
$fallos = 0;

function titulo(string $txt): void
{
    echo "\n" . str_repeat('=', 66) . "\n$txt\n" . str_repeat('=', 66) . "\n";
}

function paso(bool $correcto, string $txt): void
{
    global $ok, $fallos;
    $correcto ? $ok++ : $fallos++;
    echo ($correcto ? '[OK]   ' : '[FALLO]') . " $txt\n";
}

/**
 * Ejecuta una sentencia que SE ESPERA que la base rechace y devuelve el
 * codigo de error del motor, o 0 si la fila entro.
 *
 * mysqli esta configurado para lanzar excepciones, asi que el rechazo
 * llega como mysqli_sql_exception: capturarla es la forma correcta de
 * comprobar que la restriccion actuo.
 */
function intentar(mysqli_stmt $stmt): int
{
    try {
        $stmt->execute();
        return 0;
    } catch (mysqli_sql_exception $e) {
        return $e->getCode();
    }
}

// Los datos de prueba se marcan para poder borrarlos al final sin
// tocar el catalogo real.
const TITULO_PRUEBA = 'ZZZ_PRUEBA_Momento2';

// Limpieza previa, por si una ejecucion anterior quedo a medias.
// Esto es lo que hace que el proceso se pueda repetir sin duplicar.
$limpiar = $conexion->prepare("DELETE FROM comics WHERE titulo LIKE ?");
$patron = 'ZZZ_PRUEBA%';
$limpiar->bind_param('s', $patron);
$limpiar->execute();
$limpiar->close();

// ====================================================================
titulo('OPERACION 1 - Registrar un comic de una editorial');

// La editorial llega por nombre y se resuelve a su id: es lo que
// permite exigir la clave foranea.
$buscar = $conexion->prepare("SELECT id FROM editoriales WHERE nombre = ?");
$nombre_ed = 'DC';
$buscar->bind_param('s', $nombre_ed);
$buscar->execute();
$buscar->bind_result($editorial_id);
$buscar->fetch();
$buscar->close();

paso($editorial_id > 0, "Editorial '$nombre_ed' resuelta a id=$editorial_id");

// El titulo se normaliza AL INSERTAR (correccion 1), no dentro del CHECK.
$titulo_sucio = '  ' . TITULO_PRUEBA . '  ';
$titulo_limpio = trim($titulo_sucio);

$ins = $conexion->prepare(
    "INSERT INTO comics (titulo, clasificacion, descripcion, capitulos,
                         anio, genero, portada, editorial, editorial_id, tipo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$clasificacion = '+13';
$descripcion = 'Registro de prueba del Momento 2.';
$capitulos = '1';
$anio = 2025;
$genero = 'Accion';
$portada = 'prueba.jpg';
$tipo = 'Comic';
$ins->bind_param(
    'ssssisssis',
    $titulo_limpio, $clasificacion, $descripcion, $capitulos,
    $anio, $genero, $portada, $nombre_ed, $editorial_id, $tipo
);
$ins->execute();
$comic_id = $conexion->insert_id;
$ins->close();

paso($comic_id > 0, "Comic registrado -> id=$comic_id");

// Se comprueba que lo guardado es el titulo ya normalizado.
$ver = $conexion->prepare("SELECT titulo FROM comics WHERE id = ?");
$ver->bind_param('i', $comic_id);
$ver->execute();
$ver->bind_result($titulo_guardado);
$ver->fetch();
$ver->close();

paso($titulo_guardado === TITULO_PRUEBA,
     "El titulo se guardo normalizado: '[$titulo_guardado]' (entro con espacios)");

// ====================================================================
titulo('OPERACION 2 - Consultar las paginas de un capitulo en orden');

$cap = $conexion->prepare(
    "SELECT id, titulo FROM capitulos ORDER BY id LIMIT 1"
);
$cap->execute();
$cap->bind_result($capitulo_id, $cap_titulo);
$cap->fetch();
$cap->close();

$sel = $conexion->prepare(
    "SELECT id, imagen_url, orden, es_doble
       FROM paginas
      WHERE capitulo_id = ?
      ORDER BY orden"
);
$sel->bind_param('i', $capitulo_id);

$t0 = microtime(true);
$sel->execute();
$res = $sel->get_result();
$filas = $res->fetch_all(MYSQLI_ASSOC);
$ms = (microtime(true) - $t0) * 1000;
$sel->close();

$n = count($filas);
paso($n > 0, sprintf('Capitulo %d ("%s"): %d paginas leidas en %.2f ms',
                     $capitulo_id, $cap_titulo, $n, $ms));

// Verificar que realmente salieron ordenadas.
$ordenado = true;
$previo = 0;
foreach ($filas as $f) {
    if ((int) $f['orden'] <= $previo) { $ordenado = false; break; }
    $previo = (int) $f['orden'];
}
paso($ordenado, 'Las paginas salieron en orden ascendente 1..' . $previo);

// ====================================================================
titulo('OPERACION 3 - Actualizar la clasificacion de un comic');

$upd = $conexion->prepare("UPDATE comics SET clasificacion = ? WHERE id = ?");
$nueva = '+18';
$upd->bind_param('si', $nueva, $comic_id);
$upd->execute();
$afectadas = $upd->affected_rows;
$upd->close();

paso($afectadas === 1, "Clasificacion actualizada a '$nueva' ($afectadas fila)");

// Un valor fuera del dominio debe ser rechazado por el CHECK.
$upd2 = $conexion->prepare("UPDATE comics SET clasificacion = ? WHERE id = ?");
$invalida = '21+';
$upd2->bind_param('si', $invalida, $comic_id);
$err_check = intentar($upd2);
$upd2->close();

paso($err_check !== 0,
     "CHECK ck_comics_clasificacion rechaza '$invalida' -> errno $err_check");

// ====================================================================
titulo('OPERACION 4 - Rechazo: la base no guarda el dato invalido');

// 4a. Editorial inexistente -> FOREIGN KEY
$fk = $conexion->prepare(
    "INSERT INTO comics (titulo, clasificacion, descripcion, capitulos,
                         anio, genero, portada, editorial, editorial_id, tipo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$t_fk = 'ZZZ_PRUEBA_FK';
$ed_fantasma = 'Editorial Fantasma';
$id_fantasma = 999;
$fk->bind_param('ssssisssis',
    $t_fk, $clasificacion, $descripcion, $capitulos,
    $anio, $genero, $portada, $ed_fantasma, $id_fantasma, $tipo);
$err_fk = intentar($fk);
$fk->close();

paso($err_fk === 1452,
     "FOREIGN KEY fk_comics_editorial rechaza editorial_id=999 -> errno $err_fk");

// 4b. Titulo repetido -> UNIQUE
$uq = $conexion->prepare(
    "INSERT INTO comics (titulo, clasificacion, descripcion, capitulos,
                         anio, genero, portada, editorial, editorial_id, tipo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$mismo = TITULO_PRUEBA;
$uq->bind_param('ssssisssis',
    $mismo, $clasificacion, $descripcion, $capitulos,
    $anio, $genero, $portada, $nombre_ed, $editorial_id, $tipo);
$err_uq = intentar($uq);
$uq->close();

paso($err_uq === 1062,
     "UNIQUE uq_comics_titulo rechaza el titulo repetido -> errno $err_uq");

// 4c. Año futuro -> CHECK
$an = $conexion->prepare(
    "INSERT INTO comics (titulo, clasificacion, descripcion, capitulos,
                         anio, genero, portada, editorial, editorial_id, tipo)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$t_an = 'ZZZ_PRUEBA_ANIO';
$anio_futuro = 2099;
$an->bind_param('ssssisssis',
    $t_an, $clasificacion, $descripcion, $capitulos,
    $anio_futuro, $genero, $portada, $nombre_ed, $editorial_id, $tipo);
$err_an = intentar($an);
$an->close();

paso($err_an !== 0, "CHECK ck_comics_anio rechaza anio=2099 -> errno $err_an");

// ====================================================================
titulo('IDEMPOTENCIA - El proceso se repite sin duplicar');

$cnt = $conexion->prepare("SELECT COUNT(*) FROM comics WHERE titulo LIKE ?");
$cnt->bind_param('s', $patron);
$cnt->execute();
$cnt->bind_result($n_prueba);
$cnt->fetch();
$cnt->close();

paso($n_prueba === 1,
     "Tras los 3 rechazos solo quedo 1 fila de prueba (los invalidos no entraron)");

// ====================================================================
titulo('LIMPIEZA - El catalogo real queda intacto');

$del = $conexion->prepare("DELETE FROM comics WHERE titulo LIKE ?");
$del->bind_param('s', $patron);
$del->execute();
$del->close();

$final = $conexion->query(
    "SELECT (SELECT COUNT(*) FROM comics) c,
            (SELECT COUNT(*) FROM capitulos) k,
            (SELECT COUNT(*) FROM paginas) p"
)->fetch_assoc();

echo "Catalogo final: {$final['c']} comics, {$final['k']} capitulos, "
   . "{$final['p']} paginas\n";

echo "\n" . str_repeat('-', 66) . "\n";
printf("Resultado: %d correctas, %d fallidas\n", $ok, $fallos);
echo str_repeat('-', 66) . "\n";

exit($fallos === 0 ? 0 : 1);
