<?php
$titulo = 'Registrar Asistencia - ISTPET';
$ocultarNavbar = true;
require dirname(__DIR__) . '/layouts/header.php';

$hayClase   = !empty($sesion) && !empty($sesion['vigente']) && $sesion['estado'] === 'abierta';
$tipo       = $sesion['tipo_qr'] ?? 'entrada';
$esSalida   = $hayClase && $tipo === 'salida';
$segundos   = $hayClase ? max(0, strtotime($sesion['expira_en']) - time()) : 0;
?>

<div class="auth-page-bg">
    <div class="auth-card auth-card-ancha">
        <div class="auth-top-bar">
            <a href="<?= $base ?>/" class="auth-top-back">&larr; Inicio</a>
        </div>

        <div class="auth-logo-wrap">
            <img src="<?= $base ?>/assets/img/logo-istpet.jpg" alt="Logo ISTPET" class="auth-logo-img">
            <h2 class="auth-title"><?= $esSalida ? 'Registrar Salida' : 'Registrar Asistencia' ?></h2>
            <p class="auth-subtitle">
                <?= $esSalida
                    ? 'Confirma que estás saliendo de la clase'
                    : 'Escanea el código QR de tu clase y completa tus datos' ?>
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <?php if ($hayClase): ?>
            <!-- Clase detectada correctamente -->
            <div class="clase-detectada <?= $esSalida ? 'es-salida' : '' ?>">
                <span class="clase-tag"><?= $esSalida ? 'CÓDIGO DE SALIDA' : 'CÓDIGO DE ENTRADA' ?></span>
                <strong class="clase-materia"><?= htmlspecialchars($sesion['materia']) ?></strong>
                <span class="clase-detalle">
                    <?= htmlspecialchars($sesion['ambiente']) ?> &bull;
                    <?= htmlspecialchars($sesion['semestre']) ?>
                </span>
                <span class="clase-detalle">
                    Docente: <?= htmlspecialchars(trim($sesion['docente_nombre'] . ' ' . $sesion['docente_apellido'])) ?>
                </span>
                <div class="clase-cuenta" data-segundos="<?= (int)$segundos ?>">
                    Este código caduca en <strong id="cuentaRegresiva">--:--</strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- =========== ESCANER (funciona en celular y en laptop) =========== -->
        <div class="escaner-bloque">
            <button type="button" id="btnCamara" onclick="alternarCamara()" class="btn btn-outline btn-block btn-escaner">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                <span id="btnCamaraTexto">Escanear código QR con la cámara</span>
            </button>

            <?php
            /* Respaldo cuando la camara en vivo no esta disponible. Solo aparece
               si hace falta, y lo abre el usuario: nunca se dispara solo. */
            ?>
            <input type="file" id="fotoQR" accept="image/*" capture="environment" hidden onchange="leerFoto(event)">

            <div id="respaldoFoto" class="respaldo-foto" hidden>
                <button type="button" class="btn btn-outline btn-block"
                        onclick="document.getElementById('fotoQR').click()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Subir una foto del código QR
                </button>
                <small class="form-ayuda">
                    También puedes escribir el código a mano en el campo de abajo.
                </small>
            </div>

            <div id="visorCamara" class="camera-scanner-card" hidden>
                <div class="camera-viewport-wrapper">
                    <video id="video" playsinline autoplay muted></video>
                    <canvas id="lienzo" hidden></canvas>
                    <div class="scanner-laser-overlay">
                        <div class="scanner-frame-corner top-left"></div>
                        <div class="scanner-frame-corner top-right"></div>
                        <div class="scanner-frame-corner bottom-left"></div>
                        <div class="scanner-frame-corner bottom-right"></div>
                        <div class="scanner-scan-bar"></div>
                    </div>
                </div>
                <div class="escaner-pie">
                    <span id="estadoEscaner">Apunta la cámara al código QR…</span>
                    <button type="button" onclick="detenerCamara()" class="btn btn-sm btn-outline-claro">Cerrar</button>
                </div>
            </div>

            <div id="avisoQR" class="alert" hidden></div>
        </div>

        <!-- =========== FORMULARIO =========== -->
        <?php
        /* data-geo hace que geo.js pida la ubicacion antes de enviar: es lo que
           impide registrarse desde fuera del aula con una foto del QR. */
        ?>
        <form action="<?= $base ?>/asistencia" method="POST" id="formAsistencia" data-sin-ajax
              data-geo data-geo-obligatorio>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="latitud" value="">
            <input type="hidden" name="longitud" value="">
            <input type="hidden" name="carnet" id="carnet"
                   value="<?= htmlspecialchars($estudiante['token_qr'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="codigo" class="form-label">Código de la Clase <span class="text-danger">*</span></label>
                <input type="text" id="codigo" name="codigo" class="form-control form-control-code"
                       value="<?= htmlspecialchars($codigo ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required maxlength="8" minlength="8"
                       pattern="[A-Fa-f0-9]{8}"
                       title="8 caracteres: letras de la A a la F y números"
                       placeholder="EJ: A1B2C3D4"
                       inputmode="latin" autocapitalize="characters" spellcheck="false"
                       oninput="this.value = this.value.toUpperCase().replace(/[^A-F0-9]/g,'')"
                       <?= empty($codigo) ? 'autofocus' : '' ?>>
                <small class="form-ayuda">Escanéalo con la cámara o escríbelo tal como lo proyecta el docente.</small>
            </div>

            <?php if (!empty($estudiante)): ?>
                <!-- El alumno llego con su carnet QR personal: ya esta identificado -->
                <div class="carnet-detectado">
                    <span class="carnet-tag">CARNET RECONOCIDO</span>
                    <strong><?= htmlspecialchars(trim($estudiante['nombre'] . ' ' . $estudiante['apellido'])) ?></strong>
                    <span><?= htmlspecialchars($estudiante['codigo']) ?> &bull; <?= htmlspecialchars($estudiante['semestre']) ?></span>
                    <a href="<?= $base ?>/asistencia<?= !empty($codigo) ? '?c=' . urlencode($codigo) : '' ?>" class="carnet-cambiar">No soy yo</a>
                </div>
            <?php else: ?>
                <div class="separador-o"><span>Identifícate</span></div>

                <?php
                /* Solo dos formas de identificarse, y ambas exigen que el alumno
                   ya este matriculado. El comentario va en PHP y no en HTML para
                   que no viaje al navegador: al usuario final no le aporta nada. */
                ?>
                <div class="tabs-identidad" role="tablist">
                    <button type="button" class="tab-btn activo" data-modo="cedula" onclick="cambiarModo('cedula')" role="tab" aria-selected="true">Mi cédula</button>
                    <button type="button" class="tab-btn" data-modo="codigo" onclick="cambiarModo('codigo')" role="tab" aria-selected="false">Mi código</button>
                </div>

                <div id="modoCedula" class="modo-identidad">
                    <div class="form-group mb-6">
                        <label for="cedula" class="form-label">Número de Cédula <span class="text-danger">*</span></label>
                        <input type="text" id="cedula" name="cedula"
                               class="form-control form-control-code"
                               inputmode="numeric" maxlength="10" placeholder="1701234567"
                               autocomplete="off" spellcheck="false"
                               oninput="this.value = this.value.replace(/\D/g,'')">
                        <small class="form-ayuda">
                            Los 10 dígitos de tu cédula. Es la forma más segura de identificarte
                            si no recuerdas tu código de estudiante.
                        </small>
                    </div>
                </div>

                <div id="modoCodigo" class="modo-identidad" hidden>
                    <div class="form-group mb-6">
                        <label for="codigo_estudiante" class="form-label">Tu Código de Estudiante</label>
                        <input type="text" id="codigo_estudiante" name="codigo_estudiante"
                               class="form-control form-control-code"
                               maxlength="15" placeholder="Ej: EST001"
                               autocapitalize="characters" spellcheck="false"
                               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_-]/g,'')">
                        <small class="form-ayuda">El código que aparece en tu carnet institucional.</small>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            /* Consentimiento informado: la ubicacion y la camara son datos
               personales, asi que no se activan hasta que el alumno acepta. */
            ?>
            <label class="consentimiento" for="acepto">
                <input type="checkbox" id="acepto" name="acepto" value="1" required>
                <span>
                    Acepto que el sistema use mi <strong>ubicación</strong> para comprobar que estoy
                    en el aula y mi <strong>cámara</strong> para leer el código QR.
                    <a href="<?= $base ?>/terminos" target="_blank" rel="noopener">Ver términos</a>
                </span>
            </label>

            <button type="submit" class="btn <?= $esSalida ? 'btn-danger' : 'btn-dorado' ?> btn-block btn-lg">
                <?= $esSalida ? 'Confirmar Mi Salida' : 'Confirmar Mi Asistencia' ?> &rarr;
            </button>
        </form>
    </div>
