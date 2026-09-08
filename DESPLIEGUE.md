# Subir el sistema a producción

Guía para poner el Sistema de Asistencia QR en un servidor real, paso a paso.

---

## Antes de empezar: qué proveedor sirve y cuál no

Mencionaste Render, Railway, Supabase y GitHub. No todos valen igual:

| Servicio | ¿Sirve? | Por qué |
|---|---|---|
| **Railway** | ✅ **Recomendado** | Corre PHP y ofrece **MySQL**, que es la base que usa el sistema. Todo en un mismo panel. |
| **Render** | ✅ Sirve | Corre PHP, pero su base gestionada es PostgreSQL: necesitarías un MySQL aparte (por ejemplo el de Railway o Clever Cloud). |
| **Supabase** | ❌ **No sirve como base** | Supabase es **PostgreSQL**, no MySQL. |
| **GitHub** | ✅ Para el código | Guarda el repositorio. GitHub Pages **no** puede ejecutar PHP. |

### Por qué Supabase no sirve aquí

No es un capricho de configuración: el sistema está escrito para MySQL y usa
cosas que PostgreSQL no entiende igual:

- Columnas `ENUM(...)` en `asistencias`, `sesiones` y `usuarios`
- Funciones `CURDATE()`, `NOW()`, `DATE_ADD(... INTERVAL ...)`, `TIMESTAMPDIFF()`
- `INSERT ... ON DUPLICATE KEY UPDATE`
- `REGEXP` en la generación de códigos de estudiante
- El driver `pdo_mysql`

Migrar a PostgreSQL significaría reescribir los modelos y volver a probarlo
todo. **Recomendación: Railway con MySQL.** Si más adelante quieres Supabase,
avísame y planificamos la migración como un trabajo aparte.

---

## Opción recomendada: Railway

### Paso 1 — Subir el código a GitHub

Desde la carpeta del proyecto:

```bash
git init
```

```bash
git add . && git commit -m "Sistema de Asistencia QR - ISTPET"
```

Crea el repositorio en GitHub (**privado**) y súbelo:

```bash
git remote add origin https://github.com/TU-USUARIO/asistencia-qr.git && git branch -M main && git push -u origin main
```

> `CREDENCIALES.md`, los respaldos de la base y los archivos subidos ya están
> en `.gitignore`, así que no salen del equipo.

### Paso 2 — Crear el proyecto en Railway

