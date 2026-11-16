<?php
session_start();
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php"); exit();
}

require_once __DIR__ . '/controlador/validar_editorial.php';
require_once __DIR__ . '/controlador/editorial_servicio.php';

// La portada coloca 5 logos por fila (contenedor de 1170px, logo de
// 210px). Solo 5 y 10 llenan filas completas, asi que en vez de avisar
// de una fila coja se elige el formato y la base impide pasarse.
const FORMATOS_VALIDOS = [5, 10];

$formato = (int) $conexion->query(
    "SELECT editoriales_formato FROM configuracion WHERE id = 1"
)->fetch_assoc()['editoriales_formato'];

$error = ''; $msg = '';

// Registrar editorial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'crear') {
        try {
            $resultado = ComicHouse\Editoriales\altaEditorial($conexion,
                array_replace($_POST, ['exigir_logo'=>'1']), $_FILES['imagen'] ?? []);
            if (!$resultado['ok']) {
                header('Location: editoriales.php?err=sugerencia&n=' . urlencode($_POST['nombre'] ?? '')
                    . '&s=' . urlencode($resultado['sugerencia']['nombre'] ?? ''));
            } else {
                header('Location: editoriales.php?ok=' . ($resultado['reutilizada'] ? 'reutilizada' : 'creada'));
            }
        } catch (Throwable $e) {
            header('Location: editoriales.php?err=logo');
        }
        exit();

    } elseif ($_POST['accion'] === 'destacar') {
        // Alternar el destacado. El limite de seis lo impone un trigger
        // en la base; aqui solo se traduce su rechazo a un aviso.
        $id = (int) $_POST['id'];
        $valor = empty($_POST['valor']) ? 0 : 1;

        $stmt = $conexion->prepare(
            "UPDATE editoriales
                SET destacada = ?,
                    orden_destacada = IF(? = 1,
                        COALESCE((SELECT x.m FROM (SELECT MAX(orden_destacada) m
                                                     FROM editoriales) x), 0) + 1, 0)
              WHERE id = ?"
        );
        $stmt->bind_param('iii', $valor, $valor, $id);
        try {
            $stmt->execute();
            $destino = 'ok=destacada';
        } catch (mysqli_sql_exception $e) {
            $destino = 'err=limite';
        }
        $stmt->close();
        header("Location: editoriales.php?$destino"); exit();

    } elseif ($_POST['accion'] === 'formato') {
        // Cambiar el numero de espacios de la portada. Si se baja de 10
        // a 5, las que sobran dejan de estar destacadas pero siguen
        // registradas: no se borra nada.
        $nuevo = (int) ($_POST['valor'] ?? 5);
        if (!in_array($nuevo, FORMATOS_VALIDOS, true)) {
            header("Location: editoriales.php?err=1"); exit();
        }

        $conexion->begin_transaction();
        try {
            // Primero se retiran las que no caben; si no, el trigger
            // rechazaria cualquier cambio posterior.
            $quitar = $conexion->prepare(
                "UPDATE editoriales SET destacada = 0, orden_destacada = 0
                  WHERE destacada = 1 AND orden_destacada > ?"
            );
            $quitar->bind_param('i', $nuevo);
            $quitar->execute();
            $retiradas = $quitar->affected_rows;
            $quitar->close();

            $cfg = $conexion->prepare(
                "UPDATE configuracion SET editoriales_formato = ? WHERE id = 1"
            );
            $cfg->bind_param('i', $nuevo);
            $cfg->execute();
            $cfg->close();

            $conexion->commit();
            $destino = $retiradas > 0
                ? 'ok=formato_recorte&r=' . $retiradas
                : 'ok=formato';
        } catch (Throwable $e) {
            $conexion->rollback();
            $destino = 'err=1';
        }
        header("Location: editoriales.php?$destino"); exit();

    } elseif ($_POST['accion'] === 'mover') {
        // Intercambia la posicion de dos editoriales destacadas.
        // Se hace en una transaccion: dejar una posicion duplicada o
        // vacia a medias romperia el orden de la portada.
        $origen = (int) $_POST['origen'];
        $destino_pos = (int) $_POST['destino'];

        $conexion->begin_transaction();
        try {
            $leer = $conexion->prepare(
                "SELECT id, orden_destacada FROM editoriales
                  WHERE destacada = 1 AND orden_destacada IN (?, ?)"
            );
            $leer->bind_param('ii', $origen, $destino_pos);
            $leer->execute();
            $filas = $leer->get_result()->fetch_all(MYSQLI_ASSOC);
            $leer->close();

            $mov = $conexion->prepare(
                "UPDATE editoriales SET orden_destacada = ? WHERE id = ?"
            );
            foreach ($filas as $f) {
                // Cada una toma la posicion de la otra.
                $nueva_pos = (int) $f['orden_destacada'] === $origen
                    ? $destino_pos : $origen;
                $id_f = (int) $f['id'];
                $mov->bind_param('ii', $nueva_pos, $id_f);
                $mov->execute();
            }
            $mov->close();
            $conexion->commit();
            $destino = 'ok=movida';
        } catch (Throwable $e) {
            $conexion->rollback();
            $destino = 'err=1';
        }
        header("Location: editoriales.php?$destino"); exit();

    } elseif ($_POST['accion'] === 'editar') {
        // Editar nombre y/o logo de una editorial ya registrada.
        if ((int) $conexion->query("SELECT GET_LOCK('comic_house_editoriales',10)")->fetch_row()[0] !== 1) {
            header('Location: editoriales.php?err=1'); exit();
        }
        $id = (int) $_POST['id'];
        $nombre = normalizarEditorial($_POST['nombre'] ?? '');

        if ($nombre === '' || clave_editorial($nombre) === '' || mb_strlen($nombre) > 100) {
            header("Location: editoriales.php?err=vacio"); exit();
        }

        // Si cambia el nombre, se vuelve a pasar por el validador: una
        // edicion puede introducir el mismo error que una alta.
        $actual = $conexion->prepare("SELECT nombre, imagen FROM editoriales WHERE id = ?");
        $actual->bind_param('i', $id);
        $actual->execute();
        $fila = $actual->get_result()->fetch_assoc();
        $actual->close();

        if (!$fila) {
            header("Location: editoriales.php?err=1"); exit();
        }

        if ($nombre !== $fila['nombre']) {
            $dictamen = revisarEditorial($conexion, $nombre);
            if ($dictamen['estado'] === 'duplicada' && $dictamen['sugerencia'] !== $fila['nombre']) {
                header("Location: editoriales.php?err=duplicada&n=" . urlencode($nombre));
                exit();
            }
            if ($dictamen['estado'] === 'sugerencia' && empty($_POST['confirmar'])) {
                header("Location: editoriales.php?err=sug_edit&id=$id"
                     . "&n=" . urlencode($nombre)
                     . "&s=" . urlencode($dictamen['sugerencia']));
                exit();
            }
        }

        $imagen = $fila['imagen'];
        try {
            $png = ComicHouse\Editoriales\prepararLogo($_FILES['imagen'] ?? []);
            if ($png !== null) $imagen = ComicHouse\Editoriales\guardarLogo($png);
        } catch (Throwable $e) {
            header('Location: editoriales.php?err=logo'); exit();
        }

        $stmt = null;
        try {
            $conexion->begin_transaction();
            $stmt = $conexion->prepare(
                "UPDATE editoriales SET nombre = ?, imagen = ? WHERE id = ?"
            );
            $stmt->bind_param('ssi', $nombre, $imagen, $id);
            $stmt->execute();
            // comics.editorial guarda el nombre como texto: si cambia,
            // hay que mantenerlo sincronizado hasta que esa columna
            // redundante desaparezca (seccion 9.9 del informe).
            $sinc = $conexion->prepare(
                "UPDATE comics SET editorial = ? WHERE editorial_id = ?"
            );
            $sinc->bind_param('si', $nombre, $id);
            $sinc->execute();
            $sinc->close();
            $conexion->commit();
            $destino = 'ok=editada';
        } catch (Throwable $e) {
            $conexion->rollback();
            if ($imagen !== $fila['imagen']) ComicHouse\Editoriales\retirarLogoNuevo($imagen);
            $destino = $e instanceof mysqli_sql_exception && $e->getCode() === 1062
                ? 'err=duplicada&n=' . urlencode($nombre) : 'err=1';
        }
        if ($stmt) $stmt->close();
        header("Location: editoriales.php?$destino"); exit();

    } elseif ($_POST['accion'] === 'eliminar') {
        $id = (int) $_POST['id'];
        $stmt = $conexion->prepare("DELETE FROM editoriales WHERE id = ?");
        $stmt->bind_param('i', $id);
        try {
            $stmt->execute();
            $destino = 'ok=eliminada';
        } catch (mysqli_sql_exception $e) {
            // La clave foranea con ON DELETE RESTRICT impide borrar una
            // editorial que todavia tiene comics asociados.
            $destino = $e->getCode() === 1451 ? 'err=en_uso' : 'err=1';
        }
        $stmt->close();
        header("Location: editoriales.php?$destino"); exit();
    }
}

