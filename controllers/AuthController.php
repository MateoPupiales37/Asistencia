<?php

require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Catalogo.php';
require_once dirname(__DIR__) . '/models/SolicitudClave.php';
require_once dirname(__DIR__) . '/libs/Totp.php';

/**
 * Autenticacion del sistema.
 *
 * El acceso es UNICO para administradores y docentes: mismo formulario y mismo
 * correo institucional. Lo que cambia es el panel al que se entra segun el rol.
 * Los estudiantes NO tienen cuenta: solo escanean el QR y llenan el formulario.
 */
class AuthController extends BaseController
{
    /** Igual que en AdminController: la regla de longitud es una sola */
    public const MIN_PASSWORD = 8;

    private const MAX_INTENTOS     = 5;
    private const BLOQUEO_SEGUNDOS = 120;

    /**
     * El codigo aleatorio que se mostraba en pantalla fue reemplazado por
     * VERIFICACION EN DOS PASOS con Google Authenticator.
     *
     * La diferencia importa: aquel codigo estaba a la vista de cualquiera
     * que mirara la pantalla, asi que solo frenaba el llenado automatico de
     * formularios. El de la aplicacion autenticadora vive en el telefono del
     * docente y cambia cada 30 segundos: quien robe la contraseña sigue sin
     * poder entrar.
     */

    /** La direccion vieja /login lleva al acceso nuevo, sin romper marcadores */
    public function redirigirAcceso(): void
    {
        $this->redireccionar('/acceso');
    }

    public function mostrarLogin(): void
    {
        $this->iniciarSesion();

        // Si ya hay sesion abierta, ir directo al panel que corresponde
        if (!empty($_SESSION['usuario_id'])) {
            $this->redireccionar($this->panelDelRol());
        }

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('auth.login', [
            'base'    => self::obtenerRutaBase(),
            'mensaje' => $mensaje,
            'error'   => $error,
            'csrf'    => self::tokenCsrf(),
            'dominio' => Catalogo::DOMINIO
        ]);
    }

