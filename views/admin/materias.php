<?php
/**
 * Gestion academica del administrador.
 *
 * Aqui el administrador hace las dos cosas que antes no se podian hacer desde
 * el sistema y solo existian escritas a mano en el script SQL:
 *
 *   1. Crear materias del catalogo institucional.
 *   2. Asignar QUE DOCENTE dicta cada materia, en que ambiente, semestre y
 *      semestre. Esa asignacion es lo que crea el curso.
 *
 * A partir de ahi, el docente se encarga de matricular a sus estudiantes en
 * los cursos que le asignaron. El administrador no matricula alumnos.
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
        <button type="button" class="btn btn-primary" onclick="abrirModal('modalMateria')">+ Nueva Materia</button>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<div class="alert alert-info">
    <span>
        <strong>Cómo funciona:</strong> tú creas la materia y le asignas un docente.
        Esa asignación genera el curso. Después, cada docente entra a su panel y
        matricula ahí a sus propios estudiantes.
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
                <p class="estado-vacio-titulo">Todavía no hay materias</p>
                <p class="text-muted">
                    Crea la primera con el botón "Nueva Materia". Después podrás
                    asignarle uno o varios docentes.
                </p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($materias as $m): ?>
            <?php $inactiva = ((int)$m['activa'] === 0); ?>
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
                            <?= (int)$m['total_cursos'] ?> asignación(es) &bull;
                            <?= (int)$m['total_docentes'] ?> docente(s)
                        </p>
                    </div>

                    <div class="acciones-fila">
                        <button type="button" class="btn btn-sm btn-outline"
                                onclick="editarMateria(<?= (int)$m['id'] ?>, '<?= htmlspecialchars(addslashes($m['codigo']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($m['nombre']), ENT_QUOTES, 'UTF-8') ?>', <?= (int)$m['activa'] ?>)">
                            Editar
                        </button>

                        <?php if (!$inactiva): ?>
                            <div class="asignar-rapido">
                                <button type="button" class="btn btn-sm btn-dorado"
                                        onclick="asignar(<?= (int)$m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nombre']), ENT_QUOTES, 'UTF-8') ?>')">
                                    + Asignar docente
                                </button>
                                <?php foreach ($semestres as $sem): ?>
                                    <?php if ((int)$sem['activo'] === 1): ?>
                                        <button type="button" class="chip-btn"
                                                title="Asignar un docente a esta materia en <?= htmlspecialchars($sem['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="asignar(<?= (int)$m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nombre']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($sem['nombre']), ENT_QUOTES, 'UTF-8') ?>')">
                                            <?= htmlspecialchars(explode(' ', $sem['nombre'])[0]) ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
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

                <?php if (empty($m['cursos'])): ?>
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
                                    <th>Semestre</th>
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
                                        <td class="text-muted"><?= htmlspecialchars($c['semestre']) ?></td>
                                        <td class="text-muted"></td>
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
                                    <tr><th>Docente</th><th>Ambiente</th><th>Semestre</th><th class="text-right">Acción</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($m['archivados'] as $c): ?>
                                        <tr>
                                            <td class="text-muted"><?= htmlspecialchars(trim($c['docente_nombre'] . ' ' . $c['docente_apellido'])) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($c['ambiente']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($c['semestre']) ?></td>
                                            <td class="text-muted"></td>
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
            Materia: <strong id="asignarMateriaNombre" class="text-primary"></strong>
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

            <div class="form-group">
                <label for="asignarAmbiente" class="form-label">Ambiente <span class="text-danger">*</span></label>
                <select id="asignarAmbiente" name="ambiente" class="form-select" required>
                    <?php foreach ($ambientes as $a): ?>
                        <option value="<?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-6">
                <label for="asignarSemestre" class="form-label">Semestre <span class="text-danger">*</span></label>
                <select id="asignarSemestre" name="semestre" class="form-select" required>
                    <option value="">-- Selecciona el semestre --</option>
                    <?php foreach ($semestres as $s): ?>
                        <?php if ((int)$s['activo'] === 1): ?>
                            <option value="<?= htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($s['nombre']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <small class="form-ayuda">
                    La misma materia puede asignarse varias veces: distinto ambiente o semestre es un curso aparte.
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

        <p class="text-muted mb-4" style="font-size:.86rem">
            Si renombras un semestre, el cambio se aplica también a los estudiantes
            y cursos que ya lo usaban.
        </p>

        <?php
        /* Esta lista vive dentro del modal, que queda FUERA de la region
           "materias". Sin marcarla como region propia, la capa AJAX no la
           refrescaba: al borrar un semestre la fila seguia en pantalla y, al
           pulsar Eliminar otra vez sobre ella, el servidor respondia "Ese
           semestre ya no existe". */
        ?>
        <div class="table-responsive mb-4" data-region="lista-semestres">
            <table class="table">
                <thead>
                    <tr><th>Orden</th><th>Nombre</th><th>Estado</th><th class="text-right">Acción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($semestres as $s): ?>
                        <tr>
                            <td class="text-muted"><?= (int)$s['orden'] ?></td>
                            <td class="font-medium"><?= htmlspecialchars($s['nombre']) ?></td>
                            <td>
                                <span class="badge <?= (int)$s['activo'] === 1 ? 'badge-success' : 'badge-neutral' ?>">
                                    <?= (int)$s['activo'] === 1 ? 'ACTIVO' : 'INACTIVO' ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <form action="<?= $base ?>/admin/semestres/eliminar" method="POST" class="inline"
                                      data-confirmar="¿Eliminar el semestre &quot;<?= htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8') ?>&quot;? Solo se puede si no hay estudiantes ni cursos usándolo.">
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
                           maxlength="40" placeholder="Ej: Quinto Semestre" required>
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

