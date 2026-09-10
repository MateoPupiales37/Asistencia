<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Modelo Periodo academico.
 *
 * Un periodo es el ciclo lectivo del instituto: "2026-1", "Octubre 2026 -
 * Marzo 2027". Todo lo academico cuelga de el, y esa es su razon de ser:
 * cuando empieza el ciclo siguiente, las materias, las asignaciones de
 * docentes y las clases dictadas del ciclo anterior no desaparecen, pero
 * dejan de mezclarse con las nuevas en los listados y los reportes.
 *
 * El administrador elige con que periodo trabaja al entrar. Esa eleccion
 * queda en su sesion y filtra todo lo que ve despues.
 */
class Periodo
{
    /** Cache por peticion: el listado se consulta en casi todas las pantallas */
    private static ?array $cache = null;

    /** @return array<int,array{id:int,nombre:string,fecha_inicio:string,fecha_fin:string,activo:int}> */
    public static function listar(bool $soloActivos = false): array
    {
        if (!$soloActivos && self::$cache !== null) {
            return self::$cache;
        }

        $db  = Database::conectar();
        $sql = "SELECT id, nombre, fecha_inicio, fecha_fin, activo FROM periodos";

        if ($soloActivos) {
            $sql .= " WHERE activo = 1";
        }

        // El mas reciente primero: es con el que se trabaja el 99% del tiempo
        $sql .= " ORDER BY fecha_inicio DESC, id DESC";
        $filas = $db->query($sql)->fetchAll();

        if (!$soloActivos) {
            self::$cache = $filas;
        }

        return $filas;
    }

    /** Listado con cuanto contiene cada periodo, para la pantalla de eleccion */
    public static function listarConUso(): array
    {
        $db = Database::conectar();
        return $db->query(
            "SELECT p.id, p.nombre, p.fecha_inicio, p.fecha_fin, p.activo,
                    (SELECT COUNT(*) FROM materias m
                      WHERE m.periodo_id = p.id AND m.activa = 1) AS total_materias,
                    (SELECT COUNT(*) FROM cursos c
                       JOIN materias m2 ON c.materia_id = m2.id
                      WHERE m2.periodo_id = p.id AND c.activo = 1) AS total_cursos,
                    (SELECT COUNT(*) FROM sesiones s
                       JOIN cursos c2   ON s.curso_id  = c2.id
                       JOIN materias m3 ON c2.materia_id = m3.id
                      WHERE m3.periodo_id = p.id) AS total_clases
             FROM periodos p
             ORDER BY p.fecha_inicio DESC, p.id DESC"
        )->fetchAll();
    }

    public static function buscarPorId(?int $id): ?array
    {
        if (!$id) {
            return null;
        }

        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM periodos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * El periodo que le toca al sistema cuando nadie ha elegido ninguno.
     *
     * Se prefiere el que contiene la fecha de hoy, que es el que esta
     * realmente en curso. Si ninguno la contiene (el instituto esta entre dos
     * ciclos), se usa el activo mas reciente para no dejar la pantalla vacia.
     */
    public static function actual(): ?array
    {
        $db = Database::conectar();

        $stmt = $db->query(
            "SELECT * FROM periodos
              WHERE activo = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin
              ORDER BY fecha_inicio DESC LIMIT 1"
        );

        $enCurso = $stmt->fetch();
        if ($enCurso) {
            return $enCurso;
        }

        return $db->query(
            "SELECT * FROM periodos WHERE activo = 1 ORDER BY fecha_inicio DESC LIMIT 1"
        )->fetch() ?: null;
    }

    public static function existe(?int $id): bool
    {
        return self::buscarPorId($id) !== null;
    }

    /** Devuelve 'ok', 'duplicado', 'fechas' o 'error' */
    public static function crear(string $nombre, string $inicio, string $fin): string
    {
        if ($inicio >= $fin) {
            return 'fechas';
        }

        self::$cache = null;
        $db = Database::conectar();

        try {
            $stmt = $db->prepare(
                "INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, activo) VALUES (?, ?, ?, 1)"
            );
            return $stmt->execute([trim($nombre), $inicio, $fin]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'duplicado' : 'error';
        }
    }

    public static function actualizar(int $id, string $nombre, string $inicio, string $fin, int $activo): string
    {
        if ($inicio >= $fin) {
            return 'fechas';
        }

        self::$cache = null;
        $db = Database::conectar();

        try {
            $stmt = $db->prepare(
                "UPDATE periodos SET nombre = ?, fecha_inicio = ?, fecha_fin = ?, activo = ? WHERE id = ?"
            );
            return $stmt->execute([trim($nombre), $inicio, $fin, $activo, $id]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'duplicado' : 'error';
        }
    }

    /**
     * Cierra un periodo: deja de aparecer para elegir, pero todo lo que
     * contiene se conserva y se puede seguir consultando. No existe borrado
     * porque llevarse un periodo por delante seria llevarse el historial
     * academico completo de ese ciclo.
     */
    public static function cerrar(int $id): bool
    {
        self::$cache = null;
        $db = Database::conectar();
        return $db->prepare("UPDATE periodos SET activo = 0 WHERE id = ?")->execute([$id]);
    }

    public static function reabrir(int $id): bool
    {
        self::$cache = null;
        $db = Database::conectar();
        return $db->prepare("UPDATE periodos SET activo = 1 WHERE id = ?")->execute([$id]);
    }

    public static function contar(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM periodos WHERE activo = 1")->fetch()['n'] ?? 0);
    }

    /** "01/01/2026 al 31/12/2026", para mostrarlo junto al nombre */
    public static function rango(array $periodo): string
    {
        return date('d/m/Y', strtotime($periodo['fecha_inicio']))
             . ' al ' . date('d/m/Y', strtotime($periodo['fecha_fin']));
    }

    /**
     * Sugiere el nombre del siguiente periodo a partir de la fecha de inicio:
     * de enero a junio es el "-1" del ano, de julio en adelante el "-2".
     */
    public static function sugerirNombre(string $inicio): string
    {
        $momento = strtotime($inicio) ?: time();
        return date('Y', $momento) . '-' . ((int)date('n', $momento) <= 6 ? '1' : '2');
    }
}
