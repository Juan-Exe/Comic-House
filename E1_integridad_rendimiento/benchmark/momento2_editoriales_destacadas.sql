-- ---------------------------------------------------------------
-- momento2_editoriales_destacadas.sql · Comic House
--
-- La portada muestra una fila de editoriales. Mostrarlas todas la
-- rompe: con 6 registradas la sexta cae sola a una segunda linea.
-- Se separa "estar registrada" de "estar destacada" y se limita el
-- numero de destacadas a 6.
--
-- El limite se aplica en la BASE, con triggers, y no solo en PHP:
-- asi se cumple aunque la fila entre por la consola de MariaDB o por
-- otro script. Es el mismo criterio de la seccion 9.7 del informe.
--
-- Ejecutar:
--   docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
--     comic_house < momento2_editoriales_destacadas.sql
-- ---------------------------------------------------------------

-- 1. Marca de destacada y orden de aparicion en la portada.
ALTER TABLE editoriales
  ADD COLUMN destacada TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN orden_destacada INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE editoriales
  ADD CONSTRAINT ck_editoriales_destacada CHECK (destacada IN (0, 1));

-- 2. El nombre identifica a la editorial: no puede repetirse.
ALTER TABLE editoriales
  ADD CONSTRAINT uq_editoriales_nombre UNIQUE (nombre);

-- 3. Indice para la consulta de la portada:
--    "dame las destacadas en su orden".
CREATE INDEX idx_editoriales_destacadas
  ON editoriales (destacada, orden_destacada);

-- 4. Las seis actuales se destacan, conservando el orden alfabetico
--    con el que ya se mostraban.
SET @fila = 0;
UPDATE editoriales
   SET destacada = 1,
       orden_destacada = (@fila := @fila + 1)
 ORDER BY nombre ASC
 LIMIT 6;

-- ===============================================================
-- 5. LIMITE DE SEIS DESTACADAS, GARANTIZADO POR LA BASE
--    MariaDB no permite subconsultas en un CHECK, asi que la regla
--    se expresa con triggers: es la forma de que el motor la haga
--    cumplir por su cuenta.
-- ===============================================================
DELIMITER //

DROP TRIGGER IF EXISTS tg_editoriales_max_destacadas_ins//
CREATE TRIGGER tg_editoriales_max_destacadas_ins
BEFORE INSERT ON editoriales
FOR EACH ROW
BEGIN
    IF NEW.destacada = 1
       AND (SELECT COUNT(*) FROM editoriales WHERE destacada = 1) >= 6 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Maximo 6 editoriales destacadas';
    END IF;
END//

DROP TRIGGER IF EXISTS tg_editoriales_max_destacadas_upd//
CREATE TRIGGER tg_editoriales_max_destacadas_upd
BEFORE UPDATE ON editoriales
FOR EACH ROW
BEGIN
    IF NEW.destacada = 1 AND OLD.destacada = 0
       AND (SELECT COUNT(*) FROM editoriales WHERE destacada = 1) >= 6 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Maximo 6 editoriales destacadas';
    END IF;
END//

DELIMITER ;

-- Verificacion
SELECT id, nombre, destacada, orden_destacada
  FROM editoriales
 ORDER BY destacada DESC, orden_destacada ASC;
