-- =====================================================================
-- MIGRACION 11: IDENTIFICARSE TAMBIEN CON PASAPORTE
--
-- Hasta ahora el unico documento admitido era la cedula ecuatoriana, y se
-- validaba con el algoritmo del digito verificador. Eso deja fuera a los
-- estudiantes extranjeros: no tienen cedula, su pasaporte nunca pasaba la
-- validacion y no habia forma de matricularlos ni de que se registraran.
--
-- A partir de aqui cada estudiante declara QUE documento tiene, y cada tipo
-- se valida como corresponde:
--
--     cedula     10 digitos, con el digito verificador del registro civil
--     pasaporte  6 a 15 letras y numeros, sin digito verificador
--
-- SOBRE EL NOMBRE DE LA COLUMNA
-- La columna se sigue llamando "cedula" aunque ahora tambien guarde
-- pasaportes. Renombrarla a "documento" obligaria a tocar 229 usos
-- repartidos en 17 archivos de un sistema que ya esta en produccion, y el
-- riesgo de romper algo por el camino no compensa la mejora de nombre. Lo
-- que importa es que tipo_documento dice siempre que hay dentro.
--
-- Ejecutar en phpMyAdmin sobre la base asistencia_qr.
-- =====================================================================

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- 1. Tipo de documento
--
-- Los registros que ya existen son todos cedulas ecuatorianas, asi que el
-- valor por defecto los deja correctamente clasificados sin tocar nada.
-- ---------------------------------------------------------------------
ALTER TABLE estudiantes
    ADD COLUMN tipo_documento ENUM('cedula', 'pasaporte') NOT NULL DEFAULT 'cedula'
        COMMENT 'Que documento identifica al estudiante'
        AFTER codigo;

-- ---------------------------------------------------------------------
-- 2. Espacio para el pasaporte
--
-- La cedula ocupa 10 digitos exactos, pero un pasaporte puede llegar a 15
-- caracteres alfanumericos. Con VARCHAR(10) se habrian guardado cortados
-- por la mitad, sin aviso: MySQL trunca en silencio cuando no esta en modo
-- estricto, y el alumno no habria podido volver a identificarse nunca.
-- ---------------------------------------------------------------------
ALTER TABLE estudiantes
    MODIFY COLUMN cedula VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'Numero del documento: cedula de 10 digitos o pasaporte alfanumerico';

-- ---------------------------------------------------------------------
-- 3. Busqueda por documento
--
-- Es la consulta que se hace cada vez que un alumno se registra en clase
-- escribiendo su documento. Ya existia el indice UNIQUE sobre cedula, que
-- sirve igual para buscar; este indice adicional acompaña al tipo para
-- listar rapido, por ejemplo, todos los estudiantes con pasaporte.
-- ---------------------------------------------------------------------
CREATE INDEX idx_estudiante_tipo_doc ON estudiantes (tipo_documento);

-- ---------------------------------------------------------------------
-- 4. Comprobacion
-- ---------------------------------------------------------------------
SELECT tipo_documento, COUNT(*) AS estudiantes
FROM estudiantes
GROUP BY tipo_documento;
