# Automatización del registro de cómics · Documentación técnica

**Proyecto:** Comic House · Panel de administración
**Alcance:** pantalla *Registrar cómic* (`crud.php`)
**Estado:** implementado y verificado · noviembre de 2026

> Documento de referencia del sistema ya construido. El diseño, las historias de
> usuario y los criterios de aceptación están en
> [PLAN_Registro_Automatizado.md](PLAN_Registro_Automatizado.md).

---

## 1 · Qué hace

Registrar un cómic exigía llenar nueve campos a mano, buscando los datos en
otras páginas. Ahora se escribe el título, se elige la edición, se revisan los
datos y se guarda.

```
Escribir título  →  Elegir edición  →  Revisar en el modal  →  Guardar
   (350 ms)         (8 ediciones)       (9 campos propuestos)
```

---

## 2 · De dónde sale cada campo

| Campo | Fuente | Tipo de dato |
|---|---|---|
| Título | ComicVine | verificado |
| Año | ComicVine | verificado |
| Editorial | ComicVine + catálogo local | verificado |
| Capítulos | ComicVine `count_of_issues` | verificado |
| Portada | ComicVine, descargada al servidor | verificado |
| Descripción | ComicVine, traducida al español | verificado |
| Tipo | regla propia según la editorial | inferido |
| Género | MangaDex, o palabras de la descripción | sugerido |
| **Clasificación** | **cadena de 3 capas** | **verificado o estimado** |

---

## 3 · Arquitectura

Seis endpoints, cada uno con una responsabilidad:

| Archivo | Líneas | Función |
|---|---|---|
| `buscar_metadatos.php` | 308 | Consulta ComicVine, mapea editoriales, detecta duplicados y corrige erratas |
| `traducir.php` | 166 | Traduce la descripción EN → ES |
| `descargar_portada.php` | 116 | Trae la imagen al servidor |
| `crear_editorial.php` | 73 | Da de alta una editorial ausente |
| `buscar_clasificacion.php` | 197 | Capa 1: MangaDex verificado por año |
| `estimar_clasificacion.php` | 255 | Capas 2 y 3: Wikipedia y modelo de lenguaje |

La interfaz vive en `crud.php` (modal + lógica de encadenado) y los estilos en
`admin/admin.css`.

### Orden de ejecución

```
Al escribir (350 ms después de parar)
    └── buscar_metadatos.php ──► lista de ediciones

Al elegir una edición (en paralelo)
    ├── traducir.php            ──► descripción en español
    ├── descargar_portada.php   ──► imagen en uploads/
    └── buscar_clasificacion.php ──► clasificación y género
            └── si no resuelve: estimar_clasificacion.php
```

La traducción y la portada arrancan a la vez al abrir el modal, no al
confirmar: cuando se pulsa «Usar estos datos» ya están listas.

---

## 4 · Clasificación por edad: el problema difícil

Es el campo que más trabajo costó, y conviene entender por qué.

### 4.1 · ComicVine no lo tiene

Comprobado en tres niveles:

| Recurso | Resultado |
|---|---|
| `volume` (la serie) | sin campo de clasificación |
| `issue` (un número) | sin campo de clasificación |
| los 20 recursos de la API | ninguno sobre edad o madurez |

Se probó el campo `concepts`, que incluía etiquetas prometedoras como
*Sexual Violence*. Al contrastarlo con obras de clasificación conocida:

| Obra | Clasificación real | `concepts` devueltos |
|---|---|---|
| Berserk | +18 | **0** |
| The Walking Dead | +18 | **0** |
| Archie | ATP | 9, ninguno sobre edad |

El campo está vacío justo en las obras más explícitas. **Descartado.**

### 4.2 · Por qué no hay una fuente única

No es un problema de búsqueda: **no existe un dato estandarizado**. Marvel usa
«Rated T+», DC usa «Teen», Image no marca nada, y ninguna base pública lo
recopila de forma uniforme.

### 4.3 · La solución: tres capas

```
1. MangaDex      contentRating, verificado por año   → VERIFICADO
       ↓ si no resuelve
2. Wikipedia     demografía editorial, con contexto  → VERIFICADO
       ↓ si no resuelve
3. Groq (qwen)   estimación sobre la sinopsis        → ESTIMADO
       ↓ si no resuelve
   Sin sugerencia: lo marca el administrador
```

Se detiene en la primera que responde, y **el origen viaja con el dato**.

#### Capa 1 · MangaDex

Publica `contentRating`, pero al buscar por título devuelve obras distintas.
Por eso exige **verificación cruzada por año**:

