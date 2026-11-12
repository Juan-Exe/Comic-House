<?php
session_start();
require_once __DIR__ . '/controlador/miniaturas.php';

/* Géneros habituales del catálogo. Es una lista con salida: el
   formulario deja escribir uno nuevo en «Otro…», pero ofrecer los
   conocidos evita que el mismo género entre escrito de tres formas. */
const GENEROS = [
    'Superhéroes', 'Fantasía oscura', 'Ciencia ficción', 'Fantasía',
    'Acción', 'Aventura', 'Terror', 'Misterio', 'Drama', 'Comedia',
    'Romance', 'Histórico', 'Deportes', 'Slice of life', 'Cyberpunk',
    'Post-apocalíptico', 'Sobrenatural', 'Thriller',
];
include_once("modelo/conexion.php");
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include("controlador/eliminar.php");
include("controlador/registro_comcis.php");
$comics      = $conexion->query("SELECT * FROM comics ORDER BY id DESC");
$total       = $conexion->query("SELECT COUNT(*) as n FROM comics")->fetch_object()->n;
$editoriales = $conexion->query("SELECT * FROM editoriales ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House — Admin</title>
    <link rel="icon" href="ico.ico">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="admin/admin.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Montserrat, sans-serif;
            background: var(--papel);
            color: var(--texto);
            min-height: 100vh;
        }

        /* ── TOPBAR ── */
        /* La barra superior la define admin/admin.css; antes esta
           pantalla repetía su propia versión con un grid de tres
           columnas que centraba la navegación. */

        /* ── LAYOUT ── */
        .admin-layout {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            min-height: calc(100vh - 68px);
        }
        /* minmax(0,1fr) permite que la columna se encoja: sin eso la
           tabla fuerza el ancho y se sale de la pantalla. */
        @media (max-width: 1100px) {
            .admin-layout { grid-template-columns: 1fr; }
        }

        /* ── SIDEBAR ── */
        .sidebar-form {
            background: var(--estante);
            border-right: 1px solid var(--linea);
            padding: 24px 20px;
            overflow-y: auto;
        }
        .sidebar-form h2 {
            font-size: var(--t-titulo);
            font-weight: 700;
            color: var(--texto);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--linea);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .field input,
        .field textarea,
        .field input:focus,
        .field textarea:focus,
        .field select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }
        .field select option { background: var(--estante); }

        /* Portada upload personalizado */
        .portada-upload { position: relative; }
        .portada-btn {
            width: 100%;
            height: 130px;
            border: 2px dashed var(--texto-bajo);
            border-radius: 8px;
            background: var(--papel);
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center;
            gap: 8px;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            overflow: hidden;
            position: relative;
            font-size: unset;
            font-weight: unset;
            text-transform: unset;
            letter-spacing: unset;
            color: unset;
            margin-bottom: 0;
        }
        .portada-btn:hover { border-color: var(--rojo); background: var(--rojo-tenue); }
        .portada-btn i { font-size: var(--t-grande); color: var(--texto-bajo); pointer-events: none; }
        .portada-btn span { font-size: var(--t-nota); color: var(--texto-bajo); font-weight: 600; text-transform: uppercase; letter-spacing: .8px; pointer-events: none; }
        .portada-btn input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
            width: 100%; height: 100%;
        }
        .portada-preview {
            position: absolute; inset: 0;
            object-fit: cover;
            width: 100%; height: 100%;
            border-radius: 6px;
            display: none;
        }
        .portada-preview.visible { display: block; }
        .portada-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,.5);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        /* «Cambiar» solo cuando ya hay una portada elegida: antes
   aparecía siempre y pedía cambiar algo que no existía. */
        .portada-btn.con-imagen:hover .portada-overlay { display: flex; }
        .portada-overlay span { color: #fff; font-size: var(--t-nota); font-weight: 700; text-transform: uppercase; }

        /* ---- Grupo de opciones ----
           Para pocas opciones excluyentes (clasificación, tipo) un
           grupo de botones las muestra todas de un vistazo, en vez de
           esconderlas tras un desplegable. */
        .grupo-opciones { display: flex; gap: 6px; }
        .opcion {
            flex: 1 1 0;
            min-width: 0;
            padding: 9px 12px;
            background: var(--papel);
            border: 1px solid var(--linea);
            border-radius: var(--r);
            color: var(--texto-medio);
            font-family: Montserrat, sans-serif;
            font-size: var(--t-nota);
            font-weight: 700;
            cursor: pointer;
            transition: var(--paso);
        }
        .opcion:hover { border-color: var(--texto-bajo); color: var(--texto); }
        .opcion.activa {
            background: var(--rojo);
            border-color: var(--rojo);
            color: #fff;
        }
        .opcion.ancha { flex: 1 1 0; }

        /* ---- Desplegable propio ----
           El <select> nativo pinta su lista con el estilo del sistema,
           que desentona con el resto del panel. Se conserva el select
           por accesibilidad y se le superpone la flecha de la casa. */
        .select-propio { position: relative; }
        .select-propio select {
            width: 100%;
            appearance: none;
            -webkit-appearance: none;
            padding-right: 34px;
            cursor: pointer;
        }
        .select-propio > i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--texto-bajo);
            font-size: 12px;
            pointer-events: none;
        }
        .select-propio select:focus + i { color: var(--rojo); }

        /* ---- Campo con búsqueda ----
           Dieciocho géneros no caben cómodos en una lista nativa. */
        .buscador { position: relative; }
        .buscador > i {
            position: absolute;
            right: 12px;
            top: 17px;
            color: var(--texto-bajo);
            font-size: 12px;
            cursor: pointer;
        }
        .buscador input { padding-right: 34px; }
        .buscador-lista {
            position: absolute;
            top: calc(100% + 4px);
            left: 0; right: 0;
            z-index: 60;
            max-height: 232px;
            overflow-y: auto;
            margin: 0;
            padding: 5px;
            list-style: none;
            background: var(--estante);
            border: 1px solid var(--linea);
            border-radius: var(--r);
            box-shadow: 0 10px 26px rgba(0,0,0,.5);
        }
        .buscador-lista li {
            padding: 8px 11px;
            border-radius: 5px;
            color: var(--texto-medio);
            font-size: var(--t-nota);
            cursor: pointer;
        }
        .buscador-lista li:hover,
        .buscador-lista li:focus { background: var(--estante-alto); color: var(--texto); outline: none; }
        .buscador-lista li.marcada { background: var(--rojo-tenue); color: var(--rojo); }
        .buscador-lista .sin-resultado {
            padding: 10px 11px;
            color: var(--texto-bajo);
            font-size: var(--t-nota);
            cursor: default;
        }

        /* La descripción no se estira: el tirador dejaba deformar la
           columna entera. */
        #f-descripcion { resize: none; height: 104px; overflow-y: auto; }
        .field textarea { resize: none; }

        /* Rango de capítulos: dos números y la palabra que los une. */
        .rango-caps { display: flex; align-items: center; gap: 9px; }
        .rango-caps input { flex: 1; min-width: 0; }
        .rango-sep { color: var(--texto-bajo); font-size: var(--t-nota); flex-shrink: 0; }
        .rango-caps input:disabled { opacity: .45; cursor: not-allowed; }

        /* Explica la regla en vez de dejar que se descubra fallando. */
        .pista {
            color: var(--texto-bajo);
            font-size: 11.5px;
            line-height: 1.45;
            margin-top: 6px;
        }

        .oculto { display: none; }

        /* ── TABLE PANEL ── */
        .table-panel {
            padding: 24px;
            overflow-x: auto;
            background: var(--papel);
        }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .panel-header h2 {
            font-size: var(--t-nota);
            font-weight: 700;
            color: var(--texto-medio);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .comic-count {
            background: var(--rojo);
            color: #fff;
            font-size: var(--t-nota);
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .5px;
        }

        table { width: 100%; border-collapse: collapse; font-size: var(--t-nota); }
        thead tr { border-bottom: 2px solid var(--rojo); }
        thead th {
            padding: 10px 10px;
            text-align: left;
            font-size: var(--t-nota);
            font-weight: 700;
            color: var(--texto-medio);
            color: var(--texto-bajo);
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--estante-alto); transition: background .15s; }
        tbody tr:hover { background: var(--estante); }
        tbody td { padding: 10px; vertical-align: middle; color: var(--texto-medio); }

        .portada-thumb {
            width: 46px;
            height: 64px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid var(--texto-bajo);
            display: block;
        }
        .badge-tipo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: var(--t-nota);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        /* Cómic lleva el acento de marca; manga se distingue en gris, para
           que los dos tipos se lean sin que el rojo pierda significado. */
        .badge-comic { background: var(--rojo-tenue); color: var(--rojo); border: 1px solid var(--rojo-linea); }
        .badge-manga { background: var(--estante-alto); color: var(--texto-medio); border: 1px solid var(--linea); }

        .desc-cell {
            max-width: 170px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--texto-bajo);
            font-size: var(--t-nota);
        }
        .actions-cell { display: flex; gap: 6px; white-space: nowrap; }
        .btn-imprescindible {
            background: rgba(100,100,110,.15);
            color: var(--texto-bajo);
            border: 1px solid rgba(100,100,110,.25);
            border-radius: 5px;
            padding: 5px 9px;
            font-size: var(--t-base);
            cursor: pointer;
            transition: all .2s;
        }
        .btn-imprescindible:hover { border-color: var(--rojo-linea); color: var(--rojo); background: var(--rojo-tenue); }
        .btn-imprescindible.active { background: var(--rojo-tenue); color: var(--rojo); border-color: var(--rojo-linea); }
        .btn-imprescindible.active:hover { background: rgba(239,68,68,.15); color: var(--rojo); border-color: rgba(239,68,68,.4); }
        .btn-action {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: var(--t-nota);
            font-weight: 700;
            font-family: Montserrat, sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: opacity .2s;
            cursor: pointer;
            border: none;
        }
        .btn-action:hover { opacity: .75; }
        .btn-edit   { background: var(--estante-alto); color: var(--texto-medio); border: 1px solid var(--linea); }
        .btn-delete { background: rgba(239,68,68,.12);  color: var(--rojo); border: 1px solid rgba(239,68,68,.3); }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.75); z-index: 9998;
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--estante);
            border: 1px solid var(--texto-bajo);
            border-radius: 12px;
            padding: 32px 36px;
            width: 360px;
            text-align: center;
        }
        .modal-icon { font-size: 44px; color: var(--rojo); margin-bottom: 14px; }
        .modal-box h3 { font-size: var(--t-titulo); margin-bottom: 8px; color: #fff; }
        .modal-box p  { font-size: var(--t-nota); color: var(--texto-medio); margin-bottom: 26px; line-height: 1.5; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .btn-confirm-del {
            padding: 9px 24px; background: var(--rojo); color: #fff;
            border: none; border-radius: 6px; font-weight: 700;
            font-family: Montserrat, sans-serif; cursor: pointer;
            text-decoration: none; font-size: var(--t-nota);
        }
        .btn-cancel-m {
            padding: 9px 24px; background: var(--papel); color: var(--texto-medio);
            border: 1px solid var(--texto-bajo); border-radius: 6px; font-weight: 700;
            font-family: Montserrat, sans-serif; cursor: pointer; font-size: var(--t-nota);
        }

        /* Mientras el modal esta abierto, la pagina de fondo no se
           desplaza: evita la segunda barra del catalogo. */
        body.modal-abierto { overflow: hidden; }

        /* ── MODAL METADATOS ── */
        /* Reutiliza .modal-overlay y .modal-box; solo cambia el ancho
           y el contenido, que aqui es una ficha y no una pregunta. */
        .modal-meta-box {
            width: 560px; max-width: calc(100vw - 32px);
            text-align: left; padding: 0;
            display: flex; flex-direction: column;
            /* Limita el alto para que no se corte en pantallas bajas.
               El scroll vive dentro del cuerpo, no en el overlay, asi
               solo aparece UNA barra y nunca la del catalogo de atras. */
            max-height: calc(100vh - 40px);
        }
        .meta-cabecera {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px; border-bottom: 1px solid var(--linea);
            flex-shrink: 0;
        }
        .meta-cabecera h3 { margin: 0; font-size: var(--t-titulo); color: #fff; }
        .meta-cerrar {
            background: none; border: none; color: var(--texto-medio);
            font-size: 15px; cursor: pointer; padding: 4px 6px; line-height: 1;
        }
        .meta-cerrar:hover { color: var(--texto); }

        .meta-cuerpo {
            padding: 18px 22px;
            overflow-y: auto;
            min-height: 0;      /* necesario para que flex permita encoger */
        }

        .meta-seccion {
            display: flex; align-items: center; gap: 7px;
            font-size: 11px; font-weight: 700; letter-spacing: .8px;
            text-transform: uppercase; color: var(--ok);
            margin: 0 0 12px;
        }
        .meta-seccion.decide {
            color: var(--alerta);
            margin-top: 22px; padding-top: 18px;
            border-top: 1px solid var(--linea);
        }

        /* Lo verificado: etiqueta a la izquierda, valor a la derecha */
        .meta-grid { display: flex; flex-direction: column; gap: 9px; }
        .meta-fila { display: flex; gap: 14px; align-items: flex-start; }
        .meta-fila > .meta-et { flex-shrink: 0; width: 92px; }
        .meta-et {
            font-size: var(--t-nota); color: var(--texto-medio);
            padding-top: 2px;
        }
        .meta-val {
            font-size: var(--t-base); color: var(--texto);
            flex: 1; min-width: 0; word-break: break-word;
        }
        .meta-val.falta { color: var(--texto-bajo); font-style: italic; }
        .meta-val img {
            width: 54px; height: 80px; object-fit: cover;
            border-radius: 4px; background: var(--papel);
        }
        .meta-desc {
            font-size: var(--t-nota); line-height: 1.5;
            color: var(--texto-medio);
        }

        /* Lo que requiere decision */
        .meta-decide { display: flex; flex-direction: column; gap: 14px; }
        .meta-campo { display: flex; gap: 14px; align-items: flex-start; }
        .meta-campo > .meta-et { flex-shrink: 0; width: 92px; }
        .meta-campo > div { flex: 1; min-width: 0; }

        .meta-ops { display: flex; gap: 6px; flex-wrap: wrap; }
        .meta-op {
            background: var(--papel); color: var(--texto-medio);
            border: 1px solid var(--linea); border-radius: 6px;
            padding: 6px 14px; font-family: Montserrat, sans-serif;
            font-size: var(--t-nota); font-weight: 600; cursor: pointer;
        }
        .meta-op:hover { border-color: var(--texto-bajo); color: var(--texto); }
        .meta-op.activa {
            background: var(--rojo-tenue); color: var(--rojo);
            border-color: var(--rojo-linea);
        }

        #meta-genero {
            width: 100%; background: var(--papel); color: var(--texto);
            border: 1px solid var(--linea); border-radius: 6px;
            padding: 7px 10px; font-family: Montserrat, sans-serif;
            font-size: var(--t-nota);
        }

        .meta-pista {
            display: block; margin-top: 5px;
            font-size: 11px; color: var(--texto-bajo); line-height: 1.4;
        }

        .modal-meta-box .modal-actions {
            padding: 16px 22px; border-top: 1px solid var(--linea);
            justify-content: flex-end; margin: 0;
            flex-shrink: 0;
        }

        /* Editorial ausente: la unica salida es crearla, nunca sustituirla */
        .meta-crear {
            background: var(--alerta-tenue); color: var(--alerta);
            border: 1px solid rgba(217,154,43,.35); border-radius: 6px;
            padding: 5px 12px; font-family: Montserrat, sans-serif;
            font-size: 11px; font-weight: 700; cursor: pointer;
            margin-top: 6px;
        }
        .meta-crear:hover { background: var(--alerta); color: var(--papel); }

        /* ── TOAST ── */
        .toast-notif {
            position: fixed; top: 88px; right: 24px;
            background: var(--estante); color: #fff;
            padding: 13px 18px; border-radius: 8px;
            display: flex; align-items: center; gap: 10px;
            font-size: var(--t-nota); font-weight: 600;
            box-shadow: 0 4px 24px rgba(0,0,0,.6);
            z-index: 9999; animation: toastIn .3s ease;
        }
        .toast-notif.ok    { border-left: 4px solid var(--ok); }
        .toast-notif.error { border-left: 4px solid var(--rojo); }
        .toast-notif.warn  { border-left: 4px solid var(--alerta); }
        .toast-hide { animation: toastOut .4s ease forwards !important; }
        @keyframes toastIn  { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
        @keyframes toastOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(40px)} }
    </style>
</head>
<body>

<!-- TOPBAR -->
<?php
$titulo = 'Catálogo';
require __DIR__ . '/admin/cabecera.php';
?>

</div>

<!-- TOAST -->
<?php
$msgs = [
    'ok'            => ['tipo' => 'ok',    'texto' => 'Cómic registrado correctamente'],
    'error'         => ['tipo' => 'error', 'texto' => 'Error al registrar el cómic'],
    'upload_error'  => ['tipo' => 'error', 'texto' => 'Error al subir la imagen'],
    'campos_vacios' => ['tipo' => 'warn',  'texto' => 'Todos los campos son obligatorios'],
    // Rechazos de la base traducidos a un aviso concreto: la persona debe
    // saber que regla freno la operacion, no solo que "fallo".
    'titulo_duplicado'       => ['tipo' => 'error', 'texto' => 'Ya existe un cómic con ese título'],
    'editorial_inexistente'  => ['tipo' => 'error', 'texto' => 'La editorial indicada no existe'],
    'valor_fuera_de_dominio' => ['tipo' => 'error', 'texto' => 'Año o clasificación fuera de los valores permitidos'],
    'anio'      => ['tipo' => 'error', 'texto' => 'El año debe tener cuatro cifras y no puede ser futuro'],
    'capitulos' => ['tipo' => 'error', 'texto' => 'Revisa el rango de capítulos: solo números, y el final no puede ser menor que el inicio'],
    // Borrado: antes se imprimia como texto suelto sobre el layout.
    'eliminado'      => ['tipo' => 'ok',    'texto' => 'Cómic eliminado del catálogo'],
    'no_encontrado'  => ['tipo' => 'warn',  'texto' => 'Ese cómic ya no existe en el catálogo'],
    'error_eliminar' => ['tipo' => 'error', 'texto' => 'No se pudo eliminar el cómic'],
];
$msg = $_GET['msg'] ?? '';
if (isset($msgs[$msg])): $m = $msgs[$msg];
?>
<div id="toast-crud" class="toast-notif <?= $m['tipo'] ?>">
    <?php if ($m['tipo'] === 'ok'): ?>
        <i class="bi bi-check-circle-fill" style="color:var(--ok);font-size: var(--t-titulo)"></i>
    <?php elseif ($m['tipo'] === 'warn'): ?>
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--alerta);font-size: var(--t-titulo)"></i>
    <?php else: ?>
        <i class="bi bi-x-circle-fill" style="color:var(--rojo);font-size: var(--t-titulo)"></i>
    <?php endif; ?>
    <?= htmlspecialchars($m['texto']) ?>
