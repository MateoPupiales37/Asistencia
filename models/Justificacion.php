<?php

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Modelo Justificacion: el respaldo de una falta o de una salida.
 *
 * Cuando un alumno no viene a clase, o se retira antes de que termine, el
 * docente puede dejar constancia del motivo y adjuntar el papel que lo
 * respalda: el certificado medico, el permiso de coordinacion, la comision
 * academica. Sin esto la falta queda registrada a secas y al final del
 * periodo nadie recuerda cual estaba justificada.
 *
 * Se enlaza a (sesion, estudiante) y NO a la asistencia porque lo habitual es
 * justificar a quien no vino, y en ese caso no existe ninguna fila de
 * asistencia de la que colgarse.
 *
 * SOBRE EL ARCHIVO: se guarda fuera de la carpeta publica, con un nombre
 * inventado por el sistema. Un certificado medico es un dato de salud, y si
 * viviera bajo public/ cualquiera que acertara la direccion podria abrirlo
 * sin haber iniciado sesion. Para verlo hay que pasar por el controlador, que
 * comprueba antes quien lo pide.
 */
class Justificacion
{
    /** Deben coincidir con el ENUM tipo de la tabla justificaciones */
    public const TIPOS = [
        'Cita medica'          => 'Cita médica',
        'Permiso institucional'=> 'Permiso institucional',
        'Calamidad domestica'  => 'Calamidad doméstica',
        'Comision academica'   => 'Comisión académica',
        'Otro'                 => 'Otro'
    ];

