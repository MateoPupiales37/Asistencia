<?php

require_once dirname(__DIR__) . '/config/database.php';

// Modelo Curso: una materia dictada por un docente en un ambiente concreto.
// La misma materia puede repetirse en Aula, Laboratorio y Aula Interactiva,
// y cada combinacion es un curso distinto con sus propias clases y QR.

class Curso
{
    // Consulta base reutilizada: siempre se necesita el nombre de la materia
    private const SELECT_BASE = "
        SELECT c.id, c.materia_id, c.docente_id, c.ambiente, c.semestre, c.activo,
               m.nombre AS materia, m.codigo AS materia_codigo,
               u.nombre AS docente_nombre, u.apellido AS docente_apellido
        FROM cursos c
        JOIN materias m ON c.materia_id = m.id
        JOIN usuarios u ON c.docente_id = u.id";

    // Cursos de un docente, con el conteo de clases dictadas
    public static function listarPorDocente(int $docenteId): array
    {
        $db = Database::conectar();
        $sql = self::SELECT_BASE . "
                WHERE c.docente_id = ? AND c.activo = 1
                ORDER BY m.nombre ASC, c.ambiente ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$docenteId]);
        $cursos = $stmt->fetchAll();

        // Conteo de clases por curso en una sola consulta adicional
        if (empty($cursos)) {
            return [];
        }

        $conteos = $db->prepare("SELECT COUNT(*) AS n FROM sesiones WHERE curso_id = ?");
        $alumnos = $db->prepare(
            "SELECT COUNT(*) AS n FROM matriculas m
             JOIN estudiantes e ON m.estudiante_id = e.id
             WHERE m.curso_id = ? AND e.activo = 1"
        );

        foreach ($cursos as &$curso) {
            $conteos->execute([$curso['id']]);
            $curso['total_clases'] = (int)($conteos->fetch()['n'] ?? 0);

            $alumnos->execute([$curso['id']]);
            $curso['total_matriculados'] = (int)($alumnos->fetch()['n'] ?? 0);
        }
        unset($curso);

        return $cursos;
    }

    /**
     * Todos los cursos de la institucion, con su materia y su docente.
     * Es la vista que necesita el administrador para saber quien dicta que.
     */
    public static function listarTodos(bool $soloActivos = true): array
    {
        $db  = Database::conectar();
        $sql = self::SELECT_BASE;

        if ($soloActivos) {
            $sql .= " WHERE c.activo = 1";
        }

        $sql .= " ORDER BY m.nombre ASC, c.semestre ASC";
        return $db->query($sql)->fetchAll();
    }

    /** Cursos de una materia concreta: que docentes la dictan y donde */
    public static function listarPorMateria(int $materiaId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . "
            WHERE c.materia_id = ? AND c.activo = 1
            ORDER BY c.semestre ASC");
        $stmt->execute([$materiaId]);
        $cursos = $stmt->fetchAll();

        // Cuantos alumnos tiene matriculados cada uno
        $conteo = $db->prepare(
            "SELECT COUNT(*) AS n FROM matriculas m
             JOIN estudiantes e ON m.estudiante_id = e.id
             WHERE m.curso_id = ? AND e.activo = 1"
        );

        foreach ($cursos as &$curso) {
            $conteo->execute([$curso['id']]);
            $curso['total_matriculados'] = (int)($conteo->fetch()['n'] ?? 0);
        }
        unset($curso);

        return $cursos;
    }

    /**
     * Archiva un curso desde el panel del administrador, sin exigir que le
     * pertenezca (la version del docente si lo exige, para que nadie toque
     * los cursos de otro).
     */
    public static function desactivarPorAdmin(int $id): bool
    {
        $db = Database::conectar();
        return $db->prepare("UPDATE cursos SET activo = 0 WHERE id = ?")->execute([$id]);
    }

    /**
     * Quien dicta ya una materia. Devuelve null si esta libre.
     *
     * Sostiene la regla academica: una materia pertenece a UN solo docente.
     * No hace falta filtrar tambien por semestre, como se hacia antes: la
     * materia ya nace con su semestre, su carrera y su periodo, asi que dos
     * materias del mismo nombre en semestres distintos son dos filas
     * diferentes y cada una puede tener su propio docente.
     */
    /**
     * ¿La materia tiene alguna asignacion, activa o retirada?
     *
     * Se pregunta antes de mover una materia de periodo: si ya tuvo docente,
     * cuelgan de ella las clases dictadas y sus asistencias, y llevarsela al
     * ciclo siguiente descuadraria los dos a la vez.
     */
    public static function tieneAlguno(int $materiaId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT id FROM cursos WHERE materia_id = ? LIMIT 1");
        $stmt->execute([$materiaId]);
        return (bool)$stmt->fetch();
    }

    public static function docenteDeMateria(int $materiaId): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT c.id, c.docente_id, u.nombre AS docente_nombre, u.apellido AS docente_apellido
             FROM cursos c
             JOIN usuarios u ON c.docente_id = u.id
             WHERE c.materia_id = ? AND c.activo = 1
             LIMIT 1"
        );
        $stmt->execute([$materiaId]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . " WHERE c.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // Verifica que el curso pertenezca al docente: impide que un docente
    // abra clases o vea asistencias de los cursos de otro
    public static function perteneceA(int $cursoId, int $docenteId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT id FROM cursos WHERE id = ? AND docente_id = ? LIMIT 1");
        $stmt->execute([$cursoId, $docenteId]);
        return (bool)$stmt->fetch();
    }

    public static function crear(int $materiaId, int $docenteId, string $ambiente, string $semestre): string
    {
        $db = Database::conectar();
        $sql = "INSERT INTO cursos (materia_id, docente_id, ambiente, semestre) VALUES (?, ?, ?, ?)";

        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute([$materiaId, $docenteId, $ambiente, $semestre]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            // La clave UNIQUE curso_unico impide duplicar la misma combinacion
            return ($e->getCode() === '23000') ? 'duplicado' : 'error';
        }
    }

    /**
     * Archiva un curso (baja logica).
     *
     * NO se borra nada: el curso queda con activo = 0 y sus clases y
     * asistencias siguen intactas y visibles en los reportes. Se archiva y no
     * se elimina justamente por eso: borrarlo se llevaria por delante el
     * historial academico de los alumnos que si asistieron.
     */
    public static function desactivar(int $id, int $docenteId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE cursos SET activo = 0 WHERE id = ? AND docente_id = ?");
        return $stmt->execute([$id, $docenteId]);
    }

    /** Devuelve un curso archivado a la lista activa del docente */
    public static function reactivar(int $id, int $docenteId): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE cursos SET activo = 1 WHERE id = ? AND docente_id = ?");
        return $stmt->execute([$id, $docenteId]);
    }

    /** Version del administrador: puede restaurar cualquier curso */
    public static function reactivarPorAdmin(int $id): bool
    {
        $db = Database::conectar();
        return $db->prepare("UPDATE cursos SET activo = 1 WHERE id = ?")->execute([$id]);
    }

    /**
     * Cursos ARCHIVADOS de un docente.
     *
     * Antes no existia: al archivar un curso desaparecia de la pantalla y no
     * habia ninguna forma de volver a verlo ni de recuperarlo, aunque siguiera
     * guardado en la base. Esto lo hace visible otra vez.
     */
    public static function listarArchivadosPorDocente(int $docenteId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . "
            WHERE c.docente_id = ? AND c.activo = 0
            ORDER BY m.nombre ASC, c.ambiente ASC");
        $stmt->execute([$docenteId]);
        $cursos = $stmt->fetchAll();

        if (empty($cursos)) {
            return [];
        }

        $clases = $db->prepare("SELECT COUNT(*) AS n FROM sesiones WHERE curso_id = ?");
        foreach ($cursos as &$curso) {
            $clases->execute([$curso['id']]);
            $curso['total_clases'] = (int)($clases->fetch()['n'] ?? 0);
        }
        unset($curso);

        return $cursos;
    }

    /** Asignaciones archivadas de una materia, para el panel del administrador */
    public static function listarArchivadosPorMateria(int $materiaId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . "
            WHERE c.materia_id = ? AND c.activo = 0
            ORDER BY c.semestre ASC");
        $stmt->execute([$materiaId]);
        return $stmt->fetchAll();
    }

    // Etiqueta legible: "Sistemas Web - Aula Interactiva (Cuarto Semestre)"
    public static function etiqueta(array $curso): string
    {
        return sprintf(
            '%s - %s (%s)',
            $curso['materia'] ?? '',
            $curso['ambiente'] ?? '',
            $curso['semestre'] ?? ''
        );
    }

    public static function contarTotal(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM cursos WHERE activo = 1")->fetch()['n'] ?? 0);
    }
}
