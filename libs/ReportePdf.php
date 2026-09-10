<?php

require_once __DIR__ . '/fpdf/fpdf.php';

/**
 * Generador de reportes en PDF para el ISTPET.
 *
 * Dos cosas que este generador resuelve y que conviene no volver a perder:
 *
 * 1. NADA SE CORTA. Las celdas ajustan su alto al contenido en vez de recortar
 *    el texto con puntos suspensivos. Un reporte que dice "Emergencia mé..."
 *    obliga a abrir el sistema para saber que decia, y entonces el PDF no
 *    sirve como documento.
 *
 * 2. EL PDF SE FIRMA. Un reporte de asistencia que va a Secretaría o a
 *    Coordinación no vale nada sin constancia de quién responde por él, así
 *    que al final va el bloque de firmas.
 */
class ReportePdf extends FPDF
{
    /** Alto de cada linea de texto dentro de una celda */
    private const ALTO_LINEA = 4.3;

    /** Alto minimo de una fila, aunque su contenido quepa en una sola linea */
    private const ALTO_FILA = 6.5;

    /** Margen izquierdo y ancho util de la hoja A4 horizontal */
    private const IZQUIERDA = 12.0;
    private const ANCHO_UTIL = 273.0;

    private string $docente;
    private string $rango;
    private string $filtros;

    public function __construct(string $docente, string $rango, string $filtros = '')
    {
        parent::__construct('L', 'mm', 'A4'); // Horizontal A4
        $this->docente = $docente;
        $this->rango = $rango;
        $this->filtros = $filtros;
        // El margen inferior deja sitio al pie y a que una fila alta no quede
        // partida entre dos paginas
        $this->SetAutoPageBreak(true, 18);
        $this->AliasNbPages();
    }

    public function Header(): void
    {
        // Logo institucional ISTPET
        $rutaLogo = dirname(__DIR__) . '/public/assets/img/logo-istpet.jpg';
        if (file_exists($rutaLogo)) {
            $this->Image($rutaLogo, 12, 10, 24);
        }

        // Encabezado institucional
        $this->SetXY(39, 10);
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(26, 43, 76); // Azul institucional #1A2B4C
        $this->Cell(155, 6, $this->conv('INSTITUTO SUPERIOR TECNOLÓGICO MAYOR PEDRO TRAVERSARI'), 0, 1, 'L');

        $this->SetX(39);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(184, 145, 46); // Dorado acreditación #B8912E
        $this->Cell(155, 5, $this->conv('SISTEMA DE ASISTENCIA ACADÉMICA — REPORTE OFICIAL'), 0, 1, 'L');

        // Metadatos a la derecha
        $this->SetXY(195, 10);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(71, 85, 105);
        $this->Cell(88, 4, $this->conv('Fecha de emisión: ' . date('d/m/Y H:i:s')), 0, 1, 'R');

        $this->SetX(195);
        $this->Cell(88, 4, $this->conv('Docente: ' . ($this->docente ?: 'No especificado')), 0, 1, 'R');

        if (!empty($this->rango)) {
            $this->SetX(195);
            $this->Cell(88, 4, $this->conv('Periodo: ' . $this->rango), 0, 1, 'R');
        }
        if (!empty($this->filtros)) {
            $this->SetX(195);
            $this->Cell(88, 4, $this->conv('Filtros: ' . $this->filtros), 0, 1, 'R');
        }

        // Línea divisoria
        $this->SetY(28);
        $this->SetDrawColor(184, 145, 46);
        $this->SetLineWidth(0.6);
        $this->Line(12, 28, 285, 28);
        $this->Ln(4);

        $this->cabeceraTabla();
    }

    /**
     * Anchos de las columnas, en milimetros. Suman 273, que es el ancho util
     * de la hoja. Estan aqui y no repartidos por el codigo para que la
     * cabecera y las filas no puedan desalinearse: si se cambia un ancho,
     * cambia en los dos sitios a la vez.
     *
     * @return array<string,float>
     */
    public function columnas(): array
    {
        return [
            'fecha'      => 20,
            'codigo'     => 17,
            'cedula'     => 23,
            'estudiante' => 45,
            'materia'    => 42,
            'ambiente'   => 24,
            'entrada'    => 15,
            'salida'     => 15,
            'estado'     => 26,
            'motivo'     => 46
        ];
    }

    private function cabeceraTabla(): void
    {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(26, 43, 76);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.2);

