<?php

// Punto de Entrada Principal (Front Controller)
// Recibe todas las peticiones y llama al controlador que corresponde.

// Soporte para el servidor integrado de PHP (php -S): los archivos estaticos
// se sirven directos, sin pasar por el enrutador
if (php_sapi_name() === 'cli-server') {
    $archivo = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($archivo !== '/' && is_file(__DIR__ . $archivo)) {
        return false;
    }
}

$raiz = dirname(__DIR__);


// Arranque: manejo de errores (nada de avisos de PHP en pantalla), cabeceras
// de seguridad y zona horaria. Va antes que cualquier otro require.
require_once $raiz . '/config/app.php';
App::iniciar();

require_once $raiz . '/controllers/HomeController.php';
require_once $raiz . '/controllers/AuthController.php';
require_once $raiz . '/controllers/DocenteController.php';
require_once $raiz . '/controllers/AsistenciaController.php';
require_once $raiz . '/controllers/ReporteController.php';
require_once $raiz . '/controllers/AdminController.php';
require_once $raiz . '/controllers/LegalController.php';

// Ruta y metodo solicitados
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'HEAD') {
    $metodo = 'GET';
}

// Si el proyecto vive en una subcarpeta de XAMPP, se descuenta del inicio
$directorio = str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($directorio !== '/' && $directorio !== '' && str_starts_with($uri, $directorio)) {
    $uri = substr($uri, strlen($directorio));
}
$ruta = '/' . trim($uri, '/');

