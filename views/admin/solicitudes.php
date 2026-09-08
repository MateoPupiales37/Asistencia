<?php
/**
 * Solicitudes de contraseña.
 *
 * Cuando un docente olvida su clave la pide desde el login y aparece aquí.
 * El administrador la atiende y el sistema genera un enlace de un solo uso
 * que caduca; el enlace se le envía al docente por WhatsApp.
 */

$titulo = 'Solicitudes de Contraseña - ISTPET';
$vista  = 'admin-solicitudes';
require dirname(__DIR__) . '/layouts/header.php';

$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
?>

<nav class="breadcrumb">
    <a href="<?= $base ?>/admin">Supervisión</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Solicitudes</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">Solicitudes de Contraseña</h1>
        <p class="page-subtitle">Docentes que pidieron restablecer su clave</p>
    </div>
    <div class="acciones-cabecera">
        <a href="<?= $base ?>/admin" class="btn btn-back">&larr; Supervisión</a>
        <a href="<?= $base ?>/admin/docentes" class="btn btn-outline">Cuentas</a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<div data-region="solicitudes">
<?php if (!empty($ultimoEnlace)): ?>
    <div class="card card-enlace mb-6">
        <h2 class="card-titulo">Enlace listo para <?= htmlspecialchars($ultimoEnlace['nombre']) ?></h2>
        <p class="text-muted mb-4" style="font-size:.87rem">
            Sirve una sola vez y caduca en <?= (int)$minutos ?> minutos. El docente
            elige su propia contraseña: tú nunca la ves.
        </p>

        <div class="form-group">
            <label class="form-label" for="enlaceGenerado">Enlace</label>
            <div class="input-con-boton">
                <input type="text" id="enlaceGenerado" class="form-control" readonly
                       value="<?= htmlspecialchars($ultimoEnlace['enlace'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="button" class="btn-ver-clave" onclick="copiarEnlace()">Copiar</button>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap mt-4">
            <?php if (!empty($ultimoEnlace['whatsapp'])): ?>
                <a href="<?= htmlspecialchars($ultimoEnlace['whatsapp'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener" class="btn btn-success">
                    Enviar por WhatsApp al <?= htmlspecialchars($ultimoEnlace['telefono']) ?>
                </a>
            <?php else: ?>
                <span class="alert alert-warning mb-0" style="font-size:.85rem">
                    Ese docente no tiene teléfono cargado. Agrégaselo en Cuentas
                    o cópiale el enlace a mano.
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header-flex">
        <h2 class="card-titulo mb-0">Pendientes</h2>
        <span class="badge <?= empty($solicitudes) ? 'badge-neutral' : 'badge-danger' ?>">
            <?= count($solicitudes) ?>
        </span>
    </div>

    <?php if (empty($solicitudes)): ?>
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">No hay solicitudes pendientes</p>
            <p class="text-muted">
                Cuando un docente pulse "Olvidé mi contraseña" en el acceso,
                aparecerá aquí y te sonará la campanita.
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Docente</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Solicitado</th>
                        <th class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $s): ?>
                        <tr>
                            <td class="font-medium"><?= htmlspecialchars(trim($s['nombre'] . ' ' . $s['apellido'])) ?></td>
                            <td class="celda-correo"><?= htmlspecialchars($s['correo']) ?></td>
                            <td class="text-muted">
                                <?= !empty($s['telefono'])
                                    ? htmlspecialchars($s['telefono'])
                                    : '<span class="text-danger">sin teléfono</span>' ?>
                            </td>
                            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($s['creado_en'])) ?></td>
                            <td class="text-right">
                                <div class="acciones-fila">
                                    <form action="<?= $base ?>/admin/solicitudes/atender" method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Generar enlace</button>
                                    </form>
                                    <form action="<?= $base ?>/admin/solicitudes/rechazar" method="POST" class="inline"
                                          data-confirmar="¿Descartar esta solicitud sin generar el enlace?">
                                        <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-peligro-suave">Descartar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</div>

<script>
function copiarEnlace() {
    const campo = document.getElementById('enlaceGenerado');
    campo.select();
    navigator.clipboard?.writeText(campo.value)
        .then(() => (window.avisar || alert)('Enlace copiado.', 'ok'))
        .catch(() => document.execCommand('copy'));
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
