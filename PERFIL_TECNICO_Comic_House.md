# Perfil técnico · Proyecto Comic House

**Juan Diego Arrieta** · Ingeniería de Sistemas
Repositorio: https://github.com/Juan-Exe/Comic-House

> Documento de apoyo para hoja de vida. Resume las tecnologías, prácticas y
> resultados verificables del desarrollo de **Comic House**, una plataforma web
> de lectura de cómics y mangas construida con PHP, MariaDB y Docker.

---

## Resumen para hoja de vida

> Desarrollador full-stack con experiencia en aplicaciones web PHP sobre bases de
> datos relacionales y entornos contenedorizados. Diseñé e implementé Comic House,
> una plataforma de lectura de cómics con panel administrativo, procesamiento de
> archivos comprimidos y lector paginado. Optimicé la carga de capítulos de
> **206 ms a 2.9 ms (71× más rápido)** mediante transacciones e inserción por
> lotes, y la consulta de lectura **161×** con índices compuestos, documentando
> cada mejora con evidencia medida (EXPLAIN, tiempos y factor de mejora).

**Versión corta (una línea para encabezado de CV):**

> Full-stack PHP · MariaDB · Docker — optimización de rendimiento con evidencia
> medible e integridad de datos a nivel de motor.

---

## 1 · Tecnologías y herramientas

### Lenguajes
| Lenguaje | Uso en el proyecto | Volumen |
|---|---|---|
| **PHP 8.2** | Backend, controladores, vistas, API interna | ~7.700 líneas |
| **SQL (MariaDB)** | Esquema, restricciones, índices, consultas | esquema + optimización |
| **Python 3** | Scripts de datos, benchmarking, generación de informes | ~1.800 líneas |
| **JavaScript (ES6+)** | Interactividad, fetch asíncrono, gestos táctiles | integrado en vistas |
| **HTML5 / CSS3** | Maquetación responsive, diseño propio | ~930 líneas CSS |

### Bases de datos
- **MariaDB 11.4** — modelado relacional, claves foráneas, restricciones `CHECK`,
  índices simples y compuestos, transacciones, `EXPLAIN`
- **SQLite** — prototipado de esquemas y pruebas de integridad en memoria
- **mysqli** con sentencias preparadas (prevención de inyección SQL)

### Infraestructura y DevOps
- **Docker / Docker Compose** — orquestación multi-contenedor (web + base de datos)
- **Dockerfile** personalizado: PHP 8.2 + Apache, extensiones `mysqli`, `pdo_mysql`,
  `zip`, utilidades `unrar-free`
- **Variables de entorno** (`.env`) para credenciales fuera del control de versiones
- **Volúmenes persistentes** para datos de base y archivos subidos
- **Scripts de inicialización** de base de datos (`docker-entrypoint-initdb.d`)

### Control de versiones
- **Git / GitHub** — ramas, commits descriptivos, `.gitignore` con exclusión de
  credenciales y binarios generados
- Uso de `git diff` entre commits como evidencia de cambios antes/después

### Python · análisis y documentación
- **pandas**, **numpy** — limpieza y validación de datasets
- **Jupyter / nbformat / nbclient** — construcción y ejecución programática de notebooks
- **ReportLab** — generación de informes PDF con maquetación profesional
- **PyMuPDF**, **pypdf** — lectura y verificación de documentos PDF

### Frontend
- **Tailwind CSS 4** — servido localmente para eliminar dependencia de CDN externo
- **Swiper**, **Bootstrap Icons**, **jQuery**
- Diseño responsive, tipografía autoalojada (Montserrat)

---

## 2 · Competencias técnicas demostradas

### Optimización de rendimiento con evidencia

No se trata de "hacer el código más rápido", sino de **medir, justificar y
documentar** cada decisión:

- Identificación del cuello de botella real: un `INSERT` por fila dentro de un
  bucle (220 viajes a la base y 220 commits por capítulo)