        $titulos = [
            'fecha' => 'FECHA', 'codigo' => 'CÓDIGO', 'cedula' => 'CÉDULA',
            'estudiante' => 'ESTUDIANTE', 'materia' => 'MATERIA', 'ambiente' => 'AMBIENTE',
            'entrada' => 'ENTRADA', 'salida' => 'SALIDA', 'estado' => 'ESTADO', 'motivo' => 'MOTIVO'
        ];

        $anchos = $this->columnas();
        $ultima = array_key_last($anchos);

        foreach ($anchos as $clave => $ancho) {
            $this->Cell($ancho, 7, $this->conv($titulos[$clave]), 1, ($clave === $ultima ? 1 : 0), 'C', true);
        }
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(140, 6, $this->conv('ISTPET — Documento emitido automáticamente por el Sistema de Asistencia'), 0, 0, 'L');
        $this->Cell(133, 6, $this->conv('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    // ==================================================================
    // Filas que se adaptan al contenido
    // ==================================================================

    /**
     * Escribe una fila completa ajustando el alto al texto mas largo.
     *
     * FPDF no trae nada parecido: Cell() recorta y MultiCell() baja de linea
     * pero rompe la fila, porque cada celda deja el cursor debajo de si misma.
     * Aqui se calcula primero cuantas lineas necesita cada celda, se toma la
     * mayor, y despues se dibuja cada una en su sitio devolviendo el cursor a
     * mano. Es la unica forma de que la fila quede pareja sin recortar nada.
     *
     * @param array<string,string> $valores      Texto de cada columna
     * @param array<string,string> $alineaciones 'L' o 'C' por columna
     */
    public function filaAjustada(array $valores, array $alineaciones, bool $alterno): void
    {
        $anchos = $this->columnas();

        // 1. Cuantas lineas necesita cada celda
        $lineasPorCelda = [];
        $maximo = 1;

        foreach ($anchos as $clave => $ancho) {
            $lineas = $this->dividirTexto((string)($valores[$clave] ?? ''), $ancho - 2);
            $lineasPorCelda[$clave] = $lineas;
            $maximo = max($maximo, count($lineas));
        }

        $alto = max(self::ALTO_FILA, $maximo * self::ALTO_LINEA + 2);

        // 2. Si la fila no cabe en lo que queda de pagina, se pasa entera a la
        //    siguiente. Partirla dejaria media celda arriba y media abajo.
        if ($this->GetY() + $alto > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        // 3. Dibujar
        $this->SetFillColor(...($alterno ? [248, 250, 252] : [255, 255, 255]));
        $this->SetTextColor(30, 41, 59);
        $this->SetDrawColor(226, 232, 240);

        $x = self::IZQUIERDA;
        $y = $this->GetY();

        foreach ($anchos as $clave => $ancho) {
            // El recuadro se pinta completo antes que el texto, para que el
            // fondo no tape las lineas de las celdas vecinas
            $this->Rect($x, $y, $ancho, $alto, 'DF');

            $lineas = $lineasPorCelda[$clave];
            $alineacion = $alineaciones[$clave] ?? 'L';

            // El texto se centra verticalmente dentro de la celda
            $desplazamiento = ($alto - count($lineas) * self::ALTO_LINEA) / 2;
            $this->SetXY($x, $y + $desplazamiento);

            foreach ($lineas as $linea) {
                $this->SetX($x);
                $this->Cell($ancho, self::ALTO_LINEA, $this->conv($linea), 0, 2, $alineacion);
            }

            $x += $ancho;
        }

        $this->SetXY(self::IZQUIERDA, $y + $alto);
    }

    /**
     * Parte un texto en las lineas que caben en el ancho indicado.
     *
     * Corta por espacios; solo si una palabra suelta no cabe (un correo largo,
     * un codigo sin espacios) la parte por caracteres. Devuelve al menos una
     * linea, aunque sea vacia, para que la celda conserve su alto.
     *
     * @return string[]
     */
    private function dividirTexto(string $texto, float $ancho): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));

        if ($texto === '') {
            return [''];
        }

        if ($this->GetStringWidth($this->conv($texto)) <= $ancho) {
            return [$texto];
        }

        $lineas = [];
        $actual = '';

        foreach (explode(' ', $texto) as $palabra) {
            $prueba = ($actual === '') ? $palabra : $actual . ' ' . $palabra;

            if ($this->GetStringWidth($this->conv($prueba)) <= $ancho) {
                $actual = $prueba;
                continue;
            }

            if ($actual !== '') {
                $lineas[] = $actual;
                $actual = '';
            }

            // La palabra por si sola tampoco cabe: se parte por caracteres
            if ($this->GetStringWidth($this->conv($palabra)) > $ancho) {
                $trozo = '';
                foreach (mb_str_split($palabra) as $letra) {
                    if ($this->GetStringWidth($this->conv($trozo . $letra)) > $ancho && $trozo !== '') {
                        $lineas[] = $trozo;
                        $trozo = '';
                    }
                    $trozo .= $letra;
                }
                $actual = $trozo;
            } else {
                $actual = $palabra;
            }
        }

        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas ?: [''];
    }

