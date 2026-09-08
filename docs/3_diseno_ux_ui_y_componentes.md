# Documento 3: Manual de Diseño UX/UI, Componentes Interactivos y Ergonomía Móvil

**Institución:** Instituto Superior Tecnológico Mayor Pedro Traversari (ISTPET)  
**Sistema:** Control y Registro de Asistencia QR en Tiempo Real  
**Enfoque:** Experiencia de Usuario (UX), Interfaz Visual (UI), Componentes Especiales y Adaptabilidad Móvil  

---

## 1. Filosofía de Diseño y Principios Rectores

1. **Cero Fricción en el Aula:** El registro de asistencias debe ser instantáneo. Los estudiantes no deben lidiar con contraseñas complejas al momento de la marcación; solo su código institucional de matrícula.
2. **Navegación Contextual Bidireccional:** Todo formulario, modal o subpágina dispone de vías claras y visibles para retornar a su pantalla de origen (`← Volver al Panel QR`, migas de pan *breadcrumbs*, atajos de teclado `ESC`, botones de cierre `×`).
3. **Visibilidad del Estado del Sistema:** El usuario siempre sabe en qué paso se encuentra (guía visual de 3 pasos en escaneo, badges de sesión activa con animación de pulso, indicadores de actualización en vivo cada 5 segundos).
4. **Respuesta Visual y Sensorial Inmediata (Feedback):** Acciones como copiar enlace, aplicar filtros rápidos o guardar datos ofrecen respuesta instantánea (toasts flotantes, pills removibles, sintetizador de audio nativo).
5. **Diseño Móvil Primero para Alumnos:** Dado que los estudiantes utilizan sus smartphones para escanear los códigos QR, la interfaz responde con media queries adaptadas para pantallas estrechas (< 768px y < 480px).

---

## 2. Mapa de Flujos de Usuario e Interacción

```text
                                       [ / (Home) ]
                                   Identificación de Rol
                                    /                 \
                                   /                   \
                 [ Personal Académico ]              [ Estudiantes ]
                           │                                │
                 /login (Credenciales)                      │
                  /                 \                       │
          (Rol: Admin)         (Rol: Docente)               │
                │                    │                      │
         /admin (Supervisión)  /dashboard (Panel QR)   /asistencia/escanear
         ├── /admin/docentes    ├── Modo Proyector     (Cámara o Manual)
         ├── /estudiantes       ├── /estudiantes            │
         └── /reportes          └── /reportes               │
             (Globales)             (Propios)          /asistencia/registrar
                                                            │
                                                            ▼
                                                   /asistencia/resultado
                                                    (Éxito o Reintento)
                                                            │
                                                            ▼
                                                   /estudiante/portal
                                                 (Expediente Académico)
```

---

## 3. Especificación de Componentes Interactivos Especiales

### 3.1. Modo Proyector para Pantallas Gigantes de Aula
* **Ubicación:** `views/dashboard/index.php`
* **Propósito:** Permitir al docente proyectar en pantalla completa el código QR y el código manual en proyectores o pantallas gigantes sin distracciones ni barras de navegación.
* **Características:**
  * Fondo oscuro institucional con efecto *backdrop-filter: blur(8px)*.
  * Código QR ampliado de 280×280 px con borde institucional.
  * Código de acceso manual de 8 caracteres en tipografía monoespaciada gigante (2.1rem).
  * Cierre accesible mediante botón `×`, clic fuera del modal o tecla `ESC`.

### 3.2. Sugerencias Inteligentes de Asignaturas
* **Ubicación:** Formulario de inicio de clase en `views/dashboard/index.php`.
* **Comportamiento:** Al seleccionar una carrera técnica (ej. *Desarrollo de Software*, *Mecánica Automotriz*, *Educación Inicial*), el sistema genera dinámicamente *chips* interactivos (`+ Programación Web II`, `+ Bases de Datos`, etc.).
* **Beneficio:** Reduce el tiempo de tipeo del profesor a un solo clic.

### 3.3. Copiado de Enlace Directo
* **Ubicación:** Tarjeta de clase activa en el Dashboard.
* **Comportamiento:** Copia `http://<host>/asistencia/escanear?codigo=<CODIGO>` en el portapapeles mediante la API nativa de JavaScript (`navigator.clipboard`) con notificación toast flotante (`.toast-msg`).

