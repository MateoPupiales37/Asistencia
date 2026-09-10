<?php
$base   = $base ?? '';
$titulo = $titulo ?? 'Sistema de Asistencia QR - ISTPET';

// Ruta actual, para marcar el enlace activo de la barra de navegacion
$uriActual = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$dirActual = str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($dirActual !== '/' && $dirActual !== '' && str_starts_with($uriActual, $dirActual)) {
    $uriActual = substr($uriActual, strlen($dirActual));
}
$rutaActual = '/' . trim($uriActual, '/');

$rolActual    = $_SESSION['usuario_rol'] ?? '';
$nombreActual = $_SESSION['usuario_nombre'] ?? '';

/*
 * Periodo academico con el que trabaja el administrador.
 *
 * Va SIEMPRE a la vista en la barra, y no solo en la pantalla donde se elige.
 * El motivo es concreto: sin recordatorio permanente es facil pasar una tarde
 * entera cargando la malla del ciclo nuevo dentro del anterior sin notarlo, y
 * deshacerlo despues es mucho mas caro que mostrar esta etiqueta.
 */
$periodoActual = $_SESSION['periodo_nombre'] ?? '';
$hayNavbar    = !empty($_SESSION['usuario_id']) && empty($ocultarNavbar);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>" data-base="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">

<?php if ($hayNavbar): ?>
<nav class="navbar">
    <a href="<?= $base ?><?= $rolActual === 'admin' ? '/admin' : '/docente' ?>" class="navbar-brand">
        <img src="<?= $base ?>/assets/img/logo-istpet.jpg" alt="ISTPET">
        <span>ISTPET</span>
        <span class="badge-istpet <?= $rolActual === 'admin' ? 'badge-admin' : '' ?>">
            <?= $rolActual === 'admin' ? 'ADMINISTRADOR' : 'DOCENTE' ?>
        </span>
    </a>

    <button type="button" class="navbar-toggle" onclick="document.querySelector('.nav-links').classList.toggle('abierto')" aria-label="Abrir menú">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <ul class="nav-links">
        <?php if ($rolActual === 'admin'): ?>
            <?php if ($periodoActual !== ''): ?>
                <li>
                    <a href="<?= $base ?>/admin/periodo"
                       class="nav-periodo <?= str_starts_with($rutaActual, '/admin/periodo') ? 'active' : '' ?>"
                       title="Cambiar de período académico">
                        <span class="nav-periodo-etiqueta">Período</span>
                        <strong><?= htmlspecialchars($periodoActual) ?></strong>
                    </a>
                </li>
            <?php endif; ?>
            <li><a href="<?= $base ?>/admin" class="nav-link <?= $rutaActual === '/admin' ? 'active' : '' ?>">Supervisión</a></li>
            <li><a href="<?= $base ?>/admin/materias" class="nav-link <?= str_starts_with($rutaActual, '/admin/materias') || str_starts_with($rutaActual, '/admin/semestres') ? 'active' : '' ?>">Materias</a></li>
            <li><a href="<?= $base ?>/admin/docentes" class="nav-link <?= str_starts_with($rutaActual, '/admin/docentes') ? 'active' : '' ?>">Cuentas</a></li>
            <li><a href="<?= $base ?>/reportes" class="nav-link <?= str_starts_with($rutaActual, '/reportes') ? 'active' : '' ?>">Reportes</a></li>
        <?php else: ?>
            <li><a href="<?= $base ?>/docente" class="nav-link <?= $rutaActual === '/docente' ? 'active' : '' ?>">Mi Clase</a></li>
            <li><a href="<?= $base ?>/docente/matriculas" class="nav-link <?= str_starts_with($rutaActual, '/docente/matriculas') ? 'active' : '' ?>">Estudiantes</a></li>
            <li><a href="<?= $base ?>/docente/envios" class="nav-link <?= str_starts_with($rutaActual, '/docente/envios') ? 'active' : '' ?>">Enviar</a></li>
            <li><a href="<?= $base ?>/docente/carnets" class="nav-link <?= str_starts_with($rutaActual, '/docente/carnets') ? 'active' : '' ?>">Carnets QR</a></li>
            <li><a href="<?= $base ?>/reportes" class="nav-link <?= str_starts_with($rutaActual, '/reportes') ? 'active' : '' ?>">Reportes</a></li>
        <?php endif; ?>
            <?php if ($rolActual === 'admin'): ?>
                <li>
                    <a href="<?= $base ?>/admin/solicitudes" class="nav-link campanita" id="campanita"
                       title="Solicitudes de contraseña">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="campanita-texto">Solicitudes</span>
                        <span class="campanita-contador" id="campanitaContador" hidden>0</span>
                    </a>
                </li>
            <?php endif; ?>
        <li class="nav-user">
            <?php if ($rolActual !== 'admin'): ?>
                <!-- El docente entra aqui para activar su verificacion en dos pasos -->
                <a href="<?= $base ?>/docente/perfil"
                   class="nav-perfil <?= str_starts_with($rutaActual, '/docente/perfil') ? 'active' : '' ?>"
                   title="Mi perfil y seguridad de la cuenta">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="user-name"><?= htmlspecialchars($nombreActual) ?></span>
                </a>
            <?php else: ?>
                <span class="user-name"><?= htmlspecialchars($nombreActual) ?></span>
            <?php endif; ?>
            <a href="<?= $base ?>/logout" class="btn-logout">Salir</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<main class="main-content" data-vista="<?= htmlspecialchars($vista ?? '', ENT_QUOTES, 'UTF-8') ?>">
