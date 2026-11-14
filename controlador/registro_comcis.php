<?php

if (!empty($_POST["btnregistrar"])) {
    // Se comprueba sobre el texto YA recortado: !empty('   ') es true en
    // PHP, asi que una editorial de solo espacios pasaba el filtro y se
    // guardaba el comic sin editorial real.
    $obligatorios = ['titulo', 'clasificacion', 'editorial', 'tipo',
                     'descripcion', 'anio', 'genero'];
    $completo = true;
    foreach ($obligatorios as $campo) {
        if (trim($_POST[$campo] ?? '') === '') {
            $completo = false;
            break;
        }
    }

    if ($completo) {

        // NORMALIZACION AL INSERTAR (correccion 1 del Momento 1).
        // El titulo se limpia aqui, al escribir, y no con un TRIM dentro
        // del CHECK: si la base normalizara al validar, aceptaria el dato
        // sucio y lo guardaria con los espacios intactos.
        $titulo        = trim($_POST["titulo"]);
        $editorial     = trim($_POST["editorial"]);
        $tipo          = trim($_POST["tipo"]);
        $clasificacion = trim($_POST["clasificacion"]);
        $descripcion   = trim($_POST["descripcion"]);
        $genero        = trim($_POST["genero"]);
        $estado        = trim($_POST["estado_publicacion"] ?? 'En publicacion');

        // Los números llegan como texto para poder rechazarlos con un
        // mensaje claro en vez de que (int) los convierta en basura
        // silenciosa: '45514' no es un año y '15151515' no es un
        // número de capítulos.
        $anio_txt = trim($_POST["anio"] ?? '');
        if (!preg_match('/^[0-9]{4}$/', $anio_txt)) {
            header("Location: crud.php?msg=anio"); exit();
        }
        $anio = (int) $anio_txt;
        if ($anio < 1900 || $anio > (int) date('Y') + 1) {
            header("Location: crud.php?msg=anio"); exit();
        }

        // Rango de capítulos. En emisión, el final queda abierto.
        $desde_txt = trim($_POST["capitulo_desde"] ?? '1');
        $hasta_txt = trim($_POST["capitulo_hasta"] ?? '');

        if ($estado === 'Tomo unico') {
            $cap_desde = 1;
            $cap_hasta = 1;
        } else {
            if (!preg_match('/^[1-9][0-9]{0,3}$/', $desde_txt)) {
                header("Location: crud.php?msg=capitulos"); exit();
            }
            $cap_desde = (int) $desde_txt;

            if ($estado === 'En publicacion') {
                $cap_hasta = null;              // todavía no se sabe
            } else {
                if (!preg_match('/^[1-9][0-9]{0,3}$/', $hasta_txt)) {
                    header("Location: crud.php?msg=capitulos"); exit();
                }
                $cap_hasta = (int) $hasta_txt;
                if ($cap_hasta < $cap_desde) {
                    header("Location: crud.php?msg=capitulos"); exit();
                }
            }
        }

        // El texto mostrable se compone, no se escribe a mano.
        if ($estado === 'Tomo unico') {
            $capitulos = 'Tomo único';
        } elseif ($cap_hasta === null) {
            $capitulos = $cap_desde . ' - en emisión';
        } elseif ($cap_hasta === $cap_desde) {
            $capitulos = (string) $cap_desde;
        } else {
            $capitulos = $cap_desde . ' - ' . $cap_hasta;
        }

        // La portada puede venir de dos sitios: subida a mano o ya
        // descargada de la fuente por descargar_portada.php (HU-07).
        // En el segundo caso el archivo esta en uploads/ y solo llega
        // su nombre en un campo oculto.
        $portada_auto = trim($_POST["portada_descargada"] ?? "");
        $subio_archivo = !empty($_FILES["portada"]["name"])
                         && is_uploaded_file($_FILES["portada"]["tmp_name"] ?? "");

        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        if ($subio_archivo) {
            $portada = $_FILES["portada"]["name"];
            $ruta_destino = "uploads/" . basename($portada);
            $portada_lista = move_uploaded_file($_FILES["portada"]["tmp_name"], $ruta_destino);
        } elseif ($portada_auto !== "") {
            // basename() evita que un nombre manipulado salga de uploads/
            $portada = basename($portada_auto);
            $portada_lista = is_file("uploads/" . $portada);
        } else {
            $portada = "";
            $portada_lista = false;
        }

        if ($portada_lista) {
            include_once("modelo/conexion.php");

            // La editorial llega por nombre desde el formulario; se resuelve
            // a su id para poder exigir la clave foranea. Si no existe, la
            // operacion se detiene antes de tocar comics.
            $buscar = $conexion->prepare("SELECT id FROM editoriales WHERE nombre = ?");
            $buscar->bind_param("s", $editorial);
            $buscar->execute();
            $buscar->bind_result($editorial_id);
            $existe = $buscar->fetch();
            $buscar->close();

            if (!$existe) {
                header("Location: crud.php?msg=editorial_inexistente");
                exit();
            }

            $stmt = $conexion->prepare(
                "INSERT INTO comics (titulo, clasificacion, descripcion, capitulos,
                                     anio, genero, portada, editorial, editorial_id, tipo,
                                     estado_publicacion, capitulo_desde, capitulo_hasta)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssisssissii",
                $titulo, $clasificacion, $descripcion, $capitulos,
                $anio, $genero, $portada, $editorial, $editorial_id, $tipo,
                $estado, $cap_desde, $cap_hasta
            );

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: crud.php?msg=ok");
            } else {
                // La base rechazo la fila. Se traduce el error del motor a un
                // aviso concreto para la persona, en vez de un fallo generico.
                $errno = $conexion->errno;
                $error = $conexion->error;
                $stmt->close();

                if ($errno === 1062) {               // UNIQUE (titulo)
                    $motivo = 'titulo_duplicado';
                } elseif ($errno === 1452) {         // FOREIGN KEY (editorial_id)
                    $motivo = 'editorial_inexistente';
                } elseif ($errno === 4025) {         // CHECK (anio, clasificacion, tipo)
                    $motivo = 'valor_fuera_de_dominio';
                } else {
                    $motivo = 'error';
                }

                error_log("registro_comcis: [$errno] $error");
                header("Location: crud.php?msg=" . $motivo);
            }
            exit();
        } else {
            header("Location: crud.php?msg=upload_error");
            exit();
        }

    } else {
        header("Location: crud.php?msg=campos_vacios");
        exit();
    }
}

?>
