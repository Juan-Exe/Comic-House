-- Recibos independientes para reemplazos explícitos. No modifica capítulos existentes.
CREATE TABLE IF NOT EXISTS reemplazos_capitulo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  capitulo_id INT UNSIGNED NOT NULL,
  version_anterior CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  version_nueva CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  archivo_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  paginas INT UNSIGNED NOT NULL,
  pendientes LONGTEXT DEFAULT NULL,
  creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reemplazos_clave (clave),
  KEY idx_reemplazos_capitulo (capitulo_id),
  CONSTRAINT fk_reemplazos_capitulo FOREIGN KEY (capitulo_id) REFERENCES capitulos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
