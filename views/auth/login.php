<?php
/**
 * Acceso del personal. La MISMA vista sirve a las dos puertas —docente y
 * administración— y el controlador le pasa cuál es en $puerta.
 *
 * Es una sola plantilla y no dos porque el formulario es idéntico: lo único
 * que cambia son el título, el color del distintivo y a dónde se envía. Dos
 * copias del mismo formulario habrían empezado a separarse en cuanto se
 * tocara una sola de ellas.
 *
 * Deliberadamente NO muestra credenciales de ejemplo ni el formato del correo
 * institucional. Esa caja de "usa estas credenciales" existía antes y era un
 * problema real: publicaba en una página pública una cuenta de administrador
 * que funcionaba. Las credenciales están ahora en CREDENCIALES.md, fuera del
 * alcance del navegador.
 *
 * Tampoco distingue si falló el correo o la contraseña: decir "ese correo no
 * existe" permitiría averiguar qué cuentas son válidas probando una por una.
 */

$esAdmin = ($puerta ?? 'docente') === 'admin';

$titulo = ($tituloPuerta ?? 'Acceso Institucional') . ' - ISTPET';
$vista  = 'acceso-' . ($puerta ?? 'docente');
$ocultarNavbar = true;
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="auth-page-bg">
    <div class="auth-card auth-card-<?= htmlspecialchars($puerta ?? 'docente') ?>">
        <div class="auth-top-bar">
            <a href="<?= $base ?>/" class="auth-top-back">&larr; Volver al inicio</a>
        </div>

        <div class="auth-logo-wrap">
            <img src="<?= $base ?>/assets/img/logo-istpet.jpg" alt="Logo ISTPET" class="auth-logo-img">

            <span class="auth-distintivo <?= $esAdmin ? 'es-admin' : 'es-docente' ?>">
                <?= $esAdmin ? 'ADMINISTRACIÓN' : 'DOCENTE' ?>
            </span>

            <h2 class="auth-title"><?= htmlspecialchars($tituloPuerta ?? ($esAdmin ? 'Acceso Administración' : 'Acceso Docente')) ?></h2>
            <p class="auth-subtitle"><?= htmlspecialchars($subtitulo ?? '') ?></p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <form action="<?= $base . htmlspecialchars($accion ?? '/acceso/docente') ?>" method="POST" autocomplete="on" data-sin-ajax>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="correo" class="form-label">Correo institucional <span class="text-danger">*</span></label>
                <input type="email" id="correo" name="correo" class="form-control"
                       required autofocus maxlength="150"
                       inputmode="email" autocapitalize="none" spellcheck="false"
                       autocomplete="username">
            </div>

            <div class="form-group mb-6">
                <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                <div class="input-con-boton">
                    <input type="password" id="password" name="password" class="form-control"
                           required maxlength="72" autocomplete="current-password">
                    <button type="button" class="btn-ver-clave" onclick="alternarClave(this)"
                            aria-label="Mostrar contraseña">Ver</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Ingresar</button>
        </form>

        <p class="text-center mt-4">
            <button type="button" class="enlace-olvide" onclick="mostrarSolicitud()">
                ¿Olvidaste tu contraseña?
            </button>
        </p>

        <!-- Solicitud de clave: le llega al administrador como aviso -->
        <div id="cajaSolicitud" class="caja-solicitud" hidden>
            <p class="text-muted mb-3" style="font-size:.85rem">
                Escribe tu correo institucional. El administrador recibirá el aviso y
                se pondrá en contacto contigo.
            </p>
            <form action="<?= $base ?>/solicitar-clave" method="POST" data-sin-ajax>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="correo_solicitud" class="form-label">Correo institucional</label>
                    <input type="email" id="correo_solicitud" name="correo" class="form-control"
                           required maxlength="150">
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline flex-1" onclick="mostrarSolicitud(false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary flex-1">Enviar solicitud</button>
                </div>
            </form>
        </div>

        <div class="auth-footer">
            <p class="mb-2">
                ¿Esta no es tu entrada?
                <a href="<?= $base . htmlspecialchars($otraRuta ?? '/acceso/admin') ?>" class="text-primary font-bold">
                    <?= htmlspecialchars($otroTexto ?? 'Otra entrada') ?> &rarr;
                </a>
            </p>
            <p class="mb-0">
                ¿Eres estudiante?
                <a href="<?= $base ?>/asistencia" class="text-primary font-bold">Registra tu asistencia aquí &rarr;</a>
            </p>
        </div>
    </div>
</div>

<script>
function mostrarSolicitud(mostrar = true) {
    document.getElementById('cajaSolicitud').hidden = !mostrar;
    if (mostrar) document.getElementById('correo_solicitud').focus();
}

function alternarClave(boton) {
    const campo = document.getElementById('password');
    const oculto = campo.type === 'password';
    campo.type = oculto ? 'text' : 'password';
    boton.textContent = oculto ? 'Ocultar' : 'Ver';
    boton.setAttribute('aria-label', oculto ? 'Ocultar contraseña' : 'Mostrar contraseña');
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
