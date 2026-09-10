<?php

require_once dirname(__DIR__) . '/config/app.php';

// Controlador Base: Proporciona metodos auxiliares para cargar vistas,
// redireccionar, manejar la sesion PHP y proteger los formularios (CSRF)

class BaseController
{
    // Carga un archivo de vista ubicado en la carpeta views/ y le pasa variables
    protected function vista(string $nombreVista, array $datos = []): void
    {
        // Toda vista necesita la sesion iniciada: el header muestra la barra de
        // navegacion segun el rol guardado en $_SESSION
        self::abrirSesion();

        extract($datos, EXTR_SKIP);
        $rutaArchivo = dirname(__DIR__) . '/views/' . str_replace('.', '/', $nombreVista) . '.php';

        if (file_exists($rutaArchivo)) {
            require $rutaArchivo;
        } else {
            http_response_code(500);
            echo "Error: La vista {$nombreVista} no existe.";
        }
    }

    // Devuelve una respuesta en formato JSON (util para la API de asistencias en vivo)
    protected function json(array $datos, int $codigoHttp = 200): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------------------------------------------------------------------
    // Soporte AJAX
    //
    // Los controladores NO cambian: siguen llamando a redirigirConMensaje() o
    // redirigirConError() como siempre. Lo que cambia es la forma de la
    // respuesta: si la peticion llego por AJAX se devuelve JSON en vez de una
    // redireccion, de modo que el navegador actualice solo la parte que cambio
    // en lugar de recargar la pagina entera.
    // ---------------------------------------------------------------------

    /** ¿La peticion actual viene del JavaScript del sistema? */
    protected function esAjax(): bool
    {
        $cabecera = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strcasecmp($cabecera, 'XMLHttpRequest') === 0;
    }

    // Redirecciona al navegador a otra ruta del sistema
    protected function redireccionar(string $ruta): void
    {
        $base = self::obtenerRutaBase();
        header("Location: {$base}{$ruta}");
        exit;
    }

    // Guarda un mensaje temporal y redirecciona (evita repetir 3 lineas en cada validacion)
    protected function redirigirConError(string $mensaje, string $ruta): void
    {
        self::abrirSesion();

        if ($this->esAjax()) {
            // 422 = la peticion se entendio pero los datos no son validos.
            // El JavaScript lo muestra como aviso rojo y deja el formulario
            // abierto con lo que el usuario ya habia escrito.
            $this->json(['ok' => false, 'error' => $mensaje], 422);
        }

        $_SESSION['flash_error'] = $mensaje;
        $this->redireccionar($ruta);
    }

    protected function redirigirConMensaje(string $mensaje, string $ruta): void
    {
        self::abrirSesion();

        // El mensaje se guarda SIEMPRE, incluso respondiendo por AJAX.
        //
        // Motivo: cuando la pantalla cambia de forma, el JavaScript no puede
        // parchear trozos y navega de verdad. En ese caso el aviso flotante
        // muere junto con la pagina y el usuario se queda sin saber si su
        // accion funciono (pasaba al restablecer una contraseña). Con el
        // mensaje guardado, la pagina de destino lo muestra igual.
        $_SESSION['flash_mensaje'] = $mensaje;

        if ($this->esAjax()) {
            $this->json([
                'ok'      => true,
                'mensaje' => $mensaje,
                // Donde debe ir el navegador a buscar el contenido actualizado
                'destino' => self::obtenerRutaBase() . $ruta
            ]);
        }

        $this->redireccionar($ruta);
    }

