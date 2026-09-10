-- =====================================================================
-- MIGRACION 6: PERIODO ACADEMICO, CARRERA Y JUSTIFICACIONES
--
-- Hasta ahora una materia era solo un nombre y un codigo. Eso funcionaba
-- mientras el sistema atendia a una sola carrera y a un unico periodo, pero
-- deja de funcionar en cuanto:
--
--   * La misma materia se dicta en dos carreras distintas.
--   * El instituto pasa al siguiente periodo lectivo. Sin el periodo, las
--     clases de 2026-1 y las de 2026-2 se mezclan en el mismo reporte y ya
--     no hay forma de separarlas.
--
-- A partir de aqui la materia pertenece a una CARRERA, a un SEMESTRE y a un
-- PERIODO ACADEMICO. Esos tres datos son los que la identifican: "Programacion
-- Web / Tercer Semestre / Desarrollo de Software / 2026-1" es una fila
-- distinta de la misma materia en el periodo siguiente, y por eso puede tener
-- otro docente sin que los historiales se pisen.
--
-- Se agrega ademas el JUSTIFICANTE: el respaldo (certificado medico, permiso)
-- que el docente adjunta para justificar la falta o la salida de un alumno.
--
-- Ejecutar en phpMyAdmin sobre la base asistencia_qr.
-- =====================================================================

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- CARRERAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carreras (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20)  NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    activa TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY carrera_codigo (codigo),
    UNIQUE KEY carrera_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La unica carrera que el instituto tiene cargada hoy. El administrador
-- puede crear las demas desde el panel.
INSERT INTO carreras (codigo, nombre)
SELECT 'DSW', 'Desarrollo de Software'
WHERE NOT EXISTS (SELECT 1 FROM carreras WHERE codigo = 'DSW');

