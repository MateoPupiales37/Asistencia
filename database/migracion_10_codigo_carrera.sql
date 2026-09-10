-- =====================================================================
-- MIGRACION 10: EL CODIGO DEL ESTUDIANTE LLEVA SU CARRERA
--
-- El codigo era un correlativo unico para todo el instituto: EST001, EST002,
-- EST003... Con una sola carrera daba igual; con cinco no dice nada. Al ver
-- "EST014" en una lista no hay forma de saber de quien es, y al repartir los
-- carnets o dictar codigos en clase se mezclaban unos con otros.
--
-- A partir de aqui el codigo empieza por las siglas de la carrera y numera
-- dentro de ella:
--
--     DSW-001, DSW-002   Desarrollo de Software
--     MEA-001            Mecanica Automotriz
--     END-001            Entrenamiento Deportivo
--     EST-001            Todavia sin carrera asignada
--
-- >>> ESTO CAMBIA LOS CODIGOS QUE YA EXISTEN <<<
--
-- Un alumno que se sabia de memoria "EST002" pasara a ser "DSW-002" y tendra
-- que usar el nuevo. Lo que NO cambia es su carnet QR: el token es otro dato
-- y sigue siendo el mismo, asi que los carnets ya impresos siguen sirviendo
-- para escanear (aunque conviene reimprimirlos, porque llevan el codigo
-- escrito debajo). La cedula tampoco cambia, y sigue funcionando como via de
-- respaldo para identificarse.
--
-- Ejecutar en phpMyAdmin sobre la base asistencia_qr.
--
-- Desde la consola de Windows:
--     mysql -u root --default-character-set=utf8mb4 < migracion_10_codigo_carrera.sql
-- =====================================================================

SET NAMES utf8mb4;

USE asistencia_qr;

-- ---------------------------------------------------------------------
-- Renumeracion, carrera por carrera
--
-- Se hace en DOS pasos y no en uno. El codigo es UNIQUE, y al renumerar en
-- sitio se cruzan los valores a mitad de camino: el alumno que va a ser
-- "DSW-002" choca con el que TODAVIA se llama asi. Pasando primero por un
-- nombre temporal que nadie puede tener, el choque no ocurre.
-- ---------------------------------------------------------------------

-- Paso 1: a un valor de paso, imposible de colisionar
UPDATE estudiantes SET codigo = CONCAT('~tmp~', id);

-- Paso 2: el codigo definitivo, numerado dentro de cada carrera por orden de
-- antiguedad (el id), que es el mismo orden en que se dieron de alta
UPDATE estudiantes e
  JOIN (
        SELECT x.id,
               COALESCE(c.codigo, 'EST') AS prefijo,
               ROW_NUMBER() OVER (
                   PARTITION BY COALESCE(x.carrera_id, 0)
                   ORDER BY x.id ASC
               ) AS orden
          FROM estudiantes x
          LEFT JOIN carreras c ON x.carrera_id = c.id
  ) AS nuevo ON nuevo.id = e.id
   SET e.codigo = CONCAT(nuevo.prefijo, '-', LPAD(nuevo.orden, 3, '0'));
