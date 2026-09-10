<?php

require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Catalogo.php';
require_once dirname(__DIR__) . '/models/SolicitudClave.php';
require_once dirname(__DIR__) . '/libs/Totp.php';

/**
 * Autenticacion del sistema.
 *
 * Hay DOS PUERTAS separadas, una por rol: /acceso/docente y /acceso/admin.
 * Comparten el mecanismo (mismo correo institucional, misma contraseña, misma
 * verificacion en dos pasos) pero cada una solo deja pasar a su rol.
 *
 * La separacion no es decorativa. Con una sola puerta, un docente que probaba
 * su clave nunca sabia si el sistema le negaba el paso por la contraseña o por
 * el rol, y el panel al que caia dependia de un dato invisible. Con dos
 * puertas, cada quien sabe desde el principio a donde va y el sistema puede
 * decir con claridad "esta entrada no es la tuya".
 *
 * Los estudiantes NO tienen cuenta: solo escanean el QR y llenan el
 * formulario. Su puerta es la tercera de la portada, y no pasa por aqui.
 */
class AuthController extends BaseController
{
    /** Igual que en AdminController: la regla de longitud es una sola */
    public const MIN_PASSWORD = 8;

    private const MAX_INTENTOS     = 5;
    private const BLOQUEO_SEGUNDOS = 120;

    /**
     * Las dos puertas del personal. La clave es el rol que admite cada una,
     * y por eso coincide con el valor guardado en usuarios.rol.
     */
    private const PUERTAS = [
        'docente' => [
            'ruta'     => '/acceso/docente',
            'titulo'   => 'Acceso Docente',
            'subtitulo'=> 'Para abrir clases y pasar lista',
            'otra'     => 'admin'
        ],
        'admin' => [
            'ruta'     => '/acceso/admin',
            'titulo'   => 'Acceso Administración',
            'subtitulo'=> 'Supervisión y gestión académica',
            'otra'     => 'docente'
        ]
    ];

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

    /**
     * Las direcciones antiguas (/login y /acceso a secas) llevan a la portada,
     * que es donde ahora se elige la puerta. No se mandan a una de las dos por
     * su cuenta: acertar el rol por adivinanza dejaria a la mitad de la gente
     * en la entrada equivocada.
     */
    public function redirigirAcceso(): void
    {
        $this->redireccionar('/');
    }

    public function mostrarLoginDocente(): void
    {
        $this->mostrarPuerta('docente');
    }

    public function mostrarLoginAdmin(): void
    {
        $this->mostrarPuerta('admin');
    }

    public function procesarLoginDocente(): void
    {
        $this->procesarPuerta('docente');
    }

    public function procesarLoginAdmin(): void
    {
        $this->procesarPuerta('admin');
    }

    private function mostrarPuerta(string $puerta): void
    {
        $this->iniciarSesion();

        // Se recuerda por que puerta entro, para devolverlo aqui si algo falla
        $_SESSION['puerta_acceso'] = $puerta;

        // Si ya hay sesion abierta, ir directo al panel que corresponde
        if (!empty($_SESSION['usuario_id'])) {
            $this->redireccionar($this->panelDelRol());
        }

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('auth.login', [
            'base'      => self::obtenerRutaBase(),
            'puerta'    => $puerta,
            // Se llama 'tituloPuerta' y no 'titulo' porque las vistas usan la
            // variable $titulo para el titulo de la pestaña del navegador: con
            // el mismo nombre, una de las dos cosas pisaria a la otra.
            'tituloPuerta' => self::PUERTAS[$puerta]['titulo'],
            'subtitulo' => self::PUERTAS[$puerta]['subtitulo'],
            'accion'    => self::PUERTAS[$puerta]['ruta'],
            'otraRuta'  => self::PUERTAS[self::PUERTAS[$puerta]['otra']]['ruta'],
            'otroTexto' => self::PUERTAS[self::PUERTAS[$puerta]['otra']]['titulo'],
            'mensaje'   => $mensaje,
            'error'     => $error,
            'csrf'      => self::tokenCsrf(),
            'dominio'   => Catalogo::DOMINIO
        ]);
    }