</div>

<script src="<?= $base ?>/assets/js/jsqr.js"></script>
<script>
// ====================================================================
// Cuenta regresiva del codigo QR
// ====================================================================
(function () {
    const caja = document.querySelector('.clase-cuenta');
    if (!caja) return;

    let restante = parseInt(caja.dataset.segundos, 10) || 0;
    const salida = document.getElementById('cuentaRegresiva');

    const pintar = () => {
        if (restante <= 0) {
            caja.classList.add('caducado');
            salida.textContent = 'CADUCADO';
            return;
        }
        const m = String(Math.floor(restante / 60)).padStart(2, '0');
        const s = String(restante % 60).padStart(2, '0');
        salida.textContent = m + ':' + s;
        restante--;
        setTimeout(pintar, 1000);
    };
    pintar();
})();

// ====================================================================
// Pestañas de identificacion
// ====================================================================
// Las dos formas de identificarse. Los campos del modo que se oculta se
// vacian: si viajaran juntos, el servidor tomaria el de mayor prioridad y el
// alumno terminaria registrado como otra persona sin darse cuenta.
const MODOS = {
    cedula: { caja: 'modoCedula', campos: ['cedula'] },
    codigo: { caja: 'modoCodigo', campos: ['codigo_estudiante'] }
};

function cambiarModo(modo) {
    if (!MODOS[modo]) return;

    Object.entries(MODOS).forEach(([nombre, cfg]) => {
        const caja = document.getElementById(cfg.caja);
        if (!caja) return;

        const activo = (nombre === modo);
        caja.hidden = !activo;

        cfg.campos.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            // Se DESHABILITAN los campos del modo oculto: un campo oculto
            // pero habilitado igual viaja en el POST y puede pisar al que
            // el alumno si lleno.
            el.disabled = !activo;
            if (!activo) el.value = '';
        });
    });

    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.toggle('activo', b.dataset.modo === modo);
        b.setAttribute('aria-selected', b.dataset.modo === modo ? 'true' : 'false');
    });
}

