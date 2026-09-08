-- =====================================================================
-- MIGRACION 5: CONSENTIMIENTO INFORMADO
--
-- El sistema pide al navegador la UBICACION (para la geocerca) y la CAMARA
-- (para leer el QR). Ambos son datos personales, asi que antes de activarlos
-- hay que pedir permiso de forma expresa y dejar constancia de quien acepto
-- y cuando.
--
-- Se guarda la fecha de aceptacion y la version del texto aceptado: si mas
-- adelante cambian los terminos, se puede saber quien acepto cual y volver a
-- pedirlo solo a quien haga falta.
--
-- Ejecutar en phpMyAdmin sobre la base asistencia_qr.
-- =====================================================================

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- Docentes y administradores
-- ---------------------------------------------------------------------
ALTER TABLE usuarios
    ADD COLUMN consentimiento_en      DATETIME    NULL DEFAULT NULL COMMENT 'Cuando acepto el uso de ubicacion y camara'
        AFTER activo,
    ADD COLUMN consentimiento_version VARCHAR(10) NULL DEFAULT NULL COMMENT 'Version del texto que acepto'
        AFTER consentimiento_en;

-- ---------------------------------------------------------------------
-- Estudiantes
-- El alumno no tiene cuenta: acepta en la propia pantalla de registro y la
-- constancia queda ligada a su ficha en cuanto se identifica.
-- ---------------------------------------------------------------------
ALTER TABLE estudiantes
    ADD COLUMN consentimiento_en      DATETIME    NULL DEFAULT NULL COMMENT 'Cuando acepto el uso de su ubicacion'
        AFTER activo,
    ADD COLUMN consentimiento_version VARCHAR(10) NULL DEFAULT NULL COMMENT 'Version del texto que acepto'
        AFTER consentimiento_en;

-- ---------------------------------------------------------------------
-- Registro detallado de cada aceptacion
--
-- Se conserva aparte del usuario porque es un historial: si alguien reclama
-- "yo nunca acepte que me tomaran la ubicacion", aqui esta la fecha, la
-- version del texto y desde que navegador se acepto.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS consentimientos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tipo_persona  ENUM('usuario', 'estudiante') NOT NULL,
    persona_id    INT          NOT NULL,
    version       VARCHAR(10)  NOT NULL,
    -- Que permisos abarca la aceptacion
    usa_ubicacion TINYINT(1)   NOT NULL DEFAULT 1,
    usa_camara    TINYINT(1)   NOT NULL DEFAULT 1,
    ip            VARCHAR(45)  NULL,
    navegador     VARCHAR(255) NULL,
    aceptado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consentimiento_persona (tipo_persona, persona_id),
    INDEX idx_consentimiento_fecha (aceptado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
