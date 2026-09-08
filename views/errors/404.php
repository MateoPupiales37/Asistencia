<?php
$base   = $base ?? '';
$codigo = $codigo ?? 404;

// 405 = la direccion existe pero se llego a ella con un metodo HTTP incorrecto
$esMetodoNoPermitido = ($codigo === 405);

$titulo = $esMetodoNoPermitido
    ? '405 - Metodo No Permitido'
    : '404 - Pagina No Encontrada';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="content-narrow">
    <div class="card text-center">
        <span class="auth-badge mb-4">ERROR <?= (int)$codigo ?></span>
        <h1 class="error-404-number"><?= (int)$codigo ?></h1>
        <h2 class="text-primary font-bold mb-2" style="font-size: 1.4rem;">
            <?= $esMetodoNoPermitido ? 'Método No Permitido' : 'Página No Encontrada' ?>
        </h2>
        <p class="text-muted mb-6" style="font-size: 0.95rem;">
            <?= $esMetodoNoPermitido
                ? 'Esta dirección existe, pero no se puede abrir de esta forma. Vuelve a la pantalla anterior y usa los botones del sistema.'
                : 'La dirección web a la que intentas acceder no existe o fue movida.' ?>
        </p>

        <div class="d-flex flex-column gap-2">
            <?php if (!empty($_SESSION['usuario_id'])): ?>
                <?php $esAdmin = ($_SESSION['usuario_rol'] ?? '') === 'admin'; ?>
                <a href="<?= $base ?><?= $esAdmin ? '/admin' : '/docente' ?>" class="btn btn-primary btn-block">
                    <?= $esAdmin ? 'Ir al Panel de Administración' : 'Ir a Mi Panel Docente' ?> &rarr;
                </a>
            <?php else: ?>
                <a href="<?= $base ?>/asistencia" class="btn btn-primary btn-block">
                    Registrar mi asistencia &rarr;
                </a>
            <?php endif; ?>
            <a href="<?= $base ?>/" class="btn btn-outline btn-block">
                &larr; Volver a la Página Principal
            </a>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
