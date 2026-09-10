<?php

require_once dirname(__DIR__) . '/config/database.php';

// Modelo Asistencia: entrada, salida, estado y motivo de cada alumno en una clase.

class Asistencia
{
    private const SELECT_BASE = "
        SELECT a.id, a.sesion_id, a.estudiante_id, a.hora_entrada, a.hora_salida,
               a.estado, a.motivo, a.motivo_detalle, a.origen, a.aprobacion,
               a.latitud, a.longitud, a.distancia_metros,
               e.codigo, e.cedula, e.nombre, e.apellido, e.semestre, e.token_qr,
               s.fecha, s.estado AS estado_sesion,
               m.nombre AS materia, c.ambiente, c.semestre AS semestre_curso,
               u.nombre AS docente_nombre, u.apellido AS docente_apellido
        FROM asistencias a
        JOIN estudiantes e ON a.estudiante_id = e.id
        JOIN sesiones    s ON a.sesion_id = s.id
        JOIN cursos      c ON s.curso_id = c.id
        JOIN materias    m ON c.materia_id = m.id
        JOIN usuarios    u ON s.docente_id = u.id";

    // ------------------------------------------------------------------
    // Registro de entrada y salida
    // ------------------------------------------------------------------

    /**
     * Registra la ENTRADA de un alumno. Devuelve 'ok', 'duplicada' o 'error'.
     * La clave UNIQUE (sesion_id, estudiante_id) es la garantia real contra el
     * doble registro: si dos escaneos llegan a la vez, el segundo INSERT falla
     * con el codigo 23000 y aqui se traduce a 'duplicada'.
     */
    public static function registrarEntrada(
        int $sesionId,
        int $estudianteId,
        string $origen = 'qr',
        string $aprobacion = 'aprobada',
        ?array $ubicacion = null
    ): string {
        $db = Database::conectar();
        $sql = "INSERT INTO asistencias
                    (sesion_id, estudiante_id, hora_entrada, estado, origen, aprobacion,
                     latitud, longitud, distancia_metros)
                VALUES (?, ?, NOW(), 'presente', ?, ?, ?, ?, ?)";

        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute([
                $sesionId, $estudianteId, $origen, $aprobacion,
                $ubicacion['latitud']   ?? null,
                $ubicacion['longitud']  ?? null,
                $ubicacion['distancia'] ?? null
            ]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'duplicada' : 'error';
        }
    }

    /**
     * Registra la SALIDA de un alumno al escanear el QR de salida.
     * Devuelve 'ok', 'sin_entrada' o 'ya_salio'.
     */
    public static function registrarSalida(int $sesionId, int $estudianteId): string
    {
        $db = Database::conectar();

        $stmt = $db->prepare("SELECT id, estado FROM asistencias WHERE sesion_id = ? AND estudiante_id = ? LIMIT 1");
        $stmt->execute([$sesionId, $estudianteId]);
        $fila = $stmt->fetch();

        // No se puede marcar salida de una clase en la que nunca entro
        if (!$fila) {
            return 'sin_entrada';
        }

        if ($fila['estado'] !== 'presente') {
            return 'ya_salio';
        }

        $update = $db->prepare(
            "UPDATE asistencias SET hora_salida = NOW(), estado = 'salio' WHERE id = ?"
        );

        return $update->execute([$fila['id']]) ? 'ok' : 'error';
    }

    public static function existe(int $sesionId, int $estudianteId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT id FROM asistencias WHERE sesion_id = ? AND estudiante_id = ? LIMIT 1");
        $stmt->execute([$sesionId, $estudianteId]);
        return (bool)$stmt->fetch();
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . " WHERE a.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ------------------------------------------------------------------
    // Control del docente sobre la lista de la clase
    // ------------------------------------------------------------------

    /**
     * Marca la salida anticipada de un alumno indicando el motivo.
     * Este es el caso de "se fue por una urgencia" o "lo sacaron de clase".
     */
    /**
     * Registra que el alumno se retiro antes de que terminara la clase.
     *
     * El estado depende del motivo y lo decide Catalogo::estadoDeSalida(): la
     * cita medica o el permiso del docente dejan la salida JUSTIFICADA,
     * mientras que el mal comportamiento no. Se guardan como estados distintos
     * y no como un mismo "salio antes" porque al cerrar el periodo es
     * justamente esa diferencia la que hay que poder contar por separado.
     */
    public static function marcarSalidaAnticipada(int $asistenciaId, string $motivo, string $detalle): bool
    {
        require_once __DIR__ . '/Catalogo.php';

        $estado = Catalogo::estadoDeSalida($motivo);

        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE asistencias
             SET estado = ?, hora_salida = NOW(), motivo = ?, motivo_detalle = ?
             WHERE id = ?"
        );
        return $stmt->execute([$estado, $motivo, ($detalle !== '' ? $detalle : null), $asistenciaId]);
    }

