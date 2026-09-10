<?php

require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Materia.php';
require_once dirname(__DIR__) . '/models/Curso.php';
require_once dirname(__DIR__) . '/models/Estudiante.php';
require_once dirname(__DIR__) . '/models/Asistencia.php';
require_once dirname(__DIR__) . '/models/Catalogo.php';
require_once dirname(__DIR__) . '/models/Sesion.php';
require_once dirname(__DIR__) . '/models/Matricula.php';
require_once dirname(__DIR__) . '/models/Justificacion.php';
require_once dirname(__DIR__) . '/config/app.php';

/**
 * Reportes de asistencia.
 *
 * Todos los filtros son de SELECCION (listas desplegables), no de texto libre:
 * asi el usuario no puede escribir un valor que no exista y el resultado nunca
 * sale vacio por una falta de ortografia o una tilde.
 */
class ReporteController extends BaseController
{
    public function index(): void
    {
        $this->verificarDocente();

        $esAdmin = self::esAdmin();
        $filtros = $this->leerFiltros($esAdmin);
        $datos   = Asistencia::filtrar($filtros);

        $this->vista('reportes.index', [
            'base'        => self::obtenerRutaBase(),
            'asistencias' => $datos,
            'filtros'     => $filtros,
            'esAdmin'     => $esAdmin,
            'docentes'    => $esAdmin ? Usuario::listarDocentes() : [],
            'materias'    => Materia::listar(),
            'cursos'      => $esAdmin ? [] : Curso::listarPorDocente($this->idUsuarioActual()),
            'estudiantes' => Estudiante::listar(),
            'ambientes'   => Catalogo::AMBIENTES,
            'semestres'   => Catalogo::semestres(),
            'estados'     => Catalogo::ESTADOS,
            'total'       => count($datos),
            // El resumen del reporte tambien se muestra como grafico de pastel
            'resumenEstado' => $this->agrupar($datos, 'estado'),
            'resumenMateria'=> $this->agrupar($datos, 'materia'),
            'csrf'        => self::tokenCsrf()
        ]);
    }

    /**
     * Ficha completa de UNA clase.
     *
     * Es lo que faltaba para que el historial sirviera de algo: antes las
     * tablas "Ultimas Clases" eran texto plano y no se podia entrar a ver
     * quien estuvo en esa clase. Aqui se muestra todo junto: los datos de la
     * clase, el docente, el ambiente, el horario y la lista nominal de
     * estudiantes con su hora de entrada, de salida y el motivo si se retiro
     * antes.
     *
     * Un docente solo puede abrir las fichas de SUS clases; el administrador
     * puede abrir cualquiera.
     */
    public function detalleClase(): void
    {
        $this->verificarDocente();

        $id     = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $sesion = $id ? Sesion::buscarPorId($id) : null;

        if (!$sesion) {
            $this->redirigirConError('Esa clase no existe o fue eliminada.', '/reportes');
        }

        $esAdmin = self::esAdmin();

        if (!$esAdmin && (int)$sesion['docente_id'] !== $this->idUsuarioActual()) {
            $this->redirigirConError('Esa clase pertenece a otro docente.', '/reportes');
        }

        $asistencias = Asistencia::listarPorSesion((int)$sesion['id']);

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('reportes.clase', [
            'base'          => self::obtenerRutaBase(),
            'esAdmin'       => $esAdmin,
            'sesion'        => $sesion,
            'asistencias'   => $asistencias,
            'resumen'       => Asistencia::resumenSesion((int)$sesion['id']),
            // Cuantos deberian haber asistido: los matriculados en el curso
            'matriculados'  => Matricula::contarPorCurso((int)$sesion['curso_id']),
            // Quien falto, y si el docente le registro una justificacion.
            // Sin esta lista el detalle solo contaba a los presentes, y una
            // falta justificada era indistinguible de una sin justificar.
            'ausentes'      => Matricula::ausentesDeSesion((int)$sesion['id']),
            'mensaje'       => $mensaje,
            'error'         => $error,
            'csrf'          => self::tokenCsrf()
        ]);
    }

