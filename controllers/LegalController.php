<?php

require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/models/Consentimiento.php';

/**
 * Términos de uso y consentimiento para el tratamiento de datos.
 *
 * La ubicación y la cámara son datos personales, así que el sistema no los
 * activa hasta que la persona acepta de forma expresa. Aquí se muestra el
 * texto y se registra la aceptación.
 */
class LegalController extends BaseController
{
    /** Página pública con los términos completos */
    public function terminos(): void
    {
        $this->iniciarSesion();

        $this->vista('legal.terminos', [
            'base'    => self::obtenerRutaBase(),
            'version' => Consentimiento::VERSION
        ]);
    }

    /**
     * Registra la aceptación del DOCENTE o ADMINISTRADOR.
     * El estudiante acepta dentro de su propio formulario de asistencia,
     * porque hasta que no se identifica no se sabe a quién ligar la constancia.
     */
    public function aceptar(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $volverA = $this->rutaDeRetorno();

        if (empty($_POST['acepto'])) {
            $this->redirigirConError(
                'Debes marcar la casilla para aceptar el uso de ubicación y cámara.',
                $volverA
            );
        }

        if (!Consentimiento::registrar('usuario', $this->idUsuarioActual())) {
            $this->redirigirConError('No se pudo guardar tu aceptación. Intenta nuevamente.', $volverA);
        }

        self::abrirSesion();
        $_SESSION['consentimiento_ok'] = true;

        $this->redirigirConMensaje(
            'Permiso registrado. Ya puedes abrir clases con control de ubicación.',
            $volverA
        );
    }

    /** La persona cambia de opinión y retira su permiso */
    public function retirar(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/perfil');

        Consentimiento::retirar('usuario', $this->idUsuarioActual());

        self::abrirSesion();
        $_SESSION['consentimiento_ok'] = false;

        $this->redirigirConMensaje(
            'Permiso retirado. Tus clases se abrirán sin control de ubicación hasta que lo vuelvas a aceptar.',
            '/docente/perfil'
        );
    }

    // ------------------------------------------------------------------

    /**
     * Solo se admiten rutas internas conocidas: si se aceptara cualquier
     * valor del formulario, se podria usar para redirigir a un sitio externo.
     */
    private function rutaDeRetorno(): string
    {
        $permitidas = ['/docente', '/docente/perfil', '/admin'];
        $pedida = (string)($_POST['volver'] ?? '/docente');

        return in_array($pedida, $permitidas, true) ? $pedida : '/docente';
    }
}
