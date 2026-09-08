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
$sinCedula = 0;
foreach ($estudiantes as $e) {
    if (empty($e['cedula'])) {
        $sinCedula++;
    }
}
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

<!-- Filtro por semestre -->
<div class="card card-filter mb-6 no-imprimir">
    <form method="GET" action="<?= $base ?>/docente/carnets" class="filtros-grid">
        <div class="form-group mb-0">
            <label for="semestre" class="form-label">Filtrar por semestre</label>
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
                <?php if ($semestre !== ''): ?>
                    <a href="<?= $base ?>/docente/carnets" class="btn btn-outline btn-sm">Quitar filtro</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (empty($estudiantes)): ?>
    <div class="card">
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">Todavía no hay estudiantes en el padrón</p>
            <p class="text-muted">
                Los alumnos se agregan solos la primera vez que registran su asistencia,
                o puedes darlos de alta desde el panel de clase con el registro manual.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="carnets-grid" data-region="carnets">
        <?php foreach ($estudiantes as $e): ?>
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
