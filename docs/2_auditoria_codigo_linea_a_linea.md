# Documento 2: Auditoría Exhaustiva de Código Línea a Línea

**Institución:** Instituto Superior Tecnológico Mayor Pedro Traversari (ISTPET)  
**Carrera:** Desarrollo de Software (Tercer Semestre)  
**Sistema:** Control y Registro de Asistencia QR en Tiempo Real  
**Enfoque:** Análisis técnico, línea a línea, de cada archivo, controlador, modelo, vista y biblioteca del sistema.

---

## 1. Capa de Configuración

### `config/Database.php`
* **Líneas 6-8:** Declaración de la clase `Database` con la propiedad estática privada `?PDO $conexion = null`. Implementa el patrón de diseño **Singleton** para garantizar una única conexión activa por ciclo de vida de la petición.
* **Líneas 10-18:** Método estático `conectar(): PDO`. Extrae las variables de entorno (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`) con valores de respaldo predeterminados (`localhost`, `3306`, `asistencia_qr`, `root`, `12345`).
* **Líneas 21-25:** Construcción del DSN de conexión `mysql:host=...;port=...;dbname=...;charset=utf8mb4`. Instanciación del objeto `PDO` configurando los atributos `ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` (manejo de errores mediante excepciones) y `ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC` (retorno de filas como arreglos asociativos).
* **Líneas 26-28:** Bloque `catch (PDOException $e)` para captura de fallos de conexión sin exponer la traza completa ni contraseñas.

---

## 2. Enrutador Frontal y Despacho

### `public/index.php`
* **Líneas 3-8:** Verificación de entorno CLI `php_sapi_name() === 'cli-server'` para servir archivos estáticos (`.css`, `.js`, imágenes) de forma nativa sin pasar por el enrutador.
* **Líneas 10-15:** Importación de controladores mediante `require_once`.
* **Líneas 17-21:** Extracción del path de la URI solicitada mediante `parse_url` y normalización del método HTTP (`GET`, `POST`, transformando `HEAD` a `GET`).
* **Líneas 24-28:** Cálculo dinámico del prefijo de subdirectorio para compatibilidad total en subcarpetas de XAMPP (como `/asistencia`) o servidores virtuales en raíz. Normalización de la variable `$ruta`.
* **Líneas 31-120:** Sentencia `switch ("{$metodo} {$ruta}")` que despacha las rutas del sistema:
  * `GET /` e `institucional`: `HomeController`.
  * `GET/POST /login`, `GET /logout`, `GET/POST /login-estudiante`, `GET /logout-estudiante`: `AuthController`.
  * `GET /dashboard`, `POST /dashboard/sesion/crear`, `POST /dashboard/sesion/cerrar`: `DashboardController`.
  * `GET /estudiantes`, `POST /estudiantes/crear`, `POST /estudiantes/actualizar`, `POST /estudiantes/eliminar`, `GET /estudiante/portal`: `EstudianteController`.
  * `GET /asistencia/escanear`, `POST /asistencia/registrar`, `GET /api/asistencias/activas`: `AsistenciaController`.
  * `GET /reportes`, `GET /reportes/csv`, `GET /reportes/excel`, `GET /reportes/pdf`: `ReporteController`.
  * Bloque `default`: Envío de cabecera `HTTP/1.0 404 Not Found` y renderizado de la vista de error `views/errors/404.php`.

---

## 3. Capa de Controladores (`controllers/`)

### `controllers/BaseController.php`
* **Líneas 8-18:** Método protegido `vista(string $nombreVista, array $datos = [])`. Utiliza `extract($datos)` para exponer variables directamente en la vista y carga el archivo PHP correspondiente transformando puntos en barras (ej. `estudiantes.index` a `views/estudiantes/index.php`).
* **Líneas 21-26:** Método protegido `json(array $datos)`. Establece la cabecera `Content-Type: application/json; charset=utf-8`, serializa con `json_encode` y detiene el script con `exit`.
* **Líneas 29-34:** Método protegido `redireccionar(string $ruta)`. Concatena la ruta base calculada y emite la cabecera `Location: ...`.
* **Líneas 37-41:** Método público estático `obtenerRutaBase(): string`. Determina el prefijo de subcarpeta de ejecución.
* **Líneas 44-78:** Métodos de Control de Acceso (RBAC):
  * `iniciarSesion()`: Inicializa `session_start()` de forma segura si no está activa.
  * `verificarDocente()`: Comprueba que el usuario autenticado posea rol `docente` o `admin`.
  * `verificarAdmin()`: Protege rutas exclusivas directivas; si el rol no es `admin`, deniega el acceso y redirige.
  * `esAdmin(): bool`: Helper booleano para renderizado condicional de vistas e interfaces.

### `controllers/HomeController.php`
* **Líneas 8-11:** `index()`. Carga la vista principal `views/home/index.php` con banners dinámicos según el rol autenticado (`admin`, `docente` o `estudiante`).
* **Líneas 14-17:** `institucional()`. Carga la vista `views/home/institucional.php` con la descripción de las 4 carreras técnicas del ISTPET.

### `controllers/AuthController.php`
* **Líneas 11-30:** `mostrarLoginDocente()`. Si existe sesión activa, evalúa el rol del usuario: redirige al administrador a `/admin` y al docente a `/dashboard`.
* **Líneas 33-60:** `loginDocente()`. Sanitiza credenciales, consulta `Docente::buscarPorUsuario($usuario)` y valida la contraseña mediante `password_verify()`. Verifica que la cuenta esté habilitada (`activo = 1`). Si es válida, inicializa las variables de sesión y redirige inteligentemente según su rol (`admin` o `docente`).
* **Líneas 63-71:** `logoutDocente()`. Destruye la sesión de forma segura y redirige al login.
* **Líneas 74-125:** `loginEstudiante()` y `logoutEstudiante()`. Controla el acceso del estudiante mediante su código institucional.

### `controllers/AdminController.php` (Nuevo Módulo Institucional)
* **`index()`:** Dashboard institucional. Recupera métricas globales (total de docentes activos, estudiantes matriculados, total de clases dictadas, asistencias de hoy), lista las clases activas en tiempo real en toda la institución (`Sesion::listarTodasActivas()`), obtiene la distribución de asistencias por carrera y el historial reciente.
* **`docentes()`:** Directorio de personal con soporte de búsqueda y filtro por rol (`docente` o `admin`).
* **`crearDocente()`:** Validación defensiva de datos e inserción con hash seguro Bcrypt.
* **`actualizarDocente()`:** Modificación de datos, roles y estados. Incluye protección para evitar que el administrador en sesión se auto-desactive o revoque sus propios privilegios.
* **`resetearPassword()`:** Permite al administrador asignar una nueva contraseña cifrada ante olvidos del personal.
* **`cambiarEstadoDocente()`:** Activa o suspende el acceso al sistema (`activo = 1 / 0`).
* **`eliminarDocente()`:** Baja física controlada con captura de excepciones de clave foránea (`PDOException`).
* **`cerrarSesionForzada()`:** Supervisión institucional para finalizar clases que hayan quedado abiertas por descuido del profesor.

### `controllers/DashboardController.php`
* **Líneas 10-25:** Hereda `verificarDocente()` de `BaseController`.
* **Líneas 28-76:** `index()`. Obtiene la sesión activa mediante `Sesion::obtenerActiva($docenteId)`, genera el código QR con API pública, consulta la lista en vivo de alumnos y calcula las estadísticas de su jornada.
* **Líneas 78-140:** `crearSesion()`. Valida Carrera, Nivel y Materia. Genera un token aleatorio de 8 caracteres con `bin2hex(random_bytes(4))` y abre la nueva clase.
* **Líneas 143-155:** `cerrarSesion()`. Finaliza la sesión de clase actual.

### `controllers/EstudianteController.php`
* **Líneas 15-45:** `index()`. Lista estudiantes con buscador, pasa la variable `esAdmin` para adaptar navegación de retorno y calcula el siguiente código sugerido correlativo (`sugerirSiguienteCodigo()`).
* **Líneas 48-97:** `crear()`. Valida unicidad de código institucional e inserta el nuevo alumno.
* **Líneas 100-167:** `actualizar()` y `eliminar()`. Procesa la edición y baja de alumnos.
* **Líneas 170-194:** `portal()`. Muestra el expediente académico del estudiante autenticado.

### `controllers/AsistenciaController.php`
* **Líneas 10-32:** `mostrarEscanear()`. Muestra el visor de cámara y escáner manual.
* **Líneas 35-104:** `registrar()`. Valida estado de clase (`activa = 1`), existencia de alumno y previene doble marcación con `Asistencia::existe()`.
* **Líneas 107-150:** `apiListarActivas()`. Endpoint JSON para el sondeo (*polling*) en tiempo real del panel docente.

### `controllers/ReporteController.php`
* **Líneas 10-52:** `index()`. Detecta si el usuario es `admin` o `docente`. Si es admin, permite seleccionar cualquier docente o "Todos", además de filtrar por carrera institucional. Consulta `Asistencia::filtrar()`.
* **Líneas 54-101:** `exportarCsv()`. Genera archivo CSV con codificación UTF-8 BOM, agregando la columna *Docente* en exportaciones de nivel administrativo.
* **Líneas 104-185:** `exportarExcel()`. Genera hoja de cálculo SpreadsheetML estilizada con membrete oficial del ISTPET.
* **Líneas 188-251:** `exportarPdf()`. Genera documento A4 Landscape oficial mediante `libs/ReportePdf.php`.

---

## 4. Capa de Modelos (`models/`)

### `models/Docente.php`
* **`buscarPorUsuario(string $usuario)`:** Consulta parametrizada `SELECT * FROM docentes WHERE usuario = ? LIMIT 1`.
* **`buscarPorId(int $id)`:** Consulta por clave primaria incluyendo contraseña cifrada y rol.
* **`listar(string $busqueda = '', string $rol = '')`:** Directorio con agregación `LEFT JOIN` a sesiones y asistencias para calcular métricas de clases impartidas y alumnos atendidos.
* **`crear(string $nombre, string $usuario, string $password, string $rol = 'docente')`:** Cifra contraseña con `password_hash(..., PASSWORD_BCRYPT)` y registra el nuevo usuario.
* **`actualizar(int $id, string $nombre, string $usuario, string $rol, int $activo)`:** Modifica datos y perfil del personal.
* **`cambiarPassword(int $id, string $nuevaPassword)`:** Actualiza el hash de clave mediante sentencia preparada.
* **`cambiarEstado(int $id, int $activo)`:** Alterna estado activo/suspendido.
* **`eliminar(int $id)`:** Elimina físicamente si no existen dependencias relacionales foráneas.
* **`contarActivos(?string $rol = null)`:** Calcula el conteo de usuarios activos por rol.

### `models/Estudiante.php`
* **`listar(string $busqueda = '')`:** Consultas con ordenamiento `ORDER BY nombre ASC`.
* **`sugerirSiguienteCodigo(): string`:** Recupera todos los códigos, extrae el número con `preg_match('/^EST(\d+)$/i')`, calcula el valor máximo y devuelve `sprintf('EST%03d', $max + 1)`.
* **`crear()`, `actualizar()`, `eliminar()`:** Operaciones CRUD parametrizadas con sentencias preparadas PDO.

### `models/Sesion.php`
* **`obtenerActiva(int $docenteId)`:** Consulta `SELECT * FROM sesiones WHERE docente_id = ? AND activa = 1 ORDER BY id DESC LIMIT 1`.
* **`crear()`:** Inserta nueva sesión con `activa = 1`, fecha y hora actual.
* **`cerrar(int $id, int $docenteId)`:** Finaliza la clase por parte del docente.
* **`listarTodasActivas(): array`:** Consulta institucional para el Administrador que obtiene todas las aulas transmitiendo en tiempo real con sus respectivos conteos de alumnos en vivo.
* **`cerrarPorAdmin(int $sesionId): bool`:** Cierre forzoso de sesiones huérfanas u olvidadas ejecutado por el Administrador.
* **`contarActivasGlobal()`, `contarTotalInstitucional()`, `listarRecientesGlobal()`:** Funciones de analítica directiva.

### `models/Asistencia.php`
* **`registrar(int $sesionId, int $estudianteId)`:** Inserta el registro con `fecha = CURDATE()` y `hora = CURTIME()`.
* **`existe(int $sesionId, int $estudianteId): bool`:** Verifica duplicidad previa mediante `SELECT id FROM asistencias WHERE sesion_id = ? AND estudiante_id = ?`.
* **`filtrar(?int $docenteId, ?string $inicio, ?string $fin, ?string $materia, ?string $busqueda, ?string $carrera = null): array`:** Consulta dinámica institucional que admite `$docenteId = null` para reportes globales consolidados de todo el instituto y filtro por especialidad técnica.
* **`contarHoyGlobal()`, `contarTotalGlobal()`:** Totalizadores de concurrencia diaria e histórica.
* **`contarPorCarrera(): array`:** Distribución agrupada de asistencias por carrera técnica.

---

## 5. Capa de Bibliotecas (`libs/`)

### `libs/ReportePdf.php`
* **Extensión de `FPDF`:** Sobrescribe los métodos `Header()` y `Footer()` para incorporar el membrete oficial del ISTPET, logotipo corporativo, datos de emisión, línea divisoria dorada y paginación automática `AliasNbPages()`.
* **`celdaAjustada($ancho, $alto, $texto, ...)`:** Función matemática que evalúa `GetStringWidth()`. Si el texto excede el ancho de la columna, lo trunca y agrega `...`, garantizando una tabla perfectamente alineada.
* **`conv(string $txt): string`:** Transforma cadenas UTF-8 a codificación ISO-8859-1 para soporte nativo de caracteres en español (tildes, eñes) en el motor tipográfico de FPDF.

---

## 6. Capa de Vistas y Layouts (`views/`)

### 6.1. Layouts Globales
* **`views/layouts/header.php`:**
  * Define la cabecera HTML5 estándar con `<!DOCTYPE html>`, charset `UTF-8` y meta `viewport` para diseño responsivo.
  * Carga tipografía local autoalojada *Inter*.
  * Enlaza la hoja de estilos maestra [`public/assets/css/style.css`](../public/assets/css/style.css).
  * Renderiza la barra de navegación institucional superior según el rol autenticado:
    * **Administrador:** Badge distintivo rojo `ADMINISTRADOR`, enlaces a *Supervisión*, *Docentes*, *Estudiantes*, *Reportes Globales* y botón de salida.
    * **Docente:** Badge dorado `DOCENTE`, enlaces a *Panel QR*, *Estudiantes*, *Reportes* y botón de salida.
    * **Estudiante:** Badge dorado `ESTUDIANTE`, enlaces a *Mi Expediente*, *Escanear QR* y botón de salida.
* **`views/layouts/footer.php`:**
  * Cierra las etiquetas de contenedor principal y cuerpo.
  * Imprime el pie de página institucional con derechos reservados y créditos académicos del ISTPET.

### 6.2. Vistas del Administrador (`views/admin/`)
* **`views/admin/index.php`:** Dashboard directivo. Presenta 4 tarjetas de métricas institucionales, pizarra en vivo de clases activas con opción de cierre forzado, barras proporcionales de asistencia por carrera e historial reciente de clases dictadas.
* **`views/admin/docentes.php`:** Directorio del personal académico y usuarios. Tabla con estado de cuenta, clases dictadas y modales interactivos para registro, edición de datos/rol y reseteo de claves con Bcrypt.

### 6.3. Vistas de Inicio e Institucional (`views/home/`)
* **`views/home/index.php`:** Pantalla principal de selección de rol. Presenta dos tarjetas interactivas (*Acceso Docente* y *Registrar Asistencia en Clase*) con microinteracciones CSS y enlaces directos a `/login` y `/asistencia/escanear`.
* **`views/home/institucional.php`:** Portal informativo sobre el ISTPET, su misión educativa y el catálogo de las 4 carreras técnicas disponibles.

### 6.3. Vistas de Autenticación (`views/auth/`)
* **`views/auth/login-docente.php`:** Formulario centrado con validación de credenciales para docentes (`usuario` y `password`), alertas de error estilizadas, asistente visual con credenciales de prueba preconfiguradas (`profesor`/`12345`) y enlace de retorno.
* **`views/auth/login-estudiante.php`:** Formulario de acceso al portal del estudiante mediante código institucional (ej. `EST001`), transformador a mayúsculas automático y atajos para pruebas rápidas.

### 6.4. Vistas del Panel y Estudiantes (`views/dashboard/` y `views/estudiantes/`)
* **`views/dashboard/index.php`:**
  * Formulario de apertura de clase con selección de Carrera, Nivel y generación dinámica de chips de materias sugeridas.
  * Tarjeta de clase activa con código QR ampliado, código manual de 8 caracteres, enlace copiable al portapapeles y botón para finalizar sesión.
  * **Modo Proyector de Aula:** Modal a pantalla completa con fondo oscurecido y desenfocado (`backdrop-filter: blur(8px)`) para proyectar el QR en grande ante los alumnos.
  * Tabla de asistencias en vivo con temporizador JavaScript que consulta `/api/asistencias/activas` cada 5 segundos y emite un tono de campana institucional al registrarse un nuevo alumno.
* **`views/estudiantes/index.php`:**
  * Tabla de mantenimiento de alumnos con buscador instantáneo.
  * Modal interactivo para crear nuevo estudiante con **código correlativo autogenerado** (ej. `EST009`), campo editable y foco automático en el nombre.
  * Modales para edición de datos y confirmación de eliminación segura.
* **`views/estudiantes/portal.php`:**
  * Tarjetas métricas con total de clases asistidas, porcentaje de asistencia y materias cursadas.
  * Historial tabular cronológico de todas las asistencias registradas por el estudiante.

### 6.5. Vistas de Asistencia y Reportes (`views/asistencia/`, `views/reportes/` y `views/errors/`)
* **`views/asistencia/escanear.php`:**
  * Guía visual paso a paso para el estudiante.
  * Visor HUD animado con línea láser de barrido (`.scanner-scan-bar`) y esquinas con marco dorado.
  * Decodificación dual con `BarcodeDetector` nativo por hardware y fallback local `jsQR` optimizado a 800px.
  * Formulario alternativo de ingreso manual para estudiantes con problemas en su cámara.
* **`views/asistencia/resultado.php`:**
  * Pantalla de confirmación con detalles de la clase y hora exacta de registro.
  * Sintetizador Web Audio API puro (`AudioContext`) que emite tonos armónicos ascendentes (880-1760 Hz) en éxito o graves (320-240 Hz) en caso de duplicidad.
* **`views/reportes/index.php`:**
  * Formulario de filtros por fechas (con botones rápidos *Hoy*, *Este Mes*, *Últimos 30 días*, *Histórico*), materia y búsqueda de estudiante.
  * Badges interactivos para remover filtros individuales.
  * Botones de descarga directa en **CSV** (con BOM UTF-8), **Excel** (SpreadsheetML) y **PDF** (A4 Landscape oficial).
* **`views/errors/404.php`:**
  * Pantalla de error 404 personalizada con código de estado HTTP adecuado, mensaje amigable y botón de regreso a la página principal.

---

## 7. Capa de Configuración Web y Estilos (`public/`)

### `public/.htaccess`
* Activa `RewriteEngine On` en servidores Apache.
* Reglas `RewriteCond %{REQUEST_FILENAME} !-f` y `RewriteCond %{REQUEST_FILENAME} !-d`: Si la petición no coincide con un archivo o directorio físico existente, reescribe transparentemente la URL hacia `index.php`, permitiendo URLs limpias y amigables.

### `public/assets/css/style.css` y `estilos.css`
* **Paleta de Colores Institucionales:** Azul Marino Profundo (`#1A2B4C`), Azul Real (`#2563EB`), Dorado Corporativo (`#B8912E`), Fondo de Interfaz (`#F4F6F9`) y Blanco Puro (`#FFFFFF`).
* **Sistema Responsivo en Cascada:** Media queries estructuradas en 4 niveles (1024px, 900px, 768px, 480px) con `-webkit-overflow-scrolling: touch` para desplazamiento inercial en tablas y `overflow-x: hidden` para prevenir desbordes laterales en móviles.

---

## 8. Análisis de Seguridad e Integridad

1. **Inyección SQL (100% Mitigada):** Todos los modelos interactúan con la base de datos utilizando exclusivamente sentencias preparadas de PDO (`$stmt->prepare()` y `$stmt->execute($params)`). Ningún dato introducido por el usuario se concatena directamente en las consultas SQL.
2. **Cross-Site Scripting (XSS):** Toda variable dinámica impresa en las vistas PHP pasa por `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`.
3. **Control de Acceso y Sesiones:** Validación de roles en controladores mediante `$_SESSION['docente_id']` y `$_SESSION['estudiante_id']`, redirigiendo de inmediato a `/login` ante peticiones no autenticadas.
4. **Manejo de Búferes:** Uso estricto de `ob_end_clean()` antes de emitir las cabeceras HTTP de descarga binaria (CSV, Excel, PDF) para garantizar archivos limpios y no corruptos.