</div>
<script>
    setTimeout(() => {
        const t = document.getElementById('toast-crud');
        if (t) { t.classList.add('toast-hide'); setTimeout(() => t.remove(), 400); }
    }, 3500);
</script>
<?php endif; ?>

<!-- MODAL ELIMINAR -->
<div class="modal-overlay" id="modal-eliminar">
    <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-trash3-fill"></i></div>
        <h3>¿Eliminar cómic?</h3>
        <p>Esta acción no se puede deshacer. El cómic y sus datos serán eliminados permanentemente.</p>
        <div class="modal-actions">
            <button class="btn-cancel-m" onclick="cerrarModal()">Cancelar</button>
            <a id="btn-confirm-del" href="#" class="btn-confirm-del">Sí, eliminar</a>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR METADATOS -->
<!-- Separa lo VERIFICADO en la fuente de lo que REQUIERE DECISION.
     Nada se escribe en el formulario hasta que se confirma aqui. -->
<div class="modal-overlay" id="modal-meta">
    <div class="modal-box modal-meta-box">

        <div class="meta-cabecera">
            <h3>Datos encontrados</h3>
            <button type="button" class="meta-cerrar" id="meta-cerrar" aria-label="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="meta-cuerpo">

            <p class="meta-seccion"><i class="bi bi-check-circle-fill"></i> Verificado en la fuente</p>
            <div class="meta-grid" id="meta-verificado"></div>

            <p class="meta-seccion decide"><i class="bi bi-person-fill"></i> Requiere tu revisión</p>
            <div class="meta-decide">

                <div class="meta-campo">
                    <span class="meta-et">Tipo</span>
                    <div>
                        <div class="meta-ops" id="meta-tipo"></div>
                        <span class="meta-pista" id="meta-tipo-pista"></span>
                    </div>
                </div>

                <div class="meta-campo">
                    <span class="meta-et">Género</span>
                    <div>
                        <select id="meta-genero"></select>
                        <span class="meta-pista" id="meta-genero-pista"></span>
                    </div>
                </div>

                <div class="meta-campo">
                    <span class="meta-et">Clasificación</span>
                    <div>
                        <div class="meta-ops" id="meta-clasif"></div>
                        <span class="meta-pista">Sin sugerencia: es control parental, lo decides tú</span>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-cancel-m" id="meta-cancelar">Cancelar</button>
            <button type="button" class="btn-confirm-del" id="meta-usar">Usar estos datos</button>
        </div>
    </div>
