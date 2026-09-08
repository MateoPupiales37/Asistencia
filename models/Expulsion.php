<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Bloqueo temporal de un estudiante en una clase.
 *
 * El problema que resuelve: el docente elimina de la lista a alguien que no
 * está en el aula, y el alumno vuelve a escanear el mismo QR y se registra
 * otra vez. El docente lo saca, el alumno entra: un bucle que el docente
 * siempre pierde, porque él tiene que estar mirando y el alumno no.
 *
 * Al eliminar un registro queda un bloqueo de 4 horas sobre esa clase
 * concreta. No afecta a las demás materias del alumno ni a las clases del
 * día siguiente: solo a la clase de la que lo sacaron.
 */
class Expulsion
{
    /** Horas que dura el bloqueo tras ser retirado de una clase */
    public const HORAS_BLOQUEO = 4;

    /**
     * Registra el bloqueo. Si ya existía uno para ese alumno en esa clase,
     * se renueva: el reloj vuelve a empezar desde ahora.
     */
    public static function registrar(int $sesionId, int $estudianteId, int $docenteId, string $motivo = ''): bool
    {
        $db = Database::conectar();

        $db->prepare("DELETE FROM expulsiones WHERE sesion_id = ? AND estudiante_id = ?")
           ->execute([$sesionId, $estudianteId]);

        $stmt = $db->prepare(
            "INSERT INTO expulsiones (sesion_id, estudiante_id, docente_id, bloqueado_hasta, motivo)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?)"
        );

        return $stmt->execute([
            $sesionId, $estudianteId, $docenteId,
            self::HORAS_BLOQUEO,
            ($motivo !== '' ? mb_substr($motivo, 0, 200) : null)
        ]);
    }

    /**
     * Devuelve el bloqueo vigente, o null si el alumno puede registrarse.
     * Incluye los minutos que faltan, para poder decírselo con claridad.
     */
    public static function vigente(int $sesionId, int $estudianteId): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT id, bloqueado_hasta, motivo,
                    TIMESTAMPDIFF(MINUTE, NOW(), bloqueado_hasta) AS minutos_restantes
             FROM expulsiones
             WHERE sesion_id = ? AND estudiante_id = ? AND bloqueado_hasta > NOW()
             LIMIT 1"
        );
        $stmt->execute([$sesionId, $estudianteId]);
        return $stmt->fetch() ?: null;
    }

    /** Levanta el bloqueo: el docente reconsideró y lo deja volver */
    public static function levantar(int $sesionId, int $estudianteId): bool
    {
        $db = Database::conectar();
        return $db->prepare("DELETE FROM expulsiones WHERE sesion_id = ? AND estudiante_id = ?")
                  ->execute([$sesionId, $estudianteId]);
    }

    /** Bloqueos activos de una clase, para mostrárselos al docente */
    public static function deSesion(int $sesionId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT x.id, x.estudiante_id, x.bloqueado_hasta, x.motivo,
                    TIMESTAMPDIFF(MINUTE, NOW(), x.bloqueado_hasta) AS minutos_restantes,
                    e.codigo, e.nombre, e.apellido
             FROM expulsiones x
             JOIN estudiantes e ON x.estudiante_id = e.id
             WHERE x.sesion_id = ? AND x.bloqueado_hasta > NOW()
             ORDER BY x.creado_en DESC"
        );
        $stmt->execute([$sesionId]);
        return $stmt->fetchAll();
    }

    /** Texto para el alumno: cuánto le falta para poder volver a intentarlo */
    public static function mensaje(array $bloqueo): string
    {
        $minutos = max(1, (int)$bloqueo['minutos_restantes']);

        $espera = ($minutos >= 60)
            ? intdiv($minutos, 60) . ' h ' . ($minutos % 60) . ' min'
            : $minutos . ' minutos';

        return 'El docente te retiró de esta clase, así que no puedes volver a '
             . "registrarte en ella. Podrás intentarlo de nuevo en {$espera}. "
             . 'Si fue un error, háblalo con tu docente.';
    }
}
