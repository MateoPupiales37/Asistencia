<?php
/**
 * Elección del período académico.
 *
 * Es la primera pantalla del administrador al entrar. Todo lo que hará
 * después —crear materias, asignar docentes, sacar reportes— ocurre dentro
 * de un ciclo lectivo concreto, y dar por supuesto cuál es el ciclo es como
 * termina la malla de un período cargada dentro de otro.
 *
 * Desde aquí se administran también las carreras, porque son el otro dato
 * que ubica a una materia y se toca con la misma frecuencia: casi nunca.
 */

$titulo = 'Período Académico - ISTPET';
$vista  = 'admin-periodo';
require dirname(__DIR__) . '/layouts/header.php';

$tok     = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
$hoy     = date('Y-m-d');
$activos = array_values(array_filter($periodos, static fn($p) => (int)$p['activo'] === 1));

// Los cuatro ambientes que existen. Cada carrera marca los suyos: el taller
// solo lo usa Mecanica, y ofrecerselo a Educacion Inicial seria una opcion
// mas para equivocarse al asignar un docente.
require_once dirname(dirname(__DIR__)) . '/models/Catalogo.php';
$todosAmbientes = Catalogo::AMBIENTES;
?>

<nav class="breadcrumb">
    <a href="<?= $base ?>/admin">Supervisión</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Período académico</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">¿En qué período vas a trabajar?</h1>
        <p class="page-subtitle">
            Las materias, las asignaciones y los reportes que veas después
            corresponderán solo al período que elijas aquí
        </p>
    </div>
    <div class="acciones-cabecera">
        <button type="button" class="btn btn-outline" onclick="abrirModal('modalCarreras')">Carreras</button>
        <button type="button" class="btn btn-primary" onclick="abrirModal('modalPeriodo')">+ Nuevo Período</button>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<?php if (empty($periodos)): ?>
    <div class="estado-vacio">
        <p class="estado-vacio-titulo">Todavía no hay ningún período académico</p>
        <p class="text-muted mb-4">
            El período es el ciclo lectivo del instituto. Crea el primero para
            poder registrar materias y asignarles docentes.
        </p>
        <button type="button" class="btn btn-primary" onclick="abrirModal('modalPeriodo')">
            Crear el primer período
        </button>
    </div>
<?php endif; ?>

<!-- ------------------------- Elegir período ------------------------- -->
<div class="periodos-grid mb-6">
    <?php foreach ($periodos as $p): ?>
        <?php
            $esElegido = ((int)$p['id'] === (int)$elegido);
            $cerrado   = ((int)$p['activo'] === 0);
            $enCurso   = (!$cerrado && $hoy >= $p['fecha_inicio'] && $hoy <= $p['fecha_fin']);
        ?>
        <div class="periodo-tarjeta <?= $esElegido ? 'es-elegido' : '' ?> <?= $cerrado ? 'es-cerrado' : '' ?>">
            <div class="periodo-cabecera">
                <h3 class="periodo-nombre"><?= htmlspecialchars($p['nombre']) ?></h3>
                <?php if ($enCurso): ?>
                    <span class="badge badge-success">EN CURSO</span>
                <?php elseif ($cerrado): ?>
                    <span class="badge badge-neutral">CERRADO</span>
                <?php endif; ?>
            </div>

            <p class="periodo-fechas">
                <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?>
                al <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?>
            </p>

            <ul class="periodo-cifras">
                <li><strong><?= (int)$p['total_materias'] ?></strong> materias</li>
                <li><strong><?= (int)$p['total_cursos'] ?></strong> asignaciones</li>
                <li><strong><?= (int)$p['total_clases'] ?></strong> clases dictadas</li>
            </ul>

            <form action="<?= $base ?>/admin/periodo/elegir" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <input type="hidden" name="periodo_id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn <?= $esElegido ? 'btn-outline' : 'btn-primary' ?> btn-block">
                    <?= $esElegido ? 'Seguir en este período' : 'Trabajar en este período' ?>
                </button>
            </form>

            <button type="button" class="periodo-editar"
                    onclick="abrirModal('modalEditar<?= (int)$p['id'] ?>')">Editar nombre o fechas</button>
        </div>
    <?php endforeach; ?>
</div>

