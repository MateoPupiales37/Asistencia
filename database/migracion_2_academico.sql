-- =====================================================================
-- MIGRACION 2: gestion academica
--
-- Agrega lo necesario para:
--   1. Semestres administrables desde el panel (ya no fijos en el codigo).
--   2. Matriculas: que estudiante pertenece a que curso.
--   3. Aprobacion del docente cuando se registra alguien no matriculado.
--   4. Telefono para enviar claves y codigos por WhatsApp.
--   5. Solicitudes de clave del docente, con aviso al administrador.
--
-- Es segura sobre una base con datos: no borra nada.
-- Importala desde phpMyAdmin -> Importar, o por consola:
--     mysql -u root asistencia_qr < database/migracion_2_academico.sql
-- =====================================================================

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- 1. SEMESTRES
--    Antes eran una lista fija dentro de Catalogo.php: para agregar o
--    quitar uno habia que tocar el codigo. Ahora los administra el
--    administrador desde su panel.
--    El campo "orden" existe porque alfabeticamente quedarian mal
--    (Cuarto, Primer, Segundo, Tercer).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS semestres (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE,
    orden  TINYINT     NOT NULL DEFAULT 1,
    activo TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO semestres (nombre, orden) VALUES
('Primer Semestre',  1),
('Segundo Semestre', 2),
('Tercer Semestre',  3),
('Cuarto Semestre',  4);

-- ---------------------------------------------------------------------
-- 2. TELEFONO
--    Se guarda solo el numero, sin el codigo de pais: el sistema arma el
--    enlace de WhatsApp agregando 593 y quitando el cero inicial.
-- ---------------------------------------------------------------------
ALTER TABLE usuarios    ADD COLUMN telefono VARCHAR(15) NULL AFTER correo;
ALTER TABLE estudiantes ADD COLUMN telefono VARCHAR(15) NULL AFTER cedula;

-- ---------------------------------------------------------------------
-- 3. MATRICULAS
--    Se matricula en un CURSO, no en una materia suelta, porque el curso
--    es lo que identifica de verdad al grupo: la misma materia puede
--    dictarse en Aula y en Laboratorio, con distinto paralelo y distinto
--    docente. Matricular por materia mezclaria esos grupos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matriculas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    curso_id      INT NOT NULL,
    creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY matricula_unica (estudiante_id, curso_id),
    CONSTRAINT fk_matricula_estudiante FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    CONSTRAINT fk_matricula_curso      FOREIGN KEY (curso_id)      REFERENCES cursos(id)      ON DELETE CASCADE,
    INDEX idx_matricula_curso (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. APROBACION DE ASISTENCIA
--    Si escanea alguien que NO esta matriculado en ese curso, su registro
--    entra como 'pendiente' y aparece marcado en la lista en vivo para
--    que el docente confirme que si esta en el aula, o lo rechace.
--    Esta es la comprobacion que antes no existia: cualquiera podia
--    escribir un nombre y quedar registrado sin que nadie lo verificara.
-- ---------------------------------------------------------------------
ALTER TABLE asistencias
    ADD COLUMN aprobacion ENUM('aprobada', 'pendiente') NOT NULL DEFAULT 'aprobada' AFTER origen;

-- Todo lo que ya existia se da por aprobado
UPDATE asistencias SET aprobacion = 'aprobada';

-- ---------------------------------------------------------------------
-- 5. SOLICITUDES DE CLAVE
--    El docente que olvido su contraseña la pide desde el login. Queda
--    aqui como pendiente y le suena la campanita al administrador.
--    Cuando el admin la atiende se genera un token de un solo uso, que
--    caduca, y se le manda al docente por WhatsApp. Asi la contraseña
--    real nunca viaja escrita en un chat.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS solicitudes_clave (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT NOT NULL,
    estado       ENUM('pendiente', 'atendida', 'rechazada') NOT NULL DEFAULT 'pendiente',
    token        CHAR(64)  NULL UNIQUE,
    token_expira DATETIME  NULL,
    usado_en     DATETIME  NULL,
    creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendida_en  DATETIME  NULL,
    atendida_por INT       NULL,
    CONSTRAINT fk_solicitud_usuario FOREIGN KEY (usuario_id)   REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_admin   FOREIGN KEY (atendida_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_solicitud_estado (estado, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Matricular a los estudiantes que ya venian asistiendo
--    Se deducen de su historial: si alguien ya registro asistencia en un
--    curso, evidentemente pertenece a ese curso. Sin esto, los alumnos
--    que ya existian apareceria todos como "pendientes" de golpe.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO matriculas (estudiante_id, curso_id)
SELECT DISTINCT a.estudiante_id, s.curso_id
FROM asistencias a
JOIN sesiones s ON a.sesion_id = s.id;
