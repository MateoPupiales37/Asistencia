<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Catalogo.php';

// Modelo Sesion: una clase concreta con sus dos codigos QR.
//
//   codigo_entrada -> se genera al abrir la clase, caduca a los 15 minutos
//   codigo_salida  -> lo genera el docente cuando quiere, caduca a los 15 minutos
//
// Los codigos son irrepetibles a nivel de toda la tabla (clave UNIQUE), asi que
// un QR de una clase pasada jamas sirve para registrarse en otra.

class Sesion
{
    private const SELECT_BASE = "
        SELECT s.*, m.nombre AS materia, m.codigo AS materia_codigo,
               c.ambiente, c.semestre,
               u.nombre AS docente_nombre, u.apellido AS docente_apellido
        FROM sesiones s
        JOIN cursos   c ON s.curso_id = c.id
        JOIN materias m ON c.materia_id = m.id
        JOIN usuarios u ON s.docente_id = u.id";

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    // Clase abierta del docente (solo puede haber una a la vez)
    public static function abiertaDelDocente(int $docenteId): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . "
            WHERE s.docente_id = ? AND s.estado = 'abierta'
            ORDER BY s.id DESC LIMIT 1");
        $stmt->execute([$docenteId]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . " WHERE s.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Busca la clase a la que corresponde un codigo escaneado.
     * Devuelve la sesion junto al tipo detectado ('entrada' u 'salida')
     * y si el codigo sigue vigente.
     */
    public static function buscarPorCodigo(string $codigo): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . "
            WHERE s.codigo_entrada = ? OR s.codigo_salida = ?
            LIMIT 1");
        $stmt->execute([$codigo, $codigo]);
        $sesion = $stmt->fetch();

        if (!$sesion) {
            return null;
        }

        $esEntrada = ($sesion['codigo_entrada'] === $codigo);
        $sesion['tipo_qr'] = $esEntrada ? 'entrada' : 'salida';
        $expira = $esEntrada ? $sesion['entrada_expira'] : $sesion['salida_expira'];
        $sesion['expira_en'] = $expira;
        $sesion['vigente']   = ($expira !== null && strtotime($expira) >= time());

        return $sesion;
    }

    // Clases abiertas en toda la institucion (monitoreo del administrador)
    public static function listarAbiertas(): array
    {
        $db = Database::conectar();
        return $db->query(self::SELECT_BASE . "
            WHERE s.estado = 'abierta'
            ORDER BY s.hora_inicio DESC")->fetchAll();
    }

    public static function historialDocente(int $docenteId, int $limite = 10): array
    {
        $db = Database::conectar();
        $limite = max(1, min(100, $limite));
        $stmt = $db->prepare(self::SELECT_BASE . "
            WHERE s.docente_id = ?
            ORDER BY s.id DESC
            LIMIT {$limite}");
        $stmt->execute([$docenteId]);
        return $stmt->fetchAll();
    }

    public static function historialGlobal(int $limite = 10): array
    {
        $db = Database::conectar();
        $limite = max(1, min(100, $limite));
        return $db->query(self::SELECT_BASE . " ORDER BY s.id DESC LIMIT {$limite}")->fetchAll();
    }

    // ------------------------------------------------------------------
    // Acciones sobre la clase
    // ------------------------------------------------------------------

    /** Abre una clase y genera su codigo QR de entrada, valido 15 minutos */
    /**
     * Abre una clase y genera su codigo QR de entrada, valido 15 minutos.
     *
     * Si el navegador del docente entrego coordenadas, quedan guardadas como
     * el centro de la geocerca: a partir de ahi, solo quien este dentro del
     * radio puede registrarse. Si no las entrego (pasa cuando se entra por
     * HTTP y no por HTTPS), la clase funciona igual pero sin esa proteccion.
     */
    public static function abrir(
        int $cursoId, int $docenteId,
        ?float $latitud = null, ?float $longitud = null, int $radio = 500
    ): ?array {
        $db = Database::conectar();
        $codigo = self::codigoUnico();

        if ($codigo === null) {
            return null;
        }

        $sql = "INSERT INTO sesiones
                    (curso_id, docente_id, fecha, hora_inicio, codigo_entrada, entrada_expira,
                     estado, latitud, longitud, radio_metros)
                VALUES (?, ?, CURDATE(), NOW(), ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 'abierta', ?, ?, ?)";
        $stmt = $db->prepare($sql);

        $ok = $stmt->execute([
            $cursoId, $docenteId, $codigo, Catalogo::MINUTOS_QR,
            $latitud, $longitud, max(50, min(5000, $radio))
        ]);

        if (!$ok) {
            return null;
        }

        return self::buscarPorId((int)$db->lastInsertId());
    }

    /** Corrige o agrega la ubicacion de una clase ya abierta */
    public static function guardarUbicacion(int $sesionId, int $docenteId, float $latitud, float $longitud, int $radio): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE sesiones SET latitud = ?, longitud = ?, radio_metros = ?
             WHERE id = ? AND docente_id = ? AND estado = 'abierta'"
        );
        return $stmt->execute([$latitud, $longitud, max(50, min(5000, $radio)), $sesionId, $docenteId]);
    }