    // Lee y borra los mensajes temporales (patron flash) para mostrarlos una sola vez
    protected function obtenerFlash(): array
    {
        self::abrirSesion();
        $mensaje = $_SESSION['flash_mensaje'] ?? null;
        $error   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);
        return [$mensaje, $error];
    }

    // Calcula la carpeta base si el proyecto corre en subdirectorios como XAMPP
    public static function obtenerRutaBase(): string
    {
        $dir = str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return ($dir === '/' || empty($dir)) ? '' : $dir;
    }

    // Inicia la sesión PHP con cookies endurecidas si no ha sido iniciada previamente
    public static function abrirSesion(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,                                  // Bloquea el acceso por JavaScript
            'samesite' => 'Lax',                                 // Mitiga peticiones desde otros sitios
            // App::esHttps() tambien reconoce el HTTPS que termina en un proxy,
            // como ocurre en cualquier hosting de produccion
            'secure'   => App::esHttps()
        ]);
        session_start();
    }

    // Alias conservado para compatibilidad con el codigo existente
    protected function iniciarSesion(): void
    {
        self::abrirSesion();
    }

    // ---------------------------------------------------------------------
    // Proteccion CSRF: cada formulario POST lleva un token unico de sesion
    // ---------------------------------------------------------------------

    public static function tokenCsrf(): string
    {
        self::abrirSesion();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Detiene la peticion si el token enviado no coincide con el de la sesion
    protected function verificarCsrf(string $rutaRetorno = '/'): void
    {
        self::abrirSesion();
        $enviado  = $_POST['csrf_token'] ?? '';
        $esperado = $_SESSION['csrf_token'] ?? '';

        if (empty($esperado) || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
            if ($this->esAjax()) {
                // 419 le dice al JavaScript que renueve el token y reintente
                // una sola vez, en lugar de mostrarle un error al usuario por
                // algo que puede resolverse solo.
                $this->json([
                    'ok'    => false,
                    'error' => 'La sesión expiró. Vuelve a intentarlo.',
                    'csrf'  => self::tokenCsrf()
                ], 419);
            }

            $_SESSION['flash_error'] = 'La sesión expiró o el formulario no es válido. Vuelve a intentarlo.';
            $this->redireccionar($rutaRetorno);
        }
    }

    // ---------------------------------------------------------------------
    // Control de acceso por rol (RBAC)
    // ---------------------------------------------------------------------

    // Verifica que el usuario tenga sesión activa (docente o administrador)
    protected function verificarDocente(): void
    {
        self::abrirSesion();

        if (empty($_SESSION['usuario_id'])) {
            $this->cortarSinSesion('Debe iniciar sesión para acceder a esta sección.');
        }

        $this->comprobarInactividad();
    }

    /**
     * Corta la peticion cuando no hay sesion. En AJAX no sirve redirigir: el
     * navegador seguiria la redireccion y el JavaScript recibiria el HTML del
     * login en vez de un resultado. Por eso se responde 401 y el JS se encarga
     * de llevar al usuario al login.
     */
    private function cortarSinSesion(string $mensaje): void
    {
        if ($this->esAjax()) {
            $this->json([
                'ok'       => false,
                'error'    => $mensaje,
                // A la portada y no a una puerta concreta: la sesion ya se
                // vacio, asi que no queda forma de saber que rol tenia quien
                // estaba usando el sistema.
                'redirigir' => self::obtenerRutaBase() . '/'
            ], 401);
        }

        $_SESSION['flash_error'] = $mensaje;
        $this->redireccionar('/');
    }

    /**
     * Verifica que el usuario tenga el rol de Administrador.
     *
     * @param bool $exigirPeriodo Si el administrador debe haber elegido ya un
     *        periodo academico. Solo se pone en false en la propia pantalla de
     *        eleccion; en cualquier otra habria un bucle de redirecciones.
     */
    protected function verificarAdmin(bool $exigirPeriodo = true): void
    {
        self::abrirSesion();

        if (empty($_SESSION['usuario_id'])) {
            $this->cortarSinSesion('Debe iniciar sesión para acceder al panel de administración.');
        }

        $this->comprobarInactividad();

        if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
            // Un docente autenticado no se manda al login: se lo devuelve a SU panel
            $_SESSION['flash_error'] = 'Acceso denegado: se requieren permisos de Administrador.';
            $this->redireccionar('/docente');
        }

        if ($exigirPeriodo && $this->periodoActual() === null) {
            $this->redireccionar('/admin/periodo');
        }
    }

    // ---------------------------------------------------------------------
    // Periodo academico en curso
    //
    // Casi todo lo que ve el administrador esta acotado a un periodo: las
    // materias, las asignaciones, los reportes. El periodo elegido vive en la
    // sesion, asi que cada administrador puede estar revisando un ciclo
    // distinto sin estorbarse.
    // ---------------------------------------------------------------------

    /** El periodo elegido, o el que corresponde a la fecha de hoy */
    protected function periodoActual(): ?array
    {
        self::abrirSesion();

        require_once dirname(__DIR__) . '/models/Periodo.php';

        $elegido = Periodo::buscarPorId((int)($_SESSION['periodo_id'] ?? 0));

        if ($elegido !== null) {
            // El nombre viaja en la sesion para que la barra de navegacion lo
            // muestre sin volver a consultar la base en cada pantalla
            $_SESSION['periodo_nombre'] = $elegido['nombre'];
            return $elegido;
        }

        // Nadie ha elegido todavia (o el que estaba elegido se borro): se
        // propone el que esta en curso para no dejar la pantalla en blanco.
        $porDefecto = Periodo::actual();

        if ($porDefecto !== null) {
            $_SESSION['periodo_id']     = (int)$porDefecto['id'];
            $_SESSION['periodo_nombre'] = $porDefecto['nombre'];
        }

        return $porDefecto;
    }

    protected function idPeriodoActual(): ?int
    {
        $periodo = $this->periodoActual();
        return $periodo ? (int)$periodo['id'] : null;
    }

    /**
     * Cierra la sesion despues de un rato sin actividad.
     *
     * En un aula la computadora del docente queda encendida y a la vista de
     * todos; sin este control, cualquiera que pase podria abrir el panel con la
     * sesion todavia iniciada y manipular la lista de asistencia.
     */
    private function comprobarInactividad(): void
    {
        $limite = App::MINUTOS_INACTIVIDAD * 60;
        $ultima = $_SESSION['ultima_actividad'] ?? time();

        if (time() - $ultima > $limite) {
            // Se vacian los datos y se cambia el identificador de sesion, para
            // que la cookie que quedo en el navegador ya no sirva de nada
            $_SESSION = [];
            session_regenerate_id(true);
            $this->cortarSinSesion('Tu sesión se cerró por inactividad. Vuelve a iniciar sesión.');
        }

        $_SESSION['ultima_actividad'] = time();
    }

    // Retorna verdadero si el usuario en sesión es Administrador
    public static function esAdmin(): bool
    {
        self::abrirSesion();
        return ($_SESSION['usuario_rol'] ?? '') === 'admin';
    }

    // Identificador del docente/administrador autenticado
    protected function idUsuarioActual(): int
    {
        self::abrirSesion();
        return (int)($_SESSION['usuario_id'] ?? 0);
    }
}