    public function procesarLogin(): void
    {
        $this->iniciarSesion();
        $this->verificarCsrf('/acceso');

        $correo   = mb_strtolower(trim($_POST['correo'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        // Validacion 1: campos obligatorios
        if ($correo === '' || $password === '') {
            $this->redirigirConError('Ingresa tu correo institucional y tu contraseña.', '/acceso');
        }

        // Validacion 2: solo se admite el dominio institucional.
        // Se comprueba el dominio exacto, no que "termine en": un correo como
        // alguien@falso-istpet.edu.ec terminaria en esa cadena y pasaria.
        if (!Catalogo::esCorreoInstitucional($correo)) {
            $this->redirigirConError('Correo o contraseña incorrectos.', '/acceso');
        }

        // Validacion 3: longitudes razonables antes de tocar la base de datos
        if (mb_strlen($correo) > 150 || strlen($password) > 200) {
            $this->redirigirConError('Correo o contraseña incorrectos.', '/acceso');
        }

        // Validacion 4: bloqueo temporal tras varios intentos fallidos
        if ($this->estaBloqueado()) {
            $restante = $_SESSION['login_bloqueo_hasta'] - time();
            $this->redirigirConError(
                "Demasiados intentos fallidos. Espera {$restante} segundos antes de volver a intentarlo.",
                '/acceso'
            );
        }

        $usuario = Usuario::buscarPorCorreo($correo);

        // password_verify compara contra el hash Bcrypt: la contraseña real
        // nunca se guarda ni se puede recuperar de la base de datos
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $this->registrarIntentoFallido();
            $this->redirigirConError('Correo o contraseña incorrectos.', '/acceso');
        }

        if ((int)$usuario['activo'] === 0) {
            $this->redirigirConError(
                'Tu cuenta institucional está desactivada. Comunícate con el Administrador.',
                '/acceso'
            );
        }

        // ---- Segundo paso: verificacion en dos pasos ----
        // La contraseña ya se comprobo, pero la sesion NO se abre todavia.
        // Se deja al usuario "a medio entrar" y se le pide el codigo de su
        // aplicacion autenticadora en una pantalla aparte.
        if (Usuario::tieneTotpActivo($usuario)) {
            $this->limpiarIntentos();

            $_SESSION['totp_pendiente'] = [
                'usuario_id' => (int)$usuario['id'],
                'expira'     => time() + 300   // 5 minutos para escribir el codigo
            ];

            $this->redireccionar('/acceso/verificar');
        }

        $this->abrirSesionDe($usuario);
    }

    /**
     * Pantalla del segundo paso: el codigo de la aplicacion autenticadora.
     */
    public function mostrarVerificacion(): void
    {
        $this->iniciarSesion();

        $pendiente = $this->verificacionPendiente();

        if ($pendiente === null) {
            $this->redirigirConError('Vuelve a iniciar sesión.', '/acceso');
        }

        $usuario = Usuario::buscarPorId($pendiente['usuario_id']);

        if (!$usuario) {
            unset($_SESSION['totp_pendiente']);
            $this->redirigirConError('Vuelve a iniciar sesión.', '/acceso');
        }

        [, $error] = $this->obtenerFlash();

        $this->vista('auth.verificar', [
            'base'    => self::obtenerRutaBase(),
            'usuario' => $usuario,
            'error'   => $error,
            'csrf'    => self::tokenCsrf()
        ]);
    }

    public function procesarVerificacion(): void
    {
        $this->iniciarSesion();
        $this->verificarCsrf('/acceso');

        $pendiente = $this->verificacionPendiente();

        if ($pendiente === null) {
            $this->redirigirConError('La verificación caducó. Vuelve a iniciar sesión.', '/acceso');
        }

        // El segundo paso tambien se limita: si no, alguien con la contraseña
        // podria probar los seis digitos por fuerza bruta sin ningun freno.
        $_SESSION['totp_intentos'] = ($_SESSION['totp_intentos'] ?? 0) + 1;

        if ($_SESSION['totp_intentos'] > self::MAX_INTENTOS) {
            unset($_SESSION['totp_pendiente'], $_SESSION['totp_intentos']);
            $this->redirigirConError(
                'Demasiados códigos incorrectos. Vuelve a iniciar sesión.',
                '/acceso'
            );
        }

        $usuario = Usuario::buscarPorId($pendiente['usuario_id']);
        $secreto = $usuario ? Usuario::secretoTotp((int)$usuario['id']) : null;

        if (!$usuario || !Totp::verificar($secreto, $_POST['codigo'] ?? '')) {
            $this->redirigirConError(
                'El código no es válido. Revisa la app y escribe el código que se ve ahora.',
                '/acceso/verificar'
            );
        }

        unset($_SESSION['totp_pendiente'], $_SESSION['totp_intentos']);
        $this->abrirSesionDe($usuario);
    }

    /** Devuelve la verificacion en curso, o null si no hay o ya caduco */
    private function verificacionPendiente(): ?array
    {
        $pendiente = $_SESSION['totp_pendiente'] ?? null;

        if (!is_array($pendiente) || ($pendiente['expira'] ?? 0) < time()) {
            unset($_SESSION['totp_pendiente'], $_SESSION['totp_intentos']);
            return null;
        }

        return $pendiente;
    }

    /** Abre la sesion de verdad, ya superados todos los pasos */
    private function abrirSesionDe(array $usuario): void
    {
        $this->limpiarIntentos();
        session_regenerate_id(true);   // Previene el secuestro de sesion por fijacion

        $_SESSION['usuario_id']     = (int)$usuario['id'];
        $_SESSION['usuario_nombre'] = Usuario::nombreCompleto($usuario);
        $_SESSION['usuario_correo'] = $usuario['correo'];
        $_SESSION['usuario_rol']    = Catalogo::esRolValido($usuario['rol']) ? $usuario['rol'] : 'docente';
        $_SESSION['ultima_actividad'] = time();   // Punto de partida del cierre por inactividad
        $_SESSION['totp_activo']      = Usuario::tieneTotpActivo($usuario);

        $this->redireccionar($this->panelDelRol());
    }

    /**
     * El docente pide que le restablezcan la clave.
     *
     * La respuesta es SIEMPRE la misma, exista o no la cuenta. Si dijera
     * "ese correo no existe", cualquiera podria averiguar que correos son
     * validos probando uno por uno.
     */
    public function solicitarClave(): void
    {
        $this->iniciarSesion();
        $this->verificarCsrf('/acceso');

        $correo = mb_strtolower(trim($_POST['correo'] ?? ''));
        $aviso  = 'Si ese correo pertenece a una cuenta del instituto, el administrador '
                . 'recibió tu solicitud y se pondrá en contacto contigo.';

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->redirigirConError('Escribe tu correo institucional.', '/acceso');
        }

        $usuario = Usuario::buscarPorCorreo($correo);

        if ($usuario && (int)$usuario['activo'] === 1) {
            SolicitudClave::crear((int)$usuario['id']);
        }

        $this->redirigirConMensaje($aviso, '/acceso');
    }

    /** Pantalla donde el docente escribe su nueva clave, desde el enlace */
    public function mostrarNuevaClave(): void
    {
        $this->iniciarSesion();

        $token     = (string)($_GET['t'] ?? '');
        $solicitud = SolicitudClave::porToken($token);

        if (!$solicitud) {
            $this->redirigirConError(
                'Ese enlace no es válido, ya se usó o caducó. Solicita la clave de nuevo.',
                '/acceso'
            );
        }

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('auth.nueva_clave', [
            'base'      => self::obtenerRutaBase(),
            'token'     => $token,
            'solicitud' => $solicitud,
            'minimo'    => self::MIN_PASSWORD,
            'mensaje'   => $mensaje,
            'error'     => $error,
            'csrf'      => self::tokenCsrf()
        ]);
    }

    public function guardarNuevaClave(): void
    {
        $this->iniciarSesion();
        $this->verificarCsrf('/acceso');

        $token     = (string)($_POST['token'] ?? '');
        $solicitud = SolicitudClave::porToken($token);

        if (!$solicitud) {
            $this->redirigirConError('Ese enlace no es válido o ya caducó.', '/acceso');
        }

        $password = (string)($_POST['password'] ?? '');
        $repetir  = (string)($_POST['password2'] ?? '');
        $ruta     = '/clave-nueva?t=' . urlencode($token);

        if (strlen($password) < self::MIN_PASSWORD) {
            $this->redirigirConError(
                'La contraseña debe tener al menos ' . self::MIN_PASSWORD . ' caracteres.',
                $ruta
            );
        }
        if (strlen($password) > 72) {
            // Bcrypt ignora lo que pase de 72 bytes: mejor avisar que truncar
            $this->redirigirConError('La contraseña no puede superar los 72 caracteres.', $ruta);
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $this->redirigirConError('La contraseña debe combinar al menos una letra y un número.', $ruta);
        }
        if ($password !== $repetir) {
            $this->redirigirConError('Las dos contraseñas no coinciden.', $ruta);
        }

        if (!Usuario::cambiarPassword((int)$solicitud['usuario_id'], $password)) {
            $this->redirigirConError('No se pudo guardar la nueva contraseña.', $ruta);
        }

        // El enlace se quema: no puede volver a usarse
        SolicitudClave::marcarUsado((int)$solicitud['id']);

        $this->redirigirConMensaje('Contraseña actualizada. Ya puedes iniciar sesión.', '/acceso');
    }

    public function logout(): void
    {
        $this->iniciarSesion();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();
        $this->redireccionar('/');
    }

    // ------------------------------------------------------------------

    private function panelDelRol(): string
    {
        return (($_SESSION['usuario_rol'] ?? '') === 'admin') ? '/admin' : '/docente';
    }

    private function estaBloqueado(): bool
    {
        return !empty($_SESSION['login_bloqueo_hasta']) && $_SESSION['login_bloqueo_hasta'] > time();
    }

    private function registrarIntentoFallido(): void
    {
        $_SESSION['login_intentos'] = ($_SESSION['login_intentos'] ?? 0) + 1;

        if ($_SESSION['login_intentos'] >= self::MAX_INTENTOS) {
            $_SESSION['login_bloqueo_hasta'] = time() + self::BLOQUEO_SEGUNDOS;
            $_SESSION['login_intentos'] = 0;
        }
    }

    private function limpiarIntentos(): void
    {
        unset($_SESSION['login_intentos'], $_SESSION['login_bloqueo_hasta']);
    }
}
