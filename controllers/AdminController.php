<?php

require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Materia.php';
require_once dirname(__DIR__) . '/models/Curso.php';
require_once dirname(__DIR__) . '/models/Sesion.php';
require_once dirname(__DIR__) . '/models/Estudiante.php';
require_once dirname(__DIR__) . '/models/Asistencia.php';
require_once dirname(__DIR__) . '/models/Catalogo.php';
require_once dirname(__DIR__) . '/models/Semestre.php';
require_once dirname(__DIR__) . '/models/Matricula.php';
require_once dirname(__DIR__) . '/models/SolicitudClave.php';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/libs/Whatsapp.php';
require_once dirname(__DIR__) . '/libs/Totp.php';
require_once dirname(__DIR__) . '/libs/QrCodigo.php';

/**
 * Panel del ADMINISTRADOR.
 *
 * Su funcion es de CONTROL, no de gestion operativa: supervisa como funciona
 * todo el sistema (clases activas, estadisticas, historial) y administra las
 * cuentas de los docentes. No abre clases ni pasa lista: eso es del docente.
 */
class AdminController extends BaseController
{
    public const MIN_PASSWORD = 8;

    private const PATRON_NOMBRE = "/^[\p{L}][\p{L}\s'.-]{1,49}$/u";

    // ==================================================================
    // Supervision general
    // ==================================================================
    public function index(): void
    {
        $this->verificarAdmin();

        [$mensaje, $error] = $this->obtenerFlash();

        $this->vista('admin.index', [
            'base'            => self::obtenerRutaBase(),
            'adminNombre'     => $_SESSION['usuario_nombre'] ?? 'Administrador',
            'totalDocentes'   => Usuario::contarPorRol('docente'),
            'totalEstudiantes'=> Estudiante::contar(),
            'totalMaterias'   => Materia::contar(),
            'totalCursos'     => Curso::contarTotal(),
            'totalClases'     => Sesion::contarTotal(),
            'clasesHoy'       => Sesion::contarHoy(),
            'asistenciasHoy'  => Asistencia::contarHoy(),
            'asistenciasTotal'=> Asistencia::contarTotal(),
            'clasesAbiertas'  => Sesion::listarAbiertas(),
            'solicitudes'     => SolicitudClave::contarPendientes(),
            'historial'       => Sesion::historialGlobal(10),
            // Estadisticas en grafico de pastel, separadas por criterio
            // Mapa nombre -> id, para que cada porcion del grafico enlace al
            // reporte filtrado por esa materia
            'materiasPorNombre' => array_column(Materia::listar(false), 'id', 'nombre'),
            'graficoMateria'  => Asistencia::porMateria(),
            'graficoEstado'   => Asistencia::porEstado(),
            'graficoAmbiente' => Asistencia::porAmbiente(),
            'graficoMotivo'   => Asistencia::porMotivo(),
            'mensaje'         => $mensaje,
            'error'           => $error,
            'csrf'            => self::tokenCsrf()
        ]);
    }

    // ==================================================================
    // Gestion de cuentas de docentes
    // ==================================================================
    public function docentes(): void
    {
        $this->verificarAdmin();

        $rol = trim($_GET['rol'] ?? '');
        if (!Catalogo::esRolValido($rol)) {
            $rol = '';
        }

        [$mensaje, $error] = $this->obtenerFlash();

        self::abrirSesion();
        $ultimo = $_SESSION['ultimo_enlace'] ?? null;
        $qrTotp = $_SESSION['totp_qr'] ?? null;
        unset($_SESSION['ultimo_enlace'], $_SESSION['totp_qr']);

        $this->vista('admin.docentes', [
            'base'            => self::obtenerRutaBase(),
            'ultimoEnlace'    => $ultimo,
            'qrTotp'          => $qrTotp,
            'sinTotp'         => Usuario::contarSinTotp(),
            'usuarios'        => Usuario::listar($rol),
            'rolFiltro'       => $rol,
            'roles'           => Catalogo::ROLES,
            'dominio'         => Catalogo::DOMINIO,
            'minPassword'     => self::MIN_PASSWORD,
            'usuarioActualId' => $this->idUsuarioActual(),
            'mensaje'         => $mensaje,
            'error'           => $error,
            'csrf'            => self::tokenCsrf()
        ]);
    }

