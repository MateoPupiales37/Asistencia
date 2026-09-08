# Sistema de Asistencia QR - ISTPET (Arquitectura MVC Clasica)

Sistema web para registrar y supervisar la asistencia a clases mediante codigos QR,
construido con PHP puro sobre el patron **Modelo - Vista - Controlador (MVC)**,
sin frameworks ni dependencias externas.

Instituto Superior Tecnologico Mayor Pedro Traversari (ISTPET).

---

## Idea general

| Actor | Que hace | Necesita cuenta |
|---|---|---|
| **Estudiante** | Escanea el QR que proyecta el docente y confirma su asistencia | No |
| **Docente** | Abre la clase, proyecta el QR, controla la lista en vivo y exporta reportes | Si |
| **Administrador** | Supervisa todas las clases, administra cuentas y audita el historial | Si |

El QR de **entrada** dura 15 minutos. El docente decide cuando generar el QR de
**salida**, que dura otros 15 minutos. Esa caducidad es lo que impide que alguien
se registre desde fuera del aula con una foto del codigo.

---

## Estructura del Proyecto

```text
asistencia/
├── config/
│   ├── app.php                 # Arranque: errores, cabeceras de seguridad y URL del QR
│   └── database.php            # Conexion PDO con sentencias preparadas
├── models/                     # MODELOS: consultas SQL y reglas de datos
│   ├── Catalogo.php            # Listas fijas del sistema y validacion de cedula
│   ├── Usuario.php             # Docentes y administradores
│   ├── Materia.php             # Catalogo de asignaturas
│   ├── Curso.php               # Materia + docente + ambiente + semestre + paralelo
│   ├── Estudiante.php          # Padron de alumnos, cedula y carnet QR
│   ├── Sesion.php              # Clases y sus dos codigos QR
│   └── Asistencia.php          # Entradas, salidas, motivos y filtros de reporte
├── controllers/                # CONTROLADORES
│   ├── BaseController.php      # Vistas, redirecciones, CSRF, roles e inactividad
│   ├── HomeController.php      # Portada y paginas de error
│   ├── AuthController.php      # Acceso unico de docente y administrador
│   ├── DocenteController.php   # Panel de clase, QR, lista en vivo y carnets
│   ├── AsistenciaController.php# Pantalla publica del estudiante
│   ├── ReporteController.php   # Filtros y exportacion a CSV, Excel y PDF
│   └── AdminController.php     # Supervision institucional y cuentas
├── libs/
│   ├── QrCodigo.php            # Generador de QR en PHP puro (funciona sin internet)
│   ├── ReportePdf.php          # Reporte A4 horizontal con membrete ISTPET
│   └── fpdf/                   # FPDF 1.86 autonomo
├── views/                      # VISTAS
│   ├── layouts/                # Encabezado y pie de pagina
│   ├── home/                   # Portada
│   ├── auth/                   # Acceso institucional
│   ├── docente/                # Panel de clase y carnets QR
│   ├── asistencia/             # Formulario del estudiante y resultado
│   ├── admin/                  # Supervision y cuentas
│   ├── reportes/               # Tabla con filtros y descargas
│   ├── partials/               # Grafico de pastel en SVG
│   └── errors/                 # 404 y 405
├── public/                     # Unica carpeta visible desde el navegador
│   ├── index.php               # Front Controller (tabla de rutas)
│   ├── .htaccess               # URLs amigables
│   └── assets/                 # CSS, JS, fuentes e imagenes
├── storage/logs/               # Registro de errores (no accesible por HTTP)
├── database/
│   ├── database.sql            # Crea la base desde cero con datos de prueba
│   └── migracion_cedula.sql    # Agrega la cedula a una base que ya existe
└── docs/                       # Documentacion tecnica
```

---

## Instalacion

### Con XAMPP (Apache + MySQL)

1. Copia la carpeta `asistencia` dentro de `htdocs`
   (`C:\xampp\htdocs\asistencia` en Windows, `/opt/lampp/htdocs/asistencia` en Linux).
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Entra a phpMyAdmin (`http://localhost/phpmyadmin`), pestaña **Importar**, y carga
   [`database/database.sql`](database/database.sql). El script crea la base
   `asistencia_qr` con las tablas y los datos de prueba.
4. Abre `http://localhost/asistencia/`.

> **Si ya tenias la base creada de antes**, no importes `database.sql` (borra todo).
> Importa unicamente [`database/migracion_cedula.sql`](database/migracion_cedula.sql),
> que agrega la columna de cedula sin tocar los datos existentes.