</div>

<!-- LAYOUT -->
<div class="admin-layout">

    <!-- FORMULARIO -->
    <aside class="sidebar-form">
        <h2><i class="bi bi-plus-circle-fill"></i> Registrar cómic</h2>
        <!-- autocomplete="off": el navegador proponía lo escrito antes
             y pintaba una flecha que parecía un desplegable. -->
        <form enctype="multipart/form-data" method="POST" autocomplete="off">

            <div class="field">
                <label for="f-titulo">Título</label>
                <!-- Al escribir, el sistema busca los metadatos solo.
                     El indicador vive DENTRO del campo para no alterar
                     la altura ni el ritmo del formulario. -->
                <div class="campo-con-estado">
                    <input type="text" id="f-titulo" name="titulo"
                           placeholder="Ej. House of M" autocomplete="off" required>
                    <span class="estado-busca" id="estado-meta" aria-hidden="true"></span>
                </div>
                <p class="nota nota-meta" id="aviso-meta" hidden></p>
                <div id="resultados-meta" class="resultados-meta"></div>
            </div>

            <div class="field">
                <label for="f-editorial">Editorial</label>
                <div class="select-propio">
                    <select name="editorial" id="f-editorial" required>
                        <option value="" disabled selected hidden>Elegir editorial</option>
                        <?php
                        $editoriales->data_seek(0);
                        while ($ed = $editoriales->fetch_assoc()):
                        ?>
                            <option value="<?= htmlspecialchars($ed['nombre']) ?>"><?= htmlspecialchars($ed['nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <i class="bi bi-chevron-down"></i>
                </div>
            </div>

            <!-- Clasificación y tipo tienen pocas opciones excluyentes:
                 un grupo de botones las muestra todas de un vistazo, sin
                 abrir un desplegable para elegir entre cuatro. -->
            <div class="field">
                <label>Clasificación</label>
                <div class="grupo-opciones" id="g-clasificacion">
                    <button type="button" class="opcion" data-valor="ATP">ATP</button>
                    <button type="button" class="opcion" data-valor="+13">+13</button>
                    <button type="button" class="opcion" data-valor="+16">+16</button>
                    <button type="button" class="opcion" data-valor="+18">+18</button>
                </div>
                <input type="hidden" name="clasificacion" id="f-clasificacion" required>
            </div>

            <!-- El tipo va antes que los capítulos: un manga y un cómic
                 no se numeran igual, así que decide lo que viene abajo. -->
            <div class="field">
                <label>Tipo</label>
                <div class="grupo-opciones" id="g-tipo">
                    <button type="button" class="opcion ancha" data-valor="Comic">Cómic</button>
                    <button type="button" class="opcion ancha" data-valor="Manga">Manga</button>
                </div>
                <input type="hidden" name="tipo" id="f-tipo" required>
            </div>

            <!-- Todo lo que depende del tipo queda oculto hasta elegirlo. -->
            <div id="segun-tipo" class="oculto">

                <div class="field">
                    <label for="f-estado">Publicación</label>
                    <select name="estado_publicacion" id="f-estado" onchange="ajustarEstado()">
                        <option value="En publicacion">En emisión</option>
                        <option value="Publicacion Terminada">Terminada</option>
                        <option value="Tomo unico">Tomo único</option>
                    </select>
                </div>

                <div class="field" id="campo-capitulos">
                    <label>Capítulos</label>
                    <div class="rango-caps">
                        <input type="text" name="capitulo_desde" id="f-cap-desde"
                               inputmode="numeric" placeholder="Desde" value="1"
                               autocomplete="off">
                        <span class="rango-sep">a</span>
                        <input type="text" name="capitulo_hasta" id="f-cap-hasta"
                               inputmode="numeric" placeholder="Hasta"
                               autocomplete="off">
                    </div>
                    <p class="pista" id="pista-caps">Mientras siga en emisión,
                       el último número queda abierto.</p>
                </div>

            </div>

            <div class="field">
                <label for="f-anio">Año de publicación</label>
                <input type="text" id="f-anio" name="anio" inputmode="numeric"
                       placeholder="Ej. 2005" maxlength="4" autocomplete="off" required>
            </div>

            <!-- Lista de géneros habituales, con salida para uno nuevo:
                 así no se inventan variantes del mismo género. -->
            <div class="field">
                <label for="f-genero-busca">Género</label>
                <!-- Desplegable propio: con dieciocho géneros, la lista
                     nativa del navegador se vuelve incómoda y no deja
                     buscar. Aquí se escribe para filtrar, y lo que no
                     esté en la lista se guarda tal cual. -->
                <div class="buscador" id="caja-genero">
                    <input type="text" id="f-genero-busca" name="genero"
                           placeholder="Buscar o escribir un género"
                           autocomplete="off" required
                           onfocus="abrirGeneros()" oninput="filtrarGeneros()">
                    <i class="bi bi-chevron-down" onclick="alternarGeneros()"></i>
                    <ul class="buscador-lista oculto" id="lista-generos">
                        <?php foreach (GENEROS as $g): ?>
                            <li tabindex="0"><?= htmlspecialchars($g) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="field">
                <label for="f-descripcion">Descripción</label>
                <textarea name="descripcion" id="f-descripcion" rows="4"
                          placeholder="Sinopsis del cómic..." required></textarea>
            </div>

            <div class="field">
                <label>Portada</label>
                <div class="portada-upload">
                    <label class="portada-btn" id="portada-btn">
                        <img class="portada-preview" id="portada-preview" src="" alt="">
                        <i class="bi bi-image" id="portada-icon"></i>
                        <span id="portada-label">Elegir imagen</span>
                        <input type="file" name="portada" accept="image/*" id="portada-input">
                        <!-- Si la portada vino de la fuente ya esta en
                             uploads/ y solo viaja su nombre (HU-07). -->
                        <input type="hidden" name="portada_descargada" id="portada-descargada">
                        <!-- El rótulo «Cambiar» solo tiene sentido cuando
                             ya hay una imagen elegida. -->
                        <div class="portada-overlay"><span><i class="bi bi-arrow-repeat"></i> Cambiar</span></div>
                    </label>
                </div>
            </div>

            <button type="submit" name="btnregistrar" value="ok" class="btn btn-rojo" style="width:100%">
                <i class="bi bi-plus-lg"></i> Registrar cómic
            </button>
        </form>
    </aside>

    <!-- TABLA -->
    <main class="table-panel">
        <div class="panel-header">
            <h2><i class="bi bi-collection"></i> Catálogo</h2>
            <span class="comic-count"><?= $total ?> títulos</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Portada</th>
                    <th>Título</th>
                    <th>Editorial</th>
                    <th>Tipo</th>
                    <th>Clasif.</th>
                    <th>Caps.</th>
                    <th>Año</th>
                    <th>Género</th>
                    <th>Descripción</th>
                    <th>Imprescind.</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($datos = $comics->fetch_object()): ?>
                <tr>
                    <td style="color:var(--texto-bajo);font-size: var(--t-nota)"><?= $datos->id ?></td>
                    <td>
                        <img src="<?= htmlspecialchars(miniatura($datos->portada, 400)) ?>"
                             loading="lazy" decoding="async"
                             class="portada-thumb" alt="portada">
                    </td>
                    <td style="font-weight:700;color:#fff"><?= htmlspecialchars($datos->titulo) ?></td>
                    <td><?= htmlspecialchars($datos->editorial) ?></td>
                    <td>
                        <span class="badge-tipo <?= strtolower($datos->tipo) === 'manga' ? 'badge-manga' : 'badge-comic' ?>">
                            <?= htmlspecialchars($datos->tipo) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($datos->clasificacion) ?></td>
                    <td><?= htmlspecialchars($datos->capitulos) ?></td>
                    <td><?= htmlspecialchars($datos->anio) ?></td>
                    <td><?= htmlspecialchars($datos->genero) ?></td>
                    <td class="desc-cell" title="<?= htmlspecialchars($datos->descripcion) ?>">
                        <?= htmlspecialchars($datos->descripcion) ?>
                    </td>
                    <td style="text-align:center;">
                        <button class="btn-imprescindible <?= $datos->imprescindible ? 'active' : '' ?>"
                            onclick="toggleImprescindible(this, <?= $datos->id ?>)"
                            title="<?= $datos->imprescindible ? 'Quitar de imprescindibles' : 'Marcar como imprescindible' ?>">
                            <i class="bi bi-star<?= $datos->imprescindible ? '-fill' : '' ?>"></i>
                        </button>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <a href="modificar_comic.php?id=<?= $datos->id ?>" class="btn-action btn-edit">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            <button class="btn-action btn-delete"
                                onclick="abrirModal('crud.php?id=<?= $datos->id ?>')">
                                <i class="bi bi-trash3"></i> Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
</div>

<script>
/* ---- El formulario se adapta a lo que se registra ----
   El tipo de obra decide cómo se numera, y el estado de publicación
   decide si el último capítulo se conoce. Nada de eso se pregunta
   hasta que hace falta. */

function ajustarTipo() {
    const tipo = document.getElementById('f-tipo').value;
    const bloque = document.getElementById('segun-tipo');
    bloque.classList.toggle('oculto', tipo === '');
    if (tipo) ajustarEstado();
}

function ajustarEstado() {
    const estado = document.getElementById('f-estado').value;
    const tipo   = document.getElementById('f-tipo').value;
    const campo  = document.getElementById('campo-capitulos');
    const hasta  = document.getElementById('f-cap-hasta');
    const desde  = document.getElementById('f-cap-desde');
    const pista  = document.getElementById('pista-caps');

    // Un tomo único es una obra cerrada de una sola entrega.
    if (estado === 'Tomo unico') {
        campo.classList.add('oculto');
        desde.value = '1';
        hasta.value = '1';
        return;
    }
    campo.classList.remove('oculto');

    const enEmision = estado === 'En publicacion';
    hasta.disabled = enEmision;
    hasta.value = enEmision ? '' : hasta.value;
    hasta.placeholder = enEmision ? 'Sin cerrar' : 'Hasta';

    pista.textContent = enEmision
        ? 'Mientras siga en emisión, el último número queda abierto.'
        : (tipo === 'Manga'
            ? 'Número del primer y del último capítulo publicados.'
            : 'Número de la primera y la última grapa publicadas.');
}



/* ---- Grupos de opciones ----
   Clasificación y tipo se eligen con botones; el valor viaja en un
   campo oculto, para que el formulario se envíe igual que antes. */
function montarGrupo(idGrupo, idCampo, alElegir) {
    const grupo = document.getElementById(idGrupo);
    const campo = document.getElementById(idCampo);
    if (!grupo || !campo) return;

    grupo.addEventListener('click', (e) => {
        const btn = e.target.closest('.opcion');
        if (!btn) return;
        grupo.querySelectorAll('.opcion').forEach(b => b.classList.remove('activa'));
        btn.classList.add('activa');
        campo.value = btn.dataset.valor;
        if (alElegir) alElegir(btn.dataset.valor);
    });
}

/* ---- Género con búsqueda ----
   Se escribe para filtrar y se puede elegir de la lista o dejar un
   género nuevo escrito a mano. */
function abrirGeneros() {
    document.getElementById('lista-generos').classList.remove('oculto');
}

function cerrarGeneros() {
    document.getElementById('lista-generos').classList.add('oculto');
}

function alternarGeneros() {
    const lista = document.getElementById('lista-generos');
    lista.classList.toggle('oculto');
    if (!lista.classList.contains('oculto')) {
        document.getElementById('f-genero-busca').focus();
    }
}

function filtrarGeneros() {
    const escrito = document.getElementById('f-genero-busca').value.trim();
    const texto = escrito.toLowerCase();
    const lista = document.getElementById('lista-generos');
    lista.classList.remove('oculto');

    let visibles = 0;
    lista.querySelectorAll('li').forEach(li => {
        if (li.classList.contains('sin-resultado')) return;
        const cabe = li.textContent.toLowerCase().includes(texto);
        li.hidden = !cabe;
        if (cabe) visibles++;
    });

    // Un género nuevo es válido: se dice, en vez de dejar la lista vacía.
    let aviso = lista.querySelector('.sin-resultado');
    if (!visibles && texto) {
        if (!aviso) {
            aviso = document.createElement('li');
            aviso.className = 'sin-resultado';
            lista.appendChild(aviso);
        }
        aviso.textContent = 'Sin coincidencias · se guardará «' + escrito + '»';
        aviso.hidden = false;
    } else if (aviso) {
        aviso.hidden = true;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    montarGrupo('g-clasificacion', 'f-clasificacion');
    montarGrupo('g-tipo', 'f-tipo', ajustarTipo);

    const lista = document.getElementById('lista-generos');
    const campo = document.getElementById('f-genero-busca');
    if (lista && campo) {
        lista.addEventListener('click', (e) => {
            const li = e.target.closest('li');
            if (!li || li.classList.contains('sin-resultado')) return;
            campo.value = li.textContent;
            lista.querySelectorAll('li').forEach(x => x.classList.remove('marcada'));
            li.classList.add('marcada');
            cerrarGeneros();
        });
        // Se cierra al pulsar fuera, como cualquier desplegable.
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#caja-genero')) cerrarGeneros();
        });
        campo.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') cerrarGeneros();
        });
    }
});