// ---------------------------------------------------------------------
// Tabla de rutas: ruta => [metodo HTTP => [Controlador, accion]]
// ---------------------------------------------------------------------
$rutas = [
    // --- Publico ---
    '/'                    => ['GET'  => [HomeController::class, 'index']],
    // Acceso del personal: una puerta por rol. Cada una solo deja pasar al
    // suyo, y la portada es la que reparte a quien va a cada sitio.
    '/acceso/docente'      => ['GET'  => [AuthController::class, 'mostrarLoginDocente'],
                               'POST' => [AuthController::class, 'procesarLoginDocente']],
    '/acceso/admin'        => ['GET'  => [AuthController::class, 'mostrarLoginAdmin'],
                               'POST' => [AuthController::class, 'procesarLoginAdmin']],
    // Segundo paso: codigo de la aplicacion autenticadora
    '/acceso/verificar'    => ['GET'  => [AuthController::class, 'mostrarVerificacion'],
                               'POST' => [AuthController::class, 'procesarVerificacion']],
    // Direcciones antiguas: se conservan redirigiendo a la portada, para no
    // romper los enlaces que la gente ya tenga guardados
    '/acceso'              => ['GET'  => [AuthController::class, 'redirigirAcceso']],
    '/login'               => ['GET'  => [AuthController::class, 'redirigirAcceso']],
    '/logout'              => ['GET'  => [AuthController::class, 'logout']],
    '/solicitar-clave'     => ['POST' => [AuthController::class, 'solicitarClave']],
    '/clave-nueva'         => ['GET'  => [AuthController::class, 'mostrarNuevaClave'],
                               'POST' => [AuthController::class, 'guardarNuevaClave']],

    // --- Estudiante: sin cuenta, solo escanea el QR y llena el formulario ---
    '/asistencia'          => ['GET'  => [AsistenciaController::class, 'mostrar'],
                               'POST' => [AsistenciaController::class, 'registrar']],

    // --- Terminos de uso y consentimiento de datos ---
    '/terminos'            => ['GET'  => [LegalController::class, 'terminos']],
    '/terminos/aceptar'    => ['POST' => [LegalController::class, 'aceptar']],
    '/terminos/retirar'    => ['POST' => [LegalController::class, 'retirar']],

    // --- Panel del Docente ---
    '/docente'                     => ['GET'  => [DocenteController::class, 'index']],
    '/docente/curso/crear'         => ['POST' => [DocenteController::class, 'crearCurso']],
    '/docente/curso/eliminar'      => ['POST' => [DocenteController::class, 'eliminarCurso']],
    '/docente/curso/restaurar'     => ['POST' => [DocenteController::class, 'restaurarCurso']],
    '/docente/clase/abrir'         => ['POST' => [DocenteController::class, 'abrirClase']],
    '/docente/clase/renovar'       => ['POST' => [DocenteController::class, 'renovarEntrada']],
    '/docente/clase/cerrar-entrada'=> ['POST' => [DocenteController::class, 'cerrarEntrada']],
    '/docente/clase/qr-salida'     => ['POST' => [DocenteController::class, 'generarSalida']],
    '/docente/clase/cerrar'        => ['POST' => [DocenteController::class, 'cerrarClase']],
    '/docente/asistencia/manual'   => ['POST' => [DocenteController::class, 'registrarManual']],
    '/docente/asistencia/salida'   => ['POST' => [DocenteController::class, 'marcarSalida']],
    '/docente/asistencia/devolver' => ['POST' => [DocenteController::class, 'devolverAClase']],
    '/docente/asistencia/aprobar'  => ['POST' => [DocenteController::class, 'aprobarAsistencia']],
    '/docente/asistencia/desbloquear' => ['POST' => [DocenteController::class, 'levantarBloqueo']],
    // Justificantes: el respaldo de una falta o de una salida
    '/docente/justificar'          => ['POST' => [DocenteController::class, 'justificar']],
    '/docente/justificar/quitar'   => ['POST' => [DocenteController::class, 'quitarJustificacion']],
    '/docente/justificante'        => ['GET'  => [DocenteController::class, 'verJustificante']],
    '/docente/perfil'              => ['GET'  => [DocenteController::class, 'perfil']],
    '/docente/perfil/2fa/preparar' => ['POST' => [DocenteController::class, 'prepararTotp']],
    '/docente/perfil/2fa/activar'  => ['POST' => [DocenteController::class, 'confirmarTotp']],
    '/docente/perfil/2fa/quitar'   => ['POST' => [DocenteController::class, 'desactivarTotp']],
    '/docente/asistencia/eliminar' => ['POST' => [DocenteController::class, 'eliminarAsistencia']],
    '/docente/matriculas'          => ['GET'  => [DocenteController::class, 'matriculas']],
    '/docente/matriculas/agregar'  => ['POST' => [DocenteController::class, 'matricular']],
    '/docente/matriculas/inscribir'=> ['POST' => [DocenteController::class, 'inscribirEstudiante']],
    '/docente/matriculas/quitar'   => ['POST' => [DocenteController::class, 'quitarMatricula']],
    '/docente/matriculas/editar'   => ['POST' => [DocenteController::class, 'actualizarEstudiante']],
    '/docente/matriculas/importar' => ['POST' => [DocenteController::class, 'importarEstudiantes']],
    '/docente/matriculas/plantilla'=> ['GET'  => [DocenteController::class, 'plantillaEstudiantes']],
    '/docente/envios'              => ['GET'  => [DocenteController::class, 'envios']],
    '/docente/carnets'             => ['GET'  => [DocenteController::class, 'carnets']],
    '/docente/carnets/cedula'      => ['POST' => [DocenteController::class, 'asignarCedula']],

    // Token CSRF fresco para la capa AJAX
    '/api/csrf'            => ['GET'  => [HomeController::class, 'csrf']],

    // API JSON que refresca el panel en vivo del docente
    '/api/clase/en-vivo'   => ['GET'  => [DocenteController::class, 'apiEnVivo']],

    // --- Reportes (docente ve los suyos, admin ve todos) ---
    '/reportes'            => ['GET'  => [ReporteController::class, 'index']],
    '/reportes/clase'      => ['GET'  => [ReporteController::class, 'detalleClase']],
    '/reportes/excel'      => ['GET'  => [ReporteController::class, 'exportarExcel']],
    '/reportes/pdf'        => ['GET'  => [ReporteController::class, 'exportarPdf']],

    // --- Panel del Administrador (solo control y cuentas) ---
    '/admin'                       => ['GET'  => [AdminController::class, 'index']],
    '/admin/clase/cerrar'          => ['POST' => [AdminController::class, 'cerrarClase']],
    '/admin/docentes'              => ['GET'  => [AdminController::class, 'docentes']],
    '/admin/docentes/crear'        => ['POST' => [AdminController::class, 'crearDocente']],
    '/admin/docentes/actualizar'   => ['POST' => [AdminController::class, 'actualizarDocente']],
    '/admin/docentes/password'     => ['POST' => [AdminController::class, 'resetearPassword']],
    '/admin/docentes/estado'       => ['POST' => [AdminController::class, 'cambiarEstado']],
    '/admin/docentes/2fa/preparar' => ['POST' => [AdminController::class, 'prepararTotpDocente']],
    '/admin/docentes/2fa/activar'  => ['POST' => [AdminController::class, 'activarTotpDocente']],
    '/admin/docentes/2fa/quitar'   => ['POST' => [AdminController::class, 'quitarTotpDocente']],
    '/admin/envios'                => ['GET'  => [AdminController::class, 'enviosDocentes']],
    '/admin/envios/generar'        => ['POST' => [AdminController::class, 'generarEnlaces']],
    '/admin/solicitudes'           => ['GET'  => [AdminController::class, 'solicitudes']],
    '/admin/solicitudes/atender'   => ['POST' => [AdminController::class, 'atenderSolicitud']],
    '/admin/solicitudes/rechazar'  => ['POST' => [AdminController::class, 'rechazarSolicitud']],
    '/api/solicitudes/pendientes'  => ['GET'  => [AdminController::class, 'apiPendientes']],

    // --- Periodo academico: lo primero que elige el administrador ---
    '/admin/periodo'                  => ['GET'  => [AdminController::class, 'periodo']],
    '/admin/periodo/elegir'           => ['POST' => [AdminController::class, 'elegirPeriodo']],
    '/admin/periodo/crear'            => ['POST' => [AdminController::class, 'crearPeriodo']],
    '/admin/periodo/actualizar'       => ['POST' => [AdminController::class, 'actualizarPeriodo']],
    '/admin/periodo/copiar-materias'  => ['POST' => [AdminController::class, 'copiarMaterias']],
    '/admin/carreras/crear'           => ['POST' => [AdminController::class, 'crearCarrera']],
    '/admin/carreras/actualizar'      => ['POST' => [AdminController::class, 'actualizarCarrera']],

    // --- Gestion academica: solo el administrador ---
    '/admin/materias'                 => ['GET'  => [AdminController::class, 'materias']],
    '/admin/materias/crear'           => ['POST' => [AdminController::class, 'crearMateria']],
    '/admin/materias/actualizar'      => ['POST' => [AdminController::class, 'actualizarMateria']],
    '/admin/materias/estado'          => ['POST' => [AdminController::class, 'estadoMateria']],
    '/admin/materias/asignar'         => ['POST' => [AdminController::class, 'asignarDocente']],
    '/admin/materias/quitar-docente'  => ['POST' => [AdminController::class, 'quitarAsignacion']],
    '/admin/materias/restaurar-docente' => ['POST' => [AdminController::class, 'restaurarAsignacion']],
    '/admin/semestres/crear'          => ['POST' => [AdminController::class, 'crearSemestre']],
    '/admin/semestres/actualizar'     => ['POST' => [AdminController::class, 'actualizarSemestre']],
    '/admin/semestres/eliminar'       => ['POST' => [AdminController::class, 'eliminarSemestre']],
];

// ---------------------------------------------------------------------
// Despacho
// ---------------------------------------------------------------------
if (!isset($rutas[$ruta])) {
    (new HomeController())->noEncontrado(404);
    exit;
}

if (!isset($rutas[$ruta][$metodo])) {
    // La direccion existe pero no admite este verbo HTTP
    header('Allow: ' . implode(', ', array_keys($rutas[$ruta])));
    (new HomeController())->noEncontrado(405);
    exit;
}

[$clase, $accion] = $rutas[$ruta][$metodo];
(new $clase())->$accion();
