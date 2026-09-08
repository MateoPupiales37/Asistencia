<?php
/**
 * Envío de credenciales a los estudiantes por WhatsApp.
 *
 * Nota importante: WhatsApp NO permite el envío automático sin contratar su
 * API de negocios, que exige empresa verificada y cobra por mensaje. Lo que
 * se hace aquí es dejar cada mensaje escrito y a un clic, de modo que enviar
 * a un curso entero sea cuestión de minutos en vez de redactar uno por uno.
 */

$titulo = 'Enviar Credenciales - ISTPET';
$vista  = 'docente-envios';
require dirname(__DIR__) . '/layouts/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= $base ?>/docente">Mi Clase</a>
    <span class="breadcrumb-separator">/</span>
    <a href="<?= $base ?>/docente/matriculas">Estudiantes</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Enviar credenciales</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">Enviar Credenciales</h1>
        <p class="page-subtitle">
            Manda a cada estudiante su código y su cédula para que pueda registrarse
        </p>
    </div>
    <div class="acciones-cabecera">
        <a href="<?= $base ?>/docente/matriculas?curso_id=<?= (int)$cursoId ?>" class="btn btn-back">
            &larr; Estudiantes
        </a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<?php if (empty($cursos)): ?>
    <div class="card">
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">No tienes materias asignadas</p>
            <p class="text-muted">El administrador debe asignarte una materia primero.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card card-filter mb-6">
        <form method="GET" action="<?= $base ?>/docente/envios" class="filtros-grid">
            <div class="form-group mb-0">
                <label for="curso_id" class="form-label">Curso</label>
                <select id="curso_id" name="curso_id" class="form-select" onchange="this.form.requestSubmit()">
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$cursoId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(Curso::etiqueta($c)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="filtro" class="form-label">A quiénes</label>
                <select id="filtro" name="filtro" class="form-select" onchange="this.form.requestSubmit()">
                    <option value="">Todos los matriculados</option>
                    <option value="nuevos" <?= $filtro === 'nuevos' ? 'selected' : '' ?>>
                        Solo los nuevos (últimas 24 h)
                    </option>
                    <option value="con_telefono" <?= $filtro === 'con_telefono' ? 'selected' : '' ?>>
                        Solo los que tienen teléfono
                    </option>
                    <option value="sin_telefono" <?= $filtro === 'sin_telefono' ? 'selected' : '' ?>>
                        Solo los que NO tienen teléfono
                    </option>
                </select>
            </div>
        </form>
    </div>

    <?php
    $tituloTanda = 'Mensajes preparados' . ($curso ? ' — ' . htmlspecialchars($curso['materia']) : '');
    include dirname(__DIR__) . '/partials/tanda_envios.php';
    ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
