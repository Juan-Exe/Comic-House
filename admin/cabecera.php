<?php
/**
 * cabecera.php - Barra superior del panel, una sola para todas las
 * pantallas.
 *
 * Antes cada pantalla escribía la suya: en unas el botón decía
 * «Dashboard», en otras «Panel» o «Volver», y en crud.php la única
 * salida era cerrar sesión.
 *
 * El enlace de vuelta lleva flecha y fondo propio: como texto plano no
 * se leía como algo pulsable.
 *
 * Uso, antes de abrir <body>:
 *     $titulo = 'Catálogo';                  // dónde estás
 *     $volver = ['crud.php', 'Catálogo'];    // opcional, un paso atrás
 *     require __DIR__ . '/admin/cabecera.php';
 */

$es_panel = basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php';

// Un paso atrás: la pantalla anterior si se declaró, el panel si no.
$atras_url  = $volver[0] ?? 'dashboard.php';
$atras_txt  = $volver[1] ?? 'Panel';
?>
<header class="topbar">
    <a href="dashboard.php" class="topbar-brand" title="Ir al panel">
        <img src="Imagenes/Logo-Comic-Huse.png" alt="Comic House">
    </a>

    <?php if (!$es_panel): ?>
        <a href="<?= htmlspecialchars($atras_url) ?>" class="topbar-volver">
            <i class="bi bi-arrow-left"></i>
            <span>Volver a <?= htmlspecialchars($atras_txt) ?></span>
        </a>
    <?php endif; ?>

    <span class="topbar-donde"><?= htmlspecialchars($titulo ?? 'Administración') ?></span>

    <div class="topbar-acciones">
        <a href="index.php" class="topbar-enlace" title="Abrir el sitio público">
            <i class="bi bi-box-arrow-up-right"></i><span>Ver el sitio</span>
        </a>
        <a href="logout.php" class="topbar-enlace" title="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i>
            <?php if ($es_panel): ?>
                <span>Cerrar sesión</span>
            <?php endif; ?>
        </a>
    </div>
</header>
