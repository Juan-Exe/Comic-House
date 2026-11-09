-- ---------------------------------------------------------------
-- momento2_registro_comic.sql · Comic House
--
-- El registro de cómics admitía cualquier cosa: `capitulos` es texto
-- libre y permitía '15151515'; `anio` aceptaba 45514; `clasificacion`
-- y `genero` eran campos abiertos donde cada quien escribía a su
-- manera ('ciencia ficción ' con espacio, 'Fantasia oscura' sin
-- tilde).
--
-- Aquí se estructura lo que el formulario pedía como texto:
--
--     capitulos '1 - 380'  ->  capitulo_desde 1, capitulo_hasta 380
--     capitulos '6'        ->  capitulo_desde 1, capitulo_hasta 6
--     'Tomo Unico'         ->  estado 'Tomo unico'
--
-- Ejecutar:
--   docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
--     comic_house < momento2_registro_comic.sql
-- ---------------------------------------------------------------

-- ===============================================================
-- 1. RANGO DE CAPITULOS ESTRUCTURADO
--    `capitulos` se conserva como texto mostrable, pero deja de ser
--    la fuente de verdad.
-- ===============================================================
ALTER TABLE comics
  ADD COLUMN capitulo_desde SMALLINT UNSIGNED NOT NULL DEFAULT 1
      COMMENT 'primer numero publicado, casi siempre 1',
  ADD COLUMN capitulo_hasta SMALLINT UNSIGNED DEFAULT NULL
      COMMENT 'ultimo numero; NULL mientras la obra siga en emision';

-- 1a. Rangos: '1 - 380'
UPDATE comics
   SET capitulo_desde = CAST(REGEXP_SUBSTR(capitulos, '[0-9]+') AS UNSIGNED),
       capitulo_hasta = CAST(REGEXP_SUBSTR(
                             REGEXP_SUBSTR(capitulos, '-[^0-9]*[0-9]+'),
                             '[0-9]+') AS UNSIGNED)
 WHERE capitulos REGEXP '[0-9]+[^0-9]*-[^0-9]*[0-9]+';

-- 1b. Un solo número ('6'): va del 1 a ese número.
UPDATE comics
   SET capitulo_desde = 1,
       capitulo_hasta = CAST(REGEXP_SUBSTR(capitulos, '[0-9]+') AS UNSIGNED)
 WHERE capitulos REGEXP '[0-9]'
   AND capitulos NOT REGEXP '[0-9]+[^0-9]*-[^0-9]*[0-9]+';

-- ===============================================================
-- 2. ESTADO DE PUBLICACION
--    Faltaba el caso del tomo unico, que no es una serie en curso
--    ni una terminada: es una obra cerrada de una sola entrega.
-- ===============================================================
ALTER TABLE comics
  MODIFY estado_publicacion
    ENUM('En publicacion', 'Publicacion Terminada', 'Tomo unico')
    NOT NULL DEFAULT 'En publicacion';

-- Cyberpunk es un tomo unico: una sola entrega cerrada.
UPDATE comics
   SET estado_publicacion = 'Tomo unico'
 WHERE capitulo_hasta = 1 AND capitulo_desde = 1;

-- Una obra en emision no tiene ultimo numero conocido.
UPDATE comics
   SET capitulo_hasta = NULL
 WHERE estado_publicacion = 'En publicacion';

-- ===============================================================
-- 3. LIMPIEZA DE LOS CAMPOS DE TEXTO
--    Se normaliza el formato, sin inventar valores.
-- ===============================================================
UPDATE comics SET genero = TRIM(genero) WHERE genero <> TRIM(genero);
UPDATE comics SET clasificacion = TRIM(clasificacion)
 WHERE clasificacion <> TRIM(clasificacion);

-- ===============================================================
-- 4. REGLAS QUE LA BASE HACE CUMPLIR
-- ===============================================================

-- El primer capitulo existe y no es un cero de mas.
ALTER TABLE comics
  ADD CONSTRAINT ck_comics_cap_desde CHECK (capitulo_desde >= 1);

-- Si se conoce el final, no puede ser anterior al principio.
ALTER TABLE comics
  ADD CONSTRAINT ck_comics_cap_rango
  CHECK (capitulo_hasta IS NULL OR capitulo_hasta >= capitulo_desde);

-- Cuatro digitos: hay obras de mas de mil capitulos.
ALTER TABLE comics
  ADD CONSTRAINT ck_comics_cap_tope
  CHECK (capitulo_hasta IS NULL OR capitulo_hasta <= 9999);

-- Una obra terminada o un tomo unico tienen final conocido.
ALTER TABLE comics
  ADD CONSTRAINT ck_comics_estado_coherente CHECK (
      estado_publicacion = 'En publicacion' OR capitulo_hasta IS NOT NULL
  );

-- ===============================================================
-- Verificacion
-- ===============================================================
SELECT id, titulo, capitulos AS texto_viejo,
       capitulo_desde, capitulo_hasta, estado_publicacion, genero
  FROM comics ORDER BY id;