1. Entra a [railway.app](https://railway.app) y accede con GitHub.
2. **New Project → Deploy from GitHub repo** y elige tu repositorio.
3. Railway detecta PHP por el `composer.json` y lo despliega.

### Paso 3 — Añadir la base de datos MySQL

1. Dentro del proyecto: **New → Database → Add MySQL**.
2. Railway crea la base y sus variables automáticamente.

### Paso 4 — Conectar la aplicación con la base

En el servicio de la **aplicación** (no en el de la base), pestaña
**Variables**, añade:

| Variable | Valor |
|---|---|
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USER` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASS` | `${{MySQL.MYSQLPASSWORD}}` |
| `CONFIAR_EN_PROXY` | `1` |

Esa sintaxis `${{MySQL.…}}` es de Railway: enlaza el valor real sin que tengas
que copiarlo a mano.

> **`CONFIAR_EN_PROXY=1` no es opcional.** Railway habla HTTPS con el navegador
> y HTTP con PHP. Sin esta variable el sistema cree que la conexión es
> insegura, y entonces los códigos QR apuntarían a `http://`, el navegador los
> bloquearía por contenido mixto, y la cámara y la ubicación quedarían
> desactivadas.

### Paso 5 — Crear las tablas

1. Abre el servicio **MySQL** → pestaña **Data** → **Query**.
2. Copia el contenido de [`database/instalacion_completa.sql`](database/instalacion_completa.sql) y ejecútalo.

Ese archivo crea las 11 tablas con las cinco migraciones ya aplicadas y deja
**una sola cuenta**:

| Correo | Contraseña |
|---|---|
| `admin.general@istpet.edu.ec` | `Istpet2026` |

### Paso 6 — Publicar la dirección

En el servicio de la aplicación: **Settings → Networking → Generate Domain**.
Te dará algo como `https://asistencia-qr-production.up.railway.app`.

### Paso 7 — Lo primero al entrar

1. Entra con la cuenta de arriba.
2. **Cambia esa contraseña de inmediato** (Cuentas → botón `Clave` de tu fila).
3. Activa la **verificación en dos pasos** de tu cuenta.
4. Crea las cuentas de los docentes.
5. Crea las materias y asígnales docente.

---

## Qué mejora al pasar a HTTPS

En local, sobre `http://`, el navegador bloquea dos cosas por seguridad. En
producción, con HTTPS, empiezan a funcionar solas:

| Función | En local (HTTP) | En producción (HTTPS) |
|---|---|---|
| Cámara para leer el QR | Solo en `localhost` | ✅ En cualquier teléfono |
| Ubicación (geocerca de 500 m) | Bloqueada | ✅ Activa |
| Cookie de sesión protegida | No | ✅ Sí |

Es decir: **el control de distancia y el escáner del celular solo funcionan de
verdad una vez desplegado**.

---

## Alternativa: Render

Render corre PHP, pero su base gestionada es PostgreSQL, así que necesitas un
MySQL externo (puedes crear solo la base en Railway y apuntar Render ahí).

1. **New → Web Service** y conecta el repositorio.
2. **Runtime:** Docker o PHP.
3. **Start Command:**
   ```bash
   php -S 0.0.0.0:$PORT -t public public/index.php
   ```
4. En **Environment**, las mismas variables del paso 4 (con los datos de tu
   MySQL) más `CONFIAR_EN_PROXY=1`.

---

## Comprobaciones después de desplegar

Recorre esta lista una vez publicado:

- [ ] La portada abre y muestra las dos puertas
- [ ] `/acceso` **no** muestra ninguna credencial
- [ ] Entras con la cuenta de administrador
- [ ] Cambiaste la contraseña inicial
- [ ] Activaste la verificación en dos pasos
- [ ] Creas un docente y entra con su correo
- [ ] El docente abre una clase y **se ve el código QR**
- [ ] El navegador **pide permiso de ubicación** al abrir la clase
- [ ] Escaneas el QR con un celular y **abre la página** (no `localhost`)
- [ ] El celular **pide permiso de cámara** al pulsar escanear
- [ ] Un estudiante matriculado registra su asistencia
- [ ] Aparece en la lista en vivo del docente
- [ ] El QR **caduca a los 15 minutos**
- [ ] El QR de salida registra la salida
- [ ] Los reportes exportan en CSV y PDF

---

## Problemas frecuentes

| Síntoma | Causa | Solución |
|---|---|---|
| «No se pudo conectar con la base de datos» | Faltan las variables `DB_*` | Revisa el paso 4 |
| El QR apunta a `http://` o a `localhost` | Falta `CONFIAR_EN_PROXY=1` | Añádela y vuelve a desplegar |
| El celular no abre el QR | El QR se generó antes de publicar el dominio | Cierra la clase y ábrela otra vez |
| La ubicación no se pide nunca | La página no está en HTTPS | Usa el dominio `https://` que da Railway |
| «Table doesn't exist» | No se ejecutó el instalador | Repite el paso 5 |
| Pantalla en blanco | Error de PHP | Mira los *Deploy Logs* de Railway |

---

## Mantenimiento

**Actualizar el sistema:** haz `git push` y Railway vuelve a desplegar solo.

**Respaldar la base:** desde el servicio MySQL de Railway, pestaña **Data →
Backups**. Hazlo antes de cada cambio grande.

**Ver errores:** los registros quedan en `storage/logs/error.log` y en los
*Deploy Logs* del panel.