/* Los números solo admiten dígitos: se filtra al escribir para que
   '15151515' o '45514' no lleguen siquiera al envío. */
document.addEventListener('DOMContentLoaded', () => {
    const soloDigitos = (id, max) => {
        const campo = document.getElementById(id);
        if (!campo) return;
        campo.addEventListener('input', () => {
            let v = campo.value.replace(/[^0-9]/g, '');
            v = v.replace(/^0+(?=[0-9])/, '');
            if (v.length > max) v = v.slice(0, max);
            if (campo.value !== v) campo.value = v;
        });
    };
    soloDigitos('f-cap-desde', 4);
    soloDigitos('f-cap-hasta', 4);
    soloDigitos('f-anio', 4);

    // El género arranca con el selector como campo activo.
    const sel = document.getElementById('f-genero');
    if (sel) sel.name = 'genero';
});

    // Auto-resize textareas
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    }
    document.querySelectorAll('textarea').forEach(t => {
        autoResize(t);
        t.addEventListener('input', () => autoResize(t));
    });

    // Preview portada
    document.getElementById('portada-input').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const preview = document.getElementById('portada-preview');
        const icon    = document.getElementById('portada-icon');
        const label   = document.getElementById('portada-label');
        preview.src = URL.createObjectURL(file);
        preview.classList.add('visible');
        icon.style.display  = 'none';
        label.style.display = 'none';
        // Solo con imagen elegida tiene sentido ofrecer «Cambiar».
        document.getElementById('portada-btn').classList.add('con-imagen');
    });

    // Toggle imprescindible
    function toggleImprescindible(btn, id) {
        fetch('controlador/toggle_imprescindible.php?id=' + id)
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    const activo = res.imprescindible == 1;
                    btn.classList.toggle('active', activo);
                    btn.querySelector('i').className = activo ? 'bi bi-star-fill' : 'bi bi-star';
                    btn.title = activo ? 'Quitar de imprescindibles' : 'Marcar como imprescindible';
                    if (activo && res.total > 4) {
                        alert('Máximo 4 cómics imprescindibles. Quita uno antes de añadir otro.');
                        // Revertir visualmente
                        btn.classList.remove('active');
                        btn.querySelector('i').className = 'bi bi-star';
                    }
                }
            });
    }

    // Modal eliminar
    function abrirModal(url) {
        document.getElementById('btn-confirm-del').href = url;
        document.getElementById('modal-eliminar').classList.add('active');
    }
    function cerrarModal() {
        document.getElementById('modal-eliminar').classList.remove('active');
    }
    document.getElementById('modal-eliminar').addEventListener('click', function (e) {
        if (e.target === this) cerrarModal();
    });

    /* =========================================================
       Modal de confirmacion de metadatos  (HU-01, HU-02)

       Separa lo VERIFICADO en la fuente de lo que REQUIERE DECISION.
       Nada se escribe en el formulario hasta que se pulsa "Usar estos
       datos": el sistema propone, la persona confirma.
       ========================================================= */
    const modalMeta = (function () {
        const caja     = document.getElementById('modal-meta');
        const verif    = document.getElementById('meta-verificado');
        const opTipo   = document.getElementById('meta-tipo');
        const pistaTipo= document.getElementById('meta-tipo-pista');
        const selGen   = document.getElementById('meta-genero');
        const pistaGen = document.getElementById('meta-genero-pista');
        const opClasif = document.getElementById('meta-clasif');
        if (!caja) return { abrir: function () {} };

        let actual = null;        // candidato en pantalla
        let alConfirmar = null;   // callback
        let portadaLista = null;  // nombre del archivo ya descargado

        // Editoriales que SI existen en el catalogo, leidas del propio
        // formulario para no duplicar la lista en dos sitios.
        function editorialesLocales() {
            const sel = document.getElementById('f-editorial');
            if (!sel) return [];
            return [...sel.options]
                .filter(function (o) { return o.value; })
                .map(function (o) { return { id: o.value, nombre: o.textContent.trim() }; });
        }

        /** Tipo segun la editorial: las de manga publican manga. */
        const EDITORIALES_MANGA = ['shueisha', 'kodansha', 'shogakukan',
            'hakusensha', 'young animal', 'square enix', 'kadokawa', 'shonen'];

        function tipoSugerido(editorial) {
            const e = (editorial || '').toLowerCase();
            if (!e) return null;
            for (let i = 0; i < EDITORIALES_MANGA.length; i++) {
                if (e.indexOf(EDITORIALES_MANGA[i]) !== -1) return 'Manga';
            }
            return 'Comic';
        }

        /** Genero segun palabras de la descripcion, entre los que YA existen. */
        /* Los nombres deben coincidir EXACTO con la constante GENEROS
           del formulario, tildes incluidas, o la sugerencia no encaja
           con ninguna opcion del desplegable. */
        const PISTAS_GENERO = [
            { genero: 'Superhéroes',      palabras: ['superhéroe','superheroe','superhéroes','supervillano','poderes','villano'] },
            { genero: 'Fantasía oscura',  palabras: ['demonio','oscuro','oscura','maldición','infierno','espadachín'] },
            { genero: 'Ciencia ficción',  palabras: ['espacio','futuro','nave','galaxia','alienígena','extraterrestre'] },
            { genero: 'Cyberpunk',        palabras: ['cyberpunk','ciberpunk','implante','neón','corporación'] },
            { genero: 'Terror',           palabras: ['terror','horror','miedo','pesadilla','zombi'] },
            { genero: 'Post-apocalíptico',palabras: ['apocalipsis','post-apocalíptico','ruinas','devastado'] },
            { genero: 'Sobrenatural',     palabras: ['sobrenatural','fantasma','espíritu','poseído'] },
            { genero: 'Fantasía',         palabras: ['magia','mago','dragón','reino','hechizo'] },
            { genero: 'Misterio',         palabras: ['misterio','detective','investigación','crimen'] },
            { genero: 'Aventura',         palabras: ['aventura','viaje','expedición','explorador'] },
            { genero: 'Acción',           palabras: ['pelea','batalla','combate','lucha'] }
        ];

        function generoSugerido(desc) {
            const t = (desc || '').toLowerCase();
            if (!t) return null;
            for (let i = 0; i < PISTAS_GENERO.length; i++) {
                const p = PISTAS_GENERO[i];
                for (let j = 0; j < p.palabras.length; j++) {
                    if (t.indexOf(p.palabras[j]) !== -1) return p.genero;
                }
            }
            return null;   // sin evidencia, no se inventa
        }

        function fila(etiqueta, html, falta) {
            return '<div class="meta-fila"><span class="meta-et">' + etiqueta +
                   '</span><div class="meta-val' + (falta ? ' falta' : '') + '">' +
                   html + '</div></div>';
        }

        function esc(t) {
            return String(t == null ? '' : t)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        /** Dibuja el bloque de datos verificados. */
        function pintarVerificado(c) {
            let h = '';
            h += fila('Título', esc(c.titulo));
            h += fila('Año', c.anio ? esc(c.anio) : 'no lo trae la fuente', !c.anio);

            // Editorial: si no esta en el catalogo, la unica salida es crearla.
            if (c.editorial_id) {
                const loc = editorialesLocales().find(function (e) { return e.id == c.editorial_id; });
                const nombreLocal = loc ? loc.nombre : c.editorial;
                const distinto = nombreLocal.toLowerCase() !== (c.editorial || '').toLowerCase();
                h += fila('Editorial', distinto
                    ? esc(c.editorial) + ' <span style="color:var(--texto-bajo)">→</span> ' + esc(nombreLocal)
                    : esc(nombreLocal));
            } else if (c.editorial) {
                h += fila('Editorial',
                    esc(c.editorial) +
                    '<span class="meta-pista">No está en tu catálogo. Es la editorial que publicó ' +
                    'esta obra, así que no puede sustituirse por otra.</span>' +
                    '<button type="button" class="meta-crear" id="meta-crear-edit">' +
                    '<i class="bi bi-plus-lg"></i> Crear «' + esc(c.editorial) + '»</button>');
            } else {
                h += fila('Editorial', 'no la trae la fuente', true);
            }

            h += fila('Capítulos', c.capitulo_hasta
                ? esc(c.capitulo_desde) + ' – ' + esc(c.capitulo_hasta)
                : 'no los trae la fuente', !c.capitulo_hasta);

            h += fila('Portada', c.portada_url
                ? '<img src="' + esc(c.portada_url) + '" alt="">'
                : 'no la trae la fuente', !c.portada_url);

            h += fila('Descripción', c.descripcion
                ? '<div class="meta-desc" id="meta-desc-txt">' + esc(c.descripcion) + '</div>' +
                  '<span class="meta-pista" id="meta-desc-estado"></span>'
                : 'no la trae la fuente', !c.descripcion);

            verif.innerHTML = h;

            const btnCrear = document.getElementById('meta-crear-edit');
            if (btnCrear) {
                btnCrear.addEventListener('click', function () {
                    crearEditorial(c.editorial, btnCrear);
                });
            }
        }

        /** Crea la editorial que falta y la deja seleccionada. */
        async function crearEditorial(nombre, boton) {
            boton.disabled = true;
            boton.innerHTML = 'Creando…';
            try {
                const r = await fetch('controlador/crear_editorial.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'nombre=' + encodeURIComponent(nombre)
                });
                const d = await r.json();
                if (!d.ok) {
                    boton.disabled = false;
                    boton.textContent = d.aviso || 'No se pudo crear';
                    return;
                }
                // Agregar al desplegable del formulario y al candidato
                const sel = document.getElementById('f-editorial');
                if (sel && !sel.querySelector('option[value="' + d.id + '"]')) {
                    const o = document.createElement('option');
                    o.value = d.id;
                    o.textContent = d.nombre;
                    sel.appendChild(o);
                }
                actual.editorial_id = d.id;
                actual.editorial = d.nombre;
                pintarVerificado(actual);
                aplicarSugerencias(actual);
            } catch (e) {
                boton.disabled = false;
                boton.textContent = 'No se pudo crear';
            }
        }

        /** Botones de opcion (tipo, clasificacion). */
        function pintarOpciones(cont, valores, elegido) {
            cont.innerHTML = '';
            valores.forEach(function (v) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'meta-op' + (v === elegido ? ' activa' : '');
                b.dataset.valor = v;
                b.textContent = v === 'Comic' ? 'Cómic' : v;
                b.addEventListener('click', function () {
                    cont.querySelectorAll('.meta-op').forEach(function (x) {
                        x.classList.remove('activa');
                    });
                    b.classList.add('activa');
                });
                cont.appendChild(b);
            });
        }

        function valorElegido(cont) {
            const a = cont.querySelector('.meta-op.activa');
            return a ? a.dataset.valor : '';
        }

        /** Rellena los tres campos que la fuente no trae. */
        function aplicarSugerencias(c) {
            const tipo = tipoSugerido(c.editorial);
            pintarOpciones(opTipo, ['Comic', 'Manga'], tipo);
            pistaTipo.textContent = tipo
                ? 'Sugerido por la editorial «' + c.editorial + '». Puedes cambiarlo.'
                : 'Sin editorial conocida: elige el formato.';

            // Generos: los del formulario, para no multiplicar variantes
            const lista = document.getElementById('lista-generos');
            const gens = lista
                ? [...lista.querySelectorAll('li')].map(function (li) { return li.textContent.trim(); })
                : [];
            const sug = generoSugerido(c.descripcion);
            selGen.innerHTML = '<option value="">— elegir —</option>' +
                gens.map(function (g) {
                    return '<option value="' + esc(g) + '"' +
                           (g === sug ? ' selected' : '') + '>' + esc(g) + '</option>';
                }).join('');
            pistaGen.textContent = sug
                ? 'Sugerido por la descripción. Puedes cambiarlo.'
                : 'La descripción no da pistas claras: elígelo tú.';

            // Clasificacion: NUNCA se sugiere (control parental)
            pintarOpciones(opClasif, ['ATP', '+13', '+16', '+18'], null);
        }

        function cerrar() {
            caja.classList.remove('active');
            document.body.classList.remove('modal-abierto');
            actual = null;
        }

        /**
         * Traduce la descripcion EN -> ES (HU-06).
         * Se lanza al abrir el modal, no durante la busqueda: asi solo
         * se traduce la edicion que el administrador decidio mirar.
         * Si el servicio falla queda el texto original con aviso, nunca
         * una version inventada.
         */
        async function traducirDescripcion(c) {
            if (!c.descripcion) return;
            const caja_ = document.getElementById('meta-desc-txt');
            const est   = document.getElementById('meta-desc-estado');
            if (!caja_ || !est) return;

            est.textContent = 'Traduciendo al español…';
            try {
                const r = await fetch('controlador/traducir.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: 'texto=' + encodeURIComponent(c.descripcion)
                });
                const d = await r.json();
                if (!d.ok) { est.textContent = d.aviso || 'No se pudo traducir.'; return; }

                // El candidato guarda el texto ya traducido: es el que
                // acabara en el formulario si se confirma.
                c.descripcion = d.texto;
                caja_.textContent = d.texto;
                est.textContent = d.aviso || '';
            } catch (e) {
                est.textContent = 'No se pudo traducir: queda el texto original.';
            }
        }

        function abrir(candidato, callback) {
            actual = candidato;
            alConfirmar = callback;
            pintarVerificado(candidato);
            aplicarSugerencias(candidato);
            caja.classList.add('active');
            document.body.classList.add('modal-abierto');

            // Traduccion y portada arrancan A LA VEZ al abrir el modal.
            // Antes la portada esperaba al boton "Usar estos datos" y se
            // notaba el tiron; ahora mientras se revisa ya se descarga.
            portadaLista = null;
            traducirDescripcion(candidato).then(function () {
                // El genero se sugiere desde la descripcion: si cambio al
                // traducirse, hay que volver a mirarla.
                if (actual === candidato) aplicarSugerencias(candidato);
            });
            descargarPortada(candidato).then(function (archivo) {
                if (actual === candidato) portadaLista = archivo;
            });
        }

        document.getElementById('meta-cerrar').addEventListener('click', cerrar);
        document.getElementById('meta-cancelar').addEventListener('click', cerrar);
        caja.addEventListener('click', function (e) { if (e.target === caja) cerrar(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && caja.classList.contains('active')) cerrar();
        });

        /**
         * Trae la portada al servidor (HU-07). Se hace al confirmar, no
         * al buscar: asi solo se descarga la edicion que se va a usar.
         * Si falla, queda activa la subida manual.
         */
        async function descargarPortada(c) {
            if (!c.portada_url) return '';
            try {
                const r = await fetch('controlador/descargar_portada.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: 'url=' + encodeURIComponent(c.portada_url) +
                          '&titulo=' + encodeURIComponent(c.titulo)
                });
                const d = await r.json();
                return d.ok ? d.archivo : '';
            } catch (e) {
                return '';
            }
        }

        document.getElementById('meta-usar').addEventListener('click', async function () {
            if (!actual) return;
            const boton = this;
            const decidido = {
                tipo: valorElegido(opTipo),
                genero: selGen.value,
                clasificacion: valorElegido(opClasif)
            };

            // La descarga arranco al abrir el modal. Lo normal es que ya
            // este; solo se espera si el admin fue mas rapido que la red.
            let archivo = portadaLista;
            if (archivo === null && actual.portada_url) {
                boton.disabled = true;
                boton.textContent = 'Un momento…';
                archivo = await descargarPortada(actual);
                boton.disabled = false;
                boton.textContent = 'Usar estos datos';
            }

            const c = actual;
            cerrar();
            if (alConfirmar) alConfirmar(c, decidido, archivo || '');
        });

        return { abrir: abrir };
    })();

    /* =========================================================
       Autocompletado de metadatos (ComicVine)

       Al escribir el titulo, el sistema busca solo: no hay boton.
       Es una REGLA, no un modelo: trae datos de una fuente externa
       y los mapea a los campos. Si la API no responde o no encuentra
       el titulo, los campos quedan vacios y se llenan a mano — nunca
       se inventa un valor. El administrador confirma antes de guardar.
       ========================================================= */
    (function () {
        const campoTit = document.getElementById('f-titulo');
        const estado   = document.getElementById('estado-meta');
        const lista    = document.getElementById('resultados-meta');
        const aviso    = document.getElementById('aviso-meta');
        if (!campoTit || !estado) return;

        let reloj = null;          // temporizador del tecleo
        let ultimaBusqueda = '';   // evita repetir la misma consulta

        /** Indicador dentro del campo: un icono, sin texto que descoloque. */
        function marcar(clase, titulo) {
            if (!clase) {
                estado.className = 'estado-busca';
                estado.innerHTML = '';
                estado.title = '';
                return;
            }
            const iconos = {
                cargando: 'bi-arrow-repeat',
                ok:       'bi-check-circle-fill',
                warn:     'bi-exclamation-circle',
                error:    'bi-x-circle'
            };
            estado.innerHTML = '<i class="bi ' + iconos[clase] + '"></i>';
            estado.className = 'estado-busca visible ' + clase;
            estado.title = titulo || '';
        }

        /**
         * Texto visible bajo el campo. El icono solo da el color; lo que
         * paso hay que poder leerlo sin pasar el cursor por encima.
         * Si la fuente sugiere un titulo, se ofrece como enlace.
         */
        function decir(texto, sugerencia) {
            if (!texto) { aviso.hidden = true; aviso.innerHTML = ''; return; }
            aviso.hidden = false;
            aviso.textContent = texto;
            if (!sugerencia) return;
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'enlace-sugerencia';
            a.textContent = 'Usar «' + sugerencia + '»';
            a.addEventListener('click', function () {
                campoTit.value = sugerencia;
                ultimaBusqueda = sugerencia.toLowerCase();
                buscar(sugerencia);
            });
            aviso.appendChild(document.createTextNode(' '));
            aviso.appendChild(a);
        }

        /** Vuelca un candidato en el formulario, sin pisar lo ya escrito.
         *  decidido: lo que el administrador eligio en el modal. */
        function rellenar(c, desdeLista, decidido, archivoPortada) {
            if (desdeLista || decidido) campoTit.value = c.titulo;

            if (decidido) {
                // Tipo y clasificacion son grupos de botones con input oculto
                aplicarGrupo('g-tipo', 'f-tipo', decidido.tipo);
                aplicarGrupo('g-clasificacion', 'f-clasificacion', decidido.clasificacion);
                const cg = document.getElementById('f-genero-busca');
                if (cg && decidido.genero) cg.value = decidido.genero;
            }

            const pisar = desdeLista || !!decidido;
            const anio = document.getElementById('f-anio');
            if (anio && c.anio && (!anio.value || pisar)) anio.value = c.anio;

            const desc = document.getElementById('f-descripcion');
            if (desc && c.descripcion && (!desc.value || pisar)) {
                desc.value = c.descripcion;
            }

            // La editorial solo se selecciona si ya existe en el catalogo:
            // no se crea sola, eso lo decide el administrador.
            const sel = document.getElementById('f-editorial');
            if (sel && c.editorial_id && (!sel.value || pisar)) {
                sel.value = c.editorial_id;
            }

            const d = document.querySelector('[name="capitulo_desde"]');
            const h = document.querySelector('[name="capitulo_hasta"]');
            if (d && c.capitulo_desde && (!d.value || pisar)) d.value = c.capitulo_desde;
            if (h && c.capitulo_hasta && (!h.value || pisar)) h.value = c.capitulo_hasta;

            // Portada ya descargada al servidor: se guarda su nombre y
            // se muestra la vista previa, como si se hubiera subido.
            if (archivoPortada) {
                const campo = document.getElementById('portada-descargada');
                if (campo) campo.value = archivoPortada;
                const prev = document.getElementById('portada-preview');
                const ico  = document.getElementById('portada-icon');
                const rot  = document.getElementById('portada-label');
                if (prev) {
                    prev.src = 'uploads/' + archivoPortada;
                    prev.style.display = 'block';
                }
                if (ico) ico.style.display = 'none';
                if (rot) rot.textContent = 'Portada descargada';
                const inp = document.getElementById('portada-input');
                if (inp) inp.removeAttribute('required');
            }

            lista.classList.remove('visible');
            lista.innerHTML = '';

            if (c.ya_registrado) {
                marcar('warn', 'Ya existe un cómic con ese título');
                decir('Ya existe un cómic con ese título en el catálogo.');
                return;
            }
            const faltan = [];
            if (!c.anio)         faltan.push('año');
            if (!c.editorial_id) faltan.push('editorial');
            if (!c.descripcion)  faltan.push('descripción');
            const txt = faltan.length
                ? 'Datos cargados. La fuente no trajo: ' + faltan.join(', ') + '.'
                : 'Datos cargados. Revísalos antes de guardar.';
            marcar('ok', txt);
            decir(txt);
        }

        /** Marca una opcion en un grupo de botones del formulario. */
        function aplicarGrupo(idGrupo, idCampo, valor) {
            if (!valor) return;
            const grupo = document.getElementById(idGrupo);
            const campo = document.getElementById(idCampo);
            if (!grupo || !campo) return;
            const bt = [...grupo.querySelectorAll('.opcion')]
                .find(function (b) { return b.dataset.valor === valor; });
            if (!bt) return;
            grupo.querySelectorAll('.opcion').forEach(function (b) { b.classList.remove('activa'); });
            bt.classList.add('activa');
            campo.value = valor;
            if (idCampo === 'f-tipo' && typeof ajustarTipo === 'function') ajustarTipo(valor);
        }

        function esc(t) {
            return String(t == null ? '' : t)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        /** Dibuja la lista cuando hay varias coincidencias. */
        function mostrarLista(cands) {
            lista.innerHTML = '';
            cands.forEach(function (c) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'meta-item';

                const img = c.portada_url
                    ? '<img src="' + c.portada_url + '" alt="" loading="lazy">'
                    : '<img src="" alt="">';

                let marca = '';
                if (c.ya_registrado) {
                    marca = '<span class="meta-marca duplicado">ya existe</span>';
                } else if (c.coincide_exacto) {
                    marca = '<span class="meta-marca exacta">exacto</span>';
                }

                // La editorial es lo que distingue una edicion de otra
                // ("Invincible" de Image no es el de Kodansha), asi que
                // se muestra destacada y no como dato secundario.
                const sub = '<strong>' + esc(c.editorial || 'editorial desconocida') +
                            '</strong> · ' + (c.anio || 's/f');

                b.innerHTML = img +
                    '<span class="meta-info">' +
                        '<span class="meta-titulo">' + c.titulo + '</span>' +
                        '<span class="meta-sub">' + sub + '</span>' +
                    '</span>' + marca;

                b.addEventListener('click', function () {
                    modalMeta.abrir(c, function (cc, decidido, archivo) {
                        rellenar(cc, true, decidido, archivo);
                    });
                });
                lista.appendChild(b);
            });
            lista.classList.add('visible');
        }

        async function buscar(titulo) {
            marcar('cargando', 'Buscando metadatos…');
            try {
                const r = await fetch('controlador/buscar_metadatos.php?titulo='
                                      + encodeURIComponent(titulo));
                const d = await r.json();

                if (!d.ok) {
                    const sug = (d.datos && d.datos.sugerencia) || '';
                    marcar('warn', d.aviso || 'Sin resultados.');
                    decir(d.aviso || 'Sin resultados. Regístralo a mano.', sug);
                    lista.classList.remove('visible');
                    return;
                }

                const cands = d.datos.candidatos || [];
                if (!cands.length) {
                    marcar('warn', d.aviso);
                    decir(d.aviso);
                    lista.classList.remove('visible');
                    return;
                }

                // NUNCA se abre el modal solo: un mismo titulo tiene
                // varias ediciones ("Invincible" de Image, de Kodansha,
                // de Delcourt...) y el sistema no puede saber cual
                // quiere el administrador. Se listan y el elige.
                marcar('ok', cands.length + ' ediciones encontradas');
                decir(cands.length === 1
                    ? 'Se encontró una edición. Pulsa sobre ella para revisar los datos.'
                    : 'Se encontraron ' + cands.length + ' ediciones. Elige cuál corresponde.');
                mostrarLista(cands);
            } catch (err) {
                marcar('error', 'No se pudo contactar la fuente');
                decir('No se pudo contactar la fuente de datos. Regístralo a mano.');
                lista.classList.remove('visible');
            }
        }

        // Busca sola 350 ms despues de dejar de escribir. Se midio que
        // el endpoint responde en ~0.22 s, asi que esperar mas solo
        // anadia retraso percibido sin ahorrar peticiones reales.
        campoTit.addEventListener('input', function () {
            clearTimeout(reloj);
            const t = campoTit.value.trim();

            if (t.length < 3) {
                marcar(null);
                decir('');
                lista.classList.remove('visible');
                ultimaBusqueda = '';
                return;
            }
            if (t.toLowerCase() === ultimaBusqueda) return;

            reloj = setTimeout(function () {
                ultimaBusqueda = t.toLowerCase();
                buscar(t);
            }, 350);
        });

        // Enter busca de inmediato en vez de enviar el formulario a medias.
        campoTit.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            clearTimeout(reloj);
            const t = campoTit.value.trim();
            if (t.length >= 3) { ultimaBusqueda = t.toLowerCase(); buscar(t); }
        });
    })();
</script>
</body>
</html>
