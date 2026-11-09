-- ---------------------------------------------------------------
-- momento2_formatos_capitulo.sql · Comic House
--
-- `capitulos.numero` era un VARCHAR(50) libre, y en él convivían tres
-- formas distintas de numerar:
--
--     '#1 - #3'      un arco de manga que recoge varios capítulos
--     '#1'           una grapa suelta de cómic
--     'Tomo Unico'   un recopilatorio
--
-- Con el número guardado como texto la base no puede comprobar nada:
-- ni que los rangos no se solapen, ni que el orden sea correcto, ni
-- que el capítulo exista dentro de la obra.
--
-- Aquí se estructura la numeración en columnas propias y `numero`
-- pasa a ser el texto mostrable, generado a partir de ellas.
--
-- Ejecutar:
--   docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
--     comic_house < momento2_formatos_capitulo.sql
-- ---------------------------------------------------------------

-- ===============================================================
-- 1. COLUMNAS DE NUMERACION
-- ===============================================================
ALTER TABLE capitulos
  ADD COLUMN formato ENUM('grapa', 'rango', 'tomo') NOT NULL DEFAULT 'grapa'
      COMMENT 'grapa: un numero; rango: de X a Y; tomo: recopilatorio',
  ADD COLUMN numero_desde INT UNSIGNED DEFAULT NULL
      COMMENT 'primer capitulo que recoge esta entrada',
  ADD COLUMN numero_hasta INT UNSIGNED DEFAULT NULL
      COMMENT 'ultimo capitulo; igual a desde en una grapa',
  ADD COLUMN arco VARCHAR(120) DEFAULT NULL
      COMMENT 'nombre del arco argumental, propio de los mangas';

-- ===============================================================
-- 2. MIGRACION DE LOS DATOS EXISTENTES
--    Se interpreta el texto actual en lugar de reescribirlo a mano.
-- ===============================================================

-- 2a. Rangos: '#1 - #3' -> desde 1, hasta 3
UPDATE capitulos
   SET formato = 'rango',
       numero_desde = CAST(REGEXP_SUBSTR(numero, '[0-9]+') AS UNSIGNED),
       numero_hasta = CAST(REGEXP_SUBSTR(
                           REGEXP_SUBSTR(numero, '-[^0-9]*[0-9]+'),
                           '[0-9]+') AS UNSIGNED)
 WHERE numero REGEXP '[0-9]+[^0-9]*-[^0-9]*[0-9]+';

-- 2b. Grapas: '#1' -> desde 1, hasta 1
UPDATE capitulos
   SET formato = 'grapa',
       numero_desde = CAST(REGEXP_SUBSTR(numero, '[0-9]+') AS UNSIGNED),
       numero_hasta = CAST(REGEXP_SUBSTR(numero, '[0-9]+') AS UNSIGNED)
 WHERE numero REGEXP '[0-9]'
   AND numero NOT REGEXP '[0-9]+[^0-9]*-[^0-9]*[0-9]+';

-- 2c. Tomos: texto sin ningun numero ('Tomo Unico')
UPDATE capitulos
   SET formato = 'tomo',
       numero_desde = 1,
       numero_hasta = 1
 WHERE numero NOT REGEXP '[0-9]';

-- 2d. El arco se toma del titulo en los mangas: alli el titulo del
--     capitulo ES el arco ('Arco del Espadachin Negro 1:').
UPDATE capitulos k
  JOIN comics c ON c.id = k.comic_id
   SET k.arco = TRIM(TRAILING ':' FROM TRIM(k.titulo))
 WHERE c.tipo = 'Manga';

-- ===============================================================
-- 3. REGLAS QUE LA BASE HACE CUMPLIR
-- ===============================================================

-- Toda entrada tiene su numeracion resuelta.
ALTER TABLE capitulos
  MODIFY numero_desde INT UNSIGNED NOT NULL,
  MODIFY numero_hasta INT UNSIGNED NOT NULL;

-- El numero de capitulo empieza en 1: un '0' suele ser un cero de mas.
ALTER TABLE capitulos
  ADD CONSTRAINT ck_capitulos_desde_positivo CHECK (numero_desde >= 1);

-- Un rango no puede terminar antes de empezar.
ALTER TABLE capitulos
  ADD CONSTRAINT ck_capitulos_rango CHECK (numero_hasta >= numero_desde);

-- Una grapa y un tomo ocupan un solo numero; el rango es para varios.
ALTER TABLE capitulos
  ADD CONSTRAINT ck_capitulos_formato CHECK (
      (formato IN ('grapa', 'tomo') AND numero_hasta = numero_desde)
      OR formato = 'rango'
  );

-- Cuatro digitos: hay obras muy largas (One Piece pasa de mil).
ALTER TABLE capitulos
  ADD CONSTRAINT ck_capitulos_tope CHECK (numero_hasta <= 9999);

-- Dos entradas de una misma obra no empiezan en el mismo capitulo.
ALTER TABLE capitulos
  ADD CONSTRAINT uq_capitulos_desde UNIQUE (comic_id, numero_desde);

-- Indice para listar en orden de lectura.
CREATE INDEX idx_capitulos_orden ON capitulos (comic_id, numero_desde);

-- ===============================================================
-- Verificacion
-- ===============================================================
SELECT c.tipo, c.titulo, k.numero, k.formato,
       k.numero_desde, k.numero_hasta, k.arco
  FROM capitulos k
  JOIN comics c ON c.id = k.comic_id
 ORDER BY c.tipo, c.titulo, k.numero_desde;