| Consulta | MangaDex devuelve | Decisión |
|---|---|---|
| Berserk (1989) | Berserk, 1989, `erotica` | ✅ sugiere **+18** |
| Vagabond (1999) | Vagabond, 1999, `erotica` | ✅ sugiere **+18** |
| Invincible (2003) | otra obra, 2015 | ❌ descartado |
| Berserk (2018) | *Boushoku no Berserk* | ❌ descartado |

Si el año no coincide (±1 año de margen), no es la misma obra.

Escalas: `safe`→ATP · `suggestive`→+13 · `erotica` y `pornographic`→+18.

#### Capa 2 · Wikipedia

Busca la demografía editorial japonesa y los sellos de madurez. **Siempre con
contexto**, porque el primer intento con palabras sueltas falló de forma
peligrosa:

| Obra | Palabra hallada | De dónde salía realmente |
|---|---|---|
| Invincible | «Teen» | *Teen Team*, un grupo de personajes |
| Saga | «explicit» | *explicitly addressed*, sobre etnia |

Habría marcado clasificaciones inventadas. Exigiendo contexto
(`seinen manga`, `rated M`, `for mature readers`) los falsos positivos bajaron
a **cero**:

```
Berserk    → +18  «seinen manga»        ✓
Vagabond   → +18  «seinen manga»        ✓
Invincible → sin sugerencia
Saga       → sin sugerencia
Bone       → sin sugerencia
```

#### Capa 3 · Modelo de lenguaje (opcional)

Último recurso, solo si hay clave configurada. Tres salvaguardas:

- `temperature: 0`, para que sea lo más determinista posible
- Solo acepta una etiqueta de la escala; cualquier otra cosa **se descarta**
- Se le pide elegir la opción **más restrictiva** ante la duda

Resultados verificados:

| Obra | Estimación | Motivo dado |
|---|---|---|
| Invincible | +18 | violencia gráfica extrema y mutilaciones |
| Saga | +18 | desnudos y lenguaje explícito |
| Bone | ATP | aventuras cómicas para niños |

### 4.4 · Verificado ≠ estimado

Esta distinción es deliberada y visible en la interfaz:

| Origen | Significa | Cómo se muestra |
|---|---|---|
| **verificado** | una fuente lo publica | texto normal + «Confírmalo» |
| **estimado** | un modelo lo dedujo, puede fallar | **ámbar** + «No es un dato verificado» |

Presentarlos igual sería engañoso en un campo de control parental. En ningún
caso se guarda sin que el administrador confirme.

---

## 5 · Reglas propias

### 5.1 · Tipo (Cómic / Manga)

Se infiere de la editorial:

| Editorial | Tipo |
|---|---|
| Shueisha, Kodansha, Shogakukan, Hakusensha, Young Animal, Square Enix, Kadokawa, Shonen | Manga |
| cualquier otra | Cómic |

Preseleccionado y editable. Verificado: elegir la edición de Kodansha sugiere
Manga; la de Image, Cómic.

### 5.2 · Género

Dos vías, en orden:

1. **MangaDex** — etiquetas del grupo `genre`. Los grupos `theme` y `content`
   se ignoran: describen elementos de la trama, no el género.
2. **Palabras de la descripción** ya traducida, contrastadas con los géneros
   que **ya existen** en el catálogo, para no multiplicar variantes.

Si ninguna da resultado, el campo queda vacío.

### 5.3 · Editorial ausente

Si la fuente dice que la publicó una editorial que no está en el catálogo, la
**única** salida es crearla.

No se ofrece «elige otra de la lista»: asignarle a un cómic una editorial que no
lo publicó sería guardar un dato falso. El modal lo explica:

> *No está en tu catálogo. Es la editorial que publicó esta obra, así que no
> puede sustituirse por otra.*

### 5.4 · Corrección de erratas

Si no hay resultados, se reintenta contra el endpoint `/volumes` de ComicVine
con `filter=name:`, que sí busca por prefijo, probando prefijos cada vez más
cortos. Lo que vuelve se compara con `levenshtein`.

```
invicible  → ¿Querías decir «Invincible»?
berserck   → ¿Querías decir «Berserk»?
xyzqw123   → sin sugerencia
```

Se admite cambiar hasta un tercio de las letras. Es comparación de texto, no
interpretación: por eso una cadena sin parecido real no sugiere nada.

---

## 6 · Configuración

### Variables de entorno (`.env`)

```bash
# Obligatoria: metadatos principales
# Clave gratuita en https://comicvine.gamespot.com/api/
COMICVINE_API_KEY=

# Opcional: estimación de clasificación cuando ninguna fuente la publica
# Clave gratuita en https://console.groq.com
GROQ_API_KEY=
```