// Al cargar, se deja habilitado unicamente el modo visible
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('modoCedula')) cambiarModo('cedula');
});

/** Valida una cedula ecuatoriana igual que el servidor, para avisar antes de enviar */
function cedulaValidaLocal(cedula) {
    if (!/^\d{10}$/.test(cedula)) return false;

    const provincia = parseInt(cedula.slice(0, 2), 10);
    if ((provincia < 1 || provincia > 24) && provincia !== 30) return false;
    if (parseInt(cedula[2], 10) > 5) return false;

    const coef = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    let suma = 0;
    for (let i = 0; i < 9; i++) {
        let p = parseInt(cedula[i], 10) * coef[i];
        suma += (p > 9) ? p - 9 : p;
    }
    return ((10 - (suma % 10)) % 10) === parseInt(cedula[9], 10);
}

// ====================================================================
// Validacion antes de enviar
// ====================================================================
document.getElementById('formAsistencia').addEventListener('submit', function (e) {
    const codigo = document.getElementById('codigo').value.trim();

    if (!/^[A-F0-9]{8}$/.test(codigo)) {
        e.preventDefault();
        avisoEscaner('error', 'El código de la clase debe tener 8 caracteres (letras A-F y números).');
        document.getElementById('codigo').focus();
        return;
    }

    // Si el alumno vino con su carnet QR ya esta identificado
    if (document.getElementById('carnet').value) return;

    const visible = id => {
        const caja = document.getElementById(id);
        return caja && !caja.hidden;
    };

    // --- Identificacion por cedula ---
    if (visible('modoCedula')) {
        const ced = document.getElementById('cedula').value.trim();

        if (!ced) {
            e.preventDefault();
            avisoEscaner('error', 'Escribe tu número de cédula, o usa otra de las pestañas.');
            document.getElementById('cedula').focus();
            return;
        }
        if (!cedulaValidaLocal(ced)) {
            e.preventDefault();
            avisoEscaner('error', 'Esa cédula no es válida. Revisa que sean los 10 dígitos correctos.');
            document.getElementById('cedula').focus();
            return;
        }
        return;
    }

    // --- Identificacion por codigo institucional ---
    if (visible('modoCodigo')) {
        const cod = document.getElementById('codigo_estudiante').value.trim();
        if (cod.length < 3) {
            e.preventDefault();
            avisoEscaner('error', 'Escribe tu código de estudiante (mínimo 3 caracteres).');
            document.getElementById('codigo_estudiante').focus();
            return;
        }
        return;
    }

    // Si no hay ninguna pestana visible, el formulario no puede continuar
    e.preventDefault();
    avisoEscaner('error', 'Elige cómo quieres identificarte: con tu cédula o con tu código.');
});

