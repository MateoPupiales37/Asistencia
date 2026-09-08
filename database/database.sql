-- =====================================================================
-- SISTEMA DE ASISTENCIA QR - ISTPET
-- Instituto Superior Tecnologico Mayor Pedro Traversari
--
-- Este script BORRA y vuelve a crear la base de datos desde cero.
-- Importalo desde phpMyAdmin: pestaña "Importar" -> elegir este archivo.
-- =====================================================================

DROP DATABASE IF EXISTS asistencia_qr;
CREATE DATABASE asistencia_qr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asistencia_qr;

-- =====================================================================
-- USUARIOS DEL SISTEMA (Administrador y Docentes)
-- El correo institucional tiene el formato nombre.apellido@istpet.edu.ec
-- =====================================================================
CREATE TABLE usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(80)  NOT NULL,
    apellido   VARCHAR(80)  NOT NULL,
    correo     VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    rol        ENUM('docente', 'admin') NOT NULL DEFAULT 'docente',
    activo     TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MATERIAS INSTITUCIONALES
-- =====================================================================
CREATE TABLE materias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20)  NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL UNIQUE,
    activa TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- CURSOS: una materia dictada por un docente en un ambiente concreto.
-- La misma materia puede tener varios cursos (Aula, Laboratorio,
-- Aula Interactiva) y por eso el docente puede crear cursos adicionales.
-- =====================================================================
CREATE TABLE cursos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    materia_id INT NOT NULL,
    docente_id INT NOT NULL,
    ambiente   ENUM('Aula', 'Laboratorio', 'Aula Interactiva') NOT NULL DEFAULT 'Aula',
    semestre   VARCHAR(30) NOT NULL,
    paralelo   VARCHAR(5)  NOT NULL DEFAULT 'A',
    activo     TINYINT(1)  NOT NULL DEFAULT 1,
    creado_en  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_curso_materia FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
    CONSTRAINT fk_curso_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    -- Un docente no puede repetir la misma materia en el mismo ambiente, semestre y paralelo
    UNIQUE KEY curso_unico (materia_id, docente_id, ambiente, semestre, paralelo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ESTUDIANTES
-- token_qr es el contenido del carnet QR personal e irrepetible del alumno.
-- cedula es la via de respaldo: si el alumno no recuerda su codigo, se
-- identifica con su cedula, que siempre lleva encima y es irrepetible.
-- Se admite NULL porque un alumno puede darse de alta antes de aportarla;
-- MySQL permite varios NULL en una clave UNIQUE, asi que la unicidad solo
-- se exige a las cedulas que si estan cargadas.
-- =====================================================================
CREATE TABLE estudiantes (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    codigo    VARCHAR(15)  NOT NULL UNIQUE,
    cedula    VARCHAR(10)  NULL UNIQUE,
    nombre    VARCHAR(80)  NOT NULL,
    apellido  VARCHAR(80)  NOT NULL,
    semestre  VARCHAR(30)  NOT NULL,
    token_qr  CHAR(32)     NOT NULL UNIQUE,
    activo    TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SESIONES DE CLASE
-- Cada clase genera un codigo de ENTRADA valido 15 minutos.
-- El docente decide cuando generar el codigo de SALIDA (otros 15 minutos).
-- =====================================================================
CREATE TABLE sesiones (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    curso_id         INT NOT NULL,
    docente_id       INT NOT NULL,
    fecha            DATE     NOT NULL,
    hora_inicio      DATETIME NOT NULL,
    hora_fin         DATETIME NULL,
    codigo_entrada   CHAR(8)  NOT NULL UNIQUE,
    entrada_expira   DATETIME NOT NULL,
    codigo_salida    CHAR(8)  NULL UNIQUE,
    salida_expira    DATETIME NULL,
    estado           ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
    CONSTRAINT fk_sesion_curso   FOREIGN KEY (curso_id)   REFERENCES cursos(id)   ON DELETE CASCADE,
    CONSTRAINT fk_sesion_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_sesion_estado (estado, docente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ASISTENCIAS
-- La restriccion UNIQUE evita el doble registro del mismo alumno.
-- El motivo se guarda cuando el docente marca una salida anticipada.
-- =====================================================================
CREATE TABLE asistencias (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id      INT NOT NULL,
    estudiante_id  INT NOT NULL,
    hora_entrada   DATETIME NOT NULL,
    hora_salida    DATETIME NULL,
    estado         ENUM('presente', 'salio', 'salida_temprana') NOT NULL DEFAULT 'presente',
    motivo         ENUM('Emergencia medica', 'Llamado de coordinacion', 'Mal comportamiento', 'Permiso del docente', 'Otro') NULL,
    motivo_detalle VARCHAR(200) NULL,
    origen         ENUM('qr', 'manual') NOT NULL DEFAULT 'qr',
    UNIQUE KEY asistencia_unica (sesion_id, estudiante_id),
    CONSTRAINT fk_asistencia_sesion     FOREIGN KEY (sesion_id)     REFERENCES sesiones(id)    ON DELETE CASCADE,
    CONSTRAINT fk_asistencia_estudiante FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DATOS DE PRUEBA
-- =====================================================================

-- Usuarios: los hashes corresponden a las claves indicadas en el comentario
-- Admin:   admin.general@istpet.edu.ec   / admin123
-- Docente: juan.tapia@istpet.edu.ec      / docente123
-- Docente: maria.calderon@istpet.edu.ec  / docente123
INSERT INTO usuarios (nombre, apellido, correo, password, rol, activo) VALUES
('Admin',  'General',   'admin.general@istpet.edu.ec',  '$2y$10$npE.egs7lhz/izucPU38ZuBC3oUwjojUmla4.2BUWHfKAVBbVY.56', 'admin',   1),
('Juan',   'Tapia',     'juan.tapia@istpet.edu.ec',     '$2y$10$oveDuWDHxAZ/0Qdbf8bHk.RtowzL06nh192gedUZ7Klp7PzKJQJnO', 'docente', 1),
('Maria',  'Calderon',  'maria.calderon@istpet.edu.ec', '$2y$10$oveDuWDHxAZ/0Qdbf8bHk.RtowzL06nh192gedUZ7Klp7PzKJQJnO', 'docente', 1);

-- Materias de la carrera
INSERT INTO materias (codigo, nombre) VALUES
('SGBD2',  'SGBD2'),
('PROAPP', 'Programacion de Aplicaciones'),
('SISWEB', 'Sistemas Web');

-- Cursos del docente Juan Tapia (id 2): un ambiente distinto por materia
INSERT INTO cursos (materia_id, docente_id, ambiente, semestre, paralelo) VALUES
(1, 2, 'Laboratorio',      'Cuarto Semestre', 'A'),
(2, 2, 'Aula',             'Tercer Semestre', 'A'),
(3, 2, 'Aula Interactiva', 'Quinto Semestre', 'A');

-- Estudiantes de prueba con su token de carnet QR
INSERT INTO estudiantes (codigo, cedula, nombre, apellido, semestre, token_qr) VALUES
('EST001', '1701234567', 'Saul',  'Andrade',  'Cuarto Semestre', 'a1f4c7e920b8d3654af10c29e7b35d81'),
('EST002', '1712345675', 'Mateo', 'Zambrano', 'Tercer Semestre', 'b27d5e1930cf4a86bd0e73a1c852f496'),
('EST003', '0509876546', 'Pedro', 'Salazar',  'Quinto Semestre', 'c93b6a0257de1f4820ac96b3d7e15f08');

-- ---------------------------------------------------------------------
-- Historial de ejemplo: tres clases ya cerradas, una por materia,
-- para que los reportes y el grafico de pastel tengan datos desde el inicio.
-- ---------------------------------------------------------------------
INSERT INTO sesiones (curso_id, docente_id, fecha, hora_inicio, hora_fin, codigo_entrada, entrada_expira, codigo_salida, salida_expira, estado) VALUES
(1, 2, CURDATE() - INTERVAL 2 DAY, CURDATE() - INTERVAL 2 DAY + INTERVAL 8 HOUR,  CURDATE() - INTERVAL 2 DAY + INTERVAL 10 HOUR, 'A1B2C3D4', CURDATE() - INTERVAL 2 DAY + INTERVAL 8 HOUR + INTERVAL 15 MINUTE, 'D4C3B2A1', CURDATE() - INTERVAL 2 DAY + INTERVAL 10 HOUR, 'cerrada'),
(2, 2, CURDATE() - INTERVAL 1 DAY, CURDATE() - INTERVAL 1 DAY + INTERVAL 9 HOUR,  CURDATE() - INTERVAL 1 DAY + INTERVAL 11 HOUR, 'E5F6A7B8', CURDATE() - INTERVAL 1 DAY + INTERVAL 9 HOUR + INTERVAL 15 MINUTE, 'B8A7F6E5', CURDATE() - INTERVAL 1 DAY + INTERVAL 11 HOUR, 'cerrada'),
(3, 2, CURDATE(),                  CURDATE() + INTERVAL 7 HOUR,                   CURDATE() + INTERVAL 9 HOUR,                   'C9D0E1F2', CURDATE() + INTERVAL 7 HOUR + INTERVAL 15 MINUTE,                    'F2E1D0C9', CURDATE() + INTERVAL 9 HOUR,                   'cerrada');

INSERT INTO asistencias (sesion_id, estudiante_id, hora_entrada, hora_salida, estado, motivo, motivo_detalle, origen) VALUES
-- Clase de SGBD2
(1, 1, CURDATE() - INTERVAL 2 DAY + INTERVAL 8 HOUR + INTERVAL 3 MINUTE,  CURDATE() - INTERVAL 2 DAY + INTERVAL 10 HOUR, 'salio', NULL, NULL, 'qr'),
(1, 2, CURDATE() - INTERVAL 2 DAY + INTERVAL 8 HOUR + INTERVAL 6 MINUTE,  CURDATE() - INTERVAL 2 DAY + INTERVAL 10 HOUR, 'salio', NULL, NULL, 'qr'),
(1, 3, CURDATE() - INTERVAL 2 DAY + INTERVAL 8 HOUR + INTERVAL 12 MINUTE, CURDATE() - INTERVAL 2 DAY + INTERVAL 9 HOUR,  'salida_temprana', 'Emergencia medica', 'Retirado por enfermeria del instituto', 'qr'),
-- Clase de Programacion de Aplicaciones
(2, 1, CURDATE() - INTERVAL 1 DAY + INTERVAL 9 HOUR + INTERVAL 2 MINUTE,  CURDATE() - INTERVAL 1 DAY + INTERVAL 11 HOUR, 'salio', NULL, NULL, 'qr'),
(2, 2, CURDATE() - INTERVAL 1 DAY + INTERVAL 9 HOUR + INTERVAL 4 MINUTE,  CURDATE() - INTERVAL 1 DAY + INTERVAL 11 HOUR, 'salio', NULL, NULL, 'manual'),
-- Clase de Sistemas Web
(3, 2, CURDATE() + INTERVAL 7 HOUR + INTERVAL 5 MINUTE,  CURDATE() + INTERVAL 9 HOUR, 'salio', NULL, NULL, 'qr'),
(3, 3, CURDATE() + INTERVAL 7 HOUR + INTERVAL 9 MINUTE,  CURDATE() + INTERVAL 8 HOUR, 'salida_temprana', 'Mal comportamiento', 'Salida temprana por mal comportamiento en el laboratorio', 'qr');
