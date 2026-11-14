-- Impide que dos capitulos del mismo comic cubran el mismo numero.
--
-- Un UNIQUE no puede expresarlo: no compara columnas de UNA fila, sino
-- el rango de la fila nueva contra el de TODAS las demas. Berserk tiene
-- «#1 - #3» y la base aceptaba insertar «#2 - #4», dejando los numeros
-- 2 y 3 en dos capitulos a la vez.
--
-- La comprobacion ya existia en PHP (validar_capitulo.php), pero vivia
-- SOLO alli: cualquier script que insertara directo se la saltaba. Aqui
-- queda tambien en la base, que es la ultima linea de defensa.

DROP TRIGGER IF EXISTS trg_capitulos_sin_solape_ins;
DROP TRIGGER IF EXISTS trg_capitulos_sin_solape_upd;

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