// ====================================================================
// Escaner QR: camara en vivo (laptop y celular) con respaldo por foto
// ====================================================================
let stream = null;
let bucle = null;
let activa = false;

const visor = document.getElementById('visorCamara');
const video = document.getElementById('video');
const lienzo = document.getElementById('lienzo');
const btnTexto = document.getElementById('btnCamaraTexto');
const estado = document.getElementById('estadoEscaner');

function avisoEscaner(tipo, texto) {
    const caja = document.getElementById('avisoQR');
    caja.hidden = false;
    caja.className = 'alert ' + (tipo === 'ok' ? 'alert-success' : tipo === 'info' ? 'alert-info' : 'alert-error');
    caja.textContent = texto;
}

function ocultarAvisoEscaner() {
    const caja = document.getElementById('avisoQR');
    if (caja) caja.hidden = true;
}

function pitar() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gan = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.12);
        gan.gain.setValueAtTime(0.2, ctx.currentTime);
        gan.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
        osc.connect(gan); gan.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime + 0.15);
    } catch (e) { /* el navegador puede bloquear el audio sin interaccion previa */ }
}

/**
 * La camara no se abre hasta que el alumno acepta los terminos: son datos
 * personales y el permiso tiene que ser previo y expreso, no darse por hecho.
 */
function consentimientoDado() {
    var casilla = document.getElementById('acepto');
    return !casilla || casilla.checked;
}

function alternarCamara() {
    if (!activa && !consentimientoDado()) {
        avisoEscaner('info', 'Primero acepta el uso de la cámara y la ubicación (la casilla de abajo).');
        var casilla = document.getElementById('acepto');
        if (casilla) {
            casilla.focus();
            casilla.closest('.consentimiento')?.classList.add('resaltado');
            setTimeout(function () {
                casilla.closest('.consentimiento')?.classList.remove('resaltado');
            }, 2200);
        }
        return;
    }

    activa ? detenerCamara() : iniciarCamara();
}

