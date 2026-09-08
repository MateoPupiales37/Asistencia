<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Consentimiento informado para el uso de ubicación y cámara.
 *
 * El sistema le pide al navegador dos permisos que tocan datos personales:
 * la UBICACIÓN (para comprobar que quien se registra está en el aula) y la
 * CÁMARA (para leer el código QR). Antes de activarlos hay que pedir permiso
 * de forma expresa y guardar constancia de quién aceptó y cuándo.
 *
 * La versión del texto se guarda junto a la aceptación: si mañana cambian los
 * términos, basta con subir la versión para que el sistema vuelva a pedirlos,
 * y solo a quien aceptó una versión anterior.
 */
class Consentimiento
{
    /** Versión vigente del texto. Al cambiarla, se vuelve a pedir a todos. */
    public const VERSION = '1.0';

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /** ¿Esta persona ya aceptó la versión vigente de los términos? */
    public static function aceptado(string $tipo, ?int $personaId): bool
    {
        if (!$personaId || !self::tipoValido($tipo)) {
            return false;
        }

        $tabla = ($tipo === 'usuario') ? 'usuarios' : 'estudiantes';

        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT consentimiento_version FROM {$tabla} WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$personaId]);
        $fila = $stmt->fetch();

        return $fila !== false && ($fila['consentimiento_version'] ?? null) === self::VERSION;
    }

    /** Fecha en que aceptó, o null si nunca lo hizo */
    public static function fecha(string $tipo, ?int $personaId): ?string
    {
        if (!$personaId || !self::tipoValido($tipo)) {
            return null;
        }

        $tabla = ($tipo === 'usuario') ? 'usuarios' : 'estudiantes';

        $db = Database::conectar();
        $stmt = $db->prepare("SELECT consentimiento_en FROM {$tabla} WHERE id = ? LIMIT 1");
        $stmt->execute([$personaId]);
        $fila = $stmt->fetch();

        return $fila ? ($fila['consentimiento_en'] ?: null) : null;
    }

    // ------------------------------------------------------------------
    // Registro
    // ------------------------------------------------------------------

    /**
     * Deja constancia de la aceptación en dos sitios: en la ficha de la
     * persona (para saber de un vistazo si ya aceptó) y en el historial
     * (para poder demostrar cuándo y desde dónde lo hizo).
     */
    public static function registrar(string $tipo, int $personaId, bool $ubicacion = true, bool $camara = true): bool
    {
        if (!self::tipoValido($tipo) || $personaId < 1) {
            return false;
        }

        $tabla = ($tipo === 'usuario') ? 'usuarios' : 'estudiantes';
        $db = Database::conectar();

        try {
            $db->beginTransaction();

            $ficha = $db->prepare(
                "UPDATE {$tabla} SET consentimiento_en = NOW(), consentimiento_version = ? WHERE id = ?"
            );
            $ficha->execute([self::VERSION, $personaId]);

            $historial = $db->prepare(
                "INSERT INTO consentimientos
                    (tipo_persona, persona_id, version, usa_ubicacion, usa_camara, ip, navegador)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $historial->execute([
                $tipo,
                $personaId,
                self::VERSION,
                $ubicacion ? 1 : 0,
                $camara ? 1 : 0,
                self::ip(),
                mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
            ]);

            $db->commit();
            return true;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Retira el consentimiento: la persona puede cambiar de opinión.
     * A partir de ahí el sistema deja de pedirle ubicación y cámara, y sus
     * registros pasan a marcarse como "sin verificar".
     */
    public static function retirar(string $tipo, int $personaId): bool
    {
        if (!self::tipoValido($tipo) || $personaId < 1) {
            return false;
        }

        $tabla = ($tipo === 'usuario') ? 'usuarios' : 'estudiantes';

        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE {$tabla} SET consentimiento_en = NULL, consentimiento_version = NULL WHERE id = ?"
        );
        return $stmt->execute([$personaId]);
    }

    /** Historial de aceptaciones de una persona, de la más reciente a la más antigua */
    public static function historial(string $tipo, int $personaId): array
    {
        if (!self::tipoValido($tipo)) {
            return [];
        }

        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT version, usa_ubicacion, usa_camara, ip, aceptado_en
             FROM consentimientos
             WHERE tipo_persona = ? AND persona_id = ?
             ORDER BY aceptado_en DESC"
        );
        $stmt->execute([$tipo, $personaId]);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------

    private static function tipoValido(string $tipo): bool
    {
        return in_array($tipo, ['usuario', 'estudiante'], true);
    }

    /** IP del visitante, teniendo en cuenta que en produccion hay un proxy delante */
    private static function ip(): ?string
    {
        $reenviada = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($reenviada !== '') {
            // La cabecera puede traer varias IP encadenadas: la primera es la real
            $primera = trim(explode(',', $reenviada)[0]);
            if (filter_var($primera, FILTER_VALIDATE_IP)) {
                return $primera;
            }
        }

        $directa = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($directa, FILTER_VALIDATE_IP) ? $directa : null;
    }
}
