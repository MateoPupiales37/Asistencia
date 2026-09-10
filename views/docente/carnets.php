<?php
/**
 * Carnets QR de los estudiantes.
 *
 * Esta pantalla estaba enlazada desde la barra de navegacion y desde el panel
 * del docente, pero el archivo de vista no existia: la ruta /docente/carnets
 * respondia "La vista docente.carnets no existe". Aqui se implementa.
 *
 * Cada carnet lleva el token personal del alumno dentro del QR. Con el escaneado
 * el sistema ya sabe quien es y solo le falta el codigo de la clase.
 */

$titulo = 'Carnets QR - ISTPET';
$vista  = 'docente-carnets';
require dirname(__DIR__) . '/layouts/header.php';

$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');

/*
 * Los carnets se agrupan POR CARRERA.
 *
 * Es la pantalla desde la que se imprimen y se reparten, y una cuadrícula de
 * nombres sueltos no dice de quién es cada uno: al entregarlos terminaban
 * mezclados. Agrupados, cada bloque se imprime y se entrega junto.
 */
$sinCedula   = 0;
$porCarrera  = [];

foreach ($estudiantes as $e) {
    if (empty($e['cedula'])) {
        $sinCedula++;
    }
    $porCarrera[$e['carrera'] ?? 'Sin carrera asignada'][] = $e;
}

ksort($porCarrera);
?>

<nav class="breadcrumb">
    <a href="<?= $base ?>/docente">Mi Clase</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Carnets QR</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">Carnets QR</h1>
        <p class="page-subtitle">
            Cada alumno escanea su carnet y queda identificado sin escribir nada
        </p>
    </div>
    <div class="acciones-cabecera">
        <a href="<?= $base ?>/docente" class="btn btn-back">&larr; Volver</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir carnets</button>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<?php if (!empty($avisoRed)): ?>
    <div class="alert alert-<?= $avisoRed['tipo'] === 'error' ? 'error' : 'info' ?> no-imprimir">
        <span>
            <?= htmlspecialchars($avisoRed['texto']) ?>
            <?php if (!empty($avisoRed['ips'])): ?>
                <br><small>Direcciones detectadas: <?= htmlspecialchars(implode(' · ', $avisoRed['ips'])) ?></small>
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if ($sinCedula > 0): ?>
    <div class="alert alert-info no-imprimir">
        <span>
            Hay <strong><?= $sinCedula ?></strong> estudiante(s) sin cédula cargada.
            La cédula es lo que les permite registrarse cuando olvidan su código o
            pierden el carnet: convie&shy;ne completarla desde cada tarjeta.
        </span>
    </div>
<?php endif; ?>