// Al aceptar, se retira el aviso y el boton de la camara queda habilitado
document.addEventListener('DOMContentLoaded', function () {
    var casilla = document.getElementById('acepto');
    var boton = document.getElementById('btnCamara');
    if (!casilla || !boton) return;

    var sincronizar = function () {
        boton.classList.toggle('btn-bloqueado', !casilla.checked);
        boton.title = casilla.checked
            ? 'Abrir la cámara para leer el código QR'
            : 'Acepta los términos para poder usar la cámara';
        if (casilla.checked) ocultarAvisoEscaner();
    };

    casilla.addEventListener('change', sincronizar);
    sincronizar();
});

/**
 * Muestra el respaldo (subir una foto del QR) SIN abrirlo solo.
 *
 * Antes, cuando la cámara fallaba, se hacía click() sobre el input de archivo
 * automáticamente: al usuario le aparecía de golpe el explorador con todas sus
 * fotos, sin entender por qué. Ahora se explica qué pasó y se le deja el botón
 * para que lo pulse él si quiere.
 */
function ofrecerFoto(motivo) {
    avisoEscaner('error', motivo);
    var respaldo = document.getElementById('respaldoFoto');
    if (respaldo) respaldo.hidden = false;
}

async function iniciarCamara() {
    // La cámara en vivo solo funciona en páginas seguras: HTTPS o localhost.
    // Por http://192.168.x.x el navegador la niega sin preguntar siquiera.
    if (!window.isSecureContext) {
        ofrecerFoto(
            'Esta página no se abrió por HTTPS, así que el navegador bloquea la cámara. '
            + 'Puedes subir una foto del código QR o escribir el código a mano.'
        );
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        ofrecerFoto('Tu navegador no permite usar la cámara. Sube una foto del código QR o escríbelo a mano.');
        return;
    }

    try {
        estado.textContent = 'Solicitando acceso a la cámara…';
        visor.hidden = false;

        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false
        });

        video.srcObject = stream;
        await video.play();

        activa = true;
        btnTexto.textContent = 'Detener cámara';
        estado.textContent = 'Apunta la cámara al código QR…';
        ocultarAvisoEscaner();
        bucle = requestAnimationFrame(analizarCuadro);
    } catch (err) {
        visor.hidden = true;
        activa = false;
        btnTexto.textContent = 'Escanear código QR con la cámara';

        // Cada motivo se explica distinto: "no se pudo" no le sirve a nadie
        var nombre = (err && err.name) ? err.name : '';
        var mensaje;

        if (nombre === 'NotAllowedError' || nombre === 'SecurityError') {
            mensaje = 'Bloqueaste el permiso de la cámara. Ábrelo desde el candado de la barra '
                    + 'de direcciones, o sube una foto del código QR.';
        } else if (nombre === 'NotFoundError' || nombre === 'DevicesNotFoundError') {
            mensaje = 'Este dispositivo no tiene cámara disponible. Sube una foto del código QR '
                    + 'o escribe el código a mano.';
        } else if (nombre === 'NotReadableError') {
            mensaje = 'Otra aplicación está usando la cámara. Ciérrala e inténtalo de nuevo.';
        } else {
            mensaje = 'No se pudo abrir la cámara. Sube una foto del código QR o escríbelo a mano.';
        }

        ofrecerFoto(mensaje);
    }
}

function detenerCamara() {
    if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
    }
    if (bucle) {
        cancelAnimationFrame(bucle);
        bucle = null;
    }
    video.srcObject = null;
    visor.hidden = true;
    activa = false;
    btnTexto.textContent = 'Escanear código QR con la cámara';
}

