SET NAMES utf8mb4;

-- Configuracion de la aplicacion. Una sola fila, asegurada con un CHECK
-- sobre la clave primaria.
CREATE TABLE IF NOT EXISTS configuracion (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  -- Espacios de la fila de editoriales destacadas en la portada.
  editoriales_formato TINYINT UNSIGNED NOT NULL DEFAULT 5,
  PRIMARY KEY (id),
  CONSTRAINT ck_config_fila_unica CHECK (id = 1),
  CONSTRAINT ck_config_formato CHECK (editoriales_formato IN (5, 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracion (id, editoriales_formato) VALUES (1, 5);

-- Catalogo de referencia. Debe crearse antes que comics, porque
-- comics.editorial_id la referencia.
CREATE TABLE IF NOT EXISTS editoriales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  imagen VARCHAR(255) NOT NULL,
  -- Estar registrada y aparecer en la portada son cosas distintas.
  -- La portada coloca 5 logos por fila (contenedor 1170px / logo 210px),
  -- asi que solo 5 y 10 llenan filas completas.
  destacada TINYINT(1) NOT NULL DEFAULT 0,
  orden_destacada INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_editoriales_nombre (nombre),
  KEY idx_editoriales_destacadas (destacada, orden_destacada),
  CONSTRAINT ck_editoriales_destacada CHECK (destacada IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El numero de destacadas no puede superar el formato elegido en
-- configuracion (5 o 10 espacios). Se expresa con triggers porque
-- MariaDB no admite subconsultas dentro de un CHECK, y la regla debe
-- cumplirse aunque la fila entre por fuera de la aplicacion.
DELIMITER //

CREATE TRIGGER IF NOT EXISTS tg_editoriales_formato_ins
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

CREATE TRIGGER IF NOT EXISTS tg_editoriales_formato_upd
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
  editorial_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  imprescindible TINYINT(1) NOT NULL DEFAULT 0,
  estado_publicacion ENUM('En publicacion','Publicacion Terminada')
    NOT NULL DEFAULT 'En publicacion',
  imagen_slider VARCHAR(255) DEFAULT NULL,
  logo_slider VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  -- Dos obras con el mismo nombre son el mismo registro duplicado.
  UNIQUE KEY uq_comics_titulo (titulo),
  KEY idx_comics_tipo (tipo),
  KEY idx_comics_genero (genero),
  KEY idx_comics_editorial (editorial),
  KEY idx_comics_editorial_id (editorial_id),
  -- Cada CHECK corresponde a una regla del producto:
  --   clasificacion -> filtro parental del lector
  --   anio          -> un comic no se publica en el futuro
  --   destacado / imprescindible -> alimentan el carrusel de portada
  CONSTRAINT ck_comics_titulo_no_vacio CHECK (CHAR_LENGTH(titulo) > 0),
  CONSTRAINT ck_comics_clasificacion
    CHECK (clasificacion IN ('ATP','+13','+16','+18')),
  CONSTRAINT ck_comics_anio CHECK (anio BETWEEN 1900 AND 2026),
  CONSTRAINT ck_comics_tipo CHECK (tipo IN ('Comic','Manga')),
  CONSTRAINT ck_comics_destacado CHECK (destacado IN (0,1)),
  CONSTRAINT ck_comics_imprescindible CHECK (imprescindible IN (0,1)),
  CONSTRAINT fk_comics_editorial
    FOREIGN KEY (editorial_id) REFERENCES editoriales (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capitulos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  comic_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  -- Texto mostrable ('#1 - #3', 'Tomo único'); se compone a partir
  -- de las columnas de numeración, no se escribe a mano.
  numero VARCHAR(50) NOT NULL,
  fecha_publicacion DATE DEFAULT NULL,
  -- Un manga agrupa capítulos en arcos y un cómic publica grapas
  -- sueltas o recopilatorios: la numeración va estructurada para que
  -- la base pueda comprobar rangos, solapes y orden.
  formato ENUM('grapa','rango','tomo') NOT NULL DEFAULT 'grapa',
  numero_desde INT UNSIGNED NOT NULL,
  numero_hasta INT UNSIGNED NOT NULL,
  arco VARCHAR(120) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_capitulo_por_comic (comic_id, numero),
  UNIQUE KEY uq_capitulos_desde (comic_id, numero_desde),
  KEY idx_capitulos_comic_id (comic_id),
  KEY idx_capitulos_comic (comic_id, id),
  KEY idx_capitulos_orden (comic_id, numero_desde),
  CONSTRAINT ck_capitulos_desde_positivo CHECK (numero_desde >= 1),
  CONSTRAINT ck_capitulos_rango CHECK (numero_hasta >= numero_desde),
  CONSTRAINT ck_capitulos_tope CHECK (numero_hasta <= 9999),
  CONSTRAINT ck_capitulos_formato CHECK (
      (formato IN ('grapa','tomo') AND numero_hasta = numero_desde)
      OR formato = 'rango'
  ),
  CONSTRAINT fk_capitulos_comic
    FOREIGN KEY (comic_id) REFERENCES comics (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paginas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  capitulo_id INT UNSIGNED NOT NULL,
  imagen_url VARCHAR(255) NOT NULL,
  orden INT UNSIGNED DEFAULT NULL,
  es_doble TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  -- Dos paginas no pueden ocupar la misma posicion en un capitulo.
  UNIQUE KEY uq_paginas_cap_orden (capitulo_id, orden),
  KEY idx_paginas_capitulo_id (capitulo_id),
  -- Indice compuesto: filtra por capitulo Y entrega ya ordenado.
  KEY idx_paginas_cap_orden (capitulo_id, orden),
  CONSTRAINT ck_paginas_orden CHECK (orden > 0),
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

-- ---------------------------------------------------------------
-- Capitulos que no se solapan
--
-- Dos capitulos del mismo comic no pueden cubrir el mismo numero.
-- Un UNIQUE no puede expresarlo: no compara columnas de UNA fila,
-- sino el rango de la fila nueva contra el de TODAS las demas.
--
-- Comprobado antes de anadirlo: con «#1 - #3» ya guardado, la base
-- aceptaba insertar «#2 - #4» y los numeros 2 y 3 quedaban en dos
-- capitulos a la vez.
--
-- La regla ya existia en PHP (validar_capitulo.php), pero vivia SOLO
-- alli y cualquier script que insertara directo se la saltaba.
-- ---------------------------------------------------------------
DELIMITER $$

CREATE TRIGGER trg_capitulos_sin_solape_ins
BEFORE INSERT ON capitulos
FOR EACH ROW
BEGIN
    DECLARE choca VARCHAR(50);
    SELECT numero INTO choca
      FROM capitulos
     WHERE comic_id = NEW.comic_id
       AND numero_desde <= NEW.numero_hasta
       AND numero_hasta >= NEW.numero_desde
     LIMIT 1;
    IF choca IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Esos capitulos ya estan cubiertos por otro capitulo del mismo comic.';
    END IF;
END$$

-- Al editar se excluye la propia fila: si no, un UPDATE que no cambie
-- el rango chocaria consigo mismo.
CREATE TRIGGER trg_capitulos_sin_solape_upd
BEFORE UPDATE ON capitulos
FOR EACH ROW
BEGIN
    DECLARE choca VARCHAR(50);
    SELECT numero INTO choca
      FROM capitulos
     WHERE comic_id = NEW.comic_id
       AND id <> NEW.id
       AND numero_desde <= NEW.numero_hasta
       AND numero_hasta >= NEW.numero_desde
     LIMIT 1;
    IF choca IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Esos capitulos ya estan cubiertos por otro capitulo del mismo comic.';
    END IF;
END$$

DELIMITER ;
