<?php
$titulo = 'Cuentas del Sistema - ISTPET';
$vista  = 'admin-cuentas';
require dirname(__DIR__) . '/layouts/header.php';
$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Cuentas del Sistema</h1>
        <p class="page-subtitle">
            Docentes y administradores &bull; el correo se genera solo:
            <code>nombre.apellido@<?= htmlspecialchars($dominio) ?></code>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $base ?>/admin" class="btn btn-outline">&larr; Supervisión</a>
        <button type="button" class="btn btn-primary" onclick="abrirModal('modalCrear')">+ Nueva Cuenta</button>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<?php if (!empty($ultimoEnlace)): ?>
    <div class="card card-enlace mb-6">
        <h2 class="card-titulo">Aviso listo para <?= htmlspecialchars($ultimoEnlace['nombre']) ?></h2>

        <div class="form-group">
            <label class="form-label" for="mensajeClave">Mensaje</label>
            <textarea id="mensajeClave" class="form-control" rows="5" readonly><?= htmlspecialchars($ultimoEnlace['mensaje']) ?></textarea>
        </div>

        <div class="d-flex gap-2 flex-wrap mt-3">
            <?php if (!empty($ultimoEnlace['whatsapp'])): ?>
                <a href="<?= htmlspecialchars($ultimoEnlace['whatsapp'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener" class="btn btn-success">
                    Enviar por WhatsApp al <?= htmlspecialchars($ultimoEnlace['telefono']) ?>
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline" onclick="copiarMensaje()">Copiar mensaje</button>
        </div>

        <?php if (empty($ultimoEnlace['whatsapp'])): ?>
            <p class="form-ayuda mt-3 text-danger">
                Esta cuenta no tiene teléfono cargado. Edítala para agregarle el número
                y así poder enviárselo por WhatsApp.
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($qrTotp)): ?>
    <!-- Codigo QR recien generado para que el docente lo escanee delante del admin -->
    <div class="card card-enlace mb-6">
        <h2 class="card-titulo">Verificación en dos pasos de <?= htmlspecialchars($qrTotp['nombre']) ?></h2>
        <p class="text-muted mb-4" style="font-size:.9rem">
            Que <?= htmlspecialchars($qrTotp['nombre']) ?> escanee este código con
            <strong>Google Authenticator</strong> y escriba abajo el número de 6 dígitos
            que le muestre la aplicación.
        </p>

        <div class="totp-admin-grid">
            <div class="totp-qr-box">
                <?= $qrTotp['svg'] ?>
            </div>

            <div>
                <div class="access-code-box mb-4">
                    <span class="access-code-title">¿No puede escanear? Que escriba esta clave</span>
                    <strong class="access-code-val totp-secreto"><?= htmlspecialchars($qrTotp['secreto']) ?></strong>
                </div>

                <form action="<?= $base ?>/admin/docentes/2fa/activar" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                    <input type="hidden" name="id" value="<?= (int)$qrTotp['usuario_id'] ?>">

                    <div class="form-group mb-4">
                        <label for="codigoTotp" class="form-label">
                            Código de 6 dígitos <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="codigoTotp" name="codigo"
                               class="form-control form-control-code totp-input"
                               required inputmode="numeric" maxlength="6" minlength="6"
                               pattern="[0-9]{6}" placeholder="000000"
                               autocomplete="one-time-code" spellcheck="false" autofocus
                               oninput="this.value = this.value.replace(/\D/g,'')">
                        <small class="form-ayuda">El código cambia cada 30 segundos.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Confirmar y activar</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($sinTotp)): ?>
    <div class="alert alert-info">
        <span>
            Hay <strong><?= (int)$sinTotp ?></strong> cuenta(s) activa(s) sin verificación en dos pasos.
            Actívala desde el botón <strong>2FA</strong> de cada fila: protege la cuenta aunque
            alguien averigüe la contraseña.
        </span>
    </div>
<?php endif; ?>

