-- =====================================================================
-- INSTALACION COMPLETA - Sistema de Asistencia QR (ISTPET)
--
-- Un solo archivo que crea TODAS las tablas ya con las cinco migraciones
-- aplicadas. Es el que se usa para montar el sistema en un servidor nuevo.
--
-- Como usarlo:
--   Local  : phpMyAdmin -> Importar -> este archivo
--   Railway: pestana Data -> Query -> pegar el contenido
--
-- Trae UNA sola cuenta, la del administrador. Los docentes, materias y
-- estudiantes se crean despues desde el propio sistema.
--
--   Correo     : admin.general@istpet.edu.ec
--   Contrasena : Istpet2026
--
--   >>> CAMBIA ESA CONTRASENA NADA MAS ENTRAR <<<
--   (Cuentas -> boton Clave de tu propia fila)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sesion_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `hora_entrada` datetime NOT NULL,
  `hora_salida` datetime DEFAULT NULL,
  `estado` enum('presente','salio','salida_temprana') NOT NULL DEFAULT 'presente',
  `motivo` enum('Emergencia medica','Llamado de coordinacion','Mal comportamiento','Permiso del docente','Otro') DEFAULT NULL,
  `motivo_detalle` varchar(200) DEFAULT NULL,
  `origen` enum('qr','manual') NOT NULL DEFAULT 'qr',
  `aprobacion` enum('aprobada','pendiente') NOT NULL DEFAULT 'aprobada',
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `distancia_metros` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asistencia_unica` (`sesion_id`,`estudiante_id`),
  KEY `fk_asistencia_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_asistencia_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asistencia_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `consentimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_persona` enum('usuario','estudiante') NOT NULL,
  `persona_id` int(11) NOT NULL,
  `version` varchar(10) NOT NULL,
  `usa_ubicacion` tinyint(1) NOT NULL DEFAULT 1,
  `usa_camara` tinyint(1) NOT NULL DEFAULT 1,
  `ip` varchar(45) DEFAULT NULL,
  `navegador` varchar(255) DEFAULT NULL,
  `aceptado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consentimiento_persona` (`tipo_persona`,`persona_id`),
  KEY `idx_consentimiento_fecha` (`aceptado_en`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `cursos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `ambiente` enum('Aula','Laboratorio','Aula Interactiva') NOT NULL DEFAULT 'Aula',
  `semestre` varchar(30) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `curso_sin_paralelo` (`materia_id`,`docente_id`,`ambiente`,`semestre`),
  KEY `fk_curso_docente` (`docente_id`),
  CONSTRAINT `fk_curso_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_curso_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `estudiantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(15) NOT NULL,
  `cedula` varchar(10) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `semestre` varchar(30) NOT NULL,
  `token_qr` char(32) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `consentimiento_en` datetime DEFAULT NULL COMMENT 'Cuando acepto el uso de su ubicacion',
  `consentimiento_version` varchar(10) DEFAULT NULL COMMENT 'Version del texto que acepto',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  UNIQUE KEY `token_qr` (`token_qr`),
  UNIQUE KEY `estudiante_cedula` (`cedula`),
  KEY `idx_estudiante_semestre` (`semestre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `expulsiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sesion_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `docente_id` int(11) DEFAULT NULL,
  `bloqueado_hasta` datetime NOT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_expulsion_sesion` (`sesion_id`),
  KEY `fk_expulsion_docente` (`docente_id`),
  KEY `idx_expulsion_vigente` (`estudiante_id`,`sesion_id`,`bloqueado_hasta`),
  CONSTRAINT `fk_expulsion_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expulsion_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_expulsion_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `materias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `matriculas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricula_unica` (`estudiante_id`,`curso_id`),
  KEY `idx_matricula_curso` (`curso_id`),
  CONSTRAINT `fk_matricula_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matricula_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `semestres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `orden` tinyint(4) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `sesiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curso_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` datetime NOT NULL,
  `hora_fin` datetime DEFAULT NULL,
  `codigo_entrada` char(8) NOT NULL,
  `entrada_expira` datetime NOT NULL,
  `codigo_salida` char(8) DEFAULT NULL,
  `salida_expira` datetime DEFAULT NULL,
  `estado` enum('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `radio_metros` int(11) NOT NULL DEFAULT 500,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_entrada` (`codigo_entrada`),
  UNIQUE KEY `codigo_salida` (`codigo_salida`),
  KEY `fk_sesion_curso` (`curso_id`),
  KEY `fk_sesion_docente` (`docente_id`),
  KEY `idx_sesion_estado` (`estado`,`docente_id`),
  KEY `idx_sesion_codigos` (`codigo_entrada`,`codigo_salida`),
  CONSTRAINT `fk_sesion_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sesion_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `solicitudes_clave` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('pendiente','atendida','rechazada') NOT NULL DEFAULT 'pendiente',
  `token` char(64) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL,
  `usado_en` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `atendida_en` datetime DEFAULT NULL,
  `atendida_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_solicitud_usuario` (`usuario_id`),
  KEY `fk_solicitud_admin` (`atendida_por`),
  KEY `idx_solicitud_estado` (`estado`,`creado_en`),
  CONSTRAINT `fk_solicitud_admin` FOREIGN KEY (`atendida_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_solicitud_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `totp_secreto` varchar(64) DEFAULT NULL,
  `totp_activado` tinyint(1) NOT NULL DEFAULT 0,
  `totp_desde` datetime DEFAULT NULL,
  `rol` enum('docente','admin') NOT NULL DEFAULT 'docente',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `consentimiento_en` datetime DEFAULT NULL COMMENT 'Cuando acepto el uso de ubicacion y camara',
  `consentimiento_version` varchar(10) DEFAULT NULL COMMENT 'Version del texto que acepto',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  KEY `idx_usuario_telefono` (`telefono`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- CUENTA INICIAL DEL ADMINISTRADOR
-- La contrasena se guarda cifrada con Bcrypt: el texto plano no existe
-- en ninguna parte de la base de datos.
-- =====================================================================
INSERT INTO usuarios (nombre, apellido, correo, password, rol, activo)
VALUES ('Admin', 'General', 'admin.general@istpet.edu.ec',
        '$2y$10$52WNvgvNqXiH0veUEIS59eDg7i48wikYnyx6N2QxQjcImSQlL5xL6', 'admin', 1)
ON DUPLICATE KEY UPDATE correo = correo;

-- =====================================================================
-- SEMESTRES DEL CATALOGO
-- =====================================================================
INSERT INTO semestres (nombre, orden, activo) VALUES
('Primer Semestre', 1, 1),
('Segundo Semestre', 2, 1),
('Tercer Semestre', 3, 1),
('Cuarto Semestre', 4, 1),
('Quinto Semestre', 5, 1),
('Sexto Semestre', 6, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);