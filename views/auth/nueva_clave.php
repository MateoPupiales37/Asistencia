<?php
/**
 * El docente elige su nueva contraseña desde el enlace de un solo uso.
 *
 * Ni el administrador ni el sistema llegan a ver esta clave: se guarda
 * directamente cifrada con Bcrypt. Por eso el enlace, y no una contraseña
 * escrita en un chat de WhatsApp.
 */

$titulo = 'Nueva Contraseña - ISTPET';
$vista  = 'nueva-clave';
$ocultarNavbar = true;
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="auth-page-bg">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <img src="<?= $base ?>/assets/img/logo-istpet.jpg" alt="Logo ISTPET" class="auth-logo-img">
            <h2 class="auth-title">Nueva Contraseña</h2>
            <p class="auth-subtitle">
                Hola <strong><?= htmlspecialchars(trim($solicitud['nombre'] . ' ' . $solicitud['apellido'])) ?></strong>,
                elige la clave con la que entrarás al sistema
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <div class="alert alert-info">
            <span>
                Cuenta: <code><?= htmlspecialchars($solicitud['correo']) ?></code><br>
                <small>Este enlace sirve una sola vez. Nadie más conoce tu contraseña.</small>
            </span>
        </div>

        <form action="<?= $base ?>/clave-nueva" method="POST" id="formClave" data-sin-ajax>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="password" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                <div class="input-con-boton">
                    <input type="password" id="password" name="password" class="form-control"
                           required minlength="<?= (int)$minimo ?>" maxlength="72"
                           autocomplete="new-password" placeholder="••••••••">
                    <button type="button" class="btn-ver-clave" onclick="alternar('password', this)">Ver</button>
                </div>
                <small class="form-ayuda">
                    Mínimo <?= (int)$minimo ?> caracteres, con al menos una letra y un número.
                </small>
            </div>

            <div class="form-group mb-6">
                <label for="password2" class="form-label">Repite la contraseña <span class="text-danger">*</span></label>
                <input type="password" id="password2" name="password2" class="form-control"
                       required minlength="<?= (int)$minimo ?>" maxlength="72"
                       autocomplete="new-password" placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Guardar Contraseña</button>
        </form>

        <div class="auth-footer">
            <a href="<?= $base ?>/login">&larr; Volver al acceso</a>
        </div>
    </div>
</div>

<script>
function alternar(id, boton) {
    const campo = document.getElementById(id);
    const oculto = campo.type === 'password';
    campo.type = oculto ? 'text' : 'password';
    boton.textContent = oculto ? 'Ocultar' : 'Ver';
}

document.getElementById('formClave').addEventListener('submit', function (e) {
    const a = document.getElementById('password').value;
    const b = document.getElementById('password2').value;

    if (a !== b) {
        e.preventDefault();
        alert('Las dos contraseñas no coinciden.');
        return;
    }
    if (!/[A-Za-z]/.test(a) || !/\d/.test(a)) {
        e.preventDefault();
        alert('La contraseña debe combinar al menos una letra y un número.');
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
