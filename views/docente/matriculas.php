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
                    Estudiantes de <strong><?= htmlspecialchars($carreraCurso ?? 'esta carrera') ?></strong>
                    que ya existen en el sistema pero aún no están en este curso.
                    Marca los que correspondan.
                </p>

                <?php
                /* Filtro por semestre: acota el padron antes de mostrarlo.
                   Se envia por GET, asi que conserva el curso seleccionado. */
                ?>
                <form method="GET" action="<?= $base ?>/docente/matriculas" class="filtro-semestre">
                    <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">
                    <?php if (!empty($filtro)): ?>
                        <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>

                    <label for="semestre_padron" class="form-label mb-0">Ver semestre:</label>
                    <select id="semestre_padron" name="semestre" class="form-select"
                            onchange="this.form.requestSubmit()">
                        <option value="">Todos los semestres</option>
                        <?php foreach ($semestres as $s): ?>
                            <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $semestreFiltro === $s ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-muted"><?= count($candidatos) ?> disponible(s)</span>
                </form>

                <form action="<?= $base ?>/docente/matriculas/agregar" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                    <input type="hidden" name="curso_id" value="<?= (int)$cursoId ?>">

                    <?php foreach ($candidatosPorSemestre as $semestreGrupo => $delGrupo): ?>
                        <?php $idGrupo = 'grupo-' . preg_replace('/[^a-z0-9]/i', '', $semestreGrupo); ?>
                        <div class="grupo-semestre" data-grupo="<?= htmlspecialchars($idGrupo, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="grupo-semestre-cabecera">
                                <h3><?= htmlspecialchars($semestreGrupo) ?></h3>
                                <span class="badge badge-neutral"><?= count($delGrupo) ?></span>
                                <button type="button" class="btn btn-sm btn-outline"
                                        onclick="marcarGrupo('<?= htmlspecialchars($idGrupo, ENT_QUOTES, 'UTF-8') ?>', true)">
                                    Marcar este semestre
                                </button>
                            </div>

                            <div class="lista-candidatos">
                                <?php foreach ($delGrupo as $e): ?>
                                    <?php $sinCarrera = empty($e['carrera_id']); ?>
                                    <label class="candidato <?= $sinCarrera ? 'sin-carrera' : '' ?>">
                                        <input type="checkbox" name="estudiante_id[]" value="<?= (int)$e['id'] ?>">
                                        <span>
                                            <strong><?= htmlspecialchars(trim($e['apellido'] . ' ' . $e['nombre'])) ?></strong>
                                            <small>
                                                <?= htmlspecialchars($e['codigo']) ?>
                                                <?= !empty($e['cedula']) ? ' · ' . htmlspecialchars($e['cedula']) : '' ?>
                                            </small>
                                            <?php if ($sinCarrera): ?>
                                                <?php /* Recien importado: todavia no pertenece a ninguna
                                                         carrera, asi que se avisa antes de marcarlo. Al
                                                         matricularlo hereda la de este curso. */ ?>
                                                <small class="candidato-aviso">
                                                    Sin carrera asignada &mdash; al matricularlo entrará en
                                                    <?= htmlspecialchars($carreraCurso ?? 'esta carrera') ?>
                                                </small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

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
                                <th>Documento</th>
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
                                        <?php if (!empty($e['cedula'])): ?>
                                            <?= htmlspecialchars($e['cedula']) ?>
                                            <?php if (($e['tipo_documento'] ?? 'cedula') === 'pasaporte'): ?>
                                                <span class="mini-tag">pasaporte</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-danger">falta</span>
                                        <?php endif; ?>
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
                                                    "tipo_documento" => (string)($e["tipo_documento"] ?? "cedula"),
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

                <?php
                /* Documento: cedula para los ecuatorianos y pasaporte para los
                   extranjeros. Antes solo se admitia cedula, asi que un alumno
                   de otro pais no se podia matricular de ninguna forma. */
                ?>
                <div class="form-fila">
                    <div class="form-group">
                        <label for="i_tipo_doc" class="form-label">Documento <span class="text-danger">*</span></label>
                        <select id="i_tipo_doc" name="tipo_documento" class="form-select"
                                onchange="ajustarDocumento('i')">
                            <?php foreach ($tiposDocumento as $valor => $etiqueta): ?>
                                <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($etiqueta) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="i_cedula" class="form-label">
                            <span id="i_doc_etiqueta">Número de cédula</span> <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="i_cedula" name="cedula" class="form-control form-control-code"
                               required inputmode="numeric" maxlength="10" placeholder="1701234567">
                    </div>
                </div>

                <small class="form-ayuda" id="i_doc_ayuda">
                    Obligatorio: es lo que le permite registrarse aunque olvide su código o pierda el carnet.
                </small>

                <div class="form-group">
                    <label for="i_carrera" class="form-label">Carrera <span class="text-danger">*</span></label>
                    <select id="i_carrera" name="carrera_id" class="form-select" required
                            onchange="revisarCarrera()">
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                    data-sigla="<?= htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= (int)$c['id'] === (int)$carreraCursoId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-ayuda">
                        Viene marcada la carrera de este curso. Su código empezará por las
                        siglas: <strong id="i_ejemploCodigo">&mdash;</strong>
                    </small>
                    <?php /* Cambiarla es legitimo —hay materias que dos carreras
                             comparten— pero conviene que se vea, porque de la carrera
                             sale el identificador del alumno. */ ?>
                    <small class="form-ayuda text-danger" id="i_avisoCarrera" hidden>
                        Esta no es la carrera del curso
                        (<?= htmlspecialchars($carreraCurso ?? '—') ?>). El alumno quedará
                        registrado en la que elijas, y se matricula igual en este curso.
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

                <div class="form-fila">
                    <div class="form-group">
                        <label for="e_tipo_doc" class="form-label">Documento <span class="text-danger">*</span></label>
                        <select id="e_tipo_doc" name="tipo_documento" class="form-select"
                                onchange="ajustarDocumento('e')">
                            <?php foreach ($tiposDocumento as $valor => $etiqueta): ?>
                                <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($etiqueta) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="e_cedula" class="form-label">
                            <span id="e_doc_etiqueta">Número de cédula</span> <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="e_cedula" name="cedula" class="form-control form-control-code"
                               required inputmode="numeric" maxlength="10">
                    </div>
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

