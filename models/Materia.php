<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Periodo.php';
require_once __DIR__ . '/Carrera.php';

/**
 * Modelo Materia: catalogo institucional de asignaturas.
 *
 * Las materias las crea UNICAMENTE el administrador. Se hizo asi a proposito:
 * si cada docente pudiera crearlas, el catalogo terminaria con la misma
 * asignatura escrita de tres formas distintas ("SGBD2", "Sgbd 2", "Base de
 * Datos 2") y los reportes por materia dejarian de cuadrar.
 *
 * UBICACION DE LA MATERIA
 * Una materia no flota suelta: pertenece a una CARRERA, se dicta en un
 * SEMESTRE y ocurre dentro de un PERIODO academico. Esos tres datos son los
 * que la identifican de verdad. Por eso "Programacion Web / Tercer Semestre /
 * Desarrollo de Software / 2026-1" y la misma materia del periodo siguiente
 * son dos filas distintas: cada una con su docente y su historial, sin que
 * las clases de un ciclo se mezclen con las del otro.
 *
 * De ahi sale tambien la regla de una sola asignacion por materia: como el
 * semestre y el periodo ya vienen dentro de la materia, decir "esta materia
 * es de tal docente" no necesita ninguna condicion mas.
 */
class Materia
{
    /** Campos comunes a los listados, con el nombre de la carrera resuelto */
    private const SELECT_BASE =
        "SELECT m.id, m.codigo, m.nombre, m.semestre, m.activa,
                m.carrera_id, m.periodo_id,
                c.nombre AS carrera, c.codigo AS carrera_codigo,
                p.nombre AS periodo
         FROM materias m
         LEFT JOIN carreras c ON m.carrera_id = c.id
         LEFT JOIN periodos p ON m.periodo_id = p.id";

