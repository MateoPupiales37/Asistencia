<?php
$titulo = 'Mi Clase - ISTPET';

$tieneClase = !empty($sesionActiva);

// Nombre de la pantalla para la capa AJAX. Se define ANTES de la cabecera
// porque es la cabecera la que lo escribe en el <main>.
// Abrir o cerrar una clase reordena la pagina entera, por eso cada estado
// se declara como una vista distinta: asi la capa AJAX navega de verdad en
// lugar de intentar parchear bloques que ya no existen.
$vista = $tieneClase ? 'docente-clase' : 'docente-libre';

require dirname(__DIR__) . '/layouts/header.php';
$entradaViva = $tieneClase && $segundosEntrada > 0;
$salidaViva  = $tieneClase && $segundosSalida > 0;
$tok = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mi Clase</h1>
        <p class="page-subtitle">Docente: <strong><?= htmlspecialchars($docenteNombre) ?></strong></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $base ?>/docente/carnets" class="btn btn-outline">Carnets QR</a>
        <a href="<?= $base ?>/reportes" class="btn btn-primary">Reportes</a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><span><?= htmlspecialchars($mensaje) ?></span></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
<?php endif; ?>

<?php if (!empty($alcance) && empty($alcance['ok'])): ?>
    <div class="alert alert-error">
        <span>
            <strong>Los celulares no van a poder abrir el código QR.</strong><br>
            <?= htmlspecialchars($alcance['texto']) ?>
            <?php if (!empty($alcance['ip'])): ?>
                <br><small>
                    Comprueba desde un celular en la misma wifi:
                    <code>http://<?= htmlspecialchars($alcance['ip']) ?><?= htmlspecialchars($base) ?>/</code>
                </small>
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($avisoRed)): ?>
    <div class="alert alert-<?= $avisoRed['tipo'] === 'error' ? 'error' : 'info' ?>">
        <span>
            <?= htmlspecialchars($avisoRed['texto']) ?>
            <?php if (!empty($avisoRed['ips'])): ?>
                <br><small>Direcciones de red detectadas:
                    <?php foreach ($avisoRed['ips'] as $ip): ?>
                        <code><?= htmlspecialchars($ip) ?></code>
                    <?php endforeach; ?>
                </small>
            <?php endif; ?>
            <?php if (!empty($avisoRed['sugerencia'])): ?>
                <br><a href="<?= htmlspecialchars($avisoRed['sugerencia'], ENT_QUOTES, 'UTF-8') ?>"
                       class="enlace-detalle">Abrir por localhost &rarr;</a>
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if (empty($consentimientoOk)): ?>
    <?php
    /* Sin consentimiento no se puede tomar la ubicacion, y sin ubicacion la
       geocerca no existe: conviene decirlo antes de que abra la clase. */
    ?>
    <div class="alert alert-info">
        <span>
            <strong>El control de ubicación está desactivado.</strong>
            Hasta que aceptes el uso de tu ubicación, tus clases se abrirán sin geocerca
            y cualquiera con el código podrá registrarse desde donde esté.
            <a href="<?= $base ?>/docente/perfil" class="enlace-detalle">Activarlo en mi perfil &rarr;</a>
        </span>
    </div>
<?php endif; ?>

