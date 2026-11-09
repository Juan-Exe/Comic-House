-- ---------------------------------------------------------------
-- momento2_formato_portada.sql · Comic House
--
-- Hasta ahora el numero de editoriales destacadas podia quedar en un
-- estado invalido (6..9) y la portada tenia que recortar. Se invierte
-- el planteamiento: PRIMERO se elige el formato (5 o 10 espacios) y la
-- base impide pasar de ese numero.
--
-- Asi no existe el estado intermedio: o caben o no caben.
--
-- Ejecutar:
--   docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
--     comic_house < momento2_formato_portada.sql
-- ---------------------------------------------------------------

-- 1. Tabla de configuracion de la aplicacion.
--    Una sola fila, asegurada con un CHECK sobre la clave primaria:
--    es el patron habitual para evitar configuraciones duplicadas.
CREATE TABLE IF NOT EXISTS configuracion (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  editoriales_formato TINYINT UNSIGNED NOT NULL DEFAULT 5,
  PRIMARY KEY (id),
  CONSTRAINT ck_config_fila_unica CHECK (id = 1),
  -- Solo 5 y 10 llenan filas completas en la portada
  -- (contenedor 1170px / logo 210px = 5 por fila).
  CONSTRAINT ck_config_formato CHECK (editoriales_formato IN (5, 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracion (id, editoriales_formato) VALUES (1, 5);

-- 2. Los triggers ahora leen el formato configurado en vez de un
--    numero fijo. La regla deja de ser "maximo 10" y pasa a ser
--    "no mas de las que caben en el formato elegido".
DROP TRIGGER IF EXISTS tg_editoriales_tope_destacadas_ins;
DROP TRIGGER IF EXISTS tg_editoriales_tope_destacadas_upd;

DELIMITER //

CREATE TRIGGER tg_editoriales_formato_ins
BEFORE INSERT ON editoriales
FOR EACH ROW
BEGIN
    DECLARE tope TINYINT UNSIGNED;
    SELECT editoriales_formato INTO tope FROM configuracion WHERE id = 1;

    IF NEW.destacada = 1
       AND (SELECT COUNT(*) FROM editoriales WHERE destacada = 1) >= tope THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'No caben mas editoriales en el formato elegido';
    END IF;
END//

CREATE TRIGGER tg_editoriales_formato_upd
BEFORE UPDATE ON editoriales
FOR EACH ROW
BEGIN
    DECLARE tope TINYINT UNSIGNED;
    SELECT editoriales_formato INTO tope FROM configuracion WHERE id = 1;

    IF NEW.destacada = 1 AND OLD.destacada = 0
       AND (SELECT COUNT(*) FROM editoriales WHERE destacada = 1) >= tope THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'No caben mas editoriales en el formato elegido';
    END IF;
END//

DELIMITER ;

-- 3. El estado actual (6 destacadas) no cabe en el formato 5.
--    Se conservan las cinco primeras por su orden; las demas dejan de
--    estar destacadas pero siguen registradas.
UPDATE editoriales
   SET destacada = 0, orden_destacada = 0
 WHERE destacada = 1
   AND orden_destacada > (SELECT editoriales_formato FROM configuracion WHERE id = 1);

-- Verificacion
SELECT editoriales_formato AS formato FROM configuracion WHERE id = 1;
SELECT nombre, destacada, orden_destacada
  FROM editoriales ORDER BY destacada DESC, orden_destacada ASC;