<!-- Filtros: carrera, semestre y alcance -->
<div class="card card-filter mb-6 no-imprimir">
    <form method="GET" action="<?= $base ?>/docente/carnets" class="filtros-grid">
        <div class="form-group mb-0">
            <label for="carrera" class="form-label">Carrera</label>
            <select id="carrera" name="carrera" class="form-select" onchange="this.form.submit()">
                <option value="">Todas las carreras</option>
                <?php foreach ($carreras as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$carreraId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-0">
            <label for="alcance" class="form-label">Mostrar</label>
            <select id="alcance" name="alcance" class="form-select" onchange="this.form.submit()">
                <option value="mios"  <?= $alcance === 'mios'  ? 'selected' : '' ?>>Solo mis estudiantes</option>
                <option value="todos" <?= $alcance === 'todos' ? 'selected' : '' ?>>Todo el padrón</option>
            </select>
        </div>

        <div class="form-group mb-0">
            <label for="semestre" class="form-label">Semestre</label>
            <select id="semestre" name="semestre" class="form-select" onchange="this.form.submit()">
                <option value="">Todos los semestres</option>
                <?php foreach ($semestres as $s): ?>
                    <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $semestre === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0 alinear-abajo">
            <div class="form-fila">
                <span class="badge badge-neutral"><?= count($estudiantes) ?> estudiante(s)</span>
                <?php if ($semestre !== '' || $carreraId || $alcance !== 'mios'): ?>
                    <a href="<?= $base ?>/docente/carnets" class="btn btn-outline btn-sm">Quitar filtros</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (empty($estudiantes)): ?>
    <div class="card">
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">
                <?= $alcance === 'mios'
                    ? 'No tienes estudiantes matriculados con esos filtros'
                    : 'No hay estudiantes en el padrón con esos filtros' ?>
            </p>
            <p class="text-muted mb-4">
                <?php if ($alcance === 'mios'): ?>
                    Aquí salen los alumnos matriculados en tus cursos. Matricúlalos desde
                    <a href="<?= $base ?>/docente/matriculas">Estudiantes</a>, o cambia
                    “Mostrar” a <strong>Todo el padrón</strong> para ver los del resto del instituto.
                <?php else: ?>
                    Los alumnos se dan de alta desde
                    <a href="<?= $base ?>/docente/matriculas">Estudiantes</a>, o desde el panel de
                    clase con el registro manual.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div data-region="carnets">
    <?php foreach ($porCarrera as $nombreCarrera => $grupo): ?>
        <h2 class="grupo-carrera">
            <?= htmlspecialchars($nombreCarrera) ?>
            <span class="grupo-carrera-cifra"><?= count($grupo) ?></span>
        </h2>

        <div class="carnets-grid">
        <?php foreach ($grupo as $e): ?>
            <?php $nombreCompleto = trim($e['nombre'] . ' ' . $e['apellido']); ?>
            <article class="carnet">
                <header class="carnet-cabecera">
                    <img src="<?= $base ?>/assets/img/logo-istpet.jpg" alt="" class="carnet-logo">
                    <div>
                        <strong class="carnet-instituto">ISTPET</strong>
                        <span class="carnet-leyenda">Carnet de asistencia</span>
                    </div>
                </header>

                <div class="carnet-qr"><?= $e['qr'] ?></div>

                <div class="carnet-datos">
                    <strong class="carnet-nombre"><?= htmlspecialchars($nombreCompleto) ?></strong>
                    <span class="carnet-codigo"><?= htmlspecialchars($e['codigo']) ?></span>
                    <?php /* La carrera va impresa en el propio carnet: es lo que
                             permite devolverle el suyo a cada alumno cuando se
                             mezclan al repartirlos. */ ?>
                    <span class="carnet-carrera <?= empty($e['carrera']) ? 'es-faltante' : '' ?>">
                        <?= htmlspecialchars($e['carrera'] ?? 'Sin carrera asignada') ?>
                    </span>
                    <span class="carnet-semestre"><?= htmlspecialchars($e['semestre']) ?></span>
                    <span class="carnet-cedula">
                        <?php if (!empty($e['cedula'])): ?>
                            Cédula: <?= htmlspecialchars($e['cedula']) ?>
                        <?php else: ?>
                            <span class="text-danger">Sin cédula registrada</span>
                        <?php endif; ?>
                    </span>
                </div>

                <footer class="carnet-acciones no-imprimir">
                    <button type="button" class="btn btn-sm btn-outline btn-block"
                            onclick="abrirCedula(<?= (int)$e['id'] ?>, '<?= htmlspecialchars(addslashes($nombreCompleto), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)$e['cedula'], ENT_QUOTES, 'UTF-8') ?>')">
                        <?= !empty($e['cedula']) ? 'Cambiar cédula' : 'Cargar cédula' ?>
                    </button>

                </footer>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    </div>

    <p class="aviso-carnet no-imprimir">
        Cada estudiante tiene un único carnet permanente. Imprímelo y entrégaselo:
        con él se identifica sin escribir nada.
    </p>
<?php endif; ?>

<!-- Modal para cargar o corregir la cedula -->
<div id="modalCedula" class="modal-overlay no-imprimir">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Cédula del estudiante</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalCedula')" aria-label="Cerrar">&times;</button>
        </div>

        <form action="<?= $base ?>/docente/carnets/cedula" method="POST" id="formCedula">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <input type="hidden" name="estudiante_id" id="cedulaEstudianteId">

            <p class="text-muted mb-4" style="font-size:.88rem">
                Estudiante: <strong id="cedulaNombre"></strong>
            </p>

            <div class="form-group">
                <label for="cedulaValor" class="form-label">Número de cédula <span class="text-danger">*</span></label>
                <input type="text" id="cedulaValor" name="cedula" class="form-control form-control-code"
                       inputmode="numeric" maxlength="10" required placeholder="1701234567"
                       oninput="this.value = this.value.replace(/\D/g,'')">
                <small class="form-ayuda">
                    10 dígitos. Se valida el dígito verificador, así que una cédula inventada será rechazada.
                </small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCedula')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cédula</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('abierto'); }
}

function cerrarModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('abierto'); }
}

function abrirCedula(id, nombre, cedula) {
    document.getElementById('cedulaEstudianteId').value = id;
    document.getElementById('cedulaNombre').textContent = nombre;
    document.getElementById('cedulaValor').value = cedula || '';
    abrirModal('modalCedula');
    document.getElementById('cedulaValor').focus();
}

// Cerrar con Escape o pulsando fuera de la tarjeta
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { cerrarModal('modalCedula'); }
});
document.getElementById('modalCedula').addEventListener('click', function (e) {
    if (e.target === this) { cerrarModal('modalCedula'); }
});

document.getElementById('formCedula').addEventListener('submit', function (e) {
    if (document.getElementById('cedulaValor').value.length !== 10) {
        e.preventDefault();
        alert('La cédula debe tener exactamente 10 dígitos.');
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
