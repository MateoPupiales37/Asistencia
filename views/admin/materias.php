<?php
/**
 * Gestión académica del administrador.
 *
 * Aquí el administrador hace las dos cosas que el docente no puede hacer:
 *
 *   1. Crear las materias del catálogo institucional. Cada materia nace
 *      ubicada: pertenece a una CARRERA, se dicta en un SEMESTRE y ocurre
 *      dentro del PERÍODO con el que se está trabajando.
 *   2. Asignar QUÉ DOCENTE dicta cada materia y en qué ambiente. Esa
 *      asignación es lo que crea el curso.
 *
 * El semestre ya no se elige al asignar el docente: viene dentro de la
 * materia. Antes se pedía en los dos sitios y bastaba un descuido para
 * asignar "Programación Web de Tercero" a un Cuarto Semestre, dejando dos
 * filas de la misma materia en semestres distintos.
 *
 * A partir de ahí, el docente matricula a sus estudiantes en los cursos que
 * le asignaron. El administrador no matricula alumnos.
 */

$titulo = 'Materias y Asignaciones - ISTPET';
$vista  = 'admin-materias';
require dirname(__DIR__) . '/layouts/header.php';

$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');

$sinDocente = 0;
foreach ($materias as $m) {
    if ((int)$m['activa'] === 1 && empty($m['cursos'])) {
        $sinDocente++;
    }
}

// Las materias se agrupan por semestre: es como el instituto lee su malla,
// y con cuatro semestres la lista plana obligaba a buscar a ojo
$porSemestre = [];
foreach ($materias as $m) {
    $porSemestre[$m['semestre'] ?: 'Sin semestre'][] = $m;
}

// Se respeta el orden oficial de los semestres, no el alfabético
$ordenSemestres = array_column($semestres, 'nombre');
uksort($porSemestre, static function ($a, $b) use ($ordenSemestres) {
    $ia = array_search($a, $ordenSemestres, true);
    $ib = array_search($b, $ordenSemestres, true);
    return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
});

$carrerasActivas = array_values(array_filter($carreras, static fn($c) => (int)$c['activa'] === 1));

// Solo se ofrecen los periodos abiertos: uno cerrado esta para consultar, y
// cargarle materias nuevas seria volver a abrirlo por la puerta de atras.
$periodosAbiertos = array_values(array_filter($periodos, static fn($p) => (int)$p['activo'] === 1));
?>

<nav class="breadcrumb">
    <a href="<?= $base ?>/admin">Supervisión</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Materias</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">Materias y Asignaciones</h1>
        <p class="page-subtitle">
            Crea las materias del instituto y define qué docente dicta cada una
        </p>
    </div>
    <div class="acciones-cabecera">
        <a href="<?= $base ?>/admin" class="btn btn-back">&larr; Supervisión</a>
        <button type="button" class="btn btn-outline" onclick="abrirModal('modalSemestres')">Semestres</button>
        <button type="button" class="btn btn-primary" onclick="nuevaMateria()">+ Nueva Materia</button>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<!-- Recordatorio del período: todo lo de esta pantalla cuelga de él -->
<div class="periodo-barra">
    <div>
        <span class="periodo-barra-etiqueta">Período académico</span>
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

<div class="alert alert-info">
    <span>
        <strong>Cómo funciona:</strong> tú creas la materia indicando su carrera y su
        semestre, y le asignas un docente. Esa asignación genera el curso. Después,
        cada docente entra a su panel y matricula ahí a sus propios estudiantes.
        <br>
        <strong>Una materia tiene un solo docente.</strong> Como el semestre y el
        período ya vienen dentro de la materia, "Programación de Aplicaciones" de
        Tercero y "Programación de Aplicaciones 2" de Cuarto son materias distintas
        y cada una puede tener el suyo.
        <?php if ($sinDocente > 0): ?>
            <br>Hay <strong><?= $sinDocente ?></strong> materia(s) activa(s) sin ningún docente asignado:
            mientras no tengan uno, nadie puede abrir clases de esa materia.
        <?php endif; ?>
    </span>
</div>

