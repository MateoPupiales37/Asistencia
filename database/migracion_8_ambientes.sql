-- =====================================================================
-- MIGRACION 8: PISCINA Y AREA DEPORTIVA, Y EL REPARTO POR CARRERA
--
-- Entrenamiento Deportivo no da clase en un laboratorio: entrena en la
-- PISCINA y en el AREA DEPORTIVA. Faltaban las dos, y sin ellas esas clases
-- se registraban como "Aula" o "Laboratorio", con lo que el reporte decia
-- una cosa distinta de la que pasaba.
--
-- Se aprovecha para dejar el reparto tal como lo usa el instituto. Cada
-- carrera ve SOLO sus ambientes: ofrecerle la piscina a Educacion Inicial, o
-- el taller a Diseno, es una opcion mas para equivocarse al asignar.
--
--   Desarrollo de Software  : Aula, Aula Interactiva, Laboratorio
--   Mecanica Automotriz     : Aula, Aula Interactiva, Laboratorio, Taller
--   Diseno Grafico          : Aula, Aula Interactiva, Laboratorio
--   Entrenamiento Deportivo : Aula, Piscina, Area Deportiva
--   Educacion Inicial       : Aula, Aula Interactiva
--
-- Ejecutar en phpMyAdmin sobre la base asistencia_qr.
--
-- Desde la consola de Windows:
--     mysql -u root --default-character-set=utf8mb4 < migracion_8_ambientes.sql
--
-- El SET NAMES de abajo ya protege el archivo aunque se olvide la opcion:
-- sin el, "Area Deportiva" entraria con la A partida.
-- =====================================================================

SET NAMES utf8mb4;

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- Los dos ambientes nuevos
-- ---------------------------------------------------------------------
ALTER TABLE cursos
    MODIFY COLUMN ambiente
        ENUM('Aula', 'Aula Interactiva', 'Laboratorio', 'Taller', 'Piscina', 'Área Deportiva')
        NOT NULL DEFAULT 'Aula';

ALTER TABLE carreras
    MODIFY COLUMN ambientes
        SET('Aula', 'Aula Interactiva', 'Laboratorio', 'Taller', 'Piscina', 'Área Deportiva')
        NOT NULL DEFAULT 'Aula,Aula Interactiva,Laboratorio';

-- ---------------------------------------------------------------------
-- El reparto que usa el instituto
-- ---------------------------------------------------------------------
UPDATE carreras SET ambientes = 'Aula,Aula Interactiva,Laboratorio'          WHERE codigo = 'DSW';
UPDATE carreras SET ambientes = 'Aula,Aula Interactiva,Laboratorio,Taller'   WHERE codigo = 'MEA';
UPDATE carreras SET ambientes = 'Aula,Aula Interactiva,Laboratorio'          WHERE codigo = 'DIG';
UPDATE carreras SET ambientes = 'Aula,Piscina,Área Deportiva'                WHERE codigo = 'END';
UPDATE carreras SET ambientes = 'Aula,Aula Interactiva'                      WHERE codigo = 'EDI';