    /**
     * @param bool     $soloActivas Excluye las materias dadas de baja
     * @param int|null $periodoId   Acota al periodo elegido por el administrador
     */
    public static function listar(bool $soloActivas = true, ?int $periodoId = null): array
    {
        $db  = Database::conectar();
        $sql = self::SELECT_BASE;

        $condiciones = [];
        $parametros  = [];

        if ($soloActivas) {
            $condiciones[] = "m.activa = 1";
        }

        if ($periodoId) {
            $condiciones[] = "m.periodo_id = ?";
            $parametros[]  = $periodoId;
        }

        if ($condiciones) {
            $sql .= " WHERE " . implode(' AND ', $condiciones);
        }

        // Por semestre y luego por nombre: asi el desplegable sale en el mismo
        // orden en que el docente piensa su malla
        $sql .= " ORDER BY m.semestre ASC, m.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    /** Listado con el conteo de cursos y docentes asignados, para el panel del admin */
    public static function listarConUso(?int $periodoId = null): array
    {
        $db = Database::conectar();

        $sql = "SELECT m.id, m.codigo, m.nombre, m.semestre, m.activa,
                       m.carrera_id, m.periodo_id,
                       ca.nombre AS carrera, ca.codigo AS carrera_codigo,
                       p.nombre  AS periodo,
                       (SELECT COUNT(*) FROM cursos c
                         WHERE c.materia_id = m.id AND c.activo = 1) AS total_cursos,
                       (SELECT COUNT(DISTINCT c.docente_id) FROM cursos c
                         WHERE c.materia_id = m.id AND c.activo = 1) AS total_docentes
                FROM materias m
                LEFT JOIN carreras ca ON m.carrera_id = ca.id
                LEFT JOIN periodos p  ON m.periodo_id = p.id";

        $parametros = [];

        if ($periodoId) {
            $sql .= " WHERE m.periodo_id = ?";
            $parametros[] = $periodoId;
        }

        $sql .= " ORDER BY m.semestre ASC, m.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(self::SELECT_BASE . " WHERE m.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function existe(int $id): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT id FROM materias WHERE id = ? AND activa = 1 LIMIT 1");
        $stmt->execute([$id]);
        return (bool)$stmt->fetch();
    }

    /** Devuelve 'ok', 'duplicada' o 'error' */
    public static function crear(
        string $codigo,
        string $nombre,
        string $semestre,
        ?int $carreraId,
        ?int $periodoId
    ): string {
        $db = Database::conectar();

        try {
            $stmt = $db->prepare(
                "INSERT INTO materias (codigo, nombre, semestre, carrera_id, periodo_id, activa)
                 VALUES (?, ?, ?, ?, ?, 1)"
            );
            $ok = $stmt->execute([
                strtoupper(trim($codigo)), trim($nombre), trim($semestre), $carreraId, $periodoId
            ]);
            return $ok ? 'ok' : 'error';
        } catch (PDOException $e) {
            // La combinacion nombre + semestre + carrera + periodo es UNIQUE,
            // igual que el codigo dentro de un periodo: la base es la que
            // garantiza de verdad que no haya materias repetidas
            return ($e->getCode() === '23000') ? 'duplicada' : 'error';
        }
    }

    public static function actualizar(
        int $id,
        string $codigo,
        string $nombre,
        string $semestre,
        ?int $carreraId,
        ?int $periodoId,
        int $activa
    ): string {
        $db = Database::conectar();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "UPDATE materias
                    SET codigo = ?, nombre = ?, semestre = ?, carrera_id = ?, periodo_id = ?, activa = ?
                  WHERE id = ?"
            );
            $stmt->execute([
                strtoupper(trim($codigo)), trim($nombre), trim($semestre),
                $carreraId, $periodoId, $activa, $id
            ]);

            // Los cursos guardan el semestre por su cuenta (viene de antes de
            // que la materia lo tuviera). Si el administrador corrige el
            // semestre de la materia, hay que arrastrarlo: si no, el curso
            // seguiria diciendo "Tercer Semestre" mientras la materia ya dice
            // "Cuarto", y las dos pantallas mostrarian cosas distintas.
            $db->prepare("UPDATE cursos SET semestre = ? WHERE materia_id = ?")
               ->execute([trim($semestre), $id]);

            $db->commit();
            return 'ok';
        } catch (PDOException $e) {
            $db->rollBack();
            return ($e->getCode() === '23000') ? 'duplicada' : 'error';
        }
    }

    /**
     * Baja logica. No se borra de verdad porque los cursos, las clases y las
     * asistencias ya dictadas apuntan a esta materia: eliminarla se llevaria
     * por delante el historial academico.
     */
    public static function desactivar(int $id): bool
    {
        $db = Database::conectar();
        return $db->prepare("UPDATE materias SET activa = 0 WHERE id = ?")->execute([$id]);
    }

    public static function activar(int $id): bool
    {
        $db = Database::conectar();
        return $db->prepare("UPDATE materias SET activa = 1 WHERE id = ?")->execute([$id]);
    }

    public static function contar(?int $periodoId = null): int
    {
        $db = Database::conectar();

        if ($periodoId) {
            $stmt = $db->prepare("SELECT COUNT(*) AS n FROM materias WHERE activa = 1 AND periodo_id = ?");
            $stmt->execute([$periodoId]);
            return (int)($stmt->fetch()['n'] ?? 0);
        }

        return (int)($db->query("SELECT COUNT(*) AS n FROM materias WHERE activa = 1")->fetch()['n'] ?? 0);
    }

    /**
     * Copia las materias de un periodo al siguiente.
     *
     * Al abrir un ciclo la malla es casi la misma que la del anterior, y
     * volver a escribirla materia por materia es donde aparecen los errores
     * de tipeo. Se copian sin docente asignado: quien la dicta se decide en
     * cada periodo.
     *
     * @return int Cuantas materias se copiaron
     */
    public static function copiarPeriodo(int $origenId, int $destinoId): int
    {
        $db = Database::conectar();

        $stmt = $db->prepare(
            "INSERT IGNORE INTO materias (codigo, nombre, semestre, carrera_id, periodo_id, activa)
             SELECT codigo, nombre, semestre, carrera_id, ?, 1
             FROM materias
             WHERE periodo_id = ? AND activa = 1"
        );
        $stmt->execute([$destinoId, $origenId]);

        return $stmt->rowCount();
    }

    /**
     * Sugiere un codigo corto a partir del nombre, para que el admin no tenga
     * que inventarlo: "Programacion de Aplicaciones" -> "PROAPL".
     */
    public static function sugerirCodigo(string $nombre): string
    {
        $sinTildes = strtr($nombre, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'
        ]);

        // Se ignoran las palabras de enlace, que no aportan al codigo
        $ignorar  = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'a', 'en'];
        $palabras = preg_split('/\s+/', trim($sinTildes)) ?: [];
        $utiles   = [];

        foreach ($palabras as $palabra) {
            $limpia = preg_replace('/[^A-Za-z0-9]/', '', $palabra);
            if ($limpia !== '' && !in_array(mb_strtolower($limpia), $ignorar, true)) {
                $utiles[] = strtoupper($limpia);
            }
        }

        if (empty($utiles)) {
            return '';
        }

        // Una palabra: sus primeras 6 letras. Varias: 3 de cada una.
        $codigo = (count($utiles) === 1)
            ? substr($utiles[0], 0, 6)
            : implode('', array_map(static fn($p) => substr($p, 0, 3), array_slice($utiles, 0, 3)));

        return substr($codigo, 0, 20);
    }
}
