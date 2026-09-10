<?php

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Catalogo.php';

// Modelo Estudiante: padron de alumnos.
// Cada alumno tiene un token_qr irrepetible que es el contenido de su carnet QR.
// El estudiante NO tiene cuenta ni contraseña: solo escanea y llena el formulario.

class Estudiante
{
    // Busca por el codigo institucional escrito a mano (ej. DSW-001)
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
    /**
     * Busca por el numero de documento, sea cedula o pasaporte.
     *
     * Cuando no se indica el tipo se prueban las dos formas de limpiarlo: el
     * alumno que se registra en clase no elige tipo, solo teclea un numero, y
     * el sistema tiene que encontrarlo igual. La cedula se deja en digitos y
     * el pasaporte en mayusculas sin separadores, asi que un mismo texto puede
     * dar dos formas distintas.
     */
    public static function buscarPorDocumento(string $documento, ?string $tipo = null): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM estudiantes WHERE cedula = ? LIMIT 1");

        $formas = ($tipo === null)
            ? [Catalogo::normalizarCedula($documento), Catalogo::normalizarPasaporte($documento)]
            : [Catalogo::normalizarDocumento($documento, $tipo)];

        foreach (array_unique(array_filter($formas)) as $forma) {
            $stmt->execute([$forma]);
            $fila = $stmt->fetch();
            if ($fila) {
                return $fila;
            }
        }

