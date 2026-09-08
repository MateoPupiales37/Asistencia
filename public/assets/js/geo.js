/**
 * Captura de ubicación para la geocerca.
 *
 * Dos usos, el mismo código:
 *   - El DOCENTE, al abrir la clase: sus coordenadas fijan el centro del aula.
 *   - El ESTUDIANTE, al registrarse: se comparan con ese centro y, si está a
 *     más de los metros permitidos, el servidor rechaza el registro.
 *
 * Así es como se evita que un alumno mande la foto del QR a un compañero que
 * está en su casa: aunque tenga el código correcto, no está en el aula.
 *
 * Cómo se usa: se marca el formulario con  data-geo  y, dentro, dos campos
 * ocultos llamados "latitud" y "longitud".
 *
 *     <form method="POST" data-geo>
 *       <input type="hidden" name="latitud">
 *       <input type="hidden" name="longitud">
 *
 * Detalle importante: el navegador SOLO entrega la ubicación en páginas
 * seguras (HTTPS, o localhost). Entrando por http://192.168.x.x la niega sin
 * siquiera preguntar. Por eso, si no se consigue, el envío continúa igual: el
 * servidor guarda la asistencia marcándola como "sin verificar" y el docente
 * la ve señalada, en vez de dejar al alumno sin poder registrarse.
 */
(function () {
    'use strict';

    var ESPERA_MS = 8000;   // Más de esto y el alumno se queda mirando la pantalla

    /** ¿El navegador va a entregar la ubicación en esta página? */
    function hayGeolocalizacion() {
        return ('geolocation' in navigator) && window.isSecureContext;
    }

    /** Pide la posición una vez. Nunca rechaza: devuelve null si no se pudo. */
    function pedirPosicion() {
        return new Promise(function (resolver) {
            if (!hayGeolocalizacion()) {
                resolver(null);
                return;
            }

            var contestado = false;

            var terminar = function (valor) {
                if (!contestado) {
                    contestado = true;
                    resolver(valor);
                }
            };

            // Red de seguridad: algunos navegadores no llaman a ningún callback
            // si el usuario ignora el aviso de permiso y deja el diálogo abierto.
            var reloj = setTimeout(function () { terminar(null); }, ESPERA_MS + 500);

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    clearTimeout(reloj);
                    terminar({
                        latitud: pos.coords.latitude,
                        longitud: pos.coords.longitude,
                        precision: pos.coords.accuracy
                    });
                },
                function () {
                    // Permiso denegado, sin señal o fuera de tiempo: se sigue sin ubicación
                    clearTimeout(reloj);
                    terminar(null);
                },
                { enableHighAccuracy: true, timeout: ESPERA_MS, maximumAge: 0 }
            );
        });
    }

    /** Mensaje discreto dentro del propio formulario */
    function avisar(formulario, texto, tipo) {
        var caja = formulario.querySelector('[data-geo-aviso]');

        if (!caja) {
            caja = document.createElement('p');
            caja.setAttribute('data-geo-aviso', '');
            caja.className = 'geo-aviso';
            var boton = formulario.querySelector('button[type="submit"]');
            if (boton && boton.parentNode) {
                boton.parentNode.insertBefore(caja, boton);
            } else {
                formulario.appendChild(caja);
            }
        }

        caja.className = 'geo-aviso' + (tipo ? ' geo-' + tipo : '');
        caja.textContent = texto;
        caja.hidden = false;
    }

    function prepararFormulario(formulario) {
        var campoLat = formulario.querySelector('input[name="latitud"]');
        var campoLon = formulario.querySelector('input[name="longitud"]');

        if (!campoLat || !campoLon) {
            return;   // El formulario no está preparado para recibirlas
        }

        var enviando = false;

        formulario.addEventListener('submit', function (evento) {
            // Segunda pasada: ya se pidió la ubicación, se deja pasar el envío
            if (enviando) {
                return;
            }

            // Si el navegador no la va a dar, no se retrasa el envío ni un segundo
            if (!hayGeolocalizacion()) {
                return;
            }

            evento.preventDefault();

            var boton = formulario.querySelector('button[type="submit"]');
            var textoOriginal = boton ? boton.textContent : '';

            if (boton) {
                boton.disabled = true;
                boton.textContent = 'Comprobando tu ubicación…';
            }

            avisar(formulario, 'Permite el acceso a la ubicación para confirmar que estás en el aula.', 'info');

            pedirPosicion().then(function (posicion) {
                if (posicion) {
                    campoLat.value = posicion.latitud;
                    campoLon.value = posicion.longitud;
                } else {
                    // Se envía igual: el servidor lo marcará como no verificado
                    campoLat.value = '';
                    campoLon.value = '';
                    avisar(
                        formulario,
                        'No se pudo obtener tu ubicación. El registro quedará marcado para que lo revise el docente.',
                        'error'
                    );
                }

                enviando = true;

                if (boton) {
                    boton.disabled = false;
                    boton.textContent = textoOriginal;
                }

                // requestSubmit respeta las validaciones del propio formulario
                if (typeof formulario.requestSubmit === 'function') {
                    formulario.requestSubmit();
                } else {
                    formulario.submit();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var formularios = document.querySelectorAll('form[data-geo]');
        Array.prototype.forEach.call(formularios, prepararFormulario);

        // Aviso previo en las pantallas que dependen de la ubicación, para que
        // el usuario sepa por qué el navegador se la va a pedir.
        if (formularios.length && !hayGeolocalizacion()) {
            Array.prototype.forEach.call(formularios, function (f) {
                if (f.hasAttribute('data-geo-obligatorio')) {
                    avisar(
                        f,
                        'Esta página no se abrió por HTTPS, así que el navegador no entrega la ubicación. '
                        + 'El control de distancia quedará desactivado.',
                        'error'
                    );
                }
            });
        }
    });
})();
