<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Modelo Materia: catalogo institucional de asignaturas.
 *
 * Las materias las crea UNICAMENTE el administrador. Se hizo asi a proposito:
 * si cada docente pudiera crearlas, el catalogo terminaria con la misma
 * asignatura escrita de tres formas distintas ("SGBD2", "Sgbd 2", "Base de
 * Datos 2") y los reportes por materia dejarian de cuadrar.
 *
 * La materia NO tiene semestre propio: el semestre lo define el CURSO, porque
 * la misma asignatura puede dictarse en distintos semestres y ambientes.
 */
class Materia
{
    public static function listar(bool $soloActivas = true): array
    {
        $db  = Database::conectar();
        $sql = "SELECT id, codigo, nombre, activa FROM materias";

        if ($soloActivas) {
            $sql .= " WHERE activa = 1";
        }

        $sql .= " ORDER BY nombre ASC";
        return $db->query($sql)->fetchAll();
    }

    /** Listado con el conteo de cursos y docentes asignados, para el panel del admin */
    public static function listarConUso(): array
    {
        $db = Database::conectar();
        return $db->query(
            "SELECT m.id, m.codigo, m.nombre, m.activa,
                    (SELECT COUNT(*) FROM cursos c WHERE c.materia_id = m.id AND c.activo = 1) AS total_cursos,
                    (SELECT COUNT(DISTINCT c.docente_id) FROM cursos c WHERE c.materia_id = m.id AND c.activo = 1) AS total_docentes
             FROM materias m
             ORDER BY m.nombre ASC"
        )->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM materias WHERE id = ? LIMIT 1");
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
    public static function crear(string $codigo, string $nombre): string
    {
        $db = Database::conectar();

        try {
            $stmt = $db->prepare("INSERT INTO materias (codigo, nombre, activa) VALUES (?, ?, 1)");
            return $stmt->execute([strtoupper(trim($codigo)), trim($nombre)]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            // El codigo y el nombre son UNIQUE: la base es la que garantiza
            // de verdad que no haya materias repetidas
            return ($e->getCode() === '23000') ? 'duplicada' : 'error';
        }
    }

    public static function actualizar(int $id, string $codigo, string $nombre, int $activa): string
    {
        $db = Database::conectar();

        try {
            $stmt = $db->prepare("UPDATE materias SET codigo = ?, nombre = ?, activa = ? WHERE id = ?");
            return $stmt->execute([strtoupper(trim($codigo)), trim($nombre), $activa, $id]) ? 'ok' : 'error';
        } catch (PDOException $e) {
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

    public static function contar(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM materias WHERE activa = 1")->fetch()['n'] ?? 0);
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