### 3.4. Escaneo Directo con Cámara Web y Móvil (Visor HUD)
* **Ubicación:** `views/asistencia/escanear.php`
* **Propósito:** Permitir al alumno escanear el código QR directamente desde el navegador del celular o laptop sin descargar ninguna aplicación adicional de terceros.
* **Componentes y Tecnología:**
  * Visor HUD con animación de barrido láser (`.scanner-scan-bar`) y esquinas con borde institucional dorado.
  * Detección dual: `BarcodeDetector` nativo por hardware cuando está disponible, y decodificación universal mediante `jsQR` sobre canvas optimizado a 800px.
  * Fallback automático de captura fotográfica nativa (`<input type="file" capture="environment">`) para conexiones locales sin HTTPS.

### 3.5. Feedback Acústico en Tiempo Real (Web Audio API)
* **Ubicación:** `views/asistencia/resultado.php` y `views/dashboard/index.php`
* **Propósito:** Brindar retroalimentación sensorial inmediata (auditiva) en el momento de la marcación y en el proyector de clase.
* **Comportamiento:**
  * **En el celular del alumno:** Doble tono armónico ascendente (880 Hz a 1760 Hz) al confirmar la asistencia; doble tono grave (320 Hz a 240 Hz) en caso de error o duplicidad.
  * **En el proyector del docente:** Tono armónico de campana institucional (784 Hz a 1046.5 Hz) en el momento en que un nuevo alumno marca en vivo, con botón para silenciar o activar a voluntad.
  * **Tecnología:** 100% nativa con `AudioContext` de JavaScript, sin descargas de archivos de audio externos (0 KB de latencia y total compatibilidad offline).

### 3.6. Generación Correlativa Inteligente y Editable de Códigos
* **Ubicación:** `views/estudiantes/index.php` y `models/Estudiante.php`
* **Comportamiento:** Al pulsar `+ Registrar Nuevo Estudiante`, el sistema analiza los códigos existentes, calcula el correlativo disponible más alto (ej. `EST009`) y lo precarga en el campo.
* **Facilidad de Uso:** El campo es 100% editable por si se requiere un código personalizado, el cursor salta de inmediato al campo de nombres para agilizar el registro, y cuenta con un botón para autogenerar o regenerar en cualquier instante.

### 3.7. Portal Estudiantil con Métricas
* **Ubicación:** `views/estudiantes/portal.php`
* **Métricas en Tarjetas:** Total de clases asistidas, asistencias del mes actual y cantidad de materias distintas cursadas.

### 3.8. Pizarra de Supervisión Institucional en Tiempo Real (Admin)
* **Ubicación:** `views/admin/index.php`
* **Propósito:** Brindar al personal directivo visibilidad integral de todas las clases activas en la institución en el instante exacto en que ocurren.
* **Componentes:**
  * Indicador de pulso animado (`pulse-badge`) para aulas transmitiendo en vivo.
  * Tarjetas de métricas institucionales con tipografía tabular para evitar saltos de renderizado.
  * Botón de acción inmediata con confirmación modal para forzar el cierre de sesiones olvidadas.
  * Barras de progreso porcentual comparativo de asistencia acumulada por carrera técnica.

### 3.9. Directorio Interactivo de Personal Académico y Modales (Admin)
* **Ubicación:** `views/admin/docentes.php`
* **Propósito:** Administrar altas, bajas lógicas, asignación de roles y credenciales del personal docente y directivo.
* **Componentes:**
  * Modales reactivos nativos en Vanilla JS con soporte para teclado (`ESC`), cierre al clic exterior y validación inline.
  * Interruptores de estado de cuenta (*Activar / Desactivar*) con feedback inmediato.
  * Modal específico de restablecimiento de contraseña con cifrado Bcrypt.

---

## 4. Adaptabilidad Móvil y Diseño Responsivo Integral

El sistema implementa una arquitectura CSS estructurada en cascada en [`public/assets/css/style.css`](../public/assets/css/style.css) para soportar cualquier dispositivo: smartphones pequeños (320px - 375px), teléfonos modernos (390px - 430px), tablets (768px - 900px) y monitores de escritorio (1024px+).

### 4.1. Matriz de Breakpoints y Comportamiento