- Rediseño con **transacciones** e **inserción por lotes** (220 consultas → 3)
- Medición con repeticiones controladas y reporte de media y mejor caso
- Análisis de planes de ejecución con `EXPLAIN`: `ALL + filesort` → `ref`
- Diseño de **índices compuestos** `(capitulo_id, orden)` que resuelven filtro y
  ordenamiento en un solo acceso
- Proyección de escalabilidad: comportamiento O(n) vs O(log n) medido hasta
  880.000 filas

**Resultados medidos:**

| Operación | Antes | Después | Factor |
|---|---|---|---|
| Inserción de 220 páginas | 206,47 ms | 2,92 ms | **71×** |
| Solo transacción (sin lotes) | 206,47 ms | 17,22 ms | 12× |
| Lectura de capítulo · 880.000 filas | 75,38 ms | 0,47 ms | **161×** |

### Integridad de datos a nivel de motor

Traslado de reglas de negocio desde el código de aplicación a la base de datos,
para que se cumplan independientemente de quién escriba el código:

- `PRIMARY KEY`, `FOREIGN KEY` con `ON DELETE CASCADE`
- `UNIQUE` compuesto para impedir posiciones duplicadas
- `CHECK` para dominios cerrados y valores positivos
- `NOT NULL` en campos obligatorios
- **Transacciones con ROLLBACK** para garantizar atomicidad: una carga fallida no
  deja registros parciales

Hallazgo documentado: `PRIMARY KEY` no impide identificadores negativos; se
requiere `CHECK` explícito o tipo `UNSIGNED`.

### Calidad y limpieza de datos (ETL)

- Diseño de un pipeline determinista de seis etapas:
  **ingestión → limpieza → validación → transformación → almacenamiento → consumo**
- Distinción metodológica entre **normalizar** (corrección inequívoca) y
  **rechazar** (valor no deducible con evidencia)
- Trazabilidad: los registros rechazados se conservan para auditoría, no se descartan
- Reconciliación verificable de cada transformación

### Pruebas y verificación

- Batería de pruebas de integridad ejecutada sobre la base real (7/7 correctas)
- Verificación de que las restricciones **rechazan** datos inválidos, no solo que
  el código funciona con datos válidos
- Comprobación de atomicidad mediante fallos provocados a propósito
- Limpieza automática del entorno de pruebas tras cada ejecución

### Seguridad en aplicaciones web

- Hash de contraseñas con `password_hash()` / `password_verify()`
- Control de acceso por rol verificado en servidor (`$_SESSION['rol']`)
- Sentencias preparadas para prevenir inyección SQL
- Credenciales fuera del repositorio mediante `.env` y `.gitignore`
- Diseño de backend con **lista blanca de acciones**: el cliente envía una clave,
  nunca un comando, impidiendo ejecución arbitraria

### Procesamiento de archivos

- Descompresión de **CBR (RAR)** y **CBZ (ZIP)** en servidor
- Filtrado de contenido por tipo, normalización de nombres y ordenamiento natural
- Detección automática de páginas dobles mediante análisis de dimensiones de imagen
- Gestión de cargas de hasta 512 MB con configuración personalizada de PHP

### Depuración y diagnóstico

Casos reales resueltos durante el desarrollo:

- **Fallo de CDN externo** — diagnóstico de resolución DNS bloqueada para un
  dominio específico; solución: servir la dependencia localmente, eliminando el
  punto único de fallo
- **Regresión de estilos** — identificación de que el *preflight* de Tailwind 4
  resetea elementos de formulario; corrección declarando estilos explícitos
- **Error silencioso de validación** — un valor nulo convertido al texto `'nan'`
  pasaba un filtro de obligatoriedad sin lanzar excepción
- **Aislamiento de contenedores** — rediseño de arquitectura al detectar que el
  contenedor web no tiene acceso a binarios del anfitrión

---

## 3 · Entregables producidos

