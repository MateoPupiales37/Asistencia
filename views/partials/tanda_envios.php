<?php
/**
 * Lista de envíos por WhatsApp lista para disparar.
 *
 * Variables esperadas:
 *   $listos       filas con nombre, telefono, mensaje, enlace
 *   $sinTelefono  filas a las que les falta el número
 *   $tituloTanda  encabezado del bloque
 *
 * Por qué es así y no un botón de "enviar todo": WhatsApp no permite el envío
 * automático sin su API de negocios, que exige empresa verificada y cobra por
 * mensaje. Lo que sí se puede es dejar cada mensaje escrito y a un clic.
 */

$listos       = $listos       ?? [];
$sinTelefono  = $sinTelefono  ?? [];
$tituloTanda  = $tituloTanda  ?? 'Envíos preparados';
?>

<div class="card mb-6" data-region="tanda">
    <div class="card-header-flex">
        <h2 class="card-titulo mb-0"><?= htmlspecialchars($tituloTanda) ?></h2>
        <span class="badge <?= empty($listos) ? 'badge-neutral' : 'badge-success' ?>">
            <?= count($listos) ?> listo(s)
        </span>
    </div>

    <?php if (empty($listos) && empty($sinTelefono)): ?>
        <div class="estado-vacio">
            <p class="estado-vacio-titulo">No hay a quién enviar</p>
            <p class="text-muted">Cambia el filtro o el curso para ver otra lista.</p>
        </div>
    <?php else: ?>

        <?php if (!empty($listos)): ?>
            <div class="alert alert-info">
                <span>
                    WhatsApp no deja enviar en masa sin su API de pago, así que cada
                    mensaje se abre con un clic, pero ya viene escrito. Usa
                    <strong>Abrir los siguientes 5</strong> para ir en tandas, o
                    <strong>Copiar todos</strong> si prefieres pegarlos en otra herramienta.
                </span>
            </div>

            <div class="d-flex gap-2 flex-wrap mb-4">
                <button type="button" class="btn btn-success" onclick="abrirTanda(5)">
                    Abrir los siguientes 5
                </button>
                <button type="button" class="btn btn-outline" onclick="copiarTodos()">
                    Copiar todos los mensajes
                </button>
                <button type="button" class="btn btn-outline" onclick="descargarTanda()">
                    Descargar lista
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="reiniciarTanda()">
                    Reiniciar marcas
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="tablaTanda">
                    <thead>
                        <tr>
                            <th>Destinatario</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th class="text-right">Enviar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listos as $i => $p): ?>
                            <tr data-indice="<?= $i ?>">
                                <td class="font-medium"><?= htmlspecialchars($p['nombre']) ?></td>
                                <td class="table-code"><?= htmlspecialchars((string)$p['telefono']) ?></td>
                                <td>
                                    <span class="badge badge-neutral marca-envio">Sin abrir</span>
                                </td>
                                <td class="text-right">
                                    <a href="<?= htmlspecialchars($p['enlace'], ENT_QUOTES, 'UTF-8') ?>"
                                       target="_blank" rel="noopener"
                                       class="btn btn-sm btn-success enlace-wa"
                                       onclick="marcarEnviado(this)">
                                        WhatsApp
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($sinTelefono)): ?>
            <div class="alert alert-warning mt-4">
                <span>
                    <strong><?= count($sinTelefono) ?></strong> sin teléfono cargado.
                    A estos no se les puede enviar nada hasta que se les registre el número:
                    <?= htmlspecialchars(implode(', ', array_map(
                        static fn($p) => $p['nombre'],
                        array_slice($sinTelefono, 0, 15)
                    ))) ?><?= count($sinTelefono) > 15 ? '…' : '' ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Los mensajes viajan a JavaScript ya armados desde PHP
const TANDA = <?= json_encode(array_map(static fn($p) => [
    'nombre'   => $p['nombre'],
    'telefono' => $p['telefono'],
    'mensaje'  => $p['mensaje'],
    'enlace'   => $p['enlace'],
], $listos), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

/** Marca visualmente a quién ya se le abrió el chat, para no repetir ni saltarse a nadie */
function marcarEnviado(enlace) {
    const fila = enlace.closest('tr');
    fila.classList.add('ya-enviado');
    const marca = fila.querySelector('.marca-envio');
    marca.textContent = 'Abierto';
    marca.className = 'badge badge-success marca-envio';
}

/**
 * Abre los siguientes pendientes en pestañas nuevas.
 * De cinco en cinco porque los navegadores bloquean si se abren muchas de
 * golpe, y porque hay que darle tiempo a WhatsApp Web a cargar cada chat.
 */
function abrirTanda(cuantos) {
    const pendientes = [...document.querySelectorAll('#tablaTanda tbody tr:not(.ya-enviado)')].slice(0, cuantos);

    if (!pendientes.length) {
        (window.avisar || alert)('Ya se abrieron todos los mensajes de esta lista.', 'ok');
        return;
    }

    pendientes.forEach((fila, i) => {
        const enlace = fila.querySelector('.enlace-wa');
        // Se escalonan para que el navegador no los tome por ventanas emergentes
        setTimeout(() => {
            window.open(enlace.href, '_blank', 'noopener');
            marcarEnviado(enlace);
        }, i * 600);
    });
}

function copiarTodos() {
    const texto = TANDA.map(p => `--- ${p.nombre} (${p.telefono}) ---\n${p.mensaje}`).join('\n\n');
    navigator.clipboard?.writeText(texto)
        .then(() => (window.avisar || alert)(TANDA.length + ' mensaje(s) copiados.', 'ok'))
        .catch(() => (window.avisar || alert)('El navegador bloqueó el portapapeles.', 'error'));
}

function descargarTanda() {
    const filas = [['nombre', 'telefono', 'mensaje', 'enlace']]
        .concat(TANDA.map(p => [p.nombre, p.telefono, p.mensaje, p.enlace]));

    // Se entrecomilla y se duplican las comillas internas, que es como manda el CSV
    const csv = filas.map(f => f.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(';')).join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'envios_whatsapp.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

function reiniciarTanda() {
    document.querySelectorAll('#tablaTanda tbody tr').forEach(fila => {
        fila.classList.remove('ya-enviado');
        const marca = fila.querySelector('.marca-envio');
        marca.textContent = 'Sin abrir';
        marca.className = 'badge badge-neutral marca-envio';
    });
}
</script>