    // ==================================================================
    // Cierre del documento
    // ==================================================================

    /**
     * Bloque de firmas al final del reporte.
     *
     * Un reporte de asistencia que va a Secretaría o a Coordinación es un
     * documento de respaldo: sin constancia de quién responde por él, nadie
     * puede darlo por válido. Aquí quedan el total de registros —para que no
     * se le puedan agregar filas después— y las dos firmas.
     */
    public function bloqueFirmas(string $docente, int $totalRegistros): void
    {
        // El bloque completo ocupa unos 42 mm. Si no caben, va en hoja aparte:
        // una firma partida entre dos paginas no la acepta nadie.
        if ($this->GetY() + 42 > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }

        $this->Ln(6);

        // Total de registros: cierra el documento y deja constancia de cuantas
        // filas tenia cuando se emitio
        $this->SetX(self::IZQUIERDA);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(26, 43, 76);
        $this->Cell(
            self::ANCHO_UTIL, 6,
            $this->conv('Total de registros en este reporte: ' . $totalRegistros),
            0, 1, 'R'
        );

        $this->Ln(12);

        $anchoFirma = 78.0;
        $separacion = (self::ANCHO_UTIL - ($anchoFirma * 2)) / 3;
        $y = $this->GetY();

        $firmas = [
            [
                'x'      => self::IZQUIERDA + $separacion,
                'nombre' => $docente ?: 'Docente responsable',
                'cargo'  => 'Docente responsable',
                'nota'   => 'Firma'
            ],
            [
                'x'      => self::IZQUIERDA + $separacion * 2 + $anchoFirma,
                'nombre' => 'Coordinación Académica',
                'cargo'  => 'Instituto Superior Tecnológico Mayor Pedro Traversari',
                'nota'   => 'Firma y sello'
            ]
        ];

        foreach ($firmas as $firma) {
            // Línea sobre la que se firma
            $this->SetDrawColor(71, 85, 105);
            $this->SetLineWidth(0.3);
            $this->Line($firma['x'], $y, $firma['x'] + $anchoFirma, $y);

            $this->SetXY($firma['x'], $y + 1.5);
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetTextColor(26, 43, 76);
            $this->Cell($anchoFirma, 5, $this->conv($firma['nombre']), 0, 2, 'C');

            $this->SetX($firma['x']);
            $this->SetFont('Helvetica', '', 7.5);
            $this->SetTextColor(100, 116, 139);
            $this->Cell($anchoFirma, 4, $this->conv($firma['cargo']), 0, 2, 'C');

            $this->SetX($firma['x']);
            $this->SetFont('Helvetica', 'I', 7);
            $this->Cell($anchoFirma, 4, $this->conv($firma['nota']), 0, 2, 'C');
        }

        // El cursor queda debajo del bloque mas alto de los dos
        $this->SetXY(self::IZQUIERDA, $y + 20);
    }

    /**
     * Convierte el texto al juego de caracteres que entienden las fuentes
     * base de FPDF (ISO-8859-1).
     *
     * Antes de convertir se sustituyen los signos tipograficos que NO existen
     * en ese juego. Sin este paso, mb_convert_encoding los reemplaza por un
     * signo de interrogacion y el titulo del reporte salia impreso como
     * "SISTEMA DE ASISTENCIA ACADÉMICA ? REPORTE OFICIAL". Las tildes y la ñ
     * si existen en ISO-8859-1 y se conservan tal cual.
     */
    public function conv(string $texto): string
    {
        $equivalencias = [
            '—' => '-',  '–' => '-',  '‒' => '-',
            '“' => '"',  '”' => '"',  '„' => '"',
            '‘' => "'",  '’' => "'",
            '•' => '-',  '·' => '-',
            '…' => '...',
            '€' => 'EUR', '→' => '->', '←' => '<-',
            "\u{00A0}" => ' '   // espacio duro
        ];

        $texto = strtr($texto, $equivalencias);

        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
}
