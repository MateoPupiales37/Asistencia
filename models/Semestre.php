<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Modelo Semestre.
 *
 * Antes los semestres eran una lista fija escrita dentro de Catalogo.php:
 * agregar o quitar uno obligaba a editar el codigo. Ahora viven en la base y
 * los administra el administrador desde su panel.
 *
 * Se guarda el NOMBRE del semestre (no su id) dentro de estudiantes y cursos,
 * igual que antes, para no romper el historial ya registrado. Por eso el
 * nombre es UNIQUE: es lo que enlaza las tablas en la practica.
 */
class Semestre
{
    /** Cache por peticion: el listado se pide muchas veces en una sola pantalla */
    private static ?array $cache = null;

    /** @return array<int,array{id:int,nombre:string,orden:int,activo:int}> */
    public static function listar(bool $soloActivos = true): array
    {
        if ($soloActivos && self::$cache !== null) {
            return self::$cache;
        }

        $db  = Database::conectar();
        $sql = "SELECT id, nombre, orden, activo FROM semestres";

        if ($soloActivos) {
            $sql .= " WHERE activo = 1";
        }

        $sql .= " ORDER BY orden ASC, nombre ASC";
        $filas = $db->query($sql)->fetchAll();

        if ($soloActivos) {
            self::$cache = $filas;
        }

        return $filas;
    }

    /**
     * Solo los nombres, que es lo que consumen los <select> de las vistas.
     * Reemplaza a la antigua constante Catalogo::SEMESTRES.
     *
     * @return string[]
     */
    public static function nombres(): array
    {
        return array_column(self::listar(), 'nombre');
    }

    public static function esValido(?string $nombre): bool
    {
        return $nombre !== null && $nombre !== '' && in_array($nombre, self::nombres(), true);
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM semestres WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorNombre(string $nombre): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM semestres WHERE nombre = ? LIMIT 1");
        $stmt->execute([trim($nombre)]);
        return $stmt->fetch() ?: null;
    }

    /** Devuelve 'ok', 'duplicado' o 'error' */
    public static function crear(string $nombre, int $orden): string
    {
        self::$cache = null;
        $db = Database::conectar();

        try {
            $stmt = $db->prepare("INSERT INTO semestres (nombre, orden) VALUES (?, ?)");
            return $stmt->execute([trim($nombre), $orden]) ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'duplicado' : 'error';
        }
    }

    /**
     * Renombrar un semestre obliga a arrastrar el cambio a estudiantes y
     * cursos, porque ahi se guarda el nombre y no el id. Se hace dentro de
     * una transaccion para que no quede a medias.
     */
    public static function actualizar(int $id, string $nombre, int $orden, int $activo): string
    {
        self::$cache = null;
        $db = Database::conectar();

        $actual = self::buscarPorId($id);
        if (!$actual) {
            return 'error';
        }

        $nombre = trim($nombre);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE semestres SET nombre = ?, orden = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $orden, $activo, $id]);

            if ($actual['nombre'] !== $nombre) {
                $db->prepare("UPDATE estudiantes SET semestre = ? WHERE semestre = ?")
                   ->execute([$nombre, $actual['nombre']]);
                $db->prepare("UPDATE cursos SET semestre = ? WHERE semestre = ?")
                   ->execute([$nombre, $actual['nombre']]);
            }

            $db->commit();
            return 'ok';
        } catch (PDOException $e) {
            $db->rollBack();
            return ($e->getCode() === '23000') ? 'duplicado' : 'error';
        }
    }

    /**
     * Cuenta cuantos registros dependen de un semestre. Se consulta ANTES de
     * borrarlo: eliminar un semestre en uso dejaria estudiantes y cursos
     * apuntando a un semestre que ya no existe.
     */
    public static function enUso(int $id): array
    {
        $semestre = self::buscarPorId($id);

        if (!$semestre) {
            return ['estudiantes' => 0, 'cursos' => 0];
        }

        $db = Database::conectar();

        $e = $db->prepare("SELECT COUNT(*) AS n FROM estudiantes WHERE semestre = ?");
        $e->execute([$semestre['nombre']]);

        $c = $db->prepare("SELECT COUNT(*) AS n FROM cursos WHERE semestre = ?");
        $c->execute([$semestre['nombre']]);

        return [
            'estudiantes' => (int)($e->fetch()['n'] ?? 0),
            'cursos'      => (int)($c->fetch()['n'] ?? 0),
        ];
    }

    /** Devuelve 'ok' o 'en_uso' */
    public static function eliminar(int $id): string
    {
        $uso = self::enUso($id);

        if ($uso['estudiantes'] > 0 || $uso['cursos'] > 0) {
            return 'en_uso';
        }

        self::$cache = null;
        $db = Database::conectar();
        $db->prepare("DELETE FROM semestres WHERE id = ?")->execute([$id]);
        return 'ok';
    }

    public static function contar(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM semestres WHERE activo = 1")->fetch()['n'] ?? 0);
    }
}