    /**
     * Exportacion a Excel.
     *
     * La vista de reportes ya ofrecia este boton, pero la accion no existia y el
     * enlace terminaba en un 404. Se genera una hoja en formato SpreadsheetML
     * (XML de Excel): Excel y LibreOffice la abren directamente y, a diferencia
     * del CSV, conserva los tipos, los acentos y el ancho de las columnas sin
     * depender de ninguna libreria externa.
     */
    public function exportarExcel(): void
    {
        $this->verificarDocente();

        $esAdmin = self::esAdmin();
        $filtros = $this->leerFiltros($esAdmin);
        $datos   = Asistencia::filtrar($filtros);

        $this->limpiarBuffer();

        $archivo = 'asistencias_' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$archivo}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $columnas = [
            ['Fecha', 70], ['Codigo', 60], ['Cedula', 85], ['Estudiante', 160],
            ['Semestre', 105], ['Materia', 150], ['Ambiente', 95], ['Docente', 150],
            ['Entrada', 60], ['Salida', 60], ['Estado', 105], ['Motivo', 130],
            ['Detalle', 200], ['Origen', 90]
        ];

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
           . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
           . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
           . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

        echo '<Styles>'
           . '<Style ss:ID="titulo"><Font ss:Bold="1" ss:Size="13" ss:Color="#2C356D"/></Style>'
           . '<Style ss:ID="sub"><Font ss:Size="9" ss:Color="#64748B"/></Style>'
           . '<Style ss:ID="cab">'
           . '<Font ss:Bold="1" ss:Color="#FFFFFF"/>'
           . '<Interior ss:Color="#2C356D" ss:Pattern="Solid"/>'
           . '<Alignment ss:Vertical="Center" ss:WrapText="1"/>'
           . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>'
           . '</Style>'
           . '<Style ss:ID="txt"><Alignment ss:Vertical="Top"/></Style>'
           . '</Styles>' . "\n";

        echo '<Worksheet ss:Name="Asistencias"><Table>';

        foreach ($columnas as $i => [$nombre, $ancho]) {
            echo '<Column ss:Index="' . ($i + 1) . '" ss:Width="' . $ancho . '" ss:AutoFitWidth="0"/>';
        }

        // Encabezado del documento
        echo '<Row ss:Height="22"><Cell ss:StyleID="titulo"><Data ss:Type="String">'
           . $this->xml('Reporte de Asistencias - ' . App::SIGLA) . '</Data></Cell></Row>';
        echo '<Row><Cell ss:StyleID="sub"><Data ss:Type="String">'
           . $this->xml($this->describirRango($filtros)
                . ($this->describirFiltros($filtros) !== '' ? ' | ' . $this->describirFiltros($filtros) : '')
                . ' | Generado el ' . date('d/m/Y H:i') . ' | ' . count($datos) . ' registro(s)')
           . '</Data></Cell></Row>';
        echo '<Row/>';

        // Fila de encabezados
        echo '<Row ss:Height="26">';
        foreach ($columnas as [$nombre]) {
            echo '<Cell ss:StyleID="cab"><Data ss:Type="String">' . $this->xml($nombre) . '</Data></Cell>';
        }
        echo '</Row>';

        foreach ($datos as $a) {
            $fila = [
                $a['fecha'],
                $a['codigo'],
                (string)($a['cedula'] ?? ''),
                trim($a['nombre'] . ' ' . $a['apellido']),
                $a['semestre'],
                $a['materia'],
                $a['ambiente'],
                trim($a['docente_nombre'] . ' ' . $a['docente_apellido']),
                $a['hora_entrada'] ? date('H:i:s', strtotime($a['hora_entrada'])) : '',
                $a['hora_salida'] ? date('H:i:s', strtotime($a['hora_salida'])) : '',
                Catalogo::etiquetaEstado($a['estado']),
                Catalogo::etiquetaMotivo($a['motivo']),
                (string)($a['motivo_detalle'] ?? ''),
                $a['origen'] === 'manual' ? 'Registro manual' : 'Escaneo QR'
            ];

            echo '<Row>';
            foreach ($fila as $valor) {
                // Todo va como texto: asi la cedula 0509876546 no pierde su cero
                echo '<Cell ss:StyleID="txt"><Data ss:Type="String">' . $this->xml((string)$valor) . '</Data></Cell>';
            }
            echo '</Row>';
        }

