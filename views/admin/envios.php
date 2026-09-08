<?php
/**
 * Envío masivo de accesos a los docentes.
 *
 * El caso real: arranca el periodo con muchos docentes y hay que hacerles
 * llegar su acceso. Se marcan los que correspondan, el sistema genera un
 * enlace de un solo uso para cada uno y prepara su mensaje de WhatsApp.
 *
 * Se envía un enlace y no la contraseña porque una clave escrita en un chat
 * queda ahí para siempre; el enlace se quema al usarse y caduca.
 */

$titulo = 'Enviar Accesos - ISTPET';
$vista  = 'admin-envios';
require dirname(__DIR__) . '/layouts/header.php';

$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');

$sinTelefonoTotal = 0;
foreach ($usuarios as $u) {
    if (empty($u['telefono'])) {
        $sinTelefonoTotal++;
    }
}
?>

<nav class="breadcrumb">
    <a href="<?= $base ?>/admin">Supervisión</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Enviar accesos</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">Enviar Accesos a Docentes</h1>
        <p class="page-subtitle">
            Genera el enlace de contraseña de varios docentes a la vez y envíaselos por WhatsApp
        </p>
    </div>
    <div class="acciones-cabecera">
        <a href="<?= $base ?>/admin/docentes" class="btn btn-back">&larr; Cuentas</a>
        <a href="<?= $base ?>/admin/solicitudes" class="btn btn-outline">Solicitudes</a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<?php if ($sinTelefonoTotal > 0): ?>
    <div class="alert alert-warning">
        <span>
            Hay <strong><?= $sinTelefonoTotal ?></strong> cuenta(s) sin teléfono cargado.
            A esas se les genera igual el enlace, pero habrá que pasárselo a mano:
            cárgales el número en <a href="<?= $base ?>/admin/docentes">Cuentas</a> para
            poder enviárselo por WhatsApp.
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($generados)): ?>
    <?php
    // Se reutiliza el mismo bloque de envíos que usa el docente
    $conWhatsapp = array_values(array_filter($generados, static fn($g) => !empty($g['whatsapp'])));

    $listos = array_map(static fn($g) => [
        'nombre'   => $g['nombre'],
        'telefono' => $g['telefono'],
        'mensaje'  => $g['mensaje'],
        'enlace'   => $g['whatsapp'],
    ], $conWhatsapp);

    $sinTelefono = array_values(array_map(
        static fn($g) => ['nombre' => $g['nombre'] . ' — ' . $g['enlace']],
        array_filter($generados, static fn($g) => empty($g['whatsapp']))
    ));

    $tituloTanda = 'Enlaces generados (válidos ' . (int)$minutos . ' minutos)';
    include dirname(__DIR__) . '/partials/tanda_envios.php';
    ?>
<?php endif; ?>

<div class="card">
    <div class="card-header-flex">
        <h2 class="card-titulo mb-0">Elige a quiénes</h2>
        <span class="badge badge-neutral"><?= count($usuarios) ?> cuenta(s) activa(s)</span>
    </div>

    <form method="GET" action="<?= $base ?>/admin/envios" class="filtros-grid mb-4">
        <div class="form-group mb-0">
            <label for="rol" class="form-label">Rol</label>
            <select id="rol" name="rol" class="form-select" onchange="this.form.requestSubmit()">
                <option value="">Todas las cuentas</option>
                <option value="docente" <?= $rolFiltro === 'docente' ? 'selected' : '' ?>>Solo docentes</option>
                <option value="admin"   <?= $rolFiltro === 'admin' ? 'selected' : '' ?>>Solo administradores</option>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="filtro" class="form-label">Filtro</label>
            <select id="filtro" name="filtro" class="form-select" onchange="this.form.requestSubmit()">
                <option value="">Todos</option>
                <option value="sin_telefono" <?= $filtro === 'sin_telefono' ? 'selected' : '' ?>>
                    Solo los que no tienen teléfono
                </option>
            </select>
        </div>
    </form>

    <?php if (empty($usuarios)): ?>
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">No hay cuentas con ese filtro</p>
        </div>
    <?php else: ?>
        <form action="<?= $base ?>/admin/envios/generar" method="POST"
              data-confirmar="Se generará un enlace nuevo de contraseña para cada docente marcado. Los enlaces anteriores que no se hayan usado dejarán de servir. ¿Continuar?">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">

            <div class="lista-candidatos">
                <?php foreach ($usuarios as $u): ?>
                    <?php $esYo = ((int)$u['id'] === (int)$usuarioActualId); ?>
                    <label class="candidato">
                        <input type="checkbox" name="usuario_id[]" value="<?= (int)$u['id'] ?>"
                               <?= $esYo ? 'disabled' : '' ?>>
                        <span>
                            <strong><?= htmlspecialchars(trim($u['apellido'] . ' ' . $u['nombre'])) ?></strong>
                            <small>
                                <?= htmlspecialchars($u['correo']) ?>
                                <?php if (!empty($u['telefono'])): ?>
                                    &middot; <?= htmlspecialchars($u['telefono']) ?>
                                <?php else: ?>
                                    &middot; <span class="text-danger">sin teléfono</span>
                                <?php endif; ?>
                                <?php if ($esYo): ?>
                                    &middot; <em>(tu cuenta)</em>
                                <?php endif; ?>
                            </small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 flex-wrap mt-4">
                <button type="button" class="btn btn-outline btn-sm" onclick="marcarTodos(true)">Marcar todos</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="marcarTodos(false)">Desmarcar</button>
                <button type="submit" class="btn btn-primary">Generar enlaces</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function marcarTodos(estado) {
    document.querySelectorAll('.lista-candidatos input[type=checkbox]:not([disabled])')
        .forEach(c => { c.checked = estado; });
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