/*
 * Mantiene al dia el ejemplo del codigo y el aviso de carrera distinta.
 *
 * El ejemplo importa porque de la carrera sale el identificador del alumno
 * (DSW-001, MEA-001): verlo antes de guardar evita el codigo equivocado.
 */
const CARRERA_DEL_CURSO = '<?= (int)$carreraCursoId ?>';

function revisarCarrera() {
    const select = document.getElementById('i_carrera');
    if (!select || !select.selectedOptions.length) return;

    const sigla = select.selectedOptions[0].dataset.sigla || 'EST';
    document.getElementById('i_ejemploCodigo').textContent = sigla + '-00…';

    const aviso = document.getElementById('i_avisoCarrera');
    if (aviso) {
        aviso.hidden = (select.value === CARRERA_DEL_CURSO);
    }
}

document.addEventListener('DOMContentLoaded', revisarCarrera);

/*
 * El campo del documento cambia de forma segun el tipo elegido.
 *
 * No es un detalle estetico: la cedula solo admite digitos y son diez
 * exactos, mientras que un pasaporte lleva letras y su largo varia. Con un
 * unico campo "solo numeros, maximo 10" no habia manera de escribir un
 * pasaporte, que es justamente lo que dejaba fuera a los extranjeros.
 */
function ajustarDocumento(prefijo) {
    const tipo    = document.getElementById(prefijo + '_tipo_doc');
    const campo   = document.getElementById(prefijo + '_cedula');
    const etiqueta = document.getElementById(prefijo + '_doc_etiqueta');
    if (!tipo || !campo) return;

    const esPasaporte = (tipo.value === 'pasaporte');

    if (esPasaporte) {
        campo.maxLength = 15;
        campo.placeholder = 'AB123456';
        campo.inputMode = 'text';
        campo.setAttribute('autocapitalize', 'characters');
        if (etiqueta) etiqueta.textContent = 'Número de pasaporte';
    } else {
        campo.maxLength = 10;
        campo.placeholder = '1701234567';
        campo.inputMode = 'numeric';
        campo.removeAttribute('autocapitalize');
        if (etiqueta) etiqueta.textContent = 'Número de cédula';
    }

    // Se limpia lo que ya no encaja con el tipo nuevo
    campo.value = esPasaporte
        ? campo.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15)
        : campo.value.replace(/\D/g, '').slice(0, 10);

    const ayuda = document.getElementById(prefijo + '_doc_ayuda');
    if (ayuda) {
        ayuda.textContent = esPasaporte
            ? 'Entre 6 y 15 letras y números, tal como aparece en el pasaporte.'
            : 'Obligatorio: es lo que le permite registrarse aunque olvide su código o pierda el carnet.';
    }
}