| Componente | Desktop (> 1024px) | Tablets (769px - 1024px) | Móviles Estándar (< 768px) | Pantallas Estrechas (< 480px) |
| :--- | :--- | :--- | :--- | :--- |
| **Barra de Navegación** | Fija a 70px, enlaces horizontales con nombre | Enlaces compactos | Auto-ajustable, enlaces con scroll táctil (`overflow-x: auto; scrollbar-width: none`), oculta nombre | Padding 10px 12px, logo 32px |
| **Panel de Administración** | Cuadrícula fluida con métricas 4 cols y 2 cols | Cuadrícula 2 columnas | 1 columna vertical apilada | Métricas en tarjetas individuales al 100% |
| **Directorio de Personal** | Tabla completa con botones agrupados | Tabla con scroll horizontal | Contenedor `.table-responsive` | Botones de acción compactos `.btn-sm` |
| **Panel Docente (Dashboard)** | Cuadrícula 2 columnas (`360px 1fr`) | Cuadrícula 2 columnas (`320px 1fr`) | Colapso a 1 columna vertical apilada | 1 columna, botones al 100% |
| **Modo Proyector de Aula** | Modal centrado de 620px con QR de 280px | Modal 560px con QR de 240px | Ancho 95vw, alto máx 90vh con scroll interno, QR autoajustable a 210px | Tipografía de código con `clamp(1.4rem, 5vw, 2.1rem)` |
| **Tablas de Datos (En vivo, CRUD, Reportes)** | Tabla completa con encabezados fijos | Tabla completa adaptable | Contenedor `.table-responsive` con scroll horizontal táctil inercial (`-webkit-overflow-scrolling: touch`) | Celdas compactas, botones de acción optimizados para dedos |
| **Tarjetas Métricas (`.stats-grid`)** | Cuadrícula de 3 a 4 columnas | Cuadrícula de 2 a 3 columnas | Cuadrícula de 1 columna vertical | Valores escalados a 1.8rem para evitar cortes numéricos |
| **Formularios de Filtros** | Cuadrícula fluida de 4 a 6 columnas horizontales | Cuadrícula de 2 a 4 columnas | 1 columna vertical apilada | Botones de acción (`Filtrar` / `Limpiar`) al 100% |
| **Tarjetas de Autenticación (`.auth-card`)** | Centrado con máx 440px y padding de 44px | Centrado con máx 440px | Ancho al 100% con padding de 26px | Asistente de credenciales apilado verticalmente |
| **Escáner HUD de Cámara** | Visor de 280px con marco láser | Visor de 280px | Visor adaptativo a 240px de altura | Campos de entrada con `font-size: 1.05rem` centrado |
| **Pantalla de Inicio (`/`)** | Cuadrícula de 2 roles con padding de 38px | 2 columnas | 1 columna vertical con padding de 26px | Título fluido escalable con `clamp(1.5rem, 5.5vw, 2rem)` |

### 4.2. Principios Técnicos de Ergonomía Táctil
1. **Prevención Global de Desbordamiento:** `html, body { width: 100%; max-width: 100%; overflow-x: hidden; }` para evitar desplazamientos horizontales accidentales en dispositivos móviles.
2. **Área Táctil Mínima (Touch Targets):** Todos los botones primarios, enlaces de menú y selectores disponen de una altura mínima de 44px a 48px con padding generoso para facilitar la pulsación con el pulgar.
3. **Tipografía Fluida y Legibilidad:** Utilización de funciones matemáticas CSS `clamp()` para asegurar que los títulos institucionales y códigos de 8 caracteres nunca se trunquen ni salten de línea de forma antiestética en teléfonos pequeños.
4. **Tablas Seguras con Scroll Inercial:** Ninguna tabla de base de datos rompe el ancho del contenedor en móviles; el usuario puede deslizar horizontalmente la tabla con fluidez nativa mientras el resto de la interfaz permanece fija y centrada.

---

## 5. Accesibilidad y Atajos de Teclado

* **Tecla Escape (`ESC`):** Cierra instantáneamente tanto el modal de creación/edición de estudiantes como el modal de Modo Proyector de aula.
* **Autofocus Inteligente:** Al abrir el modal de nuevo estudiante, el cursor se posiciona automáticamente en el campo de código; al abrir el modal de edición, en el nombre.
* **Auto-formato de Códigos:** En las pantallas de asistencia, los campos de código institucional transforman automáticamente el texto ingresado a MAYÚSCULAS y remueven espacios en blanco accidentales.
