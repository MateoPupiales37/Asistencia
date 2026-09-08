<?php

require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/models/Sesion.php';
require_once dirname(__DIR__) . '/models/Estudiante.php';
require_once dirname(__DIR__) . '/models/Asistencia.php';
require_once dirname(__DIR__) . '/models/Catalogo.php';
require_once dirname(__DIR__) . '/models/Matricula.php';
require_once dirname(__DIR__) . '/models/Expulsion.php';
require_once dirname(__DIR__) . '/models/Consentimiento.php';
require_once dirname(__DIR__) . '/libs/Geo.php';

/**
 * Pantalla publica del ESTUDIANTE.
 *
 * El alumno no tiene cuenta ni contraseña. Escanea el QR que proyecta el docente
 * (o lo abre desde el enlace) y solo llena un formulario con su nombre y semestre.
 * El mismo codigo sirve para entrada o para salida: el sistema detecta cual es.
 */
class AsistenciaController extends BaseController
{
    private const PATRON_CODIGO  = '/^[A-F0-9]{8}$/';
    private const PATRON_TOKEN   = '/^[a-f0-9]{32}$/';
    private const PATRON_NOMBRE  = "/^[\p{L}][\p{L}\s'.-]{1,49}$/u";

    /** Muestra el formulario de registro de asistencia */
    public function mostrar(): void
    {
        $this->iniciarSesion();

        $codigo = $this->normalizarCodigo($_GET['c'] ?? '');
        $carnet = strtolower(trim($_GET['carnet'] ?? ''));

        $sesion    = null;
        $error     = null;
        $estudiante = null;

        // Si el alumno llego escaneando su carnet personal, se autocompletan sus datos
        if ($carnet !== '' && preg_match(self::PATRON_TOKEN, $carnet)) {
            $estudiante = Estudiante::buscarPorToken($carnet);
        }

        if ($codigo !== '') {
            if (!preg_match(self::PATRON_CODIGO, $codigo)) {
                $error = 'El código escaneado no tiene un formato válido.';
                $codigo = '';
            } else {
                $sesion = Sesion::buscarPorCodigo($codigo);

                if (!$sesion) {
                    $error = 'Ese código no corresponde a ninguna clase.';
                } elseif ($sesion['estado'] === 'cerrada') {
                    $error = 'Esta clase ya fue finalizada por el docente.';
                } elseif (!$sesion['vigente']) {
                    $minutos = Catalogo::MINUTOS_QR;
                    $error = "El código QR caducó (dura {$minutos} minutos). Pídele al docente que genere uno nuevo.";
                }
            }
        }

        [$mensaje, $flashError] = $this->obtenerFlash();

        $this->vista('asistencia.registrar', [
            'base'       => self::obtenerRutaBase(),
            'codigo'     => $codigo,
            'sesion'     => $sesion,
            'estudiante' => $estudiante,
            'minutosQr'  => Catalogo::MINUTOS_QR,
            'mensaje'    => $mensaje,
            'error'      => $error ?? $flashError,
            'csrf'       => self::tokenCsrf()
        ]);
    }

    /** Procesa el formulario: registra la entrada o la salida segun el tipo de QR */
    public function registrar(): void
    {
        $this->iniciarSesion();
        $this->verificarCsrf('/asistencia');

        $codigo = $this->normalizarCodigo($_POST['codigo'] ?? '');

        // ---- Consentimiento informado ----
        // La casilla del formulario se puede quitar desde el navegador, asi que
        // la comprobacion que vale es esta: sin aceptacion expresa no se trata
        // la ubicacion de nadie.
        if (empty($_POST['acepto'])) {
            $this->fallo(
                'Debes aceptar el uso de tu ubicación y cámara para registrar tu asistencia. '
                . 'Puedes leer los términos desde el enlace del formulario.',
                $codigo
            );
        }

        // ---- Validacion del codigo de la clase ----
        if ($codigo === '') {
            $this->fallo('Debes escanear el código QR de la clase o escribirlo manualmente.', '');
        }

        if (!preg_match(self::PATRON_CODIGO, $codigo)) {
            $this->fallo('El código de la clase debe tener 8 caracteres (letras A-F y números).', '');
        }

        $sesion = Sesion::buscarPorCodigo($codigo);

        if (!$sesion) {
            $this->fallo("El código {$codigo} no corresponde a ninguna clase.", '');
        }

        if ($sesion['estado'] === 'cerrada') {
            $this->fallo('Esta clase ya fue finalizada por el docente.', $codigo, $sesion);
        }

        // La caducidad corta es lo que impide registrarse desde fuera del aula
        if (!$sesion['vigente']) {
            $this->fallo(
                'Este código QR ya caducó. Los códigos duran ' . Catalogo::MINUTOS_QR
                . ' minutos; pídele al docente que genere uno nuevo.',
                $codigo,
                $sesion
            );
        }

        // ---- Identificacion del estudiante ----
        $estudiante = $this->resolverEstudiante($codigo, $sesion);

        // ---- Registro segun el tipo de QR ----
        if ($sesion['tipo_qr'] === 'salida') {
            $this->procesarSalida($sesion, $estudiante, $codigo);
        }

        $this->procesarEntrada($sesion, $estudiante, $codigo);
    }

