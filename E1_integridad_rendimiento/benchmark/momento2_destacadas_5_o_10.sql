-- ---------------------------------------------------------------
-- momento2_destacadas_5_o_10.sql · Comic House
--
-- Corrige el limite de editoriales destacadas.
--
-- POR QUE 5 O 10, Y NO "HASTA 6":
--   El contenedor de la portada mide 1170px y cada logo ocupa 210px,
--   asi que caben 1170/210 = 5,57 -> CINCO por fila. Con seis, el
--   sexto baja solo a una segunda fila y la seccion se ve coja.
--   Las unicas cantidades que llenan filas completas son 5 (una fila)
--   y 10 (dos filas). 6, 7, 8 y 9 dejan huecos.
--
-- El limite vive en la BASE, con triggers, porque es una regla del
-- producto que debe cumplirse venga el dato de donde venga.
--
-- Ejecutar:
--   docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
--     comic_house < momento2_destacadas_5_o_10.sql
-- ---------------------------------------------------------------

-- 1. Se retiran los triggers del limite anterior (maximo 6).
DROP TRIGGER IF EXISTS tg_editoriales_max_destacadas_ins;
DROP TRIGGER IF EXISTS tg_editoriales_max_destacadas_upd;

-- 2. Nuevo tope duro: nunca mas de 10 destacadas.
--    El minimo de 5 y la prohibicion de 6..9 NO se imponen por fila,
--    porque obligarian a pasar por estados intermedios invalidos al
--    ir marcando una por una. Se validan al publicar, desde la app,
--    y se avisan en el panel.
DELIMITER //

CREATE TRIGGER tg_editoriales_tope_destacadas_ins
BEFORE INSERT ON editoriales
FOR EACH ROW
BEGIN
    IF NEW.destacada = 1
       AND (SELECT COUNT(*) FROM editoriales WHERE destacada = 1) >= 10 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Maximo 10 editoriales destacadas';
    END IF;
END//

CREATE TRIGGER tg_editoriales_tope_destacadas_upd
BEFORE UPDATE ON editoriales
FOR EACH ROW
BEGIN
    IF NEW.destacada = 1 AND OLD.destacada = 0
       AND (SELECT COUNT(*) FROM editoriales WHERE destacada = 1) >= 10 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Maximo 10 editoriales destacadas';
    END IF;
END//

DELIMITER ;

-- Verificacion
SELECT COUNT(*) AS destacadas_actuales
  FROM editoriales WHERE destacada = 1;

SELECT TRIGGER_NAME
  FROM information_schema.TRIGGERS
 WHERE TRIGGER_SCHEMA = 'comic_house';
