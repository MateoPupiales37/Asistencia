# Documento 1: Arquitectura MVC, Base de Datos y Endpoints

**Institución:** Instituto Superior Tecnológico Mayor Pedro Traversari (ISTPET)  
**Carrera:** Desarrollo de Software (Tercer Semestre)  
**Asignatura:** Programación Web / Ingeniería de Software  
**Sistema:** Control y Registro de Asistencia QR en Tiempo Real  
**Patrón de Diseño:** Modelo - Vista - Controlador (MVC Clásico en PHP 8.x nativo con PDO y MySQL)  

---

## 1. Fundamentos del Patrón de Diseño MVC

El patrón **Modelo - Vista - Controlador (MVC)** es el estándar de arquitectura de software que separa la lógica del sistema en tres capas fundamentales para evitar mezclar código HTML con consultas a la base de datos o lógica de negocio.

```text
                           [ Petición del Usuario ]
                         (Navegador Web / Teléfono)
                                     │
                                     ▼
                      ┌─────────────────────────────┐
                      │      public/index.php       │
                      │    (Front Controller)       │
                      └──────────────┬──────────────┘
                                     │ Despacha ruta
                                     ▼
                      ┌─────────────────────────────┐
                      │        Controlador          │
                      │    (controllers/*.php)      │
                      └───────┬─────────────┬───────┘
                              │             │
              [1] Pide datos  │             │ [3] Envía datos procesados
                              ▼             ▼
                      ┌──────────────┐   ┌──────────────┐
                      │    Modelo    │   │    Vista     │
                      │(models/*.php)│   │(views/*.php) │
                      └───────┬──────┘   └──────┬───────┘
                              │                 │
              [2] Consulta    ▼                 │ [4] Renderiza HTML
                      ┌──────────────┐          │
                      │Base de Datos │          ▼
                      │   (MySQL)    │    [ Pantalla del Usuario ]
                      └──────────────┘
```

### 1.1. Las Tres Capas Explicadas al Detalle:

1. **El Modelo (`models/`):**
   * Representa las tablas de la base de datos y sus reglas de negocio.
   * **Regla estricta:** Es el **único** lugar del sistema donde se escriben consultas SQL (`SELECT`, `INSERT`, `UPDATE`, `DELETE`).
   * Utiliza la clase centralizada `config/Database.php` mediante **PDO** con sentencias preparadas y parámetros enlazados para impedir inyecciones SQL.

2. **La Vista (`views/`):**
   * Es la interfaz visual que ve el usuario en su pantalla.
   * Contiene la estructura HTML5 semántica, hojas de estilo CSS y scripts de interactividad en el cliente.
   * **Regla estricta:** No realiza consultas SQL ni interactúa directamente con la base de datos; solo imprime las variables pasadas por el controlador usando `htmlspecialchars()` para prevenir ataques XSS.

3. **El Controlador (`controllers/`):**
   * Es el coordinador y cerebro del flujo de trabajo.
   * Recibe las peticiones HTTP (`GET` y `POST`), valida las entradas de formularios, solicita o envía datos a los modelos y decide qué vista cargar o a qué ruta redirigir.

---

## 2. Estructura de Carpetas del Proyecto

