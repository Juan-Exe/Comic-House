# Comic House

Plataforma web para leer y gestionar cómics y mangas en formato digital. Permite organizar tu colección, subir capítulos, y leerlos desde el navegador con un lector integrado.

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?style=flat&logo=mariadb&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat&logo=docker&logoColor=white)

---

## Funcionalidades

- Catálogo de cómics y mangas con filtros por tipo, género y editorial
- Lector de capítulos con soporte para páginas dobles
- Subida de capítulos en formato CBR, CBZ, ZIP o imágenes sueltas
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
3. Sube el archivo (`.cbr`, `.cbz`, `.zip`) o imágenes sueltas
4. El sistema extrae las páginas automáticamente y detecta páginas dobles por dimensiones

### Destacar en el slider

1. Panel → **Destacar cómics**
2. Activa el toggle del cómic que quieres en el slider principal

### Comics Imprescindibles

1. Panel → **Gestión de cómics (CRUD)**
2. Columna "Imprescind." — activa el toggle (máximo 4 activos a la vez)
3. La sección aparece en el inicio solo si hay al menos uno activo

### Editoriales

1. Panel → **Editoriales**
2. Sube el logo y el nombre — aparece automáticamente en la sección de inicio

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
