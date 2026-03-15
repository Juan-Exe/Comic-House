# Comic House

Proyecto PHP de streaming de comics y mangas, desacoplado de XAMPP.

## Que se restauró

- Base de datos reconstruida desde el codigo PHP en `database/init/01_schema.sql`.
- Conexion de `modelo/conexion.php` migrada a variables de entorno (`.env`).
- Entorno de ejecucion con Docker Compose (PHP + Apache + MariaDB).

## Requisitos

- Docker Desktop
- DBeaver (opcional, para administrar la BD)

## Levantar el proyecto (sin XAMPP)

1. En la carpeta del proyecto ejecutar:

```bash
docker compose up -d --build
```

2. Abrir la app en:

```text
http://localhost:8080
```

La primera vez, MariaDB crea la base `comic_house` y ejecuta el script `database/init/01_schema.sql` automaticamente.

## Conexion desde DBeaver (MariaDB)

Configura una nueva conexion MariaDB con:

- Host: `127.0.0.1`
- Puerto: `3307`
- Base de datos: `comic_house`
- Usuario: `comic_user`
- Password: `comic_pass_123`

Credenciales definidas en `.env`.

## Estructura de datos reconstruida

Tablas creadas:

- `comics`
- `capitulos`
- `paginas`
- `usuarios`
- `biblioteca_usuarios`

Incluye llaves foraneas e indices para mantener integridad referencial.

## Notas de usuarios y acceso

- El login espera contrasenas con hash (`password_hash`).
- Puedes registrar usuarios desde `Registro/registro.php`.
- Para acceso admin, cambia el campo `rol` del usuario a `admin` desde DBeaver.

## Reiniciar completamente la base de datos

Si necesitas volver a crear la BD desde cero:

```bash
docker compose down -v
docker compose up -d --build
```

Eso elimina el volumen de MariaDB y reejecuta el script SQL de inicializacion.