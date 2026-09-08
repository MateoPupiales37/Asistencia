# Documento 4: Guía de Instalación (XAMPP / MySQL Workbench), Manual de Uso y Guía de Defensa

**Institución:** Instituto Superior Tecnológico Mayor Pedro Traversari (ISTPET)  
**Carrera:** Desarrollo de Software (Tercer Semestre)  
**Objetivo:** Guía práctica para instalar el proyecto en cualquier computadora, realizar pruebas completas y defender con éxito el proyecto ante el tribunal docente.

---

## 1. Requisitos del Sistema

* **Intérprete:** PHP versión 8.0 o superior (recomendado PHP 8.2 o 8.3).
* **Extensiones PHP Requeridas:** `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `curl`.
* **Servidor Web:** Apache 2.4 (incluido en XAMPP) o el Servidor Web Integrado de PHP (`php -S`).
* **Base de Datos:** MySQL 5.7 / 8.0+ o MariaDB 10.4+ (gestionable mediante **MySQL Workbench** o **phpMyAdmin**).

---

## 2. Métodos de Instalación y Despliegue

### Método 1: Con XAMPP (Apache + MySQL / phpMyAdmin)

Recomendado si ya tienes instalada la suite completa de XAMPP:

1. **Ubicación del Código:**
   * Copia o clona la carpeta `asistencia` dentro del directorio `htdocs` de XAMPP:
     * En Windows: `C:\xampp\htdocs\asistencia`
     * En Linux: `/opt/lampp/htdocs/asistencia`
2. **Iniciar Servicios:**
   * Abre el Panel de Control de XAMPP e inicia **Apache** y **MySQL**.
3. **Importar la Base de Datos:**
   * Abre tu navegador web e ingresa a `http://localhost/phpmyadmin`.
   * En la pestaña **Importar**, selecciona el archivo [`database/database.sql`](../database/database.sql) y pulsa **Continuar**. Se creará la base de datos `asistencia_qr` con todas sus tablas y datos de prueba.
4. **Acceso:**
   * Desde tu computadora: `http://localhost/asistencia/`
   * Desde un teléfono móvil (misma red Wi-Fi): `http://<IP_LOCAL>/asistencia/` *(ejemplo: `http://192.168.1.15/asistencia/`)*.

---

### Método 2: Con MySQL Workbench + PHP Standalone (Sin XAMPP)

Recomendado si tienes el motor MySQL oficial con MySQL Workbench instalado independientemente:

1. **Importar la Base de Datos en MySQL Workbench:**
   * Abre **MySQL Workbench** y conéctate a tu instancia local (`localhost:3306`).
   * Abre el archivo [`database/database.sql`](../database/database.sql) (*File -> Open SQL Script*).
   * Ejecuta el script completo (ícono de rayo). En el panel *SCHEMAS*, actualiza y verás la base de datos `asistencia_qr` lista.
2. **Configuración de Contraseña ([`config/Database.php`](../config/Database.php)):**
   * El sistema viene preconfigurado con la clave `12345`. Si tu usuario `root` de MySQL tiene otra contraseña, configúrala en la línea 18 de `config/Database.php`.
