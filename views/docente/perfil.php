<?php
/**
 * Perfil del docente: datos de la cuenta y verificación en dos pasos.
 *
 * La activación va en dos tiempos a propósito. Primero se genera el secreto y
 * se muestra el QR; el doble factor solo queda activo cuando el docente
 * escribe un código correcto. Si se activara de una, un fallo al escanear lo
 * dejaría fuera del sistema sin manera de entrar.
 */

$titulo = 'Mi Perfil - ISTPET';
$vista  = 'docente-perfil';
require dirname(__DIR__) . '/layouts/header.php';
$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mi Perfil</h1>
        <p class="page-subtitle">Datos de tu cuenta y seguridad de acceso</p>
    </div>
    <a href="<?= $base ?>/docente" class="btn btn-outline">&larr; Volver a mi clase</a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<div class="perfil-grid">

    <!-- ============ DATOS DE LA CUENTA ============ -->
    <div class="card">
        <h2 class="card-titulo">Datos de la cuenta</h2>

        <dl class="ficha-datos">
            <div>
                <dt>Nombre</dt>
                <dd><?= htmlspecialchars(trim($usuario['nombre'] . ' ' . $usuario['apellido'])) ?></dd>
            </div>
            <div>
                <dt>Correo institucional</dt>
                <dd class="celda-correo"><?= htmlspecialchars($usuario['correo']) ?></dd>
            </div>
            <div>
                <dt>Rol</dt>
                <dd><span class="badge badge-info">DOCENTE</span></dd>
            </div>
            <div>
                <dt>Cuenta creada</dt>
                <dd><?= htmlspecialchars(substr((string)$usuario['creado_en'], 0, 10)) ?></dd>
            </div>
        </dl>

        <p class="form-ayuda mt-4">
            El nombre y el correo los administra el Administrador del sistema.
            Si algo está mal escrito, pídele que lo corrija.
        </p>
    </div>

    <!-- ============ CONSENTIMIENTO DE DATOS ============ -->
    <div class="card">
        <div class="card-header-flex">
            <h2 class="card-titulo mb-0">Ubicación y cámara</h2>
            <span class="badge <?= !empty($consentimientoOk) ? 'badge-success' : 'badge-neutral' ?>">
                <?= !empty($consentimientoOk) ? 'ACEPTADO' : 'PENDIENTE' ?>
            </span>
        </div>

        <?php if (!empty($consentimientoOk)): ?>
            <div class="totp-estado ok">
                <p>
                    Aceptaste el uso de tu ubicación para fijar el área de tus clases.
                    <?php if (!empty($consentimientoFecha)): ?>
                        <br><span class="text-muted">
                            Aceptado el <?= htmlspecialchars(date('d/m/Y \a \l\a\s H:i', strtotime($consentimientoFecha))) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <p class="form-ayuda mb-3">
                Al abrir una clase, tus coordenadas marcan el centro del aula. Los
                estudiantes que estén a más de <?= (int)($radioGeo ?? 500) ?> metros no
                podrán registrarse aunque tengan el código.
            </p>

            <details class="bloque-desplegable">
                <summary>Retirar mi permiso</summary>
                <p class="form-ayuda mb-3">
                    Si lo retiras, tus clases se abrirán sin control de ubicación:
                    cualquiera con el código podrá registrarse desde donde esté.
                </p>
                <form action="<?= $base ?>/terminos/retirar" method="POST"
                      onsubmit="return confirm('¿Retirar tu permiso? Tus clases dejarán de comprobar la distancia.');">
                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                    <button type="submit" class="btn btn-peligro-suave">Retirar permiso</button>
                </form>
            </details>

        <?php else: ?>
            <div class="totp-estado">
                <p>
                    Para que el control de distancia funcione, el sistema necesita tu
                    <strong>ubicación</strong> en el momento de abrir la clase.
                </p>
                <p class="text-muted">
                    Se consulta una sola vez, al abrir cada clase. No se guarda ningún
                    recorrido tuyo ni se te sigue después.
                </p>
            </div>

            <form action="<?= $base ?>/terminos/aceptar" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <input type="hidden" name="volver" value="/docente/perfil">

                <label class="consentimiento" for="aceptoDocente">
                    <input type="checkbox" id="aceptoDocente" name="acepto" value="1" required>
                    <span>
                        Acepto el uso de mi <strong>ubicación</strong> para delimitar el aula
                        y de la <strong>cámara</strong> cuando la necesite el sistema.
                        <a href="<?= $base ?>/terminos" target="_blank" rel="noopener">Ver términos</a>
                    </span>
                </label>

                <button type="submit" class="btn btn-dorado btn-block">Aceptar y activar</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- ============ VERIFICACIÓN EN DOS PASOS ============ -->
    <div class="card">
        <div class="card-header-flex">
            <h2 class="card-titulo mb-0">Verificación en dos pasos</h2>
            <span class="badge <?= $totpActivo ? 'badge-success' : 'badge-neutral' ?>">
                <?= $totpActivo ? 'ACTIVADA' : 'DESACTIVADA' ?>
            </span>
        </div>

        <?php if ($totpActivo): ?>
            <!-- ---------- Ya está activa ---------- -->
            <div class="totp-estado ok">
                <p>
                    Tu cuenta está protegida. Al entrar te pedimos tu contraseña y,
                    después, el código de 6 dígitos de tu aplicación autenticadora.
                </p>
                <p class="text-muted">
                    Aunque alguien averigüe tu contraseña, no puede entrar sin tu teléfono.
                </p>
            </div>

            <details class="bloque-desplegable">
                <summary>Desactivar la verificación en dos pasos</summary>
                <p class="form-ayuda mb-3">
                    Solo desactívala si cambiaste de teléfono o perdiste el acceso a la
                    aplicación. Tu cuenta queda menos protegida.
                </p>

                <form action="<?= $base ?>/docente/perfil/2fa/quitar" method="POST"
                      onsubmit="return confirm('¿Seguro que quieres desactivar la verificación en dos pasos?');">
                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">

                    <div class="form-group mb-4">
                        <label for="password" class="form-label">
                            Confirma tu contraseña <span class="text-danger">*</span>
                        </label>
                        <input type="password" id="password" name="password" class="form-control"
                               required maxlength="72" autocomplete="current-password"
                               placeholder="••••••••">
                        <small class="form-ayuda">
                            Se pide por si dejaste la sesión abierta: nadie más debe poder
                            quitarle la protección a tu cuenta.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-peligro-suave">Desactivar</button>
                </form>
            </details>

        <?php elseif ($totpPreparado): ?>
            <!-- ---------- Paso 2: escanear y confirmar ---------- -->
            <ol class="pasos-totp">
                <li>Instala <strong>Google Authenticator</strong> en tu teléfono.</li>
                <li>Ábrelo, toca <strong>+</strong> y elige <strong>Escanear código QR</strong>.</li>
                <li>Apunta al código de abajo.</li>
                <li>Escribe aquí el número de 6 dígitos que te muestre.</li>
            </ol>

            <div class="totp-qr-box">
                <?= $qrTotp ?>
            </div>

            <?php if (!empty($secretoLegible)): ?>
                <div class="access-code-box">
                    <span class="access-code-title">¿No puedes escanear? Escribe esta clave</span>
                    <strong class="access-code-val totp-secreto"><?= htmlspecialchars($secretoLegible) ?></strong>
                </div>
            <?php endif; ?>

            <form action="<?= $base ?>/docente/perfil/2fa/activar" method="POST" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">

                <div class="form-group mb-4">
                    <label for="codigo" class="form-label">
                        Código de 6 dígitos <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="codigo" name="codigo" class="form-control form-control-code totp-input"
                           required inputmode="numeric" maxlength="6" minlength="6"
                           pattern="[0-9]{6}" placeholder="000000"
                           autocomplete="one-time-code" spellcheck="false" autofocus
                           oninput="this.value = this.value.replace(/\D/g,'')">
                    <small class="form-ayuda">
                        El código cambia cada 30 segundos. Escribe el que se ve <strong>ahora</strong>.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Activar verificación</button>
            </form>

            <form action="<?= $base ?>/docente/perfil/2fa/preparar" method="POST" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <button type="submit" class="btn btn-outline btn-sm">Generar otro código QR</button>
            </form>

        <?php else: ?>
            <!-- ---------- Paso 1: todavía no configurada ---------- -->
            <div class="totp-estado">
                <p>
                    Con la verificación en dos pasos, entrar a tu cuenta necesita
                    <strong>dos cosas</strong>: tu contraseña y tu teléfono.
                </p>
                <p class="text-muted">
                    Es la misma protección que usan los bancos. Si alguien te ve escribir
                    la contraseña, sigue sin poder entrar.
                </p>
            </div>

            <ol class="pasos-totp">
                <li>Instala <strong>Google Authenticator</strong> (también sirve Authy o Microsoft Authenticator).</li>
                <li>Pulsa el botón de abajo: aparecerá un código QR.</li>
                <li>Escanéalo con la aplicación y confirma con el número que te dé.</li>
            </ol>

            <form action="<?= $base ?>/docente/perfil/2fa/preparar" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <button type="submit" class="btn btn-dorado btn-lg btn-block">
                    Activar verificación en dos pasos
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
