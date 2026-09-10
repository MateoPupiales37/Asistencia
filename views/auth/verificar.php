<?php
/**
 * Segundo paso del acceso: código de la aplicación autenticadora.
 *
 * En este punto la contraseña ya se comprobó, pero la sesión TODAVÍA no está
 * abierta: el usuario queda "a medio entrar" hasta que demuestra que tiene su
 * teléfono. Por eso quien robe solo la contraseña no llega a ninguna parte.
 */

$titulo = 'Verificación en dos pasos - ISTPET';
$vista  = 'verificar';
$ocultarNavbar = true;
require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="auth-page-bg">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <img src="<?= $base ?>/assets/img/logo-istpet.jpg" alt="Logo ISTPET" class="auth-logo-img">
            <h2 class="auth-title">Verificación en dos pasos</h2>
            <p class="auth-subtitle">
                Hola <strong><?= htmlspecialchars(trim($usuario['nombre'] . ' ' . $usuario['apellido'])) ?></strong>,
                escribe el código de tu aplicación autenticadora
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <form action="<?= $base ?>/acceso/verificar" method="POST" data-sin-ajax id="formVerificar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group mb-6">
                <label for="codigo" class="form-label">Código de 6 dígitos <span class="text-danger">*</span></label>
                <input type="text" id="codigo" name="codigo" class="form-control campo-totp"
                       required autofocus inputmode="numeric" autocomplete="one-time-code"
                       maxlength="6" minlength="6" placeholder="000000"
                       oninput="this.value = this.value.replace(/\D/g,''); if (this.value.length === 6) this.form.requestSubmit();">
                <small class="form-ayuda">
                    Ábrela en tu teléfono y copia el número que aparece para
                    <strong>ISTPET</strong>. Cambia cada 30 segundos.
                </small>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Verificar y entrar</button>
        </form>

        <div class="auth-footer">
            <p class="mb-2 text-muted" style="font-size:.83rem">
                ¿Perdiste el teléfono? El administrador puede retirarte la verificación
                para que vuelvas a configurarla.
            </p>
            <a href="<?= $base . htmlspecialchars($volver ?? '/acceso/docente') ?>">&larr; Volver al acceso</a>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