3. **Instalar PHP en Windows (si no lo tienes):**
   * Descarga PHP 8.2 o 8.3 x64 desde [windows.php.net](https://windows.php.net/download/) y descomprímelo en `C:\php`.
   * En `C:\php\php.ini`, habilita:
     ```ini
     extension_dir = "C:\php\ext"
     extension=pdo_mysql
     extension=mbstring
     ```
   * Agrega `C:\php` a tu variable de entorno `PATH`.
4. **Iniciar el Servidor Web:**
   * Abre PowerShell o CMD en la carpeta del proyecto y ejecuta:
     ```powershell
     php -S 0.0.0.0:8085 -t public public/index.php
     ```
5. **Acceso:**
   * Desde tu computadora: `http://localhost:8085/`
   * Desde un teléfono móvil (misma red Wi-Fi): `http://<IP_LOCAL>:8085/` *(ejemplo: `http://192.168.1.15:8085/`)*.

---

## 3. Resolución de Incidencias Comunes (Troubleshooting)

| Problema | Causa | Solución |
|---|---|---|
| `could not find driver` en PDO | Extensión MySQL deshabilitada | En `php.ini`, asegúrate de tener `extension_dir = "C:\php\ext"` y `extension=pdo_mysql` sin punto y coma al inicio. |
| `Access denied for user 'root'@'localhost'` | Contraseña de MySQL incorrecta | Edita `config/Database.php` y coloca en `$pass` la contraseña exacta de tu usuario `root` de MySQL Workbench (ej. `'12345'`). |
| `Address already in use` | Puerto ocupado por otro servicio | Cambia el puerto en el comando PHP: `php -S 0.0.0.0:8090 -t public public/index.php`. |
| El celular no conecta a la página | Bloqueo de Firewall de Windows | Permite el tráfico entrante para el puerto en el Firewall de Windows y confirma que el celular y la PC estén en la misma red Wi-Fi. |

---

## 4. Manual de Usuario Rápido

### 4.1. Para Administradores:
1. **Inicio de Sesión:** Accede a `/login` con las credenciales:
   * **Administrador General:** `admin` / Clave: `admin123`
2. **Supervisión en Vivo:** En `/admin`, supervisa las métricas de concurrencia diaria, distribución por carrera y el panel de clases activas en tiempo real en toda la institución.
3. **Cierre Forzoso:** Si un docente olvidó cerrar su clase, presiona **Finalizar Clase** desde la tabla en vivo para cortar el flujo de asistencias.
4. **Directorio de Personal:** En `/admin/docentes`, registra nuevos profesores o administradores, edita sus datos, activa o suspende cuentas y restablece contraseñas con cifrado Bcrypt.
5. **Auditoría y Reportes Globales:** En `/reportes`, visualiza la asistencia de cualquier docente de la institución o de todos en conjunto, con desglose por carrera y descarga en CSV, Excel o PDF oficial membretado.

### 4.2. Para Docentes:
1. **Inicio de Sesión:** Accede a `/login` con las credenciales:
   * **Docente Titular:** `profesor` / Clave: `12345`
   * **Docente Demo:** `Demo` / Clave: `Demo123`
2. **Generar Clase:** Selecciona Carrera, Nivel, pulsa sobre una materia sugerida y presiona **Generar Código QR de Asistencia**.
3. **Modo Proyector:** Presiona el botón azul para ampliar el código QR en pantalla gigante para los estudiantes.
4. **Gestión de Estudiantes:** En `/estudiantes`, presiona `+ Registrar Nuevo Estudiante` para ver el código correlativo autogenerado (ej. `EST009`), o edita/elimina alumnos existentes.
5. **Reportes Propios:** En `/reportes`, filtra por fechas o materias y descarga los reportes de sus asignaturas.

### 4.3. Para Estudiantes:
1. **Acceso a Escaneo:** Ingresa a `/asistencia/escanear` (o escanea el QR con la cámara del celular).
2. **Reconocimiento:** Apunta al QR; la cámara detectará el código y emitirá un sonido de confirmación.
3. **Registro:** Ingresa tu código institucional (`EST001` a `EST008`) y presiona **Confirmar Mi Asistencia**.
4. **Historial:** En `/login-estudiante`, introduce tu código para consultar tu expediente y porcentaje de asistencia.

---

## 5. Guía Paso a Paso para la Demostración y Defensa del Proyecto

Esta guía está diseñada para que el grupo de estudiantes exponga el proyecto con máxima solidez técnica ante el jurado calificador:

### 5.1. Flujo de Demostración en Vivo (Paso a Paso):

1. **Introducción y Arquitectura (2 minutos):**
   * Mostrar la estructura de carpetas limpia y explicar cómo el patrón MVC separa responsabilidades: *Modelos (SQL PDO)*, *Vistas (HTML/CSS limpio)* y *Controladores (Lógica, Despacho y RBAC)*.
2. **Demostración del Rol de Administrador Institucional (2 minutos):**
   * Iniciar sesión con `admin` / `admin123`.
   * Mostrar el panel de supervisión en vivo `/admin` con indicadores de pulso de aulas activas en tiempo real.
   * Acceder a `/admin/docentes`, demostrar el alta de un nuevo profesor con validación y modal reactivo, y el reseteo de claves con Bcrypt.
3. **Demostración del Panel Docente y Proyector (3 minutos):**
   * Iniciar sesión como docente (`profesor` / `12345`).
   * Mostrar la selección inteligente de materias con chips interactivos.
   * Generar la sesión de clase y activar el **Modo Proyector** a pantalla completa con desenfoque de fondo.
4. **Demostración del Escaneo en Vivo con Móvil (3 minutos):**
   * Abrir la cámara web o del teléfono móvil conectado a la red local.
   * Apuntar al proyector: demostrar cómo el sistema detecta el QR instantáneamente.
   * Registrar al alumno `EST001`: destacar el **feedback acústico inmediato** (tono armónico de confirmación en el celular y sonido de campana institucional en el proyector del docente).
   * Mostrar cómo la **Tabla en Vivo del Docente se actualiza automáticamente** sin recargar la página gracias a la API JSON en tiempo real.
5. **Demostración de Reglas de Negocio y Seguridad (2 minutos):**
   * Intentar registrar al mismo alumno `EST001` por segunda vez: mostrar cómo el sistema y la restricción `UNIQUE` en MySQL rechazan el duplicado y emiten un tono de advertencia.
   * Demostrar que un docente normal no puede ingresar a `/admin` (redirección protegida con mensaje flash).
6. **Demostración de Reportes Multiformato (2 minutos):**
   * Ir al módulo de Reportes como administrador.
   * Filtrar por docente y carrera técnica.
   * Descargar en vivo el **CSV** (con BOM UTF-8 y columna Docente), el **Excel estructurado** con colores institucionales y el **PDF oficial membretado en A4 Landscape**.
7. **Verificación en Base de Datos (1 minuto):**
   * Abrir **MySQL Workbench** y ejecutar `SELECT * FROM docentes;` y `SELECT * FROM asistencias;` para mostrar la persistencia exacta de los datos con sus llaves foráneas y hashes Bcrypt.

---


## 6. Preguntas Frecuentes del Jurado Calificador y Cómo Responderlas

### Pregunta 1: ¿Por qué eligieron una arquitectura MVC clásica sin frameworks pesados como Laravel?
> **Respuesta Recomendada:**  
> *"Elegimos implementar un patrón MVC puro en PHP 8 nativo con PDO porque nos permite demostrar un dominio profundo de los fundamentos del desarrollo web: enrutamiento frontal, ciclo de vida de peticiones HTTP, separación de responsabilidades, seguridad criptográfica y manejo de memoria sin capas de abstracción ocultas. Esto hace que el sistema sea extremadamente ligero, de inicio instantáneo y 100% portable."*

### Pregunta 2: ¿Cómo garantizan la seguridad contra inyecciones SQL y ataques XSS?
> **Respuesta Recomendada:**  
> *"La seguridad contra inyección SQL está garantizada al 100% mediante el uso exclusivo de PDO con consultas preparadas y parámetros enlazados en todos los modelos. Para prevenir ataques Cross-Site Scripting (XSS), todas las variables que se renderizan en las vistas pasan por `htmlspecialchars` con codificación UTF-8. Además, las contraseñas de personal se almacenan con algoritmos de hashing BCRYPT mediante `password_hash()`."*

### Pregunta 3: ¿Cómo funciona el control de acceso basado en roles (RBAC)?
> **Respuesta Recomendada:**  
> *"La clase abstracta `BaseController` centraliza los métodos `verificarAdmin()` y `verificarDocente()`, validando las variables de sesión del servidor en cada petición antes de ejecutar cualquier acción. Si un usuario intenta acceder a una ruta para la que no tiene privilegios (por ejemplo, un docente intentando ingresar a `/admin`), el middleware intercepta la petición, genera un mensaje flash en sesión y lo redirige a su área permitida."*

### Pregunta 4: ¿Cómo funciona el escaneo de la cámara sin requerir una app móvil instalada?
> **Respuesta Recomendada:**  
> *"Utilizamos tecnologías web estándar modernas: la API nativa `BarcodeDetector` por hardware del navegador y la biblioteca JavaScript `jsQR` ejecutada localmente sobre un elemento `<canvas>`. Para garantizar compatibilidad universal en redes locales HTTP, incluimos un fallback con captura fotográfica nativa del sistema operativo que redimensiona dinámicamente la imagen a 800 píxeles, logrando decodificar el código QR en menos de 50 milisegundos."*

### Pregunta 5: ¿Cómo se implementó el feedback de audio sin archivos MP3 externos?
> **Respuesta Recomendada:**  
> *"Utilizamos la API nativa de JavaScript `AudioContext` (Web Audio API) para sintetizar ondas sonoras senoidales puras mediante código directamente en la tarjeta de sonido del dispositivo. Esto elimina por completo la necesidad de descargar archivos de audio externos, logrando 0 KB de sobrecarga y latencia cero."*
