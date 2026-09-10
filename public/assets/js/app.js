/* ==========================================================================
   Capa AJAX del sistema de asistencia - ISTPET

   Que resuelve
   ------------
   Antes, cualquier accion (abrir la clase, registrar a un alumno, marcar una
   salida, filtrar un reporte) enviaba el formulario y RECARGABA la pagina
   entera: la pantalla parpadeaba, se perdia la posicion del scroll y el
   docente tenia que volver a bajar hasta donde estaba.

   Ahora el formulario se envia por detras y solo se vuelve a dibujar el
   pedazo que cambio.

   Como esta pensado
   -----------------
   El truco esta en las REGIONES. Cada bloque de la pagina que puede cambiar
   lleva un atributo data-region="algo" en su HTML. Al terminar una accion, el
   sistema pide la pagina de nuevo y reemplaza unicamente esos bloques.

   Se hace asi, y no reemplazando la pagina completa, por un motivo concreto:
   las vistas llevan sus propios <script> con funciones que usan los onclick
   del HTML (abrirModal, modoManual, proyectar...). Si se reemplazara todo, el
   navegador volveria a ejecutar esos scripts y reventaria al redeclarar sus
   constantes. Las regiones contienen solo marcado, nunca scripts, asi que el
   intercambio es seguro y las funciones de la vista siguen vivas.

   Si la accion cambia la pantalla de forma estructural (por ejemplo abrir una
   clase, que convierte "no tienes clase" en el panel completo), las regiones
   ya no coinciden: en ese caso se navega normalmente, que es lo correcto.
   ========================================================================== */

