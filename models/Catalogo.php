<?php

require_once __DIR__ . '/Semestre.php';

// Catalogo institucional: unica fuente de verdad para las listas fijas del sistema.
// Todos los <select> del sistema se llenan desde aqui, de modo que la validacion
// del servidor y las opciones que ve el usuario nunca se desincronizan.

class Catalogo
{
    // Minutos que dura un codigo QR antes de caducar
    public const MINUTOS_QR = 5;

    // Ambientes en los que se puede dictar una misma materia.
    // Deben coincidir con el ENUM ambiente de la tabla cursos.
    public const AMBIENTES = ['Aula', 'Laboratorio', 'Aula Interactiva'];

    // Los SEMESTRES ya no viven aqui: los administra el administrador desde
    // el panel y se leen de la tabla 'semestres'. Ver el modelo Semestre.
    // Se conserva este acceso para que las vistas sigan pidiendolos igual.

    // Los PARALELOS se eliminaron: el instituto maneja un solo grupo por
    // semestre (de Primero a Cuarto), asi que pedirlo era un campo mas
    // que llenar sin que aportara informacion.

    // Motivos de salida anticipada. Deben coincidir con el ENUM motivo de asistencias.
    public const MOTIVOS = [
        'Emergencia medica'       => 'Emergencia médica',
        'Llamado de coordinacion' => 'Llamado de coordinación',
        'Mal comportamiento'      => 'Mal comportamiento',
        'Permiso del docente'     => 'Permiso del docente',
        'Otro'                    => 'Otro'
    ];

    // Estados posibles de una asistencia con su etiqueta visible
    public const ESTADOS = [
        'presente'        => 'En clase',
        'salio'           => 'Salida registrada',
        'salida_temprana' => 'Salida anticipada'
    ];

    public const ROLES = ['docente', 'admin'];

    // Dominio obligatorio del correo institucional
    public const DOMINIO = 'istpet.edu.ec';

    public static function esAmbienteValido(?string $valor): bool
    {
        return in_array($valor, self::AMBIENTES, true);
    }

    /** @return string[] Nombres de los semestres activos, en orden */
    public static function semestres(): array
    {
        return Semestre::nombres();
    }

    public static function esSemestreValido(?string $valor): bool
    {
        return Semestre::esValido($valor);
    }

    public static function esMotivoValido(?string $valor): bool
    {
        return isset(self::MOTIVOS[$valor]);
    }

    /**
     * Un correo solo se acepta si es del dominio institucional.
     *
     * Se comprueba el dominio EXACTO, no que "termine en" istpet.edu.ec:
     * un correo como alguien@falso-istpet.edu.ec terminaria en esa cadena y
     * pasaria el filtro ingenuo. Aqui se separa por la ultima arroba y se
     * compara la parte de la derecha completa.
     */
    public static function esCorreoInstitucional(?string $correo): bool
    {
        $correo = mb_strtolower(trim((string)$correo));

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $arroba = strrpos($correo, '@');
        if ($arroba === false) {
            return false;
        }

        return substr($correo, $arroba + 1) === self::DOMINIO;
    }

    public static function esRolValido(?string $valor): bool
    {
        return in_array($valor, self::ROLES, true);
    }

    // ------------------------------------------------------------------
    // Cedula ecuatoriana
    //
    // Es la via de respaldo para identificar al alumno que no recuerda su
    // codigo institucional. Se valida de verdad (no solo "que sean 10
    // numeros") para que nadie invente una cedula y termine creando fichas
    // duplicadas en el padron.
    // ------------------------------------------------------------------

    /** Deja solo los digitos: el alumno suele escribirla con guiones o espacios */
    public static function normalizarCedula(?string $valor): string
    {
        return preg_replace('/\D/', '', (string)$valor);
    }

    /**
     * Valida una cedula ecuatoriana con el algoritmo oficial (modulo 10):
     *   1. Diez digitos exactos.
     *   2. Los dos primeros son la provincia: 01 a 24, o 30 (ecuatorianos
     *      registrados en el exterior).
     *   3. El tercero es menor que 6 (persona natural).
     *   4. El decimo digito es el verificador: se multiplican los nueve
     *      primeros por 2,1,2,1... restando 9 a los productos mayores que 9,
     *      y el verificador completa la suma a la siguiente decena.
     */
    public static function esCedulaValida(?string $valor): bool
    {
        $cedula = self::normalizarCedula($valor);

        if (strlen($cedula) !== 10) {
            return false;
        }

        $provincia = (int)substr($cedula, 0, 2);
        if (($provincia < 1 || $provincia > 24) && $provincia !== 30) {
            return false;
        }

        if ((int)$cedula[2] > 5) {
            return false;
        }

        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $producto = (int)$cedula[$i] * $coeficientes[$i];
            $suma += ($producto > 9) ? $producto - 9 : $producto;
        }

        $verificador = (10 - ($suma % 10)) % 10;

        return $verificador === (int)$cedula[9];
    }

    public static function etiquetaEstado(?string $estado): string
    {
        return self::ESTADOS[$estado] ?? 'Desconocido';
    }

    public static function etiquetaMotivo(?string $motivo): string
    {
        return self::MOTIVOS[$motivo] ?? '';
    }

    // Construye el correo institucional a partir del nombre y el apellido:
    // "Juan Carlos" + "Tapia Ruiz" -> juan.tapia@istpet.edu.ec
    public static function generarCorreo(string $nombre, string $apellido): string
    {
        $limpiar = static function (string $texto): string {
            $sinTildes = strtr($texto, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n'
            ]);
            // Se toma solo la primera palabra: los nombres compuestos harian
            // correos larguisimos y dificiles de dictar en clase
            $primera = explode(' ', trim($sinTildes))[0] ?? '';
            return mb_strtolower(preg_replace('/[^A-Za-z0-9]/', '', $primera));
        };

        return $limpiar($nombre) . '.' . $limpiar($apellido) . '@' . self::DOMINIO;
    }
}
