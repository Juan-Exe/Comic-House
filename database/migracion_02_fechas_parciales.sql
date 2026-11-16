-- Se conserva DATE para fechas completas. Un mes conocido se guarda aparte,
-- sin inventar un día y sin convertir las fechas de capítulos existentes.
ALTER TABLE capitulos
  ADD COLUMN IF NOT EXISTS fecha_publicacion_mes CHAR(7) DEFAULT NULL;

ALTER TABLE capitulos
  DROP CONSTRAINT IF EXISTS ck_capitulos_fecha_precision,
  DROP CONSTRAINT IF EXISTS ck_capitulos_fecha_mes;

ALTER TABLE capitulos
  ADD CONSTRAINT ck_capitulos_fecha_precision
    CHECK (fecha_publicacion IS NULL OR fecha_publicacion_mes IS NULL),
  ADD CONSTRAINT ck_capitulos_fecha_mes CHECK (
    fecha_publicacion_mes IS NULL OR (
      fecha_publicacion_mes REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'
      AND CAST(LEFT(fecha_publicacion_mes, 4) AS UNSIGNED) >= 1900
    )
  );
