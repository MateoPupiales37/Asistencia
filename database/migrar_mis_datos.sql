-- =====================================================================
-- MIGRACION DE DATOS: del servidor local a produccion
--
-- Lleva el catalogo academico y las personas ya cargadas en el XAMPP:
-- docentes, materias, cursos, estudiantes y matriculas.
--
-- NO copia clases ni asistencias: ese historial es de las pruebas
-- locales y no tiene sentido en el sistema real.
--
-- Las contrasenas viajan como hash Bcrypt: cada docente entra con la
-- misma clave de siempre y la contrasena nunca existe en texto legible.
--
-- Generado el 08/09/2026 07:22
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------
-- Docentes y administradores
-- ------------------------------------------------------------------
INSERT INTO usuarios (id, nombre, apellido, correo, telefono, password, rol, activo)
VALUES (1, 'Admin', 'General', 'admin.general@istpet.edu.ec', NULL, '$2y$10$npE.egs7lhz/izucPU38ZuBC3oUwjojUmla4.2BUWHfKAVBbVY.56', 'admin', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), apellido=VALUES(apellido),
  telefono=VALUES(telefono), password=VALUES(password), rol=VALUES(rol), activo=VALUES(activo);
INSERT INTO usuarios (id, nombre, apellido, correo, telefono, password, rol, activo)
VALUES (2, 'Saul', 'Pupiales', 'saul.pupiales@istpet.edu.ec', '0992931770', '$2y$10$DewD4mTWomRbwTb27UqRXugbWS06c3j4IuOjhh5gVpA2X4IwQit7a', 'docente', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), apellido=VALUES(apellido),
  telefono=VALUES(telefono), password=VALUES(password), rol=VALUES(rol), activo=VALUES(activo);
INSERT INTO usuarios (id, nombre, apellido, correo, telefono, password, rol, activo)
VALUES (6, 'Saul', 'Ceron', 'saul.ceron@istpet.edu.ec', '0992931770', '$2y$10$hbvGo3n3mRzrDMdj.BaYielw.J9TqOve.7Nr5oAP/IPMHHpJfNQZq', 'docente', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), apellido=VALUES(apellido),
  telefono=VALUES(telefono), password=VALUES(password), rol=VALUES(rol), activo=VALUES(activo);
INSERT INTO usuarios (id, nombre, apellido, correo, telefono, password, rol, activo)
VALUES (7, 'Odman', 'Alcivar', 'odman.alcivar@istpet.edu.ec', '0988110576', '$2y$10$tU.SvUaXtzEL2KYeSFXgM.sF8FUeRntW3mjvLi81aD/lesElWzszq', 'docente', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), apellido=VALUES(apellido),
  telefono=VALUES(telefono), password=VALUES(password), rol=VALUES(rol), activo=VALUES(activo);

-- ------------------------------------------------------------------
-- Semestres del catalogo
--
-- El instalador crea seis semestres de ejemplo (Primero a Sexto). Si el
-- instituto solo usa algunos, los demas sobran y ensucian todos los
-- desplegables del sistema, asi que se retiran los que no esten aqui.
-- Solo se borran los que no tengan ningun estudiante ni curso: si uno
-- esta en uso se conserva, porque eliminarlo dejaria registros huerfanos.
-- ------------------------------------------------------------------
INSERT INTO semestres (id, nombre, orden, activo) VALUES (1, 'Primer Semestre', 1, 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), orden=VALUES(orden), activo=VALUES(activo);
INSERT INTO semestres (id, nombre, orden, activo) VALUES (2, 'Segundo Semestre', 2, 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), orden=VALUES(orden), activo=VALUES(activo);
INSERT INTO semestres (id, nombre, orden, activo) VALUES (3, 'Tercer Semestre', 3, 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), orden=VALUES(orden), activo=VALUES(activo);
INSERT INTO semestres (id, nombre, orden, activo) VALUES (4, 'Cuarto Semestre', 4, 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), orden=VALUES(orden), activo=VALUES(activo);

DELETE FROM semestres
 WHERE nombre NOT IN ('Primer Semestre', 'Segundo Semestre', 'Tercer Semestre', 'Cuarto Semestre')
   AND nombre NOT IN (SELECT DISTINCT semestre FROM estudiantes)
   AND nombre NOT IN (SELECT DISTINCT semestre FROM cursos);

