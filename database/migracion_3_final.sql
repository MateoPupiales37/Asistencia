-- =====================================================================
-- MIGRACION 3
--
--   1. Se elimina el PARALELO. El instituto maneja un solo grupo por
--      semestre (de Primero a Cuarto), asi que pedir un paralelo era un
--      campo mas que llenar sin que aportara nada. La materia queda
--      identificada por docente + ambiente + semestre.
--
--   2. Se agrega la tabla de estudiantes pendientes de aprobacion y el
--      indice que necesita la busqueda por telefono.
--
-- Segura sobre una base con datos: no borra registros.
-- =====================================================================

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- 1. Quitar el paralelo
--    Primero cae la clave UNIQUE que lo incluye, y se recrea sin el.
-- ---------------------------------------------------------------------
-- El orden importa: MySQL usa 'curso_unico' para sostener la clave foranea
-- de materia_id, asi que no deja borrarlo mientras sea el unico indice que
-- empieza por esa columna. Se crea primero el nuevo y despues cae el viejo.
ALTER TABLE cursos
    ADD UNIQUE KEY curso_sin_paralelo (materia_id, docente_id, ambiente, semestre);

ALTER TABLE cursos DROP INDEX curso_unico;

ALTER TABLE cursos DROP COLUMN paralelo;

-- ---------------------------------------------------------------------
-- 2. Indice para buscar por telefono al enviar claves por WhatsApp
-- ---------------------------------------------------------------------
CREATE INDEX idx_usuario_telefono ON usuarios (telefono);

-- ---------------------------------------------------------------------
-- 3. Semestre del estudiante como referencia, no como muro
--    Un alumno de Tercero puede estar matriculado en una materia de
--    Cuarto (arrastre, homologacion). La matricula manda; el semestre
--    del alumno es solo informativo.
-- ---------------------------------------------------------------------
CREATE INDEX idx_estudiante_semestre ON estudiantes (semestre);