(function () {
    'use strict';

    const AJAX = { 'X-Requested-With': 'XMLHttpRequest' };

    // ======================================================================
    // Avisos flotantes
    // ======================================================================

    let contenedorAvisos = null;

    function contenedor() {
        if (!contenedorAvisos) {
            contenedorAvisos = document.createElement('div');
            contenedorAvisos.className = 'avisos-flotantes';
            contenedorAvisos.setAttribute('role', 'status');
            contenedorAvisos.setAttribute('aria-live', 'polite');
            document.body.appendChild(contenedorAvisos);
        }
        return contenedorAvisos;
    }

    /** tipo: 'ok' | 'error' | 'info' */
    function avisar(texto, tipo) {
        const aviso = document.createElement('div');
        aviso.className = 'aviso aviso-' + (tipo || 'info');
        aviso.textContent = texto;

        const cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'aviso-cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar aviso');
        cerrar.textContent = '×';
        cerrar.onclick = () => quitar(aviso);
        aviso.appendChild(cerrar);

        contenedor().appendChild(aviso);
        requestAnimationFrame(() => aviso.classList.add('visible'));

        // Los errores se quedan mas tiempo: hay que poder leerlos y entenderlos
        setTimeout(() => quitar(aviso), tipo === 'error' ? 7000 : 4000);
        return aviso;
    }

    function quitar(aviso) {
        if (!aviso || !aviso.parentNode) return;
        aviso.classList.remove('visible');
        setTimeout(() => aviso.remove(), 250);
    }

    // Se expone para que las vistas puedan avisar sin depender de alert()
    window.avisar = avisar;

    // ======================================================================
    // Indicador de "trabajando"
    // ======================================================================

    function ocupar(boton, ocupado) {
        if (!boton) return;

        if (ocupado) {
            boton.dataset.textoOriginal = boton.innerHTML;
            boton.disabled = true;
            boton.classList.add('cargando');
        } else {
            if (boton.dataset.textoOriginal) {
                boton.innerHTML = boton.dataset.textoOriginal;
                delete boton.dataset.textoOriginal;
            }
            boton.disabled = false;
            boton.classList.remove('cargando');
        }
    }

    // ======================================================================
    // Intercambio de regiones
    // ======================================================================

    /**
     * Vuelve a pedir la pagina y reemplaza cada bloque marcado con data-region.
     * Devuelve false si la pantalla cambio de forma y hay que navegar de verdad.
     */
    async function refrescarRegiones(url) {
        const regiones = document.querySelectorAll('[data-region]');
        if (!regiones.length) return false;

        const respuesta = await fetch(url, { headers: AJAX, credentials: 'same-origin' });
        if (!respuesta.ok) return false;

        const doc = new DOMParser().parseFromString(await respuesta.text(), 'text/html');

        // Si la vista es otra, las regiones no se corresponden: hay que navegar
        const vistaActual = document.querySelector('[data-vista]')?.dataset.vista;
        const vistaNueva  = doc.querySelector('[data-vista]')?.dataset.vista;
        if (vistaActual && vistaNueva && vistaActual !== vistaNueva) return false;

        let reemplazadas = 0;

        regiones.forEach(vieja => {
            const nueva = doc.querySelector('[data-region="' + vieja.dataset.region + '"]');
            if (nueva) {
                vieja.replaceWith(nueva);
                reemplazadas++;
            }
        });

        // Ninguna region coincidio: la pagina es distinta de la que esperabamos
        if (reemplazadas === 0) return false;

        // El token CSRF puede haberse renovado junto con la pagina
        const tokenNuevo = doc.querySelector('input[name="csrf_token"]')?.value;
        if (tokenNuevo) actualizarTokens(tokenNuevo);

        document.dispatchEvent(new CustomEvent('regiones:actualizadas'));
        return true;
    }

    function actualizarTokens(token) {
        document.querySelectorAll('input[name="csrf_token"]').forEach(i => { i.value = token; });
    }

    /** Pide un token nuevo cuando el anterior caduco */
    async function renovarToken() {
        try {
            const r = await fetch(base() + '/api/csrf', { headers: AJAX, credentials: 'same-origin' });
            if (!r.ok) return null;
            const datos = await r.json();
            if (datos.csrf) actualizarTokens(datos.csrf);
            return datos.csrf || null;
        } catch (e) {
            return null;
        }
    }

    function base() {
        return document.body.dataset.base || '';
    }

    // ======================================================================
    // Envio de formularios
    // ======================================================================

    /**
     * Un formulario queda fuera del AJAX si lleva data-sin-ajax.
     * Se usa en los que tienen que navegar de verdad: el login (cambia de
     * pantalla completa) y las descargas de reportes.
     */
    function esInterceptable(form) {
        if (form.hasAttribute('data-sin-ajax')) return false;
        if (form.enctype === 'multipart/form-data' && form.hasAttribute('data-subida')) return false;
        return true;
    }

    async function enviar(form, evento) {
        const metodo = (form.method || 'get').toUpperCase();
        const boton  = form.querySelector('[type="submit"]') ||
                       document.querySelector('[form="' + form.id + '"][type="submit"]');

        // --- Formularios de filtro (GET): se recarga el resultado y se
        //     actualiza la direccion para que el boton "atras" funcione ---
        if (metodo === 'GET') {
            evento.preventDefault();
            const parametros = new URLSearchParams(new FormData(form));
            const url = form.action + (parametros.toString() ? '?' + parametros : '');

            ocupar(boton, true);
            try {
                if (await refrescarRegiones(url)) {
                    history.pushState({}, '', url);
                } else {
                    window.location.assign(url);
                }
            } catch (e) {
                window.location.assign(url);
            } finally {
                ocupar(boton, false);
            }
            return;
        }

        // --- Acciones (POST) ---
        evento.preventDefault();
        ocupar(boton, true);

        try {
            let respuesta = await postear(form);

            // Token caducado: se renueva y se reintenta UNA sola vez
            if (respuesta.estado === 419) {
                const token = respuesta.datos.csrf || await renovarToken();
                if (token) {
                    actualizarTokens(token);
                    respuesta = await postear(form);
                }
            }

            await procesar(respuesta, form);
        } catch (e) {
            avisar('No se pudo conectar con el servidor. Revisa tu conexión.', 'error');
        } finally {
            ocupar(boton, false);
        }
    }

    async function postear(form) {
        const r = await fetch(form.action, {
            method: 'POST',
            headers: AJAX,
            credentials: 'same-origin',
            body: new FormData(form)
        });

        let datos = {};
        try {
            datos = await r.json();
        } catch (e) {
            // El servidor no devolvio JSON: probablemente redirigio a una
            // pagina completa. Se trata como "hay que navegar".
            datos = { ok: false, navegar: true };
        }

        return { estado: r.status, datos: datos };
    }

    async function procesar(respuesta, form) {
        const d = respuesta.datos;

        // Sesion caida
        if (respuesta.estado === 401 && d.redirigir) {
            avisar(d.error || 'Tu sesión se cerró.', 'error');
            setTimeout(() => window.location.assign(d.redirigir), 1200);
            return;
        }

        // El servidor no hablo JSON: se envia a la antigua
        if (d.navegar) {
            form.removeEventListener('submit', manejador);
            form.submit();
            return;
        }

        if (!d.ok) {
            avisar(d.error || 'No se pudo completar la acción.', 'error');
            if (d.campo) {
                const campo = form.querySelector('[name="' + d.campo + '"]');
                if (campo) { campo.focus(); campo.classList.add('campo-error'); }
            }
            return;
        }

        // Exito
        if (d.mensaje) avisar(d.mensaje, 'ok');

        cerrarModales();
        form.reset();

        const destino = d.destino || window.location.href;
        if (!await refrescarRegiones(destino)) {
            window.location.assign(destino);
        }
    }

    function cerrarModales() {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.classList.remove('abierto');
            m.style.display = 'none';
        });
    }

    function manejador(e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // Si alguien ya cancelo el envio (un onsubmit que devolvio false, o la
        // confirmacion de mas abajo), no hay nada que enviar. Sin esta linea,
        // un "¿seguro?" respondido con Cancelar igual habria disparado la
        // peticion por detras.
        if (e.defaultPrevented) return;

        if (!esInterceptable(form)) return;
        enviar(form, e);
    }

    // Se escucha en el documento (no en cada formulario) para que los
    // formularios que llegan dentro de una region recien reemplazada tambien
    // queden cubiertos, sin tener que volver a registrarlos.
    document.addEventListener('submit', manejador);

    // El boton atras del navegador vuelve a pedir el contenido
    window.addEventListener('popstate', async () => {
        if (!await refrescarRegiones(window.location.href)) {
            window.location.reload();
        }
    });

    // ======================================================================
    // Confirmaciones
    //
    // Antes cada formulario peligroso llevaba onsubmit="return confirm(...)".
    // Eso funciona, pero el confirm() del navegador bloquea todo y se ve
    // distinto en cada equipo. Con data-confirmar el texto viaja en el HTML.
    // ======================================================================
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        const pregunta = form.dataset.confirmar;
        if (!pregunta) return;

        // Solo se interviene cuando el usuario dice QUE NO. Si acepta, el
        // evento sigue su curso y lo recoge el envio por AJAX de mas abajo.
        //
        // Antes esto cancelaba SIEMPRE el envio y, si el usuario aceptaba,
        // volvia a llamar a form.requestSubmit(). Eso no funciona: el estandar
        // dice que requestSubmit() no hace nada si se invoca mientras el
        // propio evento submit se esta despachando, que es justo el caso. El
        // resultado era que botones como "Retirar" preguntaban y despues no
        // hacian absolutamente nada.
        if (!window.confirm(pregunta)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);   // en captura: corre antes que el envio por AJAX

    // ======================================================================
    // Campanita de solicitudes de contraseña (solo administrador)
    //
    // Se consulta cada 30 segundos. No es Web Push del navegador a proposito:
    // eso exige HTTPS y en el aula se entra por la IP local (http://192.168...),
    // donde el navegador lo bloquea. Esto funciona en cualquier caso.
    // ======================================================================

    const campanita = document.getElementById('campanita');

    if (campanita) {
        const contador = document.getElementById('campanitaContador');
        let ultimoConocido = -1;

        async function revisarSolicitudes() {
            try {
                const r = await fetch(base() + '/api/solicitudes/pendientes', {
                    headers: AJAX, credentials: 'same-origin'
                });
                if (!r.ok) return;

                const datos = await r.json();
                const n = parseInt(datos.pendientes, 10) || 0;

                contador.textContent = n;
                contador.hidden = (n === 0);
                campanita.classList.toggle('tiene-pendientes', n > 0);

                // Solo suena cuando aparece una solicitud NUEVA, no en cada
                // consulta: si no, sonaria cada 30 segundos sin parar
                if (ultimoConocido !== -1 && n > ultimoConocido) {
                    sonar();
                    avisar('Hay una nueva solicitud de contraseña.', 'info');
                }
                ultimoConocido = n;
            } catch (e) {
                /* sin conexion: se reintenta en la proxima vuelta */
            }
        }

        function sonar() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                const ctx = new Ctx();
                const osc = ctx.createOscillator();
                const gan = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                gan.gain.setValueAtTime(0.15, ctx.currentTime);
                gan.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                osc.connect(gan); gan.connect(ctx.destination);
                osc.start(); osc.stop(ctx.currentTime + 0.3);
            } catch (e) { /* el navegador puede bloquear el audio */ }
        }

        revisarSolicitudes();
        setInterval(revisarSolicitudes, 30000);
    }

    // ======================================================================
    // Boton "Ver" en los campos de contraseña
    //
    // Se pone solo, en todos. Antes existia unicamente en el acceso, con su
    // propia funcion escrita a mano en la vista; eso dejaba sin el a los cinco
    // campos restantes, que son justo donde mas falta hace: el administrador
    // escribe una contraseña que despues tiene que dictarle al docente, y sin
    // poder verla no hay forma de comprobar que la escribio bien.
    //
    // Al hacerlo aqui, cualquier campo de contraseña que se agregue en el
    // futuro lo hereda sin tener que acordarse.
    // ======================================================================

    function ponerBotonVer(campo) {
        if (campo.dataset.conBotonVer === '1') return;
        campo.dataset.conBotonVer = '1';

        // El campo necesita un contenedor posicionado donde anclar el boton
        var envoltorio = campo.parentNode;

        if (!envoltorio || !envoltorio.classList.contains('input-con-boton')) {
            envoltorio = document.createElement('div');
            envoltorio.className = 'input-con-boton';
            campo.parentNode.insertBefore(envoltorio, campo);
            envoltorio.appendChild(campo);
        }

        // Si la vista ya trae su propio boton, no se agrega un segundo
        if (envoltorio.querySelector('.btn-ver-clave')) return;

        var boton = document.createElement('button');
        boton.type = 'button';          // Sin esto enviaria el formulario
        boton.className = 'btn-ver-clave';
        boton.textContent = 'Ver';
        boton.setAttribute('aria-label', 'Mostrar contraseña');

        boton.addEventListener('click', function () {
            var oculto = campo.type === 'password';
            campo.type = oculto ? 'text' : 'password';
            boton.textContent = oculto ? 'Ocultar' : 'Ver';
            boton.setAttribute('aria-label', oculto ? 'Ocultar contraseña' : 'Mostrar contraseña');
            campo.focus();
        });

        envoltorio.appendChild(boton);
    }

    function prepararCamposClave(raiz) {
        var campos = (raiz || document).querySelectorAll('input[type="password"]');
        Array.prototype.forEach.call(campos, ponerBotonVer);
    }

    document.addEventListener('DOMContentLoaded', function () { prepararCamposClave(); });

    // Los formularios que llegan dentro de una region recien reemplazada por
    // AJAX tambien lo necesitan: si no, el boton solo saldria al recargar
    document.addEventListener('regiones:actualizadas', function () { prepararCamposClave(); });

    // ======================================================================
    // Validacion mientras se escribe
    // ======================================================================

    /** Cedula ecuatoriana: mismo algoritmo que valida el servidor */
    function cedulaValida(cedula) {
        return porQueCedulaInvalida(cedula) === '';
    }

    /**
     * Explica POR QUE una cedula no sirve. Devuelve '' cuando es correcta.
     *
     * El mensaje unico que habia antes ("el digito verificador no coincide")
     * era falso en la mitad de los casos: una cedula como 1788888888 se
     * rechaza por el TERCER digito, no por el ultimo, y quien la escribia se
     * quedaba revisando el numero equivocado. Es la copia exacta de
     * Catalogo::porQueCedulaInvalida en el servidor.
     */
    function porQueCedulaInvalida(cedula) {
        cedula = String(cedula || '').replace(/\D/g, '');

        if (cedula === '') return 'Escribe el número de cédula.';

        if (cedula.length !== 10) {
            return 'La cédula debe tener 10 dígitos y escribiste ' + cedula.length + '.';
        }

        const provincia = parseInt(cedula.slice(0, 2), 10);
        if ((provincia < 1 || provincia > 24) && provincia !== 30) {
            return 'Los dos primeros dígitos son la provincia y deben ir del 01 al 24 (o 30). '
                 + 'Los tuyos son ' + cedula.slice(0, 2) + '.';
        }

        if (parseInt(cedula[2], 10) > 5) {
            return 'El tercer dígito de una cédula de persona natural es menor que 6, y el tuyo es '
                 + cedula[2] + '.';
        }

        const coef = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        let suma = 0;
        for (let i = 0; i < 9; i++) {
            const p = parseInt(cedula[i], 10) * coef[i];
            suma += (p > 9) ? p - 9 : p;
        }

        if (((10 - (suma % 10)) % 10) !== parseInt(cedula[9], 10)) {
            return 'El último dígito (el verificador) no corresponde a los otros nueve. '
                 + 'Revisa que no hayas cambiado dos números de lugar.';
        }

        return '';
    }

    window.cedulaValida = cedulaValida;
    window.porQueCedulaInvalida = porQueCedulaInvalida;

    function pintarCampo(campo, estado, mensaje) {
        campo.classList.toggle('campo-error', estado === 'mal');
        campo.classList.toggle('campo-ok', estado === 'bien');

        let nota = campo.parentNode.querySelector('.campo-nota');
        if (!nota) {
            nota = document.createElement('small');
            nota.className = 'campo-nota';
            campo.parentNode.appendChild(nota);
        }
        nota.textContent = mensaje || '';
        nota.className = 'campo-nota' + (estado === 'mal' ? ' es-error' : estado === 'bien' ? ' es-ok' : '');
    }

    document.addEventListener('input', function (e) {
        const campo = e.target;
        if (!(campo instanceof HTMLInputElement)) return;

        if (campo.name === 'cedula') {
            const v = campo.value.trim();
            if (v === '') return pintarCampo(campo, '', '');
            if (v.length < 10) return pintarCampo(campo, '', 'Faltan ' + (10 - v.length) + ' dígito(s)');

            // El aviso dice exactamente que parte del numero esta mal, en vez
            // de culpar siempre al ultimo digito
            const problema = porQueCedulaInvalida(v);
            pintarCampo(campo, problema === '' ? 'bien' : 'mal', problema || 'Cédula válida');
        }
    });

    // ======================================================================
    // Correo institucional previsualizado mientras se escribe el nombre
    // ======================================================================
    function sinTildes(t) {
        return t.normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    window.correoInstitucional = function (nombre, apellido, dominio) {
        const limpiar = t => sinTildes(String(t).trim().split(/\s+/)[0] || '')
            .toLowerCase().replace(/[^a-z0-9]/g, '');
        const n = limpiar(nombre), a = limpiar(apellido);
        return (n && a) ? n + '.' + a + '@' + dominio : '';
    };

})();
