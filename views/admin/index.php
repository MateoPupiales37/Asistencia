<?php
$titulo = 'Supervisión Institucional - ISTPET';
$vista  = 'admin-supervision';
require dirname(__DIR__) . '/layouts/header.php';
$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Supervisión Institucional</h1>
        <p class="page-subtitle">
            Control y monitoreo del sistema &bull; <strong><?= htmlspecialchars($adminNombre) ?></strong>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $base ?>/admin/periodo" class="btn btn-outline">Período</a>
        <a href="<?= $base ?>/admin/docentes" class="btn btn-outline">Cuentas</a>
        <a href="<?= $base ?>/reportes" class="btn btn-primary">Reportes</a>
    </div>
</div>

<!-- Recordatorio del ciclo en el que se esta trabajando -->
<div class="periodo-barra">
    <div>
        <span class="periodo-barra-etiqueta">Trabajando en el período</span>
        <strong><?= htmlspecialchars($periodo['nombre'] ?? 'Sin período') ?></strong>
        <?php if (!empty($periodo)): ?>
            <span class="text-muted">
                (<?= date('d/m/Y', strtotime($periodo['fecha_inicio'])) ?>
                al <?= date('d/m/Y', strtotime($periodo['fecha_fin'])) ?>)
            </span>
        <?php endif; ?>
    </div>
    <a href="<?= $base ?>/admin/periodo" class="btn btn-sm btn-outline">Cambiar período</a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- Métricas generales -->
<div class="stats-grid mb-6" data-region="resumen">
    <div class="stat-card clicable" onclick="window.location='<?= $base ?>/admin/docentes'">
        <span class="stat-label">Docentes activos</span>
        <span class="stat-value"><?= (int)$totalDocentes ?></span>
        <span class="stat-pie">Cuentas habilitadas</span>
    </div>
    <div class="stat-card clicable" onclick="window.location='<?= $base ?>/reportes'">
        <span class="stat-label">Estudiantes</span>
        <span class="stat-value"><?= (int)$totalEstudiantes ?></span>
        <span class="stat-pie">En el padrón</span>
    </div>
    <div class="stat-card clicable" onclick="window.location='<?= $base ?>/admin/materias'">
        <span class="stat-label">Cursos activos</span>
        <span class="stat-value"><?= (int)$totalCursos ?></span>
        <span class="stat-pie"><?= (int)$totalMaterias ?> materias</span>
    </div>
    <div class="stat-card clicable" onclick="window.location='<?= $base ?>/reportes'">
        <span class="stat-label">Clases dictadas</span>
        <span class="stat-value"><?= (int)$totalClases ?></span>
        <span class="stat-pie"><?= (int)$clasesHoy ?> hoy</span>
    </div>
    <div class="stat-card destacada clicable" onclick="window.location='<?= $base ?>/reportes?fecha_inicio=<?= date('Y-m-d') ?>&fecha_fin=<?= date('Y-m-d') ?>'">
        <span class="stat-label">Asistencias hoy</span>
        <span class="stat-value"><?= (int)$asistenciasHoy ?></span>
        <span class="stat-pie"><?= (int)$asistenciasTotal ?> en total</span>
    </div>
</div>

<!-- Clases abiertas en este momento -->
<div class="card mb-6" data-region="clases-abiertas">
    <div class="card-header-flex">
        <div class="d-flex align-center gap-2">
            <span class="badge badge-info pulse-badge">EN VIVO</span>
            <h2 class="card-titulo mb-0">Clases Abiertas Ahora</h2>
        </div>
        <span class="text-muted"><?= count($clasesAbiertas) ?> aula(s)</span>
    </div>

    <?php if (empty($clasesAbiertas)): ?>
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">No hay clases abiertas en este momento</p>
            <p class="text-muted">
                Cuando un docente inicie una clase y proyecte su QR, aparecerá aquí para su supervisión.
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Docente</th>
                        <th>Materia</th>
                        <th>Ambiente</th>
                        <th>Inicio</th>
                        <th class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clasesAbiertas as $c): ?>
                        <tr>
                            <td class="font-medium">
                                <?= htmlspecialchars(trim($c['docente_nombre'] . ' ' . $c['docente_apellido'])) ?>
                            </td>
                            <td><?= htmlspecialchars($c['materia']) ?></td>
                            <td class="text-muted">
                                <?= htmlspecialchars($c['ambiente']) ?>
                                <div class="celda-sub"><?= htmlspecialchars($c['semestre']) ?></div>
                            </td>
                            <td class="text-muted">
                                <?= date('H:i', strtotime($c['hora_inicio'])) ?>
                                <div><a href="<?= $base ?>/reportes/clase?id=<?= (int)$c['id'] ?>" class="enlace-detalle">Ver detalle &rarr;</a></div>
                            </td>
                            <td class="text-right">
                                <form action="<?= $base ?>/admin/clase/cerrar" method="POST" class="inline"
                                      onsubmit="return confirm('¿Forzar el cierre de esta clase?');">
                                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                    <input type="hidden" name="sesion_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-peligro-suave">Finalizar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Estadísticas: solo gráficos de pastel, separados por criterio -->
