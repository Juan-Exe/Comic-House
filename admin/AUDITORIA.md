# Auditoría del panel de administración · Comic House

Estado medido el 24 de octubre de 2026, antes del rediseño.

---

## Resumen

El panel funciona, pero se lee como siete pantallas hechas por separado en
lugar de un producto. No es cuestión de color —eso ya se unificó— sino de
estructura: cada pantalla inventa su propio ancho, su propia tarjeta y su
propio nombre para el mismo botón.

| Métrica | Valor |
|---|---|
| Líneas de CSS en el panel | **1.488** |
| Clases propias por pantalla | **145** en total |
| Anchos de contenedor distintos | **5** (420, 620, 680, 720, 1100 px) |
| Radios de borde distintos | **11** (de 3 a 24 px) |
| Pantallas que redefinen `.topbar` | **5 de 7** |
| Pantallas que redefinen `box-sizing` | **5 de 7** |

---

## 1 · El mismo elemento con cinco nombres

El botón de guardar se llama distinto en cada pantalla:

| Pantalla | Clase del botón principal |
|---|---|
| `crud.php` | `.btn-registrar` |
| `destacar.php` | `.btn-save` |
| `subir_capitulo.php` | `.btn-submit` |
| `modificar_comic.php` | `.btn-guardar` |
| `editoriales.php` | `.btn-primario` (ya migrado) |

Lo mismo ocurre con las tarjetas (`.card`, `.card-form`, `.form-card`,
`.panel`) y con los contenedores de formulario (`.form-grid`, `.form-actions`
redefinidos en tres sitios con valores distintos).

**Consecuencia:** cambiar el aspecto de los botones exige editar cinco
archivos y es fácil olvidar uno. Es exactamente el problema que ya se dio con
la barra de búsqueda y el preflight de Tailwind.

---

## 2 · Uso del espacio

Capturas a 1440 px de ancho:

- **`destacar.php`** — una sola tarjeta de 280 px centrada, con una imagen
  vacía de 400 px de alto. Más del 70 % del lienzo sin usar. Gestionar seis
  cómics obliga a desplazarse por una columna estrechísima.
- **`subir_capitulo.php`** — contenedor de 720 px en una pantalla de 1440.
  La rejilla de portadas cabe a cinco por fila y deja la sexta sola.
  Los títulos se truncan («Absolute Batm…», «Ultimate Spide…»).
- **`crud.php`** — la tabla se corta por la derecha: las columnas
  «Imprescind.» y las acciones quedan fuera del área visible.
- **`dashboard.php`** — contenido centrado vertical y horizontalmente en una
  pantalla por lo demás vacía.

**Consecuencia:** el panel parece una maqueta de móvil estirada, no una
herramienta de escritorio.

---

## 3 · Jerarquía tipográfica

Los tamaños ya se normalizaron a la escala de cinco pasos, pero la jerarquía
sigue sin leerse:

- Los títulos de pantalla y los de tarjeta usan pesos parecidos, así que no
  se distingue el nivel.
- Varias etiquetas de formulario van en mayúsculas con `letter-spacing`, y
  compiten visualmente con los títulos reales.
- `destacar.php` y `subir_capitulo.php` ponen el título dentro de la tarjeta,
  mientras `crud.php` y `editoriales.php` lo ponen fuera.

---

## 4 · Patrones que no coinciden con el sitio público

La parte pública (diseñada en Figma) tiene recursos propios que el panel
ignora:

| Recurso del sitio | ¿Lo usa el panel? |
|---|---|
| Títulos en mayúsculas con líneas rojas a los lados | No |
| Tarjetas con borde rojo de 3 px | No |
| Rejilla ancha de portadas (catálogo) | Parcial |
| Franja superior `#ce4646` sobre navbar `#ef4444` | No, solo una barra |

---

## Plan de rediseño

Por orden de impacto:

1. **Un layout de página único** — misma cabecera, mismo ancho máximo
   (1240 px), mismo espaciado vertical en las siete pantallas.
2. **Eliminar las clases duplicadas** — un solo `.btn`, una sola `.tarjeta`,
   un solo `.campo`, ya definidos en `admin/admin.css`. Se calculan unas
   900 líneas de CSS que desaparecen.
3. **Rehacer las pantallas que desperdician espacio** — `destacar.php` a
   rejilla de tarjetas, `subir_capitulo.php` a dos columnas, `crud.php` con
   la tabla completa visible.
4. **Fijar la jerarquía** — título de pantalla fuera de la tarjeta, un solo
   estilo de etiqueta, radios reducidos a los tres del sistema.
5. **Recuperar recursos del sitio público** — el tratamiento de los títulos
   y el borde rojo de las tarjetas, para que panel y sitio se reconozcan.
