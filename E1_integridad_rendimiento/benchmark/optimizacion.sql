-- ---------------------------------------------------------------
-- optimizacion.sql · Comic House
-- Indices e integridad aplicados tras la evidencia del benchmark.
-- Ejecutar:
--   docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
--     comic_house < optimizacion.sql
-- ---------------------------------------------------------------

-- 1. INDICE COMPUESTO para la consulta del lector
--    "dame las paginas del capitulo N en orden"
--    Antes: idx_paginas_capitulo_id (solo capitulo_id) -> filtra pero
--           todavia necesita ordenar (filesort).
--    Ahora: (capitulo_id, orden) -> filtra Y entrega ordenado.
CREATE INDEX idx_paginas_cap_orden ON paginas (capitulo_id, orden);

-- 2. UNICIDAD: dos paginas no pueden ocupar la misma posicion
--    dentro del mismo capitulo.
ALTER TABLE paginas
    ADD CONSTRAINT uq_paginas_cap_orden UNIQUE (capitulo_id, orden);

-- 3. INDICE para el listado de capitulos de un comic, ordenado.
CREATE INDEX idx_capitulos_comic ON capitulos (comic_id, id);

-- 4. INTEGRIDAD REFERENCIAL
--    NOTA: fk_paginas_capitulo y fk_capitulos_comic YA EXISTEN en el
--    esquema original del proyecto (database/init/01_schema.sql).
--    No se recrean aqui: intentarlo devuelve errno 121.
--    Se dejan documentadas porque son parte del modelo de integridad:
--
--      paginas.capitulo_id  -> capitulos.id
--      capitulos.comic_id   -> comics.id
--
--    Verificar con:
--      SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME
--      FROM information_schema.KEY_COLUMN_USAGE
--      WHERE TABLE_SCHEMA='comic_house' AND REFERENCED_TABLE_NAME IS NOT NULL;

-- 5. CHECK: el orden de pagina es positivo.
ALTER TABLE paginas
    ADD CONSTRAINT ck_paginas_orden CHECK (orden > 0);

-- Verificacion
SHOW INDEX FROM paginas;
