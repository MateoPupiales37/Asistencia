<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Modelo Carrera.
 *
 * Hoy el instituto tiene cargada una sola carrera, Desarrollo de Software, y
 * mientras eso sea asi la carrera casi no se nota en las pantallas. Existe
 * igual porque en cuanto entre la segunda hay materias que se llaman igual en
 * las dos ("Matematica I", "Ingles") y, sin este dato, quedarian mezcladas en
 * el mismo catalogo y en el mismo reporte.
 */
class Carrera
{
    private static ?array $cache = null;

    /** @return array<int,array{id:int,codigo:string,nombre:string,activa:int}> */
    public static function listar(bool $soloActivas = true): array
    {
        if ($soloActivas && self::$cache !== null) {
            return self::$cache;
        }

        $db  = Database::conectar();
        $sql = "SELECT id, codigo, nombre, activa FROM carreras";

        if ($soloActivas) {
            $sql .= " WHERE activa = 1";
        }

        $sql .= " ORDER BY nombre ASC";
        $filas = $db->query($sql)->fetchAll();

        if ($soloActivas) {
            self::$cache = $filas;
        }

        return $filas;
    }

    /** Listado con el numero de materias de cada una, para el panel del admin */
    public static function listarConUso(): array
    {
        $db = Database::conectar();
        return $db->query(
            "SELECT c.id, c.codigo, c.nombre, c.activa,
                    (SELECT COUNT(*) FROM materias m
                      WHERE m.carrera_id = c.id AND m.activa = 1) AS total_materias
             FROM carreras c
             ORDER BY c.nombre ASC"
        )->fetchAll();
    }

    public static function buscarPorId(?int $id): ?array
    {
        if (!$id) {
            return null;
        }

        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM carreras WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function existe(?int $id): bool
    {
        if (!$id) {
            return false;
        }

        $db = Database::conectar();
        $stmt = $db->prepare("SELECT id FROM carreras WHERE id = ? AND activa = 1 LIMIT 1");
        $stmt->execute([$id]);
        return (bool)$stmt->fetch();
    }

    /** La que se propone por defecto cuando solo hay una cargada */
    public static function porDefecto(): ?array
    {
        $activas = self::listar();
        return count($activas) === 1 ? $activas[0] : null;
    }

    /** Devuelve 'ok', 'duplicada' o 'error' */
    public static function crear(string $codigo, string $nombre): string
    {
        self::$cache = null;
        $db = Database::conectar();

        try {
            $stmt = $db->prepare("INSERT INTO carreras (codigo, nombre, activa) VALUES (?, ?, 1)");
            return $stmt->execute([strtoupper(trim($codigo)), trim($nombre)]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'duplicada' : 'error';
        }
    }

    public static function actualizar(int $id, string $codigo, string $nombre, int $activa): string
    {
        self::$cache = null;
        $db = Database::conectar();

        try {
            $stmt = $db->prepare("UPDATE carreras SET codigo = ?, nombre = ?, activa = ? WHERE id = ?");
            return $stmt->execute([strtoupper(trim($codigo)), trim($nombre), $activa, $id]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'duplicada' : 'error';
        }
    }

    public static function contar(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM carreras WHERE activa = 1")->fetch()['n'] ?? 0);
    }

    /** Siglas a partir del nombre: "Desarrollo de Software" -> "DSW" */
    public static function sugerirCodigo(string $nombre): string
    {
        $sinTildes = strtr($nombre, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'
        ]);

        $ignorar  = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'a', 'en'];
        $iniciales = '';

        foreach (preg_split('/\s+/', trim($sinTildes)) ?: [] as $palabra) {
            $limpia = preg_replace('/[^A-Za-z0-9]/', '', $palabra);
            if ($limpia !== '' && !in_array(mb_strtolower($limpia), $ignorar, true)) {
                $iniciales .= strtoupper($limpia[0]);
            }
        }

        return substr($iniciales, 0, 20);
    }
}