    // ==================================================================

    private function procesarEntrada(array $sesion, array $estudiante, string $codigo): void
    {
        // AQUI ESTA LA COMPROBACION que antes no existia: si el alumno no
        // pertenece a este curso, su registro entra como PENDIENTE y aparece
        // marcado en el panel para que el docente confirme que si esta en el
        // aula. Antes, cualquiera que tuviera el codigo quedaba registrado
        // sin que nadie lo verificara.
        // ---- 1. ¿El docente lo saco de esta clase hace poco? ----
        // Sin este control, el alumno al que el docente retira vuelve a
        // escanear el mismo QR y se registra otra vez, en bucle.
        $bloqueo = Expulsion::vigente((int)$sesion['id'], (int)$estudiante['id']);

        if ($bloqueo !== null) {
            $this->fallo(Expulsion::mensaje($bloqueo), $codigo, $sesion, $estudiante);
        }

        // ---- 2. Geocerca: ¿esta cerca del aula? ----
        $geo = $this->comprobarUbicacion($sesion);

        if (!$geo['permitido']) {
            $this->fallo($geo['texto'], $codigo, $sesion, $estudiante);
        }

        // ---- 3. ¿Pertenece al curso? ----
        $matriculado = Matricula::existe((int)$estudiante['id'], (int)$sesion['curso_id']);
        $aprobacion  = $matriculado ? 'aprobada' : 'pendiente';

        $resultado = Asistencia::registrarEntrada(
            (int)$sesion['id'],
            (int)$estudiante['id'],
            'qr',
            $aprobacion,
            $geo['ubicacion']
        );

        if ($resultado === 'duplicada') {
            $this->fallo(
                'Ya tienes registrada tu asistencia en esta clase. No necesitas volver a escanear.',
                $codigo,
                $sesion,
                $estudiante
            );
        }

        if ($resultado !== 'ok') {
            $this->fallo('Ocurrió un error al guardar tu asistencia. Intenta nuevamente.', $codigo, $sesion);
        }

        $this->exito('entrada', $sesion, $estudiante, $aprobacion === 'pendiente');
    }

    private function procesarSalida(array $sesion, array $estudiante, string $codigo): void
    {
        $resultado = Asistencia::registrarSalida((int)$sesion['id'], (int)$estudiante['id']);

        if ($resultado === 'sin_entrada') {
            $this->fallo(
                'No puedes registrar tu salida porque no marcaste tu entrada en esta clase.',
                $codigo,
                $sesion,
                $estudiante
            );
        }

        if ($resultado === 'ya_salio') {
            $this->fallo('Tu salida de esta clase ya estaba registrada.', $codigo, $sesion, $estudiante);
        }

        if ($resultado !== 'ok') {
            $this->fallo('Ocurrió un error al registrar tu salida. Intenta nuevamente.', $codigo, $sesion);
        }

        $this->exito('salida', $sesion, $estudiante);
    }

    /**
     * Identifica al alumno. Solo hay tres vias, y las tres exigen que YA
     * exista en el padron:
     *
     *   1. Carnet QR personal   -> token irrepetible
     *   2. Codigo institucional -> EST001
     *   3. Numero de cedula     -> la via de respaldo
     *
     * Antes existia una cuarta via: escribir nombre, apellido y semestre, y
     * el sistema daba de alta al alumno en el momento. Se elimino porque era
     * un agujero: cualquiera con el codigo de la clase inventaba un nombre y
     * quedaba registrado. Ahora, quien no este matriculado previamente por su
     * docente no puede registrar asistencia de ninguna forma.
     */
    private function resolverEstudiante(string $codigo, array $sesion): array
    {
        // ---- 1. Carnet QR personal ----
        $token = strtolower(trim($_POST['carnet'] ?? ''));

        if ($token !== '' && preg_match(self::PATRON_TOKEN, $token)) {
            $estudiante = Estudiante::buscarPorToken($token);

            if ($estudiante) {
                return $this->exigirActivo($estudiante, $codigo, $sesion);
            }

            $this->fallo('Tu carnet QR no es válido. Identifícate con tu cédula o tu código.', $codigo, $sesion);
        }

        // ---- 2. Codigo institucional ----
        $codigoEst = strtoupper(trim($_POST['codigo_estudiante'] ?? ''));

        if ($codigoEst !== '') {
            if (!preg_match('/^[A-Z0-9_-]{3,15}$/', $codigoEst)) {
                $this->fallo('Tu código debe tener entre 3 y 15 caracteres (ejemplo: EST001).', $codigo, $sesion);
            }

            $estudiante = Estudiante::buscarPorCodigo($codigoEst);

            if (!$estudiante) {
                $this->fallo(
                    "El código {$codigoEst} no está registrado en el sistema. "
                    . 'Habla con tu docente para que te matricule.',
                    $codigo,
                    $sesion
                );
            }

            return $this->exigirActivo($estudiante, $codigo, $sesion);
        }

        // ---- 3. Numero de cedula ----
        $cedula = Catalogo::normalizarCedula($_POST['cedula'] ?? '');

        if ($cedula === '') {
            $this->fallo('Escribe tu cédula o tu código de estudiante para identificarte.', $codigo, $sesion);
        }

        if (!Catalogo::esCedulaValida($cedula)) {
            $this->fallo('Ese número de cédula no es válido. Revisa los 10 dígitos.', $codigo, $sesion);
        }

        $estudiante = Estudiante::buscarPorCedula($cedula);

        // AQUI esta el control que faltaba: una cedula valida pero que no
        // existe en el padron NO da de alta a nadie. Sin esto, cualquiera
        // podia inventar una cedula con digito verificador correcto y
        // registrar una asistencia de un alumno que no existe.
        if (!$estudiante) {
            $this->fallo(
                "La cédula {$cedula} no está registrada en el sistema. "
                . 'Solo pueden registrar asistencia los estudiantes matriculados: '
                . 'habla con tu docente.',
                $codigo,
                $sesion
            );
        }

        return $this->exigirActivo($estudiante, $codigo, $sesion);
    }

