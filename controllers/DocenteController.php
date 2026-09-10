<?php

require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Materia.php';
require_once dirname(__DIR__) . '/models/Curso.php';
require_once dirname(__DIR__) . '/models/Sesion.php';
require_once dirname(__DIR__) . '/models/Estudiante.php';
require_once dirname(__DIR__) . '/models/Asistencia.php';
require_once dirname(__DIR__) . '/models/Catalogo.php';
require_once dirname(__DIR__) . '/models/Matricula.php';
require_once dirname(__DIR__) . '/models/Consentimiento.php';
require_once dirname(__DIR__) . '/libs/QrCodigo.php';
require_once dirname(__DIR__) . '/libs/LectorExcel.php';
require_once dirname(__DIR__) . '/libs/Whatsapp.php';
require_once dirname(__DIR__) . '/libs/Geo.php';
require_once dirname(__DIR__) . '/libs/Totp.php';
require_once dirname(__DIR__) . '/models/Expulsion.php';
require_once dirname(__DIR__) . '/models/Justificacion.php';
require_once dirname(__DIR__) . '/config/app.php';

/**
 * Panel del Docente: es el centro de control de la clase.
 *
 * Desde aqui el docente gestiona sus cursos, abre una clase, proyecta el QR de
 * entrada, genera el QR de salida y controla en vivo quien esta presente,
 * pudiendo registrar manualmente, marcar salidas anticipadas con su motivo o
 * eliminar registros de alumnos que no estan fisicamente en el aula.
 */
class DocenteController extends BaseController
{
    private const PATRON_NOMBRE = "/^[\p{L}][\p{L}\s'.-]{1,49}$/u";

    // ==================================================================
    // Panel principal
    // ==================================================================
    public function index(): void
    {
        $this->verificarDocente();
        $docenteId = $this->idUsuarioActual();

        $sesionActiva = Sesion::abiertaDelDocente($docenteId);
        $asistencias  = [];
        $resumen      = ['total' => 0, 'presentes' => 0, 'salieron' => 0, 'anticipadas' => 0, 'manuales' => 0];
        $qrEntrada    = null;
        $qrSalida     = null;
        $urlEntrada   = '';
        $urlSalida    = '';

        if ($sesionActiva) {
            $asistencias = Asistencia::listarPorSesion((int)$sesionActiva['id']);
            $resumen     = Asistencia::resumenSesion((int)$sesionActiva['id']);

            // El QR se dibuja localmente en SVG: funciona sin internet
            if (Sesion::entradaVigente($sesionActiva)) {
                $urlEntrada = $this->urlPublica('/asistencia?c=' . $sesionActiva['codigo_entrada']);
                $qrEntrada  = QrCodigo::svg($urlEntrada, 250, 'Codigo QR de entrada a la clase');
            }
            if (Sesion::salidaVigente($sesionActiva)) {
                $urlSalida = $this->urlPublica('/asistencia?c=' . $sesionActiva['codigo_salida']);
                $qrSalida  = QrCodigo::svg($urlSalida, 250, 'Codigo QR de salida de la clase');
            }
        }

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('docente.index', [
            'base'             => self::obtenerRutaBase(),
            'docenteNombre'    => $_SESSION['usuario_nombre'] ?? 'Docente',
            'cursos'           => Curso::listarPorDocente($docenteId),
            'cursosArchivados' => Curso::listarArchivadosPorDocente($docenteId),
            'materias'         => Materia::listar(),
            'sesionActiva'     => $sesionActiva,
            'asistencias'      => $asistencias,
            'resumen'          => $resumen,
            'qrEntrada'        => $qrEntrada,
            'qrSalida'         => $qrSalida,
            'urlEntrada'       => $urlEntrada,
            'urlSalida'        => $urlSalida,
            'avisoRed'         => $this->avisoDeRed(),
            'bloqueados'       => $sesionActiva ? Expulsion::deSesion((int)$sesionActiva['id']) : [],
            // Quien esta matriculado pero no ha marcado: son los candidatos a
            // que el docente les justifique la falta
            'ausentes'         => $sesionActiva ? Matricula::ausentesDeSesion((int)$sesionActiva['id']) : [],
            'justificaciones'  => $sesionActiva ? Justificacion::deSesion((int)$sesionActiva['id']) : [],
            'tiposJustificacion' => Justificacion::TIPOS,
            'maxJustificante'  => Justificacion::MAX_BYTES,
            'geocerca'         => $sesionActiva && Geo::coordenadaValida(
                                      $sesionActiva['latitud'] ?? null,
                                      $sesionActiva['longitud'] ?? null
                                  ),
            'radioGeocerca'    => $sesionActiva ? (int)($sesionActiva['radio_metros'] ?? 0) : 0,
            'alcance'          => $this->comprobarAlcance(),
            'segundosEntrada'  => $sesionActiva ? Sesion::segundosRestantes($sesionActiva['entrada_expira']) : 0,
            'segundosSalida'   => $sesionActiva ? Sesion::segundosRestantes($sesionActiva['salida_expira'] ?? null) : 0,
            'historial'        => Sesion::historialDocente($docenteId, 8),
            'estudiantes'      => Estudiante::listar(),
            'totalClases'      => Sesion::contarPorDocente($docenteId),
            'asistenciasHoy'   => Asistencia::contarHoy($docenteId),
            'graficoMateria'   => Asistencia::porMateria($docenteId),
            'graficoEstado'    => Asistencia::porEstado($docenteId),
            'graficoAmbiente'  => Asistencia::porAmbiente($docenteId),
            'ambientes'        => Catalogo::AMBIENTES,
            'semestres'        => Catalogo::semestres(),
            'motivos'          => Catalogo::MOTIVOS,
            'minutosQr'        => Catalogo::MINUTOS_QR,
            'consentimientoOk' => Consentimiento::aceptado('usuario', $docenteId),
            'radioGeo'         => Geo::RADIO_POR_DEFECTO,
            'mensaje'          => $mensaje,
            'error'            => $error,
            'csrf'             => self::tokenCsrf()
        ]);
    }

    // ==================================================================
    // Gestion de cursos (una materia por ambiente)
    // ==================================================================
    public function crearCurso(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $materiaId = filter_var($_POST['materia_id'] ?? null, FILTER_VALIDATE_INT);
        $ambiente  = trim($_POST['ambiente'] ?? '');
        $semestre  = trim($_POST['semestre'] ?? '');

        if (!$materiaId || !Materia::existe($materiaId)) {
            $this->redirigirConError('Selecciona una materia válida de la lista.', '/docente');
        }
        if (!Catalogo::esAmbienteValido($ambiente)) {
            $this->redirigirConError('Selecciona un ambiente válido (Aula, Laboratorio o Aula Interactiva).', '/docente');
        }
        if (!Catalogo::esSemestreValido($semestre)) {
            $this->redirigirConError('Selecciona un semestre válido.', '/docente');
        }
        $resultado = Curso::crear($materiaId, $this->idUsuarioActual(), $ambiente, $semestre);

        if ($resultado === 'duplicado') {
            $this->redirigirConError('Ya tienes un curso con esa misma materia, ambiente y semestre.', '/docente');
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudo crear el curso.', '/docente');
        }

        $this->redirigirConMensaje('Curso creado correctamente. Ya puedes abrir una clase.', '/docente');
    }

