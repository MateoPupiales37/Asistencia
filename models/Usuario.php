<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/libs/Totp.php';

// Modelo Usuario: administradores y docentes del sistema.
// El login es unico para ambos roles; lo que cambia es el panel al que entran.

class Usuario
{
    // Busca por correo institucional (login unico de admin y docente)
    public static function buscarPorCorreo(string $correo): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE correo = ? LIMIT 1");
        $stmt->execute([mb_strtolower($correo)]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT id, nombre, apellido, correo, telefono, rol, activo, creado_en,
                    totp_secreto, totp_activado, totp_desde
             FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // Directorio de usuarios con sus estadisticas, para el panel del administrador
    public static function listar(string $rol = ''): array
    {
        $db = Database::conectar();
        $sql = "SELECT u.id, u.nombre, u.apellido, u.correo, u.telefono, u.rol, u.activo, u.creado_en,
                       u.totp_activado,
                       (SELECT COUNT(*) FROM cursos c WHERE c.docente_id = u.id)   AS total_cursos,
                       (SELECT COUNT(*) FROM sesiones s WHERE s.docente_id = u.id) AS total_clases
                FROM usuarios u";

        $params = [];
        if ($rol !== '') {
            $sql .= " WHERE u.rol = ?";
            $params[] = $rol;
        }

        $sql .= " ORDER BY u.rol ASC, u.apellido ASC, u.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Solo docentes activos (para los filtros de seleccion del administrador)
    public static function listarDocentes(): array
    {
        $db = Database::conectar();
        return $db->query(
            "SELECT id, nombre, apellido, correo, telefono FROM usuarios
             WHERE rol = 'docente' AND activo = 1
             ORDER BY apellido ASC, nombre ASC"
        )->fetchAll();
    }

    // Registra un usuario con la contraseña cifrada en Bcrypt
    public static function crear(
        string $nombre, string $apellido, string $correo,
        string $password, string $rol, ?string $telefono = null
    ): bool {
        $db = Database::conectar();
        $sql = "INSERT INTO usuarios (nombre, apellido, correo, telefono, password, rol, activo)
                VALUES (?, ?, ?, ?, ?, ?, 1)";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            $nombre,
            $apellido,
            mb_strtolower($correo),
            self::normalizarTelefono($telefono),
            password_hash($password, PASSWORD_BCRYPT),
            $rol
        ]);
    }

    public static function actualizar(
        int $id, string $nombre, string $apellido, string $correo,
        string $rol, int $activo, ?string $telefono = null
    ): bool {
        $db = Database::conectar();
        $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, correo = ?, telefono = ?, rol = ?, activo = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $nombre, $apellido, mb_strtolower($correo),
            self::normalizarTelefono($telefono), $rol, $activo, $id
        ]);
    }

    /**
     * Telefono en formato local de 10 digitos (09XXXXXXXX).
     * Se guarda NULL y no cadena vacia para poder distinguir "no tiene
     * telefono" de "esta mal escrito".
     */
    public static function normalizarTelefono(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string)$telefono);

        if ($digitos === '') {
            return null;
        }

        if (str_starts_with($digitos, '593')) {
            $digitos = '0' . substr($digitos, 3);
        }

        return (strlen($digitos) === 10 && str_starts_with($digitos, '0')) ? $digitos : null;
    }

    public static function cambiarPassword(int $id, string $nueva): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        return $stmt->execute([password_hash($nueva, PASSWORD_BCRYPT), $id]);
    }

    public static function cambiarEstado(int $id, int $activo): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
        return $stmt->execute([$activo, $id]);
    }

    public static function contarPorRol(string $rol): int
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE rol = ? AND activo = 1");
        $stmt->execute([$rol]);
        return (int)($stmt->fetch()['total'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Doble factor (Google Authenticator)
    // ------------------------------------------------------------------

    /**
     * Guarda un secreto nuevo SIN activarlo todavia.
     *
     * El doble factor no se exige hasta que el usuario demuestra que
     * escaneo bien el codigo QR escribiendo un codigo valido. Si se
     * activara de una, un fallo al escanear dejaria a esa persona fuera
     * del sistema sin manera de entrar.
     */
    public static function prepararTotp(int $id, string $secreto): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE usuarios SET totp_secreto = ?, totp_activado = 0, totp_desde = NULL WHERE id = ?"
        );
        return $stmt->execute([$secreto, $id]);
    }

    /** Confirma el doble factor: a partir de aqui se exige al entrar */
    public static function activarTotp(int $id): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE usuarios SET totp_activado = 1, totp_desde = NOW() WHERE id = ? AND totp_secreto IS NOT NULL"
        );
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /** Lo desactiva y borra el secreto: hay que volver a configurarlo desde cero */
    public static function desactivarTotp(int $id): bool
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "UPDATE usuarios SET totp_secreto = NULL, totp_activado = 0, totp_desde = NULL WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /** El secreto en crudo. Solo se usa para verificar o para armar el QR */
    public static function secretoTotp(int $id): ?string
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT totp_secreto FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return ($fila && !empty($fila['totp_secreto'])) ? $fila['totp_secreto'] : null;
    }

    public static function tieneTotpActivo(array $usuario): bool
    {
        return (int)($usuario['totp_activado'] ?? 0) === 1
            && !empty($usuario['totp_secreto']);
    }

    /** Cuantas cuentas activas todavia no tienen el doble factor puesto */
    public static function contarSinTotp(): int
    {
        $db = Database::conectar();
        return (int)($db->query(
            "SELECT COUNT(*) AS n FROM usuarios WHERE activo = 1 AND totp_activado = 0"
        )->fetch()['n'] ?? 0);
    }

    // Nombre completo listo para mostrar
    public static function nombreCompleto(array $usuario): string
    {
        return trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
    }
}
