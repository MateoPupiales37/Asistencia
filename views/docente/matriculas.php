<?php
/**
 * Estudiantes de cada curso (matrículas).
 *
 * El administrador asigna las materias al docente; aquí el docente decide qué
 * estudiantes pertenecen a cada una. Esa lista es la que después permite
 * comprobar una asistencia: quien escanea y no está matriculado queda como
 * pendiente para que el docente lo apruebe.
 */

$titulo = 'Estudiantes del Curso - ISTPET';
$vista  = 'docente-matriculas';
require dirname(__DIR__) . '/layouts/header.php';

$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
?>

<nav class="breadcrumb">
    <a href="<?= $base ?>/docente">Mi Clase</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Estudiantes</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">Estudiantes del Curso</h1>
        <p class="page-subtitle">
            La lista de clase: quién pertenece a cada materia que dictas
        </p>
    </div>
    <div class="acciones-cabecera">
        <a href="<?= $base ?>/docente" class="btn btn-back">&larr; Mi Clase</a>
        <?php if ($cursoId): ?>
            <a href="<?= $base ?>/docente/envios?curso_id=<?= (int)$cursoId ?>" class="btn btn-success">
                Enviar credenciales
            </a>
            <button type="button" class="btn btn-outline" onclick="abrirModal('modalImportar')">Importar Excel</button>
            <button type="button" class="btn btn-primary" onclick="abrirModal('modalInscribir')">+ Inscribir estudiante</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<?php if (!empty($resumenImport['problemas'])): ?>
    <div class="alert alert-warning">
        <span>
            <strong>Filas que no se pudieron cargar:</strong>
            <ul class="lista-problemas">
                <?php foreach ($resumenImport['problemas'] as $problema): ?>
                    <li><?= htmlspecialchars($problema) ?></li>
                <?php endforeach; ?>
            </ul>
        </span>
    </div>
<?php endif; ?>

<?php
// Sin telefono no se le puede enviar el codigo al alumno, asi que se avisa
$sinTelefono = 0;
foreach ($matriculados as $m) {
    if (empty($m['telefono'])) { $sinTelefono++; }
}
?>

<?php if ($sinTelefono > 0): ?>
    <div class="alert alert-warning">
        <span>
            Hay <strong><?= $sinTelefono ?></strong> estudiante(s) sin teléfono.
            A esos no se les puede enviar su código: edítalos y agrégales el número.
        </span>
    </div>
<?php endif; ?>

<?php if (empty($cursos)): ?>
    <div class="card">
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">Todavía no tienes materias asignadas</p>
            <p class="text-muted">
                El administrador es quien te asigna las materias. Pídele que te
                asigne una desde su panel y aquí podrás matricular a tus estudiantes.
            </p>
        </div>
    </div>
