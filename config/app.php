<?php

/**
 * Configuracion general y arranque del sistema.
 *
 * Este archivo se carga ANTES que cualquier otra cosa (lo llama public/index.php)
 * y se encarga de tres asuntos que antes no tenian dueño:
 *
 *   1. ERRORES: en el navegador nunca debe aparecer un "Warning: Undefined array
 *      key ... in C:\xampp\htdocs\..." como pasaba en la pantalla de reportes.
 *      Eso no solo se ve mal: revela la ruta interna del servidor, la version de
 *      PHP y la estructura de carpetas, que es informacion util para un ataque.
 *      Aqui los errores se GUARDAN en un archivo de registro y al usuario se le
 *      muestra una pantalla limpia.
 *
 *   2. CABECERAS DE SEGURIDAD: se envian en todas las respuestas.
 *
 *   3. URL PUBLICA DEL QR: el celular del alumno no puede abrir "localhost",
 *      porque para el celular localhost es el propio telefono. Aqui se resuelve
 *      cual es la direccion que debe ir dentro del codigo QR.
 */

class App
{
    public const NOMBRE       = 'Sistema de Control de Asistencia QR';
    public const INSTITUCION  = 'Instituto Superior Tecnologico Mayor Pedro Traversari';
    public const SIGLA        = 'ISTPET';
    public const ZONA_HORARIA = 'America/Guayaquil';

    /**
     * Direccion publica con la que los CELULARES alcanzan al servidor.
     *
     * Dejalo vacio y el sistema la deduce solo. Escribela a mano cuando el
     * servidor tenga una IP o un dominio fijo, por ejemplo:
     *
     *     private const URL_PUBLICA = 'http://192.168.1.50/asistencia/public';
     */
    private const URL_PUBLICA = '';

    /**
     * Modo depuracion. En false (lo normal, y lo que debe quedar el dia de la
     * presentacion) los errores se registran en archivo y jamas se imprimen.
     * Ponlo en true solo mientras programas.
     */
    private const DEPURAR = false;

    /** Minutos de inactividad tras los que se cierra la sesion del docente */
    public const MINUTOS_INACTIVIDAD = 60;

    private static bool $iniciada = false;

    // ==================================================================
    // Arranque
    // ==================================================================

    public static function iniciar(): void
    {
        if (self::$iniciada) {
            return;
        }
        self::$iniciada = true;

        date_default_timezone_set(self::ZONA_HORARIA);
        mb_internal_encoding('UTF-8');

        self::configurarErrores();
        self::enviarCabeceras();
    }

    public static function depurando(): bool
    {
        return self::DEPURAR;
    }

    public static function carpetaRegistros(): string
    {
        return dirname(__DIR__) . '/storage/logs';
    }

    // ------------------------------------------------------------------
    // 1. Manejo de errores
    // ------------------------------------------------------------------

