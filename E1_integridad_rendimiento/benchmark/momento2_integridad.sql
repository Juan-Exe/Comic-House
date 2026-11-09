-- ---------------------------------------------------------------
-- momento2_integridad.sql · Comic House
--
-- Traslada a MariaDB las reglas que en el Momento 1 solo se habian
-- demostrado en SQLite en memoria, y atiende las tres correcciones
-- pedidas por el docente:
--
--   1. Normalizar el titulo al insertar (en la app, no con TRIM
--      dentro del CHECK) -> aqui solo se limpia lo ya almacenado.
--   2. Incluir la llave foranea hacia editoriales.
--   3. Guardar los capitulos en la base del proyecto, no en memoria.
--
-- Ejecutar:
--   docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
--     comic_house < momento2_integridad.sql
-- ---------------------------------------------------------------

-- ===============================================================
-- 1. NORMALIZACION PREVIA
--    El CHECK no puede llevar TRIM dentro: eso aceptaria el dato
--    sucio y lo disimularia al comparar. La norma se aplica al
--    escribir (controlador/registro_comcis.php) y aqui solo se
--    sanea lo que ya estaba guardado.
-- ===============================================================
UPDATE comics SET titulo = TRIM(titulo) WHERE titulo <> TRIM(titulo);

-- ===============================================================
-- 2. CLAVE FORANEA HACIA editoriales
--    La columna editorial_id existia pero estaba vacia y sin FK:
--    la integridad referencial dependia del texto libre en
--    comics.editorial. Se puebla por nombre y luego se exige.
-- ===============================================================
UPDATE comics c
  JOIN editoriales e ON e.nombre = TRIM(c.editorial)
   SET c.editorial_id = e.id
 WHERE c.editorial_id IS NULL;

ALTER TABLE comics
  MODIFY editorial_id INT UNSIGNED NOT NULL;

ALTER TABLE comics
  ADD CONSTRAINT fk_comics_editorial
  FOREIGN KEY (editorial_id) REFERENCES editoriales (id)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- ===============================================================
-- 3. UNICIDAD DEL TITULO
--    Dos obras con el mismo nombre son el mismo registro duplicado.
-- ===============================================================
ALTER TABLE comics
  ADD CONSTRAINT uq_comics_titulo UNIQUE (titulo);

-- ===============================================================
-- 4. DOMINIOS CERRADOS Y RANGOS
--    Cada CHECK corresponde a una regla del producto, no a una
--    restriccion decorativa:
--      - clasificacion: controla el filtro parental del lector.
--      - anio: un comic no puede publicarse en el futuro.
--      - destacado / imprescindible: alimentan el carrusel de portada.
-- ===============================================================
ALTER TABLE comics
  ADD CONSTRAINT ck_comics_titulo_no_vacio CHECK (CHAR_LENGTH(titulo) > 0);

ALTER TABLE comics
  ADD CONSTRAINT ck_comics_clasificacion
  CHECK (clasificacion IN ('ATP', '+13', '+16', '+18'));

ALTER TABLE comics
  ADD CONSTRAINT ck_comics_anio CHECK (anio BETWEEN 1900 AND 2026);

ALTER TABLE comics
  ADD CONSTRAINT ck_comics_tipo CHECK (tipo IN ('Comic', 'Manga'));

ALTER TABLE comics
  ADD CONSTRAINT ck_comics_destacado CHECK (destacado IN (0, 1));

ALTER TABLE comics
  ADD CONSTRAINT ck_comics_imprescindible CHECK (imprescindible IN (0, 1));

-- ===============================================================
-- 5. INDICE PARA LA CONSULTA POR EDITORIAL
--    Sustituye al indice sobre el texto libre comics.editorial.
-- ===============================================================
CREATE INDEX idx_comics_editorial_id ON comics (editorial_id);

-- ===============================================================
-- Verificacion
-- ===============================================================
SELECT CONSTRAINT_NAME, CHECK_CLAUSE
  FROM information_schema.CHECK_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = 'comic_house';

SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME
  FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA = 'comic_house'
   AND REFERENCED_TABLE_NAME IS NOT NULL;
