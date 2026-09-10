-- =====================================================================
-- MIGRACION 9: EL ESTUDIANTE PERTENECE A UNA CARRERA
--
-- Hasta ahora el estudiante solo tenia SEMESTRE, no carrera. Mientras el
-- instituto atendia una sola, no se notaba; con cinco, si: al matricular en
-- un curso de Mecanica aparecian como candidatos los alumnos de Desarrollo de
-- Software, que no tienen nada que hacer ahi. El docente tenia que conocerlos
-- a todos de memoria para no marcar al que no era.
--
-- Con la carrera dentro del estudiante, la lista de candidatos se reduce sola
-- a los que de verdad pueden estar en ese curso.
--
-- Ejecutar en phpMyAdmin sobre la base asistencia_qr.
--
-- Desde la consola de Windows:
--     mysql -u root --default-character-set=utf8mb4 < migracion_9_carrera_estudiante.sql
-- =====================================================================

SET NAMES utf8mb4;

USE asistencia_qr;

ALTER TABLE estudiantes
    ADD COLUMN IF NOT EXISTS carrera_id INT NULL DEFAULT NULL AFTER apellido,
    ADD KEY IF NOT EXISTS idx_estudiante_carrera (carrera_id, semestre);

-- ---------------------------------------------------------------------
-- A los que ya existen se les deduce la carrera de los cursos donde estan
-- matriculados: es el unico dato fiable que hay sobre ellos.
--
-- Si alguno estuviera en cursos de dos carreras distintas (no deberia, pero
-- la base no lo impedia antes), se toma la del curso mas antiguo y queda para
-- que el docente lo corrija: es preferible un valor visible y editable a
-- dejarlo sin carrera y que desaparezca de todas las listas.
-- ---------------------------------------------------------------------
UPDATE estudiantes e
   SET e.carrera_id = (
       SELECT m.carrera_id
         FROM matriculas mt
         JOIN cursos   cu ON mt.curso_id  = cu.id
         JOIN materias m  ON cu.materia_id = m.id
        WHERE mt.estudiante_id = e.id AND m.carrera_id IS NOT NULL
        ORDER BY mt.id ASC
        LIMIT 1
   )
 WHERE e.carrera_id IS NULL;

ALTER TABLE estudiantes
    ADD CONSTRAINT fk_estudiante_carrera
        FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE SET NULL;