    /** Devuelve a un alumno al estado "en clase" (deshace una salida marcada por error) */
    public static function devolverAClase(int $asistenciaId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE asistencias
             SET estado = 'presente', hora_salida = NULL, motivo = NULL, motivo_detalle = NULL
             WHERE id = ?"
        );
        return $stmt->execute([$asistenciaId]);
    }

    /**
     * Elimina un registro de asistencia. Es el caso de un alumno que se registro
     * con el QR pero no esta fisicamente en el aula (le pasaron la foto del QR).
     */
    public static function eliminar(int $asistenciaId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("DELETE FROM asistencias WHERE id = ?");
        return $stmt->execute([$asistenciaId]);
    }

    /**
     * Aprueba el registro de alguien que no estaba matriculado, despues de que
     * el docente confirmo que si estaba en el aula.
     */
    public static function aprobar(int $asistenciaId): bool
    {
        $db = Database::conectar();
        return $db->prepare("UPDATE asistencias SET aprobacion = 'aprobada' WHERE id = ?")
                  ->execute([$asistenciaId]);
    }

    /** Cuantos registros esperan la confirmacion del docente en esta clase */
    public static function contarPendientes(int $sesionId): int
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS n FROM asistencias WHERE sesion_id = ? AND aprobacion = 'pendiente'"
        );
        $stmt->execute([$sesionId]);
        return (int)($stmt->fetch()['n'] ?? 0);
    }

    /** Comprueba que la asistencia pertenezca a una clase del docente indicado */
    public static function perteneceADocente(int $asistenciaId, int $docenteId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT a.id FROM asistencias a
             JOIN sesiones s ON a.sesion_id = s.id
             WHERE a.id = ? AND s.docente_id = ? LIMIT 1"
        );
        $stmt->execute([$asistenciaId, $docenteId]);
        return (bool)$stmt->fetch();
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** Lista en vivo de la clase: quien esta dentro y en que estado */
    public static function listarPorSesion(int $sesionId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . "
            WHERE a.sesion_id = ?
            ORDER BY a.hora_entrada DESC");
        $stmt->execute([$sesionId]);
        return $stmt->fetchAll();
    }

    /** Resumen de la clase para las tarjetas del panel del docente */
    public static function resumenSesion(int $sesionId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT
                COUNT(*)                                                AS total,
                SUM(estado = 'presente')                                AS presentes,
                SUM(estado = 'salio')                                   AS salieron,
                SUM(estado = 'salida_temprana')                         AS anticipadas,
                SUM(estado = 'salida_justificada')                      AS justificadas,
                SUM(origen = 'manual')                                  AS manuales,
                SUM(aprobacion = 'pendiente')                            AS pendientes
             FROM asistencias WHERE sesion_id = ?"
        );
        $stmt->execute([$sesionId]);
        $fila = $stmt->fetch() ?: [];

        return [
            'total'       => (int)($fila['total'] ?? 0),
            'presentes'   => (int)($fila['presentes'] ?? 0),
            'salieron'    => (int)($fila['salieron'] ?? 0),
            'anticipadas' => (int)($fila['anticipadas'] ?? 0),
            'justificadas'=> (int)($fila['justificadas'] ?? 0),
            'manuales'    => (int)($fila['manuales'] ?? 0),
            'pendientes'  => (int)($fila['pendientes'] ?? 0),
        ];
    }

    /**
     * Filtrado para los reportes. Todos los criterios son de seleccion,
     * ninguno de texto libre, tal como se pidio para el panel.
     */
    public static function filtrar(array $f): array
    {
        $db = Database::conectar();
        $sql = self::SELECT_BASE . " WHERE 1 = 1";
        $params = [];

        if (!empty($f['docente_id'])) {
            $sql .= " AND s.docente_id = ?";
            $params[] = (int)$f['docente_id'];
        }
        if (!empty($f['materia_id'])) {
            $sql .= " AND c.materia_id = ?";
            $params[] = (int)$f['materia_id'];
        }
        if (!empty($f['curso_id'])) {
            $sql .= " AND s.curso_id = ?";
            $params[] = (int)$f['curso_id'];
        }
        if (!empty($f['ambiente'])) {
            $sql .= " AND c.ambiente = ?";
            $params[] = $f['ambiente'];
        }
        if (!empty($f['semestre'])) {
            $sql .= " AND e.semestre = ?";
            $params[] = $f['semestre'];
        }
        if (!empty($f['estado'])) {
            $sql .= " AND a.estado = ?";
            $params[] = $f['estado'];
        }
        if (!empty($f['estudiante_id'])) {
            $sql .= " AND a.estudiante_id = ?";
            $params[] = (int)$f['estudiante_id'];
        }
        if (!empty($f['cedula'])) {
            $sql .= " AND e.cedula = ?";
            $params[] = $f['cedula'];
        }
        if (!empty($f['fecha_inicio'])) {
            $sql .= " AND s.fecha >= ?";
            $params[] = $f['fecha_inicio'];
        }
        if (!empty($f['fecha_fin'])) {
            $sql .= " AND s.fecha <= ?";
            $params[] = $f['fecha_fin'];
        }

        $sql .= " ORDER BY s.fecha DESC, a.hora_entrada DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Estadisticas (se dibujan como grafico de pastel)
    // ------------------------------------------------------------------

    /** Reparto de asistencias por materia */
    public static function porMateria(?int $docenteId = null): array
    {
        $db = Database::conectar();
        $sql = "SELECT m.nombre AS etiqueta, COUNT(a.id) AS total
                FROM asistencias a
                JOIN sesiones s ON a.sesion_id = s.id
                JOIN cursos   c ON s.curso_id = c.id
                JOIN materias m ON c.materia_id = m.id";
        $params = [];

        if ($docenteId !== null) {
            $sql .= " WHERE s.docente_id = ?";
            $params[] = $docenteId;
        }

        $sql .= " GROUP BY m.id, m.nombre HAVING total > 0 ORDER BY total DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Reparto por estado (en clase / salida registrada / salida anticipada) */
    public static function porEstado(?int $docenteId = null): array
    {
        $db = Database::conectar();
        $sql = "SELECT a.estado AS etiqueta, COUNT(a.id) AS total
                FROM asistencias a
                JOIN sesiones s ON a.sesion_id = s.id";
        $params = [];

        if ($docenteId !== null) {
            $sql .= " WHERE s.docente_id = ?";
            $params[] = $docenteId;
        }

        $sql .= " GROUP BY a.estado HAVING total > 0 ORDER BY total DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Reparto por ambiente (Aula / Laboratorio / Aula Interactiva) */
    public static function porAmbiente(?int $docenteId = null): array
    {
        $db = Database::conectar();
        $sql = "SELECT c.ambiente AS etiqueta, COUNT(a.id) AS total
                FROM asistencias a
                JOIN sesiones s ON a.sesion_id = s.id
                JOIN cursos   c ON s.curso_id = c.id";
        $params = [];

        if ($docenteId !== null) {
            $sql .= " WHERE s.docente_id = ?";
            $params[] = $docenteId;
        }

        $sql .= " GROUP BY c.ambiente HAVING total > 0 ORDER BY total DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Reparto por motivo de salida anticipada */
    public static function porMotivo(?int $docenteId = null): array
    {
        $db = Database::conectar();
        $sql = "SELECT a.motivo AS etiqueta, COUNT(a.id) AS total
                FROM asistencias a
                JOIN sesiones s ON a.sesion_id = s.id
                WHERE a.motivo IS NOT NULL";
        $params = [];

        if ($docenteId !== null) {
            $sql .= " AND s.docente_id = ?";
            $params[] = $docenteId;
        }

        $sql .= " GROUP BY a.motivo ORDER BY total DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function contarHoy(?int $docenteId = null): int
    {
        $db = Database::conectar();
        $sql = "SELECT COUNT(a.id) AS n FROM asistencias a
                JOIN sesiones s ON a.sesion_id = s.id
                WHERE s.fecha = CURDATE()";
        $params = [];

        if ($docenteId !== null) {
            $sql .= " AND s.docente_id = ?";
            $params[] = $docenteId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)($stmt->fetch()['n'] ?? 0);
    }

    public static function contarTotal(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM asistencias")->fetch()['n'] ?? 0);
    }
}