<?php else: ?>

    <!-- Selector de curso -->
    <div class="card card-filter mb-6">
        <form method="GET" action="<?= $base ?>/docente/matriculas" class="filtros-grid">
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
                <label for="filtro" class="form-label">Mostrar</label>
                <select id="filtro" name="filtro" class="form-select" onchange="this.form.requestSubmit()">
                    <option value="">Todos (<?= (int)$totalCurso ?>)</option>
                    <option value="nuevos" <?= $filtro === 'nuevos' ? 'selected' : '' ?>>
                        Recién agregados (<?= (int)$totalNuevos ?>)
                    </option>
                    <option value="sin_telefono" <?= $filtro === 'sin_telefono' ? 'selected' : '' ?>>
                        Sin teléfono
                    </option>
                    <option value="con_telefono" <?= $filtro === 'con_telefono' ? 'selected' : '' ?>>
                        Con teléfono
                    </option>
                </select>
            </div>
            <div class="form-group mb-0 alinear-abajo">
                <span class="badge badge-neutral"><?= count($matriculados) ?> en pantalla</span>
            </div>
        </form>
    </div>

    <div data-region="matriculas">
        <!-- Matricular a alguien que ya está en el padrón -->
        <?php if (!empty($candidatos)): ?>
            <div class="card mb-6">
                <h2 class="card-titulo">Agregar del padrón</h2>
                <p class="text-muted mb-4" style="font-size:.87rem">
                    Estudiantes que ya existen en el sistema pero aún no están en este curso.
                    Marca los que correspondan.
                </p>

                <form action="<?= $base ?>/docente/matriculas/agregar" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                    <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">

                    <div class="lista-candidatos">
                        <?php foreach ($candidatos as $e): ?>
                            <label class="candidato">
                                <input type="checkbox" name="estudiante_id[]" value="<?= (int)$e['id'] ?>">
                                <span>
                                    <strong><?= htmlspecialchars(trim($e['apellido'] . ' ' . $e['nombre'])) ?></strong>
                                    <small>
                                        <?= htmlspecialchars($e['codigo']) ?>
                                        <?= !empty($e['cedula']) ? ' · ' . htmlspecialchars($e['cedula']) : '' ?>
                                        · <?= htmlspecialchars($e['semestre']) ?>
                                    </small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-4">
                        <button type="button" class="btn btn-outline btn-sm" onclick="marcarTodos(true)">Marcar todos</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="marcarTodos(false)">Desmarcar</button>
                        <button type="submit" class="btn btn-primary">Matricular seleccionados</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Lista de matriculados -->
        <div class="card">
            <div class="card-header-flex">
                <h2 class="card-titulo mb-0">
                    Matriculados<?= $curso ? ' en ' . htmlspecialchars($curso['materia']) : '' ?>
                </h2>
                <span class="badge badge-neutral"><?= count($matriculados) ?></span>
            </div>

            <?php if (empty($matriculados)): ?>
                <div class="estado-vacio">
                    <p class="estado-vacio-titulo">Este curso todavía no tiene estudiantes</p>
                    <p class="text-muted">
                        Inscríbelos uno a uno, agrégalos del padrón, o sube la lista
                        completa desde un Excel.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Código</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Semestre</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matriculados as $e): ?>
                                <tr class="<?= empty($e['telefono']) ? 'fila-pendiente' : '' ?>">
                                    <td class="font-medium"><?= htmlspecialchars(trim($e['apellido'] . ' ' . $e['nombre'])) ?></td>
                                    <td class="table-code"><?= htmlspecialchars($e['codigo']) ?></td>
                                    <td class="table-code">
                                        <?= !empty($e['cedula']) ? htmlspecialchars($e['cedula']) : '<span class="text-danger">falta</span>' ?>
                                    </td>
                                    <td class="text-muted">
                                        <?= !empty($e['telefono'])
                                            ? htmlspecialchars($e['telefono'])
                                            : '<span class="text-danger">falta</span>' ?>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($e['semestre']) ?></td>
                                    <td class="text-right">
                                        <div class="acciones-fila">
                                        <button type="button" class="btn btn-sm btn-outline"
                                                onclick='editarEstudiante(<?= json_encode([
                                                    "id"       => (int)$e["id"],
                                                    "nombre"   => $e["nombre"],
                                                    "apellido" => $e["apellido"],
                                                    "cedula"   => (string)($e["cedula"] ?? ""),
                                                    "telefono" => (string)($e["telefono"] ?? ""),
                                                    "semestre" => $e["semestre"],
                                                    "codigo"   => $e["codigo"],
                                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            Editar
                                        </button>
                                        <form action="<?= $base ?>/docente/matriculas/quitar" method="POST" class="inline"
                                              data-confirmar="¿Retirar a <?= htmlspecialchars(trim($e['nombre'] . ' ' . $e['apellido']), ENT_QUOTES, 'UTF-8') ?> de este curso? No se borra al estudiante ni su historial de asistencias.">
                                            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                            <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">
                                            <input type="hidden" name="estudiante_id" value="<?= (int)$e['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-peligro-suave">Retirar</button>
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

    <!-- ============ MODAL: inscribir estudiante nuevo ============ -->
    <div id="modalInscribir" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header-row">
                <h3 class="modal-title mb-0">Inscribir Estudiante</h3>
                <button type="button" class="modal-close-btn" onclick="cerrarModal('modalInscribir')" aria-label="Cerrar">&times;</button>
            </div>

            <p class="text-muted mb-4" style="font-size:.87rem">
                Para el alumno que no está en el sistema. Se crea su ficha y queda
                matriculado en este curso de una vez.
            </p>

            <form action="<?= $base ?>/docente/matriculas/inscribir" method="POST" id="formInscribir">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">

                <div class="form-fila">
                    <div class="form-group">
                        <label for="i_nombre" class="form-label">Nombres <span class="text-danger">*</span></label>
                        <input type="text" id="i_nombre" name="nombre" class="form-control" required maxlength="50" placeholder="Saul">
                    </div>
                    <div class="form-group">
                        <label for="i_apellido" class="form-label">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" id="i_apellido" name="apellido" class="form-control" required maxlength="50" placeholder="Andrade">
                    </div>
                </div>

                <div class="form-group">
                    <label for="i_cedula" class="form-label">Cédula <span class="text-danger">*</span></label>
                    <input type="text" id="i_cedula" name="cedula" class="form-control form-control-code"
                           required inputmode="numeric" maxlength="10" placeholder="1701234567"
                           oninput="this.value = this.value.replace(/\D/g,'')">
                    <small class="form-ayuda">
                        Obligatoria: es lo que le permite registrarse aunque olvide su código o pierda el carnet.
                    </small>
                </div>

                <div class="form-fila mb-6">
                    <div class="form-group">
                        <label for="i_semestre" class="form-label">Semestre <span class="text-danger">*</span></label>
                        <select id="i_semestre" name="semestre" class="form-select" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($semestres as $s): ?>
                                <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="i_telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                        <input type="tel" id="i_telefono" name="telefono" class="form-control"
                               required inputmode="numeric" maxlength="13" placeholder="0991112233"
                               oninput="this.value = this.value.replace(/[^\d]/g,'')">
                        <small class="form-ayuda">10 dígitos. Sin número no se le puede enviar su código.</small>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalInscribir')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Inscribir y Matricular</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============ MODAL: editar estudiante ============ -->
    <div id="modalEditar" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header-row">
                <h3 class="modal-title mb-0">Editar Estudiante</h3>
                <button type="button" class="modal-close-btn" onclick="cerrarModal('modalEditar')" aria-label="Cerrar">&times;</button>
            </div>

            <p class="text-muted mb-4" style="font-size:.87rem">
                Código <strong id="e_codigo" class="text-primary"></strong> &mdash;
                no cambia, es su identidad y la de su carnet impreso.
            </p>

            <form action="<?= $base ?>/docente/matriculas/editar" method="POST" id="formEditar">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">
                <input type="hidden" name="estudiante_id" id="e_id">

                <div class="form-fila">
                    <div class="form-group">
                        <label for="e_nombre" class="form-label">Nombres <span class="text-danger">*</span></label>
                        <input type="text" id="e_nombre" name="nombre" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="e_apellido" class="form-label">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" id="e_apellido" name="apellido" class="form-control" required maxlength="50">
                    </div>
                </div>

                <div class="form-group">
                    <label for="e_cedula" class="form-label">Cédula <span class="text-danger">*</span></label>
                    <input type="text" id="e_cedula" name="cedula" class="form-control form-control-code"
                           required inputmode="numeric" maxlength="10"
                           oninput="this.value = this.value.replace(/\D/g,'')">
                </div>

                <div class="form-fila mb-6">
                    <div class="form-group">
                        <label for="e_semestre" class="form-label">Semestre <span class="text-danger">*</span></label>
                        <select id="e_semestre" name="semestre" class="form-select" required>
                            <?php foreach ($semestres as $sem): ?>
                                <option value="<?= htmlspecialchars($sem, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sem) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="e_telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                        <input type="tel" id="e_telefono" name="telefono" class="form-control"
                               required inputmode="numeric" maxlength="13" placeholder="0991112233"
                               oninput="this.value = this.value.replace(/[^\d]/g,'')">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditar')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============ MODAL: importar Excel ============ -->
    <div id="modalImportar" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header-row">
                <h3 class="modal-title mb-0">Importar desde Excel</h3>
                <button type="button" class="modal-close-btn" onclick="cerrarModal('modalImportar')" aria-label="Cerrar">&times;</button>
            </div>

            <p class="text-muted mb-3" style="font-size:.87rem">
                El archivo debe tener estas cinco columnas, en este orden:
            </p>

            <div class="table-responsive mb-3">
                <table class="table" style="min-width:0">
                    <thead>
                        <tr><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>cédula</td><td>nombres</td><td>apellidos</td><td>semestre</td><td>teléfono</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="form-ayuda mb-4">
                <strong>Las cinco columnas son obligatorias.</strong> Una fila sin
                teléfono se rechaza y se te indica cuál: sin número no hay forma de
                enviarle su código al alumno.<br>
                La primera fila puede ser el título de las columnas: se detecta y se salta.
                El semestre acepta "Tercer Semestre", "tercero" o simplemente "3".
                Si una cédula ya existe, no se duplica: solo se matricula.
            </p>

            <a href="<?= $base ?>/docente/matriculas/plantilla" class="btn btn-outline btn-block mb-4">
                Descargar plantilla de ejemplo
            </a>

            <form action="<?= $base ?>/docente/matriculas/importar" method="POST"
                  enctype="multipart/form-data" data-sin-ajax>
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">

                <div class="form-group mb-6">
                    <label for="archivo" class="form-label">Archivo <span class="text-danger">*</span></label>
                    <input type="file" id="archivo" name="archivo" class="form-control"
                           accept=".xlsx,.csv" required>
                    <small class="form-ayuda">Excel (.xlsx) o CSV. Máximo 3 MB.</small>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalImportar')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Importar y Matricular</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.style.display = 'none'; });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
});