-- ---------------------------------------------------------------------
-- PERIODOS ACADEMICOS
--
-- 'activo' marca cual esta en curso. El administrador elige con cual quiere
-- trabajar al entrar, y todo lo que vea despues queda acotado a ese periodo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS periodos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(40) NOT NULL COMMENT 'Como lo llama el instituto: 2026-1, Octubre 2026 - Marzo 2027',
    fecha_inicio DATE        NOT NULL,
    fecha_fin    DATE        NOT NULL,
    activo       TINYINT(1)  NOT NULL DEFAULT 1 COMMENT '0 = periodo cerrado, solo lectura',
    creado_en    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY periodo_nombre (nombre),
    INDEX idx_periodo_activo (activo, fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Periodo inicial: cubre el ano en curso para que las materias que ya
-- existen tengan a donde colgarse.
INSERT INTO periodos (nombre, fecha_inicio, fecha_fin)
SELECT CONCAT(YEAR(CURDATE()), '-1'),
       MAKEDATE(YEAR(CURDATE()), 1),
       MAKEDATE(YEAR(CURDATE()), 1) + INTERVAL 11 MONTH + INTERVAL 30 DAY
WHERE NOT EXISTS (SELECT 1 FROM periodos);

-- ---------------------------------------------------------------------
-- MATERIAS: ahora ubicadas en carrera, semestre y periodo
-- ---------------------------------------------------------------------
ALTER TABLE materias
    ADD COLUMN IF NOT EXISTS carrera_id INT         NULL DEFAULT NULL AFTER nombre,
    ADD COLUMN IF NOT EXISTS semestre   VARCHAR(30) NOT NULL DEFAULT '' AFTER carrera_id,
    ADD COLUMN IF NOT EXISTS periodo_id INT         NULL DEFAULT NULL AFTER semestre;

-- Las materias que ya existian heredan el semestre del curso donde se dicta.
-- Si una materia no tiene curso todavia, se le deja Primer Semestre para que
-- el administrador la corrija: es preferible un valor visible y editable a
-- una fila a medio llenar que rompa los desplegables.
UPDATE materias m
   SET m.semestre = COALESCE((
       SELECT c.semestre FROM cursos c
        WHERE c.materia_id = m.id
        ORDER BY c.activo DESC, c.id ASC
        LIMIT 1
   ), 'Primer Semestre')
 WHERE m.semestre = '';

UPDATE materias SET carrera_id = (SELECT id FROM carreras ORDER BY id LIMIT 1) WHERE carrera_id IS NULL;
UPDATE materias SET periodo_id = (SELECT id FROM periodos ORDER BY id LIMIT 1) WHERE periodo_id IS NULL;

-- El nombre dejo de ser unico por si solo: la misma materia se repite cada
-- periodo y puede existir en dos carreras. Lo que no puede repetirse es la
-- combinacion completa.
ALTER TABLE materias DROP INDEX IF EXISTS nombre;
ALTER TABLE materias DROP INDEX IF EXISTS codigo;

ALTER TABLE materias
    ADD UNIQUE KEY IF NOT EXISTS materia_unica (nombre, semestre, carrera_id, periodo_id),
    ADD UNIQUE KEY IF NOT EXISTS materia_codigo (codigo, periodo_id),
    ADD KEY IF NOT EXISTS idx_materia_periodo (periodo_id, semestre);

ALTER TABLE materias
    ADD CONSTRAINT fk_materia_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_materia_periodo FOREIGN KEY (periodo_id) REFERENCES periodos (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- JUSTIFICACIONES
--
-- El respaldo de una falta o de una salida: certificado medico, permiso de
-- coordinacion, calamidad domestica. El archivo NO se guarda dentro de la
-- base: aqui queda solo su nombre en disco, y el archivo vive fuera de la
-- carpeta publica para que nadie pueda abrirlo escribiendo la direccion.
--
-- Se enlaza a (sesion, estudiante) y no a la asistencia, porque lo habitual
-- es justificar a quien NO vino: en ese caso no existe ninguna fila de
-- asistencia a la que colgarse.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS justificaciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id     INT NOT NULL,
    estudiante_id INT NOT NULL,
    docente_id    INT NULL COMMENT 'Quien la registro',
    tipo          ENUM('Cita medica','Permiso institucional','Calamidad domestica','Comision academica','Otro')
                  NOT NULL DEFAULT 'Otro',
    detalle       VARCHAR(300) NULL,
    -- Datos del archivo adjunto (opcional: puede justificarse sin respaldo)
    archivo       VARCHAR(120) NULL COMMENT 'Nombre en storage/justificantes',
    archivo_nombre VARCHAR(160) NULL COMMENT 'Nombre original, para la descarga',
    archivo_tipo  VARCHAR(80)  NULL COMMENT 'application/pdf, image/jpeg...',
    archivo_peso  INT          NULL,
    creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY justificacion_unica (sesion_id, estudiante_id),
    KEY fk_justificacion_estudiante (estudiante_id),
    KEY fk_justificacion_docente (docente_id),
    CONSTRAINT fk_justificacion_sesion     FOREIGN KEY (sesion_id)     REFERENCES sesiones (id)     ON DELETE CASCADE,
    CONSTRAINT fk_justificacion_estudiante FOREIGN KEY (estudiante_id) REFERENCES estudiantes (id)  ON DELETE CASCADE,
    CONSTRAINT fk_justificacion_docente    FOREIGN KEY (docente_id)    REFERENCES usuarios (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ASISTENCIAS: nuevo estado para la salida justificada
--
-- 'salida_temprana' ya existia, pero no distinguia entre el alumno que se
-- fue por mal comportamiento y el que salio con un certificado medico. Para
-- el reporte de fin de periodo esa diferencia es justamente la que importa.
-- ---------------------------------------------------------------------
ALTER TABLE asistencias
    MODIFY COLUMN estado ENUM('presente','salio','salida_temprana','salida_justificada')
        NOT NULL DEFAULT 'presente';

-- ---------------------------------------------------------------------
-- ASISTENCIAS: la cita medica programada es su propio motivo
--
-- Antes solo existia "Emergencia medica", y el alumno que sale a una cita
-- agendada con semanas de antelacion no esta en una emergencia. Meterlos en
-- el mismo saco hacia imposible distinguir en el reporte lo previsible de lo
-- imprevisto.
-- ---------------------------------------------------------------------
ALTER TABLE asistencias
    MODIFY COLUMN motivo ENUM('Cita medica','Emergencia medica','Llamado de coordinacion',
                              'Mal comportamiento','Permiso del docente','Otro')
        DEFAULT NULL;
