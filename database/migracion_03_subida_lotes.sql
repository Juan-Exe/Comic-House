-- Recibos de subida: el mismo intento puede recuperarse tras perder la respuesta.
-- NULL conserva el comportamiento de capítulos anteriores y subidas individuales.
ALTER TABLE capitulos
  ADD COLUMN IF NOT EXISTS subida_clave CHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS archivo_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  ADD UNIQUE INDEX IF NOT EXISTS uq_capitulos_subida_clave (subida_clave);