<?php if (count($periodos) >= 2): ?>
<!-- --------------------- Copiar malla de materias --------------------- -->
<div class="card mb-6">
    <div class="card-header-flex">
        <h3 class="card-titulo">Copiar la malla de un período a otro</h3>
    </div>

    <form action="<?= $base ?>/admin/periodo/copiar-materias" method="POST"
          data-confirmar="Se copiarán las materias del período de origen al de destino, sin docente asignado. ¿Continuar?">
        <input type="hidden" name="csrf_token" value="<?= $tok ?>">

        <p class="form-ayuda mb-3">
            Al abrir un ciclo la malla suele ser la misma que la del anterior.
            Copiarla evita volver a escribir cada materia, que es justo donde
            aparecen los errores de tipeo. Se copian <strong>sin docente</strong>:
            quién dicta cada una se decide en cada período.
        </p>

        <div class="form-fila">
            <div class="form-group">
                <label class="form-label" for="origen_id">Copiar desde <span class="text-danger">*</span></label>
                <select id="origen_id" name="origen_id" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= htmlspecialchars($p['nombre']) ?> (<?= (int)$p['total_materias'] ?> materias)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="destino_id">Copiar hacia <span class="text-danger">*</span></label>
                <select id="destino_id" name="destino_id" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($activos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-outline btn-block">Copiar materias</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ====================== Modal: nuevo período ====================== -->
<div class="modal-overlay" id="modalPeriodo">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title">Abrir un nuevo período</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalPeriodo')" aria-label="Cerrar">&times;</button>
        </div>

        <form action="<?= $base ?>/admin/periodo/crear" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">

            <div class="form-group">
                <label class="form-label" for="nuevo_nombre">Nombre del período <span class="text-danger">*</span></label>
                <input type="text" id="nuevo_nombre" name="nombre" class="form-control"
                       placeholder="<?= htmlspecialchars($sugerido) ?>" required maxlength="40">
                <small class="form-ayuda">
                    Como lo llama el instituto: <?= htmlspecialchars($sugerido) ?>,
                    &ldquo;Octubre 2026 &ndash; Marzo 2027&rdquo;&hellip;
                </small>
            </div>

            <div class="form-fila">
                <div class="form-group">
                    <label class="form-label" for="nuevo_inicio">Fecha de inicio <span class="text-danger">*</span></label>
                    <input type="date" id="nuevo_inicio" name="fecha_inicio" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="nuevo_fin">Fecha de cierre <span class="text-danger">*</span></label>
                    <input type="date" id="nuevo_fin" name="fecha_fin" class="form-control" required>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalPeriodo')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear período</button>
            </div>
        </form>
    </div>
</div>

<!-- ================== Modales: editar cada período ================== -->
<?php foreach ($periodos as $p): ?>
<div class="modal-overlay" id="modalEditar<?= (int)$p['id'] ?>">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title">Editar &ldquo;<?= htmlspecialchars($p['nombre']) ?>&rdquo;</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalEditar<?= (int)$p['id'] ?>')" aria-label="Cerrar">&times;</button>
        </div>

        <form action="<?= $base ?>/admin/periodo/actualizar" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

            <div class="form-group">
                <label class="form-label" for="pnombre<?= (int)$p['id'] ?>">Nombre <span class="text-danger">*</span></label>
                <input type="text" id="pnombre<?= (int)$p['id'] ?>" name="nombre" class="form-control"
                       value="<?= htmlspecialchars($p['nombre']) ?>" required maxlength="40">
            </div>

            <div class="form-fila">
                <div class="form-group">
                    <label class="form-label" for="pinicio<?= (int)$p['id'] ?>">Inicio <span class="text-danger">*</span></label>
                    <input type="date" id="pinicio<?= (int)$p['id'] ?>" name="fecha_inicio" class="form-control"
                           value="<?= htmlspecialchars($p['fecha_inicio']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pfin<?= (int)$p['id'] ?>">Cierre <span class="text-danger">*</span></label>
                    <input type="date" id="pfin<?= (int)$p['id'] ?>" name="fecha_fin" class="form-control"
                           value="<?= htmlspecialchars($p['fecha_fin']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pactivo<?= (int)$p['id'] ?>">Estado</label>
                    <select id="pactivo<?= (int)$p['id'] ?>" name="activo" class="form-select">
                        <option value="1" <?= (int)$p['activo'] === 1 ? 'selected' : '' ?>>Abierto</option>
                        <option value="0" <?= (int)$p['activo'] === 0 ? 'selected' : '' ?>>Cerrado (solo consulta)</option>
                    </select>
                </div>
            </div>

            <p class="form-ayuda mb-3">
                Cerrar un período no borra nada: sus clases y sus reportes se
                conservan, pero deja de proponerse para trabajar en él.
            </p>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditar<?= (int)$p['id'] ?>')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- ======================== Modal: carreras ======================== -->
<div class="modal-overlay" id="modalCarreras">
    <div class="modal-content modal-ancho">
        <div class="modal-header-row">
            <h3 class="modal-title">Carreras</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalCarreras')" aria-label="Cerrar">&times;</button>
        </div>

        <p class="form-ayuda mb-4">
            Cada materia pertenece a una carrera. Mientras haya una sola, el
            sistema la propone por defecto y casi no se nota; en cuanto entre la
            segunda, es lo que impide que dos materias llamadas igual queden
            mezcladas en el mismo catálogo.
        </p>

        <div class="table-responsive mb-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Carrera</th>
                        <th>Ambientes</th>
                        <th class="text-right">Materias</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($carreras)): ?>
                        <tr><td colspan="5" class="text-muted">Todavía no hay carreras cargadas.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($carreras as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['codigo']) ?></strong></td>
                            <td><?= htmlspecialchars($c['nombre']) ?></td>
                            <td>
                                <?php foreach (Carrera::ambientes($c) as $a): ?>
                                    <span class="curso-ambiente amb-<?= Catalogo::claseAmbiente($a) ?>">
                                        <?= htmlspecialchars($a) ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-right"><?= (int)$c['total_materias'] ?></td>
                            <td>
                                <span class="badge <?= (int)$c['activa'] === 1 ? 'badge-success' : 'badge-neutral' ?>">
                                    <?= (int)$c['activa'] === 1 ? 'Activa' : 'Archivada' ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5">
                                <form action="<?= $base ?>/admin/carreras/actualizar" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">

                                    <div class="form-fila">
                                    <div class="form-group">
                                        <label class="form-label" for="ccod<?= (int)$c['id'] ?>">Código</label>
                                        <input type="text" id="ccod<?= (int)$c['id'] ?>" name="codigo" class="form-control"
                                               value="<?= htmlspecialchars($c['codigo']) ?>" required maxlength="20">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="cnom<?= (int)$c['id'] ?>">Nombre</label>
                                        <input type="text" id="cnom<?= (int)$c['id'] ?>" name="nombre" class="form-control"
                                               value="<?= htmlspecialchars($c['nombre']) ?>" required maxlength="120">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="cact<?= (int)$c['id'] ?>">Estado</label>
                                        <select id="cact<?= (int)$c['id'] ?>" name="activa" class="form-select">
                                            <option value="1" <?= (int)$c['activa'] === 1 ? 'selected' : '' ?>>Activa</option>
                                            <option value="0" <?= (int)$c['activa'] === 0 ? 'selected' : '' ?>>Archivada</option>
                                        </select>
                                    </div>
                                </div>

                                <?php $suyos = Carrera::ambientes($c); ?>
                                <fieldset class="grupo-casillas">
                                    <legend>Ambientes donde dicta clases</legend>
                                    <?php foreach ($todosAmbientes as $a): ?>
                                        <label class="casilla">
                                            <input type="checkbox" name="ambientes[]"
                                                   value="<?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?>"
                                                   <?= in_array($a, $suyos, true) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($a) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>

                                <button type="submit" class="btn btn-outline btn-sm">Guardar carrera</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form action="<?= $base ?>/admin/carreras/crear" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">

            <div class="form-fila">
            <div class="form-group">
                <label class="form-label" for="carrera_nombre">Nueva carrera <span class="text-danger">*</span></label>
                <input type="text" id="carrera_nombre" name="nombre" class="form-control"
                       placeholder="Desarrollo de Software" required maxlength="120"
                       oninput="sugerirSiglas(this)">
            </div>
            <div class="form-group">
                <label class="form-label" for="carrera_codigo">Código</label>
                <input type="text" id="carrera_codigo" name="codigo" class="form-control form-control-code"
                       placeholder="DSW" maxlength="20"
                       oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g,''); this.dataset.tocado = '1';">
                <small class="form-ayuda">Se propone solo con las iniciales.</small>
            </div>
        </div>

        <fieldset class="grupo-casillas">
            <legend>Ambientes donde dicta clases</legend>
            <?php foreach ($todosAmbientes as $a): ?>
                <label class="casilla">
                    <input type="checkbox" name="ambientes[]"
                           value="<?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?>"
                           <?= $a === 'Taller' ? '' : 'checked' ?>>
                    <span><?= htmlspecialchars($a) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit" class="btn btn-primary">Agregar carrera</button>
        </form>
    </div>
</div>

<script>
function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

// Cerrar tocando fuera de la ventana, no dentro de ella
document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.style.display = 'none'; });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
});

/*
 * Propone las siglas mientras se escribe el nombre, y deja de hacerlo en
 * cuanto el administrador escribe el código a mano: si no, le borraría lo
 * que acaba de teclear en cada letra del nombre.
 */
function sugerirSiglas(campo) {
    const codigo = document.getElementById('carrera_codigo');
    if (!codigo || codigo.dataset.tocado === '1') return;

    const ignorar = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'a', 'en'];
    codigo.value = campo.value
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .split(/\s+/)
        .filter(p => p && !ignorar.includes(p.toLowerCase()))
        .map(p => p[0].toUpperCase())
        .join('')
        .slice(0, 20);
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
