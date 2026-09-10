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
│   ├── Periodo.php             # Ciclo lectivo: separa un semestre academico del siguiente
│   ├── Carrera.php             # Carreras del instituto
│   ├── Materia.php             # Asignatura ubicada en carrera, semestre y periodo
│   ├── Curso.php               # Materia + docente + ambiente
│   ├── Estudiante.php          # Padron de alumnos, cedula y carnet QR
│   ├── Matricula.php           # Quien pertenece a cada curso, y quien falto a cada clase
│   ├── Sesion.php              # Clases, sus dos codigos QR y la geocerca
│   ├── Asistencia.php          # Entradas, salidas, motivos y filtros de reporte
│   ├── Justificacion.php       # Respaldo de una falta: certificado medico, permiso
│   └── Expulsion.php           # Bloqueo temporal del alumno retirado de una clase
├── controllers/                # CONTROLADORES
│   ├── BaseController.php      # Vistas, redirecciones, CSRF, roles e inactividad
│   ├── HomeController.php      # Portada y paginas de error
│   ├── AuthController.php      # Las dos puertas del personal y el doble factor
│   ├── DocenteController.php   # Panel de clase, QR, lista en vivo, carnets y justificantes
│   ├── AsistenciaController.php# Pantalla publica del estudiante
│   ├── ReporteController.php   # Filtros y exportacion a Excel y PDF
│   └── AdminController.php     # Periodo, carreras, materias, cuentas y supervision
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

## Como se entra

La portada reparte en **tres puertas**, y cada una lleva a donde corresponde:

| Puerta | Direccion | Quien entra |
|---|---|---|
| Estudiantes | `/asistencia` | Sin cuenta: escanea el QR y se identifica |
| Docentes | `/acceso/docente` | Correo institucional + contraseña + doble factor |
| Administracion | `/acceso/admin` | Igual, pero a su propio panel |

Cada puerta del personal solo deja pasar a su rol. Si alguien se equivoca, el
sistema se lo dice y le ofrece la correcta, pero solo **despues** de comprobar
la contraseña: decirlo antes confirmaria que ese correo existe sin necesidad de
saber la clave.

El administrador cae primero en la pantalla de **periodo academico**. Casi todo
lo que hara despues ocurre dentro de un ciclo concreto, y dar por supuesto cual
es el ciclo es como termina la malla de un periodo cargada dentro de otro.

Los **estudiantes no tienen cuenta**. Se identifican de tres formas, y las tres
exigen estar matriculados previamente por su docente:

1. **Carnet QR personal** — lo escanea y queda identificado sin escribir nada.
2. **Codigo institucional** — `EST001`, `EST002`, ...
3. **Numero de cedula** — la via de respaldo para cuando no recuerda su codigo.

> Las credenciales de las cuentas de prueba **no estan en este archivo**: viven
> en `CREDENCIALES.md`, que esta en `.gitignore` y no sale del equipo. Tampoco
> se muestran ya en las pantallas de acceso, donde antes publicaban una cuenta
> de administrador que funcionaba.

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
| Registro desde casa | Geocerca: el registro se rechaza a mas de 500 m de donde se abrio la clase |
| QR reenviado a un companero | La geocerca, mas la caducidad del codigo a los pocos minutos |
| Reutilizar el QR de otro dia | El codigo de una clase cerrada o caducada se rechaza |
| Cedula inventada | Ademas del digito verificador, tiene que estar registrada en el padron |
| Volver tras ser retirado | Bloqueo de 4 horas en esa misma clase |
| Contraseña robada | Verificacion en dos pasos (TOTP, RFC 6238) con Google Authenticator |
| Correo de otro dominio | Solo se acepta `@istpet.edu.ec`, comparando el dominio exacto |
| Certificados medicos expuestos | Los justificantes viven fuera de `public/` y se sirven por un controlador que comprueba quien los pide |

Durante el desarrollo, el modo depuracion se activa poniendo `DEPURAR = true` en
[`config/app.php`](config/app.php). **Debe quedar en `false` en produccion.**

---

## Documentacion tecnica

En [`docs/`](docs/) hay cuatro documentos de estudio y defensa:

1. [Arquitectura MVC y base de datos](docs/1_arquitectura_mvc_y_base_de_datos.md)
2. [Auditoria del codigo](docs/2_auditoria_codigo_linea_a_linea.md)
3. [Diseño UX/UI y componentes](docs/3_diseno_ux_ui_y_componentes.md)
4. [Guia de instalacion, pruebas y defensa](docs/4_guia_instalacion_pruebas_y_defensa.md)