| Entregable | Descripción |
|---|---|
| **Aplicación web** | Catálogo, lector paginado, biblioteca de usuario, panel administrativo CRUD |
| **Infraestructura Docker** | Entorno reproducible de dos contenedores, levantable con un comando |
| **Suite de benchmarking** | 4 scripts de medición: inserción, umbral de índice, escalado y verificación |
| **Notebook Jupyter** | 11 celdas ejecutables documentando el proceso completo de integridad |
| **Informes PDF** | 2 documentos técnicos (7 y 5 páginas) generados programáticamente |
| **Presentación interactiva** | 18 diapositivas web que ejecutan comandos reales contra la base en vivo |
| **Documentación** | Guía de referencia operativa y scripts SQL de migración |

---

## 4 · Metodología de trabajo

- **Evidencia sobre afirmación** — toda mejora de rendimiento se acompaña de
  medición reproducible, no de estimaciones
- **Reproducibilidad** — entorno versionado en Docker, scripts deterministas,
  resultados idénticos entre ejecuciones
- **Documentación de límites** — reconocimiento explícito de lo que la solución
  no cubre y de la deuda técnica pendiente
- **Trazabilidad en control de versiones** — commits descriptivos que explican
  el *porqué* del cambio, no solo el *qué*
- **Verificación antes de dar por terminado** — pruebas ejecutadas sobre datos
  reales y comprobación visual del resultado

---

## 5 · Conceptos de ingeniería de datos aplicados

- Pipelines ETL vs ELT y criterios de elección
- Procesamiento por lotes (*batch*) frente a flujo continuo (*streaming*)
- Modelado relacional y normalización
- Criterios de decisión SQL vs NoSQL: integridad, flexibilidad, volumen,
  latencia y mantenibilidad
- Planes de ejecución y selectividad de índices
- Propiedades ACID y control transaccional
- Análisis de complejidad algorítmica aplicado a consultas

---

## 6 · Métricas del proyecto

| Métrica | Valor |
|---|---|
| Líneas de PHP | ~7.700 |
| Líneas de Python | ~1.800 |
| Líneas de CSS | ~930 |
| Commits en el repositorio | 22 |
| Contenedores Docker orquestados | 2 |
| Tablas en el modelo relacional | 6 |
| Restricciones de integridad e índices | 13 |
| Pruebas de integridad automatizadas | 7 |
| Diapositivas de la presentación interactiva | 18 |

---

## 7 · Sugerencia de redacción para la hoja de vida

### Como experiencia de proyecto

> **Comic House** — Plataforma web de lectura de cómics · *Proyecto académico y personal*
>
> - Desarrollé una aplicación full-stack en **PHP 8.2 / MariaDB 11.4** desplegada
>   con **Docker Compose**, con catálogo, lector paginado y panel administrativo.
> - Implementé el procesamiento de archivos **CBR/CBZ**: descompresión en servidor,
>   normalización y registro de hasta 240 páginas por capítulo.
> - Optimicé la carga de capítulos **71×** (206 ms → 2,9 ms) mediante transacciones
>   e inserción por lotes, y la consulta de lectura **161×** con índices compuestos,
>   documentando cada mejora con `EXPLAIN` y mediciones reproducibles.
> - Trasladé las reglas de negocio a **restricciones de base de datos**
>   (FK, UNIQUE, CHECK) y verifiqué su cumplimiento con una batería de 7 pruebas
>   automatizadas sobre datos reales.
> - Diseñé un pipeline **ETL determinista** de seis etapas con trazabilidad de
>   registros rechazados para auditoría.

### Como bloque de habilidades técnicas

> **Lenguajes:** PHP · SQL · Python · JavaScript · HTML5 · CSS3
> **Bases de datos:** MariaDB/MySQL · SQLite · modelado relacional · índices · transacciones
> **DevOps:** Docker · Docker Compose · Git · GitHub · variables de entorno
> **Datos:** pandas · numpy · Jupyter · pipelines ETL · validación e integridad
> **Prácticas:** optimización con evidencia · pruebas de integridad · documentación técnica

---

*Documento generado el 13 de octubre de 2026 a partir del historial de desarrollo
del repositorio. Las cifras de rendimiento provienen de ejecuciones medidas y son
reproducibles con los scripts incluidos en `E1_integridad_rendimiento/benchmark/`.*