/** Propone el codigo corto mientras se escribe el nombre, sin pisar lo que el admin escriba */
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
    document.getElementById('tituloModalMateria').textContent = 'Nueva Materia';
    document.getElementById('botonMateria').textContent = 'Crear Materia';
    document.getElementById('grupoEstadoMateria').hidden = true;
    abrirModal('modalMateria');
    document.getElementById('materiaNombre').focus();
}

/** Modo "editar": el mismo formulario apunta a otra ruta */
function editarMateria(id, codigo, nombre, activa) {
    const f = document.getElementById('formMateria');
    f.action = BASE + '/admin/materias/actualizar';
    document.getElementById('materiaId').value = id;
    document.getElementById('materiaNombre').value = nombre;
    document.getElementById('materiaCodigo').value = codigo;
    document.getElementById('materiaCodigo').dataset.tocado = '1';
    document.getElementById('materiaActiva').value = activa ? '1' : '0';
    document.getElementById('tituloModalMateria').textContent = 'Editar Materia';
    document.getElementById('botonMateria').textContent = 'Guardar Cambios';
    document.getElementById('grupoEstadoMateria').hidden = false;
    abrirModal('modalMateria');
}

/**
 * Abre el modal de asignacion. Si se indica un semestre, llega ya elegido:
 * asi el administrador que quiere "esta materia en Tercero" lo hace de un
 * clic en vez de abrir el modal y volver a buscarlo en la lista.
 */
function asignar(materiaId, nombre, semestre) {
    const f = document.getElementById('formAsignar');
    f.reset();
    document.getElementById('asignarMateriaId').value = materiaId;
    document.getElementById('asignarMateriaNombre').textContent = nombre;

    if (semestre) {
        document.getElementById('asignarSemestre').value = semestre;
    }

    abrirModal('modalAsignar');
    document.getElementById('asignarDocente').focus();
}

// El boton "+ Nueva Materia" de la cabecera siempre abre en modo crear
document.querySelector('.acciones-cabecera .btn-primary')
    ?.addEventListener('click', nuevaMateria);
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