        if (empty($datos)) {
            echo '<Row><Cell ss:StyleID="txt"><Data ss:Type="String">'
               . $this->xml('No se encontraron registros con los filtros seleccionados.')
               . '</Data></Cell></Row>';
        }

        echo '</Table>'
           . '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
           . '<FreezePanes/><SplitHorizontal>4</SplitHorizontal><TopRowBottomPane>4</TopRowBottomPane>'
           . '<ActivePane>2</ActivePane></WorksheetOptions>'
           . '</Worksheet></Workbook>';
        exit;
    }

    public function exportarPdf(): void
    {
        $this->verificarDocente();

        $esAdmin = self::esAdmin();
        $filtros = $this->leerFiltros($esAdmin);
        $datos   = Asistencia::filtrar($filtros);

        require_once dirname(__DIR__) . '/libs/ReportePdf.php';

        $titular = $esAdmin ? 'Supervisión Institucional' : ($_SESSION['usuario_nombre'] ?? 'Docente');
        $pdf = new ReportePdf($titular, $this->describirRango($filtros), $this->describirFiltros($filtros));
        $pdf->AddPage();

        if (empty($datos)) {
            $pdf->Ln(5);
            $pdf->SetFont('Helvetica', 'I', 10);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(273, 14, $pdf->conv('No se encontraron registros con los filtros seleccionados.'), 1, 1, 'C');
        } else {
            $pdf->SetFont('Helvetica', '', 8);

            // Alineacion de cada columna. Los datos cortos y comparables van
            // centrados; los que se leen como texto, a la izquierda.
            $alineaciones = [
                'fecha' => 'C', 'codigo' => 'C', 'cedula' => 'C',
                'estudiante' => 'L', 'materia' => 'L', 'ambiente' => 'L',
                'entrada' => 'C', 'salida' => 'C', 'estado' => 'C', 'motivo' => 'L'
            ];

            $alterno = false;

            foreach ($datos as $a) {
                // El motivo lleva su descripcion pegada: es donde el docente
                // escribio lo que de verdad paso, y en el reporte impreso no
                // hay ningun sitio donde ir a consultarla
                $motivo = Catalogo::etiquetaMotivo($a['motivo']);
                if (!empty($a['motivo_detalle'])) {
                    $motivo = ($motivo !== '' ? $motivo . ': ' : '') . $a['motivo_detalle'];
                }

                $pdf->filaAjustada([
                    'fecha'      => $a['fecha'],
                    'codigo'     => $a['codigo'],
                    'cedula'     => (string)($a['cedula'] ?? '-'),
                    'estudiante' => trim($a['nombre'] . ' ' . $a['apellido']),
                    'materia'    => $a['materia'],
                    'ambiente'   => $a['ambiente'],
                    'entrada'    => $a['hora_entrada'] ? date('H:i', strtotime($a['hora_entrada'])) : '-',
                    'salida'     => $a['hora_salida'] ? date('H:i', strtotime($a['hora_salida'])) : '-',
                    'estado'     => Catalogo::etiquetaEstado($a['estado']),
                    'motivo'     => $motivo
                ], $alineaciones, $alterno);

                $alterno = !$alterno;
            }
        }

        // Quien responde por el documento. Sin esto el reporte no sirve como
        // respaldo ante Secretaria ni Coordinacion.
        $pdf->bloqueFirmas($titular, count($datos));

        $this->limpiarBuffer();
        $pdf->Output('D', 'asistencias_' . date('Ymd_His') . '.pdf');
        exit;
    }

    // ==================================================================
    // Filtros
    // ==================================================================

    /**
     * Lee y valida los filtros una sola vez, para que la pantalla y las
     * exportaciones usen siempre exactamente los mismos criterios.
     */
    private function leerFiltros(bool $esAdmin): array
    {
        $inicio = $this->fecha($_GET['fecha_inicio'] ?? null);
        $fin    = $this->fecha($_GET['fecha_fin'] ?? null);

        // Si el usuario invierte el rango se corrige, en vez de devolver una tabla vacia
        if ($inicio && $fin && $inicio > $fin) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        // Un docente solo ve sus propias clases; el administrador ve todo
        $docenteId = $this->idUsuarioActual();
        if ($esAdmin) {
            $sel = filter_var($_GET['docente_id'] ?? null, FILTER_VALIDATE_INT);
            $docenteId = ($sel && $sel > 0) ? $sel : null;
        }

        $ambiente = trim($_GET['ambiente'] ?? '');
        $semestre = trim($_GET['semestre'] ?? '');
        $estado   = trim($_GET['estado'] ?? '');

        // La cedula es el unico filtro que se escribe a mano, porque es
        // justamente el dato con el que secretaria pide un caso puntual.
        // Solo se acepta si es una cedula real; si no, se ignora en vez de
        // devolver una tabla vacia sin explicacion.
        $cedula = Catalogo::normalizarCedula($_GET['cedula'] ?? '');

        return [
            'docente_id'    => $docenteId,
            'materia_id'    => $this->entero($_GET['materia_id'] ?? null),
            'curso_id'      => $esAdmin ? null : $this->cursoPropio($_GET['curso_id'] ?? null),
            'estudiante_id' => $this->entero($_GET['estudiante_id'] ?? null),
            'cedula'        => Catalogo::esCedulaValida($cedula) ? $cedula : '',
            'ambiente'      => Catalogo::esAmbienteValido($ambiente) ? $ambiente : '',
            'semestre'      => Catalogo::esSemestreValido($semestre) ? $semestre : '',
            'estado'        => isset(Catalogo::ESTADOS[$estado]) ? $estado : '',
            'fecha_inicio'  => $inicio,
            'fecha_fin'     => $fin,
        ];
    }

    private function entero($valor): ?int
    {
        $n = filter_var($valor, FILTER_VALIDATE_INT);
        return ($n && $n > 0) ? $n : null;
    }

    // Solo se acepta un curso que realmente pertenezca al docente en sesion
    private function cursoPropio($valor): ?int
    {
        $id = $this->entero($valor);
        return ($id && Curso::perteneceA($id, $this->idUsuarioActual())) ? $id : null;
    }

    // Solo fechas reales con formato YYYY-MM-DD
    private function fecha(?string $valor): ?string
    {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return null;
        }

        $fecha = DateTime::createFromFormat('Y-m-d', $valor);
        return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : null;
    }

    // ==================================================================
    // Apoyo
    // ==================================================================

    /** Agrupa los resultados para dibujar el grafico de pastel del reporte */
    private function agrupar(array $datos, string $campo): array
    {
        $conteo = [];
        foreach ($datos as $fila) {
            $clave = $fila[$campo] ?? '';
            if ($clave === '') {
                continue;
            }
            $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
        }
        arsort($conteo);

        $salida = [];
        foreach ($conteo as $etiqueta => $total) {
            $salida[] = ['etiqueta' => $etiqueta, 'total' => $total];
        }
        return $salida;
    }

    private function describirRango(array $f): string
    {
        if ($f['fecha_inicio'] && $f['fecha_fin']) {
            return "{$f['fecha_inicio']} al {$f['fecha_fin']}";
        }
        if ($f['fecha_inicio']) {
            return "Desde {$f['fecha_inicio']}";
        }
        if ($f['fecha_fin']) {
            return "Hasta {$f['fecha_fin']}";
        }
        return 'Historial completo';
    }

    private function describirFiltros(array $f): string
    {
        $partes = [];
        if (!empty($f['ambiente'])) {
            $partes[] = $f['ambiente'];
        }
        if (!empty($f['semestre'])) {
            $partes[] = $f['semestre'];
        }
        if (!empty($f['estado'])) {
            $partes[] = Catalogo::etiquetaEstado($f['estado']);
        }
        if (!empty($f['cedula'])) {
            $partes[] = 'Cedula ' . $f['cedula'];
        }
        return implode(' | ', $partes);
    }

    /** Escapa un texto para incrustarlo en la hoja XML de Excel */
    private function xml(string $valor): string
    {
        // Excel rechaza el archivo entero si aparece un caracter de control
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $valor);
        return htmlspecialchars($valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    // Evita que Excel interprete como formula un texto que empiece por = + - @
    private function blindar(string $valor): string
    {
        return (isset($valor[0]) && str_contains("=+-@\t\r", $valor[0])) ? "'" . $valor : $valor;
    }

    private function limpiarBuffer(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
