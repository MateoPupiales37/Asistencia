<?php

/**
 * Armado de mensajes y enlaces de WhatsApp.
 *
 * IMPORTANTE, y conviene tenerlo claro desde el principio:
 * WhatsApp NO permite enviar mensajes de forma automatica sin contratar su
 * API oficial de negocios (WhatsApp Business API), que exige una empresa
 * verificada y tiene costo por mensaje. Cualquier atajo que prometa lo
 * contrario acaba con el numero bloqueado por spam.
 *
 * Lo que si se puede hacer, y es lo que hace esta clase, es preparar el
 * trabajo para que enviar sea un clic por persona en vez de escribir el
 * mensaje a mano:
 *
 *   1. Genera el enlace wa.me de cada destinatario con el texto ya escrito.
 *   2. Permite abrirlos en tanda desde el navegador.
 *   3. Deja copiar todos los mensajes de una vez, o descargarlos, por si se
 *      usa alguna herramienta de envio externa.
 *
 * Para 5 docentes se hace en un minuto; para 100 sigue siendo un clic cada
 * uno, pero sin redactar nada.
 */
class Whatsapp
{
    /** Codigo de pais de Ecuador */
    private const PAIS = '593';

    /**
     * Convierte un numero local a formato internacional sin signos.
     * 0991112233 -> 593991112233
     */
    public static function numeroInternacional(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string)$telefono);

        if ($digitos === '') {
            return null;
        }

        if (str_starts_with($digitos, '0')) {
            $digitos = self::PAIS . substr($digitos, 1);
        } elseif (!str_starts_with($digitos, self::PAIS)) {
            $digitos = self::PAIS . $digitos;
        }

        // Ecuador: 593 + 9 digitos de celular
        return (strlen($digitos) === 12) ? $digitos : null;
    }

    /** Enlace listo para abrir el chat con el mensaje escrito */
    public static function enlace(?string $telefono, string $mensaje): ?string
    {
        $numero = self::numeroInternacional($telefono);

        if ($numero === null) {
            return null;
        }

        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje);
    }

    /**
     * Mensaje para un estudiante con sus datos de acceso.
     * Se le manda el codigo Y la cedula porque puede usar cualquiera de los
     * dos para registrarse, y asi no depende de recordar el codigo.
     */
    public static function mensajeEstudiante(array $estudiante, string $instituto, ?string $curso = null): string
    {
        $nombre = trim($estudiante['nombre'] . ' ' . $estudiante['apellido']);
        $texto  = "Hola {$nombre}, soy del {$instituto}.\n\n";

        if ($curso !== null) {
            $texto .= "Ya estás matriculado en: {$curso}\n\n";
        }

        $texto .= "Para registrar tu asistencia usa cualquiera de estos datos:\n"
                . "• Tu código: {$estudiante['codigo']}\n";

        if (!empty($estudiante['cedula'])) {
            $texto .= "• O tu cédula: {$estudiante['cedula']}\n";
        }

        $texto .= "\nEscanea el código QR que proyecte el docente en clase y "
                . "escribe uno de esos datos. No necesitas usuario ni contraseña.";

        return $texto;
    }

    /** Mensaje para un docente con el enlace de un solo uso para su clave */
    public static function mensajeClaveDocente(array $docente, string $enlace, int $minutos, string $instituto): string
    {
        $nombre = trim($docente['nombre'] . ' ' . $docente['apellido']);

        return "Hola {$nombre}, soy del {$instituto}.\n\n"
             . "Para crear tu contraseña del sistema de asistencia entra aquí:\n{$enlace}\n\n"
             . "Tu usuario es: {$docente['correo']}\n"
             . "El enlace sirve una sola vez y caduca en {$minutos} minutos.";
    }

    /**
     * Prepara una tanda de envios.
     *
     * @param array    $personas  filas con al menos telefono, nombre, apellido
     * @param callable $armar     recibe una persona y devuelve su mensaje
     * @return array{listos:array,sinTelefono:array}
     */
    public static function prepararTanda(array $personas, callable $armar): array
    {
        $listos      = [];
        $sinTelefono = [];

        foreach ($personas as $persona) {
            $mensaje = $armar($persona);
            $enlace  = self::enlace($persona['telefono'] ?? null, $mensaje);

            $ficha = [
                'id'       => (int)($persona['id'] ?? 0),
                'nombre'   => trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '')),
                'telefono' => $persona['telefono'] ?? null,
                'mensaje'  => $mensaje,
                'enlace'   => $enlace,
            ];

            // Sin telefono valido no hay a donde enviar: se separa para que el
            // usuario sepa exactamente a quien le falta el dato
            if ($enlace === null) {
                $sinTelefono[] = $ficha;
            } else {
                $listos[] = $ficha;
            }
        }

        return ['listos' => $listos, 'sinTelefono' => $sinTelefono];
    }
}
