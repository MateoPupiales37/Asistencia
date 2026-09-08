<?php

/**
 * Doble factor de autenticacion compatible con Google Authenticator.
 *
 * Implementa TOTP (RFC 6238), que es el estandar que usan Google
 * Authenticator, Microsoft Authenticator, Authy y FreeOTP. No hace falta
 * ninguna libreria ni conexion a internet: el codigo se calcula a partir de
 * un secreto compartido y de la hora actual.
 *
 * Como funciona, en corto:
 *
 *   1. Se genera un SECRETO aleatorio y se le entrega al usuario dentro de
 *      un codigo QR. La app lo guarda.
 *   2. Cada 30 segundos, app y servidor calculan por separado el mismo
 *      numero de 6 digitos: HMAC-SHA1(secreto, contador_de_tiempo).
 *   3. Si el numero que escribe el usuario coincide con el que calculo el
 *      servidor, es que tiene el telefono correcto en la mano.
 *
 * El secreto NUNCA viaja despues del alta, y los codigos caducan solos: por
 * eso esto protege aunque alguien conozca la contraseña.
 */
class Totp
{
    /** Segundos que dura cada codigo. 30 es el valor que asumen todas las apps */
    private const PERIODO = 30;

    /** Digitos del codigo */
    private const DIGITOS = 6;

    /**
     * Tolerancia hacia atras y adelante, en periodos.
     *
     * Con 1 se aceptan el codigo anterior, el actual y el siguiente: una
     * ventana de 90 segundos. Cubre el desfase normal entre el reloj del
     * telefono y el del servidor sin abrir demasiado la puerta.
     */
    private const TOLERANCIA = 1;

    /** Alfabeto base32 del estandar (RFC 4648), que es lo que leen las apps */
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ==================================================================
    // Secreto
    // ==================================================================

    /**
     * Genera un secreto nuevo en base32.
     *
     * 20 bytes (160 bits) es lo que recomienda el RFC para HMAC-SHA1.
     * Se usa random_bytes, el generador criptografico de PHP: con rand()
     * el secreto seria predecible y el doble factor no serviria de nada.
     */
    public static function generarSecreto(): string
    {
        return self::aBase32(random_bytes(20));
    }

    /** Comprueba que un texto tenga forma de secreto base32 valido */
    public static function esSecretoValido(?string $secreto): bool
    {
        return is_string($secreto)
            && $secreto !== ''
            && preg_match('/^[A-Z2-7]{16,64}$/', $secreto) === 1;
    }

    // ==================================================================
    // Codigos
    // ==================================================================

    /**
     * Calcula el codigo que corresponde a un instante dado.
     * Con $desplazamiento se piden los periodos vecinos.
     */
    public static function codigo(string $secreto, int $desplazamiento = 0, ?int $momento = null): string
    {
        $momento  = $momento ?? time();
        $contador = intdiv($momento, self::PERIODO) + $desplazamiento;

        // El contador viaja como entero de 8 bytes, big endian.
        // pack('J') existe desde PHP 5.6 y evita tener que partirlo a mano.
        $binario = pack('J', $contador);
        $hash    = hash_hmac('sha1', $binario, self::deBase32($secreto), true);

        // "Truncamiento dinamico" del RFC: el ultimo nibble del hash dice
        // desde que byte se toman los 4 bytes que forman el numero.
        $desde  = ord($hash[19]) & 0x0F;
        $numero = ((ord($hash[$desde])     & 0x7F) << 24)
                | ((ord($hash[$desde + 1]) & 0xFF) << 16)
                | ((ord($hash[$desde + 2]) & 0xFF) << 8)
                |  (ord($hash[$desde + 3]) & 0xFF);

        $codigo = $numero % (10 ** self::DIGITOS);

        return str_pad((string)$codigo, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica el codigo que escribio el usuario.
     *
     * La comparacion usa hash_equals, que tarda lo mismo acierte o falle:
     * con un == normal, el tiempo de respuesta iria filtrando cuantos
     * digitos son correctos y el codigo se podria adivinar por partes.
     */
    public static function verificar(?string $secreto, ?string $codigoEscrito, ?int $momento = null): bool
    {
        if (!self::esSecretoValido($secreto)) {
            return false;
        }

        $limpio = preg_replace('/\D/', '', (string)$codigoEscrito);

        if (strlen($limpio) !== self::DIGITOS) {
            return false;
        }

        for ($i = -self::TOLERANCIA; $i <= self::TOLERANCIA; $i++) {
            if (hash_equals(self::codigo($secreto, $i, $momento), $limpio)) {
                return true;
            }
        }

        return false;
    }

    /** Segundos que le quedan de vida al codigo actual */
    public static function segundosRestantes(?int $momento = null): int
    {
        return self::PERIODO - (($momento ?? time()) % self::PERIODO);
    }

    // ==================================================================
    // Alta en la aplicacion del telefono
    // ==================================================================

    /**
     * Arma la direccion otpauth:// que se mete dentro del codigo QR.
     * Es el formato que leen todas las aplicaciones autenticadoras.
     */
    public static function uri(string $secreto, string $cuenta, string $emisor): string
    {
        return 'otpauth://totp/'
             . rawurlencode($emisor) . ':' . rawurlencode($cuenta)
             . '?secret=' . $secreto
             . '&issuer=' . rawurlencode($emisor)
             . '&algorithm=SHA1'
             . '&digits=' . self::DIGITOS
             . '&period=' . self::PERIODO;
    }

    /**
     * El secreto en grupos de cuatro, para poder dictarlo o escribirlo a
     * mano cuando la camara no lee el QR.
     */
    public static function secretoLegible(string $secreto): string
    {
        return trim(chunk_split($secreto, 4, ' '));
    }

    // ==================================================================
    // Base32
    // ==================================================================

    private static function aBase32(string $bytes): string
    {
        $bits = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Base32 agrupa de a 5 bits; se rellena el ultimo grupo incompleto
        $bits  = str_pad($bits, (int)(ceil(strlen($bits) / 5) * 5), '0', STR_PAD_RIGHT);
        $salida = '';

        foreach (str_split($bits, 5) as $grupo) {
            $salida .= self::BASE32[bindec($grupo)];
        }

        return $salida;
    }

    private static function deBase32(string $secreto): string
    {
        $secreto = strtoupper(rtrim($secreto, '='));
        $bits    = '';

        for ($i = 0, $n = strlen($secreto); $i < $n; $i++) {
            $posicion = strpos(self::BASE32, $secreto[$i]);

            if ($posicion === false) {
                continue;   // Se ignora cualquier caracter ajeno al alfabeto
            }

            $bits .= str_pad(decbin($posicion), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $grupo) {
            // El ultimo grupo puede quedar incompleto por el relleno: se descarta
            if (strlen($grupo) === 8) {
                $bytes .= chr(bindec($grupo));
            }
        }

        return $bytes;
    }
}