    /**
     * Renueva el QR de entrada por otros 15 minutos (para los atrasados).
     *
     * Al hacerlo CADUCA el QR de salida: solo puede haber un codigo vivo a la
     * vez. Si convivieran, un alumno que llega tarde podria escanear por error
     * el de salida y quedar marcado como que ya se retiro de una clase en la
     * que ni siquiera habia entrado.
     */
    public static function renovarEntrada(int $sesionId, int $docenteId): bool
    {
        $db = Database::conectar();
        $codigo = self::codigoUnico();

        if ($codigo === null) {
            return false;
        }

        $sql = "UPDATE sesiones
                SET codigo_entrada = ?,
                    entrada_expira = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    salida_expira  = IF(salida_expira IS NULL, NULL, NOW())
                WHERE id = ? AND docente_id = ? AND estado = 'abierta'";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$codigo, Catalogo::MINUTOS_QR, $sesionId, $docenteId]);
    }

    /** Caduca el QR de entrada de inmediato, sin cerrar la clase */
    public static function cerrarEntrada(int $sesionId, int $docenteId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE sesiones SET entrada_expira = NOW()
             WHERE id = ? AND docente_id = ? AND estado = 'abierta'"
        );
        return $stmt->execute([$sesionId, $docenteId]);
    }

    /**
     * Genera el QR de salida de la clase activa, valido 15 minutos.
     *
     * Al generarlo se CADUCA el QR de entrada, porque la clase pasa a su etapa
     * de cierre: quien no alcanzo a marcar entrada ya no debe poder hacerlo
     * escaneando, y quien escanea ahora es porque se esta retirando.
     * Si el docente necesita reabrir la entrada, "Generar QR de entrada nuevo"
     * hace lo contrario y caduca el de salida.
     */
    public static function generarSalida(int $sesionId, int $docenteId): bool
    {
        $db = Database::conectar();
        $codigo = self::codigoUnico();

        if ($codigo === null) {
            return false;
        }

        $sql = "UPDATE sesiones
                SET codigo_salida = ?,
                    salida_expira  = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    entrada_expira = NOW()
                WHERE id = ? AND docente_id = ? AND estado = 'abierta'";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$codigo, Catalogo::MINUTOS_QR, $sesionId, $docenteId]);
    }

    /** Cierra la clase y da por terminada a toda la gente que seguia dentro */
    public static function cerrar(int $sesionId, int $docenteId): bool
    {
        $db = Database::conectar();

        $stmt = $db->prepare(
            "UPDATE sesiones SET estado = 'cerrada', hora_fin = NOW()
             WHERE id = ? AND docente_id = ? AND estado = 'abierta'"
        );

        if (!$stmt->execute([$sesionId, $docenteId]) || $stmt->rowCount() === 0) {
            return false;
        }

        // Los que nunca escanearon el QR de salida quedan con la hora de cierre
        $cierre = $db->prepare(
            "UPDATE asistencias SET hora_salida = NOW(), estado = 'salio'
             WHERE sesion_id = ? AND estado = 'presente'"
        );
        $cierre->execute([$sesionId]);

        return true;
    }

    // Cierre forzoso desde el panel del administrador (clase olvidada abierta)
    public static function cerrarPorAdmin(int $sesionId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT docente_id FROM sesiones WHERE id = ? AND estado = 'abierta' LIMIT 1");
        $stmt->execute([$sesionId]);
        $fila = $stmt->fetch();

        return $fila ? self::cerrar($sesionId, (int)$fila['docente_id']) : false;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** Genera un codigo de 8 caracteres que no exista ya en ninguna clase */
    private static function codigoUnico(int $intentos = 10): ?string
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT id FROM sesiones WHERE codigo_entrada = ? OR codigo_salida = ? LIMIT 1"
        );

        for ($i = 0; $i < $intentos; $i++) {
            $codigo = strtoupper(bin2hex(random_bytes(4)));
            $stmt->execute([$codigo, $codigo]);
            if (!$stmt->fetch()) {
                return $codigo;
            }
        }

        return null;
    }

    /** Segundos que le quedan de vida a un codigo (0 si ya caduco) */
    public static function segundosRestantes(?string $expira): int
    {
        if ($expira === null) {
            return 0;
        }
        return max(0, strtotime($expira) - time());
    }

    public static function entradaVigente(array $sesion): bool
    {
        return self::segundosRestantes($sesion['entrada_expira'] ?? null) > 0;
    }

    public static function salidaVigente(array $sesion): bool
    {
        return !empty($sesion['codigo_salida']) && self::segundosRestantes($sesion['salida_expira'] ?? null) > 0;
    }

    public static function contarTotal(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM sesiones")->fetch()['n'] ?? 0);
    }

    public static function contarHoy(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM sesiones WHERE fecha = CURDATE()")->fetch()['n'] ?? 0);
    }

    public static function contarPorDocente(int $docenteId): int
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT COUNT(*) AS n FROM sesiones WHERE docente_id = ?");
        $stmt->execute([$docenteId]);
        return (int)($stmt->fetch()['n'] ?? 0);
    }
}