    private static function configurarErrores(): void
    {
        // Se informan TODOS los errores, pero al registro, no a la pantalla.
        error_reporting(E_ALL);
        ini_set('display_errors', self::DEPURAR ? '1' : '0');
        ini_set('display_startup_errors', self::DEPURAR ? '1' : '0');
        ini_set('log_errors', '1');

        $carpeta = self::carpetaRegistros();
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0775, true);
        }
        if (is_dir($carpeta) && is_writable($carpeta)) {
            ini_set('error_log', $carpeta . '/error.log');
        }

        // Los avisos (warning/notice) se convierten en excepciones solo cuando
        // se esta depurando; en produccion se registran y la pagina continua,
        // porque tumbar un reporte entero por un aviso menor seria peor.
        set_error_handler(static function (int $tipo, string $mensaje, string $archivo, int $linea): bool {
            if (!(error_reporting() & $tipo)) {
                return false;
            }

            if (self::DEPURAR) {
                throw new ErrorException($mensaje, 0, $tipo, $archivo, $linea);
            }

            error_log(sprintf('[aviso %d] %s en %s:%d', $tipo, $mensaje, $archivo, $linea));
            return true;   // Atendido: PHP no lo imprime
        });

        set_exception_handler(static function (Throwable $e): void {
            error_log('[excepcion] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            self::pantallaDeError($e);
        });

        // Los errores fatales no pasan por el manejador de excepciones
        register_shutdown_function(static function (): void {
            $ultimo = error_get_last();
            if ($ultimo === null || !in_array($ultimo['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            error_log(sprintf('[fatal] %s en %s:%d', $ultimo['message'], $ultimo['file'], $ultimo['line']));

            if (!self::DEPURAR) {
                self::pantallaDeError(null);
            }
        });
    }

    /** Pagina de error sin rutas internas ni detalles del servidor */
    private static function pantallaDeError(?Throwable $e): void
    {
        if (headers_sent()) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        $detalle = '';
        if (self::DEPURAR && $e !== null) {
            $detalle = '<pre style="text-align:left;overflow:auto;background:#f1f5f9;padding:12px;'
                     . 'border-radius:8px;font-size:.8rem">'
                     . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
                     . '</pre>';
        }

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Error del sistema</title><style>'
           . 'body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f4f6fa;margin:0;'
           . 'padding:40px 16px;color:#1e293b}'
           . '.caja{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;'
           . 'border-radius:14px;padding:28px;text-align:center;box-shadow:0 4px 16px rgba(15,23,42,.08)}'
           . 'h1{color:#2C356D;font-size:1.25rem;margin:0 0 10px}'
           . 'p{color:#64748b;line-height:1.6;font-size:.94rem;margin:0 0 18px}'
           . 'a{display:inline-block;background:#2C356D;color:#fff;padding:11px 20px;'
           . 'border-radius:10px;text-decoration:none;font-weight:600;font-size:.9rem}'
           . '</style></head><body><div class="caja">'
           . '<h1>Ocurrió un problema inesperado</h1>'
           . '<p>El sistema registró el detalle del error para el administrador. '
           . 'Vuelve a intentarlo; si el problema continúa, avisa al área de sistemas.</p>'
           . $detalle
           . '<a href="' . htmlspecialchars(self::rutaBase() ?: '/', ENT_QUOTES, 'UTF-8') . '/">Volver al inicio</a>'
           . '</div></body></html>';
        exit;
    }

    // ------------------------------------------------------------------
    // 2. Cabeceras de seguridad
    // ------------------------------------------------------------------

    private static function enviarCabeceras(): void
    {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }

        // Impide que el navegador "adivine" el tipo de un archivo servido
        header('X-Content-Type-Options: nosniff');
        // Impide que el sistema se cargue dentro de un iframe ajeno (clickjacking)
        header('X-Frame-Options: DENY');
        // No filtra la direccion interna al salir hacia otro sitio
        header('Referrer-Policy: same-origin');
        // La geocerca y el escaner QR necesitan GPS y camara, pero SOLO desde
        // paginas de este mismo dominio: 'self' los habilita aqui y los sigue
        // bloqueando para cualquier iframe de terceros.
        //
        // Antes decia geolocation=(), que los desactivaba por completo: la
        // comprobacion de los 500 metros nunca llegaba a ejecutarse porque el
        // navegador negaba la ubicacion antes de preguntarle al alumno.
        header('Permissions-Policy: geolocation=(self), camera=(self), microphone=(), payment=()');

        // Politica de contenido: solo se ejecuta lo que sirve este mismo sitio.
        // 'unsafe-inline' sigue siendo necesario porque las vistas llevan sus
        // scripts y estilos incrustados.
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' data: blob:; "
            . "media-src 'self' blob:; "
            . "script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "form-action 'self'; "
            . "base-uri 'self'; "
            . "frame-ancestors 'none'"
        );
    }

    // ------------------------------------------------------------------
    // 3. URL publica para el contenido de los codigos QR
    // ------------------------------------------------------------------

    /**
     * ¿La visita llegó por HTTPS?
     *
     * En XAMPP basta con mirar $_SERVER['HTTPS']. Pero en un servidor de
     * produccion (Railway, Render, cualquier hosting con balanceador) el
     * cifrado TERMINA en el proxy: este habla HTTPS con el navegador y HTTP
     * con PHP, asi que $_SERVER['HTTPS'] llega vacio y el sistema creeria que
     * la conexion es insegura. Eso tenia tres consecuencias:
     *
     *   - los codigos QR apuntarian a http:// dentro de una pagina https://,
     *     y el navegador bloquearia el enlace por contenido mixto;
     *   - la cookie de sesion no se marcaria como segura;
     *   - la geolocalizacion y la camara quedarian desactivadas.
     *
     * El proxy avisa del protocolo real con la cabecera X-Forwarded-Proto.
     * Solo se hace caso de esa cabecera cuando CONFIA_EN_PROXY esta activo,
     * porque el cliente puede falsificarla si nadie la filtra delante.
     */
    public static function esHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        // Algunos servidores lo indican por el puerto
        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        if (!self::confiaEnProxy()) {
            return false;
        }

        $protocolo = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($protocolo !== '') {
            // Puede venir encadenada ("https, http"): manda la primera
            return str_starts_with($protocolo, 'https');
        }

        // Render y algunos balanceadores usan esta otra
        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
    }

    /**
     * Solo se confia en las cabeceras del proxy cuando la variable de entorno
     * CONFIAR_EN_PROXY vale 1. Se activa al desplegar; en local queda apagada.
     */
    public static function confiaEnProxy(): bool
    {
        $valor = getenv('CONFIAR_EN_PROXY');
        return $valor !== false && in_array(strtolower(trim($valor)), ['1', 'true', 'si', 'yes'], true);
    }

    /** Carpeta base del proyecto dentro del servidor (ej. /asistencia/public) */
    public static function rutaBase(): string
    {
        $dir = str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return ($dir === '/' || $dir === '') ? '' : $dir;
    }

    /**
     * Arma la URL absoluta que va DENTRO del codigo QR.
     *
     * El problema real: si el docente entra por http://localhost/... el QR
     * contendria "localhost", y para el celular del alumno localhost es su
     * propio telefono, asi que el enlace nunca abriria. Por eso, cuando el
     * docente esta en localhost se sustituye por la IP de red del servidor.
     */
    public static function urlPublica(string $ruta = ''): string
    {
        if (self::URL_PUBLICA !== '') {
            return rtrim(self::URL_PUBLICA, '/') . $ruta;
        }

        $protocolo = self::esHttps() ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

        // HTTP_HOST lo controla el cliente: se sanea antes de usarlo
        if (!preg_match('/^[A-Za-z0-9.\-\[\]:]{1,255}$/', $host)) {
            $host = 'localhost';
        }

        if (self::esHostLocal($host)) {
            $ip = self::ipDeRed();
            if ($ip !== null) {
                // Se conserva el puerto si el servidor no usa el 80
                $puerto = '';
                if (str_contains($host, ':')) {
                    $trozo = substr($host, strrpos($host, ':') + 1);
                    if ($trozo !== '80' && ctype_digit($trozo)) {
                        $puerto = ':' . $trozo;
                    }
                }
                $host = $ip . $puerto;
            }
        }

        return $protocolo . '://' . $host . self::rutaBase() . $ruta;
    }

    /**
     * ¿El sistema esta corriendo en un equipo del aula (XAMPP) o en un
     * servidor publico de internet?
     *
     * Sirve para no mostrar en produccion los avisos que solo tienen sentido
     * en local, como "entra por localhost en vez de por la IP": con un dominio
     * propio esa recomendacion no significa nada y solo confunde al docente.
     */
    public static function esEntornoLocal(): bool
    {
        $host = strtolower(explode(':', trim((string)($_SERVER['HTTP_HOST'] ?? ''), '[]'))[0]);

        if ($host === '' || self::esHostLocal($host)) {
            return true;
        }

        // Una IP de red privada (192.168.x, 10.x, 172.16-31.x) es el equipo
        // del aula compartido por wifi; cualquier dominio con nombre, no.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }

        return false;
    }

    /** true si el host escrito en el navegador solo funciona en esta maquina */
    public static function esHostLocal(?string $host = null): bool
    {
        $host = $host ?? (string)($_SERVER['HTTP_HOST'] ?? '');
        $host = strtolower(explode(':', trim($host, '[]'))[0]);

        return in_array($host, ['localhost', '127.0.0.1', '::1', ''], true);
    }

    /**
     * IP de la red local del servidor (192.168.x.x, 10.x.x.x, 172.16-31.x.x),
     * que es la unica que un celular conectado al mismo wifi puede alcanzar.
     * Devuelve null si no se pudo determinar.
     */
    public static function ipDeRed(): ?string
    {
        return self::ipsDeRed()[0] ?? null;
    }

    /**
     * Todas las IP de red local de la maquina, ordenadas de mas a menos
     * probable. Se muestran al docente para que sepa cual funciona: en un
     * equipo con VirtualBox, Docker o WSL hay adaptadores virtuales que el
     * celular NO puede alcanzar, y adivinar mal dejaria el QR inservible.
     *
     * @return string[]
     */
    public static function ipsDeRed(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $candidatas = [];

        // 1. La IP con la que el servidor se ve a si mismo
        $propia = (string)($_SERVER['SERVER_ADDR'] ?? '');
        if (self::esIpDeRed($propia)) {
            $candidatas[] = $propia;
        }

        // 2. Todas las direcciones del nombre de la maquina
        $nombre = @gethostname();
        if ($nombre !== false) {
            $lista = @gethostbynamel($nombre);
            if (is_array($lista)) {
                foreach ($lista as $ip) {
                    if (self::esIpDeRed($ip)) {
                        $candidatas[] = $ip;
                    }
                }
            }
        }

        $candidatas = array_values(array_unique($candidatas));

        // Se ordenan poniendo al final los rangos que suelen ser adaptadores
        // virtuales (VirtualBox usa 192.168.56.x; Docker y WSL, 172.x).
        usort($candidatas, static function (string $a, string $b): int {
            return self::prioridadIp($a) <=> self::prioridadIp($b);
        });

        $cache = $candidatas;
        return $cache;
    }

    /** Cuanto menor el numero, mas probable es que sea la red real del aula */
    private static function prioridadIp(string $ip): int
    {
        if (str_starts_with($ip, '192.168.56.')) {
            return 3;   // Adaptador anfitrion de VirtualBox
        }
        if (str_starts_with($ip, '172.')) {
            return 2;   // Docker / WSL en la mayoria de equipos
        }
        if (str_starts_with($ip, '169.254.')) {
            return 4;   // Sin DHCP: no sirve para nada
        }
        return 1;       // 192.168.x.x y 10.x.x.x normales
    }

    private static function esIpDeRed(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        // Se descartan las publicas y la de loopback: solo interesa la LAN
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false && !str_starts_with($ip, '127.');
    }
}
