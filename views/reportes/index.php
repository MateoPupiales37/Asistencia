<?php
/**
 * Reportes de asistencia.
 *
 * Esta vista habia quedado desfasada del controlador: pedia campos que la
 * consulta ya no devuelve ('hora', 'estudiante', 'carrera', 'docente') y por eso
 * la pantalla se llenaba de avisos "Undefined array key" con la ruta del
 * servidor a la vista. Ahora usa exactamente las claves que entrega
 * Asistencia::filtrar() y los mismos filtros que valida ReporteController.
 */

$titulo = 'Reportes de Asistencia - ISTPET';
$vista  = 'reportes';
require dirname(__DIR__) . '/layouts/header.php';

$esAdmin = $esAdmin ?? false;
$panel   = $esAdmin ? '/admin' : '/docente';

// Los mismos filtros viajan a la pantalla y a las tres exportaciones, para que
// lo que se descarga sea siempre lo que se esta viendo en la tabla.
$paramsExport = array_filter([
    'fecha_inicio'  => $filtros['fecha_inicio'] ?? '',
    'fecha_fin'     => $filtros['fecha_fin'] ?? '',
    'materia_id'    => $filtros['materia_id'] ?? '',
    'curso_id'      => $filtros['curso_id'] ?? '',
    'estudiante_id' => $filtros['estudiante_id'] ?? '',
    'cedula'        => $filtros['cedula'] ?? '',
    'ambiente'      => $filtros['ambiente'] ?? '',
    'semestre'      => $filtros['semestre'] ?? '',
    'estado'        => $filtros['estado'] ?? '',
    'docente_id'    => $esAdmin ? ($filtros['docente_id'] ?? '') : '',
], static fn($v) => $v !== '' && $v !== null);

$queryExport = http_build_query($paramsExport);

// Etiquetas legibles de los filtros activos, para las pastillas de resumen
$activos = [];
if ($esAdmin && !empty($filtros['docente_id'])) {
    foreach ($docentes as $d) {
        if ((int)$d['id'] === (int)$filtros['docente_id']) {
            $activos['Docente'] = trim($d['nombre'] . ' ' . $d['apellido']);
        }
    }
}
if (!empty($filtros['materia_id'])) {
    foreach ($materias as $m) {
        if ((int)$m['id'] === (int)$filtros['materia_id']) {
            $activos['Materia'] = $m['nombre'];
        }
    }
}
if (!empty($filtros['curso_id'])) {
    foreach ($cursos as $c) {
        if ((int)$c['id'] === (int)$filtros['curso_id']) {
            $activos['Curso'] = Curso::etiqueta($c);
        }
    }
}
if (!empty($filtros['estudiante_id'])) {
    foreach ($estudiantes as $e) {
        if ((int)$e['id'] === (int)$filtros['estudiante_id']) {
            $activos['Estudiante'] = trim($e['apellido'] . ' ' . $e['nombre']);
        }
    }
}
if (!empty($filtros['cedula']))   { $activos['Cédula']   = $filtros['cedula']; }
if (!empty($filtros['ambiente'])) { $activos['Ambiente'] = $filtros['ambiente']; }
if (!empty($filtros['semestre'])) { $activos['Semestre'] = $filtros['semestre']; }
if (!empty($filtros['estado']))   { $activos['Estado']   = Catalogo::etiquetaEstado($filtros['estado']); }
if (!empty($filtros['fecha_inicio'])) { $activos['Desde'] = $filtros['fecha_inicio']; }
if (!empty($filtros['fecha_fin']))    { $activos['Hasta'] = $filtros['fecha_fin']; }
?>

<nav class="breadcrumb">
    <a href="<?= $base ?><?= $panel ?>"><?= $esAdmin ? 'Supervisión' : 'Mi Clase' ?></a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Reportes</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">Reportes de Asistencia</h1>
        <p class="page-subtitle">
            <?= $esAdmin
                ? 'Auditoría y exportación consolidada de toda la institución'
                : 'Consulta y exporta la asistencia de tus clases' ?>
        </p>
    </div>

    <div class="acciones-cabecera" data-region="exportar">
        <a href="<?= $base ?><?= $panel ?>" class="btn btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Volver
        </a>
        <a href="<?= $base ?>/reportes/excel<?= $queryExport ? '?' . $queryExport : '' ?>" class="btn btn-excel">Excel</a>
        <a href="<?= $base ?>/reportes/pdf<?= $queryExport ? '?' . $queryExport : '' ?>" class="btn btn-pdf"
           title="Descarga exactamente los registros que estás viendo">PDF</a>
    </div>
</div>