```text
asistencia/
├── config/
│   └── Database.php            # Conexión Singleton PDO a MySQL con credenciales
├── models/                     # MODELOS (Lógica de Datos y Consultas SQL)
│   ├── Docente.php             # Autenticación y consultas de docentes
│   ├── Estudiante.php          # CRUD de alumnos y cálculo correlativo de códigos
│   ├── Sesion.php              # Apertura, token QR y finalización de clases
│   └── Asistencia.php          # Registro de marcas, consultas en vivo y filtros
├── controllers/                # CONTROLADORES (Recepción de peticiones y flujo)
│   ├── BaseController.php      # Métodos base ($this->vista, $this->json, $this->redireccionar, RBAC)
│   ├── HomeController.php      # Portal de bienvenida e información institucional
│   ├── AuthController.php      # Login y Logout de personal y acceso estudiantil
│   ├── AdminController.php     # Panel institucional, personal docente y supervisión en vivo
│   ├── DashboardController.php # Panel de control del profesor y generación de QR
│   ├── EstudianteController.php# Mantenimiento de alumnos y portal del estudiante
│   ├── AsistenciaController.php# Confirmación de escaneo y API de tiempo real
│   └── ReporteController.php   # Filtros avanzados y descargas (CSV, Excel, PDF)
├── libs/                       # BIBLIOTECAS AUTÓNOMAS
│   ├── ReportePdf.php          # Extensión de FPDF con membrete y estilos del ISTPET
│   └── fpdf/                   # Motor FPDF 1.86 independiente (sin Composer)
├── views/                      # VISTAS (Interfaz Visual HTML5)
│   ├── layouts/                # Encabezado con navbar condicional (header.php) y pie (footer.php)
│   ├── home/                   # Pantalla de selección de rol e institucional
│   ├── auth/                   # Formularios de acceso institucional y estudiantil
│   ├── admin/                  # Panel de supervisión en tiempo real y directorio de personal
│   ├── dashboard/              # Panel de asistencia en vivo y modo proyector
│   ├── estudiantes/            # Listado tabular, modal de alta y portal estudiantil
│   ├── asistencia/             # Visor de escaneo de cámara y confirmación acústica
│   ├── reportes/               # Tablero de filtros y botones de descarga multiformato
│   └── errors/                 # Página de error 404 personalizada
├── public/                     # RAÍZ PÚBLICA ACCESIBLE POR EL NAVEGADOR
│   ├── index.php               # Front Controller (Enrutador central)
│   ├── .htaccess               # Reescritura de URLs limpias en Apache
│   └── assets/                 # Estilos CSS nativos, scripts JS e imágenes institucionales
├── database/                   # SCRIPTS SQL
│   └── database.sql            # Estructura de base de datos y datos semilla
└── docs/                       # DOCUMENTACIÓN TÉCNICA
    ├── 1_arquitectura_mvc_y_base_de_datos.md
    ├── 2_auditoria_codigo_linea_a_linea.md
    ├── 3_diseno_ux_ui_y_componentes.md
    └── 4_guia_instalacion_pruebas_y_defensa.md
```

---

## 3. Diccionario y Modelo de Base de Datos

* **Nombre de Base de Datos:** `asistencia_qr`
* **Motor de Almacenamiento:** InnoDB (garantiza integridad referencial, transacciones y claves foráneas)
* **Juego de Caracteres:** `utf8mb4` / Collation: `utf8mb4_unicode_ci`

```text
┌─────────────────┐       1:N       ┌─────────────────┐       1:N       ┌─────────────────┐
│    docentes     ├─────────────────┤    sesiones     ├─────────────────┤   asistencias   │
│(Personal/Admin) │                 │                 │                 │                 │
│ id (PK)         │                 │ id (PK)         │                 │ id (PK)         │
│ nombre          │                 │ docente_id (FK) │                 │ sesion_id (FK)  │
│ usuario (UQ)    │                 │ codigo_sesion   │            ┌────┤ estudiante_id   │
│ password        │                 │ materia         │            │    │ fecha, hora     │
│ rol (ENUM)      │                 │ activa          │            │    └─────────────────┘
│ activo (TINYINT)│                 └─────────────────┘            │
└─────────────────┘                                                │ 1:N
                                                            ┌──────┴──────────┐
                                                            │   estudiantes   │
                                                            │                 │
                                                            │ id (PK)         │
                                                            │ codigo (UQ)     │
                                                            │ nombre, apellido│
                                                            │ carrera         │
                                                            └─────────────────┘
```

### 3.1. Tablas y Especificación de Columnas:

#### A. Tabla: `docentes`
Almacena las cuentas de acceso para los profesores y administradores de la institución.
* `id` (`INT AUTO_INCREMENT PRIMARY KEY`): Identificador único del usuario institucional.
* `nombre` (`VARCHAR(150) NOT NULL`): Nombres y apellidos completos con título académico.
* `usuario` (`VARCHAR(100) NOT NULL UNIQUE`): Nombre de usuario para autenticación en el portal.
* `password` (`VARCHAR(255) NOT NULL`): Contraseña cifrada mediante hash BCRYPT (`password_hash`).
* `rol` (`ENUM('docente', 'admin') NOT NULL DEFAULT 'docente'`): Perfil y nivel de autorización asignado.
* `activo` (`TINYINT(1) NOT NULL DEFAULT 1`): `1` para cuenta habilitada; `0` para cuenta suspendida/inactiva.
* `creado_en` (`TIMESTAMP DEFAULT CURRENT_TIMESTAMP`): Fecha y hora de creación.