<!-- Filtro por seleccion -->
<div class="card card-filter mb-6">
    <form method="GET" action="<?= $base ?>/admin/docentes" class="filtros-grid">
        <div class="form-group mb-0">
            <label for="rol" class="form-label">Filtrar por rol</label>
            <select id="rol" name="rol" class="form-select" onchange="this.form.submit()">
                <option value="">Todas las cuentas</option>
                <option value="docente" <?= $rolFiltro === 'docente' ? 'selected' : '' ?>>Solo docentes</option>
                <option value="admin"   <?= $rolFiltro === 'admin' ? 'selected' : '' ?>>Solo administradores</option>
            </select>
        </div>
        <?php if ($rolFiltro !== ''): ?>
            <div class="form-group mb-0 alinear-abajo">
                <a href="<?= $base ?>/admin/docentes" class="btn btn-outline btn-block">Quitar filtro</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Tabla de cuentas -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo institucional</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>2FA</th>
                    <th class="text-center">Cursos</th>
                    <th class="text-center">Clases</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="8" class="table-empty">No hay cuentas con ese filtro.</td></tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u):
                        $esYo     = ((int)$u['id'] === $usuarioActualId);
                        $esActivo = ((int)$u['activo'] === 1);
                        $tiene2fa = ((int)($u['totp_activado'] ?? 0) === 1);
                        $nombreCompleto = trim($u['nombre'] . ' ' . $u['apellido']);
                    ?>
                        <tr>
                            <td class="font-medium">
                                <?= htmlspecialchars($nombreCompleto) ?>
                                <?php if ($esYo): ?><span class="mini-tag">tu cuenta</span><?php endif; ?>
                                <div class="celda-sub">Desde <?= htmlspecialchars(substr((string)$u['creado_en'], 0, 10)) ?></div>
                            </td>
                            <td class="celda-correo">
                                <?= htmlspecialchars($u['correo']) ?>
                                <div class="celda-sub">
                                    <?= !empty($u['telefono'])
                                        ? htmlspecialchars($u['telefono'])
                                        : '<span class="text-danger">sin teléfono</span>' ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $u['rol'] === 'admin' ? 'badge-rol-admin' : 'badge-info' ?>">
                                    <?= $u['rol'] === 'admin' ? 'ADMIN' : 'DOCENTE' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $esActivo ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $esActivo ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tiene2fa): ?>
                                    <span class="badge badge-success" title="Verificación en dos pasos activa">Activa</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral" title="Esta cuenta solo pide contraseña">Sin 2FA</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= (int)$u['total_cursos'] ?></td>
                            <td class="text-center"><?= (int)$u['total_clases'] ?></td>
                            <td class="text-right">
                                <div class="acciones-fila">
                                    <button type="button" class="btn btn-sm btn-outline"
                                            onclick='abrirEditar(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                        Editar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline"
                                            onclick="abrirClave(<?= (int)$u['id'] ?>, <?= htmlspecialchars(json_encode($nombreCompleto), ENT_QUOTES, 'UTF-8') ?>)">
                                        Clave
                                    </button>

                                    <?php if ($tiene2fa): ?>
                                        <!-- Salida de emergencia: el docente perdio el telefono -->
                                        <form action="<?= $base ?>/admin/docentes/2fa/quitar" method="POST" class="inline"
                                              onsubmit="return confirm('¿Quitar la verificación en dos pasos de <?= htmlspecialchars(addslashes($nombreCompleto), ENT_QUOTES) ?>? Su cuenta quedará protegida solo por la contraseña.');">
                                            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-peligro-suave">Quitar 2FA</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="<?= $base ?>/admin/docentes/2fa/preparar" method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-dorado">Activar 2FA</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$esYo): ?>
                                        <form action="<?= $base ?>/admin/docentes/estado" method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="activo" value="<?= $esActivo ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-sm <?= $esActivo ? 'btn-peligro-suave' : 'btn-dorado' ?>">
                                                <?= $esActivo ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== MODAL: crear cuenta ==================== -->
<div id="modalCrear" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Nueva Cuenta</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalCrear')">&times;</button>
        </div>

        <form action="<?= $base ?>/admin/docentes/crear" method="POST" id="formCrear">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">

            <div class="form-fila">
                <div class="form-group">
                    <label for="c_nombre" class="form-label">Nombres <span class="text-danger">*</span></label>
                    <input type="text" id="c_nombre" name="nombre" class="form-control" required maxlength="50"
                           placeholder="Ej: Juan" oninput="previsualizar()">
                </div>
                <div class="form-group">
                    <label for="c_apellido" class="form-label">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" id="c_apellido" name="apellido" class="form-control" required maxlength="50"
                           placeholder="Ej: Tapia" oninput="previsualizar()">
                </div>
            </div>

            <div class="vista-previa-correo">
                <span>Correo que se generará:</span>
                <strong id="previewCorreo">nombre.apellido@<?= htmlspecialchars($dominio) ?></strong>
            </div>

            <div class="form-group">
                <label for="c_telefono" class="form-label">Teléfono (WhatsApp)</label>
                <input type="tel" id="c_telefono" name="telefono" class="form-control"
                       inputmode="numeric" maxlength="13" placeholder="0991112233"
                       oninput="this.value = this.value.replace(/[^\d]/g,'')">
                <small class="form-ayuda">
                    Es por donde se le envía el enlace si alguna vez olvida su contraseña.
                    Sin teléfono habrá que pasárselo a mano.
                </small>
            </div>

            <div class="form-group">
                <label for="c_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                <select id="c_rol" name="rol" class="form-select" required>
                    <option value="docente">Docente — dicta clases y genera QR</option>
                    <option value="admin">Administrador — solo supervisa y gestiona cuentas</option>
                </select>
            </div>

            <div class="form-group mb-6">
                <label for="c_password" class="form-label">Contraseña inicial <span class="text-danger">*</span></label>
                <input type="password" id="c_password" name="password" class="form-control" required
                       minlength="<?= (int)$minPassword ?>" maxlength="72" placeholder="••••••••">
                <small class="form-ayuda">
                    Mínimo <?= (int)$minPassword ?> caracteres, con al menos una letra y un número.
                    Se guarda cifrada con Bcrypt.
                </small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrear')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Cuenta</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: editar cuenta ==================== -->
