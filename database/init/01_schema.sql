SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS comics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(255) NOT NULL,
  clasificacion VARCHAR(50) NOT NULL,
  descripcion TEXT NOT NULL,
  capitulos VARCHAR(50) NOT NULL,
  anio YEAR NOT NULL,
  genero VARCHAR(100) NOT NULL,
  portada VARCHAR(255) NOT NULL,
  editorial VARCHAR(100) NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_comics_tipo (tipo),
  KEY idx_comics_genero (genero),
  KEY idx_comics_editorial (editorial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capitulos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  comic_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  numero VARCHAR(50) NOT NULL,
  fecha_publicacion DATE DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_capitulo_por_comic (comic_id, numero),
  KEY idx_capitulos_comic_id (comic_id),
  CONSTRAINT fk_capitulos_comic
    FOREIGN KEY (comic_id) REFERENCES comics (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paginas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  capitulo_id INT UNSIGNED NOT NULL,
  imagen_url VARCHAR(255) NOT NULL,
  orden INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_paginas_capitulo_id (capitulo_id),
  CONSTRAINT fk_paginas_capitulo
    FOREIGN KEY (capitulo_id) REFERENCES capitulos (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Email VARCHAR(191) NOT NULL,
  `Contraseña` VARCHAR(255) NOT NULL,
  usuario VARCHAR(100) NOT NULL,
  rol ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (Email),
  UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS biblioteca_usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(100) NOT NULL,
  comic_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_biblioteca_usuario_comic (usuario, comic_id),
  KEY idx_biblioteca_comic_id (comic_id),
  CONSTRAINT fk_biblioteca_usuario
    FOREIGN KEY (usuario) REFERENCES usuarios (usuario)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_biblioteca_comic
    FOREIGN KEY (comic_id) REFERENCES comics (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
