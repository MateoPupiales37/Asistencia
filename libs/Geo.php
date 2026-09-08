<?php

/**
 * Geocerca: comprueba que el alumno esté físicamente cerca del aula.
 *
 * El problema que resuelve: un alumno le manda la foto del QR por WhatsApp a
 * un compañero que no vino, y ese compañero se registra desde su casa. El
 * docente puede sacarlo si se da cuenta, pero tiene que darse cuenta.
 *
 * Con la geocerca no hace falta: al abrir la clase se guardan las coordenadas
 * del aula, y quien escanea manda las suyas. Si está a más de N metros, no se
 * registra. La distancia queda guardada como evidencia.
 *
 * LIMITACION IMPORTANTE, y hay que tenerla presente:
 * los navegadores solo entregan la ubicación en "contextos seguros", es decir
 * por HTTPS o desde localhost. Entrando por http://192.168.x.x —que es como
 * se usa en el aula— Chrome y Safari NIEGAN la ubicación sin siquiera
 * preguntar. Por eso el sistema trata la geocerca como una capa que se activa
 * cuando el dato existe, y nunca deja a un alumno sin registrarse solo porque
 * el navegador no quiso dar coordenadas: eso se informa aparte al docente.
 */
class Geo
{
    /** Radio de la Tierra en metros (media aritmética, WGS-84) */
    private const RADIO_TIERRA = 6371000;

    /** Radio por defecto de la geocerca */
    public const RADIO_POR_DEFECTO = 500;

    /**
     * Distancia en metros entre dos puntos, por la fórmula del haversine.
     *
     * Se usa haversine y no una resta simple de coordenadas porque un grado
     * de longitud mide distinto según la latitud: cerca del ecuador son ~111
     * km y cerca de los polos tiende a cero. Restar daría errores enormes.
     */
    public static function distancia(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lon2 - $lon1);

        $a = sin($Δφ / 2) ** 2
           + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return self::RADIO_TIERRA * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Una coordenada es válida si cae dentro del rango real del planeta */
    public static function coordenadaValida($latitud, $longitud): bool
    {
        if (!is_numeric($latitud) || !is_numeric($longitud)) {
            return false;
        }

        $lat = (float)$latitud;
        $lon = (float)$longitud;

        // El punto (0,0) está en el Golfo de Guinea: en la práctica siempre
        // es un dato vacío mal enviado, no una ubicación real de un aula.
        if ($lat === 0.0 && $lon === 0.0) {
            return false;
        }

        return $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180;
    }

    /**
     * Evalúa si un alumno puede registrarse según su ubicación.
     *
     * Devuelve el veredicto y una explicación en lenguaje llano, porque el
     * alumno tiene que entender por qué se le rechaza.
     *
     * @return array{permitido:bool, motivo:string, distancia:?int, texto:string}
     */
    public static function evaluar(
        ?float $latAula, ?float $lonAula, int $radio,
        $latAlumno, $lonAlumno
    ): array {
        // La clase no guardó ubicación: no hay contra qué comparar, así que
        // la geocerca simplemente no aplica a esta clase.
        if ($latAula === null || $lonAula === null || !self::coordenadaValida($latAula, $lonAula)) {
            return [
                'permitido' => true,
                'motivo'    => 'sin_geocerca',
                'distancia' => null,
                'texto'     => ''
            ];
        }

        // El alumno no mandó ubicación. No se le bloquea —el navegador puede
        // haberla negado por estar en HTTP— pero queda marcado para que el
        // docente lo vea en su lista.
        if (!self::coordenadaValida($latAlumno, $lonAlumno)) {
            return [
                'permitido' => true,
                'motivo'    => 'sin_ubicacion',
                'distancia' => null,
                'texto'     => 'No se pudo verificar tu ubicación. El docente verá tu registro marcado.'
            ];
        }

        $metros = self::distancia($latAula, $lonAula, (float)$latAlumno, (float)$lonAlumno);

        if ($metros > $radio) {
            return [
                'permitido' => false,
                'motivo'    => 'fuera_de_zona',
                'distancia' => (int)round($metros),
                'texto'     => 'Estás a ' . self::formatear($metros) . ' del aula. '
                             . 'Solo puedes registrarte dentro de ' . self::formatear($radio)
                             . '. Acércate al salón e inténtalo de nuevo.'
            ];
        }

        return [
            'permitido' => true,
            'motivo'    => 'dentro',
            'distancia' => (int)round($metros),
            'texto'     => ''
        ];
    }

    /** 850 -> "850 m"; 1400 -> "1.4 km" */
    public static function formatear(float $metros): string
    {
        return ($metros < 1000)
            ? round($metros) . ' m'
            : round($metros / 1000, 1) . ' km';
    }
}
