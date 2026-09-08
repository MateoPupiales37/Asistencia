<?php
/**
 * Ficha completa de una clase.
 *
 * Antes, las tablas "Últimas Clases" del docente y del administrador eran solo
 * texto: se veia la fecha y la materia, pero no habia forma de entrar y saber
 * QUIENES estuvieron en esa clase. Esta pantalla resuelve eso.
 */

$titulo = 'Detalle de la Clase - ISTPET';
$vista  = 'clase-detalle';
require dirname(__DIR__) . '/layouts/header.php';

$abierta  = ($sesion['estado'] === 'abierta');
$presentes = (int)$resumen['presentes'];
$total     = (int)$resumen['total'];

// Cuantos matriculados no aparecieron. Solo tiene sentido si el curso tiene
// alumnos matriculados; si no, no hay contra que comparar.
$ausentes = max(0, (int)$matriculados - $total);

$duracion = '';
if (!empty($sesion['hora_fin'])) {
    $minutos  = max(0, (int)round((strtotime($sesion['hora_fin']) - strtotime($sesion['hora_inicio'])) / 60));
    $duracion = intdiv($minutos, 60) . 'h ' . str_pad((string)($minutos % 60), 2, '0', STR_PAD_LEFT) . 'm';
}
?>

<nav class="breadcrumb">
    <a href="<?= $base ?><?= $esAdmin ? '/admin' : '/docente' ?>"><?= $esAdmin ? 'Supervisión' : 'Mi Clase' ?></a>
    <span class="breadcrumb-separator">/</span>
    <a href="<?= $base ?>/reportes">Reportes</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Clase del <?= htmlspecialchars($sesion['fecha']) ?></span>
</nav>

<div class="page-header">
    <div>
        <div class="d-flex align-center gap-2 flex-wrap mb-2">
            <span class="badge <?= $abierta ? 'badge-success pulse-badge' : 'badge-neutral' ?>">
                <?= $abierta ? 'EN CURSO' : 'CERRADA' ?>
            </span>
            <span class="curso-ambiente amb-<?= strtolower(str_replace(' ', '-', $sesion['ambiente'])) ?>">
                <?= htmlspecialchars($sesion['ambiente']) ?>
            </span>
        </div>
        <h1 class="page-title"><?= htmlspecialchars($sesion['materia']) ?></h1>
        <p class="page-subtitle">
            <?= htmlspecialchars($sesion['semestre']) ?>
            &bull; <?= htmlspecialchars(trim($sesion['docente_nombre'] . ' ' . $sesion['docente_apellido'])) ?>
        </p>
    </div>
    <div class="acciones-cabecera">
        <a href="<?= $base ?><?= $esAdmin ? '/admin' : '/docente' ?>" class="btn btn-back">&larr; Volver</a>
        <a href="<?= $base ?>/reportes?curso_id=<?= (int)$sesion['curso_id'] ?>" class="btn btn-outline">
            Ver todas las clases de este curso
        </a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- ================== DATOS DE LA CLASE ================== -->
<div class="card mb-6">
    <h2 class="card-titulo">Datos de la Clase</h2>
    <div class="datos-clase">
        <div class="dato">
            <span class="dato-etiqueta">Fecha</span>
            <span class="dato-valor"><?= htmlspecialchars($sesion['fecha']) ?></span>
        </div>
        <div class="dato">
            <span class="dato-etiqueta">Inicio</span>
            <span class="dato-valor"><?= date('H:i', strtotime($sesion['hora_inicio'])) ?></span>
        </div>
        <div class="dato">
            <span class="dato-etiqueta">Fin</span>
            <span class="dato-valor">
                <?= !empty($sesion['hora_fin']) ? date('H:i', strtotime($sesion['hora_fin'])) : 'en curso' ?>
            </span>
        </div>
        <?php if ($duracion !== ''): ?>
            <div class="dato">
                <span class="dato-etiqueta">Duración</span>
                <span class="dato-valor"><?= htmlspecialchars($duracion) ?></span>
            </div>
        <?php endif; ?>
        <div class="dato">
            <span class="dato-etiqueta">Docente</span>
            <span class="dato-valor"><?= htmlspecialchars(trim($sesion['docente_nombre'] . ' ' . $sesion['docente_apellido'])) ?></span>
        </div>
        <div class="dato">
            <span class="dato-etiqueta">Ambiente</span>
            <span class="dato-valor"><?= htmlspecialchars($sesion['ambiente']) ?></span>
        </div>
        <div class="dato">
            <span class="dato-etiqueta">Código de entrada</span>
            <span class="dato-valor mono"><?= htmlspecialchars($sesion['codigo_entrada']) ?></span>
        </div>
        <div class="dato">
            <span class="dato-etiqueta">Código de salida</span>
            <span class="dato-valor mono">
                <?= !empty($sesion['codigo_salida']) ? htmlspecialchars($sesion['codigo_salida']) : '—' ?>
            </span>
        </div>
    </div>
