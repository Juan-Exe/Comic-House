# Comic House

Plataforma web para leer y gestionar cómics y mangas en formato digital. Permite organizar tu colección, subir capítulos, y leerlos desde el navegador con un lector integrado.

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?style=flat&logo=mariadb&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat&logo=docker&logoColor=white)

---

## Funcionalidades

- Catálogo de cómics y mangas con filtros por tipo, género y editorial
- Lector de capítulos con soporte para páginas dobles
- Subida de capítulos en formato CBR (RAR) o CBZ (ZIP), con validación de imágenes
- Panel de administración para gestionar el contenido
- Slider de destacados configurable
- Sección "Comics Imprescindibles" (máximo 4)
- Editoriales destacadas con logo
- Biblioteca personal por usuario
- Buscador en tiempo real
- Registro e inicio de sesión con roles (admin / usuario)

---

## Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y corriendo

Nada más. No necesitas PHP, ni MySQL, ni XAMPP.

---

## Instalación y ejecución

### 1. Clona el repositorio

```bash
git clone https://github.com/Juan-Exe/Comic-House.git
cd Comic-House
```

### 2. Crea el archivo de entorno

Crea un archivo llamado `.env` en la raíz del proyecto con este contenido:

```env
DB_HOST=db
DB_PORT=3306
DB_NAME=comic_house
DB_USER=comic_user
DB_PASSWORD=comic_pass_123
DB_ROOT_PASSWORD=root_123
```

### 3. Levanta el proyecto

```bash
docker compose up -d --build
```

### 4. Abre la aplicación

```
http://localhost:8080
```

La base de datos se crea automáticamente al primer arranque.