    private function procesarPuerta(string $puerta): void
    {
        $this->iniciarSesion();
        $_SESSION['puerta_acceso'] = $puerta;
        $this->verificarCsrf(self::PUERTAS[$puerta]['ruta']);

        $correo   = mb_strtolower(trim($_POST['correo'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        // Validacion 1: campos obligatorios
        if ($correo === '' || $password === '') {
            $this->redirigirConError('Ingresa tu correo institucional y tu contraseña.', $this->rutaAcceso());
        }

        // Validacion 2: solo se admite el dominio institucional.
        // Se comprueba el dominio exacto, no que "termine en": un correo como
        // alguien@falso-istpet.edu.ec terminaria en esa cadena y pasaria.
        if (!Catalogo::esCorreoInstitucional($correo)) {
            $this->redirigirConError('Correo o contraseña incorrectos.', $this->rutaAcceso());
        }

        // Validacion 3: longitudes razonables antes de tocar la base de datos
        if (mb_strlen($correo) > 150 || strlen($password) > 200) {
            $this->redirigirConError('Correo o contraseña incorrectos.', $this->rutaAcceso());
        }

        // Validacion 4: bloqueo temporal tras varios intentos fallidos
        if ($this->estaBloqueado()) {
            $restante = $_SESSION['login_bloqueo_hasta'] - time();
            $this->redirigirConError(
                "Demasiados intentos fallidos. Espera {$restante} segundos antes de volver a intentarlo.",
                $this->rutaAcceso()
            );
        }

        $usuario = Usuario::buscarPorCorreo($correo);

        // password_verify compara contra el hash Bcrypt: la contraseña real
        // nunca se guarda ni se puede recuperar de la base de datos
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $this->registrarIntentoFallido();
            $this->redirigirConError('Correo o contraseña incorrectos.', $this->rutaAcceso());
        }

        if ((int)$usuario['activo'] === 0) {
            $this->redirigirConError(
                'Tu cuenta institucional está desactivada. Comunícate con el Administrador.',
                $this->rutaAcceso()
            );
        }

        // ---- La puerta tiene que corresponder al rol ----
        //
        // Se comprueba DESPUES de la contraseña a proposito. Si se hiciera
        // antes, el mensaje "esta entrada no es la tuya" le confirmaria a
        // cualquiera que ese correo existe y con que rol, sin necesidad de
        // saber la clave. Aqui ya la sabe, asi que decirselo con claridad no
        // le revela nada nuevo y le ahorra el desconcierto.
        $rolReal = Catalogo::esRolValido($usuario['rol']) ? $usuario['rol'] : 'docente';

        if ($rolReal !== $puerta) {
            $correcta = self::PUERTAS[$rolReal];
            $this->limpiarIntentos();
            $this->redirigirConError(
                'Esta entrada es solo para ' . ($puerta === 'admin' ? 'administradores' : 'docentes')
                . '. Tu cuenta entra por "' . $correcta['titulo'] . '".',
                $correcta['ruta']
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
            $this->redirigirConError('Vuelve a iniciar sesión.', $this->rutaAcceso());
        }

        $usuario = Usuario::buscarPorId($pendiente['usuario_id']);

        if (!$usuario) {
            unset($_SESSION['totp_pendiente']);
            $this->redirigirConError('Vuelve a iniciar sesión.', $this->rutaAcceso());
        }

        [, $error] = $this->obtenerFlash();

        $this->vista('auth.verificar', [
            'base'    => self::obtenerRutaBase(),
            'usuario' => $usuario,
            'volver'  => $this->rutaAcceso(),
            'error'   => $error,
            'csrf'    => self::tokenCsrf()
        ]);
    }

    public function procesarVerificacion(): void
    {
        $this->iniciarSesion();
        $this->verificarCsrf($this->rutaAcceso());

        $pendiente = $this->verificacionPendiente();

        if ($pendiente === null) {
            $this->redirigirConError('La verificación caducó. Vuelve a iniciar sesión.', $this->rutaAcceso());
        }

        // El segundo paso tambien se limita: si no, alguien con la contraseña
        // podria probar los seis digitos por fuerza bruta sin ningun freno.
        $_SESSION['totp_intentos'] = ($_SESSION['totp_intentos'] ?? 0) + 1;

        if ($_SESSION['totp_intentos'] > self::MAX_INTENTOS) {
            unset($_SESSION['totp_pendiente'], $_SESSION['totp_intentos']);
            $this->redirigirConError(
                'Demasiados códigos incorrectos. Vuelve a iniciar sesión.',
                $this->rutaAcceso()
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

        // El periodo se vuelve a elegir en cada entrada. Arrastrar el de la
        // sesion anterior es como se termina cargando la malla del ciclo nuevo
        // dentro del viejo sin darse cuenta.
        unset($_SESSION['periodo_id']);

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
        $this->verificarCsrf($this->rutaAcceso());

        $correo = mb_strtolower(trim($_POST['correo'] ?? ''));
        $aviso  = 'Si ese correo pertenece a una cuenta del instituto, el administrador '
                . 'recibió tu solicitud y se pondrá en contacto contigo.';

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->redirigirConError('Escribe tu correo institucional.', $this->rutaAcceso());
        }

        $usuario = Usuario::buscarPorCorreo($correo);

        if ($usuario && (int)$usuario['activo'] === 1) {
            SolicitudClave::crear((int)$usuario['id']);
        }

        $this->redirigirConMensaje($aviso, $this->rutaAcceso());
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
                $this->rutaAcceso()
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
        $this->verificarCsrf($this->rutaAcceso());

        $token     = (string)($_POST['token'] ?? '');
        $solicitud = SolicitudClave::porToken($token);

        if (!$solicitud) {
            // Sin solicitud no se sabe de quien es la cuenta, asi que no hay
            // forma de elegir su puerta: se lo devuelve a la portada.
            $this->redirigirConError('Ese enlace no es válido o ya caducó.', '/');
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

        // Se lo deja en la puerta que le toca segun su rol, no en una
        // generica: acaba de cambiar la clave y lo siguiente que hara es entrar.
        $duenio = Usuario::buscarPorId((int)$solicitud['usuario_id']);
        $puerta = ($duenio && ($duenio['rol'] ?? '') === 'admin') ? 'admin' : 'docente';

        $this->redirigirConMensaje(
            'Contraseña actualizada. Ya puedes iniciar sesión.',
            self::PUERTAS[$puerta]['ruta']
        );
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

    /**
     * A donde va cada rol nada mas entrar.
     *
     * El administrador no cae en su panel sino en la eleccion de PERIODO
     * ACADEMICO. Es deliberado: casi todo lo que hara despues (crear materias,
     * asignar docentes, sacar reportes) ocurre dentro de un ciclo concreto, y
     * dar por supuesto cual es el ciclo es como termina la malla de un periodo
     * cargada dentro de otro.
     */
    private function panelDelRol(): string
    {
        return (($_SESSION['usuario_rol'] ?? '') === 'admin') ? '/admin/periodo' : '/docente';
    }

    /** La puerta por la que entro el usuario, para devolverlo a la correcta */
    private function rutaAcceso(): string
    {
        $puerta = $_SESSION['puerta_acceso'] ?? 'docente';
        return self::PUERTAS[$puerta]['ruta'] ?? '/acceso/docente';
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