<div id="modalEditar" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Editar Cuenta</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalEditar')">&times;</button>
        </div>

        <form action="<?= $base ?>/admin/docentes/actualizar" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <input type="hidden" name="id" id="e_id">

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

            <div class="alert alert-info" style="font-size:.82rem">
                <span>Si cambias el nombre o el apellido, el correo institucional se regenera automáticamente.</span>
            </div>

            <div class="form-group">
                <label for="e_telefono" class="form-label">Teléfono (WhatsApp)</label>
                <input type="tel" id="e_telefono" name="telefono" class="form-control"
                       inputmode="numeric" maxlength="13" placeholder="0991112233"
                       oninput="this.value = this.value.replace(/[^\d]/g,'')">
            </div>

            <div class="form-fila">
                <div class="form-group">
                    <label for="e_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                    <select id="e_rol" name="rol" class="form-select" required>
                        <option value="docente">Docente</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="e_activo" class="form-label">Estado <span class="text-danger">*</span></label>
                    <select id="e_activo" name="activo" class="form-select" required>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditar')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: restablecer clave ==================== -->
<div id="modalClave" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Restablecer Contraseña</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalClave')">&times;</button>
        </div>

        <form action="<?= $base ?>/admin/docentes/password" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <input type="hidden" name="id" id="k_id">

            <p class="text-muted mb-4" style="font-size:.88rem">
                Nueva contraseña para <strong id="k_nombre" class="text-primary"></strong>
            </p>

            <div class="form-group mb-6">
                <label for="k_password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                <input type="password" id="k_password" name="password" class="form-control" required
                       minlength="<?= (int)$minPassword ?>" maxlength="72" placeholder="••••••••">
                <small class="form-ayuda">
                    Mínimo <?= (int)$minPassword ?> caracteres, con al menos una letra y un número.
                </small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalClave')">Cancelar</button>
                <button type="submit" class="btn btn-dorado">Actualizar Contraseña</button>
            </div>
        </form>
    </div>
</div>

<script>
const DOMINIO = <?= json_encode($dominio) ?>;

function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.style.display = 'none'; });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
});

// Muestra en vivo el correo que se va a generar, con la misma regla del servidor
function limpiarParte(texto) {
    return (texto || '')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')  // quita tildes
        .trim().split(/\s+/)[0]                            // solo la primera palabra
        .toLowerCase().replace(/[^a-z0-9]/g, '');
}

function previsualizar() {
    const n = limpiarParte(document.getElementById('c_nombre').value);
    const a = limpiarParte(document.getElementById('c_apellido').value);
    document.getElementById('previewCorreo').textContent =
        (n || 'nombre') + '.' + (a || 'apellido') + '@' + DOMINIO;
}

document.getElementById('formCrear').addEventListener('submit', function (e) {
    const clave = document.getElementById('c_password').value;
    if (!/[A-Za-z]/.test(clave) || !/\d/.test(clave)) {
        e.preventDefault();
        alert('La contraseña debe combinar al menos una letra y un número.');
    }
});

function copiarMensaje() {
    const campo = document.getElementById('mensajeClave');
    campo.select();
    navigator.clipboard?.writeText(campo.value)
        .then(() => (window.avisar || alert)('Mensaje copiado.', 'ok'))
        .catch(() => document.execCommand('copy'));
}

function abrirEditar(u) {
    document.getElementById('e_id').value = u.id;
    document.getElementById('e_nombre').value = u.nombre;
    document.getElementById('e_apellido').value = u.apellido;
    document.getElementById('e_telefono').value = u.telefono || '';
    document.getElementById('e_rol').value = u.rol;
    document.getElementById('e_activo').value = String(u.activo);
    abrirModal('modalEditar');
}

function abrirClave(id, nombre) {
    document.getElementById('k_id').value = id;
    document.getElementById('k_nombre').textContent = nombre;
    document.getElementById('k_password').value = '';
    abrirModal('modalClave');
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