    public function crearDocente(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/docentes');

        $nombre   = $this->limpiar($_POST['nombre'] ?? '');
        $apellido = $this->limpiar($_POST['apellido'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $rol      = trim($_POST['rol'] ?? 'docente');

        if (!Catalogo::esRolValido($rol)) {
            $rol = 'docente';
        }

        $this->validarNombre($nombre, 'nombre');
        $this->validarNombre($apellido, 'apellido');
        $this->validarPassword($password);

        // El correo institucional se arma solo: nombre.apellido@istpet.edu.ec
        $correo = Catalogo::generarCorreo($nombre, $apellido);

        if (!Catalogo::esCorreoInstitucional($correo)) {
            $this->redirigirConError(
                'Con ese nombre y apellido no se puede generar un correo institucional válido '
                . 'del dominio @' . Catalogo::DOMINIO . '.',
                '/admin/docentes'
            );
        }

        if (Usuario::buscarPorCorreo($correo)) {
            $this->redirigirConError(
                "Ya existe una cuenta con el correo {$correo}. Usa un segundo nombre o apellido para diferenciarla.",
                '/admin/docentes'
            );
        }

        if (Usuario::crear($nombre, $apellido, $correo, $password, $rol, $telefono)) {
            $this->redirigirConMensaje(
                "Cuenta creada. El acceso es {$correo} con la contraseña que asignaste.",
                '/admin/docentes'
            );
        }

        $this->redirigirConError('No se pudo crear la cuenta.', '/admin/docentes');
    }

    public function actualizarDocente(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/docentes');

        $id       = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $nombre   = $this->limpiar($_POST['nombre'] ?? '');
        $apellido = $this->limpiar($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $rol      = trim($_POST['rol'] ?? 'docente');
        $activo   = ((string)($_POST['activo'] ?? '1') === '0') ? 0 : 1;

        if (!$id || !Usuario::buscarPorId($id)) {
            $this->redirigirConError('La cuenta indicada ya no existe.', '/admin/docentes');
        }

        if (!Catalogo::esRolValido($rol)) {
            $rol = 'docente';
        }

        $this->validarNombre($nombre, 'nombre');
        $this->validarNombre($apellido, 'apellido');

        // Un administrador no puede quitarse a si mismo el rol ni desactivarse:
        // asi siempre queda al menos una cuenta capaz de administrar el sistema
        if ($id === $this->idUsuarioActual() && ($rol !== 'admin' || $activo === 0)) {
            $this->redirigirConError(
                'No puedes quitarte tus propios privilegios ni desactivar tu cuenta.',
                '/admin/docentes'
            );
        }

        $correo    = Catalogo::generarCorreo($nombre, $apellido);
        $existente = Usuario::buscarPorCorreo($correo);

        if ($existente && (int)$existente['id'] !== $id) {
            $this->redirigirConError("El correo {$correo} ya pertenece a otra cuenta.", '/admin/docentes');
        }

        if (Usuario::actualizar($id, $nombre, $apellido, $correo, $rol, $activo, $telefono)) {
            $this->redirigirConMensaje("Cuenta actualizada. Su acceso ahora es {$correo}.", '/admin/docentes');
        }

        $this->redirigirConError('No se pudo actualizar la cuenta.', '/admin/docentes');
    }

    public function resetearPassword(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/docentes');

        $id       = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $password = (string)($_POST['password'] ?? '');

        if (!$id || !Usuario::buscarPorId($id)) {
            $this->redirigirConError('La cuenta indicada ya no existe.', '/admin/docentes');
        }

        $this->validarPassword($password);

        $usuario = Usuario::buscarPorId($id);

        if (!Usuario::cambiarPassword($id, $password)) {
            $this->redirigirConError('No se pudo restablecer la contraseña.', '/admin/docentes');
        }

        // Se prepara el aviso por WhatsApp. Aqui SI viaja la contraseña, porque
        // la escribio el administrador y es la que el docente tiene que usar;
        // por eso el mensaje le pide cambiarla al entrar.
        $nombre  = Usuario::nombreCompleto($usuario);
        $entrada = App::urlPublica('/acceso');

        $texto = "Hola {$nombre}, soy del " . App::SIGLA . ".\n\n"
               . "Se restableció tu contraseña del sistema de asistencia.\n\n"
               . "Usuario: {$usuario['correo']}\n"
               . "Contraseña: {$password}\n\n"
               . "Entra en: {$entrada}\n"
               . "Cámbiala apenas ingreses.";

        self::abrirSesion();
        $_SESSION['ultimo_enlace'] = [
            'nombre'   => $nombre,
            'correo'   => $usuario['correo'],
            'telefono' => $usuario['telefono'],
            'enlace'   => $entrada,
            'mensaje'  => $texto,
            'whatsapp' => Whatsapp::enlace($usuario['telefono'], $texto)
        ];

        $this->redirigirConMensaje(
            empty($usuario['telefono'])
                ? "Contraseña restablecida. {$nombre} no tiene teléfono cargado: pásasela tú o agrégale el número."
                : "Contraseña restablecida. Envíasela a {$nombre} por WhatsApp con el botón de abajo.",
            '/admin/docentes'
        );
    }

    public function cambiarEstado(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/docentes');

        $id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $activo = ((string)($_POST['activo'] ?? '1') === '0') ? 0 : 1;

        if (!$id || !Usuario::buscarPorId($id)) {
            $this->redirigirConError('La cuenta indicada ya no existe.', '/admin/docentes');
        }

        if ($id === $this->idUsuarioActual()) {
            $this->redirigirConError('No puedes desactivar tu propia cuenta.', '/admin/docentes');
        }

        if (Usuario::cambiarEstado($id, $activo)) {
            $texto = ($activo === 1) ? 'activada' : 'desactivada';
            $this->redirigirConMensaje("Cuenta {$texto} correctamente.", '/admin/docentes');
        }

        $this->redirigirConError('No se pudo cambiar el estado de la cuenta.', '/admin/docentes');
    }

    // ------------------ Verificacion en dos pasos ------------------

    /**
     * Prepara el doble factor de una cuenta y muestra su codigo QR.
     *
     * El administrador puede hacerlo por el docente cuando lo tiene delante:
     * genera el QR aqui, el docente lo escanea con su telefono y confirma con
     * un codigo. Si el docente prefiere hacerlo solo, tiene la misma opcion
     * en su propio perfil.
     */
    public function prepararTotpDocente(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/docentes');

        $id      = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $usuario = $id ? Usuario::buscarPorId($id) : null;

        if (!$usuario) {
            $this->redirigirConError('Esa cuenta ya no existe.', '/admin/docentes');
        }

        if (Usuario::tieneTotpActivo($usuario)) {
            $this->redirigirConError(
                Usuario::nombreCompleto($usuario) . ' ya tiene la verificación activa. '
                . 'Quítala primero si necesita configurar otro teléfono.',
                '/admin/docentes'
            );
        }

        $secreto = Totp::generarSecreto();

        if (!Usuario::prepararTotp($id, $secreto)) {
            $this->redirigirConError('No se pudo generar el código.', '/admin/docentes');
        }

        $uri = Totp::uri($secreto, $usuario['correo'], App::SIGLA);

        self::abrirSesion();
        $_SESSION['totp_qr'] = [
            'usuario_id' => $id,
            'nombre'     => Usuario::nombreCompleto($usuario),
            'correo'     => $usuario['correo'],
            'svg'        => QrCodigo::svg($uri, 220, 'Codigo QR para Google Authenticator'),
            'secreto'    => Totp::secretoLegible($secreto)
        ];

        $this->redirigirConMensaje(
            'Código QR generado. Que ' . Usuario::nombreCompleto($usuario)
            . ' lo escanee con Google Authenticator y escriba el número de 6 dígitos.',
            '/admin/docentes'
        );
    }

    /** Confirma el doble factor de una cuenta con el codigo del telefono */
    public function activarTotpDocente(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/docentes');

        $id      = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $secreto = $id ? Usuario::secretoTotp($id) : null;

        if ($secreto === null) {
            $this->redirigirConError('Esa cuenta no tiene un código pendiente de confirmar.', '/admin/docentes');
        }

        if (!Totp::verificar($secreto, $_POST['codigo'] ?? '')) {
            $this->redirigirConError(
                'Ese código no coincide. Debe escribirse el que se ve AHORA en la app: cambia cada 30 segundos.',
                '/admin/docentes'
            );
        }

        Usuario::activarTotp($id);
        $usuario = Usuario::buscarPorId($id);

        $this->redirigirConMensaje(
            'Verificación en dos pasos activada para ' . Usuario::nombreCompleto($usuario) . '.',
            '/admin/docentes'
        );
    }

    /**
     * Quita el doble factor de una cuenta.
     * Es la salida cuando un docente pierde el telefono y no puede entrar.
     */
    public function quitarTotpDocente(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/docentes');

        $id      = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $usuario = $id ? Usuario::buscarPorId($id) : null;

        if (!$usuario) {
            $this->redirigirConError('Esa cuenta ya no existe.', '/admin/docentes');
        }

        Usuario::desactivarTotp($id);

        $this->redirigirConMensaje(
            'Verificación retirada de ' . Usuario::nombreCompleto($usuario)
            . '. Vuelve a configurarla en cuanto tenga su teléfono.',
            '/admin/docentes'
        );
    }

    /** Cierre forzoso de una clase que quedo abierta por olvido */
    public function cerrarClase(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin');

        $sesionId = filter_var($_POST['sesion_id'] ?? null, FILTER_VALIDATE_INT);

        if ($sesionId && Sesion::cerrarPorAdmin($sesionId)) {
            $this->redirigirConMensaje('La clase fue finalizada por el Administrador.', '/admin');
        }

        $this->redirigirConError('No se pudo finalizar la clase seleccionada.', '/admin');
    }

    // ==================================================================
    // SOLICITUDES DE CONTRASEÑA
    //
    // El docente que olvido su clave la pide desde el login y aqui le llega
    // al administrador. Al atenderla se genera un enlace de un solo uso que
    // caduca a la media hora, y se le envia al docente por WhatsApp.
    //
    // Se manda un enlace y no la contraseña porque una clave escrita en un
    // chat queda ahi para siempre; el enlace se quema al usarse.
    // ==================================================================

    public function solicitudes(): void
    {
        $this->verificarAdmin();

        [$mensaje, $error] = $this->obtenerFlash();

        self::abrirSesion();
        $ultimo = $_SESSION['ultimo_enlace'] ?? null;
        unset($_SESSION['ultimo_enlace']);

        $this->vista('admin.solicitudes', [
            'base'         => self::obtenerRutaBase(),
            'solicitudes'  => SolicitudClave::pendientes(),
            'ultimoEnlace' => $ultimo,
            'minutos'      => SolicitudClave::MINUTOS_VALIDEZ,
            'mensaje'      => $mensaje,
            'error'        => $error,
            'csrf'         => self::tokenCsrf()
        ]);
    }

    public function atenderSolicitud(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/solicitudes');

        $id        = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $solicitud = $id ? SolicitudClave::buscarPorId($id) : null;

        if (!$solicitud) {
            $this->redirigirConError('Esa solicitud ya no existe.', '/admin/solicitudes');
        }
        if ($solicitud['estado'] !== 'pendiente') {
            $this->redirigirConError('Esa solicitud ya fue atendida.', '/admin/solicitudes');
        }

        $token = SolicitudClave::atender($id, $this->idUsuarioActual());

        if ($token === null) {
            $this->redirigirConError('No se pudo generar el enlace.', '/admin/solicitudes');
        }

        $enlace  = App::urlPublica('/clave-nueva?t=' . $token);
        $nombre  = trim($solicitud['nombre'] . ' ' . $solicitud['apellido']);
        $minutos = SolicitudClave::MINUTOS_VALIDEZ;

        $texto = "Hola {$nombre}, soy del ISTPET.\n\n"
               . "Para crear tu nueva contraseña entra aquí:\n{$enlace}\n\n"
               . "El enlace sirve una sola vez y caduca en {$minutos} minutos.";

        // Se guarda para mostrarlo en pantalla: el envio no es automatico, el
        // administrador abre WhatsApp con el mensaje ya escrito y pulsa enviar
        self::abrirSesion();
        $_SESSION['ultimo_enlace'] = [
            'nombre'    => $nombre,
            'correo'    => $solicitud['correo'],
            'telefono'  => $solicitud['telefono'],
            'enlace'    => $enlace,
            'mensaje'   => $texto,
            'whatsapp'  => SolicitudClave::enlaceWhatsapp($solicitud['telefono'], $texto)
        ];

        $this->redirigirConMensaje(
            empty($solicitud['telefono'])
                ? "Enlace generado para {$nombre}. Ese docente no tiene teléfono cargado: cópiale el enlace a mano."
                : "Enlace generado para {$nombre}. Envíaselo por WhatsApp con el botón de abajo.",
            '/admin/solicitudes'
        );
    }

    public function rechazarSolicitud(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/solicitudes');

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || !SolicitudClave::buscarPorId($id)) {
            $this->redirigirConError('Esa solicitud ya no existe.', '/admin/solicitudes');
        }

        SolicitudClave::rechazar($id, $this->idUsuarioActual());
        $this->redirigirConMensaje('Solicitud descartada.', '/admin/solicitudes');
    }

    /** La campanita consulta esto cada cierto tiempo */
    public function apiPendientes(): void
    {
        $this->iniciarSesion();

        if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
            $this->json(['ok' => false], 403);
        }

        $this->json(['ok' => true, 'pendientes' => SolicitudClave::contarPendientes()]);
    }

    /**
     * Envio masivo de claves nuevas a los docentes.
     *
     * El caso real: el instituto arranca el periodo con 100 docentes y hay que
     * hacerles llegar su acceso. Ir uno por uno desde la pantalla de cuentas
     * seria inviable.
     *
     * Aqui se genera un enlace de un solo uso para CADA docente seleccionado y
     * se prepara su mensaje de WhatsApp. WhatsApp no deja enviar en masa sin su
     * API de pago, asi que el envio sigue siendo un clic por persona, pero sin
     * escribir nada ni generar claves a mano.
     */
    public function enviosDocentes(): void
    {
        $this->verificarAdmin();

        $rol = trim($_GET['rol'] ?? '');
        if (!Catalogo::esRolValido($rol)) {
            $rol = '';
        }

        $soloSinTelefono = (($_GET['filtro'] ?? '') === 'sin_telefono');

        $usuarios = array_values(array_filter(
            Usuario::listar($rol),
            static function (array $u) use ($soloSinTelefono) {
                if ((int)$u['activo'] !== 1) {
                    return false;
                }
                return $soloSinTelefono ? empty($u['telefono']) : true;
            }
        ));

        [$mensaje, $error] = $this->obtenerFlash();

        self::abrirSesion();
        $generados = $_SESSION['enlaces_generados'] ?? [];
        unset($_SESSION['enlaces_generados']);

        $this->vista('admin.envios', [
            'base'       => self::obtenerRutaBase(),
            'usuarios'   => $usuarios,
            'rolFiltro'  => $rol,
            'filtro'     => $soloSinTelefono ? 'sin_telefono' : '',
            'generados'  => $generados,
            'usuarioActualId' => $this->idUsuarioActual(),
            'minutos'    => SolicitudClave::MINUTOS_VALIDEZ,
            'mensaje'    => $mensaje,
            'error'      => $error,
            'csrf'       => self::tokenCsrf()
        ]);
    }

    /** Genera los enlaces de un solo uso para los docentes marcados */
    public function generarEnlaces(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/envios');

        $ids = $_POST['usuario_id'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $ids = array_slice(array_filter(array_map('intval', $ids)), 0, 200);

        if (empty($ids)) {
            $this->redirigirConError('Selecciona al menos un docente.', '/admin/envios');
        }

        $generados = [];

        foreach ($ids as $id) {
            $usuario = Usuario::buscarPorId($id);

            if (!$usuario || (int)$usuario['activo'] !== 1) {
                continue;
            }

            // No puede quedar sin acceso quien esta pidiendo el cambio, ni el
            // propio administrador que ejecuta la accion
            if ($id === $this->idUsuarioActual()) {
                continue;
            }

            SolicitudClave::crear($id);
            $pendiente = null;

            foreach (SolicitudClave::pendientes() as $p) {
                if ((int)$p['usuario_id'] === $id) {
                    $pendiente = $p;
                    break;
                }
            }

            if (!$pendiente) {
                continue;
            }

            $token = SolicitudClave::atender((int)$pendiente['id'], $this->idUsuarioActual());

            if ($token === null) {
                continue;
            }

            $enlace  = App::urlPublica('/clave-nueva?t=' . $token);
            $texto   = Whatsapp::mensajeClaveDocente(
                $usuario, $enlace, SolicitudClave::MINUTOS_VALIDEZ, App::SIGLA
            );

            $generados[] = [
                'id'       => $id,
                'nombre'   => Usuario::nombreCompleto($usuario),
                'correo'   => $usuario['correo'],
                'telefono' => $usuario['telefono'],
                'enlace'   => $enlace,
                'mensaje'  => $texto,
                'whatsapp' => Whatsapp::enlace($usuario['telefono'], $texto)
            ];
        }

        if (empty($generados)) {
            $this->redirigirConError('No se pudo generar ningún enlace.', '/admin/envios');
        }

        self::abrirSesion();
        $_SESSION['enlaces_generados'] = $generados;

        $sinTelefono = count(array_filter($generados, static fn($g) => $g['whatsapp'] === null));

        $this->redirigirConMensaje(
            count($generados) . ' enlace(s) generado(s).'
            . ($sinTelefono > 0 ? " {$sinTelefono} sin teléfono: cópialos a mano." : ''),
            '/admin/envios'
        );
    }

    // ==================================================================
    // GESTION ACADEMICA: materias, asignacion de docentes y semestres
    //
    // El catalogo lo maneja SOLO el administrador. Si cada docente pudiera
    // crear materias, la misma asignatura terminaria escrita de varias formas
    // y los reportes por materia dejarian de cuadrar.
    //
    // El reparto de responsabilidades queda asi:
    //   Administrador -> crea materias y semestres, y asigna que docente
    //                    dicta cada materia (eso genera el curso).
    //   Docente       -> matricula a sus estudiantes en los cursos que le
    //                    asignaron y pasa lista.
    // ==================================================================

    public function materias(): void
    {
        $this->verificarAdmin();

        [$mensaje, $error] = $this->obtenerFlash();

        // A cada materia se le adjuntan los cursos que la dictan, para que el
        // administrador vea de un vistazo quien la tiene a cargo
        $materias = Materia::listarConUso();
        foreach ($materias as &$materia) {
            $materia['cursos']     = Curso::listarPorMateria((int)$materia['id']);
            $materia['archivados'] = Curso::listarArchivadosPorMateria((int)$materia['id']);
        }
        unset($materia);

        $this->vista('admin.materias', [
            'base'      => self::obtenerRutaBase(),
            'materias'  => $materias,
            'docentes'  => Usuario::listarDocentes(),
            'semestres' => Semestre::listar(false),
            'ambientes' => Catalogo::AMBIENTES,
            'mensaje'   => $mensaje,
            'error'     => $error,
            'csrf'      => self::tokenCsrf()
        ]);
    }

    // ---------------------- Materias ----------------------

    public function crearMateria(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $nombre = $this->limpiar($_POST['nombre'] ?? '');
        $codigo = strtoupper($this->limpiar($_POST['codigo'] ?? ''));

        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) {
            $this->redirigirConError('El nombre de la materia debe tener entre 3 y 120 caracteres.', '/admin/materias');
        }

        // Si el admin no escribio codigo, se propone uno a partir del nombre
        if ($codigo === '') {
            $codigo = Materia::sugerirCodigo($nombre);
        }

        if (!preg_match('/^[A-Z0-9-]{2,20}$/', $codigo)) {
            $this->redirigirConError('El código debe tener entre 2 y 20 caracteres: letras, números o guiones.', '/admin/materias');
        }

        $resultado = Materia::crear($codigo, $nombre);

        if ($resultado === 'duplicada') {
            $this->redirigirConError("Ya existe una materia con el nombre \"{$nombre}\" o el código \"{$codigo}\".", '/admin/materias');
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudo crear la materia.', '/admin/materias');
        }

        $this->redirigirConMensaje("Materia \"{$nombre}\" creada. Ahora asígnale un docente.", '/admin/materias');
    }

    public function actualizarMateria(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $nombre = $this->limpiar($_POST['nombre'] ?? '');
        $codigo = strtoupper($this->limpiar($_POST['codigo'] ?? ''));
        $activa = ((string)($_POST['activa'] ?? '1') === '0') ? 0 : 1;

        if (!$id || !Materia::buscarPorId($id)) {
            $this->redirigirConError('Esa materia ya no existe.', '/admin/materias');
        }
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) {
            $this->redirigirConError('El nombre de la materia debe tener entre 3 y 120 caracteres.', '/admin/materias');
        }
        if (!preg_match('/^[A-Z0-9-]{2,20}$/', $codigo)) {
            $this->redirigirConError('El código debe tener entre 2 y 20 caracteres: letras, números o guiones.', '/admin/materias');
        }

        $resultado = Materia::actualizar($id, $codigo, $nombre, $activa);

        if ($resultado === 'duplicada') {
            $this->redirigirConError('Ese nombre o código ya pertenece a otra materia.', '/admin/materias');
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudo actualizar la materia.', '/admin/materias');
        }

        $this->redirigirConMensaje('Materia actualizada.', '/admin/materias');
    }