$editoriales = $conexion->query(
    "SELECT * FROM editoriales ORDER BY destacada DESC, orden_destacada ASC, nombre ASC"
);
$destacadas = $conexion->query(
    "SELECT * FROM editoriales WHERE destacada = 1
      ORDER BY orden_destacada ASC, nombre ASC LIMIT " . $formato
);
$lista_destacadas = $destacadas->fetch_all(MYSQLI_ASSOC);
$n_destacadas = count($lista_destacadas);
$total_editoriales = (int) $conexion->query("SELECT COUNT(*) n FROM editoriales")
                                    ->fetch_assoc()['n'];

// Con el formato elegido no hay estados invalidos: o esta completo o
// faltan espacios por llenar.
$espacios_libres = $formato - $n_destacadas;
$portada_completa = $espacios_libres === 0;
$filas_portada = intdiv($formato, 5);

// Las destacadas, indexadas por su posicion en la rejilla. Las
// posiciones se reasignan de forma consecutiva al leerlas: quitar una
// del medio deja un hueco en la numeracion, y la rejilla necesita
// posiciones 1..n sin saltos para poder intercambiarlas.
$por_posicion = [];
$pos = 1;
foreach ($lista_destacadas as $d) {
    if ((int) $d['orden_destacada'] !== $pos) {
        $fix = $conexion->prepare(
            "UPDATE editoriales SET orden_destacada = ? WHERE id = ?"
        );
        $id_d = (int) $d['id'];
        $fix->bind_param('ii', $pos, $id_d);
        $fix->execute();
        $fix->close();
        $d['orden_destacada'] = $pos;
    }
    $por_posicion[$pos] = $d;
    $pos++;
}