La imagen instala [UnRAR desde Debian](https://packages.debian.org/trixie/unrar)
(componente `non-free`) para comprobar la integridad de los CBR. Se sustituyó
`unrar-free`: la prueba de un RAR con CRC incorrecto no era rechazada por ese
extractor. Después de actualizar el Dockerfile, ejecutar
`docker compose up -d --build web` para conservar esta dependencia al recrear
el contenedor. No elimina el volumen de la base de datos.

---

## Credenciales por defecto

No hay usuarios precargados. Regístrate en:

```
http://localhost:8080/Registro/registro.php
```

Para convertir tu cuenta en administrador, conéctate a la base de datos y ejecuta:

```sql
UPDATE usuarios SET rol = 'admin' WHERE usuario = 'tu_usuario';
```

Puedes usar cualquier cliente MySQL (TablePlus, DBeaver, etc.) con estos datos:

| Campo    | Valor         |
|----------|---------------|
| Host     | 127.0.0.1     |
| Puerto   | 3307          |
| Base     | comic_house   |
| Usuario  | comic_user    |
| Password | comic_pass_123 |

---

## Uso del panel de administración

Una vez con rol admin, accede desde el menú de usuario → **Panel de Admin**.

### Agregar un cómic

1. Panel → **Nuevo cómic**
2. Completa título, tipo (Comic / Manga), género, clasificación, año, editorial y portada
3. Guarda — el cómic aparece en el catálogo

### Subir un capítulo

1. Panel → **Subir capítulo**
2. Selecciona el cómic del catálogo visual
3. Elige uno o varios archivos `.cbr` o `.cbz`
4. Si eliges varios, revisa la lista y selecciona una fila para corregir sus datos
5. Sube los archivos listos; cada entrega conserva su estado y sus páginas dobles

Si falla un archivo, los demás se conservan. Puedes cambiar el archivo dañado
y continuar solo con los pendientes. Al recargar la misma pestaña se recuperan
la lista y los datos; vuelve a elegir los archivos pendientes para reanudarlos.
Los archivos ya guardados se reconocen aunque se haya perdido su respuesta.
«Vaciar lista» o «Cerrar lote» no elimina capítulos de la base.

La lista incluye los números sin título publicado. Un archivo como `#1-2.cbr`
consulta ambos números sin convertirlo en un tomo completo. Cuando la fuente
no trae título individual, usa el nombre del recopilatorio si se confirma qué
números contiene y señala ese origen. Las traducciones se identifican como
orientativas y conservan el nombre original. Si no hay un nombre verificable,
propone uno descriptivo y lo indica; puedes cambiarlo.
Para un archivo combinado se identifica la fecha del primer número incluido.

La fecha de publicación admite **día/mes/año** o **mes/año**, según lo que se
conozca. Esa precisión se conserva al guardar, editar y consultar el capítulo.
Las instalaciones existentes deben aplicar
[`database/migracion_02_fechas_parciales.sql`](database/migracion_02_fechas_parciales.sql)
para incorporar las fechas mensuales. Las instalaciones nuevas incluyen este
cambio en el esquema inicial.

La subida múltiple requiere además
[`database/migracion_03_subida_lotes.sql`](database/migracion_03_subida_lotes.sql),
que añade los recibos de intento y la huella del comprimido. Está aplicada en
la base local de esta revisión. El esquema inicial también la incluye.

Verificación de lotes, con Docker activo y Python/Playwright:

```powershell
python tests/lote_capitulos.py
```

La prueba crea sus propios registros y archivos y los limpia al terminar.

### Reemplazar el archivo de un capítulo

1. En **Ya subidos**, abre el lápiz del capítulo.
2. En **Reemplazar archivo del capítulo**, elige el CBR o CBZ nuevo.
3. Pulsa **Revisar reemplazo**, comprueba el capítulo y el archivo y confirma.

Se conservan el id, el título, la numeración y la fecha guardados. Si necesitas
cambiarlos, guarda esos cambios antes de reemplazar el archivo. Las páginas
anteriores se sustituyen por completo; si falla la validación o el guardado,
se conserva la edición anterior. Un reintento con el mismo archivo reconoce el
reemplazo ya completado. Si otra pestaña cambió el capítulo, debes recargar y revisarlo.

Las instalaciones existentes necesitan
[`database/migracion_04_reemplazos.sql`](database/migracion_04_reemplazos.sql).
Está aplicada en la base local. Las instalaciones nuevas incluyen la tabla de
recibos en el esquema inicial. Verificación con fixtures propios:

```powershell
python tests/reemplazo_capitulo.py
```

La [guía de operación y verificación](docs/OPERACION_Y_VERIFICACION.md) reúne
los flujos, migraciones, pruebas y límites del plan.

### Destacar en el slider

1. Panel → **Destacar cómics**
2. Activa el toggle del cómic que quieres en el slider principal

### Comics Imprescindibles

1. Panel → **Gestión de cómics (CRUD)**
2. Columna "Imprescind." — activa el toggle (máximo 4 activos a la vez)
3. La sección aparece en el inicio solo si hay al menos uno activo

### Editoriales

1. Panel → **Editoriales**
2. Escribe el nombre y revisa las propuestas de logo de Wikipedia/Wikimedia.
3. Selecciona el logo correcto o descártalo y sube una imagen manual.
4. Guarda la editorial. Para mostrarla en el inicio, activa su destacado.

Admite PNG, JPG, WebP y GIF, hasta **5 MB y 16 megapíxeles**, y guarda los nuevos
logos como PNG. Los SVG de Wikimedia se proponen mediante su versión PNG.
Los logos ya guardados se conservan. Si la búsqueda falla, puedes subir uno a mano.

Los nombres existentes se reutilizan aunque cambien las mayúsculas, tildes o
espacios. Los nombres parecidos requieren revisión antes de crear otra editorial.
Desde el registro de un cómic también puedes crear su editorial y dejarla
seleccionada sin perder los datos del formulario; allí el logo es opcional y
puede añadirse después. Si la fuente indica una editorial, se ofrece crear esa.
Si no la indica, puedes escribir el nombre manualmente.

Verificación del asistente (Docker y Python/Playwright para la interfaz):

```powershell
docker exec comic_house_web php tests/editoriales.php
python tests/editoriales_ui.py
```

La segunda prueba consulta y descarga el logo real de Kodansha desde Wikimedia;
el resto usa editoriales temporales que se eliminan al terminar.

---

## Detener el proyecto

```bash
docker compose down
```

Para borrar también la base de datos y empezar desde cero:

```bash
docker compose down -v
```

---

## Entregables académicos

El repositorio sostiene también los entregables de Optativa II. Con los
contenedores levantados (`docker compose up -d`):

```bash
# Momento 2 · las cuatro operaciones del prototipo (11 comprobaciones)
docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/momento2_operaciones.php

# Momento 2 · punto 9.8: orden inverso, con y sin índice
docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/momento2_orden_inverso.php

# Momento 1 · inserción por lotes: 206 ms -> 2.9 ms
docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/benchmark.php 220 5
```

Las restricciones de integridad vienen aplicadas en
`database/init/01_schema.sql`, así que una base creada desde cero ya las
incluye. Para aplicarlas sobre una base existente:

```bash
docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
  comic_house < E1_integridad_rendimiento/benchmark/momento2_integridad.sql
```

Los informes, capturas y materiales de entrega académica se conservan localmente
y no se incluyen en este repositorio. Aquí se versionan el código, las pruebas,
las migraciones y su documentación técnica.

---

## Estructura del proyecto

```
Comic-House/
├── Comics/          # Página de detalle de un cómic
├── Lector/          # Lector de capítulos
├── P-comics/        # Catálogo (comics y mangas)
├── Biblioteca/      # Biblioteca personal del usuario
├── Login/           # Inicio de sesión
├── Registro/        # Registro de usuarios
├── controlador/     # Lógica de negocio (PHP)
├── modelo/          # Conexión a base de datos
├── database/init/   # Script SQL de inicialización
├── uploads/         # Imágenes subidas (portadas, logos)
├── paginas/         # Páginas extraídas de capítulos
├── js/              # JavaScript (buscador, etc.)
├── Dockerfile       # Imagen PHP + Apache + extensiones
├── docker-compose.yml
└── index.php        # Página principal
```

---

## Tecnologías utilizadas

| Capa       | Tecnología              |
|------------|-------------------------|
| Backend    | PHP 8.2                 |
| Base datos | MariaDB 11.4            |
| Frontend   | HTML, CSS, JavaScript   |
| UI         | Tailwind CSS, Bootstrap Icons, Swiper.js |
| Entorno    | Docker + Apache         |