        return null;
    }

    /** Nombre anterior del metodo. Se conserva para no reescribir las llamadas ya existentes. */
    public static function buscarPorCedula(string $cedula): ?array
    {
        return self::buscarPorDocumento($cedula);
    }

    // Busca por el token del carnet QR personal
    public static function buscarPorToken(string $token): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare("SELECT * FROM estudiantes WHERE token_qr = ? LIMIT 1");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Le pone carrera a un estudiante que todavia no la tenia.
     *
     * Nunca la CAMBIA: si ya pertenece a una carrera y alguien lo matricula en
     * un curso de otra, se deja como esta y el docente lo vera marcado. Mover
     * a un alumno de carrera es una decision academica, no algo que deba pasar
     * de rebote al matricularlo.
     */
    public static function asignarCarreraSiFalta(int $id, ?int $carreraId): void
    {
        if (!$carreraId) {
            return;
        }

        $db = Database::conectar();
        $db->prepare("UPDATE estudiantes SET carrera_id = ? WHERE id = ? AND carrera_id IS NULL")
           ->execute([$carreraId, $id]);
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
    /**
     * Padron de estudiantes activos.
     *
     * Trae siempre el nombre de la carrera: con cinco carreras cargadas, una
     * lista de nombres sueltos no permite saber quien es de quien, y era lo
     * que hacia que al repartir los carnets se confundieran unos con otros.
     *
     * @param int|null $carreraId Acota a una sola carrera
     */
    public static function listar(string $semestre = '', ?int $carreraId = null): array
    {
        $db = Database::conectar();

        $sql = "SELECT e.*, c.nombre AS carrera, c.codigo AS carrera_codigo
                FROM estudiantes e
                LEFT JOIN carreras c ON e.carrera_id = c.id
                WHERE e.activo = 1";
        $params = [];

        if ($semestre !== '') {
            $sql .= " AND e.semestre = ?";
            $params[] = $semestre;
        }

        if ($carreraId) {
            $sql .= " AND e.carrera_id = ?";
            $params[] = $carreraId;
        }

        // Agrupados por carrera y luego por apellido: es el orden en que se
        // reparten los carnets, carrera por carrera
        $sql .= " ORDER BY c.nombre ASC, e.apellido ASC, e.nombre ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Los estudiantes que el docente tiene matriculados en SUS cursos.
     *
     * Es lo que de verdad necesita para imprimir carnets: el padron completo
     * incluye alumnos de carreras que no dicta, y buscar los suyos ahi dentro
     * es justamente donde aparecen las equivocaciones.
     */
    public static function listarDeDocente(int $docenteId, string $semestre = '', ?int $carreraId = null): array
    {
        $db = Database::conectar();

        $sql = "SELECT DISTINCT e.*, c.nombre AS carrera, c.codigo AS carrera_codigo
                FROM estudiantes e
                JOIN matriculas mt ON mt.estudiante_id = e.id
                JOIN cursos cu     ON mt.curso_id = cu.id
                LEFT JOIN carreras c ON e.carrera_id = c.id
                WHERE e.activo = 1 AND cu.docente_id = ? AND cu.activo = 1";
        $params = [$docenteId];

        if ($semestre !== '') {
            $sql .= " AND e.semestre = ?";
            $params[] = $semestre;
        }

        if ($carreraId) {
            $sql .= " AND e.carrera_id = ?";
            $params[] = $carreraId;
        }

        $sql .= " ORDER BY c.nombre ASC, e.apellido ASC, e.nombre ASC";

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
    /**
     * @param int|null $carreraId La carrera a la que pertenece. Viene del curso
     *        donde lo esta matriculando el docente: un alumno de Mecanica no
     *        tiene por que aparecer entre los candidatos de Desarrollo.
     */
    public static function crear(
        string $nombre,
        string $apellido,
        string $semestre,
        ?string $cedula = null,
        ?string $telefono = null,
        ?int $carreraId = null,
        string $tipoDocumento = 'cedula'
    ): ?array
    {
        $db = Database::conectar();

        if (!Catalogo::esTipoDocumentoValido($tipoDocumento)) {
            $tipoDocumento = 'cedula';
        }

        $cedula = ($cedula !== null) ? Catalogo::normalizarDocumento($cedula, $tipoDocumento) : '';
        if ($cedula !== '') {
            $existente = self::buscarPorDocumento($cedula, $tipoDocumento);
            if ($existente) {
                return $existente;
            }
        }

        // Se reintenta por si dos registros simultaneos piden el mismo correlativo
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = self::siguienteCodigo(self::prefijoDeCarrera($carreraId));
            $token  = bin2hex(random_bytes(16));

            try {
                $stmt = $db->prepare(
                    "INSERT INTO estudiantes
                        (codigo, tipo_documento, cedula, telefono, nombre, apellido, semestre, carrera_id, token_qr)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $codigo,
                    $tipoDocumento,
                    ($cedula !== '' ? $cedula : null),
                    self::normalizarTelefono($telefono),
                    $nombre, $apellido, $semestre, $carreraId, $token
                ]);
                return self::buscarPorId((int)$db->lastInsertId());
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    return null;   // Error distinto a "clave duplicada"
                }

                // Choque de documento: otro registro simultaneo lo gano. Se
                // devuelve esa ficha en vez de reintentar y crear un duplicado.
                if ($cedula !== '') {
                    $existente = self::buscarPorDocumento($cedula, $tipoDocumento);
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
    public static function asignarCedula(int $id, string $cedula, string $tipo = 'cedula'): bool
    {
        if (!Catalogo::esTipoDocumentoValido($tipo)) {
            $tipo = 'cedula';
        }

        $cedula = Catalogo::normalizarDocumento($cedula, $tipo);

        if ($cedula === '') {
            return false;
        }

        $duenio = self::buscarPorDocumento($cedula, $tipo);
        if ($duenio && (int)$duenio['id'] !== $id) {
            return false;
        }

        $db = Database::conectar();
        $stmt = $db->prepare("UPDATE estudiantes SET cedula = ?, tipo_documento = ? WHERE id = ?");
        return $stmt->execute([$cedula, $tipo, $id]);
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
        string $cedula, ?string $telefono, string $tipoDocumento = 'cedula'
    ): string {
        if (!Catalogo::esTipoDocumentoValido($tipoDocumento)) {
            $tipoDocumento = 'cedula';
        }

        $cedula = Catalogo::normalizarDocumento($cedula, $tipoDocumento);

        $duenio = self::buscarPorDocumento($cedula, $tipoDocumento);
        if ($duenio && (int)$duenio['id'] !== $id) {
            return 'cedula_ocupada';
        }

        $db = Database::conectar();

        try {
            $stmt = $db->prepare(
                "UPDATE estudiantes
                 SET nombre = ?, apellido = ?, semestre = ?, cedula = ?, tipo_documento = ?, telefono = ?
                 WHERE id = ?"
            );
            $ok = $stmt->execute([
                $nombre, $apellido, $semestre,
                ($cedula !== '' ? $cedula : null),
                $tipoDocumento,
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

    /** Prefijo que se usa cuando el alumno todavia no tiene carrera */
    public const PREFIJO_SIN_CARRERA = 'EST';

    /**
     * El codigo lleva dentro la carrera: DSW-001, MEA-001, END-001...
     *
     * Antes era un correlativo unico para todo el instituto (EST001, EST002).
     * Con cinco carreras eso no dice nada: al ver "EST014" en una lista no hay
     * forma de saber de quien es, y al repartir carnets o dictar codigos en
     * clase se mezclaban. Con el prefijo de la carrera, el propio codigo lo
     * responde.
     *
     * La numeracion es independiente por carrera, asi que cada una empieza en
     * 001 y no hereda huecos de las demas.
     */
    public static function siguienteCodigo(?string $prefijo = null): string
    {
        $prefijo = self::limpiarPrefijo($prefijo);

        $db = Database::conectar();

        // Se cuenta solo dentro de este prefijo. El patron exige el guion para
        // que "DSW-001" no se confunda con un hipotetico "DSWX-001".
        $stmt = $db->prepare(
            "SELECT MAX(CAST(SUBSTRING(codigo, ?) AS UNSIGNED)) AS maximo
               FROM estudiantes
              WHERE codigo REGEXP ?"
        );
        $stmt->execute([
            strlen($prefijo) + 2,                       // 1-indexado, saltando el guion
            '^' . preg_quote($prefijo, '/') . '-[0-9]+$'
        ]);

        $siguiente = (int)($stmt->fetch()['maximo'] ?? 0) + 1;

        return $prefijo . '-' . str_pad((string)$siguiente, 3, '0', STR_PAD_LEFT);
    }

    /**
     * El prefijo sale del codigo de la carrera y se limpia: va dentro de una
     * expresion regular y de una clave UNIQUE, y la columna admite 15
     * caracteres contando el guion y los tres digitos.
     */
    private static function limpiarPrefijo(?string $prefijo): string
    {
        $limpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$prefijo));

        return ($limpio === '') ? self::PREFIJO_SIN_CARRERA : substr($limpio, 0, 10);
    }

    /** El prefijo que le toca a un alumno segun su carrera */
    public static function prefijoDeCarrera(?int $carreraId): string
    {
        require_once __DIR__ . '/Carrera.php';

        $carrera = Carrera::buscarPorId($carreraId);

        return self::limpiarPrefijo($carrera['codigo'] ?? null);
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