    /**
     * Formatos aceptados como respaldo, con la extension que se les pone al
     * guardarlos. La comprobacion se hace sobre el contenido real del archivo
     * (finfo), no sobre lo que dice el navegador: el tipo que envia el cliente
     * lo elige quien sube el archivo y se puede falsificar.
     */
    public const FORMATOS = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/heic'      => 'heic'
    ];

    /** 6 MB: una foto de celular cabe de sobra y un PDF escaneado tambien */
    public const MAX_BYTES = 6291456;

    public static function esTipoValido(?string $valor): bool
    {
        return isset(self::TIPOS[$valor]);
    }

    public static function etiquetaTipo(?string $tipo): string
    {
        return self::TIPOS[$tipo] ?? '';
    }

    /** Carpeta donde viven los respaldos. Se crea sola la primera vez. */
    public static function carpeta(): string
    {
        $ruta = dirname(__DIR__) . '/storage/justificantes';

        if (!is_dir($ruta)) {
            @mkdir($ruta, 0775, true);
        }

        return $ruta;
    }

    public static function rutaArchivo(?string $archivo): ?string
    {
        if (!$archivo) {
            return null;
        }

        // basename() corta cualquier intento de salirse de la carpeta con
        // nombres del tipo "../../config/database.php"
        $ruta = self::carpeta() . '/' . basename($archivo);
        return is_file($ruta) ? $ruta : null;
    }

    // ==================================================================
    // Consultas
    // ==================================================================

    public static function buscarPorId(int $id): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT j.*, e.nombre, e.apellido, e.codigo, s.docente_id AS sesion_docente_id
             FROM justificaciones j
             JOIN estudiantes e ON j.estudiante_id = e.id
             JOIN sesiones    s ON j.sesion_id     = s.id
             WHERE j.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function buscar(int $sesionId, int $estudianteId): ?array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT * FROM justificaciones WHERE sesion_id = ? AND estudiante_id = ? LIMIT 1"
        );
        $stmt->execute([$sesionId, $estudianteId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Las justificaciones de una clase, indexadas por estudiante.
     * La vista pregunta por el alumno y recibe la suya sin recorrer el array.
     *
     * @return array<int,array>
     */
    public static function deSesion(int $sesionId): array
    {
        $db = Database::conectar();
        $stmt = $db->prepare(
            "SELECT j.*, u.nombre AS docente_nombre, u.apellido AS docente_apellido
             FROM justificaciones j
             LEFT JOIN usuarios u ON j.docente_id = u.id
             WHERE j.sesion_id = ?"
        );
        $stmt->execute([$sesionId]);

        $porEstudiante = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porEstudiante[(int)$fila['estudiante_id']] = $fila;
        }

        return $porEstudiante;
    }

    /** Cuantas faltas justificadas acumula un alumno en un curso */
    public static function contarDeEstudiante(int $estudianteId, ?int $cursoId = null): int
    {
        $db = Database::conectar();

        $sql = "SELECT COUNT(*) AS n FROM justificaciones j
                JOIN sesiones s ON j.sesion_id = s.id
                WHERE j.estudiante_id = ?";
        $parametros = [$estudianteId];

        if ($cursoId) {
            $sql .= " AND s.curso_id = ?";
            $parametros[] = $cursoId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);
        return (int)($stmt->fetch()['n'] ?? 0);
    }

    // ==================================================================
    // Alta
    // ==================================================================

    /**
     * Registra o reemplaza la justificacion de un alumno en una clase.
     *
     * @param array|null $archivo Entrada de $_FILES ya subida, o null si se
     *                            justifica sin adjuntar respaldo.
     * @return string 'ok' o el motivo del rechazo
     */
    public static function guardar(
        int $sesionId,
        int $estudianteId,
        ?int $docenteId,
        string $tipo,
        string $detalle,
        ?array $archivo = null
    ): string {
        if (!self::esTipoValido($tipo)) {
            return 'tipo_invalido';
        }

        $guardado = null;

        if ($archivo !== null) {
            $guardado = self::guardarArchivo($archivo);

            // guardarArchivo devuelve un texto con el motivo cuando falla
            if (is_string($guardado)) {
                return $guardado;
            }
        }

        $db = Database::conectar();

        // Si ya habia una justificacion para este alumno en esta clase, se
        // reemplaza: el docente esta corrigiendo lo que puso antes. El archivo
        // anterior se borra para no dejar documentos medicos huerfanos.
        $previa = self::buscar($sesionId, $estudianteId);

        try {
            if ($previa) {
                // Sin archivo nuevo se conserva el que ya estaba adjunto
                $campos = $guardado ?? [
                    'archivo'        => $previa['archivo'],
                    'archivo_nombre' => $previa['archivo_nombre'],
                    'archivo_tipo'   => $previa['archivo_tipo'],
                    'archivo_peso'   => $previa['archivo_peso']
                ];

                $stmt = $db->prepare(
                    "UPDATE justificaciones
                        SET docente_id = ?, tipo = ?, detalle = ?,
                            archivo = ?, archivo_nombre = ?, archivo_tipo = ?, archivo_peso = ?
                      WHERE id = ?"
                );
                $stmt->execute([
                    $docenteId, $tipo, ($detalle !== '' ? $detalle : null),
                    $campos['archivo'], $campos['archivo_nombre'],
                    $campos['archivo_tipo'], $campos['archivo_peso'],
                    $previa['id']
                ]);

                if ($guardado !== null && $previa['archivo'] && $previa['archivo'] !== $guardado['archivo']) {
                    self::borrarArchivo($previa['archivo']);
                }

                return 'ok';
            }

            $stmt = $db->prepare(
                "INSERT INTO justificaciones
                    (sesion_id, estudiante_id, docente_id, tipo, detalle,
                     archivo, archivo_nombre, archivo_tipo, archivo_peso)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $sesionId, $estudianteId, $docenteId, $tipo,
                ($detalle !== '' ? $detalle : null),
                $guardado['archivo']        ?? null,
                $guardado['archivo_nombre'] ?? null,
                $guardado['archivo_tipo']   ?? null,
                $guardado['archivo_peso']   ?? null
            ]);

            return 'ok';
        } catch (PDOException $e) {
            // Si la fila no llego a guardarse, el archivo que ya se copio al
            // disco no le sirve a nadie
            if ($guardado !== null) {
                self::borrarArchivo($guardado['archivo']);
            }

            error_log('[justificacion] ' . $e->getMessage());
            return 'error';
        }
    }

    public static function eliminar(int $id, ?int $docenteId = null): bool
    {
        $justificacion = self::buscarPorId($id);

        if (!$justificacion) {
            return false;
        }

        // Un docente solo puede retirar las de sus propias clases
        if ($docenteId !== null && (int)$justificacion['sesion_docente_id'] !== $docenteId) {
            return false;
        }

        $db = Database::conectar();
        $db->prepare("DELETE FROM justificaciones WHERE id = ?")->execute([$id]);

        self::borrarArchivo($justificacion['archivo']);
        return true;
    }

    // ==================================================================
    // Archivo adjunto
    // ==================================================================

    /**
     * Comprueba y copia el respaldo a storage/justificantes.
     *
     * @return array|string Los datos a guardar, o el motivo del rechazo
     */
    private static function guardarArchivo(array $archivo): array|string
    {
        $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return 'sin_archivo';
        }

        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return 'muy_pesado';
        }

        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'] ?? '')) {
            return 'error_subida';
        }

        if (($archivo['size'] ?? 0) > self::MAX_BYTES) {
            return 'muy_pesado';
        }

        // El tipo real del contenido, no el que declara el navegador
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $tipo  = $finfo ? finfo_file($finfo, $archivo['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!isset(self::FORMATOS[$tipo])) {
            return 'formato_invalido';
        }

        $nombreEnDisco = bin2hex(random_bytes(16)) . '.' . self::FORMATOS[$tipo];
        $destino       = self::carpeta() . '/' . $nombreEnDisco;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            return 'error_subida';
        }

        @chmod($destino, 0644);

        return [
            'archivo'        => $nombreEnDisco,
            'archivo_nombre' => self::limpiarNombre($archivo['name'] ?? 'justificante'),
            'archivo_tipo'   => $tipo,
            'archivo_peso'   => (int)($archivo['size'] ?? 0)
        ];
    }

    private static function borrarArchivo(?string $archivo): void
    {
        $ruta = self::rutaArchivo($archivo);

        if ($ruta !== null) {
            @unlink($ruta);
        }
    }

    /**
     * El nombre original solo se conserva para que la descarga se llame igual
     * que el archivo que subio el docente. Se limpia porque va en una cabecera
     * HTTP: un salto de linea ahi dentro permitiria inyectar otras cabeceras.
     */
    private static function limpiarNombre(string $nombre): string
    {
        $limpio = preg_replace('/[^\w \-.()áéíóúüñÁÉÍÓÚÜÑ]/u', '', basename($nombre));
        $limpio = trim(preg_replace('/\s+/', ' ', (string)$limpio));
        return ($limpio === '') ? 'justificante' : mb_substr($limpio, 0, 160);
    }

    /** Mensaje para el docente a partir del codigo que devuelve guardar() */
    public static function mensajeError(string $codigo): string
    {
        return match ($codigo) {
            'tipo_invalido'    => 'Selecciona un tipo de justificación válido.',
            'sin_archivo'      => 'No se recibió ningún archivo.',
            'muy_pesado'       => 'El archivo pesa más de ' . round(self::MAX_BYTES / 1048576) . ' MB. '
                                . 'Si es una foto, vuelve a tomarla con menos calidad.',
            'formato_invalido' => 'El respaldo debe ser un PDF o una imagen (JPG, PNG o WEBP).',
            'error_subida'     => 'El archivo no llegó completo. Vuelve a intentarlo.',
            default            => 'No se pudo guardar la justificación.'
        };
    }

    /** "1,4 MB" / "820 KB", para mostrarlo junto al nombre del archivo */
    public static function formatearPeso(?int $bytes): string
    {
        if (!$bytes) {
            return '';
        }

        return ($bytes >= 1048576)
            ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
            : max(1, (int)round($bytes / 1024)) . ' KB';
    }
}