Sin `GROQ_API_KEY`, el sistema funciona igual pero solo sugiere clasificaciones
verificables.

### Servicios externos

| Servicio | Uso | Clave | Límite |
|---|---|---|---|
| ComicVine | metadatos, portada | sí | — |
| MyMemory | traducción EN→ES | no | 500 caracteres (se cortan bloques a 450) |
| MangaDex | clasificación y género | no | — |
| Wikipedia | demografía editorial | no | — |
| Groq | estimación | sí | capa gratuita |

---

## 7 · Decisiones de seguridad

| Riesgo | Medida |
|---|---|
| Descargar cualquier archivo de internet | Solo `https` y solo del dominio de la fuente |
| Que un `.jpg` no sea una imagen | Validación con `getimagesizefromstring` |
| Archivos enormes | Tope de 5 MB por portada |
| Nombre manipulado que salga de `uploads/` | Se pasa por `basename()` |
| Inyección SQL | Sentencias preparadas en todos los endpoints |
| Uso sin permiso | Verificación de rol admin en los que escriben |
| Claves expuestas | `.env` fuera del repositorio |

---

## 8 · Comportamiento ante fallos

Probado forzando cada escenario, no simulándolo:

| Escenario | Respuesta |
|---|---|
| Clave de API inválida | *«No se pudo consultar la fuente (HTTP 401). Registra los datos a mano.»* |
| Clave ausente | *«Falta configurar COMICVINE_API_KEY»* |
| Traducción caída | Se conserva el texto original con aviso |
| Traducción parcial | Se traduce lo posible y se avisa |
| Portada no descargable | Queda activa la subida manual |
| Título ya registrado | Avisa qué edición existe, sin bloquear |
| Ninguna fuente resuelve | Campos vacíos, registro manual |

**El formulario nunca queda bloqueado.** Si todas las fuentes fallan, se
comporta como antes de esta automatización.

---

## 9 · Límites conocidos

- **La clasificación de cómics occidentales es una estimación**, no un dato.
  Conviene revisarla siempre.
- **La equivalencia `suggestive → +13`** es una interpretación de una escala a
  otra; merece revisión según crezca el catálogo.
- **El catálogo de modelos de Groq cambia sin aviso.** Por eso se prueban varios
  en orden; si todos se retiran habrá que actualizar la lista.
- **Sin caché.** Cada búsqueda consulta las APIs de nuevo.
- **Solo cubre el registro.** Editar cómic y subir capítulos siguen siendo
  manuales.

---

## 10 · Cómo probarlo

```bash
cd "C:/Users/juand/Documents/JUAN DIEGO/PROYECTOS/Comic House/Comic-House"
docker compose up -d
```

Luego http://localhost:8080/crud.php y escribir un título.

### Casos de prueba

| Escribe | Qué debe pasar |
|---|---|
| `Invincible` | 8 ediciones; al elegir Image 2003 → Cómic, Superhéroes, +18 estimado |
| `Berserk` | avisa que ya existe (Young Animal · 1989) |
| `Vagabond` | Manga, +18 **verificado** por MangaDex |
| `invicible` | sugiere «Invincible» |
| `xyzqw123` | sin resultados, registro manual |

### Endpoints por separado

```bash
# Metadatos
curl "http://localhost:8080/controlador/buscar_metadatos.php?titulo=Invincible"

# Clasificación verificada
curl "http://localhost:8080/controlador/buscar_clasificacion.php?titulo=Berserk&anio=1989"

# Estimación
curl -X POST --data-urlencode "titulo=Saga" --data-urlencode "tipo=Comic" \
     --data-urlencode "descripcion=Opera espacial con desnudos y violencia." \
     "http://localhost:8080/controlador/estimar_clasificacion.php"
```

---

## 11 · Historial

| Commit | Qué incorporó |
|---|---|
| `9aa96ff` | Plan con 9 historias y 47 criterios |
| `bae0ee1` | Fase 1: modal de confirmación |
| `2b5093e` | El modal deja de abrirse solo |
| `1d9c2a3` | Fase 2: traducción |
| `3b19d02` | Fase 3: descarga de portada |
| `f9bf86c` | Fuente secundaria verificada |
| `d2739b2` | Fase 6: casos límite |
| `acecc50` | Clasificación en tres capas |
| `7873a68` | Activación de Groq y corrección del encadenado |

---

*Todos los datos sobre las APIs de este documento se verificaron con consultas
reales, no con suposiciones. Donde una fuente no sirve, se indica la prueba que
lo demuestra.*
