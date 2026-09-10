<?php
/**
 * Portada: puerta de entrada del sistema.
 *
 * Separa desde el primer clic los TRES caminos, que no tienen nada que ver
 * entre sí:
 *
 *   Estudiante -> formulario público, sin cuenta ni contraseña.
 *   Docente    -> acceso con contraseña y verificación en dos pasos.
 *   Administración -> el mismo acceso, pero a su propio panel.
 *
 * Docente y administración estaban antes detrás de una sola entrada. Al
 * separarlas, cada quien sabe desde el principio a dónde va y el sistema
 * puede decir con claridad "esta entrada no es la tuya" en vez de dejar que
 * el rol decida en silencio a qué panel se cae.
 *
 * Aquí no se menciona el formato del correo institucional ni ningún dato de
 * las cuentas: esa información solo le sirve a quien intenta entrar sin
 * permiso, porque quien tiene cuenta ya conoce la suya.
 */

$titulo = 'Sistema de Asistencia QR - ISTPET';
$vista  = 'portada';
$bodyClass = 'portada-body';
$ocultarNavbar = true;
require dirname(__DIR__) . '/layouts/header.php';

/*
 * Malla de constelación del fondo.
 *
 * Se genera con una semilla fija para que el dibujo sea siempre el mismo:
 * si cambiara en cada carga, la portada "parpadearía" distinto cada vez.
 * Se dibuja en SVG y no con imágenes, así pesa unos pocos kilobytes y se ve
 * nítida en cualquier pantalla.
 */
mt_srand(20260907);

$ancho = 1440;
$alto  = 900;
$nodos = [];

for ($i = 0; $i < 46; $i++) {
    $nodos[] = [
        'x' => mt_rand(0, $ancho),
        'y' => mt_rand(0, $alto),
        'r' => mt_rand(2, 5),
        // Uno de cada cuatro puntos es dorado, para dar el acento institucional
        'oro' => ($i % 4 === 0),
    ];
}

// Se unen solo los nodos cercanos: así aparece la retícula sin saturar
$lineas = [];
foreach ($nodos as $a => $na) {
    foreach ($nodos as $b => $nb) {
        if ($b <= $a) {
            continue;
        }
        $distancia = sqrt(($na['x'] - $nb['x']) ** 2 + ($na['y'] - $nb['y']) ** 2);
        if ($distancia < 260) {
            $lineas[] = [$na, $nb, $na['oro'] && $nb['oro']];
        }
    }
}
?>

<div class="portada">

    <!-- Fondo: malla de constelación institucional -->
    <svg class="portada-malla" viewBox="0 0 <?= $ancho ?> <?= $alto ?>"
         preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
        <g class="malla-lineas">
            <?php foreach ($lineas as [$a, $b, $esOro]): ?>
                <line x1="<?= $a['x'] ?>" y1="<?= $a['y'] ?>" x2="<?= $b['x'] ?>" y2="<?= $b['y'] ?>"
                      class="<?= $esOro ? 'linea-oro' : 'linea-azul' ?>"/>
            <?php endforeach; ?>
        </g>
        <g class="malla-puntos">
            <?php foreach ($nodos as $n): ?>
                <circle cx="<?= $n['x'] ?>" cy="<?= $n['y'] ?>" r="<?= $n['r'] ?>"
                        class="<?= $n['oro'] ? 'punto-oro' : 'punto-azul' ?>"/>
            <?php endforeach; ?>
        </g>
    </svg>

    <!-- Centro: identidad institucional -->
    <div class="portada-centro">
        <?php if (!empty($rol)): ?>
            <div class="portada-sesion">
                <span>Sesión activa &bull; <?= htmlspecialchars($nombre ?? '') ?></span>
                <a href="<?= $base ?><?= $rol === 'admin' ? '/admin' : '/docente' ?>" class="portada-sesion-ir">
                    Ir a mi panel &rarr;
                </a>
                <a href="<?= $base ?>/logout" class="portada-sesion-salir">Cerrar sesión</a>
            </div>
        <?php endif; ?>

        <div class="portada-logo">
            <img src="<?= $base ?>/assets/img/logo-istpet.jpg" alt="Instituto Superior Tecnológico Mayor Pedro Traversari">
        </div>

        <p class="portada-institucion">Instituto Superior Tecnológico Mayor Pedro Traversari</p>

        <h1 class="portada-titulo">Sistema de Asistencia</h1>

        <p class="portada-lema">
            Registro de asistencia con código QR &bull; el código caduca a los <?= (int)($minutosQr ?? 15) ?> minutos
        </p>
    </div>

    <!-- Barra inferior: los tres caminos de entrada -->
    <!--
        Tres puertas y no una sola con roles dentro. Quien llega sabe quien es,
        y decirlo desde el primer clic evita el caso mas comun de soporte: el
        estudiante intentando entrar con "usuario y contraseña" que nunca tuvo.
    -->
    <nav class="portada-puertas" aria-label="Elige cómo entrar">
        <a href="<?= $base ?>/asistencia" class="puerta puerta-estudiante">
            <span class="puerta-icono" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3"/><path d="M14 20h7"/></svg>
            </span>
            <span class="puerta-texto">
                <strong>ESTUDIANTES</strong>
                <small>Escanea el QR y registra tu asistencia</small>
            </span>
            <span class="puerta-flecha" aria-hidden="true">&rarr;</span>
        </a>

        <a href="<?= $base ?>/acceso/docente" class="puerta puerta-docente">
            <span class="puerta-icono" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2.5 6 2.5s6-1.4 6-2.5v-5"/></svg>
            </span>
            <span class="puerta-texto">
                <strong>DOCENTES</strong>
                <small>Abre tu clase y pasa lista</small>
            </span>
            <span class="puerta-flecha" aria-hidden="true">&rarr;</span>
        </a>

        <a href="<?= $base ?>/acceso/admin" class="puerta puerta-admin">
            <span class="puerta-icono" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <span class="puerta-texto">
                <strong>ADMINISTRACIÓN</strong>
                <small>Supervisión y gestión académica</small>
            </span>
            <span class="puerta-flecha" aria-hidden="true">&rarr;</span>
        </a>
    </nav>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
