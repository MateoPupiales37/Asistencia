-- =====================================================================
-- MIGRACION 7: EL TALLER, Y QUE AMBIENTES USA CADA CARRERA
--
-- Mecanica Automotriz no da clase en un aula ni en un laboratorio: trabaja
-- en el TALLER. Faltaba como ambiente, y el docente tenia que elegir
-- "Laboratorio" a falta de algo mejor, con lo que el reporte decia una cosa
-- distinta de la que pasaba.
--
-- Pero el taller no le sirve a las demas carreras: ofrecerselo a Educacion
-- Inicial solo es una opcion mas para equivocarse. Por eso los ambientes
-- dejan de ser una lista fija igual para todos y pasan a ser un dato de LA
-- CARRERA. El administrador marca cuales usa cada una y el formulario ofrece
-- solo esos.
--
-- Se hizo asi, y no escribiendo "si la carrera es Mecanica" dentro del
-- codigo, porque ese "si" habria que volver a tocarlo el dia que renombren la
-- carrera o que entre otra que tambien trabaje en taller.
--
-- Ejecutar en phpMyAdmin sobre la base asistencia_qr.
--
-- Desde la consola de Windows hace falta pedir el juego de caracteres:
--     mysql -u root --default-character-set=utf8mb4 < migracion_7_taller.sql
-- =====================================================================

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- El taller entra como ambiente posible de un curso
-- ---------------------------------------------------------------------
ALTER TABLE cursos
    MODIFY COLUMN ambiente ENUM('Aula', 'Laboratorio', 'Aula Interactiva', 'Taller')
        NOT NULL DEFAULT 'Aula';

-- ---------------------------------------------------------------------
-- Que ambientes usa cada carrera
--
-- Por defecto los tres de siempre, que es lo que hoy usan todas: asi ninguna
-- carrera pierde una opcion que ya tenia.
-- ---------------------------------------------------------------------
ALTER TABLE carreras
    ADD COLUMN IF NOT EXISTS ambientes
        SET('Aula', 'Laboratorio', 'Aula Interactiva', 'Taller')
        NOT NULL DEFAULT 'Aula,Laboratorio,Aula Interactiva'
        AFTER nombre;

-- Mecanica Automotriz suma el taller. Conserva las demas porque la teoria
-- si la da en aula: el taller se agrega, no reemplaza a nada.
UPDATE carreras
   SET ambientes = 'Aula,Laboratorio,Aula Interactiva,Taller'
 WHERE codigo = 'MEA';