<div data-region="materias">
    <?php if (empty($materias)): ?>
        <div class="card">
            <div class="estado-vacio">
                <p class="estado-vacio-titulo">
                    Todavía no hay materias en <?= htmlspecialchars($periodo['nombre'] ?? 'este período') ?>
                </p>
                <p class="text-muted mb-4">
                    Crea la primera con el botón "Nueva Materia". Si ya cargaste la
                    malla en otro período, puedes copiarla desde la pantalla de períodos.
                </p>
                <a href="<?= $base ?>/admin/periodo" class="btn btn-outline">Ir a períodos</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($porSemestre as $nombreSemestre => $grupo): ?>
            <h2 class="grupo-semestre"><?= htmlspecialchars($nombreSemestre) ?></h2>

            <?php foreach ($grupo as $m): ?>
                <?php
                    $inactiva = ((int)$m['activa'] === 0);
                    $tieneDocente = !empty($m['cursos']);
                ?>
                <div class="card mb-4 <?= $inactiva ? 'materia-archivada' : '' ?>">

                    <div class="card-header-flex">
                        <div>
                            <div class="d-flex align-center gap-2 flex-wrap">
                                <span class="badge badge-neutral"><?= htmlspecialchars($m['codigo']) ?></span>
                                <h2 class="card-titulo mb-0"><?= htmlspecialchars($m['nombre']) ?></h2>
                                <?php if ($inactiva): ?>
                                    <span class="badge badge-danger">ARCHIVADA</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted mt-2" style="font-size:.83rem">
                                <?= htmlspecialchars($m['carrera'] ?? 'Sin carrera') ?> &bull;
                                <?= htmlspecialchars($m['semestre']) ?> &bull;
                                <?= (int)$m['total_cursos'] ?> asignación(es)
                            </p>
                        </div>

                        <div class="acciones-fila">
                            <button type="button" class="btn btn-sm btn-outline"
                                    onclick='editarMateria(<?= json_encode([
                                        "id"      => (int)$m["id"],
                                        "codigo"  => $m["codigo"],
                                        "nombre"  => $m["nombre"],
                                        "semestre"=> $m["semestre"],
                                        "carrera" => (int)($m["carrera_id"] ?? 0),
                                        "periodo" => (int)($m["periodo_id"] ?? 0),
                                        // Solo se puede mover de ciclo mientras no
                                        // arrastre asignaciones ni clases dictadas
                                        "movible" => empty($m["cursos"]) && empty($m["archivados"]),
                                        "activa"  => (int)$m["activa"]
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                Editar
                            </button>

                            <?php if (!$inactiva && !$tieneDocente): ?>
                                <button type="button" class="btn btn-sm btn-dorado"
                                        onclick='asignar(<?= json_encode([
                                            "id"        => (int)$m["id"],
                                            "nombre"    => $m["nombre"],
                                            "semestre"  => $m["semestre"],
                                            "carrera"   => $m["carrera"] ?? "esta carrera",
                                            "ambientes" => Carrera::ambientesDe($m["carrera_id"] ? (int)$m["carrera_id"] : null)
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                    + Asignar docente
                                </button>
                            <?php elseif (!$inactiva): ?>
                                <button type="button" class="btn btn-sm btn-outline"
                                        onclick='asignar(<?= json_encode([
                                            "id"        => (int)$m["id"],
                                            "nombre"    => $m["nombre"],
                                            "semestre"  => $m["semestre"],
                                            "carrera"   => $m["carrera"] ?? "esta carrera",
                                            "ambientes" => Carrera::ambientesDe($m["carrera_id"] ? (int)$m["carrera_id"] : null)
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'
                                        title="El mismo docente puede tenerla en otro ambiente">
                                    + Otro ambiente
                                </button>
                            <?php endif; ?>

                            <form action="<?= $base ?>/admin/materias/estado" method="POST" class="inline"
                                  data-confirmar="<?= $inactiva ? '¿Reactivar esta materia?' : '¿Archivar esta materia? Dejará de aparecer al crear asignaciones, pero su historial se conserva.' ?>">
                                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="activa" value="<?= $inactiva ? 1 : 0 ?>">
                                <button type="submit" class="btn btn-sm <?= $inactiva ? 'btn-outline' : 'btn-peligro-suave' ?>">
                                    <?= $inactiva ? 'Reactivar' : 'Archivar' ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php if (!$tieneDocente): ?>
                        <p class="text-muted" style="font-size:.87rem">
                            Sin docente asignado todavía.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Docente</th>
                                        <th>Ambiente</th>
                                        <th>Matriculados</th>
                                        <th class="text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($m['cursos'] as $c): ?>
                                        <tr>
                                            <td class="font-medium">
                                                <?= htmlspecialchars(trim($c['docente_nombre'] . ' ' . $c['docente_apellido'])) ?>
                                            </td>
                                            <td>
                                                <span class="curso-ambiente amb-<?= strtolower(str_replace(' ', '-', $c['ambiente'])) ?>">
                                                    <?= htmlspecialchars($c['ambiente']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= (int)$c['total_matriculados'] > 0 ? 'badge-success' : 'badge-neutral' ?>">
                                                    <?= (int)$c['total_matriculados'] ?> alumno(s)
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <form action="<?= $base ?>/admin/materias/quitar-docente" method="POST" class="inline"
                                                      data-confirmar="¿Retirar esta asignación? No se borra nada: las clases dictadas se conservan y podrás restaurarla desde &quot;Asignaciones retiradas&quot;.">
                                                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                                    <input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-peligro-suave">Retirar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($m['archivados'])): ?>
                        <details class="archivados">
                            <summary class="archivados-titulo">
                                Asignaciones retiradas (<?= count($m['archivados']) ?>)
                            </summary>
                            <p class="text-muted mb-3" style="font-size:.83rem">
                                No se borraron: las clases que ese docente ya dictó siguen en los reportes.
                            </p>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Docente</th><th>Ambiente</th><th class="text-right">Acción</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($m['archivados'] as $c): ?>
                                            <tr>
                                                <td class="text-muted"><?= htmlspecialchars(trim($c['docente_nombre'] . ' ' . $c['docente_apellido'])) ?></td>
                                                <td class="text-muted"><?= htmlspecialchars($c['ambiente']) ?></td>
                                                <td class="text-right">
                                                    <form action="<?= $base ?>/admin/materias/restaurar-docente" method="POST" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                                        <input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline">Restaurar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ================== MODAL: crear / editar materia ================== -->
<div id="modalMateria" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0" id="tituloModalMateria">Nueva Materia</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalMateria')" aria-label="Cerrar">&times;</button>
        </div>

        <form action="<?= $base ?>/admin/materias/crear" method="POST" id="formMateria">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <input type="hidden" name="id" id="materiaId">

            <div class="form-group">
                <label for="materiaNombre" class="form-label">Nombre de la materia <span class="text-danger">*</span></label>
                <input type="text" id="materiaNombre" name="nombre" class="form-control"
                       required maxlength="120" placeholder="Ej: Programación de Aplicaciones"
                       oninput="proponerCodigo()">
            </div>

            <div class="form-fila">
                <div class="form-group">
                    <label for="materiaSemestre" class="form-label">Semestre <span class="text-danger">*</span></label>
                    <select id="materiaSemestre" name="semestre" class="form-select" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($semestres as $s): ?>
                            <?php if ((int)$s['activo'] === 1): ?>
                                <option value="<?= htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="materiaCarrera" class="form-label">Carrera <span class="text-danger">*</span></label>
                    <select id="materiaCarrera" name="carrera_id" class="form-select" required>
                        <?php if (count($carrerasActivas) !== 1): ?>
                            <option value="">-- Selecciona --</option>
                        <?php endif; ?>
                        <?php foreach ($carrerasActivas as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($carrerasActivas)): ?>
                        <small class="form-ayuda text-danger">
                            No hay carreras activas. Créala primero en la pantalla de períodos.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="materiaPeriodo" class="form-label">Período académico <span class="text-danger">*</span></label>
                <select id="materiaPeriodo" name="periodo_id" class="form-select" required>
                    <?php foreach ($periodosAbiertos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"
                                <?= (int)$p['id'] === (int)($periodo['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                            (<?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?>
                            al <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-ayuda" id="ayudaPeriodo">
                    Viene marcado el período en el que estás trabajando. Si eliges otro,
                    la pantalla se moverá a ese período para que veas la materia recién creada.
                </small>
                <small class="form-ayuda" id="avisoPeriodoFijo" hidden>
                    Esta materia ya tiene docente asignado, así que no puede cambiar de
                    período: se llevaría consigo las clases ya dictadas. Para el ciclo
                    siguiente, copia la malla desde la pantalla de períodos.
                </small>
                <?php if (empty($periodosAbiertos)): ?>
                    <small class="form-ayuda text-danger">
                        No hay ningún período abierto. Abre uno en la pantalla de períodos.
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="materiaCodigo" class="form-label">Código corto</label>
                <input type="text" id="materiaCodigo" name="codigo" class="form-control form-control-code"
                       maxlength="20" placeholder="Se propone solo"
                       oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g,''); this.dataset.tocado='1'">
                <small class="form-ayuda">
                    Se genera solo a partir del nombre. Puedes cambiarlo: es lo que aparece en los reportes.
                </small>
            </div>

            <div class="form-group mb-6" id="grupoEstadoMateria" hidden>
                <label for="materiaActiva" class="form-label">Estado</label>
                <select id="materiaActiva" name="activa" class="form-select">
                    <option value="1">Activa</option>
                    <option value="0">Archivada</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalMateria')">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="botonMateria">Crear Materia</button>
            </div>
        </form>
    </div>
</div>

<!-- ================== MODAL: asignar docente ================== -->
<div id="modalAsignar" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Asignar Docente</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalAsignar')" aria-label="Cerrar">&times;</button>
        </div>

        <p class="text-muted mb-4" style="font-size:.87rem">
            Materia: <strong id="asignarMateriaNombre" class="text-primary"></strong><br>
            Semestre: <strong id="asignarMateriaSemestre"></strong>
            <span class="text-muted">(viene de la materia, no se elige aquí)</span>
        </p>

        <form action="<?= $base ?>/admin/materias/asignar" method="POST" id="formAsignar">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <input type="hidden" name="materia_id" id="asignarMateriaId">

            <div class="form-group">
                <label for="asignarDocente" class="form-label">Docente <span class="text-danger">*</span></label>
                <select id="asignarDocente" name="docente_id" class="form-select" required>
                    <option value="">-- Selecciona el docente --</option>
                    <?php foreach ($docentes as $d): ?>
                        <option value="<?= (int)$d['id'] ?>">
                            <?= htmlspecialchars(trim($d['apellido'] . ' ' . $d['nombre'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($docentes)): ?>
                    <small class="form-ayuda text-danger">
                        No hay docentes activos. Crea una cuenta primero en la sección Cuentas.
                    </small>
                <?php endif; ?>
            </div>

            <div class="form-group mb-6">
                <label for="asignarAmbiente" class="form-label">Ambiente <span class="text-danger">*</span></label>
                <!--
                    Las opciones las pone el JavaScript segun la CARRERA de la
                    materia: Mecanica trabaja en el taller y las demas no, asi
                    que una lista fija para todas seria una opcion mas para
                    equivocarse.
                -->
                <select id="asignarAmbiente" name="ambiente" class="form-select" required></select>
                <small class="form-ayuda">
                    Solo aparecen los ambientes que usa <strong id="asignarCarreraNombre"></strong>.
                    El mismo docente puede repetir la materia en otro ambiente: la teoría
                    en el Aula y la práctica en el Taller son dos cursos.
                </small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalAsignar')">Cancelar</button>
                <button type="submit" class="btn btn-dorado">Asignar Docente</button>
            </div>
        </form>
    </div>
</div>

<!-- ================== MODAL: semestres ================== -->
<div id="modalSemestres" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Semestres</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalSemestres')" aria-label="Cerrar">&times;</button>
        </div>

        <p class="text-muted mb-4" style="font-size:.87rem">
            Los semestres son comunes a todos los períodos. Renombrar uno arrastra
            el cambio a los estudiantes y a los cursos que ya lo usan.
        </p>

        <div class="table-responsive mb-4">
            <table class="table">
                <thead>
                    <tr><th>Orden</th><th>Semestre</th><th>Estado</th><th class="text-right">Acción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($semestres as $s): ?>
                        <tr>
                            <td class="text-muted"><?= (int)$s['orden'] ?></td>
                            <td class="font-medium"><?= htmlspecialchars($s['nombre']) ?></td>
                            <td>
                                <span class="badge <?= (int)$s['activo'] === 1 ? 'badge-success' : 'badge-neutral' ?>">
                                    <?= (int)$s['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <form action="<?= $base ?>/admin/semestres/eliminar" method="POST" class="inline"
                                      data-confirmar="¿Eliminar este semestre? Solo se puede si ningún estudiante ni curso lo usa.">
                                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-peligro-suave">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form action="<?= $base ?>/admin/semestres/crear" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <div class="form-fila">
                <div class="form-group">
                    <label for="semestreNombre" class="form-label">Nuevo semestre</label>
                    <input type="text" id="semestreNombre" name="nombre" class="form-control"
                           required maxlength="40" placeholder="Quinto Semestre">
                </div>
                <div class="form-group">
                    <label for="semestreOrden" class="form-label">Orden</label>
                    <input type="number" id="semestreOrden" name="orden" class="form-control"
                           min="1" max="99" value="<?= count($semestres) + 1 ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Agregar Semestre</button>
        </form>
    </div>
</div>

<script>
function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.style.display = 'none'; });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
});

const BASE = document.body.dataset.base || '';
const PERIODO_ACTUAL = '<?= (int)($periodo['id'] ?? 0) ?>';

/** Propone el código corto mientras se escribe el nombre, sin pisar lo que el admin escriba */
function proponerCodigo() {
    const campoCodigo = document.getElementById('materiaCodigo');
    if (campoCodigo.dataset.tocado === '1') return;

    const nombre = document.getElementById('materiaNombre').value;
    const ignorar = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'a', 'en'];

    const palabras = nombre.normalize('NFD').replace(/[̀-ͯ]/g, '')
        .split(/\s+/)
        .map(p => p.replace(/[^A-Za-z0-9]/g, ''))
        .filter(p => p && !ignorar.includes(p.toLowerCase()))
        .map(p => p.toUpperCase());

    if (!palabras.length) { campoCodigo.value = ''; return; }

    campoCodigo.value = (palabras.length === 1)
        ? palabras[0].slice(0, 6)
        : palabras.slice(0, 3).map(p => p.slice(0, 3)).join('');
}

/** Modo "crear" */
function nuevaMateria() {
    const f = document.getElementById('formMateria');
    f.action = BASE + '/admin/materias/crear';
    f.reset();
    document.getElementById('materiaId').value = '';
    document.getElementById('materiaCodigo').dataset.tocado = '';
    document.getElementById('materiaPeriodo').value = PERIODO_ACTUAL;
    document.getElementById('materiaPeriodo').disabled = false;
    document.getElementById('ayudaPeriodo').hidden = false;
    document.getElementById('avisoPeriodoFijo').hidden = true;
    document.getElementById('tituloModalMateria').textContent = 'Nueva Materia';
    document.getElementById('botonMateria').textContent = 'Crear Materia';
    document.getElementById('grupoEstadoMateria').hidden = true;
    abrirModal('modalMateria');
    document.getElementById('materiaNombre').focus();
}

/**
 * Modo "editar": el mismo formulario apunta a otra ruta.
 *
 * Recibe un objeto y no seis parámetros sueltos porque los nombres de materia
 * llevan tildes, comillas y apóstrofos, y armarlos a mano dentro del onclick
 * es como se rompía el atributo con nombres tipo "Ética y Deontología".
 */
function editarMateria(m) {
    const f = document.getElementById('formMateria');
    f.action = BASE + '/admin/materias/actualizar';
    document.getElementById('materiaId').value = m.id;
    document.getElementById('materiaNombre').value = m.nombre;
    document.getElementById('materiaSemestre').value = m.semestre;
    document.getElementById('materiaCarrera').value = m.carrera || '';
    document.getElementById('materiaCodigo').value = m.codigo;
    document.getElementById('materiaCodigo').dataset.tocado = '1';
    document.getElementById('materiaActiva').value = m.activa ? '1' : '0';

    /*
     * El período solo se puede cambiar mientras la materia esté vacía.
     *
     * En cuanto tiene un docente asignado, arrastra consigo las clases ya
     * dictadas y sus asistencias: moverla de ciclo descuadraría los dos a la
     * vez, el que deja y el que recibe. Para el ciclo siguiente se copia la
     * malla desde la pantalla de períodos, que crea materias nuevas y deja el
     * historial anterior donde está.
     */
    const campoPeriodo = document.getElementById('materiaPeriodo');
    campoPeriodo.value = m.periodo;
    campoPeriodo.disabled = !m.movible;
    document.getElementById('ayudaPeriodo').hidden = !m.movible;
    document.getElementById('avisoPeriodoFijo').hidden = m.movible;

    document.getElementById('tituloModalMateria').textContent = 'Editar Materia';
    document.getElementById('botonMateria').textContent = 'Guardar Cambios';
    document.getElementById('grupoEstadoMateria').hidden = false;
    abrirModal('modalMateria');
}

/**
 * Abre el modal de asignación con la materia ya resuelta.
 *
 * Los ambientes se rehacen en cada apertura porque dependen de la CARRERA de
 * la materia: el taller solo aparece para Mecánica. El servidor lo vuelve a
 * comprobar, porque una lista armada en el navegador se edita en dos clics.
 */
function asignar(m) {
    const f = document.getElementById('formAsignar');
    f.reset();
    document.getElementById('asignarMateriaId').value = m.id;
    document.getElementById('asignarMateriaNombre').textContent = m.nombre;
    document.getElementById('asignarMateriaSemestre').textContent = m.semestre;
    document.getElementById('asignarCarreraNombre').textContent = m.carrera;

    const select = document.getElementById('asignarAmbiente');
    select.innerHTML = '';
    (m.ambientes || []).forEach(a => {
        const opcion = document.createElement('option');
        opcion.value = a;
        opcion.textContent = a;
        select.appendChild(opcion);
    });

    abrirModal('modalAsignar');
    document.getElementById('asignarDocente').focus();
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