    public function estadoMateria(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $activa = ((string)($_POST['activa'] ?? '1') === '0') ? 0 : 1;

        if (!$id || !Materia::buscarPorId($id)) {
            $this->redirigirConError('Esa materia ya no existe.', '/admin/materias');
        }

        // Nunca se borra: el historial de clases y asistencias apunta aqui
        $ok = ($activa === 1) ? Materia::activar($id) : Materia::desactivar($id);

        if (!$ok) {
            $this->redirigirConError('No se pudo cambiar el estado de la materia.', '/admin/materias');
        }

        $this->redirigirConMensaje(
            $activa === 1
                ? 'Materia reactivada.'
                : 'Materia archivada. Su historial se conserva en los reportes.',
            '/admin/materias'
        );
    }

    // ---------------------- Asignar docente a materia ----------------------

    /**
     * Asignar un docente a una materia es, en la practica, crear el CURSO:
     * la materia mas el docente, el ambiente y el semestre.
     * Ese curso es despues el que el docente usa para matricular alumnos y
     * abrir clases.
     */
    public function asignarDocente(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $materiaId = filter_var($_POST['materia_id'] ?? null, FILTER_VALIDATE_INT);
        $docenteId = filter_var($_POST['docente_id'] ?? null, FILTER_VALIDATE_INT);
        $ambiente  = trim($_POST['ambiente'] ?? '');
        $semestre  = trim($_POST['semestre'] ?? '');

        if (!$materiaId || !Materia::existe($materiaId)) {
            $this->redirigirConError('Selecciona una materia activa.', '/admin/materias');
        }

        $docente = $docenteId ? Usuario::buscarPorId($docenteId) : null;

        if (!$docente) {
            $this->redirigirConError('Selecciona un docente de la lista.', '/admin/materias');
        }
        if ((int)$docente['activo'] === 0) {
            $this->redirigirConError('Esa cuenta está desactivada: no se le puede asignar una materia.', '/admin/materias');
        }
        if (!Catalogo::esAmbienteValido($ambiente)) {
            $this->redirigirConError('Selecciona un ambiente válido.', '/admin/materias');
        }
        if (!Catalogo::esSemestreValido($semestre)) {
            $this->redirigirConError('Selecciona un semestre válido.', '/admin/materias');
        }
        // REGLA ACADEMICA: una materia en un semestre la dicta UN solo docente.
        // El mismo docente si puede tenerla en varios ambientes (teoria en
        // Aula y practica en Laboratorio), pero dos docentes distintos en la
        // misma materia y semestre significaria dos listas de clase paralelas
        // para el mismo grupo, y las asistencias dejarian de cuadrar.
        $ocupada = Curso::docenteDeMateriaEnSemestre($materiaId, $semestre);

        if ($ocupada !== null && (int)$ocupada['docente_id'] !== $docenteId) {
            $this->redirigirConError(
                'Esa materia en ' . $semestre . ' ya la dicta '
                . trim($ocupada['docente_nombre'] . ' ' . $ocupada['docente_apellido'])
                . '. Una materia solo puede tener un docente por semestre: retira primero la asignación actual.',
                '/admin/materias'
            );
        }

        $resultado = Curso::crear($materiaId, $docenteId, $ambiente, $semestre);

        if ($resultado === 'duplicado') {
            $this->redirigirConError(
                'Ese docente ya tiene esta materia en el mismo ambiente y semestre.',
                '/admin/materias'
            );
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudo asignar la materia al docente.', '/admin/materias');
        }

        $nombreDocente = Usuario::nombreCompleto($docente);
        $this->redirigirConMensaje(
            "{$nombreDocente} quedó asignado. Ya puede matricular estudiantes y abrir clases.",
            '/admin/materias'
        );
    }