#### B. Tabla: `estudiantes`
Catálogo de alumnos matriculados habilitados para registrar asistencia.
* `id` (`INT AUTO_INCREMENT PRIMARY KEY`): Identificador interno del alumno.
* `codigo` (`VARCHAR(50) NOT NULL UNIQUE`): Código de matrícula institucional (ej: `EST001`).
* `nombre` (`VARCHAR(150) NOT NULL`): Nombres del estudiante.
* `apellido` (`VARCHAR(150)`): Apellidos del estudiante.
* `carrera` (`VARCHAR(100)`): Especialidad técnica cursada.
* `fecha_registro` (`TIMESTAMP DEFAULT CURRENT_TIMESTAMP`): Fecha y hora de alta en el sistema.

#### C. Tabla: `sesiones`
Representa una jornada académica o clase creada por un docente con su código QR.
* `id` (`INT AUTO_INCREMENT PRIMARY KEY`): Identificador único de la sesión.
* `docente_id` (`INT NOT NULL`): Clave foránea referenciando a `docentes(id)` con `ON DELETE CASCADE`.
* `codigo_sesion` (`VARCHAR(20) NOT NULL UNIQUE`): Código alfanumérico único de 8 caracteres generado para el QR.
* `materia` (`VARCHAR(150) NOT NULL`): Nombre de la asignatura con carrera y nivel entre paréntesis.
* `fecha` (`DATE NOT NULL`): Fecha de impartición de la clase.
* `hora_inicio` (`TIME`): Hora en que se generó el código QR.
* `hora_fin` (`TIME`): Hora en que el docente finalizó la sesión.
* `activa` (`TINYINT(1) NOT NULL DEFAULT 1`): `1` para clase abierta; `0` para clase finalizada.

#### D. Tabla: `asistencias`
Registro individual de asistencia de un estudiante a una sesión de clase específica.
* `id` (`INT AUTO_INCREMENT PRIMARY KEY`): Identificador de la marca de asistencia.
* `sesion_id` (`INT NOT NULL`): Clave foránea referenciando a `sesiones(id)` con `ON DELETE CASCADE`.
* `estudiante_id` (`INT NOT NULL`): Clave foránea referenciando a `estudiantes(id)` con `ON DELETE CASCADE`.
* `fecha` (`DATE NOT NULL`): Fecha de marcación.
* `hora` (`TIME NOT NULL`): Hora exacta del registro.
* `fecha_registro` (`TIMESTAMP DEFAULT CURRENT_TIMESTAMP`): Timestamp de inserción.

#### Restricción de Integridad de Negocio:
```sql
UNIQUE KEY unica_asistencia (sesion_id, estudiante_id)
```
* **Propósito:** Evita a nivel de motor de base de datos que un estudiante registre su asistencia más de una vez en la misma sesión de clase.

---

## 4. Catálogo de Rutas HTTP y Endpoints

El archivo [`public/index.php`](../public/index.php) opera como **Front Controller** único que despacha las peticiones:

