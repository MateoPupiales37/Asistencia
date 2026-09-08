-- =====================================================================
-- MIGRACION: numero de cedula como identificador alternativo
--
-- Antes, el alumno que no recordaba su codigo (EST001) no tenia forma de
-- identificarse y el sistema lo daba de alta otra vez, duplicando su ficha.
-- La cedula es un dato que el alumno SIEMPRE lleva encima y es irrepetible,
-- asi que se convierte en la via de respaldo para registrarse.
--
-- Importalo desde phpMyAdmin -> Importar, o ejecutalo desde la consola:
--     mysql -u root asistencia_qr < database/migracion_cedula.sql
--
-- Es seguro ejecutarlo sobre una base que ya tiene datos: no borra nada.
-- =====================================================================

USE asistencia_qr;

-- La columna se crea NULL porque los alumnos que ya estaban registrados
-- todavia no tienen cedula cargada. MySQL permite varios NULL en una clave
-- UNIQUE, de modo que la unicidad solo se exige a las cedulas reales.
ALTER TABLE estudiantes
    ADD COLUMN cedula VARCHAR(10) NULL AFTER codigo;

ALTER TABLE estudiantes
    ADD UNIQUE KEY estudiante_cedula (cedula);

-- Cedulas de los estudiantes de prueba (validas segun el digito verificador)
UPDATE estudiantes SET cedula = '1701234567' WHERE codigo = 'EST001' AND cedula IS NULL;
UPDATE estudiantes SET cedula = '1712345675' WHERE codigo = 'EST002' AND cedula IS NULL;
UPDATE estudiantes SET cedula = '0509876546' WHERE codigo = 'EST003' AND cedula IS NULL;