    public function quitarAsignacion(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $cursoId = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$cursoId || !Curso::buscarPorId($cursoId)) {
            $this->redirigirConError('Esa asignación ya no existe.', '/admin/materias');
        }

        if (Curso::desactivarPorAdmin($cursoId)) {
            $this->redirigirConMensaje(
                'Asignación retirada. Las clases ya dictadas se conservan en los reportes.',
                '/admin/materias'
            );
        }

        $this->redirigirConError('No se pudo retirar la asignación.', '/admin/materias');
    }

    /** Vuelve a activar una asignacion docente-materia que se habia retirado */
    public function restaurarAsignacion(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $cursoId = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$cursoId || !Curso::buscarPorId($cursoId)) {
            $this->redirigirConError('Esa asignación ya no existe.', '/admin/materias');
        }

        if (Curso::reactivarPorAdmin($cursoId)) {
            $this->redirigirConMensaje('Asignación restaurada.', '/admin/materias');
        }

        $this->redirigirConError('No se pudo restaurar la asignación.', '/admin/materias');
    }

    // ---------------------- Semestres ----------------------

    public function crearSemestre(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $nombre = $this->limpiar($_POST['nombre'] ?? '');
        $orden  = filter_var($_POST['orden'] ?? null, FILTER_VALIDATE_INT) ?: (Semestre::contar() + 1);

        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 40) {
            $this->redirigirConError('El nombre del semestre debe tener entre 3 y 40 caracteres.', '/admin/materias');
        }

        $resultado = Semestre::crear($nombre, max(1, min(99, $orden)));

        if ($resultado === 'duplicado') {
            $this->redirigirConError("Ya existe un semestre llamado \"{$nombre}\".", '/admin/materias');
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudo crear el semestre.', '/admin/materias');
        }

        $this->redirigirConMensaje("Semestre \"{$nombre}\" creado.", '/admin/materias');
    }

    public function actualizarSemestre(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $nombre = $this->limpiar($_POST['nombre'] ?? '');
        $orden  = filter_var($_POST['orden'] ?? null, FILTER_VALIDATE_INT) ?: 1;
        $activo = ((string)($_POST['activo'] ?? '1') === '0') ? 0 : 1;

        if (!$id || !Semestre::buscarPorId($id)) {
            $this->redirigirConError('Ese semestre ya no existe.', '/admin/materias');
        }
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 40) {
            $this->redirigirConError('El nombre del semestre debe tener entre 3 y 40 caracteres.', '/admin/materias');
        }

        $resultado = Semestre::actualizar($id, $nombre, max(1, min(99, $orden)), $activo);

        if ($resultado === 'duplicado') {
            $this->redirigirConError('Ya existe otro semestre con ese nombre.', '/admin/materias');
        }
        if ($resultado !== 'ok') {
            $this->redirigirConError('No se pudo actualizar el semestre.', '/admin/materias');
        }

        // Al renombrar, el modelo arrastra el cambio a estudiantes y cursos
        $this->redirigirConMensaje('Semestre actualizado en todo el sistema.', '/admin/materias');
    }

    public function eliminarSemestre(): void
    {
        $this->verificarAdmin();
        $this->verificarCsrf('/admin/materias');

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || !Semestre::buscarPorId($id)) {
            $this->redirigirConError('Ese semestre ya no existe.', '/admin/materias');
        }

        if (Semestre::eliminar($id) === 'en_uso') {
            $uso = Semestre::enUso($id);
            $this->redirigirConError(
                "No se puede eliminar: hay {$uso['estudiantes']} estudiante(s) y {$uso['cursos']} curso(s) en ese semestre. "
                . 'Desactívalo en vez de borrarlo.',
                '/admin/materias'
            );
        }

        $this->redirigirConMensaje('Semestre eliminado.', '/admin/materias');
    }

    // ==================================================================

    private function limpiar(string $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', $valor));
    }

    private function validarNombre(string $valor, string $campo): void
    {
        if (!preg_match(self::PATRON_NOMBRE, $valor)) {
            $this->redirigirConError(
                "El {$campo} debe tener entre 2 y 50 letras, sin números ni símbolos.",
                '/admin/docentes'
            );
        }
    }

    private function validarPassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD) {
            $this->redirigirConError(
                'La contraseña debe tener al menos ' . self::MIN_PASSWORD . ' caracteres.',
                '/admin/docentes'
            );
        }

        // Bcrypt ignora lo que pase de 72 bytes: mejor avisar que truncar en silencio
        if (strlen($password) > 72) {
            $this->redirigirConError('La contraseña no puede superar los 72 caracteres.', '/admin/docentes');
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $this->redirigirConError(
                'La contraseña debe combinar al menos una letra y un número.',
                '/admin/docentes'
            );
        }
    }
}