| Método | Ruta | Parámetros | Controlador / Método | Salida / Middleware |
|---|---|---|---|---|
| `GET` | `/` | - | `HomeController::index` | Vista pública inicial |
| `GET` | `/institucional` | - | `HomeController::institucional` | Información académica ISTPET |
| `GET` | `/login` | - | `AuthController::mostrarLoginDocente` | Formulario de acceso institucional |
| `POST`| `/login` | `usuario`, `password` | `AuthController::loginDocente` | Autenticación y redirección por rol |
| `GET` | `/logout` | - | `AuthController::logoutDocente` | Destruye sesión institucional |
| `GET` | `/login-estudiante` | - | `AuthController::mostrarLoginEstudiante` | Formulario de acceso alumno |
| `POST`| `/login-estudiante` | `codigo` | `AuthController::loginEstudiante` | Autentica alumno por código |
| `GET` | `/logout-estudiante` | - | `AuthController::logoutEstudiante` | Cierra sesión de alumno |
| `GET` | `/admin` | - | `AdminController::index` | Panel institucional (Requiere Admin) |
| `GET` | `/admin/docentes` | `buscar`, `rol` | `AdminController::docentes` | Directorio de personal (Requiere Admin) |
| `POST`| `/admin/docentes/crear` | `nombre`, `usuario`, `password`, `rol` | `AdminController::crearDocente` | Alta de usuario (Requiere Admin) |
| `POST`| `/admin/docentes/actualizar` | `id`, `nombre`, `usuario`, `rol`, `activo` | `AdminController::actualizarDocente` | Edición de usuario (Requiere Admin) |
| `POST`| `/admin/docentes/resetear-password` | `id`, `password` | `AdminController::resetearPassword` | Reseteo de clave (Requiere Admin) |
| `POST`| `/admin/docentes/cambiar-estado` | `id`, `activo` | `AdminController::cambiarEstadoDocente` | Activa/suspende cuenta (Requiere Admin) |
| `POST`| `/admin/docentes/eliminar` | `id` | `AdminController::eliminarDocente` | Baja física controlada (Requiere Admin) |
| `POST`| `/admin/sesion/cerrar` | `sesion_id` | `AdminController::cerrarSesionForzada` | Cierre de clase huérfana (Requiere Admin)|
| `GET` | `/dashboard` | - | `DashboardController::index` | Panel docente (Requiere Docente) |
| `POST`| `/dashboard/sesion/crear` | `carrera`, `nivel`, `materia` | `DashboardController::crearSesion` | Genera sesión y QR |
| `POST`| `/dashboard/sesion/cerrar`| `sesion_id` | `DashboardController::cerrarSesion` | Cierra sesión de clase |
| `GET` | `/estudiantes` | `buscar` (opcional) | `EstudianteController::index` | CRUD listado de alumnos |
| `POST`| `/estudiantes/crear` | `codigo`, `nombre`, `apellido`, `carrera` | `EstudianteController::crear` | Registra nuevo alumno |
| `POST`| `/estudiantes/actualizar` | `id`, `codigo`, `nombre`, `apellido`, `carrera` | `EstudianteController::actualizar` | Edita datos de alumno |
| `POST`| `/estudiantes/eliminar` | `id` | `EstudianteController::eliminar` | Elimina alumno |
| `GET` | `/estudiante/portal` | - | `EstudianteController::portal` | Historial del estudiante |
| `GET` | `/asistencia/escanear` | `codigo` (opcional en query) | `AsistenciaController::mostrarEscanear` | Visor de escaneo / Formulario |
| `POST`| `/asistencia/registrar`| `codigo_sesion`, `codigo_estudiante` | `AsistenciaController::registrar` | Valida y guarda asistencia |
| `GET` | `/reportes` | `fecha_inicio`, `fecha_fin`, `materia`, `busqueda`, `docente_id`, `carrera` | `ReporteController::index` | Reporte con filtros (Docente o Admin) |
| `GET` | `/reportes/csv` | Filtros de fecha/materia/docente | `ReporteController::exportarCsv` | Descarga CSV (con columna Docente) |
| `GET` | `/reportes/excel` | Filtros de fecha/materia/docente | `ReporteController::exportarExcel` | Descarga Excel .xls oficial |
| `GET` | `/reportes/pdf` | Filtros de fecha/materia/docente | `ReporteController::exportarPdf` | Descarga documento PDF A4 membretado |
| `GET` | `/api/asistencias/activas` | - | `AsistenciaController::apiListarActivas` | JSON para polling en vivo |

---

## 5. Especificación de Endpoints y Respuestas JSON

### Endpoint: `GET /api/asistencias/activas`

Utilizado por el panel del docente para actualizar la tabla de asistencias cada 5 segundos mediante sondeo asíncrono (*polling*) sin recargar la página.

