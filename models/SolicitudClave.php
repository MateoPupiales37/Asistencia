<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Solicitudes de restablecimiento de contraseña.
 *
 * Flujo completo:
 *
 *   1. El docente olvida su clave y pulsa "Solicitar clave" en el login.
 *   2. Queda una solicitud PENDIENTE y al administrador le suena la campanita.
 *   3. El administrador la atiende: se genera un enlace de un solo uso que
 *      caduca en 30 minutos y se le envia al docente por WhatsApp.
 *   4. El docente abre el enlace y elige su propia contraseña.
 *
 * Se manda un ENLACE y no la contraseña porque una clave escrita en un chat
 * de WhatsApp queda ahi para siempre, visible para cualquiera que tome el
 * telefono. El enlace, en cambio, se quema al usarse y caduca solo.
 */
class SolicitudClave
{
    /** Minutos que vive el enlace antes de caducar */
    public const MINUTOS_VALIDEZ = 30;

    /**
     * Registra una solicitud.
     *
     * Si el docente ya tiene una pendiente no se crea otra: asi, pulsar el
     * boton diez veces no le llena la campanita al administrador.
     */
    public static function crear(int $usuarioId): string
    {
        $db = Database::conectar();

        $stmt = $db->prepare(
            "SELECT id FROM solicitudes_clave WHERE usuario_id = ? AND estado = 'pendiente' LIMIT 1"
        );
        $stmt->execute([$usuarioId]);

        if ($stmt->fetch()) {
            return 'ya_existe';
        }

        $insert = $db->prepare("INSERT INTO solicitudes_clave (usuario_id, estado) VALUES (?, 'pendiente')");
        return $insert->execute([$usuarioId]) ? 'ok' : 'error';
    }

    /** Solicitudes pendientes, con los datos del docente que las pidio */
    public static function pendientes(): array
    {
        $db = Database::conectar();
        return $db->query(
            "SELECT s.id, s.creado_en, s.usuario_id,
                    u.nombre, u.apellido, u.correo, u.telefono, u.rol
             FROM solicitudes_clave s
             JOIN usuarios u ON s.usuario_id = u.id
             WHERE s.estado = 'pendiente'
             ORDER BY s.creado_en ASC"
        )->fetchAll();
    }

    public static function contarPendientes(): int
    {
        $db = Database::conectar();
        return (int)($db->query(
            "SELECT COUNT(*) AS n FROM solicitudes_clave WHERE estado = 'pendiente'"
        )->fetch()['n'] ?? 0);
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT s.*, u.nombre, u.apellido, u.correo, u.telefono
             FROM solicitudes_clave s
             JOIN usuarios u ON s.usuario_id = u.id
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Marca la solicitud como atendida y devuelve el token de un solo uso.
     * El token se genera con random_bytes, no con rand(): tiene que ser
     * imposible de adivinar, porque quien lo tenga puede cambiar la clave.
     */
    public static function atender(int $id, int $adminId): ?string
    {
        $db    = Database::conectar();
        $token = bin2hex(random_bytes(32));

        $stmt = $db->prepare(
            "UPDATE solicitudes_clave
             SET estado = 'atendida',
                 token = ?,
                 token_expira = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                 atendida_en = NOW(),
                 atendida_por = ?
             WHERE id = ? AND estado = 'pendiente'"
        );

        if (!$stmt->execute([$token, self::MINUTOS_VALIDEZ, $adminId, $id]) || $stmt->rowCount() === 0) {
            return null;
        }

        return $token;
    }

    public static function rechazar(int $id, int $adminId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE solicitudes_clave
             SET estado = 'rechazada', atendida_en = NOW(), atendida_por = ?
             WHERE id = ? AND estado = 'pendiente'"
        );
        return $stmt->execute([$adminId, $id]);
    }

    /**
     * Busca una solicitud por su token, solo si sigue vigente y sin usar.
     * Devuelve null si el enlace caduco, ya se uso o no existe.
     */
    public static function porToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT s.*, u.nombre, u.apellido, u.correo
             FROM solicitudes_clave s
             JOIN usuarios u ON s.usuario_id = u.id
             WHERE s.token = ?
               AND s.usado_en IS NULL
               AND s.token_expira > NOW()
             LIMIT 1"
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    /** Quema el enlace: a partir de aqui ya no sirve */
    public static function marcarUsado(int $id): bool
    {
        $db = Database::conectar();
        return $db->prepare("UPDATE solicitudes_clave SET usado_en = NOW() WHERE id = ?")->execute([$id]);
    }

    /**
     * Genera un enlace directo de WhatsApp con el mensaje ya escrito.
     * No se envia nada por detras: se abre WhatsApp Web (o la app) con el
     * texto listo y el administrador solo pulsa enviar. Funciona sin
     * configurar ningun servidor de correo ni pagar una pasarela.
     */
    public static function enlaceWhatsapp(?string $telefono, string $mensaje): ?string
    {
        $digitos = preg_replace('/\D/', '', (string)$telefono);

        if ($digitos === '') {
            return null;
        }

        // WhatsApp exige el codigo de pais. En Ecuador el numero local
        // empieza por 0, que se reemplaza por el 593.
        if (str_starts_with($digitos, '0')) {
            $digitos = '593' . substr($digitos, 1);
        } elseif (!str_starts_with($digitos, '593')) {
            $digitos = '593' . $digitos;
        }

        return 'https://wa.me/' . $digitos . '?text=' . rawurlencode($mensaje);
    }
}