async function analizarCuadro() {
    if (!activa) return;

    if (video.readyState !== video.HAVE_ENOUGH_DATA) {
        bucle = requestAnimationFrame(analizarCuadro);
        return;
    }

    const leido = await decodificar(video, video.videoWidth, video.videoHeight);

    if (leido && procesarTexto(leido)) return;   // Exito: se detiene el bucle

    bucle = requestAnimationFrame(analizarCuadro);
}

function leerFoto(evento) {
    const archivo = evento.target.files && evento.target.files[0];
    if (!archivo) return;

    avisoEscaner('info', 'Procesando la foto…');

    const lector = new FileReader();
    lector.onload = e => {
        const img = new Image();
        img.onload = async () => {
            // Las fotos del celular son enormes; se reducen para decodificar rapido
            const MAX = 900;
            let w = img.width, h = img.height;
            if (w > MAX || h > MAX) {
                if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
                else { w = Math.round(w * MAX / h); h = MAX; }
            }
            const leido = await decodificar(img, w, h);
            if (!leido || !procesarTexto(leido)) {
                avisoEscaner('error', 'No se pudo leer el código en la foto. Acércate más o escribe el código a mano.');
            }
        };
        img.src = e.target.result;
    };
    lector.readAsDataURL(archivo);
}

/** Decodifica un QR desde un <video> o una <img> usando el canvas */
async function decodificar(fuente, ancho, alto) {
    if (!ancho || !alto) return null;

    lienzo.width = ancho;
    lienzo.height = alto;
    const ctx = lienzo.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(fuente, 0, 0, ancho, alto);

    // 1) Detector nativo del navegador, cuando existe (mas rapido)
    if ('BarcodeDetector' in window) {
        try {
            const det = new BarcodeDetector({ formats: ['qr_code'] });
            const cods = await det.detect(lienzo);
            if (cods && cods.length) return cods[0].rawValue;
        } catch (e) { /* se sigue con jsQR */ }
    }

    // 2) jsQR sobre el canvas: funciona en cualquier navegador
    if (typeof jsQR !== 'undefined') {
        const datos = ctx.getImageData(0, 0, ancho, alto);
        const r = jsQR(datos.data, datos.width, datos.height, { inversionAttempts: 'attemptBoth' });
        if (r && r.data) return r.data;
    }

    return null;
}

/**
 * Interpreta lo que se leyo del QR. Puede ser:
 *   - la URL de la clase  (…/asistencia?c=A1B2C3D4)
 *   - la URL de un carnet (…/asistencia?carnet=<32 hex>)
 *   - el código suelto de la clase
 * Devuelve true si se reconocio algo util.
 */
function procesarTexto(texto) {
    const limpio = String(texto).trim();

    // Carnet personal del estudiante
    const carnet = limpio.match(/[?&]carnet=([a-f0-9]{32})/i);
    if (carnet) {
        pitar();
        detenerCamara();
        // Se recarga con el carnet para que el servidor identifique al alumno
        const cod = document.getElementById('codigo').value.trim();
        window.location.href = '<?= $base ?>/asistencia?carnet=' + carnet[1].toLowerCase()
            + (cod ? '&c=' + encodeURIComponent(cod) : '');
        return true;
    }

    // Código de la clase dentro de la URL del QR
    let codigo = '';
    const enUrl = limpio.match(/[?&]c=([A-Fa-f0-9]{8})(?![A-Fa-f0-9])/);
    if (enUrl) {
        codigo = enUrl[1];
    } else if (/^[A-Fa-f0-9]{8}$/.test(limpio)) {
        codigo = limpio;   // El QR trae solo el código
    }

    if (!codigo) return false;

    pitar();
    detenerCamara();
    const campo = document.getElementById('codigo');
    campo.value = codigo.toUpperCase();
    avisoEscaner('ok', 'Código detectado: ' + codigo.toUpperCase());

    // Se recarga para que el servidor confirme la clase y muestre sus datos
    window.location.href = '<?= $base ?>/asistencia?c=' + encodeURIComponent(codigo.toUpperCase());
    return true;
}
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