<?php if (!$tieneClase): ?>
    <!-- ============================================================
         SIN CLASE ABIERTA: elegir curso e iniciar
         ============================================================ -->
    <div class="card mb-6">
        <h2 class="card-titulo">Iniciar una Clase</h2>
        <p class="text-muted mb-4">
            Elige el curso y se generará al instante el código QR de entrada,
            válido <strong><?= (int)$minutosQr ?> minutos</strong>.
        </p>

        <?php if (empty($cursos)): ?>
            <div class="alert alert-info">
                <span>Todavía no tienes cursos. Crea uno abajo para poder iniciar clases.</span>
            </div>
        <?php else: ?>
            <?php
            /* Las coordenadas del docente al abrir la clase fijan el centro de
               la geocerca: desde ahi se miden los metros permitidos. */
            ?>
            <form action="<?= $base ?>/docente/clase/abrir" method="POST" class="form-inline-grid"
                  data-geo data-geo-obligatorio>
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <input type="hidden" name="latitud" value="">
                <input type="hidden" name="longitud" value="">
                <input type="hidden" name="radio" value="<?= (int)($radioGeo ?? 500) ?>">
                <div class="form-group mb-0 flex-1">
                    <label for="curso_id" class="form-label">Curso <span class="text-danger">*</span></label>
                    <select id="curso_id" name="curso_id" class="form-select" required>
                        <option value="">-- Selecciona la materia y el ambiente --</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int)$c['id'] ?>">
                                <?= htmlspecialchars($c['materia']) ?> — <?= htmlspecialchars($c['ambiente']) ?>
                                (<?= htmlspecialchars($c['semestre']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-dorado btn-lg">Abrir Clase y Generar QR &rarr;</button>
            </form>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ============================================================
         CLASE ABIERTA: QR de entrada, QR de salida y lista en vivo
         ============================================================ -->
    <div class="clase-cabecera">
        <div>
            <span class="badge badge-info pulse-badge">CLASE EN CURSO</span>
            <h2 class="clase-cabecera-titulo"><?= htmlspecialchars($sesionActiva['materia']) ?></h2>
            <p class="text-muted">
                <?= htmlspecialchars($sesionActiva['ambiente']) ?> &bull;
                <?= htmlspecialchars($sesionActiva['semestre']) ?> &bull;
                Inicio <?= date('H:i', strtotime($sesionActiva['hora_inicio'])) ?>
            </p>
        </div>
        <form action="<?= $base ?>/docente/clase/cerrar" method="POST"
              onsubmit="return confirm('¿Finalizar la clase? Los alumnos presentes quedarán con su hora de salida.');">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <button type="submit" class="btn btn-danger">Finalizar Clase</button>
        </form>
    </div>

    <div class="stats-grid mb-6" data-region="resumen">
        <div class="stat-card"><span class="stat-label">En clase</span><span class="stat-value" id="mEnClase"><?= (int)$resumen['presentes'] ?></span></div>
        <div class="stat-card"><span class="stat-label">Ya salieron</span><span class="stat-value" id="mSalieron"><?= (int)$resumen['salieron'] ?></span></div>
        <div class="stat-card"><span class="stat-label">Salidas anticipadas</span><span class="stat-value" id="mAnticipadas"><?= (int)$resumen['anticipadas'] ?></span></div>
        <div class="stat-card"><span class="stat-label">Total registrados</span><span class="stat-value" id="mTotal"><?= (int)$resumen['total'] ?></span></div>
    </div>

    <div class="qr-doble-grid" data-region="codigos-qr">
        <!-- ---------- QR DE ENTRADA ---------- -->
        <div class="card qr-panel" data-qr="entrada">
            <div class="qr-panel-head">
                <span class="badge badge-success">QR DE ENTRADA</span>
                <?php if ($entradaViva): ?>
                    <span class="qr-cuenta" data-segundos="<?= (int)$segundosEntrada ?>" data-destino="cuentaEntrada">
                        Caduca en <strong id="cuentaEntrada">--:--</strong>
                    </span>
                <?php else: ?>
                    <span class="qr-cuenta caducado">Registro de entrada cerrado</span>
                <?php endif; ?>
            </div>

            <?php if ($entradaViva): ?>
                <div class="qr-code-box"><?= $qrEntrada ?></div>
                <p class="qr-destino" title="Dirección que lleva el QR dentro">
                    Apunta a <code><?= htmlspecialchars(parse_url($urlEntrada, PHP_URL_HOST) ?? '') ?></code>
                </p>
                <div class="access-code-box">
                    <span class="access-code-title">Código manual</span>
                    <strong class="access-code-val"><?= htmlspecialchars($sesionActiva['codigo_entrada']) ?></strong>
                </div>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <button type="button" class="btn btn-primary btn-sm flex-1"
                            onclick="proyectar('entrada')">Modo Proyector</button>
                    <form action="<?= $base ?>/docente/clase/cerrar-entrada" method="POST" class="flex-1"
                          onsubmit="return confirm('¿Cerrar el registro de entrada? La clase seguirá activa.');">
                        <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                        <button type="submit" class="btn btn-outline btn-sm btn-block">Cerrar Entrada</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="qr-caducado-box">
                    <p>El código de entrada ya no está activo.</p>
                    <p class="text-muted">Puedes generar uno nuevo para los alumnos atrasados, o registrarlos manualmente abajo.</p>
                </div>
            <?php endif; ?>

            <form action="<?= $base ?>/docente/clase/renovar" method="POST" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <button type="submit" class="btn btn-outline btn-block btn-sm">
                    Generar QR de entrada nuevo (<?= (int)$minutosQr ?> min)
                </button>
            </form>
        </div>

        <!-- ---------- QR DE SALIDA ---------- -->
        <div class="card qr-panel" data-qr="salida">
            <div class="qr-panel-head">
                <span class="badge badge-danger">QR DE SALIDA</span>
                <?php if ($salidaViva): ?>
                    <span class="qr-cuenta" data-segundos="<?= (int)$segundosSalida ?>" data-destino="cuentaSalida">
                        Caduca en <strong id="cuentaSalida">--:--</strong>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($salidaViva): ?>
                <div class="qr-code-box"><?= $qrSalida ?></div>
                <p class="qr-destino" title="Dirección que lleva el QR dentro">
                    Apunta a <code><?= htmlspecialchars(parse_url($urlSalida, PHP_URL_HOST) ?? '') ?></code>
                </p>
                <div class="access-code-box">
                    <span class="access-code-title">Código manual</span>
                    <strong class="access-code-val"><?= htmlspecialchars($sesionActiva['codigo_salida']) ?></strong>
                </div>
                <button type="button" class="btn btn-primary btn-sm btn-block mt-3" onclick="proyectar('salida')">
                    Modo Proyector
                </button>
            <?php else: ?>
                <div class="qr-caducado-box">
                    <p>Cuando termine la clase, genera el código de salida.</p>
                    <p class="text-muted">
                        Los alumnos lo escanean al retirarse y su salida queda registrada automáticamente.
                    </p>
                </div>
            <?php endif; ?>

            <form action="<?= $base ?>/docente/clase/qr-salida" method="POST" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                <button type="submit" class="btn btn-dorado btn-block">
                    <?= $salidaViva ? 'Renovar QR de Salida' : 'Generar QR de SALIDA' ?> (<?= (int)$minutosQr ?> min)
                </button>
            </form>
        </div>
    </div>

    <!-- ---------- LISTA EN VIVO CON CONTROL TOTAL ---------- -->
    <div class="card mb-6">
        <div class="card-header-flex">
            <div>
                <h3 class="card-titulo mb-0">Estudiantes en la Clase</h3>
                <p class="text-muted" style="font-size:.85rem">Se actualiza automáticamente cada 5 segundos</p>
            </div>
            <div class="d-flex align-center gap-2 flex-wrap">
                <span id="indicadorVivo" class="live-indicator"></span>
                <button type="button" class="btn btn-dorado btn-sm" onclick="abrirModal('modalManual')">
                    + Registrar Manualmente
                </button>
            </div>
        </div>

        <!-- Filtro de seleccion, no de escritura -->
        <?php if (!empty($resumen['pendientes'])): ?>
            <div class="alert alert-warning">
                <span>
                    Hay <strong><?= (int)$resumen['pendientes'] ?></strong> registro(s) de estudiantes
                    que <strong>no están matriculados</strong> en esta materia. Confirma que sí están
                    en el aula y pulsa <strong>Aprobar</strong>, o elimínalos si no corresponden.
                </span>
            </div>
        <?php endif; ?>

        <div class="filtro-rapido">
            <label for="filtroEstado" class="form-label mb-0">Ver:</label>
            <select id="filtroEstado" class="form-select" onchange="filtrarTabla()">
                <option value="">Todos</option>
                <option value="presente">Solo los que están en clase</option>
                <option value="salio">Solo los que ya salieron</option>
                <option value="salida_temprana">Solo salidas anticipadas</option>
                <option value="salida_justificada">Solo salidas justificadas</option>
            </select>
            <span class="text-muted" id="contadorFiltro"></span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover tabla-clase">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Código</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="cuerpoTabla">
                    <?php if (empty($asistencias)): ?>
                        <tr class="fila-vacia"><td colspan="6" class="table-empty">
                            Esperando a que los estudiantes escaneen el código QR…
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($asistencias as $a): ?>
                            <?php $pendiente = (($a['aprobacion'] ?? 'aprobada') === 'pendiente'); ?>
                            <tr data-estado="<?= htmlspecialchars($a['estado']) ?>" class="<?= $pendiente ? 'fila-pendiente' : '' ?>">
                                <td class="font-medium">
                                    <?= htmlspecialchars(trim($a['nombre'] . ' ' . $a['apellido'])) ?>
                                    <?php if ($a['origen'] === 'manual'): ?>
                                        <span class="mini-tag">manual</span>
                                    <?php endif; ?>
                                    <?php if ($pendiente): ?>
                                        <span class="mini-tag tag-pendiente">no matriculado</span>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:.76rem"><?= htmlspecialchars($a['semestre']) ?></div>
                                </td>
                                <td class="table-code"><?= htmlspecialchars($a['codigo']) ?></td>
                                <td><?= date('H:i:s', strtotime($a['hora_entrada'])) ?></td>
                                <td><?= $a['hora_salida'] ? date('H:i:s', strtotime($a['hora_salida'])) : '—' ?></td>
                                <td>
                                    <span class="badge estado-<?= htmlspecialchars($a['estado']) ?>">
                                        <?= htmlspecialchars(Catalogo::etiquetaEstado($a['estado'])) ?>
                                    </span>
                                    <?php if (!empty($a['motivo'])): ?>
                                        <div class="motivo-linea" title="<?= htmlspecialchars((string)$a['motivo_detalle']) ?>">
                                            <?= htmlspecialchars(Catalogo::etiquetaMotivo($a['motivo'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="acciones-fila">
                                        <?php if ($pendiente): ?>
                                            <form action="<?= $base ?>/docente/asistencia/aprobar" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                                <input type="hidden" name="asistencia_id" value="<?= (int)$a['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Confirmar que sí está en el aula y matricularlo">
                                                    Aprobar
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($a['estado'] === 'presente'): ?>
                                            <button type="button" class="btn btn-sm btn-outline"
                                                    onclick="abrirSalida(<?= (int)$a['id'] ?>, '<?= htmlspecialchars(addslashes(trim($a['nombre'] . ' ' . $a['apellido'])), ENT_QUOTES) ?>')">
                                                Marcar Salida
                                            </button>
                                        <?php else: ?>
                                            <form action="<?= $base ?>/docente/asistencia/devolver" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                                <input type="hidden" name="asistencia_id" value="<?= (int)$a['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline">Volvió a clase</button>
                                            </form>
                                        <?php endif; ?>

                                        <form action="<?= $base ?>/docente/asistencia/eliminar" method="POST" class="inline"
                                              onsubmit="return confirm('¿Eliminar el registro de este estudiante? Úsalo cuando la persona NO está en el aula.');">
                                            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                                            <input type="hidden" name="asistencia_id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-peligro-suave">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================================
     MIS CURSOS
     ============================================================ -->
<div class="card mb-6" data-region="cursos">
    <div class="card-header-flex">
        <h3 class="card-titulo mb-0">Mis Cursos</h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="abrirModal('modalCurso')">+ Nuevo Curso</button>
    </div>
    <p class="text-muted mb-4" style="font-size:.86rem">
        Una misma materia puede tener varios cursos según el ambiente: Aula, Laboratorio o Aula Interactiva.
    </p>

    <?php if (empty($cursos)): ?>
        <p class="table-empty">Aún no tienes cursos asignados.</p>
    <?php else: ?>
        <div class="cursos-grid">
            <?php foreach ($cursos as $c): ?>
                <div class="curso-card">
                    <span class="curso-ambiente amb-<?= strtolower(str_replace(' ', '-', $c['ambiente'])) ?>">
                        <?= htmlspecialchars($c['ambiente']) ?>
                    </span>
                    <strong class="curso-materia"><?= htmlspecialchars($c['materia']) ?></strong>
                    <span class="curso-detalle">
                        <?= htmlspecialchars($c['semestre']) ?>
                    </span>
                    <span class="curso-clases"><?= (int)$c['total_clases'] ?> clase(s) dictada(s)</span>
                    <form action="<?= $base ?>/docente/curso/eliminar" method="POST"
                          data-confirmar="¿Archivar este curso? No se borra nada: sale de esta lista pero su historial se conserva y podrás restaurarlo desde &quot;Cursos archivados&quot;.">
                        <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                        <input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-peligro-suave">Archivar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cursosArchivados)): ?>
        <!-- Cursos archivados: siguen guardados, solo salieron de la lista -->
        <details class="archivados" id="cursosArchivados">
            <summary class="archivados-titulo">
                Cursos archivados (<?= count($cursosArchivados) ?>)
            </summary>

            <p class="text-muted mb-3" style="font-size:.84rem">
                No se borraron: sus clases y asistencias siguen apareciendo en
                Reportes. Puedes devolverlos a tu lista cuando quieras.
            </p>

            <div class="cursos-grid">
                <?php foreach ($cursosArchivados as $c): ?>
                    <div class="curso-card curso-archivado">
                        <span class="curso-ambiente amb-<?= strtolower(str_replace(' ', '-', $c['ambiente'])) ?>">
                            <?= htmlspecialchars($c['ambiente']) ?>
                        </span>
                        <strong class="curso-materia"><?= htmlspecialchars($c['materia']) ?></strong>
                        <span class="curso-detalle">
                            <?= htmlspecialchars($c['semestre']) ?>
                        </span>
                        <span class="curso-clases"><?= (int)$c['total_clases'] ?> clase(s) en su historial</span>

                        <form action="<?= $base ?>/docente/curso/restaurar" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
                            <input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline">Restaurar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
</div>

<!-- ============================================================
     ESTADÍSTICAS EN PASTEL
     ============================================================ -->
<div class="pasteles-grid mb-6" data-region="graficos">
    <?php
    $datos = $graficoMateria; $tituloGrafico = 'Asistencias por Materia';
    include dirname(__DIR__) . '/partials/pastel.php';
    ?>
    <?php
    $datos = $graficoEstado;
    // Se traducen las claves internas a etiquetas legibles
    $datos = array_map(static fn($d) => ['etiqueta' => Catalogo::etiquetaEstado($d['etiqueta']), 'total' => $d['total']], $datos);
    $tituloGrafico = 'Asistencias por Estado';
    include dirname(__DIR__) . '/partials/pastel.php';
    ?>
    <?php
    $datos = $graficoAmbiente; $tituloGrafico = 'Asistencias por Ambiente';
    include dirname(__DIR__) . '/partials/pastel.php';
    ?>
</div>

<!-- ============================================================
     HISTORIAL
     ============================================================ -->
<div class="card">
    <h3 class="card-titulo">Últimas Clases Dictadas</h3>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr><th>Fecha</th><th>Materia</th><th>Ambiente</th><th>Horario</th><th>Estado</th></tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="5" class="table-empty">Todavía no has dictado clases.</td></tr>
                <?php else: ?>
                    <?php foreach ($historial as $h): ?>
                        <tr class="fila-clicable" onclick="window.location='<?= $base ?>/reportes/clase?id=<?= (int)$h['id'] ?>'"
                            title="Ver quiénes asistieron a esta clase">
                            <td class="font-semibold"><?= htmlspecialchars($h['fecha']) ?></td>
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

<!-- ============================================================
     MODALES
     ============================================================ -->

<!-- Crear curso -->
<div id="modalCurso" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Nuevo Curso</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalCurso')">&times;</button>
        </div>
        <form action="<?= $base ?>/docente/curso/crear" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">

            <div class="form-group">
                <label for="materia_id" class="form-label">Materia <span class="text-danger">*</span></label>
                <select id="materia_id" name="materia_id" class="form-select" required>
                    <option value="">-- Selecciona la materia --</option>
                    <?php foreach ($materias as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ambiente" class="form-label">Ambiente <span class="text-danger">*</span></label>
                <select id="ambiente" name="ambiente" class="form-select" required>
                    <option value="">-- Selecciona el ambiente --</option>
                    <?php foreach ($ambientes as $amb): ?>
                        <option value="<?= htmlspecialchars($amb, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($amb) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-ayuda">Aula, Laboratorio o Aula Interactiva: cada uno es un curso distinto.</small>
            </div>

            <div class="form-group mb-6">
                <label for="semestre_curso" class="form-label">Semestre <span class="text-danger">*</span></label>
                <select id="semestre_curso" name="semestre" class="form-select" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($semestres as $s): ?>
                        <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCurso')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Curso</button>
            </div>
        </form>
    </div>
</div>

<?php if ($tieneClase): ?>
<!-- Registro manual -->
<div id="modalManual" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Registrar Asistencia Manual</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalManual')">&times;</button>
        </div>
        <p class="text-muted mb-4" style="font-size:.86rem">
            Úsalo cuando un estudiante no pueda escanear el QR (sin celular, sin batería, etc.).
        </p>

        <form action="<?= $base ?>/docente/asistencia/manual" method="POST" id="formManual">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">

            <div class="tabs-identidad" role="tablist">
                <button type="button" class="tab-btn activo" data-modo="padron" onclick="modoManual('padron')" role="tab">De la lista</button>
                <button type="button" class="tab-btn" data-modo="cedula" onclick="modoManual('cedula')" role="tab">Por cédula</button>
                <button type="button" class="tab-btn" data-modo="nuevo" onclick="modoManual('nuevo')" role="tab">Nuevo</button>
            </div>
            <input type="hidden" name="modo" id="modoManualInput" value="padron">

            <div id="manualPadron">
                <div class="form-group mb-6">
                    <label for="estudiante_id" class="form-label">Estudiante <span class="text-danger">*</span></label>
                    <select id="estudiante_id" name="estudiante_id" class="form-select">
                        <option value="">-- Selecciona al estudiante --</option>
                        <?php foreach ($estudiantes as $e): ?>
                            <option value="<?= (int)$e['id'] ?>">
                                <?= htmlspecialchars(trim($e['nombre'] . ' ' . $e['apellido'])) ?>
                                (<?= htmlspecialchars($e['codigo']) ?> — <?= htmlspecialchars($e['semestre']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="manualCedula" hidden>
                <div class="form-group mb-6">
                    <label for="m_cedula" class="form-label">Cédula del estudiante <span class="text-danger">*</span></label>
                    <input type="text" id="m_cedula" name="cedula" class="form-control form-control-code"
                           inputmode="numeric" maxlength="10" placeholder="1701234567"
                           oninput="this.value = this.value.replace(/\D/g,'')">
                    <small class="form-ayuda">
                        Para el alumno que ya está en el padrón pero no aparece con su nombre
                        en la lista, o cuando hay dos estudiantes que se llaman igual.
                    </small>
                </div>
            </div>

            <div id="manualNuevo" hidden>
                <div class="form-fila">
                    <div class="form-group">
                        <label for="m_nombre" class="form-label">Nombres <span class="text-danger">*</span></label>
                        <input type="text" id="m_nombre" name="nombre" class="form-control" maxlength="50" placeholder="Ej: Saul">
                    </div>
                    <div class="form-group">
                        <label for="m_apellido" class="form-label">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" id="m_apellido" name="apellido" class="form-control" maxlength="50" placeholder="Ej: Andrade">
                    </div>
                </div>
                <div class="form-fila">
                    <div class="form-group">
                        <label for="m_semestre" class="form-label">Semestre <span class="text-danger">*</span></label>
                        <select id="m_semestre" name="semestre" class="form-select">
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($semestres as $s): ?>
                                <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="m_cedula_nuevo" class="form-label">Cédula <small class="text-muted">(recomendado)</small></label>
                        <input type="text" id="m_cedula_nuevo" name="cedula" class="form-control form-control-code"
                               inputmode="numeric" maxlength="10" placeholder="1701234567"
                               oninput="this.value = this.value.replace(/\D/g,'')">
                    </div>
                </div>
                <p class="form-ayuda mb-6">
                    Cargar la cédula ahora le permite al alumno registrarse solo aunque olvide su código.
                </p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalManual')">Cancelar</button>
                <button type="submit" class="btn btn-dorado">Registrar Asistencia</button>
            </div>
        </form>
    </div>
</div>

<!-- Marcar salida anticipada -->
<div id="modalSalida" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header-row">
            <h3 class="modal-title mb-0">Salida Anticipada</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarModal('modalSalida')">&times;</button>
        </div>
        <p class="text-muted mb-4" style="font-size:.86rem">
            Estudiante: <strong id="salidaNombre" class="text-primary"></strong>
        </p>

        <form action="<?= $base ?>/docente/asistencia/salida" method="POST" id="formSalida">
            <input type="hidden" name="csrf_token" value="<?= $tok ?>">
            <input type="hidden" name="asistencia_id" id="salidaAsistenciaId">

            <div class="form-group">
                <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                <select id="motivo" name="motivo" class="form-select" required onchange="revisarDetalle()">
                    <option value="">-- Selecciona el motivo --</option>
                    <?php foreach ($motivos as $valor => $etiqueta): ?>
                        <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-6">
                <label for="motivo_detalle" class="form-label">
                    Descripción <span id="detalleObligatorio" class="text-danger" hidden>*</span>
                </label>
                <textarea id="motivo_detalle" name="motivo_detalle" class="form-control" rows="2" maxlength="200"
                          placeholder="Ej: Salida temprana por mal comportamiento en el laboratorio"></textarea>
                <small class="form-ayuda">Opcional, salvo que elijas "Otro". Máximo 200 caracteres.</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSalida')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Registrar Salida</button>
            </div>
        </form>
    </div>
</div>

<!-- Modo proyector -->
<div id="modalProyector" class="modal-proyector-overlay">
    <div class="modal-proyector-card">
        <button type="button" class="modal-proyector-close" onclick="cerrarProyector()">&times;</button>
        <span class="badge badge-info pulse-badge mb-2" id="proyectorTag">SESIÓN ACTIVA</span>
        <h2 class="proyector-materia"><?= htmlspecialchars($sesionActiva['materia']) ?></h2>
        <p class="text-muted mb-2">Escanea el código con la cámara de tu teléfono</p>
        <div id="proyectorQr" class="proyector-qr"></div>
        <div class="access-code-box proyector-codigo">
            <span class="access-code-title">Código manual</span>
            <strong id="proyectorCodigo"></strong>
        </div>
        <p class="text-muted mt-3" style="font-size:.82rem">Presiona <kbd>ESC</kbd> o haz clic fuera para salir.</p>
    </div>
</div>
<?php endif; ?>

<script>
const BASE = '<?= $base ?>';

// ==================== Modales ====================
function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', e => { if (e.target === ov) ov.style.display = 'none'; });
});
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    cerrarProyector();
});

// ==================== Registro manual ====================
// Los tres modos del registro manual. Se vacia el modo que se oculta para que
// no viajen dos identidades distintas en el mismo envio.
const MODOS_MANUAL = {
    padron: { caja: 'manualPadron', campos: ['estudiante_id'] },
    cedula: { caja: 'manualCedula', campos: ['m_cedula'] },
    nuevo:  { caja: 'manualNuevo',  campos: ['m_nombre', 'm_apellido', 'm_semestre', 'm_cedula_nuevo'] }
};

function modoManual(modo) {
    if (!MODOS_MANUAL[modo]) return;

    document.getElementById('modoManualInput').value = modo;

    Object.entries(MODOS_MANUAL).forEach(([nombre, cfg]) => {
        const caja = document.getElementById(cfg.caja);
        if (!caja) return;

        const activo = (nombre === modo);
        caja.hidden = !activo;

        cfg.campos.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            // Los campos del modo oculto se DESHABILITAN, no solo se vacian.
            // Hay dos campos llamados "cedula" (uno por modo) y PHP se queda
            // con el ultimo que le llega: si el oculto viajara vacio, pisaria
            // la cedula que el docente si escribio. Un campo deshabilitado no
            // se envia, asi que solo llega el del modo activo.
            el.disabled = !activo;
            if (!activo) el.value = '';
        });
    });

    document.querySelectorAll('#formManual .tab-btn')
        .forEach(b => b.classList.toggle('activo', b.dataset.modo === modo));
}

/** Misma validacion de cedula que aplica el servidor */
/* La validacion de cedula vive en assets/js/app.js (window.cedulaValida):
   una sola copia para todo el sistema. */

// Estado inicial: solo el modo visible queda habilitado
if (document.getElementById('modoManualInput')) {
    modoManual(document.getElementById('modoManualInput').value || 'padron');
}

const formManual = document.getElementById('formManual');
if (formManual) {
    formManual.addEventListener('submit', e => {
        const modo = document.getElementById('modoManualInput').value;

        if (modo === 'padron') {
            if (!document.getElementById('estudiante_id').value) {
                e.preventDefault();
                alert('Selecciona un estudiante de la lista.');
            }
            return;
        }

        if (modo === 'cedula') {
            const ced = document.getElementById('m_cedula').value.trim();
            if (!cedulaValida(ced)) {
                e.preventDefault();
                alert('La cédula no es válida. Deben ser 10 dígitos correctos.');
            }
            return;
        }

        const cedNueva = document.getElementById('m_cedula_nuevo').value.trim();
        if (cedNueva !== '' && !cedulaValida(cedNueva)) {
            e.preventDefault();
            alert('La cédula no es válida. Bórrala o corrígela.');
            return;
        }

        const soloLetras = /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü][A-Za-zÁÉÍÓÚáéíóúÑñÜü\s'.-]{1,49}$/;
        if (!soloLetras.test(document.getElementById('m_nombre').value.trim())) {
            e.preventDefault(); alert('Escribe el nombre del estudiante (solo letras).'); return;
        }
        if (!soloLetras.test(document.getElementById('m_apellido').value.trim())) {
            e.preventDefault(); alert('Escribe el apellido del estudiante (solo letras).'); return;
        }
        if (!document.getElementById('m_semestre').value) {
            e.preventDefault(); alert('Selecciona el semestre del estudiante.');
        }
    });
}

// ==================== Salida anticipada ====================
function abrirSalida(id, nombre) {
    document.getElementById('salidaAsistenciaId').value = id;
    document.getElementById('salidaNombre').textContent = nombre;
    document.getElementById('motivo').value = '';
    document.getElementById('motivo_detalle').value = '';
    revisarDetalle();
    abrirModal('modalSalida');
}

// El motivo "Otro" obliga a describir: si no, se perderia la razon real de la salida
function revisarDetalle() {
    const motivo = document.getElementById('motivo');
    if (!motivo) return;
    const esOtro = motivo.value === 'Otro';
    document.getElementById('detalleObligatorio').hidden = !esOtro;
    document.getElementById('motivo_detalle').required = esOtro;
}

const formSalida = document.getElementById('formSalida');
if (formSalida) {
    formSalida.addEventListener('submit', e => {
        if (!document.getElementById('motivo').value) {
            e.preventDefault(); alert('Selecciona el motivo de la salida.'); return;
        }
        if (document.getElementById('motivo').value === 'Otro'
            && !document.getElementById('motivo_detalle').value.trim()) {
            e.preventDefault(); alert('Al elegir "Otro" debes describir el motivo.');
        }
    });
}

// ==================== Cuentas regresivas de los QR ====================
//
// Se ejecuta al cargar Y cada vez que la capa AJAX reemplaza una region.
// Sin lo segundo, al refrescar el panel los contadores quedaban en "--:--"
// para siempre y no habia forma de saber si el boton habia hecho algo.
function iniciarCuentasRegresivas() {
    document.querySelectorAll('.qr-cuenta[data-segundos], .clase-cuenta[data-segundos]').forEach(caja => {
        // Marca para no montar dos temporizadores sobre el mismo elemento
        if (caja.dataset.corriendo === '1') return;
        caja.dataset.corriendo = '1';

        let restante = parseInt(caja.dataset.segundos, 10) || 0;
        const destino = caja.dataset.destino ? document.getElementById(caja.dataset.destino) : null;
        if (!destino) return;

        const pintar = () => {
            // Si el elemento ya salio del documento (lo reemplazo un refresco),
            // se detiene el temporizador en vez de seguir corriendo invisible
            if (!caja.isConnected) return;

            if (restante <= 0) {
                caja.classList.add('caducado');
                destino.textContent = 'CADUCADO';
                return;
            }
            const m = String(Math.floor(restante / 60)).padStart(2, '0');
            const s = String(restante % 60).padStart(2, '0');
            destino.textContent = m + ':' + s;
            restante--;
            setTimeout(pintar, 1000);
        };
        pintar();
    });
}

iniciarCuentasRegresivas();
document.addEventListener('regiones:actualizadas', iniciarCuentasRegresivas);

// El bloque de cursos archivados conserva si estaba desplegado: al refrescar
// la region se dibuja de nuevo y, sin esto, se cerraria solo.
document.addEventListener('click', e => {
    if (e.target.closest('#cursosArchivados > summary')) {
        // El estado se lee DESPUES de que el navegador procese el clic
        setTimeout(() => {
            const d = document.getElementById('cursosArchivados');
            if (d) sessionStorage.setItem('archivadosAbierto', d.open ? '1' : '0');
        }, 0);
    }
});

function restituirArchivados() {
    const d = document.getElementById('cursosArchivados');
    if (d && sessionStorage.getItem('archivadosAbierto') === '1') d.open = true;
}
restituirArchivados();
document.addEventListener('regiones:actualizadas', restituirArchivados);

// ==================== Modo proyector ====================
//
// El QR se lee del DOM en el momento de proyectar, NO de una constante
// generada al cargar la pagina.
//
// Antes se guardaba en una constante QRS con el contenido de ese instante. El
// problema: al generar el QR de salida, la capa AJAX reemplaza el panel pero la
// constante se queda con el valor viejo (vacio, porque al cargar aun no existia
// ese codigo). proyectar('salida') encontraba el SVG vacio, se cortaba en
// silencio y el boton "Modo Proyector" parecia no hacer nada.
function proyectar(tipo) {
    const panel = document.querySelector('[data-qr="' + tipo + '"]');
    const svg    = panel?.querySelector('.qr-code-box')?.innerHTML;
    const codigo = panel?.querySelector('.access-code-val')?.textContent.trim();

    if (!svg || !codigo) {
        (window.avisar || alert)(
            'Ese código QR todavía no está activo. Genéralo primero.', 'error'
        );
        return;
    }

    document.getElementById('proyectorQr').innerHTML = svg;
    document.getElementById('proyectorCodigo').textContent = codigo;
    document.getElementById('proyectorTag').textContent =
        (tipo === 'salida') ? 'SALIDA DE CLASE' : 'ENTRADA A CLASE';

    const modal = document.getElementById('modalProyector');
    if (modal) modal.style.display = 'flex';
}

function cerrarProyector() {
    const m = document.getElementById('modalProyector');
    if (m) m.style.display = 'none';
}

document.getElementById('modalProyector')?.addEventListener('click', function (e) {
    if (e.target === this) cerrarProyector();
});

// ==================== Filtro de la tabla ====================
function filtrarTabla() {
    const valor = document.getElementById('filtroEstado').value;
    const filas = document.querySelectorAll('#cuerpoTabla tr[data-estado]');
    let visibles = 0;

    filas.forEach(f => {
        const mostrar = (valor === '' || f.dataset.estado === valor);
        f.hidden = !mostrar;
        if (mostrar) visibles++;
    });

    document.getElementById('contadorFiltro').textContent =
        valor === '' ? '' : visibles + ' estudiante(s)';
}

// ==================== Refresco en vivo ====================
<?php if ($tieneClase): ?>
(function () {
    const cuerpo = document.getElementById('cuerpoTabla');
    const vivo = document.getElementById('indicadorVivo');
    const ETIQUETAS = <?= json_encode(Catalogo::ESTADOS, JSON_UNESCAPED_UNICODE) ?>;
    const MOTIVOS = <?= json_encode(Catalogo::MOTIVOS, JSON_UNESCAPED_UNICODE) ?>;
    const TOKEN = <?= json_encode($csrf) ?>;
    let previos = <?= (int)$resumen['total'] ?>;

    // Las celdas se crean con textContent, nunca con innerHTML: un nombre con
    // < > o comillas no puede inyectar HTML en el panel del docente.
    function celda(texto, clases) {
        const td = document.createElement('td');
        if (clases) td.className = clases;
        td.textContent = texto ?? '';
        return td;
    }

    function botonForm(accion, campos, texto, clase, confirmar) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = BASE + accion;
        f.className = 'inline';
        if (confirmar) f.addEventListener('submit', e => { if (!confirm(confirmar)) e.preventDefault(); });

        Object.entries(campos).forEach(([k, v]) => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = k; i.value = v;
            f.appendChild(i);
        });

        const b = document.createElement('button');
        b.type = 'submit'; b.className = 'btn btn-sm ' + clase; b.textContent = texto;
        f.appendChild(b);
        return f;
    }

    function pintar(lista) {
        if (!lista.length) {
            const tr = document.createElement('tr');
            tr.className = 'fila-vacia';
            const td = celda('Esperando a que los estudiantes escaneen el código QR…', 'table-empty');
            td.colSpan = 6;
            tr.appendChild(td);
            cuerpo.replaceChildren(tr);
            return;
        }

        const filas = lista.map(a => {
            const tr = document.createElement('tr');
            tr.dataset.estado = a.estado;

            // Nombre + etiquetas
            const tdNombre = document.createElement('td');
            tdNombre.className = 'font-medium';
            tdNombre.appendChild(document.createTextNode(a.nombre));
            if (a.origen === 'manual') {
                const tag = document.createElement('span');
                tag.className = 'mini-tag'; tag.textContent = 'manual';
                tdNombre.appendChild(document.createTextNode(' '));
                tdNombre.appendChild(tag);
            }
            const sem = document.createElement('div');
            sem.className = 'text-muted'; sem.style.fontSize = '.76rem';
            sem.textContent = a.semestre || '';
            tdNombre.appendChild(sem);
            tr.appendChild(tdNombre);

            tr.appendChild(celda(a.codigo, 'table-code'));
            tr.appendChild(celda(a.entrada, ''));
            tr.appendChild(celda(a.salida || '—', ''));

            // Estado + motivo
            const tdEstado = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge estado-' + a.estado;
            badge.textContent = ETIQUETAS[a.estado] || a.estado;
            tdEstado.appendChild(badge);
            if (a.motivo) {
                const mot = document.createElement('div');
                mot.className = 'motivo-linea';
                mot.textContent = MOTIVOS[a.motivo] || a.motivo;
                tdEstado.appendChild(mot);
            }
            tr.appendChild(tdEstado);

            // Acciones
            const tdAcc = document.createElement('td');
            tdAcc.className = 'text-right';
            const caja = document.createElement('div');
            caja.className = 'acciones-fila';

            if (a.estado === 'presente') {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'btn btn-sm btn-outline'; b.textContent = 'Marcar Salida';
                b.addEventListener('click', () => abrirSalida(a.id, a.nombre));
                caja.appendChild(b);
            } else {
                caja.appendChild(botonForm('/docente/asistencia/devolver',
                    { csrf_token: TOKEN, asistencia_id: a.id }, 'Volvió a clase', 'btn-outline', null));
            }

            caja.appendChild(botonForm('/docente/asistencia/eliminar',
                { csrf_token: TOKEN, asistencia_id: a.id }, 'Eliminar', 'btn-peligro-suave',
                '¿Eliminar el registro de este estudiante? Úsalo cuando la persona NO está en el aula.'));

            tdAcc.appendChild(caja);
            tr.appendChild(tdAcc);
            return tr;
        });

        cuerpo.replaceChildren(...filas);
        filtrarTabla();
    }

    function pitar() {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            const ctx = new Ctx();
            const osc = ctx.createOscillator(); const g = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(784, ctx.currentTime);
            osc.frequency.setValueAtTime(1046.5, ctx.currentTime + 0.08);
            g.gain.setValueAtTime(0.18, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.22);
            osc.connect(g); g.connect(ctx.destination);
            osc.start(); osc.stop(ctx.currentTime + 0.22);
        } catch (e) {}
    }

    async function refrescar() {
        try {
            if (vivo) vivo.style.opacity = '.3';
            const r = await fetch(BASE + '/api/clase/en-vivo', { headers: { Accept: 'application/json' } });

            // Si caducó la sesión del docente, recargar lleva al login
            if (r.status === 401) { location.reload(); return; }
            if (!r.ok) throw new Error('HTTP ' + r.status);

            const d = await r.json();
            if (vivo) vivo.style.opacity = '1';

            if (!d.success) return;
            if (!d.activa) { location.reload(); return; }

            document.getElementById('mEnClase').textContent = d.resumen.presentes;
            document.getElementById('mSalieron').textContent = d.resumen.salieron;
            document.getElementById('mAnticipadas').textContent = d.resumen.anticipadas;
            document.getElementById('mTotal').textContent = d.resumen.total;

            if (d.resumen.total > previos) pitar();
            previos = d.resumen.total;

            pintar(d.asistencias);
        } catch (e) {
            if (vivo) vivo.style.opacity = '1';
            console.error('No se pudo refrescar la lista:', e);
        }
    }

    setInterval(refrescar, 5000);
})();
<?php endif; ?>
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