$avisos = [
    'creada'     => 'Editorial registrada correctamente.',
    'reutilizada'=> 'Se reutilizó la editorial existente; no se creó un duplicado.',
    'destacada'  => 'Se actualizaron las editoriales destacadas.',
    'eliminada'  => 'Editorial eliminada.',
    'editada'    => 'Editorial actualizada.',
    'movida'     => 'Se cambió el orden de la portada.',
    'formato'    => 'Formato de la portada actualizado.',
];
if (($_GET['ok'] ?? '') === 'formato_recorte') {
    $r = (int) ($_GET['r'] ?? 0);
    $avisos['formato_recorte'] = 'Formato actualizado. ' . $r
        . ($r === 1 ? ' editorial dejó' : ' editoriales dejaron')
        . ' de estar destacada, pero sigue' . ($r === 1 ? '' : 'n')
        . ' registrada' . ($r === 1 ? '' : 's') . '.';
}
$errores = [
    'vacio'     => 'Completa el nombre y sube el logo.',
    'formato'   => 'El logo debe ser PNG, JPG, WebP o GIF.',
    'logo'      => 'Elige una imagen PNG, JPG, WebP o GIF válida, de hasta 5 MB y 16 megapíxeles.',
    'duplicada' => 'Esa editorial ya está registrada.',
    'limite'       => 'La portada está configurada para ' . $formato
                    . ' editoriales y ya están todas. Cambia el formato o quita una.',
    'formato_img'  => 'El logo debe ser PNG, JPG, WebP o GIF.',
    'en_uso'    => 'No se puede eliminar: hay cómics publicados por esa editorial.',
    '1'         => 'Ocurrió un error.',
];

