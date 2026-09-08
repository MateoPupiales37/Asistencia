<?php

// La configuracion general define la zona horaria del instituto, que esta
// clase necesita para poner el reloj de MySQL en hora.
require_once __DIR__ . '/app.php';

// Archivo de conexion a la base de datos
// Utiliza PDO para realizar consultas seguras con sentencias preparadas
//
// ---------------------------------------------------------------------------
// COMO CONFIGURAR LA CONTRASEÑA DE MySQL
// ---------------------------------------------------------------------------
// XAMPP instala MySQL con el usuario 'root' y contraseña VACIA ('').
// Si tu servidor tiene otra contraseña (por ejemplo '12345'), cambiala en la
// constante DB_PASS de abajo, o define la variable de entorno DB_PASS.
// ---------------------------------------------------------------------------

class Database
{
    // Valores por defecto para una instalacion estandar de XAMPP
    private const DB_HOST = 'localhost';
    private const DB_PORT = '3306';
    private const DB_NAME = 'asistencia_qr';
    private const DB_USER = 'root';
    private const DB_PASS = '';          // <-- Cambia aqui si tu MySQL tiene clave

    private static ?PDO $conexion = null;

    public static function conectar(): PDO
    {
        if (self::$conexion !== null) {
            return self::$conexion;
        }

        $host   = self::leerEntorno('DB_HOST', self::DB_HOST);
        $port   = self::leerEntorno('DB_PORT', self::DB_PORT);
        $dbname = self::leerEntorno('DB_NAME', self::DB_NAME);
        $user   = self::leerEntorno('DB_USER', self::DB_USER);
        $pass   = self::leerEntorno('DB_PASS', self::DB_PASS);

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            self::$conexion = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ]);

            self::igualarZonaHoraria();
        } catch (PDOException $e) {
            self::mostrarErrorConexion($e, $dbname);
        }

        return self::$conexion;
    }

    /**
     * Pone el reloj de MySQL en la misma hora que el de PHP.
     *
     * En XAMPP los dos van en la hora local y no se nota, pero en un servidor
     * de produccion MySQL suele correr en UTC mientras PHP usa la zona del
     * instituto (UTC-5). Ahi aparece un fallo silencioso y muy confuso:
     *
     *   MySQL calcula   entrada_expira = NOW() + los minutos del QR  -> en UTC
     *   PHP lee esa fecha con strtotime()                     -> como UTC-5
     *
     * ...y las cinco horas de diferencia se suman al plazo: un codigo QR que
     * debia durar unos minutos aparecia como "Caduca en 299:45" y
     * seguia siendo valido toda la tarde, que es justo lo que la caducidad
     * tenia que impedir.
     *
     * Se corrige fijando en la conexion el desfase horario real de la zona
     * configurada en la aplicacion.
     */
    private static function igualarZonaHoraria(): void
    {
        /*
         * La zona se toma de App::ZONA_HORARIA y NO de date_default_timezone_get(),
         * porque esta ultima depende de que App::iniciar() ya se haya ejecutado.
         * Un script que use los modelos sin arrancar la aplicacion (una tarea
         * programada, una migracion) heredaria la zona por defecto de PHP y
         * dejaria el reloj de MySQL desplazado sin que nada lo advirtiera.
         */
        $zona = class_exists('App') ? App::ZONA_HORARIA : date_default_timezone_get();

        // Desfase actual con formato "+HH:MM". Se calcula en cada conexion
        // porque cambia con el horario de verano donde se aplica.
        try {
            $ahora = new DateTime('now', new DateTimeZone($zona));
        } catch (Exception $e) {
            $ahora = new DateTime('now');
        }

        $desfase = $ahora->format('P');

        try {
            self::$conexion->exec("SET time_zone = '{$desfase}'");
        } catch (PDOException $e) {
            // Algunos servidores no tienen cargadas las tablas de zonas
            // horarias. No es motivo para tumbar la aplicacion: se registra
            // y se sigue, aunque las horas puedan quedar desplazadas.
            error_log('[base de datos] No se pudo fijar la zona horaria: ' . $e->getMessage());
        }
    }

    // Prepara un texto para usarlo dentro de un LIKE.
    // Sin esto, si el usuario busca "50%" o "EST_1", los caracteres % y _ se
    // interpretan como comodines y la busqueda devuelve resultados incorrectos.
    public static function comodinLike(string $texto): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto) . '%';
    }

    // Lee una variable de entorno respetando el valor "cadena vacia" como valido.
    // Solo cuando la variable NO existe se usa el valor por defecto.
    private static function leerEntorno(string $clave, string $porDefecto): string
    {
        $valor = getenv($clave);
        return ($valor === false) ? $porDefecto : $valor;
    }

    // Muestra una pantalla de error clara y sin exponer credenciales ni rutas internas
    private static function mostrarErrorConexion(PDOException $e, string $dbname): void
    {
        $codigo = $e->getCode();

        if ($codigo === 1045 || str_contains($e->getMessage(), 'Access denied')) {
            $causa    = 'La contraseña del usuario de MySQL no coincide.';
            $solucion = 'Abre <code>config/database.php</code> y escribe en <code>DB_PASS</code> la contraseña real de tu usuario root de MySQL (en XAMPP suele estar vacía).';
        } elseif ($codigo === 1049) {
            $causa    = "La base de datos <code>{$dbname}</code> no existe todavía.";
            $solucion = 'Abre phpMyAdmin, entra a la pestaña <b>Importar</b> y carga el archivo <code>database/database.sql</code>.';
        } else {
            $causa    = 'No se pudo establecer la conexión con el servidor MySQL.';
            $solucion = 'Verifica que el módulo <b>MySQL</b> esté iniciado en el Panel de Control de XAMPP.';
        }

        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<title>Error de conexión - ISTPET</title><style>'
           . 'body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f1f5f9;margin:0;padding:40px 16px;color:#1e293b}'
           . '.caja{max-width:620px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px 32px;box-shadow:0 4px 16px rgba(15,23,42,.08)}'
           . 'h1{color:#b91c1c;font-size:1.3rem;margin:0 0 6px}h2{font-size:.95rem;color:#334155;margin:20px 0 6px}'
           . 'p{line-height:1.6;font-size:.94rem;margin:0}code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.88rem}'
           . '</style></head><body><div class="caja">'
           . '<h1>No se pudo conectar con la base de datos</h1>'
           . '<p style="color:#64748b;font-size:.88rem">Sistema de Asistencia QR &bull; ISTPET</p>'
           . '<h2>Causa probable</h2><p>' . $causa . '</p>'
           . '<h2>Cómo solucionarlo</h2><p>' . $solucion . '</p>'
           . '</div></body></html>';
        exit;
    }
}
