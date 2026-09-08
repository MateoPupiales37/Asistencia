<?php
$titulo = 'Resultado del Registro - ISTPET';
$ocultarNavbar = true;
require dirname(__DIR__) . '/layouts/header.php';

$esSalida = (($tipo ?? 'entrada') === 'salida');
?>

<div class="auth-page-bg">
    <div class="auth-card text-center">

        <div class="result-status-circle <?= $exito ? 'success' : 'danger' ?>">
            <?= $exito ? '&#10003;' : '&#10007;' ?>
        </div>

        <h2 class="result-titulo <?= $exito ? 'ok' : 'mal' ?>">
            <?php if ($exito): ?>
                <?= $esSalida ? 'Salida Registrada' : 'Asistencia Registrada' ?>
            <?php else: ?>
                No se pudo registrar
            <?php endif; ?>
        </h2>

        <p class="result-mensaje"><?= htmlspecialchars($mensaje) ?></p>

        <?php if (!empty($estudiante)): ?>
            <div class="result-data-box">
                <div class="result-data-item">
                    <span class="result-label">Estudiante</span>
                    <span class="result-value primary">
                        <?= htmlspecialchars(trim($estudiante['nombre'] . ' ' . ($estudiante['apellido'] ?? ''))) ?>
                    </span>
                </div>
                <div class="result-data-item">
                    <span class="result-label">Código</span>
                    <span class="result-value table-code"><?= htmlspecialchars($estudiante['codigo']) ?></span>
                </div>
                <div class="result-data-item">
                    <span class="result-label">Semestre</span>
                    <span class="result-value"><?= htmlspecialchars($estudiante['semestre'] ?? '') ?></span>
                </div>

                <?php if (!empty($sesion)): ?>
                    <div class="result-data-item">
                        <span class="result-label">Materia</span>
                        <span class="result-value"><?= htmlspecialchars($sesion['materia']) ?></span>
                    </div>
                    <div class="result-data-item">
                        <span class="result-label">Ambiente</span>
                        <span class="result-value"><?= htmlspecialchars($sesion['ambiente']) ?></span>
                    </div>
                    <div class="result-data-item">
                        <span class="result-label">Docente</span>
                        <span class="result-value">
                            <?= htmlspecialchars(trim($sesion['docente_nombre'] . ' ' . $sesion['docente_apellido'])) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($exito): ?>
                    <div class="result-data-item">
                        <span class="result-label"><?= $esSalida ? 'Hora de salida' : 'Hora de entrada' ?></span>
                        <span class="result-value success"><?= date('d/m/Y') ?> &bull; <?= htmlspecialchars($hora) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($exito && !$esSalida && !empty($estudiante['token_qr'])): ?>
            <div class="aviso-carnet">
                Guarda este enlace: es tu <strong>carnet QR personal</strong>. La próxima vez
                te identifica al instante, sin escribir tus datos.
                <a class="btn btn-outline btn-sm mt-2"
                   href="<?= $base ?>/asistencia?carnet=<?= htmlspecialchars($estudiante['token_qr'], ENT_QUOTES, 'UTF-8') ?>">
                    Abrir mi carnet
                </a>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-column gap-2 mt-6">
            <?php if (!$exito): ?>
                <a href="<?= $base ?>/asistencia<?= !empty($codigo) ? '?c=' . urlencode($codigo) : '' ?>"
                   class="btn btn-primary btn-block btn-lg">Reintentar &rarr;</a>
            <?php else: ?>
                <a href="<?= $base ?>/asistencia" class="btn btn-outline btn-block">Registrar otro código</a>
            <?php endif; ?>
            <a href="<?= $base ?>/" class="btn btn-outline btn-block">&larr; Volver al inicio</a>
        </div>
    </div>
</div>

<script>
// Aviso sonoro distinto segun el resultado, util cuando el docente controla el aula
(function () {
    const exito = <?= $exito ? 'true' : 'false' ?>;
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gan = ctx.createGain();

        if (exito) {
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.12);
        } else {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(320, ctx.currentTime);
            osc.frequency.setValueAtTime(240, ctx.currentTime + 0.12);
        }

        gan.gain.setValueAtTime(0.25, ctx.currentTime);
        gan.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
        osc.connect(gan); gan.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime + 0.25);
    } catch (e) { /* el navegador puede bloquear el audio automatico */ }
})();
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