function marcarTodos(estado) {
    document.querySelectorAll('.lista-candidatos input[type=checkbox]')
        .forEach(c => { c.checked = estado; });
}

// La cédula es obligatoria al inscribir: se valida antes de enviar para no
// hacerle dar el viaje al servidor por un dígito mal escrito
function editarEstudiante(datos) {
    document.getElementById('e_id').value       = datos.id;
    document.getElementById('e_nombre').value   = datos.nombre;
    document.getElementById('e_apellido').value = datos.apellido;
    document.getElementById('e_cedula').value   = datos.cedula;
    document.getElementById('e_telefono').value = datos.telefono;
    document.getElementById('e_semestre').value = datos.semestre;
    document.getElementById('e_codigo').textContent = datos.codigo || '';
    abrirModal('modalEditar');
    document.getElementById('e_telefono').focus();
}

document.getElementById('formEditar')?.addEventListener('submit', function (e) {
    const ced = document.getElementById('e_cedula').value.trim();
    if (!window.cedulaValida || !window.cedulaValida(ced)) {
        e.preventDefault();
        (window.avisar || alert)('La cédula no es válida. Revisa los 10 dígitos.', 'error');
        document.getElementById('e_cedula').focus();
        return;
    }
    const tel = document.getElementById('e_telefono').value.replace(/\D/g, '');
    if (!/^0\d{9}$/.test(tel)) {
        e.preventDefault();
        (window.avisar || alert)('El teléfono debe tener 10 dígitos y empezar con 0.', 'error');
        document.getElementById('e_telefono').focus();
    }
});

document.getElementById('formInscribir')?.addEventListener('submit', function (e) {
    const ced = document.getElementById('i_cedula').value.trim();
    if (!window.cedulaValida || !window.cedulaValida(ced)) {
        e.preventDefault();
        (window.avisar || alert)('La cédula no es válida. Revisa los 10 dígitos.', 'error');
        document.getElementById('i_cedula').focus();
        return;
    }

    const tel = document.getElementById('i_telefono').value.replace(/\D/g, '');
    if (!/^0\d{9}$/.test(tel)) {
        e.preventDefault();
        (window.avisar || alert)('El teléfono debe tener 10 dígitos y empezar con 0.', 'error');
        document.getElementById('i_telefono').focus();
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
