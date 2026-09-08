<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Catalogo.php';

// Modelo Estudiante: padron de alumnos.
// Cada alumno tiene un token_qr irrepetible que es el contenido de su carnet QR.
// El estudiante NO tiene cuenta ni contraseña: solo escanea y llena el formulario.

class Estudiante
{
    // Busca por el codigo institucional escrito a mano (ej. EST001)
    public static function buscarPorCodigo(string $codigo): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM estudiantes WHERE codigo = ? LIMIT 1");
        $stmt->execute([strtoupper($codigo)]);
        return $stmt->fetch() ?: null;
    }

    // Busca por numero de cedula: es la via de respaldo cuando el alumno no
    // recuerda su codigo institucional. Se normaliza primero porque el alumno
    // la escribe con guiones o espacios segun el teclado del celular.
    public static function buscarPorCedula(string $cedula): ?array
    {
        $cedula = Catalogo::normalizarCedula($cedula);

        if ($cedula === '') {
            return null;
        }

        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM estudiantes WHERE cedula = ? LIMIT 1");
        $stmt->execute([$cedula]);
        return $stmt->fetch() ?: null;
    }

    // Busca por el token del carnet QR personal
    public static function buscarPorToken(string $token): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM estudiantes WHERE token_qr = ? LIMIT 1");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM estudiantes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // Busca por nombre y apellido exactos: sirve para reconocer a un alumno
    // que se registra por primera vez pero ya estaba en el padron
    public static function buscarPorNombre(string $nombre, string $apellido): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT * FROM estudiantes WHERE nombre = ? AND apellido = ? LIMIT 1"
        );
        $stmt->execute([$nombre, $apellido]);
        return $stmt->fetch() ?: null;
    }

    // Listado con filtros de seleccion (no de texto libre)
    public static function listar(string $semestre = ''): array
    {
        $db = Database::conectar();
        $sql = "SELECT * FROM estudiantes WHERE activo = 1";
        $params = [];

        if ($semestre !== '') {
            $sql .= " AND semestre = ?";
            $params[] = $semestre;
        }

        $sql .= " ORDER BY apellido ASC, nombre ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Crea un estudiante generando su codigo correlativo y su token de carnet QR.
     * Devuelve la fila creada, o null si no se pudo guardar.
     *
     * Si llega una cedula ya registrada, NO se crea un duplicado: se devuelve
     * la ficha que ya existe, que es justo lo que se quiere cuando el alumno
     * vuelve a registrarse escribiendo su cedula en otra clase.
     */
    public static function crear(string $nombre, string $apellido, string $semestre, ?string $cedula = null, ?string $telefono = null): ?array
    {
        $db = Database::conectar();

        $cedula = ($cedula !== null) ? Catalogo::normalizarCedula($cedula) : '';
        if ($cedula !== '') {
            $existente = self::buscarPorCedula($cedula);
            if ($existente) {
                return $existente;
            }
        }

        // Se reintenta por si dos registros simultaneos piden el mismo correlativo
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = self::siguienteCodigo();
            $token  = bin2hex(random_bytes(16));

            try {
                $stmt = $db->prepare(
                    "INSERT INTO estudiantes (codigo, cedula, telefono, nombre, apellido, semestre, token_qr)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $codigo,
                    ($cedula !== '' ? $cedula : null),
                    self::normalizarTelefono($telefono),
                    $nombre, $apellido, $semestre, $token
                ]);
                return self::buscarPorId((int)$db->lastInsertId());
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    return null;   // Error distinto a "clave duplicada"
                }

                // Choque de cedula: otro registro simultaneo la gano. Se devuelve
                // esa ficha en vez de reintentar y crear un duplicado.
                if ($cedula !== '') {
                    $existente = self::buscarPorCedula($cedula);
                    if ($existente) {
                        return $existente;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Carga la cedula de un alumno que ya estaba en el padron sin ella.
     * Devuelve false si esa cedula ya pertenece a otro estudiante.
     */
    public static function asignarCedula(int $id, string $cedula): bool
    {
        $cedula = Catalogo::normalizarCedula($cedula);

        if ($cedula === '') {
            return false;
        }

        $duenio = self::buscarPorCedula($cedula);
        if ($duenio && (int)$duenio['id'] !== $id) {
            return false;
        }

        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE estudiantes SET cedula = ? WHERE id = ?");
        return $stmt->execute([$cedula, $id]);
    }

    public static function actualizar(int $id, string $nombre, string $apellido, string $semestre): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE estudiantes SET nombre = ?, apellido = ?, semestre = ? WHERE id = ?");
        return $stmt->execute([$nombre, $apellido, $semestre, $id]);
    }

    /**
     * Actualiza la ficha completa, incluidos cedula y telefono.
     *
     * Devuelve 'ok' o 'cedula_ocupada' si esa cedula ya pertenece a otro
     * alumno. El codigo y el token del carnet NO se tocan: son la identidad
     * del estudiante y cambiarlos invalidaria su carnet impreso.
     */
    public static function actualizarCompleto(
        int $id, string $nombre, string $apellido, string $semestre,
        string $cedula, ?string $telefono
    ): string {
        $cedula = Catalogo::normalizarCedula($cedula);

        $duenio = self::buscarPorCedula($cedula);
        if ($duenio && (int)$duenio['id'] !== $id) {
            return 'cedula_ocupada';
        }

        $db = Database::conectar();

        try {
            $stmt = $db->prepare(
                "UPDATE estudiantes
                 SET nombre = ?, apellido = ?, semestre = ?, cedula = ?, telefono = ?
                 WHERE id = ?"
            );
            $ok = $stmt->execute([
                $nombre, $apellido, $semestre,
                ($cedula !== '' ? $cedula : null),
                self::normalizarTelefono($telefono),
                $id
            ]);
            return $ok ? 'ok' : 'error';
        } catch (PDOException $e) {
            return ($e->getCode() === '23000') ? 'cedula_ocupada' : 'error';
        }
    }

    // Baja logica: conserva el historial de asistencias del alumno
    public static function desactivar(int $id): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE estudiantes SET activo = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Regenera el carnet QR (por ejemplo si el alumno compartio su codigo)
    public static function regenerarToken(int $id): ?string
    {
        $db = Database::conectar();
        $token = bin2hex(random_bytes(16));
        $stmt = $db->prepare("UPDATE estudiantes SET token_qr = ? WHERE id = ?");
        return $stmt->execute([$token, $id]) ? $token : null;
    }

    public static function contar(): int
    {
        $db = Database::conectar();
        return (int)($db->query("SELECT COUNT(*) AS n FROM estudiantes WHERE activo = 1")->fetch()['n'] ?? 0);
    }

    // Calcula el siguiente codigo correlativo disponible (EST001, EST002, ...)
    public static function siguienteCodigo(): string
    {
        $db = Database::conectar();
        $fila = $db->query(
            "SELECT MAX(CAST(SUBSTRING(codigo, 4) AS UNSIGNED)) AS maximo
             FROM estudiantes
             WHERE codigo REGEXP '^EST[0-9]+$'"
        )->fetch();

        $siguiente = (int)($fila['maximo'] ?? 0) + 1;
        return 'EST' . str_pad((string)$siguiente, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Deja el telefono en 10 digitos (09XXXXXXXX), que es como se marca en
     * Ecuador. Devuelve null si no hay nada util: se guarda NULL y no una
     * cadena vacia, para poder distinguir "no tiene" de "esta mal escrito".
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string)$telefono);

        if ($digitos === '') {
            return null;
        }

        // Si viene con el codigo de pais (593), se convierte al formato local
        if (str_starts_with($digitos, '593')) {
            $digitos = '0' . substr($digitos, 3);
        }

        return (strlen($digitos) === 10 && str_starts_with($digitos, '0')) ? $digitos : null;
    }

    public static function guardarTelefono(int $id, ?string $telefono): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE estudiantes SET telefono = ? WHERE id = ?");
        return $stmt->execute([self::normalizarTelefono($telefono), $id]);
    }

    public static function nombreCompleto(array $estudiante): string
    {
        return trim(($estudiante['nombre'] ?? '') . ' ' . ($estudiante['apellido'] ?? ''));
    }
}