* **Middleware Requerido:** Sesión activa de docente (`$_SESSION['docente_id']`).
* **Cabecera de Respuesta:** `Content-Type: application/json; charset=utf-8`

#### Respuesta JSON con Sesión Activa:
```json
{
  "success": true,
  "activa": true,
  "sesion": {
    "id": 14,
    "codigo_sesion": "B7E1C840",
    "materia": "Programación Web II (Desarrollo de Software - Tercer Nivel)"
  },
  "asistencias": [
    {
      "id": 45,
      "fecha": "2026-09-02",
      "hora": "11:45:10",
      "codigo": "EST001",
      "nombre": "Juan",
      "apellido": "Pérez",
      "carrera": "Desarrollo de Software"
    },
    {
      "id": 46,
      "fecha": "2026-09-02",
      "hora": "11:45:48",
      "codigo": "EST002",
      "nombre": "María",
      "apellido": "García",
      "carrera": "Desarrollo de Software"
    }
  ]
}
```

#### Respuesta JSON sin Sesión Activa (o tras finalizar clase):
```json
{
  "success": true,
  "activa": false,
  "asistencias": []
}
```

---

## 6. Script SQL Completo de Creación de Tablas (DDL)

```sql
-- Creación de la Base de Datos
CREATE DATABASE IF NOT EXISTS asistencia_qr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asistencia_qr;

-- Tabla: docentes (Personal y Administradores)
CREATE TABLE IF NOT EXISTS docentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    usuario VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('docente', 'admin') NOT NULL DEFAULT 'docente',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: estudiantes
CREATE TABLE IF NOT EXISTS estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    apellido VARCHAR(150),
    carrera VARCHAR(100),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: sesiones
CREATE TABLE IF NOT EXISTS sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    codigo_sesion VARCHAR(20) NOT NULL UNIQUE,
    materia VARCHAR(150) NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME DEFAULT NULL,
    hora_fin TIME DEFAULT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: asistencias
CREATE TABLE IF NOT EXISTS asistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    UNIQUE KEY unica_asistencia (sesion_id, estudiante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 7. Modelo de Seguridad y Control de Acceso Basado en Roles (RBAC)

El sistema implementa una arquitectura de seguridad por capas basada en sesiones PHP seguras y verificación estricta de privilegios:

```text
               ┌─────────────────────────────────────────┐
               │         Petición HTTP entrante          │
               └────────────────────┬────────────────────┘
                                    ▼
               ┌─────────────────────────────────────────┐
               │    Front Controller (public/index.php)  │
               └────────────────────┬────────────────────┘
                                    ▼
               ┌─────────────────────────────────────────┐
               │           BaseController (RBAC)         │
               ├─────────────────────────────────────────┤
               │ • verificarDocente()                    │
               │ • verificarAdmin()                      │
               │ • esAdmin()                             │
               └──────────┬───────────────────┬──────────┘
                          │ (Si es admin)     │ (Si es docente)
                          ▼                   ▼
                 Panel de Supervisión    Panel Docente
                 (/admin)                (/dashboard)
```

### Matriz de Permisos Institucionales:

| Funcionalidad / Operación | Administrador | Docente | Estudiante |
|---|:---:|:---:|:---:|
| Iniciar sesión institucional (`/login`) | Sí | Sí | No |
| Iniciar clase y generar código QR | No (Supervisa) | Sí | No |
| Finalizar sus propias clases en vivo | Sí | Sí | No |
| Forzar cierre de sesiones huérfanas de cualquier aula | Sí | No | No |
| Escanear código QR y registrar asistencia | No | No | Sí |
| Consultar expediente personal de asistencia | No | No | Sí |
| Registrar nuevos docentes y administradores | Sí | No | No |
| Modificar datos, roles y suspender cuentas | Sí | No | No |
| Restablecer contraseñas con hash Bcrypt | Sí | No | No |
| Gestionar padrón estudiantil (CRUD) | Sí | Sí | No |
| Consultar reportes de sus asignaturas | Sí | Sí | No |
| Generar reportes consolidados de toda la institución | Sí | No | No |
| Exportación multiformato (CSV, Excel, PDF) con filtro docente | Sí | No | No |