### Sin XAMPP (PHP integrado)

```powershell
php -S 0.0.0.0:8085 -t public public/index.php
```

Luego abre `http://localhost:8085/`.

### Contraseña de MySQL

XAMPP instala MySQL con el usuario `root` y contraseña **vacia**, que es lo que el
sistema espera. Si tu servidor tiene otra clave, escribela en la constante `DB_PASS`
de [`config/database.php`](config/database.php) o define la variable de entorno `DB_PASS`.

---

## Que el celular pueda abrir el QR

Este es el punto que mas confusion genera al probar el sistema.

Si el docente entra por `http://localhost/asistencia/`, el codigo QR llevaria
"localhost" adentro, y para el celular del alumno **localhost es su propio telefono**:
el enlace nunca abriria.

El sistema lo resuelve solo: cuando detecta que se entro por `localhost`, sustituye
el host por la IP del servidor en la red local y muestra un aviso en el panel del
docente con las direcciones detectadas.

Aun asi conviene que el docente entre directamente por esa IP
(por ejemplo `http://192.168.1.38/asistencia/`), y que el celular este en la **misma
red Wi-Fi**. Si la IP fuera fija, se puede escribir de una vez en la constante
`URL_PUBLICA` de [`config/app.php`](config/app.php).

Como respaldo, cada QR muestra ademas su **codigo de 8 caracteres**, que el alumno
puede escribir a mano si la camara falla.

---

## Credenciales de prueba

El acceso es unico para docentes y administradores, con correo institucional
`nombre.apellido@istpet.edu.ec`.

| Rol | Correo | Contraseña |
|---|---|---|
| **Administrador** | `admin.general@istpet.edu.ec` | `admin123` |
| **Docente** | `juan.tapia@istpet.edu.ec` | `docente123` |
| **Docente** | `maria.calderon@istpet.edu.ec` | `docente123` |

Los **estudiantes no tienen cuenta**. Se identifican de una de estas cuatro formas,
de la mas fiable a la menos:

1. **Carnet QR personal** — lo escanea y queda identificado sin escribir nada.
2. **Codigo institucional** — `EST001`, `EST002`, ...
3. **Numero de cedula** — la via de respaldo para cuando no recuerda su codigo.
4. **Nombre, apellido y semestre** — ultimo recurso; conviene agregar la cedula.

Cedulas de los alumnos de prueba: `1701234567` (EST001), `1712345675` (EST002),
`0509876546` (EST003).

---

## Seguridad implementada

| Riesgo | Medida |
|---|---|
| Inyeccion SQL | PDO con sentencias preparadas en todas las consultas; `EMULATE_PREPARES` desactivado |
| XSS | `htmlspecialchars` en toda salida a la vista |
| CSRF | Token unico de sesion verificado con `hash_equals` en cada POST |
| Contraseñas | Bcrypt (`password_hash`), con rehash transparente |
| Fuerza bruta | Bloqueo de 2 minutos tras 5 intentos fallidos |
| Secuestro de sesion | `session_regenerate_id` al entrar, cookie `HttpOnly` + `SameSite=Lax` |
| Sesion olvidada abierta | Cierre automatico por inactividad (60 min) |
| Fuga de informacion | Los errores se guardan en `storage/logs/` y nunca se imprimen en pantalla |
| Clickjacking y sniffing | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP |
| Acceso a archivos internos | Bloqueo por `.htaccess`, independiente de `mod_rewrite` |
| Doble registro | Clave `UNIQUE (sesion_id, estudiante_id)` en la base |
| Registro fuera del aula | Los codigos QR caducan a los 15 minutos |
| Suplantacion | La cedula se valida con su digito verificador; el docente puede eliminar registros falsos |

Durante el desarrollo, el modo depuracion se activa poniendo `DEPURAR = true` en
[`config/app.php`](config/app.php). **Debe quedar en `false` en produccion.**

---

## Documentacion tecnica

En [`docs/`](docs/) hay cuatro documentos de estudio y defensa:

1. [Arquitectura MVC y base de datos](docs/1_arquitectura_mvc_y_base_de_datos.md)
2. [Auditoria del codigo](docs/2_auditoria_codigo_linea_a_linea.md)
3. [Diseño UX/UI y componentes](docs/3_diseno_ux_ui_y_componentes.md)
4. [Guia de instalacion, pruebas y defensa](docs/4_guia_instalacion_pruebas_y_defensa.md)