$msg   = $avisos[$_GET['ok'] ?? ''] ?? '';
$error = $errores[$_GET['err'] ?? ''] ?? '';

// Caso especial: nombre parecido a una editorial conocida.
$sugerencia = '';
$nombre_rechazado = '';
if (($_GET['err'] ?? '') === 'sugerencia') {
    $nombre_rechazado = $_GET['n'] ?? '';
    $sugerencia = $_GET['s'] ?? '';
    $error = '';
}
if (($_GET['err'] ?? '') === 'duplicada' && !empty($_GET['n'])) {
    $error = 'La editorial «' . htmlspecialchars($_GET['n']) . '» ya está registrada.';
}

// Sugerencia al EDITAR: el nombre nuevo se parece a otro conocido.
$sug_edit = null;
if (($_GET['err'] ?? '') === 'sug_edit') {
    $sug_edit = [
        'id'          => (int) ($_GET['id'] ?? 0),
        'escrito'     => $_GET['n'] ?? '',
        'sugerencia'  => $_GET['s'] ?? '',
    ];
    $error = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Editoriales</title>
    <link rel="icon" href="ico.ico">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="admin/admin.css">
    <link rel="stylesheet" href="admin/editoriales-asistente.css">
    <style>
        /* Específico de esta pantalla. Todo lo demás —colores, botones,
           tarjetas, avisos— viene de admin/admin.css. */

        /* ---- Vista previa de la portada ---- */
        .pp-marco {
            background: #fff;
            border-radius: var(--r);
            padding: 20px 16px;
        }
        .pp-titulo {
            text-align: center;
            font-size: var(--t-nota);
            font-weight: 800;
            letter-spacing: 1.8px;
            color: #1a1a1a;
            margin-bottom: 16px;
        }
        /* Misma rejilla que la portada: 5 por fila. Un hueco aquí es
           un hueco allí. */
        .pp-fila {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            align-items: center;
            justify-items: center;
            gap: 10px 14px;
        }
        .pp-fila + .pp-fila { margin-top: 12px; }
        .pp-logo {
            width: 100%;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .pp-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }

        /* Casilla ocupada: se pulsa para intercambiar posiciones. */
        .pp-ocupado {
            background: transparent;
            border: 1px solid transparent;
            border-radius: var(--r);
            cursor: pointer;
            padding: 4px;
            transition: var(--paso);
        }
        .pp-ocupado:hover { border-color: var(--rojo); background: #fdf2f2; }
        .pp-ocupado.seleccionada {
            border-color: var(--rojo-hondo);
            background: #fce9e9;
            box-shadow: 0 0 0 3px rgba(200, 16, 46, .15);
        }
        /* El número va dentro de la casilla, no colgando fuera: con el
           gap de la rejilla, dos casillas contiguas lo encimaban. */
        .pp-pos {
            position: absolute;
            top: 2px; left: 2px;
            width: 17px; height: 17px;
            border-radius: 50%;
            background: rgba(26, 26, 26, .8);
            color: #fff;
            font-size: 9.5px;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .pp-ocupado.seleccionada .pp-pos { background: var(--rojo-hondo); }

        .pp-hueco { border: 1.5px dashed #d8d8dc; border-radius: var(--r); }
        .pp-hueco span {
            color: #c4c4c8;
            font-size: var(--t-nota);
            font-weight: 600;
            letter-spacing: .6px;
            text-transform: uppercase;
        }

        /* Selector de formato: 5 o 10 espacios. */
        .pp-formato {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 3px;
            background: var(--estante-alto);
            border: 1px solid var(--linea);
            border-radius: var(--r-pil);
            padding: 3px 4px 3px 12px;
        }
        .pp-formato-txt {
            font-size: var(--t-nota);
            font-weight: 700;
            letter-spacing: .9px;
            text-transform: uppercase;
            color: var(--texto-bajo);
            margin-right: 4px;
        }
        .pp-formato-form { margin: 0; }
        .pp-formato-btn {
            background: transparent;
            color: var(--texto-medio);
            border: none;
            border-radius: var(--r-pil);
            padding: 4px 13px;
            font-size: var(--t-nota);
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            cursor: pointer;
            transition: var(--paso);
        }
        .pp-formato-btn:hover { color: var(--texto); }
        .pp-formato-btn.encendido { background: var(--rojo); color: #fff; }

        /* ---- Edición en línea ---- */
        .ed-editar {
            display: none;
            padding: 16px 0 20px 18px;
            border-left: 2px solid var(--rojo);
            margin: 0 0 6px 2px;
        }
        .ed-editar.abierta { display: block; }
        .ed-form {
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin: 0;
        }
        .ed-form .campo { flex: 1 1 190px; margin-bottom: 0; }
        .ed-acciones { display: flex; gap: 8px; }

        /* ---- Sugerencia de nombre parecido ---- */
        .sug-acciones { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
        .sug-form { margin: 0; }
        .sug-form label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: var(--r);
            padding: 9px 15px;
            font-size: var(--t-nota);
            font-weight: 700;
            cursor: pointer;
            transition: var(--paso);
        }
        .sug-form input[type=file] { display: none; }
    </style>
</head>
<body>

<?php
$titulo = 'Editoriales';
require __DIR__ . '/admin/cabecera.php';
?>

</div>

<main class="pagina">

    <p class="intro">
        Registra las editoriales del catálogo y elige cuáles aparecen en la portada.
    </p>

    <?php if ($msg): ?>
        <div class="aviso aviso-ok" id="aviso-flotante">
            <i class="bi bi-check-circle-fill"></i><span><?= $msg ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="aviso aviso-error" id="aviso-flotante">
            <i class="bi bi-exclamation-circle-fill"></i><span><?= $error ?></span>
        </div>
    <?php endif; ?>

    <?php if ($sugerencia): ?>
    <!-- El nombre escrito se parece a una editorial conocida. En vez de
         guardarlo en silencio o rechazarlo sin mas, se pregunta: corregir
         un dato ambiguo por cuenta propia destruye la trazabilidad. -->
    <div class="aviso aviso-atencion">
        <i class="bi bi-question-circle-fill"></i>
        <div>
            <p>
                ¿Quisiste decir <strong><?= htmlspecialchars($sugerencia) ?></strong>?
                Escribiste «<?= htmlspecialchars($nombre_rechazado) ?>», que se parece a
                una editorial ya conocida. Si la registras tal cual, el catálogo quedará
                con dos entradas para la misma empresa.
            </p>
            <div class="sug-acciones">
                <form method="POST" enctype="multipart/form-data" class="sug-form">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="nombre" value="<?= htmlspecialchars($sugerencia) ?>">
                    <input type="hidden" name="confirmar" value="1">
                    <label class="btn btn-rojo">
                        <input type="file" name="imagen" accept="image/*"
                               onchange="this.form.submit()">
                        <i class="bi bi-check-lg"></i>
                        Usar «<?= htmlspecialchars($sugerencia) ?>» y subir logo
                    </label>
                </form>
                <form method="POST" enctype="multipart/form-data" class="sug-form">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="nombre" value="<?= htmlspecialchars($nombre_rechazado) ?>">
                    <input type="hidden" name="confirmar" value="1">
                    <label class="btn btn-plano">
                        <input type="file" name="imagen" accept="image/*"
                               onchange="this.form.submit()">
                        No, es otra editorial
                    </label>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================ VISTA PREVIA
         Reproduce la rejilla real de la portada (cinco por fila).
         Cada casilla es un boton: se pulsa una y despues otra para
         intercambiar posiciones. -->
    <section class="bloque">
        <div class="bloque-cab">
            <h3>Vista previa de la portada</h3>

            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="pildora <?= $portada_completa ? 'exito' : '' ?>">
                    <?php if ($portada_completa): ?>
                        <i class="bi bi-check-circle-fill"></i>
                        <?= $n_destacadas ?> de <?= $formato ?> · completa
                    <?php else: ?>
                        <i class="bi bi-circle-half"></i>
                        <?= $n_destacadas ?> de <?= $formato ?> ·
                        <?= $espacios_libres ?> libre<?= $espacios_libres > 1 ? 's' : '' ?>
                    <?php endif; ?>
                </span>

                <!-- Define cuantos espacios tiene la portada. La base
                     impide destacar mas de los elegidos. -->
                <div class="pp-formato">
                    <span class="pp-formato-txt">Formato</span>
                    <?php foreach (FORMATOS_VALIDOS as $opcion): ?>
                        <form method="POST" class="pp-formato-form"
                              <?php if ($opcion < $n_destacadas): ?>
                              onsubmit="return confirm('Al pasar a <?= $opcion ?> espacios, <?= $n_destacadas - $opcion ?> editorial(es) dejaran de estar destacadas. Seguiran registradas. Continuar?')"
                              <?php endif; ?>>
                            <input type="hidden" name="accion" value="formato">
                            <input type="hidden" name="valor" value="<?= $opcion ?>">
                            <button type="submit"
                                    class="pp-formato-btn <?= $formato === $opcion ? 'encendido' : '' ?>">
                                <?= $opcion ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="pp-marco">
            <p class="pp-titulo">Editoriales destacadas</p>
            <?php for ($fila = 0; $fila < $filas_portada; $fila++): ?>
                <div class="pp-fila">
                    <?php for ($col = 1; $col <= 5; $col++):
                        $posicion = $fila * 5 + $col;
                        $ed_pos = $por_posicion[$posicion] ?? null; ?>
                        <?php if ($ed_pos): ?>
                            <button type="button"
                                    class="pp-logo pp-ocupado"
                                    data-pos="<?= $posicion ?>"
                                    data-nombre="<?= htmlspecialchars($ed_pos['nombre']) ?>"
                                    title="<?= htmlspecialchars($ed_pos['nombre']) ?> · posición <?= $posicion ?>">
                                <img src="uploads/<?= htmlspecialchars($ed_pos['imagen']) ?>"
                                     alt="<?= htmlspecialchars($ed_pos['nombre']) ?>">
                                <span class="pp-pos"><?= $posicion ?></span>
                            </button>
                        <?php else: ?>
                            <div class="pp-logo pp-hueco"><span>libre</span></div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endfor; ?>
        </div>

        <!-- El intercambio viaja como una peticion normal: dos
             posiciones que se permutan dentro de una transaccion. -->
        <form method="POST" id="form-mover" class="oculto">
            <input type="hidden" name="accion" value="mover">
            <input type="hidden" name="origen" id="mov-origen">
            <input type="hidden" name="destino" id="mov-destino">
        </form>

        <p class="nota" id="pp-ayuda" style="margin-top:14px">
            <?php if ($n_destacadas > 1): ?>
                Pulsa una editorial y luego otra para <strong>intercambiar</strong> sus posiciones.
            <?php endif; ?>
            La portada coloca <strong>5 por fila</strong>: el contenedor mide 1170&nbsp;px
            y cada logo ocupa 210&nbsp;px, por eso los formatos son <strong>5</strong> y
            <strong>10</strong>.
            <?php if ($formato === 10 && $total_editoriales < 10): ?>
                Hay <?= $total_editoriales ?> editoriales registradas: faltan
                <?= 10 - $total_editoriales ?> para llenar las diez.
            <?php endif; ?>
        </p>
    </section>

    <div class="dos-columnas">

        <!-- ======================================== ALTA -->
        <section class="bloque">
            <div class="bloque-cab">
                <h3>Registrar editorial</h3>
            </div>

            <form method="POST" enctype="multipart/form-data" id="alta-editorial">
                <input type="hidden" name="accion" value="crear">

                <div class="campo">
                    <label for="nombre-nuevo">Nombre</label>
                    <input type="text" id="nombre-nuevo" name="nombre"
                           placeholder="Ej. Marvel Comics" required>
                </div>

                <div class="campo">
                    <label for="logo-nuevo">Logo</label>
                    <label class="subir" id="lbl-editorial-img">
                        <input type="file" id="logo-nuevo" name="imagen"
                               accept="image/png,image/jpeg,image/webp,image/gif" required>
                        <img id="prev-editorial" class="ed-manual-previa" src="" alt="Vista previa del logo elegido"
                             style="display:none;max-height:54px;max-width:100%;object-fit:contain">
                        <i class="bi bi-image" id="icono-subir"></i>
                        <span id="texto-subir">Elegir imagen</span>
                    </label>
                    <p class="ed-ayuda">PNG, JPG, WebP o GIF · hasta 5 MB. También puedes elegir un logo propuesto.</p>
                </div>

                <button type="submit" class="btn btn-rojo" style="width:100%;justify-content:center">
                    <i class="bi bi-check-lg"></i> Registrar editorial
                </button>
            </form>
        </section>

        <!-- ======================================== LISTADO -->
        <section class="bloque">
            <div class="bloque-cab">
                <h3>Registradas</h3>
                <span class="cuenta"><?= $total_editoriales ?> en total</span>
            </div>

            <div class="lista">
                <?php if ($editoriales->num_rows === 0): ?>
                    <p class="sin-nada">No hay editoriales registradas aún.</p>
                <?php else: ?>
                    <?php while ($ed = $editoriales->fetch_assoc()):
                        $es_dest = (int) $ed['destacada'] === 1;
                        // Se bloquea cuando la portada ya esta llena para
                        // el formato elegido: o cabe o no cabe.
                        $bloqueado = !$es_dest && $espacios_libres <= 0;
                        if ($bloqueado) {
                            $titulo_btn = 'La portada ya tiene sus ' . $formato . ' editoriales';
                        } elseif ($es_dest) {
                            $titulo_btn = 'Quitar de la portada';
                        } else {
                            $titulo_btn = 'Mostrar en la portada';
                        }
                        $abierta = $sug_edit && $sug_edit['id'] === (int) $ed['id'];
                    ?>
                    <div class="fila <?= $es_dest ? 'destacado' : '' ?>">
                        <img src="uploads/<?= htmlspecialchars($ed['imagen']) ?>"
                             alt="<?= htmlspecialchars($ed['nombre']) ?>"
                             style="width:54px;height:34px;object-fit:contain;background:#fff;border-radius:4px;padding:3px">

                        <div class="fila-centro">
                            <p class="fila-nombre">
                                <?= htmlspecialchars($ed['nombre']) ?>
                                <?php if ($es_dest): ?>
                                    <span class="pildora roja">
                                        <i class="bi bi-star-fill"></i>
                                        posición <?= (int) $ed['orden_destacada'] ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <form method="POST" style="margin:0">
                            <input type="hidden" name="accion" value="destacar">
                            <input type="hidden" name="id" value="<?= $ed['id'] ?>">
                            <input type="hidden" name="valor" value="<?= $es_dest ? 0 : 1 ?>">
                            <button type="submit"
                                    class="icono <?= $es_dest ? 'encendido' : '' ?>"
                                    <?= $bloqueado ? 'disabled' : '' ?>
                                    title="<?= $titulo_btn ?>">
                                <i class="bi <?= $es_dest ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            </button>
                        </form>

                        <button type="button" class="icono"
                                onclick="alternarEdicion(<?= $ed['id'] ?>)"
                                title="Editar nombre o logo">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <form method="POST" style="margin:0"
                              onsubmit="return confirm('¿Eliminar esta editorial?')">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $ed['id'] ?>">
                            <button type="submit" class="icono riesgo" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Edicion en linea, bajo la fila. -->
                    <div class="ed-editar <?= $abierta ? 'abierta' : '' ?>" id="edit-<?= $ed['id'] ?>">
                        <?php if ($abierta): ?>
                            <div class="aviso aviso-atencion" style="margin-bottom:14px">
                                <i class="bi bi-question-circle-fill"></i>
                                <span>
                                    Escribiste «<?= htmlspecialchars($sug_edit['escrito']) ?>».
                                    ¿Quisiste decir <strong><?= htmlspecialchars($sug_edit['sugerencia']) ?></strong>?
                                </span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="ed-form">
                            <input type="hidden" name="accion" value="editar">
                            <input type="hidden" name="id" value="<?= $ed['id'] ?>">

                            <div class="campo">
                                <label for="nom-<?= $ed['id'] ?>">Nombre</label>
                                <input type="text" id="nom-<?= $ed['id'] ?>" name="nombre"
                                       value="<?= htmlspecialchars($abierta
                                           ? $sug_edit['sugerencia'] : $ed['nombre']) ?>" required>
                            </div>

                            <div class="campo">
                                <label for="img-<?= $ed['id'] ?>">Logo nuevo (opcional)</label>
                                <input type="file" id="img-<?= $ed['id'] ?>" name="imagen"
                                       accept="image/png,image/jpeg,image/webp,image/gif"
                                       style="font-size:var(--t-nota);color:var(--texto-medio)">
                            </div>

                            <div class="ed-acciones">
                                <button type="submit" class="btn btn-rojo"
                                        <?= $abierta ? 'name="confirmar" value="1"' : '' ?>>
                                    <i class="bi bi-check-lg"></i>
                                    <?= $abierta ? 'Guardar igual' : 'Guardar' ?>
                                </button>
                                <button type="button" class="btn btn-plano"
                                        onclick="alternarEdicion(<?= $ed['id'] ?>)">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </section>

    </div>
</main>

<script src="js/editoriales-asistente.js"></script>
<script>
// ---- Intercambio de posiciones en la vista previa ----
// Se pulsa una editorial (queda marcada) y luego otra: se permutan.
// Volver a pulsar la misma cancela la seleccion.
(function () {
    const casillas = document.querySelectorAll('.pp-ocupado');
    const ayuda = document.getElementById('pp-ayuda');
    const textoOriginal = ayuda ? ayuda.innerHTML : '';
    let origen = null;

    casillas.forEach(c => c.addEventListener('click', () => {
        const pos = c.dataset.pos;

        if (origen === null) {
            origen = pos;
            c.classList.add('seleccionada');
            if (ayuda) {
                ayuda.innerHTML = 'Moviendo <strong>' + c.dataset.nombre +
                    '</strong>. Pulsa la posición con la que quieres intercambiarla, ' +
                    'o púlsala otra vez para cancelar.';
            }
            return;
        }

        if (origen === pos) {               // cancelar
            origen = null;
            c.classList.remove('seleccionada');
            if (ayuda) ayuda.innerHTML = textoOriginal;
            return;
        }

        document.getElementById('mov-origen').value = origen;
        document.getElementById('mov-destino').value = pos;
        document.getElementById('form-mover').submit();
    }));
})();

// ---- Abrir y cerrar el formulario de edicion de cada fila ----
function alternarEdicion(id) {
    const caja = document.getElementById('edit-' + id);
    if (!caja) return;
    const abierta = caja.classList.toggle('abierta');
    if (abierta) {
        const campo = caja.querySelector('input[name="nombre"]');
        if (campo) campo.focus();
    }
}

// ---- El aviso se retira solo a los pocos segundos ----
document.querySelectorAll('#aviso-flotante').forEach(t => {
    setTimeout(() => {
        t.style.transition = 'opacity .5s ease';
        t.style.opacity = '0';
        setTimeout(() => t.remove(), 500);
    }, 4000);
});

</script>
</body>
</html>
