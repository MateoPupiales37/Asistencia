<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Modelo Matricula: que estudiante pertenece a que curso.
 *
 * Es la pieza que faltaba para poder comprobar de verdad una asistencia.
 * Antes, cualquiera que tuviera el codigo QR escribia un nombre y quedaba
 * registrado: no habia forma de saber si esa persona pertenecia al curso ni
 * si el nombre estaba bien escrito. Con la matricula, el sistema reconoce al
 * alumno contra una lista previa y lo que no calza queda a la vista del
 * docente para que lo apruebe o lo rechace.
 */
class Matricula
{
    private const SELECT_ESTUDIANTE = "
        SELECT e.id, e.codigo, e.cedula, e.nombre, e.apellido, e.semestre,
               e.telefono, e.token_qr, e.activo, m.creado_en AS matriculado_en
        FROM matriculas m
        JOIN estudiantes e ON m.estudiante_id = e.id";

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /**
     * Estudiantes matriculados en un curso.
     *
     * $filtro admite:
     *   'nuevos'      -> matriculados en las ultimas 24 horas. Es lo que
     *                    permite ver, despues de una importacion, exactamente
     *                    quienes entraron y a quienes hay que avisarles.
     *   'sin_telefono'-> a quienes no se les puede enviar nada todavia
     *   'con_telefono'-> los que si se pueden contactar
     */
    public static function estudiantesDeCurso(int $cursoId, bool $soloActivos = true, string $filtro = ''): array
    {
        $db  = Database::conectar();
        $sql = self::SELECT_ESTUDIANTE . " WHERE m.curso_id = ?";

        if ($soloActivos) {
            $sql .= " AND e.activo = 1";
        }

        if ($filtro === 'nuevos') {
            $sql .= " AND m.creado_en >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        } elseif ($filtro === 'sin_telefono') {
            $sql .= " AND (e.telefono IS NULL OR e.telefono = '')";
        } elseif ($filtro === 'con_telefono') {
            $sql .= " AND e.telefono IS NOT NULL AND e.telefono <> ''";
        }

        $sql .= " ORDER BY m.creado_en DESC, e.apellido ASC, e.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$cursoId]);
        return $stmt->fetchAll();
    }

    /** Cuantos se matricularon en las ultimas 24 horas */
    public static function contarNuevos(int $cursoId): int
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS n FROM matriculas m
             JOIN estudiantes e ON m.estudiante_id = e.id
             WHERE m.curso_id = ? AND e.activo = 1
               AND m.creado_en >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute([$cursoId]);
        return (int)($stmt->fetch()['n'] ?? 0);
    }

    /** Cursos en los que esta matriculado un estudiante */
    public static function cursosDeEstudiante(int $estudianteId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT c.id, c.ambiente, c.semestre,
                    mat.nombre AS materia, mat.codigo AS materia_codigo,
                    u.nombre AS docente_nombre, u.apellido AS docente_apellido
             FROM matriculas m
             JOIN cursos   c   ON m.curso_id = c.id
             JOIN materias mat ON c.materia_id = mat.id
             JOIN usuarios u   ON c.docente_id = u.id
             WHERE m.estudiante_id = ? AND c.activo = 1
             ORDER BY mat.nombre ASC"
        );
        $stmt->execute([$estudianteId]);
        return $stmt->fetchAll();
    }

    /** La comprobacion clave: ¿este alumno pertenece a este curso? */
    public static function existe(int $estudianteId, int $cursoId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT id FROM matriculas WHERE estudiante_id = ? AND curso_id = ? LIMIT 1"
        );
        $stmt->execute([$estudianteId, $cursoId]);
        return (bool)$stmt->fetch();
    }

    /** Estudiantes que AUN NO estan en el curso, para el selector de matricula */
    public static function candidatos(int $cursoId, string $semestre = ''): array
    {
        $db  = Database::conectar();
        $sql = "SELECT e.id, e.codigo, e.cedula, e.nombre, e.apellido, e.semestre
                FROM estudiantes e
                WHERE e.activo = 1
                  AND e.id NOT IN (SELECT estudiante_id FROM matriculas WHERE curso_id = ?)";
        $params = [$cursoId];

        if ($semestre !== '') {
            $sql .= " AND e.semestre = ?";
            $params[] = $semestre;
        }

        $sql .= " ORDER BY e.apellido ASC, e.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function contarPorCurso(int $cursoId): int
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS n FROM matriculas m
             JOIN estudiantes e ON m.estudiante_id = e.id
             WHERE m.curso_id = ? AND e.activo = 1"
        );
        $stmt->execute([$cursoId]);
        return (int)($stmt->fetch()['n'] ?? 0);
    }

    public static function contarTotal(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM matriculas")->fetch()['n'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Acciones
    // ------------------------------------------------------------------

    /** Devuelve 'ok', 'duplicada' o 'error' */
    public static function matricular(int $estudianteId, int $cursoId): string
    {
        $db = Database::conectar();

        try {
            $stmt = $db->prepare("INSERT INTO matriculas (estudiante_id, curso_id) VALUES (?, ?)");
            return $stmt->execute([$estudianteId, $cursoId]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'duplicada' : 'error';
        }
    }

    /**
     * Matricula varios estudiantes de una sola vez (carga masiva y matricula
     * en lote). Devuelve cuantos entraron y cuantos ya estaban.
     */
    public static function matricularVarios(array $estudianteIds, int $cursoId): array
    {
        $nuevas = 0;
        $repetidas = 0;

        foreach ($estudianteIds as $id) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }

            $resultado = self::matricular($id, $cursoId);
            if ($resultado === 'ok') {
                $nuevas++;
            } elseif ($resultado === 'duplicada') {
                $repetidas++;
            }
        }

        return ['nuevas' => $nuevas, 'repetidas' => $repetidas];
    }

    public static function quitar(int $estudianteId, int $cursoId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("DELETE FROM matriculas WHERE estudiante_id = ? AND curso_id = ?");
        return $stmt->execute([$estudianteId, $cursoId]);
    }
}
