<?php

/**
 * Lector de archivos Excel (.xlsx) en PHP puro.
 *
 * Un .xlsx no es un formato binario: es un ZIP que adentro lleva XML. Aqui se
 * abre con ZipArchive (extension estandar de PHP, ya activa en XAMPP), se lee
 * la primera hoja y se devuelven las filas como un arreglo. No hace falta
 * Composer, ni PhpSpreadsheet, ni ninguna descarga.
 *
 * Detalles del formato que hay que respetar:
 *
 *   - Las celdas de texto NO guardan el texto en la hoja: guardan un numero
 *     que apunta a sharedStrings.xml, una tabla comun a todo el libro. Por eso
 *     hay que leer ese archivo primero.
 *   - Las columnas vacias se OMITEN del XML. Si la fila trae A, B y D, hay que
 *     mirar la referencia de cada celda (r="D3") para colocarla en su sitio;
 *     si se leyeran en orden, D se correria al lugar de C.
 *
 * Tambien acepta CSV, por si la secretaria exporta la lista en ese formato.
 */
class LectorExcel
{
    /** Tope de filas, para que un archivo enorme no agote la memoria */
    public const MAX_FILAS = 2000;

    /**
     * Devuelve las filas del archivo como arreglo de arreglos.
     *
     * @throws RuntimeException si el archivo no se puede leer
     * @return array<int,array<int,string>>
     */
    public static function leer(string $ruta, string $nombreOriginal = ''): array
    {
        $extension = strtolower(pathinfo($nombreOriginal !== '' ? $nombreOriginal : $ruta, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return self::leerCsv($ruta);
        }

        if ($extension !== 'xlsx') {
            throw new RuntimeException(
                'Formato no admitido. Sube un archivo .xlsx (Excel) o .csv. '
                . 'Si tu archivo es .xls antiguo, ábrelo en Excel y usa "Guardar como" -> .xlsx'
            );
        }

        return self::leerXlsx($ruta);
    }

    // ==================================================================

    private static function leerXlsx(string $ruta): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('El servidor no tiene activada la extensión ZIP de PHP.');
        }

        $zip = new ZipArchive();

        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo. ¿Seguro que es un Excel válido?');
        }

        try {
            $textos = self::leerTextosCompartidos($zip);
            $hoja   = self::leerPrimeraHoja($zip);

            if ($hoja === null) {
                throw new RuntimeException('El archivo no tiene ninguna hoja de cálculo con datos.');
            }

            return self::filasDeLaHoja($hoja, $textos);
        } finally {
            $zip->close();
        }
    }

    /** sharedStrings.xml: la tabla de textos que comparten todas las hojas */
    private static function leerTextosCompartidos(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];   // Un libro solo con numeros no tiene este archivo
        }

        $doc = self::cargarXml($xml);
        if ($doc === null) {
            return [];
        }

        $textos = [];

        foreach ($doc->si as $si) {
            // El texto puede venir suelto en <t> o partido en varios <r><t>
            // cuando en Excel se le dio formato a una parte de la celda
            $valor = '';

            if (isset($si->t)) {
                $valor = (string)$si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $tramo) {
                    $valor .= (string)$tramo->t;
                }
            }

            $textos[] = $valor;
        }

        return $textos;
    }

    /** Devuelve el XML de la primera hoja del libro */
    private static function leerPrimeraHoja(ZipArchive $zip): ?string
    {
        // Lo habitual es xl/worksheets/sheet1.xml, pero no esta garantizado
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($xml !== false) {
            return $xml;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if ($nombre !== false && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nombre)) {
                $contenido = $zip->getFromIndex($i);
                return ($contenido === false) ? null : $contenido;
            }
        }

        return null;
    }

    private static function filasDeLaHoja(string $xmlHoja, array $textos): array
    {
        $doc = self::cargarXml($xmlHoja);

        if ($doc === null || !isset($doc->sheetData)) {
            return [];
        }

        $filas = [];

        foreach ($doc->sheetData->row as $fila) {
            if (count($filas) >= self::MAX_FILAS) {
                break;
            }

            $celdas = [];

            foreach ($fila->c as $celda) {
                // La referencia (por ejemplo "C7") dice en que columna va.
                // Sin esto, las columnas vacias correrian los datos de lugar.
                $ref     = (string)$celda['r'];
                $columna = self::indiceDeColumna($ref);
                $tipo    = (string)$celda['t'];

                $valor = '';

                if ($tipo === 's') {
                    // Texto compartido: el valor es el indice en sharedStrings
                    $indice = (int)$celda->v;
                    $valor  = $textos[$indice] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = isset($celda->is->t) ? (string)$celda->is->t : '';
                } elseif (isset($celda->v)) {
                    $valor = (string)$celda->v;
                }

                $celdas[$columna] = trim($valor);
            }

            if (empty($celdas)) {
                continue;
            }

            // Se rellenan los huecos para que toda fila tenga la misma forma
            $ancho    = max(array_keys($celdas)) + 1;
            $completa = [];
            for ($i = 0; $i < $ancho; $i++) {
                $completa[$i] = $celdas[$i] ?? '';
            }

            // Filas totalmente vacias no aportan
            if (implode('', $completa) !== '') {
                $filas[] = $completa;
            }
        }

        return $filas;
    }

    /** "C7" -> 2 (la columna A es 0) */
    private static function indiceDeColumna(string $referencia): int
    {
        if (!preg_match('/^([A-Z]+)/', strtoupper($referencia), $m)) {
            return 0;
        }

        $letras = $m[1];
        $indice = 0;

        // Base 26: A=1 ... Z=26, AA=27
        for ($i = 0, $n = strlen($letras); $i < $n; $i++) {
            $indice = $indice * 26 + (ord($letras[$i]) - 64);
        }

        return $indice - 1;
    }

    private static function leerCsv(string $ruta): array
    {
        $filas   = [];
        $puntero = fopen($ruta, 'r');

        if ($puntero === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        // Se detecta el separador mirando la primera linea: Excel en español
        // exporta con ';' y otras herramientas con ','
        $primera    = fgets($puntero);
        $separador  = (substr_count((string)$primera, ';') > substr_count((string)$primera, ',')) ? ';' : ',';
        rewind($puntero);

        while (($fila = fgetcsv($puntero, 0, $separador)) !== false) {
            if (count($filas) >= self::MAX_FILAS) {
                break;
            }

            $limpia = array_map(static fn($v) => trim((string)$v), $fila);

            if (implode('', $limpia) !== '') {
                $filas[] = $limpia;
            }
        }

        fclose($puntero);

        // Se quita el BOM que Excel pone al inicio del archivo
        if (!empty($filas[0][0])) {
            $filas[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $filas[0][0]);
        }

        return $filas;
    }

    /** Carga XML desactivando entidades externas (proteccion contra XXE) */
    private static function cargarXml(string $xml): ?SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);

        // El archivo lo sube un usuario: un XML malicioso podria intentar leer
        // ficheros del servidor mediante entidades externas. LIBXML_NONET y
        // LIBXML_NOENT desactivado lo impiden.
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return ($doc === false) ? null : $doc;
    }
}