    public function eliminarCurso(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $cursoId = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$cursoId || !Curso::perteneceA($cursoId, $this->idUsuarioActual())) {
            $this->redirigirConError('Ese curso no existe o no te pertenece.', '/docente');
        }

        if (Curso::desactivar($cursoId, $this->idUsuarioActual())) {
            $this->redirigirConMensaje('Curso archivado. Su historial de clases se conserva en los reportes.', '/docente');
        }

        $this->redirigirConError('No se pudo archivar el curso.', '/docente');
    }

    /**
     * Devuelve a la lista un curso que se archivo.
     *
     * Archivar nunca borro nada, pero hasta ahora el curso archivado quedaba
     * invisible y sin manera de recuperarlo. Con esto vuelve a estar activo
     * con todo su historial.
     */
    public function restaurarCurso(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $cursoId = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);

        // perteneceA no filtra por activo, asi que sirve tambien para los archivados
        if (!$cursoId || !Curso::perteneceA($cursoId, $this->idUsuarioActual())) {
            $this->redirigirConError('Ese curso no existe o no te pertenece.', '/docente');
        }

        if (Curso::reactivar($cursoId, $this->idUsuarioActual())) {
            $this->redirigirConMensaje('Curso restaurado. Ya puedes volver a abrir clases con él.', '/docente');
        }

        $this->redirigirConError('No se pudo restaurar el curso.', '/docente');
    }

    // ==================================================================
    // Ciclo de vida de la clase y sus codigos QR
    // ==================================================================
    public function abrirClase(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $docenteId = $this->idUsuarioActual();
        $cursoId   = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$cursoId || !Curso::perteneceA($cursoId, $docenteId)) {
            $this->redirigirConError('Selecciona uno de tus cursos para iniciar la clase.', '/docente');
        }

        // Solo puede haber una clase abierta a la vez por docente
        if (Sesion::abiertaDelDocente($docenteId)) {
            $this->redirigirConError('Ya tienes una clase abierta. Ciérrala antes de iniciar otra.', '/docente');
        }

        // El navegador manda las coordenadas del aula si pudo obtenerlas.
        // Se convierten en el centro de la geocerca: a partir de ahi, quien
        // este a mas de N metros no podra registrarse aunque tenga el codigo.
        $latitud  = $_POST['latitud']  ?? null;
        $longitud = $_POST['longitud'] ?? null;
        $radio    = filter_var($_POST['radio'] ?? null, FILTER_VALIDATE_INT) ?: Geo::RADIO_POR_DEFECTO;

        $hayGeo = Geo::coordenadaValida($latitud, $longitud);

        $sesion = Sesion::abrir(
            $cursoId,
            $docenteId,
            $hayGeo ? (float)$latitud  : null,
            $hayGeo ? (float)$longitud : null,
            $radio
        );

        if (!$sesion) {
            $this->redirigirConError('No se pudo iniciar la clase. Intenta nuevamente.', '/docente');
        }

        $this->redirigirConMensaje(
            'Clase iniciada. El código QR estará activo ' . Catalogo::MINUTOS_QR . ' minutos.'
            . ($hayGeo
                ? ' Solo podrán registrarse quienes estén a menos de ' . Geo::formatear($radio) . ' del aula.'
                : ' Sin control de ubicación: el navegador no entregó las coordenadas.'),
            '/docente'
        );
    }

    public function renovarEntrada(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $sesion = $this->sesionAbiertaPropia();

        if (Sesion::renovarEntrada((int)$sesion['id'], $this->idUsuarioActual())) {
            $this->redirigirConMensaje(
                'Código QR de ENTRADA nuevo por ' . Catalogo::MINUTOS_QR . ' minutos. El de salida quedó desactivado.',
                '/docente'
            );
        }

        $this->redirigirConError('No se pudo renovar el código QR de entrada.', '/docente');
    }

    public function cerrarEntrada(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $sesion = $this->sesionAbiertaPropia();

        if (Sesion::cerrarEntrada((int)$sesion['id'], $this->idUsuarioActual())) {
            $this->redirigirConMensaje('Registro de entrada cerrado. La clase sigue activa.', '/docente');
        }

        $this->redirigirConError('No se pudo cerrar el registro de entrada.', '/docente');
    }

    public function generarSalida(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $sesion = $this->sesionAbiertaPropia();

        if (Sesion::generarSalida((int)$sesion['id'], $this->idUsuarioActual())) {
            $this->redirigirConMensaje(
                'Código QR de SALIDA activo ' . Catalogo::MINUTOS_QR . ' minutos. El de entrada quedó cerrado.',
                '/docente'
            );
        }

        $this->redirigirConError('No se pudo generar el código QR de salida.', '/docente');
    }

    public function cerrarClase(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $sesion = $this->sesionAbiertaPropia();

        if (Sesion::cerrar((int)$sesion['id'], $this->idUsuarioActual())) {
            $this->redirigirConMensaje('Clase finalizada. Los alumnos presentes quedaron con su hora de salida.', '/docente');
        }

        $this->redirigirConError('No se pudo finalizar la clase.', '/docente');
    }

    // ==================================================================
    // Control de la lista de asistencia en vivo
    // ==================================================================

    /** Registra manualmente a un alumno que no pudo escanear el QR */
    public function registrarManual(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $sesion = $this->sesionAbiertaPropia();
        $modo   = trim($_POST['modo'] ?? 'padron');
        if (!in_array($modo, ['padron', 'cedula', 'nuevo'], true)) {
            $modo = 'padron';
        }

        if ($modo === 'padron') {
            $estudianteId = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$estudianteId || !Estudiante::buscarPorId($estudianteId)) {
                $this->redirigirConError('Selecciona un estudiante de la lista.', '/docente');
            }
        } elseif ($modo === 'cedula') {
            // El alumno esta en el padron pero no recuerda su codigo: la cedula
            // lo identifica sin riesgo de confundirlo con un homonimo.
            $cedula = Catalogo::normalizarCedula($_POST['cedula'] ?? '');

            if (!Catalogo::esCedulaValida($cedula)) {
                $this->redirigirConError('El número de cédula no es válido. Revisa los 10 dígitos.', '/docente');
            }

            $estudiante = Estudiante::buscarPorCedula($cedula);

            if (!$estudiante) {
                $this->redirigirConError(
                    "La cédula {$cedula} no está registrada. Usa \"Estudiante nuevo\" para darlo de alta.",
                    '/docente'
                );
            }

            $estudianteId = (int)$estudiante['id'];
        } else {
            // Alta rapida de un alumno que aun no esta en el padron
            $nombre   = $this->limpiarTexto($_POST['nombre'] ?? '');
            $apellido = $this->limpiarTexto($_POST['apellido'] ?? '');
            $semestre = trim($_POST['semestre'] ?? '');
            $cedula   = Catalogo::normalizarCedula($_POST['cedula'] ?? '');

            $this->validarNombre($nombre, 'nombre', '/docente');
            $this->validarNombre($apellido, 'apellido', '/docente');

            if (!Catalogo::esSemestreValido($semestre)) {
                $this->redirigirConError('Selecciona un semestre válido para el estudiante.', '/docente');
            }

            // La cedula es opcional aqui, pero si se escribe tiene que ser real:
            // una cedula inventada arruinaria la identificacion futura del alumno
            if ($cedula !== '' && !Catalogo::esCedulaValida($cedula)) {
                $this->redirigirConError('El número de cédula no es válido. Revisa los 10 dígitos.', '/docente');
            }

            // Si esa cedula ya existe, ese ES el alumno: no se crea un duplicado
            $existente = ($cedula !== '') ? Estudiante::buscarPorCedula($cedula) : null;

            if (!$existente) {
                $existente = Estudiante::buscarPorNombre($nombre, $apellido);

                // Alumno viejo sin cedula cargada: se aprovecha para completarla
                if ($existente && $cedula !== '' && empty($existente['cedula'])) {
                    Estudiante::asignarCedula((int)$existente['id'], $cedula);
                }
            }

            $estudiante = $existente ?: Estudiante::crear($nombre, $apellido, $semestre, ($cedula !== '' ? $cedula : null));

            if (!$estudiante) {
                $this->redirigirConError('No se pudo registrar al estudiante nuevo.', '/docente');
            }

            $estudianteId = (int)$estudiante['id'];
        }

        $resultado = Asistencia::registrarEntrada((int)$sesion['id'], $estudianteId, 'manual');

        if ($resultado === 'duplicada') {
            $this->redirigirConError('Ese estudiante ya está registrado en esta clase.', '/docente');
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudo registrar la asistencia manual.', '/docente');
        }

        $this->redirigirConMensaje('Asistencia registrada manualmente.', '/docente');
    }

    /** Marca la salida anticipada de un alumno indicando el motivo */
    public function marcarSalida(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $asistenciaId = $this->asistenciaPropia();

        $motivo  = trim($_POST['motivo'] ?? '');
        $detalle = trim($_POST['motivo_detalle'] ?? '');

        if (!Catalogo::esMotivoValido($motivo)) {
            $this->redirigirConError('Selecciona un motivo válido para la salida anticipada.', '/docente');
        }

        if (mb_strlen($detalle) > 200) {
            $this->redirigirConError('La descripción del motivo no puede superar los 200 caracteres.', '/docente');
        }

        // El motivo "Otro" obliga a escribir la descripcion: si no, se pierde la razon real
        if ($motivo === 'Otro' && $detalle === '') {
            $this->redirigirConError('Al elegir "Otro" debes describir brevemente el motivo.', '/docente');
        }

        if (Asistencia::marcarSalidaAnticipada($asistenciaId, $motivo, $detalle)) {
            // Se le dice al docente en que quedo la salida, porque no es lo
            // mismo de cara al cierre del periodo
            $justificada = Catalogo::estadoDeSalida($motivo) === 'salida_justificada';

            $this->redirigirConMensaje(
                $justificada
                    ? 'Salida registrada como JUSTIFICADA (' . Catalogo::etiquetaMotivo($motivo) . '). '
                    . 'Si tienes el respaldo en papel, adjúntalo desde "Justificar".'
                    : 'Salida anticipada registrada como NO justificada ('
                    . Catalogo::etiquetaMotivo($motivo) . ').',
                '/docente'
            );
        }

        $this->redirigirConError('No se pudo registrar la salida anticipada.', '/docente');
    }

    /**
     * Aprueba el registro de un alumno que no estaba matriculado.
     *
     * Ademas de aprobar la asistencia lo MATRICULA en el curso: si el docente
     * confirma que pertenece a la clase, no tiene sentido volver a preguntarlo
     * la proxima vez.
     */
    public function aprobarAsistencia(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $asistenciaId = $this->asistenciaPropia();
        $asistencia   = Asistencia::buscarPorId($asistenciaId);

        if (!$asistencia) {
            $this->redirigirConError('Ese registro ya no existe.', '/docente');
        }

        if (!Asistencia::aprobar($asistenciaId)) {
            $this->redirigirConError('No se pudo aprobar el registro.', '/docente');
        }

        $sesion = Sesion::buscarPorId((int)$asistencia['sesion_id']);

        if ($sesion) {
            Matricula::matricular((int)$asistencia['estudiante_id'], (int)$sesion['curso_id']);
        }

        $this->redirigirConMensaje(
            trim($asistencia['nombre'] . ' ' . $asistencia['apellido'])
            . ' quedó aprobado y matriculado en el curso.',
            '/docente'
        );
    }

    /** Devuelve a un alumno a "En clase" si la salida se marco por error */
    public function devolverAClase(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $asistenciaId = $this->asistenciaPropia();

        if (Asistencia::devolverAClase($asistenciaId)) {
            $this->redirigirConMensaje('El estudiante volvió al estado "En clase".', '/docente');
        }

        $this->redirigirConError('No se pudo actualizar el estado del estudiante.', '/docente');
    }

    /** Elimina un registro falso (alumno que compartio su QR y no esta en el aula) */
    /**
     * Elimina un registro falso y BLOQUEA a ese alumno en esta clase.
     *
     * Sin el bloqueo, el alumno volvia a escanear el mismo QR y se registraba
     * otra vez: el docente lo sacaba, el entraba, y asi indefinidamente. Con
     * el bloqueo de horas se acaba el bucle, y solo afecta a esta clase: sus
     * otras materias siguen normales.
     */
    public function eliminarAsistencia(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $asistenciaId = $this->asistenciaPropia();
        $asistencia   = Asistencia::buscarPorId($asistenciaId);

        if (!$asistencia) {
            $this->redirigirConError('Ese registro ya no existe.', '/docente');
        }

        if (!Asistencia::eliminar($asistenciaId)) {
            $this->redirigirConError('No se pudo eliminar el registro.', '/docente');
        }

        Expulsion::registrar(
            (int)$asistencia['sesion_id'],
            (int)$asistencia['estudiante_id'],
            $this->idUsuarioActual(),
            'Retirado por el docente de la lista de clase'
        );

        $this->redirigirConMensaje(
            trim($asistencia['nombre'] . ' ' . $asistencia['apellido'])
            . ' fue retirado. No podrá volver a registrarse en esta clase durante '
            . Expulsion::HORAS_BLOQUEO . ' horas.',
            '/docente'
        );
    }

    /** Levanta el bloqueo: el docente reconsidera y lo deja volver */
    public function levantarBloqueo(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $sesion       = $this->sesionAbiertaPropia();
        $estudianteId = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$estudianteId) {
            $this->redirigirConError('Estudiante no válido.', '/docente');
        }

        Expulsion::levantar((int)$sesion['id'], $estudianteId);

        $this->redirigirConMensaje('Bloqueo levantado. El estudiante ya puede registrarse.', '/docente');
    }

    // ==================================================================
    // MATRICULAS
    //
    // Es la lista de clase: quien pertenece a cada curso. Sin ella no habia
    // forma de comprobar una asistencia, porque cualquiera que tuviera el
    // codigo QR escribia un nombre y quedaba registrado.
    //
    // El administrador asigna las materias al docente; el docente decide
    // que estudiantes van en cada una.
    // ==================================================================

    public function matriculas(): void
    {
        $this->verificarDocente();
        $docenteId = $this->idUsuarioActual();

        $cursos = Curso::listarPorDocente($docenteId);

        // Curso seleccionado: el de la URL si es suyo, si no el primero
        $cursoId = filter_var($_GET['curso_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$cursoId || !Curso::perteneceA($cursoId, $docenteId)) {
            $cursoId = !empty($cursos) ? (int)$cursos[0]['id'] : 0;
        }

        // Filtro de la lista: sirve sobre todo para ver quien entro en la
        // ultima importacion y a quien le falta telefono para avisarle
        $filtro = trim($_GET['filtro'] ?? '');
        if (!in_array($filtro, ['nuevos', 'sin_telefono', 'con_telefono'], true)) {
            $filtro = '';
        }

        $curso        = $cursoId ? Curso::buscarPorId($cursoId) : null;
        $matriculados = $cursoId ? Matricula::estudiantesDeCurso($cursoId, true, $filtro) : [];
        $candidatos   = $cursoId ? Matricula::candidatos($cursoId) : [];

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('docente.matriculas', [
            'base'         => self::obtenerRutaBase(),
            'cursos'       => $cursos,
            'curso'        => $curso,
            'cursoId'      => $cursoId,
            'matriculados' => $matriculados,
            'candidatos'   => $candidatos,
            'filtro'       => $filtro,
            'totalCurso'   => $cursoId ? Matricula::contarPorCurso($cursoId) : 0,
            'totalNuevos'  => $cursoId ? Matricula::contarNuevos($cursoId) : 0,
            'semestres'    => Catalogo::semestres(),
            'resumenImport'=> $_SESSION['resumen_import'] ?? null,
            'mensaje'      => $mensaje,
            'error'        => $error,
            'csrf'         => self::tokenCsrf()
        ]);

        unset($_SESSION['resumen_import']);
    }

    /** Matricula a uno o varios estudiantes que ya estan en el padron */
    public function matricular(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/matriculas');

        $cursoId = $this->cursoPropio();
        $ids     = $_POST['estudiante_id'] ?? [];

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            $this->redirigirConError('Selecciona al menos un estudiante.', '/docente/matriculas?curso_id=' . $cursoId);
        }

        $r = Matricula::matricularVarios($ids, $cursoId);

        $partes = [];
        if ($r['nuevas'] > 0)    { $partes[] = $r['nuevas'] . ' matriculado(s)'; }
        if ($r['repetidas'] > 0) { $partes[] = $r['repetidas'] . ' ya estaban en el curso'; }

        $this->redirigirConMensaje(
            empty($partes) ? 'No se matriculó a nadie.' : implode(' · ', $partes),
            '/docente/matriculas?curso_id=' . $cursoId
        );
    }

    /**
     * Inscribe manualmente a un estudiante NUEVO y lo matricula de una vez.
     * Es el caso del alumno que llega a mitad de periodo y todavia no esta
     * en ningun padron.
     */
    public function inscribirEstudiante(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/matriculas');

        $cursoId  = $this->cursoPropio();
        $ruta     = '/docente/matriculas?curso_id=' . $cursoId;

        $nombre   = $this->limpiarTexto($_POST['nombre'] ?? '');
        $apellido = $this->limpiarTexto($_POST['apellido'] ?? '');
        $semestre = trim($_POST['semestre'] ?? '');
        $cedula   = Catalogo::normalizarCedula($_POST['cedula'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        $this->validarNombre($nombre, 'nombre', $ruta);
        $this->validarNombre($apellido, 'apellido', $ruta);

        if (!Catalogo::esSemestreValido($semestre)) {
            $this->redirigirConError('Selecciona un semestre válido.', $ruta);
        }

        // La cedula es obligatoria aqui: es lo que despues le permite al alumno
        // registrarse solo aunque pierda el carnet u olvide su codigo
        if (!Catalogo::esCedulaValida($cedula)) {
            $this->redirigirConError('La cédula no es válida. Revisa los 10 dígitos.', $ruta);
        }

        // El telefono tambien es obligatorio: sin numero no hay forma de
        // hacerle llegar su codigo, y el alumno se queda sin poder registrarse
        if (Estudiante::normalizarTelefono($telefono) === null) {
            $this->redirigirConError(
                'El teléfono es obligatorio y debe tener 10 dígitos (ejemplo: 0991112233). '
                . 'Sin número no se le puede enviar su código.',
                $ruta
            );
        }

        $existente = Estudiante::buscarPorCedula($cedula);

        if ($existente) {
            // Ya estaba en el padron: no se duplica, solo se matricula
            if (Matricula::matricular((int)$existente['id'], $cursoId) === 'duplicada') {
                $this->redirigirConError(
                    trim($existente['nombre'] . ' ' . $existente['apellido']) . ' ya está matriculado en este curso.',
                    $ruta
                );
            }

            $this->redirigirConMensaje(
                'Esa cédula ya existía en el padrón (' . htmlspecialchars($existente['codigo'])
                . '). Se matriculó a ese estudiante en el curso.',
                $ruta
            );
        }

        $nuevo = Estudiante::crear($nombre, $apellido, $semestre, $cedula, $telefono);

        if (!$nuevo) {
            $this->redirigirConError('No se pudo crear el estudiante.', $ruta);
        }

        Matricula::matricular((int)$nuevo['id'], $cursoId);

        $this->redirigirConMensaje(
            "Estudiante inscrito con el código {$nuevo['codigo']} y matriculado en el curso.",
            $ruta
        );
    }

    /**
     * Corrige los datos de un estudiante: nombre, cedula, telefono, semestre.
     * Sirve para cuando cambia de numero o se cargo un dato mal escrito.
     *
     * El codigo (EST001) y el carnet QR no se tocan: son su identidad, y
     * cambiarlos dejaria inservible el carnet que ya tiene impreso.
     */
    public function actualizarEstudiante(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/matriculas');

        $cursoId = $this->cursoPropio();
        $ruta    = '/docente/matriculas?curso_id=' . $cursoId;

        $estudianteId = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);
        $estudiante   = $estudianteId ? Estudiante::buscarPorId($estudianteId) : null;

        if (!$estudiante) {
            $this->redirigirConError('Ese estudiante ya no existe.', $ruta);
        }

        // Solo se pueden editar alumnos del propio curso del docente
        if (!Matricula::existe($estudianteId, $cursoId)) {
            $this->redirigirConError('Ese estudiante no está matriculado en este curso.', $ruta);
        }

        $nombre   = $this->limpiarTexto($_POST['nombre'] ?? '');
        $apellido = $this->limpiarTexto($_POST['apellido'] ?? '');
        $semestre = trim($_POST['semestre'] ?? '');
        $cedula   = Catalogo::normalizarCedula($_POST['cedula'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        $this->validarNombre($nombre, 'nombre', $ruta);
        $this->validarNombre($apellido, 'apellido', $ruta);

        if (!Catalogo::esSemestreValido($semestre)) {
            $this->redirigirConError('Selecciona un semestre válido.', $ruta);
        }
        if (!Catalogo::esCedulaValida($cedula)) {
            $this->redirigirConError('La cédula no es válida. Revisa los 10 dígitos.', $ruta);
        }
        if (Estudiante::normalizarTelefono($telefono) === null) {
            $this->redirigirConError(
                'El teléfono debe tener 10 dígitos (ejemplo: 0991112233).',
                $ruta
            );
        }

        $resultado = Estudiante::actualizarCompleto(
            $estudianteId, $nombre, $apellido, $semestre, $cedula, $telefono
        );

        if ($resultado === 'cedula_ocupada') {
            $this->redirigirConError("La cédula {$cedula} ya pertenece a otro estudiante.", $ruta);
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudieron guardar los cambios.', $ruta);
        }

        $this->redirigirConMensaje(
            'Datos de ' . trim($nombre . ' ' . $apellido) . ' actualizados.',
            $ruta
        );
    }

    public function quitarMatricula(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/matriculas');

        $cursoId      = $this->cursoPropio();
        $estudianteId = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$estudianteId) {
            $this->redirigirConError('Estudiante no válido.', '/docente/matriculas?curso_id=' . $cursoId);
        }

        Matricula::quitar($estudianteId, $cursoId);

        $this->redirigirConMensaje(
            'Estudiante retirado del curso. Su historial de asistencias se conserva.',
            '/docente/matriculas?curso_id=' . $cursoId
        );
    }

    /**
     * Carga masiva desde Excel.
     *
     * El archivo debe traer, en este orden: cedula, nombres, apellidos,
     * semestre y telefono. Se valida fila por fila y se informa exactamente
     * que paso con cada una, en vez de fallar en silencio.
     */
    public function importarEstudiantes(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/matriculas');

        $cursoId = $this->cursoPropio();
        $ruta    = '/docente/matriculas?curso_id=' . $cursoId;

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->redirigirConError($this->errorDeSubida($_FILES['archivo']['error'] ?? -1), $ruta);
        }

        // Tope de 3 MB: una lista de curso jamas pesa tanto, y un archivo
        // mucho mayor solo puede ser un error o un intento de saturar
        if ($_FILES['archivo']['size'] > 3 * 1024 * 1024) {
            $this->redirigirConError('El archivo supera los 3 MB permitidos.', $ruta);
        }

        try {
            $filas = LectorExcel::leer($_FILES['archivo']['tmp_name'], $_FILES['archivo']['name']);
        } catch (RuntimeException $e) {
            $this->redirigirConError($e->getMessage(), $ruta);
        }

        if (count($filas) < 2) {
            $this->redirigirConError('El archivo está vacío o solo tiene la fila de títulos.', $ruta);
        }

        $resultado = $this->procesarFilas($filas, $cursoId);

        self::abrirSesion();
        $_SESSION['resumen_import'] = $resultado;

        $this->redirigirConMensaje(
            "{$resultado['creados']} creado(s), {$resultado['matriculados']} matriculado(s), "
            . "{$resultado['rechazados']} con problemas.",
            $ruta
        );
    }

    /** Recorre las filas del Excel y devuelve el detalle de lo que paso */
    private function procesarFilas(array $filas, int $cursoId): array
    {
        $creados = 0;
        $matriculados = 0;
        $problemas = [];

        // Se salta la primera fila si parece un encabezado
        $inicio = (isset($filas[0][0]) && !ctype_digit(preg_replace('/\D/', '', $filas[0][0]))) ? 1 : 0;
        if ($inicio === 0 && isset($filas[0][0]) && stripos($filas[0][0], 'ced') !== false) {
            $inicio = 1;
        }

        $semestresValidos = Catalogo::semestres();

        for ($i = $inicio, $n = count($filas); $i < $n; $i++) {
            $fila     = $filas[$i];
            $numero   = $i + 1;

            $cedula   = Catalogo::normalizarCedula($fila[0] ?? '');
            $nombre   = $this->limpiarTexto($fila[1] ?? '');
            $apellido = $this->limpiarTexto($fila[2] ?? '');
            $semestre = trim($fila[3] ?? '');
            $telefono = trim($fila[4] ?? '');

            if ($cedula === '' && $nombre === '' && $apellido === '') {
                continue;   // fila en blanco
            }

            if (!Catalogo::esCedulaValida($cedula)) {
                $problemas[] = "Fila {$numero}: la cédula \"" . ($fila[0] ?? '') . "\" no es válida.";
                continue;
            }
            if (!preg_match(self::PATRON_NOMBRE, $nombre)) {
                $problemas[] = "Fila {$numero}: el nombre \"{$nombre}\" no es válido.";
                continue;
            }
            if (!preg_match(self::PATRON_NOMBRE, $apellido)) {
                $problemas[] = "Fila {$numero}: el apellido \"{$apellido}\" no es válido.";
                continue;
            }

            // El telefono es obligatorio igual que en el alta manual: sin
            // numero no hay forma de hacerle llegar su codigo al alumno, y
            // entonces la carga masiva no sirve de nada.
            if (Estudiante::normalizarTelefono($telefono) === null) {
                $problemas[] = "Fila {$numero}: falta el teléfono de {$nombre} {$apellido} "
                             . 'o no tiene 10 dígitos (ejemplo: 0991112233).';
                continue;
            }

            // El semestre se acepta escrito de forma aproximada ("3", "tercero")
            $semestreReal = $this->interpretarSemestre($semestre, $semestresValidos);

            if ($semestreReal === null) {
                $problemas[] = "Fila {$numero}: el semestre \"{$semestre}\" no existe. "
                             . 'Usa uno de: ' . implode(', ', $semestresValidos);
                continue;
            }

            $existente = Estudiante::buscarPorCedula($cedula);

            if ($existente) {
                $estudiante = $existente;
                // Se completa el telefono si el alumno no lo tenia
                if (empty($existente['telefono']) && $telefono !== '') {
                    Estudiante::guardarTelefono((int)$existente['id'], $telefono);
                }
            } else {
                $estudiante = Estudiante::crear($nombre, $apellido, $semestreReal, $cedula, $telefono);

                if (!$estudiante) {
                    $problemas[] = "Fila {$numero}: no se pudo guardar a {$nombre} {$apellido}.";
                    continue;
                }
                $creados++;
            }

            if (Matricula::matricular((int)$estudiante['id'], $cursoId) === 'ok') {
                $matriculados++;
            }
        }

        return [
            'creados'      => $creados,
            'matriculados' => $matriculados,
            'rechazados'   => count($problemas),
            'problemas'    => array_slice($problemas, 0, 40)
        ];
    }

    /** Acepta "Tercer Semestre", "tercero", "3" o "3ro" */
    private function interpretarSemestre(string $texto, array $validos): ?string
    {
        $texto = trim($texto);

        if ($texto === '') {
            return null;
        }

        foreach ($validos as $valido) {
            if (mb_strtolower($valido) === mb_strtolower($texto)) {
                return $valido;
            }
        }

        // Por numero: "3" o "3ro" -> el tercero de la lista
        if (preg_match('/^(\d+)/', $texto, $m)) {
            $indice = (int)$m[1] - 1;
            return $validos[$indice] ?? null;
        }

        // Por la primera palabra: "tercero" contra "Tercer Semestre"
        $raiz = mb_strtolower(mb_substr(preg_replace('/[^\p{L}]/u', '', $texto), 0, 5));

        foreach ($validos as $valido) {
            if ($raiz !== '' && str_starts_with(mb_strtolower($valido), $raiz)) {
                return $valido;
            }
        }

        return null;
    }

    private function errorDeSubida(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
            UPLOAD_ERR_PARTIAL   => 'La subida se interrumpió. Vuelve a intentarlo.',
            UPLOAD_ERR_NO_FILE   => 'No seleccionaste ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar el archivo temporal.',
            default => 'No se pudo subir el archivo.'
        };
    }

    /** Plantilla de ejemplo para la carga masiva */
    public function plantillaEstudiantes(): void
    {
        $this->verificarDocente();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_estudiantes.csv"');

        $salida = fopen('php://output', 'w');
        fwrite($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));   // BOM para Excel

        // Los cinco campos son obligatorios: una fila incompleta se rechaza
        fputcsv($salida, ['cedula', 'nombres', 'apellidos', 'semestre', 'telefono'], ';');

        $ejemploSemestre = Catalogo::semestres()[0] ?? 'Primer Semestre';
        fputcsv($salida, ['1701234567', 'Saul', 'Andrade', $ejemploSemestre, '0991112233'], ';');
        fputcsv($salida, ['1712345675', 'Mateo', 'Zambrano', $ejemploSemestre, '0987654321'], ';');

        fclose($salida);
        exit;
    }

    /**
     * Prepara el envio de credenciales a los estudiantes de un curso.
     *
     * WhatsApp no permite enviar en masa sin su API de negocios de pago, asi
     * que lo que se hace aqui es dejar todo listo: un enlace por alumno con el
     * mensaje ya escrito, mas la opcion de copiarlos todos de golpe. Enviar
     * pasa a ser un clic por persona en vez de redactar cada mensaje.
     */
    public function envios(): void
    {
        $this->verificarDocente();
        $docenteId = $this->idUsuarioActual();

        $cursos  = Curso::listarPorDocente($docenteId);
        $cursoId = filter_var($_GET['curso_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$cursoId || !Curso::perteneceA($cursoId, $docenteId)) {
            $cursoId = !empty($cursos) ? (int)$cursos[0]['id'] : 0;
        }

        $filtro = trim($_GET['filtro'] ?? '');
        if (!in_array($filtro, ['nuevos', 'sin_telefono', 'con_telefono'], true)) {
            $filtro = '';
        }

        $curso        = $cursoId ? Curso::buscarPorId($cursoId) : null;
        $estudiantes  = $cursoId ? Matricula::estudiantesDeCurso($cursoId, true, $filtro) : [];
        $etiquetaCurso = $curso ? Curso::etiqueta($curso) : null;

        $tanda = Whatsapp::prepararTanda(
            $estudiantes,
            static fn(array $e) => Whatsapp::mensajeEstudiante($e, App::SIGLA, $etiquetaCurso)
        );

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('docente.envios', [
            'base'        => self::obtenerRutaBase(),
            'cursos'      => $cursos,
            'curso'       => $curso,
            'cursoId'     => $cursoId,
            'filtro'      => $filtro,
            'listos'      => $tanda['listos'],
            'sinTelefono' => $tanda['sinTelefono'],
            'mensaje'     => $mensaje,
            'error'       => $error,
            'csrf'        => self::tokenCsrf()
        ]);
    }

    /** El curso indicado en el formulario, comprobando que sea del docente */
    private function cursoPropio(): int
    {
        $cursoId = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$cursoId || !Curso::perteneceA($cursoId, $this->idUsuarioActual())) {
            $this->redirigirConError('Ese curso no existe o no te pertenece.', '/docente/matriculas');
        }

        return $cursoId;
    }

    // ==================================================================
    // Carnet QR del estudiante
    // ==================================================================
    public function carnets(): void
    {
        $this->verificarDocente();

        $semestre = trim($_GET['semestre'] ?? '');
        if (!Catalogo::esSemestreValido($semestre)) {
            $semestre = '';
        }

        $estudiantes = Estudiante::listar($semestre);

        // Cada carnet lleva el token personal e irrepetible del alumno
        foreach ($estudiantes as &$e) {
            $e['qr'] = QrCodigo::svg(
                $this->urlPublica('/asistencia?carnet=' . $e['token_qr']),
                150,
                'Carnet QR de ' . Estudiante::nombreCompleto($e)
            );
        }
        unset($e);

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('docente.carnets', [
            'base'        => self::obtenerRutaBase(),
            'estudiantes' => $estudiantes,
            'semestres'   => Catalogo::semestres(),
            'semestre'    => $semestre,
            'avisoRed'    => $this->avisoDeRed(),
            'mensaje'     => $mensaje,
            'error'       => $error,
            'csrf'        => self::tokenCsrf()
        ]);
    }

    /** Carga o corrige la cedula de un alumno desde la pantalla de carnets */
    public function asignarCedula(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/carnets');

        $estudianteId = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);
        $cedula       = Catalogo::normalizarCedula($_POST['cedula'] ?? '');

        if (!$estudianteId || !Estudiante::buscarPorId($estudianteId)) {
            $this->redirigirConError('Estudiante no encontrado.', '/docente/carnets');
        }

        if (!Catalogo::esCedulaValida($cedula)) {
            $this->redirigirConError(
                'El número de cédula no es válido. Deben ser 10 dígitos y el último es el verificador.',
                '/docente/carnets'
            );
        }

        if (!Estudiante::asignarCedula($estudianteId, $cedula)) {
            $this->redirigirConError(
                "La cédula {$cedula} ya pertenece a otro estudiante del padrón.",
                '/docente/carnets'
            );
        }

        $this->redirigirConMensaje(
            'Cédula guardada. El estudiante ya puede registrarse con ella si olvida su código.',
            '/docente/carnets'
        );
    }

    // ==================================================================
    // PERFIL Y VERIFICACION EN DOS PASOS
    //
    // El docente configura aqui su Google Authenticator. Se hace en dos
    // tiempos a proposito: primero se prepara el secreto y se muestra el QR,
    // y solo cuando escribe un codigo correcto queda activado. Si se activara
    // de una, un fallo al escanear lo dejaria fuera del sistema.
    // ==================================================================

    public function perfil(): void
    {
        $this->verificarDocente();

        $usuario = Usuario::buscarPorId($this->idUsuarioActual());

        if (!$usuario) {
            $this->redirigirConError('No se pudo cargar tu perfil.', '/docente');
        }

        $secreto = Usuario::secretoTotp((int)$usuario['id']);
        $activo  = Usuario::tieneTotpActivo($usuario);

        // Si hay un secreto sin confirmar, se vuelve a mostrar su QR para
        // que pueda terminar la configuracion donde la dejo
        $qr = null;
        $uri = null;

        if ($secreto !== null && !$activo) {
            $uri = Totp::uri($secreto, $usuario['correo'], App::SIGLA);
            $qr  = QrCodigo::svg($uri, 220, 'Codigo QR para Google Authenticator');
        }

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('docente.perfil', [
            'base'            => self::obtenerRutaBase(),
            'usuario'         => $usuario,
            'totpActivo'      => $activo,
            'consentimientoOk'    => Consentimiento::aceptado('usuario', (int)$usuario['id']),
            'consentimientoFecha' => Consentimiento::fecha('usuario', (int)$usuario['id']),
            'totpPreparado'   => ($secreto !== null && !$activo),
            'qrTotp'          => $qr,
            'secretoLegible'  => $secreto !== null ? Totp::secretoLegible($secreto) : null,
            'mensaje'         => $mensaje,
            'error'           => $error,
            'csrf'            => self::tokenCsrf()
        ]);
    }

    /** Paso 1: genera el secreto y muestra el QR para escanear */
    public function prepararTotp(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/perfil');

        $usuario = Usuario::buscarPorId($this->idUsuarioActual());

        if ($usuario && Usuario::tieneTotpActivo($usuario)) {
            $this->redirigirConError(
                'Ya tienes la verificación en dos pasos activa. Desactívala primero si quieres cambiar de teléfono.',
                '/docente/perfil'
            );
        }

        if (!Usuario::prepararTotp($this->idUsuarioActual(), Totp::generarSecreto())) {
            $this->redirigirConError('No se pudo generar el código.', '/docente/perfil');
        }

        $this->redirigirConMensaje(
            'Escanea el código QR con Google Authenticator y escribe el número de 6 dígitos que te muestre.',
            '/docente/perfil'
        );
    }

    /** Paso 2: confirma que el telefono quedo bien configurado */
    public function confirmarTotp(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/perfil');

        $id      = $this->idUsuarioActual();
        $secreto = Usuario::secretoTotp($id);

        if ($secreto === null) {
            $this->redirigirConError('Primero genera el código QR.', '/docente/perfil');
        }

        if (!Totp::verificar($secreto, $_POST['codigo'] ?? '')) {
            $this->redirigirConError(
                'Ese código no coincide. Escribe el que se ve AHORA en la aplicación: cambia cada 30 segundos.',
                '/docente/perfil'
            );
        }

        if (!Usuario::activarTotp($id)) {
            $this->redirigirConError('No se pudo activar la verificación.', '/docente/perfil');
        }

        self::abrirSesion();
        $_SESSION['totp_activo'] = true;

        $this->redirigirConMensaje(
            'Verificación en dos pasos activada. A partir de ahora te pedirá el código al entrar.',
            '/docente/perfil'
        );
    }

    /**
     * Desactiva el doble factor. Se exige la contraseña: si alguien deja la
     * sesion abierta, no debe poder quitarle la proteccion a la cuenta.
     */
    public function desactivarTotp(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente/perfil');

        $usuario = Usuario::buscarPorCorreo($_SESSION['usuario_correo'] ?? '');

        if (!$usuario || !password_verify((string)($_POST['password'] ?? ''), $usuario['password'])) {
            $this->redirigirConError('La contraseña no es correcta.', '/docente/perfil');
        }

        Usuario::desactivarTotp((int)$usuario['id']);

        self::abrirSesion();
        $_SESSION['totp_activo'] = false;

        $this->redirigirConMensaje(
            'Verificación en dos pasos desactivada. Vuelve a activarla en cuanto puedas.',
            '/docente/perfil'
        );
    }

    // ==================================================================
    // API en vivo del panel
    // ==================================================================
    public function apiEnVivo(): void
    {
        $this->iniciarSesion();

        if (empty($_SESSION['usuario_id'])) {
            $this->json(['success' => false, 'error' => 'No autorizado'], 401);
        }

        $sesion = Sesion::abiertaDelDocente($this->idUsuarioActual());

        if (!$sesion) {
            $this->json(['success' => true, 'activa' => false, 'asistencias' => []]);
        }

        $lista = array_map(static function (array $a): array {
            return [
                'id'       => (int)$a['id'],
                'codigo'   => $a['codigo'],
                'cedula'   => $a['cedula'] ?? null,
                'nombre'   => trim($a['nombre'] . ' ' . $a['apellido']),
                'semestre' => $a['semestre'],
                'entrada'  => date('H:i:s', strtotime($a['hora_entrada'])),
                'salida'   => $a['hora_salida'] ? date('H:i:s', strtotime($a['hora_salida'])) : null,
                'estado'   => $a['estado'],
                'origen'   => $a['origen'],
                'motivo'   => $a['motivo'],
                'pendiente'=> (($a['aprobacion'] ?? 'aprobada') === 'pendiente'),
            ];
        }, Asistencia::listarPorSesion((int)$sesion['id']));

        $this->json([
            'success'          => true,
            'activa'           => true,
            'resumen'          => Asistencia::resumenSesion((int)$sesion['id']),
            'segundos_entrada' => Sesion::segundosRestantes($sesion['entrada_expira']),
            'segundos_salida'  => Sesion::segundosRestantes($sesion['salida_expira'] ?? null),
            'asistencias'      => $lista
        ]);
    }

    // ==================================================================
    // Utilidades internas
    // ==================================================================

    /** Devuelve la clase abierta del docente o corta la peticion con un aviso */
    private function sesionAbiertaPropia(): array
    {
        $sesion = Sesion::abiertaDelDocente($this->idUsuarioActual());

        if (!$sesion) {
            $this->redirigirConError('No tienes ninguna clase abierta en este momento.', '/docente');
        }

        return $sesion;
    }

    // ==================================================================
    // JUSTIFICANTES
    //
    // El respaldo de una falta o de una salida: el certificado medico, el
    // permiso de coordinacion. Se guarda junto a la clase y al alumno, de modo
    // que al revisar el reporte se sepa cuales de esas faltas estaban
    // respaldadas y cuales no.
    // ==================================================================

    /**
     * Registra la justificacion de un alumno en una clase del docente.
     *
     * Sirve para los dos casos: el que falto (no hay fila de asistencia) y el
     * que se retiro antes. Por eso se enlaza a la clase y al alumno, y no al
     * registro de asistencia.
     */
    public function justificar(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $sesionId      = filter_var($_POST['sesion_id'] ?? null, FILTER_VALIDATE_INT);
        $estudianteId  = filter_var($_POST['estudiante_id'] ?? null, FILTER_VALIDATE_INT);
        $tipo          = trim($_POST['tipo'] ?? '');
        $detalle       = $this->limpiarTexto($_POST['detalle'] ?? '');

        // La clase tiene que ser de este docente: si no, cualquiera con la
        // sesion abierta podria justificar faltas en las clases de otro
        $sesion = $sesionId ? Sesion::buscarPorId($sesionId) : null;

        if (!$sesion || (int)$sesion['docente_id'] !== $this->idUsuarioActual()) {
            $this->redirigirConError('Esa clase no existe o no es tuya.', '/docente');
        }

        if (!$estudianteId || !Estudiante::buscarPorId($estudianteId)) {
            $this->redirigirConError('Ese estudiante ya no existe.', '/docente');
        }

        if (!Justificacion::esTipoValido($tipo)) {
            $this->redirigirConError('Selecciona el tipo de justificación.', '/docente');
        }

        if (mb_strlen($detalle) > 300) {
            $this->redirigirConError('La descripción no puede superar los 300 caracteres.', '/docente');
        }

        // El archivo es opcional: hay permisos que se dan de palabra y el
        // docente puede querer dejar constancia igual
        $archivo = null;
        if (isset($_FILES['justificante']) && ($_FILES['justificante']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $archivo = $_FILES['justificante'];
        }

        $resultado = Justificacion::guardar(
            $sesionId, $estudianteId, $this->idUsuarioActual(), $tipo, $detalle, $archivo
        );

        if ($resultado !== 'ok') {
            $this->redirigirConError(Justificacion::mensajeError($resultado), '/docente');
        }

        $estudiante = Estudiante::buscarPorId($estudianteId);
        $nombre     = trim(($estudiante['nombre'] ?? '') . ' ' . ($estudiante['apellido'] ?? ''));

        $this->redirigirConMensaje(
            "Falta de {$nombre} justificada como " . Justificacion::etiquetaTipo($tipo)
            . ($archivo !== null ? ' con respaldo adjunto.' : '. Puedes adjuntar el respaldo más tarde.'),
            '/docente'
        );
    }

    /** Retira una justificacion registrada por error */
    public function quitarJustificacion(): void
    {
        $this->verificarDocente();
        $this->verificarCsrf('/docente');

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || !Justificacion::eliminar($id, $this->idUsuarioActual())) {
            $this->redirigirConError('Esa justificación no existe o no es de tus clases.', '/docente');
        }

        $this->redirigirConMensaje('Justificación retirada.', '/docente');
    }

    /**
     * Entrega el archivo de un justificante.
     *
     * El archivo vive FUERA de la carpeta publica, asi que esta es la unica
     * via para verlo, y aqui se comprueba antes quien lo pide. Un certificado
     * medico es un dato de salud: si el archivo estuviera bajo public/,
     * cualquiera que acertara la direccion podria abrirlo sin haber iniciado
     * sesion siquiera.
     */
    public function verJustificante(): void
    {
        $this->verificarDocente();

        $id            = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $justificacion = $id ? Justificacion::buscarPorId($id) : null;

        // Lo ve el docente de esa clase; el administrador ve cualquiera,
        // porque es quien atiende los reclamos de secretaria.
        $esDuenio = $justificacion
                 && (int)$justificacion['sesion_docente_id'] === $this->idUsuarioActual();

        if (!$justificacion || (!$esDuenio && !self::esAdmin())) {
            $this->redirigirConError('Ese justificante no existe o no es de tus clases.', '/docente');
        }

        $ruta = Justificacion::rutaArchivo($justificacion['archivo']);

        if ($ruta === null) {
            $this->redirigirConError('Esa justificación no tiene ningún archivo adjunto.', '/docente');
        }

        $this->limpiarBufferSalida();

        header('Content-Type: ' . ($justificacion['archivo_tipo'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($ruta));
        // 'inline' para que el PDF o la foto se abran en el navegador; el
        // nombre entre comillas conserva el que puso el docente al subirlo
        header('Content-Disposition: inline; filename="'
             . str_replace('"', '', $justificacion['archivo_nombre'] ?: 'justificante') . '"');
        // Nada de cache: es un documento personal y no debe quedarse guardado
        header('Cache-Control: private, no-store');
        // El navegador no debe adivinar el tipo: un archivo mal declarado no
        // puede terminar interpretandose como HTML con guiones dentro
        header('X-Content-Type-Options: nosniff');

        readfile($ruta);
        exit;
    }

    /** Vacia lo que hubiera pendiente de enviar antes de escribir un archivo */
    private function limpiarBufferSalida(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /** Valida que la asistencia indicada pertenezca a una clase del docente */
    private function asistenciaPropia(): int
    {
        $id = filter_var($_POST['asistencia_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || !Asistencia::perteneceADocente($id, $this->idUsuarioActual())) {
            $this->redirigirConError('Ese registro no existe o no pertenece a tus clases.', '/docente');
        }

        return $id;
    }

    private function limpiarTexto(string $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', $valor));
    }

    private function validarNombre(string $valor, string $campo, string $ruta): void
    {
        if (!preg_match(self::PATRON_NOMBRE, $valor)) {
            $this->redirigirConError(
                "El {$campo} debe tener entre 2 y 50 letras, sin números ni símbolos.",
                $ruta
            );
        }
    }

    /**
     * Arma la URL absoluta que va dentro del QR (la abre el celular del alumno).
     *
     * La logica vive en App porque hay un detalle que se pasaba por alto: si el
     * docente entra por "localhost", el QR llevaria localhost dentro y para el
     * celular del alumno eso apunta a su propio telefono, no al servidor. App
     * sustituye localhost por la IP del servidor en la red del aula.
     */
    private function urlPublica(string $ruta): string
    {
        return App::urlPublica($ruta);
    }

    /**
     * Aviso para el docente cuando los celulares no van a poder abrir el QR.
     * Devuelve null si todo esta en orden.
     */
    private function avisoDeRed(): ?array
    {
        // En un servidor publico estos avisos no aplican: el QR lleva el
        // dominio real y cualquier celular con internet lo abre. Recomendar
        // "entra por localhost" alli no significa nada y solo estorba.
        if (!App::esEntornoLocal()) {
            return null;
        }

        if (!App::esHostLocal()) {
            // Entro por una IP. Funciona, pero esa direccion CAMBIA cada vez
            // que el equipo se conecta a otra red wifi, y entonces el enlace
            // guardado deja de responder ("no se puede acceder a este sitio").
            // Entrando por localhost eso no pasa nunca, y el QR sigue llevando
            // la IP correcta para los celulares.
            return [
                'tipo'  => 'info',
                'texto' => 'Estás entrando por una dirección IP. Funciona, pero esa dirección '
                         . 'cambia cada vez que el equipo se conecta a otra red wifi y el enlace '
                         . 'deja de abrir. Entra siempre por localhost: el código QR seguirá '
                         . 'llevando la IP correcta para los celulares.',
                'ips'   => [],
                'sugerencia' => 'http://localhost' . self::obtenerRutaBase() . '/docente'
            ];
        }

        $ips = App::ipsDeRed();

        if (empty($ips)) {
            return [
                'tipo'  => 'error',
                'texto' => 'Estás entrando por "localhost" y no se pudo detectar la IP de red de este '
                         . 'equipo. Los celulares NO podrán abrir el código QR. Conecta el servidor a la '
                         . 'red del aula, o dicta el código de 8 caracteres para que lo escriban a mano.',
                'ips'   => []
            ];
        }

        return [
            'tipo'  => 'info',
            'texto' => 'Entraste por "localhost", así que el QR se generó apuntando a '
                     . $ips[0] . '. Si algún celular no logra abrirlo, entra tú desde esa misma '
                     . 'dirección o dicta el código de 8 caracteres.',
            'ips'   => $ips
        ];
    }

    /**
     * Comprobacion de que los celulares pueden llegar al servidor.
     *
     * El sintoma tipico es que el alumno escanea el QR y el navegador queda
     * cargando hasta dar "ERR_CONNECTION_TIMED_OUT". Casi siempre no es el QR
     * ni Apache: es el Firewall de Windows, que descarta las conexiones
     * entrantes al puerto 80 sin avisar. Desde el propio equipo funciona
     * (la peticion no pasa por el firewall) y por eso desorienta tanto.
     *
     * Aqui se comprueba de verdad: se abre un socket contra la IP de red del
     * propio servidor. Si responde, un celular tambien va a poder.
     */
    private function comprobarAlcance(): array
    {
        // En internet no hay nada que comprobar: el dominio es publico y lo
        // alcanza cualquier telefono con datos, sin depender del wifi del aula.
        if (!App::esEntornoLocal()) {
            return ['ok' => true, 'texto' => ''];
        }

        $ip = App::ipDeRed();

        if ($ip === null) {
            return [
                'ok'    => false,
                'texto' => 'Este equipo no está conectado a ninguna red local. Conéctalo al '
                         . 'wifi del aula para que los celulares puedan abrir el QR.'
            ];
        }

        $puerto = (int)($_SERVER['SERVER_PORT'] ?? 80);
        $error  = 0;
        $motivo = '';

        // Un segundo basta: es la propia maquina, en la misma red
        $conexion = @fsockopen($ip, $puerto, $error, $motivo, 1.0);

        if ($conexion === false) {
            return [
                'ok'    => false,
                'ip'    => $ip,
                'texto' => "El servidor no acepta conexiones en {$ip}:{$puerto}. Los celulares "
                         . 'verán "no se puede acceder a este sitio" al escanear el QR. '
                         . 'Casi siempre es el Firewall de Windows bloqueando el puerto: '
                         . 'hay que crear una regla de entrada que lo permita para la red local.'
            ];
        }

        fclose($conexion);

        return ['ok' => true, 'ip' => $ip];
    }
}
