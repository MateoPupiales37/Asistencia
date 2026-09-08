-- =====================================================================
-- MIGRACION 4: seguridad del acceso y del registro de asistencia
--
--   1. Doble factor con Google Authenticator para docentes y admins.
--   2. Geocerca: la clase guarda DONDE se abrio, y el alumno solo puede
--      registrarse si esta dentro del radio permitido.
--   3. Expulsiones: si el docente saca a un alumno de la clase, ese alumno
--      no puede volver a registrarse en ella durante 4 horas.
--
-- Segura sobre una base con datos: no borra registros.
--     mysql -u root asistencia_qr < database/migracion_4_seguridad.sql
-- =====================================================================

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- 1. DOBLE FACTOR (TOTP / Google Authenticator)
--
--    El secreto se guarda en base32, que es el formato que entienden las
--    aplicaciones autenticadoras. Con el se generan los codigos de 6
--    digitos que cambian cada 30 segundos.
--
--    'totp_activado' distingue el secreto YA CONFIRMADO del que todavia
--    se esta configurando: mientras el usuario no demuestre que escaneo
--    bien el QR (escribiendo un codigo valido), el doble factor no se
--    exige. Si no, un error al escanear lo dejaria fuera del sistema.
-- ---------------------------------------------------------------------
ALTER TABLE usuarios
    ADD COLUMN totp_secreto  VARCHAR(64) NULL AFTER password,
    ADD COLUMN totp_activado TINYINT(1)  NOT NULL DEFAULT 0 AFTER totp_secreto,
    ADD COLUMN totp_desde    DATETIME    NULL AFTER totp_activado;

-- ---------------------------------------------------------------------
-- 2. GEOCERCA
--
--    Al abrir la clase se guardan las coordenadas del aula. El alumno que
--    escanea manda las suyas y el sistema calcula la distancia: si esta a
--    mas de 'radio_metros', no se registra.
--
--    Resuelve el caso de la foto del QR enviada por WhatsApp: quien la
--    recibe fuera del aula no puede usarla, porque no esta en el lugar.
--
--    DECIMAL(10,7) da precision de ~1 cm, mas que suficiente.
-- ---------------------------------------------------------------------
ALTER TABLE sesiones
    ADD COLUMN latitud      DECIMAL(10,7) NULL AFTER estado,
    ADD COLUMN longitud     DECIMAL(10,7) NULL AFTER latitud,
    ADD COLUMN radio_metros INT           NOT NULL DEFAULT 500 AFTER longitud;

-- Donde estaba el alumno al registrarse: queda como evidencia auditable
ALTER TABLE asistencias
    ADD COLUMN latitud          DECIMAL(10,7) NULL AFTER aprobacion,
    ADD COLUMN longitud         DECIMAL(10,7) NULL AFTER latitud,
    ADD COLUMN distancia_metros INT           NULL AFTER longitud;

-- ---------------------------------------------------------------------
-- 3. EXPULSIONES
--
--    Cuando el docente elimina a un alumno de la clase queda el registro
--    del bloqueo. Sin esto, el alumno volvia a escanear el mismo QR y se
--    registraba otra vez: el docente lo sacaba y el entraba, en bucle.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expulsiones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id       INT NOT NULL,
    estudiante_id   INT NOT NULL,
    docente_id      INT NULL,
    bloqueado_hasta DATETIME NOT NULL,
    motivo          VARCHAR(200) NULL,
    creado_en       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expulsion_sesion     FOREIGN KEY (sesion_id)     REFERENCES sesiones(id)    ON DELETE CASCADE,
    CONSTRAINT fk_expulsion_estudiante FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    CONSTRAINT fk_expulsion_docente    FOREIGN KEY (docente_id)    REFERENCES usuarios(id)    ON DELETE SET NULL,
    INDEX idx_expulsion_vigente (estudiante_id, sesion_id, bloqueado_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Los codigos QR usados no se reciclan
--
--    Ya eran UNIQUE en toda la tabla, asi que un codigo de una clase
--    pasada nunca sirve para otra. Se agrega el indice de busqueda para
--    que la comprobacion sea inmediata aunque haya miles de clases.
-- ---------------------------------------------------------------------
CREATE INDEX idx_sesion_codigos ON sesiones (codigo_entrada, codigo_salida);