    /**
     * Compara donde esta el alumno con donde se abrio la clase.
     *
     * El navegador solo entrega la ubicacion en contextos seguros (HTTPS o
     * localhost). Entrando por http://192.168.x.x la niega sin preguntar, asi
     * que la falta de coordenadas NO bloquea: se registra la asistencia y
     * queda marcada como no verificada para que el docente lo vea.
     */
    private function comprobarUbicacion(array $sesion): array
    {
        $latAlumno = $_POST['latitud']  ?? null;
        $lonAlumno = $_POST['longitud'] ?? null;

        $veredicto = Geo::evaluar(
            isset($sesion['latitud'])  ? (float)$sesion['latitud']  : null,
            isset($sesion['longitud']) ? (float)$sesion['longitud'] : null,
            (int)($sesion['radio_metros'] ?? Geo::RADIO_POR_DEFECTO),
            $latAlumno,
            $lonAlumno
        );

        $veredicto['ubicacion'] = Geo::coordenadaValida($latAlumno, $lonAlumno)
            ? [
                'latitud'   => (float)$latAlumno,
                'longitud'  => (float)$lonAlumno,
                'distancia' => $veredicto['distancia'],
              ]
            : null;

        return $veredicto;
    }

    /** Un alumno dado de baja no puede seguir registrando asistencia */
    private function exigirActivo(array $estudiante, string $codigo, array $sesion): array
    {
        if ((int)($estudiante['activo'] ?? 1) === 0) {
            $this->fallo(
                'Tu ficha de estudiante está desactivada. Acércate al docente para reactivarla.',
                $codigo,
                $sesion
            );
        }

        // Ya se sabe quien es: aqui se puede dejar la constancia de que acepto
        // el tratamiento de sus datos. Solo se guarda si aun no habia aceptado
        // la version vigente, para no llenar el historial en cada registro.
        if (!Consentimiento::aceptado('estudiante', (int)$estudiante['id'])) {
            Consentimiento::registrar('estudiante', (int)$estudiante['id']);
        }

        return $estudiante;
    }

    // ==================================================================

    private function exito(string $tipo, array $sesion, array $estudiante, bool $pendiente = false): void
    {
        if ($pendiente) {
            $mensaje = 'Tu asistencia quedó registrada, pero como no apareces matriculado '
                     . 'en esta materia el docente debe confirmarla. Avísale.';
        } elseif ($tipo === 'entrada') {
            $mensaje = 'Tu asistencia quedó registrada.';
        } else {
            $mensaje = 'Tu salida quedó registrada.';
        }

        $this->vista('asistencia.resultado', [
            'base'       => self::obtenerRutaBase(),
            'exito'      => true,
            'tipo'       => $tipo,
            'sesion'     => $sesion,
            'estudiante' => $estudiante,
            'pendiente'  => $pendiente,
            'hora'       => date('H:i:s'),
            'mensaje'    => $mensaje
        ]);
        exit;
    }

    private function fallo(string $mensaje, string $codigo, ?array $sesion = null, ?array $estudiante = null): void
    {
        $this->vista('asistencia.resultado', [
            'base'       => self::obtenerRutaBase(),
            'exito'      => false,
            'tipo'       => $sesion['tipo_qr'] ?? 'entrada',
            'sesion'     => $sesion,
            'estudiante' => $estudiante,
            'codigo'     => $codigo,
            'hora'       => date('H:i:s'),
            'mensaje'    => $mensaje
        ]);
        exit;
    }

    private function normalizarCodigo(string $valor): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($valor)));
    }
}