<!-- ================== FILTROS ================== -->
<div class="card card-filter mb-6">
    <div class="card-header-flex">
        <h2 class="card-titulo mb-0">Filtros de Búsqueda</h2>
        <div class="chips-container">
            <span class="text-muted" style="font-size:.8rem">Periodo:</span>
            <button type="button" class="chip-btn" onclick="periodo('hoy')">Hoy</button>
            <button type="button" class="chip-btn" onclick="periodo('mes')">Este mes</button>
            <button type="button" class="chip-btn" onclick="periodo('30')">Últimos 30 días</button>
            <button type="button" class="chip-btn" onclick="periodo('todo')">Todo</button>
        </div>
    </div>

    <form method="GET" action="<?= $base ?>/reportes" class="filtros-grid" id="formFiltros">
        <div class="form-group mb-0">
            <label for="fecha_inicio" class="form-label">Desde</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control"
                   value="<?= htmlspecialchars((string)($filtros['fecha_inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group mb-0">
            <label for="fecha_fin" class="form-label">Hasta</label>
            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control"
                   value="<?= htmlspecialchars((string)($filtros['fecha_fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <?php if ($esAdmin): ?>
            <div class="form-group mb-0">
                <label for="docente_id" class="form-label">Docente</label>
                <select id="docente_id" name="docente_id" class="form-select">
                    <option value="">Todos los docentes</option>
                    <?php foreach ($docentes as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($filtros['docente_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim($d['apellido'] . ' ' . $d['nombre'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="form-group mb-0">
                <label for="curso_id" class="form-label">Mi curso</label>
                <select id="curso_id" name="curso_id" class="form-select">
                    <option value="">Todos mis cursos</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)($filtros['curso_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(Curso::etiqueta($c)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-group mb-0">
            <label for="materia_id" class="form-label">Materia</label>
            <select id="materia_id" name="materia_id" class="form-select">
                <option value="">Todas las materias</option>
                <?php foreach ($materias as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)($filtros['materia_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-0">
            <label for="ambiente" class="form-label">Ambiente</label>
            <select id="ambiente" name="ambiente" class="form-select">
                <option value="">Todos los ambientes</option>
                <?php foreach ($ambientes as $a): ?>
                    <option value="<?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?>" <?= ($filtros['ambiente'] ?? '') === $a ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-0">
            <label for="semestre" class="form-label">Semestre</label>
            <select id="semestre" name="semestre" class="form-select">
                <option value="">Todos los semestres</option>
                <?php foreach ($semestres as $s): ?>
                    <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= ($filtros['semestre'] ?? '') === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-0">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos los estados</option>
                <?php foreach ($estados as $clave => $etiqueta): ?>
                    <option value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>" <?= ($filtros['estado'] ?? '') === $clave ? 'selected' : '' ?>>
                        <?= htmlspecialchars($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-0">
            <label for="estudiante_id" class="form-label">Estudiante</label>
            <select id="estudiante_id" name="estudiante_id" class="form-select">
                <option value="">Todos los estudiantes</option>
                <?php foreach ($estudiantes as $e): ?>
                    <option value="<?= (int)$e['id'] ?>" <?= (int)($filtros['estudiante_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(trim($e['apellido'] . ' ' . $e['nombre'])) ?> — <?= htmlspecialchars($e['codigo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-0">
            <label for="cedula" class="form-label">Cédula</label>
            <input type="text" id="cedula" name="cedula" class="form-control form-control-code"
                   inputmode="numeric" maxlength="10" placeholder="10 dígitos"
                   oninput="this.value = this.value.replace(/\D/g,'')"
                   value="<?= htmlspecialchars((string)($filtros['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group mb-0 alinear-abajo">
            <div class="form-fila">
                <button type="submit" class="btn btn-primary flex-1">Filtrar</button>
                <a href="<?= $base ?>/reportes" class="btn btn-outline">Limpiar</a>
            </div>
        </div>
    </form>

    <?php if (!empty($activos)): ?>
        <div class="filtros-activos">
            <span class="text-muted" style="font-size:.8rem;font-weight:600">Filtros activos:</span>
            <?php foreach ($activos as $etiqueta => $valor): ?>
                <span class="filter-pill"><?= htmlspecialchars($etiqueta) ?>: <?= htmlspecialchars((string)$valor) ?></span>
            <?php endforeach; ?>
            <a href="<?= $base ?>/reportes" class="text-danger" style="font-size:.8rem;text-decoration:underline">Quitar todos</a>
        </div>
    <?php endif; ?>
</div>

<!-- ================== RESUMEN GRAFICO ================== -->
<?php if (!empty($asistencias)): ?>
    <div class="pasteles-grid mb-6" data-region="graficos">
        <?php
        $datos = array_map(
            static fn($d) => ['etiqueta' => Catalogo::etiquetaEstado($d['etiqueta']), 'total' => $d['total']],
            $resumenEstado
        );
        $tituloGrafico = 'Resultado por Estado';
        include dirname(__DIR__) . '/partials/pastel.php';

        $datos  = $resumenMateria;
        $tituloGrafico = 'Resultado por Materia';
        include dirname(__DIR__) . '/partials/pastel.php';
        ?>
    </div>
<?php endif; ?>

<!-- ================== TABLA ================== -->
<div class="card" data-region="resultados">
    <div class="card-header-flex">
        <h2 class="card-titulo mb-0">Resultados</h2>
        <span class="badge badge-neutral"><?= (int)$total ?> registro(s)</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover tabla-reporte">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Estudiante</th>
                    <th>Cédula</th>
                    <th>Materia</th>
                    <th>Ambiente</th>
                    <?php if ($esAdmin): ?><th>Docente</th><?php endif; ?>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($asistencias)): ?>
                    <tr>
                        <td colspan="<?= $esAdmin ? 9 : 8 ?>" class="table-empty">
                            No se encontraron asistencias con estos filtros.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($asistencias as $a): ?>
                        <tr>
                            <td class="font-semibold" data-col="Fecha"><?= htmlspecialchars($a['fecha']) ?></td>

                            <td data-col="Estudiante">
                                <span class="font-medium"><?= htmlspecialchars(trim($a['nombre'] . ' ' . $a['apellido'])) ?></span>
                                <div class="celda-sub">
                                    <?= htmlspecialchars($a['codigo']) ?> &bull; <?= htmlspecialchars($a['semestre']) ?>
                                    <?php if ($a['origen'] === 'manual'): ?>
                                        <span class="mini-tag">manual</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="table-code" data-col="Cédula">
                                <?= !empty($a['cedula']) ? htmlspecialchars($a['cedula']) : '<span class="text-light">—</span>' ?>
                            </td>

                            <td data-col="Materia"><?= htmlspecialchars($a['materia']) ?></td>

                            <td class="text-muted" data-col="Ambiente">
                                <?= htmlspecialchars($a['ambiente']) ?>
                                <div class="celda-sub"><?= htmlspecialchars($a['semestre_curso']) ?></div>
                            </td>

                            <?php if ($esAdmin): ?>
                                <td class="text-muted" data-col="Docente">
                                    <?= htmlspecialchars(trim($a['docente_nombre'] . ' ' . $a['docente_apellido'])) ?>
                                </td>
                            <?php endif; ?>

                            <td class="font-semibold text-primary" data-col="Entrada">
                                <?= $a['hora_entrada'] ? date('H:i', strtotime($a['hora_entrada'])) : '—' ?>
                            </td>

                            <td class="text-muted" data-col="Salida">
                                <?= $a['hora_salida'] ? date('H:i', strtotime($a['hora_salida'])) : '—' ?>
                            </td>

                            <td data-col="Estado">
                                <span class="badge estado-<?= htmlspecialchars($a['estado'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(Catalogo::etiquetaEstado($a['estado'])) ?>
                                </span>
                                <?php if (!empty($a['motivo'])): ?>
                                    <div class="motivo-linea" title="<?= htmlspecialchars((string)$a['motivo_detalle'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(Catalogo::etiquetaMotivo($a['motivo'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Atajos de periodo: rellenan las fechas y reenvian el formulario
function periodo(tipo) {
    const desde = document.getElementById('fecha_inicio');
    const hasta = document.getElementById('fecha_fin');
    const hoy = new Date();

    // Se formatea con la fecha LOCAL: toISOString() usa UTC y en Ecuador
    // (UTC-5) devolvia el dia siguiente a partir de las 19:00
    const fmt = d => d.getFullYear() + '-'
        + String(d.getMonth() + 1).padStart(2, '0') + '-'
        + String(d.getDate()).padStart(2, '0');

    if (tipo === 'hoy') {
        desde.value = hasta.value = fmt(hoy);
    } else if (tipo === 'mes') {
        desde.value = fmt(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
        hasta.value = fmt(hoy);
    } else if (tipo === '30') {
        const atras = new Date();
        atras.setDate(hoy.getDate() - 30);
        desde.value = fmt(atras);
        hasta.value = fmt(hoy);
    } else {
        desde.value = hasta.value = '';
    }

    document.getElementById('formFiltros').submit();
}

document.getElementById('formFiltros').addEventListener('submit', function (e) {
    const desde = document.getElementById('fecha_inicio').value;
    const hasta = document.getElementById('fecha_fin').value;

    if (desde && hasta && desde > hasta) {
        e.preventDefault();
        alert('La fecha "Desde" no puede ser posterior a la fecha "Hasta".');
        return;
    }

    const cedula = document.getElementById('cedula').value.trim();
    if (cedula !== '' && cedula.length !== 10) {
        e.preventDefault();
        alert('La cédula debe tener exactamente 10 dígitos.');
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