-- ------------------------------------------------------------------
-- Materias
-- ------------------------------------------------------------------
INSERT INTO materias (id, codigo, nombre, activa) VALUES (1, 'SGBII', 'SGBD II', 1)
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo), nombre=VALUES(nombre), activa=VALUES(activa);
INSERT INTO materias (id, codigo, nombre, activa) VALUES (2, 'PROWEB', 'Programacion Web', 1)
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo), nombre=VALUES(nombre), activa=VALUES(activa);
INSERT INTO materias (id, codigo, nombre, activa) VALUES (3, 'SGBI', 'SGBD I', 1)
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo), nombre=VALUES(nombre), activa=VALUES(activa);

-- ------------------------------------------------------------------
-- Cursos activos (materia + docente + ambiente + semestre)
-- Se omiten los archivados: eran pruebas locales.
-- ------------------------------------------------------------------
INSERT INTO cursos (id, materia_id, docente_id, ambiente, semestre, activo)
VALUES (3, 1, 2, 'Aula', 'Tercer Semestre', 1)
ON DUPLICATE KEY UPDATE docente_id=VALUES(docente_id), ambiente=VALUES(ambiente),
  semestre=VALUES(semestre), activo=1;
INSERT INTO cursos (id, materia_id, docente_id, ambiente, semestre, activo)
VALUES (4, 2, 2, 'Laboratorio', 'Tercer Semestre', 1)
ON DUPLICATE KEY UPDATE docente_id=VALUES(docente_id), ambiente=VALUES(ambiente),
  semestre=VALUES(semestre), activo=1;

-- ------------------------------------------------------------------
-- Estudiantes del padron
-- Cada uno conserva su token de carnet QR, para que los carnets ya
-- impresos sigan sirviendo.
-- ------------------------------------------------------------------
INSERT INTO estudiantes (id, codigo, cedula, telefono, nombre, apellido, semestre, token_qr, activo)
VALUES (1, 'EST001', '1753634771', NULL, 'josu', 'brito', 'Tercer Semestre', '260818b84e5f99c06145f006258aecea', 1)
ON DUPLICATE KEY UPDATE cedula=VALUES(cedula), telefono=VALUES(telefono),
  nombre=VALUES(nombre), apellido=VALUES(apellido), semestre=VALUES(semestre), activo=1;
INSERT INTO estudiantes (id, codigo, cedula, telefono, nombre, apellido, semestre, token_qr, activo)
VALUES (2, 'EST002', '1350454227', NULL, 'Odman', 'Arauz', 'Tercer Semestre', 'e7294728f13b02d8374026e001cc2543', 1)
ON DUPLICATE KEY UPDATE cedula=VALUES(cedula), telefono=VALUES(telefono),
  nombre=VALUES(nombre), apellido=VALUES(apellido), semestre=VALUES(semestre), activo=1;
INSERT INTO estudiantes (id, codigo, cedula, telefono, nombre, apellido, semestre, token_qr, activo)
VALUES (3, 'EST003', '1729127439', NULL, 'Mathias', 'Leon', 'Tercer Semestre', '14ca2d627ef5e6cc55ad2c659892fd6e', 1)
ON DUPLICATE KEY UPDATE cedula=VALUES(cedula), telefono=VALUES(telefono),
  nombre=VALUES(nombre), apellido=VALUES(apellido), semestre=VALUES(semestre), activo=1;
INSERT INTO estudiantes (id, codigo, cedula, telefono, nombre, apellido, semestre, token_qr, activo)
VALUES (4, 'EST004', '1755872544', '0992931770', 'Josue', 'Miño', 'Tercer Semestre', 'b9c1575c44dab59efa9b9ccf3e5707ab', 1)
ON DUPLICATE KEY UPDATE cedula=VALUES(cedula), telefono=VALUES(telefono),
  nombre=VALUES(nombre), apellido=VALUES(apellido), semestre=VALUES(semestre), activo=1;

-- ------------------------------------------------------------------
-- Matriculas (que estudiante pertenece a que curso)
-- ------------------------------------------------------------------
INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (3, 1);
INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (3, 2);
INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (3, 3);
INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (3, 4);
INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (4, 1);
INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (4, 2);
INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (4, 4);

SET FOREIGN_KEY_CHECKS = 1;

-- Los contadores AUTO_INCREMENT se recolocan detras de los IDs traidos,
-- para que lo que se cree despues no choque con estos registros.
ALTER TABLE usuarios     AUTO_INCREMENT = 8;
ALTER TABLE materias     AUTO_INCREMENT = 4;
ALTER TABLE cursos       AUTO_INCREMENT = 7;
ALTER TABLE estudiantes  AUTO_INCREMENT = 5;