</div>

<!-- ================== RESUMEN ================== -->
<div class="stats-grid mb-6">
    <div class="stat-card destacada">
        <span class="stat-label">Asistieron</span>
        <span class="stat-value"><?= $total ?></span>
        <span class="stat-pie">
            <?= (int)$matriculados > 0 ? 'de ' . (int)$matriculados . ' matriculados' : 'registros en la clase' ?>
        </span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Siguen en clase</span>
        <span class="stat-value"><?= $presentes ?></span>
        <span class="stat-pie"><?= $abierta ? 'ahora mismo' : 'al cerrar' ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Salidas anticipadas</span>
        <span class="stat-value"><?= (int)$resumen['anticipadas'] ?></span>
        <span class="stat-pie">con motivo registrado</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Registro manual</span>
        <span class="stat-value"><?= (int)$resumen['manuales'] ?></span>
        <span class="stat-pie">los puso el docente</span>
    </div>
    <?php if ((int)$matriculados > 0): ?>
        <div class="stat-card">
            <span class="stat-label">No asistieron</span>
            <span class="stat-value"><?= $ausentes ?></span>
            <span class="stat-pie">matriculados que faltaron</span>
        </div>
    <?php endif; ?>
</div>

<!-- ================== LISTA NOMINAL ================== -->
<div class="card">
    <div class="card-header-flex">
        <h2 class="card-titulo mb-0">Estudiantes en esta Clase</h2>
        <span class="badge badge-neutral"><?= count($asistencias) ?> registro(s)</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Cédula</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Estado</th>
                    <th>Registro</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($asistencias)): ?>
                    <tr>
                        <td colspan="6" class="table-empty">
                            Nadie registró asistencia en esta clase.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($asistencias as $a): ?>
                        <tr>
                            <td>
                                <span class="font-medium"><?= htmlspecialchars(trim($a['nombre'] . ' ' . $a['apellido'])) ?></span>
                                <div class="celda-sub">
                                    <?= htmlspecialchars($a['codigo']) ?> &bull; <?= htmlspecialchars($a['semestre']) ?>
                                </div>
                            </td>
                            <td class="table-code">
                                <?= !empty($a['cedula']) ? htmlspecialchars($a['cedula']) : '<span class="text-light">—</span>' ?>
                            </td>
                            <td class="font-semibold text-primary">
                                <?= $a['hora_entrada'] ? date('H:i:s', strtotime($a['hora_entrada'])) : '—' ?>
                            </td>
                            <td class="text-muted">
                                <?= $a['hora_salida'] ? date('H:i:s', strtotime($a['hora_salida'])) : '—' ?>
                            </td>
                            <td>
                                <span class="badge estado-<?= htmlspecialchars($a['estado'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(Catalogo::etiquetaEstado($a['estado'])) ?>
                                </span>
                                <?php if (!empty($a['motivo'])): ?>
                                    <div class="motivo-linea">
                                        <?= htmlspecialchars(Catalogo::etiquetaMotivo($a['motivo'])) ?>
                                        <?php if (!empty($a['motivo_detalle'])): ?>
                                            — <?= htmlspecialchars($a['motivo_detalle']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted">
                                <?= $a['origen'] === 'manual' ? 'Manual' : 'Escaneo QR' ?>
                                <?php if (($a['aprobacion'] ?? 'aprobada') === 'pendiente'): ?>
                                    <div><span class="mini-tag">pendiente de aprobar</span></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