/** Filtra lo que se teclea segun el tipo de documento activo */
function filtrarDocumento(prefijo) {
    const tipo  = document.getElementById(prefijo + '_tipo_doc');
    const campo = document.getElementById(prefijo + '_cedula');
    if (!tipo || !campo) return;

    campo.value = (tipo.value === 'pasaporte')
        ? campo.value.toUpperCase().replace(/[^A-Z0-9]/g, '')
        : campo.value.replace(/\D/g, '');
}

/** Comprueba el documento antes de enviar. Devuelve false si no pasa. */
function validarDocumento(prefijo, evento) {
    const tipo  = document.getElementById(prefijo + '_tipo_doc');
    const campo = document.getElementById(prefijo + '_cedula');
    if (!tipo || !campo) return true;

    const valor = campo.value.trim();

    if (tipo.value === 'pasaporte') {
        // Sin digito verificador que comprobar: se exige una forma plausible
        const bien = /^[A-Z0-9]{6,15}$/.test(valor) && /\d/.test(valor);
        if (!bien) {
            evento.preventDefault();
            (window.avisar || alert)(
                'El pasaporte debe tener entre 6 y 15 letras y números, con al menos un dígito.',
                'error'
            );
            campo.focus();
            return false;
        }
        return true;
    }

    if (!window.cedulaValida || !window.cedulaValida(valor)) {
        evento.preventDefault();
        (window.avisar || alert)('La cédula no es válida. Revisa los 10 dígitos.', 'error');
        campo.focus();
        return false;
    }

    return true;
}

// Se enlaza el filtrado al teclear y se deja cada campo con la forma correcta
document.addEventListener('DOMContentLoaded', function () {
    ['i', 'e'].forEach(function (p) {
        const campo = document.getElementById(p + '_cedula');
        if (campo) {
            campo.addEventListener('input', function () { filtrarDocumento(p); });
        }
        ajustarDocumento(p);
    });
});

function marcarTodos(estado) {
    document.querySelectorAll('.lista-candidatos input[type=checkbox]')
        .forEach(c => { c.checked = estado; });
}

/** Marca de una vez a todo un semestre, que es como se matricula de verdad */
function marcarGrupo(idGrupo, estado) {
    const grupo = document.querySelector('[data-grupo="' + idGrupo + '"]');
    if (!grupo) return;
    grupo.querySelectorAll('input[type=checkbox]').forEach(c => { c.checked = estado; });
}

// La cédula es obligatoria al inscribir: se valida antes de enviar para no
// hacerle dar el viaje al servidor por un dígito mal escrito
function editarEstudiante(datos) {
    document.getElementById('e_id').value       = datos.id;
    document.getElementById('e_nombre').value   = datos.nombre;
    document.getElementById('e_apellido').value = datos.apellido;
    document.getElementById('e_tipo_doc').value = datos.tipo_documento || 'cedula';
    document.getElementById('e_cedula').value   = datos.cedula;
    ajustarDocumento('e');
    document.getElementById('e_telefono').value = datos.telefono;
    document.getElementById('e_semestre').value = datos.semestre;
    document.getElementById('e_codigo').textContent = datos.codigo || '';
    abrirModal('modalEditar');
    document.getElementById('e_telefono').focus();
}

document.getElementById('formEditar')?.addEventListener('submit', function (e) {
    if (!validarDocumento('e', e)) {
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
    if (!validarDocumento('i', e)) {
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