<h2 class="seccion-titulo">Estadísticas por Criterio</h2>
<div class="pasteles-grid mb-6" data-region="graficos">
    <?php
    // A cada porcion se le adjunta el filtro que le corresponde en el reporte,
    // para poder hacer clic y ver exactamente esos registros.
    $enlaceBase = $base . '/reportes';

    $datos = array_map(static function ($d) use ($materiasPorNombre) {
        $d['filtro'] = isset($materiasPorNombre[$d['etiqueta']])
            ? 'materia_id=' . (int)$materiasPorNombre[$d['etiqueta']]
            : null;
        return $d;
    }, $graficoMateria);
    $tituloGrafico = 'Asistencias por Materia';
    include dirname(__DIR__) . '/partials/pastel.php';

    $enlaceBase = $base . '/reportes';
    $datos = array_map(
        static fn($d) => [
            'etiqueta' => Catalogo::etiquetaEstado($d['etiqueta']),
            'total'    => $d['total'],
            'filtro'   => 'estado=' . rawurlencode($d['etiqueta'])
        ],
        $graficoEstado
    );
    $tituloGrafico = 'Asistencias por Estado';
    include dirname(__DIR__) . '/partials/pastel.php';

    $enlaceBase = $base . '/reportes';
    $datos = array_map(
        static fn($d) => $d + ['filtro' => 'ambiente=' . rawurlencode($d['etiqueta'])],
        $graficoAmbiente
    );
    $tituloGrafico = 'Asistencias por Ambiente';
    include dirname(__DIR__) . '/partials/pastel.php';

    // Los motivos no tienen filtro propio en el reporte: se muestran sin enlace
    $datos = array_map(
        static fn($d) => ['etiqueta' => Catalogo::etiquetaMotivo($d['etiqueta']), 'total' => $d['total']],
        $graficoMotivo
    );
    $tituloGrafico = 'Motivos de Salida Anticipada';
    $vacio  = 'No se han registrado salidas anticipadas.';
    include dirname(__DIR__) . '/partials/pastel.php';
    ?>
</div>

<!-- Historial institucional -->
<div class="card" data-region="historial">
    <div class="card-header-flex">
        <h2 class="card-titulo mb-0">Últimas Clases del Instituto</h2>
        <a href="<?= $base ?>/reportes" class="enlace-mas">Ver reportes completos &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Docente</th>
                    <th>Materia</th>
                    <th>Ambiente</th>
                    <th>Horario</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="6" class="table-empty">No se registran clases todavía.</td></tr>
                <?php else: ?>
                    <?php foreach ($historial as $h): ?>
                        <tr class="fila-clicable" onclick="window.location='<?= $base ?>/reportes/clase?id=<?= (int)$h['id'] ?>'"
                            title="Ver quiénes asistieron a esta clase">
                            <td class="font-semibold"><?= htmlspecialchars($h['fecha']) ?></td>
                            <td><?= htmlspecialchars(trim($h['docente_nombre'] . ' ' . $h['docente_apellido'])) ?></td>
                            <td><?= htmlspecialchars($h['materia']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($h['ambiente']) ?></td>
                            <td class="text-muted">
                                <?= date('H:i', strtotime($h['hora_inicio'])) ?> —
                                <?= $h['hora_fin'] ? date('H:i', strtotime($h['hora_fin'])) : 'en curso' ?>
                            </td>
                            <td>
                                <span class="badge <?= $h['estado'] === 'abierta' ? 'badge-success' : 'badge-neutral' ?>">
                                    <?= $h['estado'] === 'abierta' ? 'ABIERTA' : 'CERRADA' ?>
                                </span>
                                <a href="<?= $base ?>/reportes/clase?id=<?= (int)$h['id'] ?>" class="enlace-detalle">Ver detalle &rarr;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
