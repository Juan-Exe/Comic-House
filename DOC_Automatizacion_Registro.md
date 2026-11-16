# Automatización del registro de cómics · Documentación técnica

**Proyecto:** Comic House · Panel de administración
**Alcance:** pantalla *Registrar cómic* (`crud.php`)
**Estado:** **cerrado** · implementado y verificado · noviembre de 2026

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
| Estado de publicación | ComicVine `last_issue` + `count_of_issues` | deducido |
| Tipo | regla propia según la editorial | inferido |
| **Género** | **MangaDex, palabras clave o modelo** | **sugerido o estimado** |
| **Clasificación** | **cadena de 3 capas** | **verificado o estimado** |

Los nueve campos del formulario llegan propuestos. Ninguno se guarda sin que el
administrador pulse «Usar estos datos».

---

## 3 · Arquitectura

Seis endpoints, cada uno con una responsabilidad:

| Archivo | Líneas | Función |
|---|---|---|
| `buscar_metadatos.php` | 425 | Consulta ComicVine, ordena ediciones, mapea editoriales, detecta duplicados y corrige erratas |
| `traducir.php` | 166 | Traduce la descripción EN → ES |
| `descargar_portada.php` | 116 | Trae la imagen al servidor |
| `crear_editorial.php` | 73 | Da de alta una editorial ausente |
| `buscar_clasificacion.php` | 197 | Capa 1: MangaDex verificado por año |
| `estimar_clasificacion.php` | 350 | Capas 2 y 3: Wikipedia y modelo (clasificación **y género**) |
| `resumir_descripcion.php` | 251 | Resume la descripción si se pasa de largo |

La interfaz vive en `crud.php` (modal + lógica de encadenado) y los estilos en
`admin/admin.css`.

### Orden de ejecución

```
Al escribir (350 ms después de parar)
    └── buscar_metadatos.php ──► lista de ediciones

Al elegir una edición (en paralelo)
    ├── traducir.php            ──► descripción en español
    │       └── resumir_descripcion.php  (solo si pasa de 450 caracteres)
    ├── descargar_portada.php   ──► imagen en uploads/
    └── buscar_clasificacion.php ──► clasificación y género
            └── si no resuelve: estimar_clasificacion.php
```

El resumen va **encadenado** tras la traducción, no en paralelo, porque necesita
el texto ya traducido. Entra dentro del mismo paso de la pantalla de carga, así
que no añade una espera visible.

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

> **Matiz tras añadir el género:** la cadena se detiene para la *clasificación*,
> pero la capa 3 se consulta igualmente si falta el *género*, porque ni MangaDex
> ni Wikipedia lo resuelven para cómics occidentales. Detalle en §5.2.

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

Último recurso, solo si hay clave configurada. Devuelve **clasificación y
género en la misma llamada** (§5.2). Tres salvaguardas:

- `temperature: 0`, para que sea lo más determinista posible
- Solo acepta etiquetas de las listas cerradas (4 clasificaciones, 18 géneros);
  cualquier otra cosa **se descarta** en vez de adaptarse
- Se le pide elegir la opción **más restrictiva** ante la duda

Si uno de los dos campos no valida, el otro se conserva: un género inventado no
tira abajo una clasificación correcta.

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

Tres vías, en orden:

1. **MangaDex** — etiquetas del grupo `genre`. Los grupos `theme` y `content`
   se ignoran: describen elementos de la trama, no el género.
2. **Palabras de la descripción** ya traducida, contrastadas con los géneros
   que **ya existen** en el catálogo, para no multiplicar variantes.
3. **Modelo de lenguaje** — a partir de la sinopsis, cuando las dos anteriores
   fallan.

Las dos primeras cubren bien el manga, pero un cómic occidental no está en
MangaDex y sus sinopsis rara vez contienen la palabra exacta del género. El
resultado era que el campo quedaba vacío justo en el caso más común:

> *La descripción no da pistas claras: elígelo tú.*

La tercera vía cubre ese hueco. Se pide en **la misma llamada** que ya hacía la
capa 3 para la clasificación: es la misma lectura de la misma sinopsis, así que
no añade ni una petición ni latencia perceptible.

**Resultados verificados contra el endpoint real:**

| Cómic | Género devuelto | Quién lo resolvió |
|---|---|---|
| Watchmen | Superhéroes | modelo |
| Cyberpunk 2077: Big City Dreams | Cyberpunk | modelo |
| Saga | Ciencia ficción | modelo |
| Berserk | Fantasía oscura | modelo (la clasificación la dio Wikipedia) |

#### Tres restricciones deliberadas

**El modelo no puede inventar géneros.** Solo se acepta uno de los 18 del
formulario. Si devuelve «Western» o «Drama psicológico», se descarta en vez de
adaptarlo: adaptar a ojo es justo donde se cuelan los errores.

**Las tildes se comparan, pero no se devuelven del modelo.** El modelo acierta
la palabra y falla el acento con frecuencia. La comparación ignora tildes y
mayúsculas, pero lo que se devuelve es **siempre** la forma exacta de la lista,
porque el `<option>` compara por valor literal: `Ciencia ficcion` sin tilde no
seleccionaría nada.

**Si ya se sabe el género, no se pregunta.** El frontend envía el género que las
capas anteriores hayan resuelto; si viene, el modelo no lo estima.

#### Por qué el género siempre se marca como estimado

Ninguna de las tres fuentes publica el género como dato verificable, ni siquiera
MangaDex (sus etiquetas las ponen los usuarios). Por eso se presenta en ámbar,
igual que la clasificación estimada:

> ⚠ **Estimado** por qwen3.8-27b: Es una deconstrucción directa del género de
> superhéroes. **Revísalo.**

Una excepción importante del encadenado: **Wikipedia resuelve la clasificación
pero nunca el género.** Antes, que la capa 2 acertara dejaba el género sin
estimar para siempre, porque el modelo ya no llegaba a ejecutarse. Ahora la capa
3 se consulta cuando falta *cualquiera* de los dos.

### 5.3 · Orden de las ediciones

Un mismo título devuelve muchas ediciones y **el orden de la API no es fiable**.
Comprobado con consultas reales:

| Búsqueda | Qué devolvía primero | Dónde estaba la original |
|---|---|---|
| `all star superman` | Panini Verlag (alemán), 2006 | **5.ª** — DC 2006, 12 números |
| `watchmen` | Abril (brasileño), 1999 | **3.ª** — DC 1986 |

El nombre no sirve para distinguirlas: las ocho ediciones de *All Star Superman*
se llaman **exactamente igual**. Por eso el distintivo «exacto» aparecía en
todas y no informaba de nada.

```
18139 | All Star Superman | DC Comics          | 2006 | 12 núms  ← la original
41739 | All Star Superman | Panini Verlag      | 2006 |  6 núms  ← alemán
78614 | All Star Superman | Planeta DeAgostini | 2009 |  1 núm   ← español
43166 | All Star Superman | DC Comics          | 2011 |  1 núm   ← recopilatorio
```

Lo que sí las separa son tres señales, que ahora se puntúan:

| Señal | Peso | Por qué |
|---|---|---|
| Título idéntico a lo escrito | 1000 | Separa *All Star Superman* de *Man Of Steel: All-Star Superman* |
| Editorial **no** traductora | 300 | Panini, Planeta, Saldapress, Glénat… traducen; la original está en otra parte |
| Proporción de entregas | 0–250 | La edición completa tiene más que un recopilatorio (145 vs 25) |
| Un solo número | −120 | Casi siempre es un tomo suelto |
| Año más antiguo | 0–80 | La original precede a sus traducciones |

Es una **regla determinista**, no un modelo: con los mismos datos puntúa igual.

**Ninguna edición se descarta**, solo se reordenan — registrar la edición de
Planeta puede ser justo lo que se quiere. La mejor puntuada se marca como
`original`, y la decisión sigue siendo del administrador.

| Búsqueda | Antes | Ahora |
|---|---|---|
| all star superman | 5.ª | **1.ª** ✓ |
| watchmen | 3.ª | **1.ª** ✓ |
| invincible | 1.ª | 1.ª ✓ |
| saga | 1.ª | 1.ª ✓ |

### 5.4 · Estado de publicación

ComicVine **no publica** si una serie terminó, pero se deduce comparando el
último número con el total:

| Condición | Estado |
|---|---|
| 1 solo número | Tomo único |
| `last_issue >= count_of_issues - 1` | Publicación terminada |
| resto | En publicación |

Verificado: *Invincible* tiene 145 fichas, contando el #0, y `last_issue` es el
#144. La numeración principal propuesta es **1–144**, no 1–145. El estado sigue
siendo una inferencia que debe revisarse, no un campo publicado por la API.

### 5.5 · Editorial ausente

Si la fuente dice que la publicó una editorial que no está en el catálogo, la
**única** salida es crearla.

No se ofrece «elige otra de la lista»: asignarle a un cómic una editorial que no
lo publicó sería guardar un dato falso. El modal lo explica:

> *No está en tu catálogo. Es la editorial que publicó esta obra, así que no
> puede sustituirse por otra.*

**Actualización del Sprint 6:** «Crear» abre un diálogo que conserva el nombre
de la fuente y busca propuestas de logo. No guarda ninguna hasta seleccionar
una y confirmar. Se puede consultar su fuente, descartarla o subir un logo
manual. Si la fuente no trae editorial, «Escribir editorial» permite introducir
el nombre; también hay un acceso desde el formulario manual del registro.

La creación deja el nombre seleccionado sin recargar el formulario ni restablecer
género o clasificación. Se permite crear sin logo en este flujo y añadirlo más
tarde. Si la editorial ya existe, incluso con tildes o alias diferentes, se
reutiliza su id y logo. Los nombres parecidos requieren una decisión expresa.

El servicio común está en `controlador/editorial_servicio.php` y los logos en
`controlador/logo_editorial.php`; los endpoints de búsqueda y alta exigen rol
administrador. Las imágenes remotas solo se descargan por HTTPS de los hosts
`upload.wikimedia.org` y `thumb.wikimedia.org`. Se validan bytes y píxeles y
se vuelven a codificar en PNG. Los archivos manuales aceptan PNG/JPG/WebP/GIF
hasta 5 MB y 16 megapíxeles; los SVG remotos se obtienen como PNG de Wikimedia.
El logo se obtiene de [P154 de Wikidata](https://www.wikidata.org/wiki/Property:P154)
o del campo explícito de la ficha, y su miniatura mediante
[Imageinfo](https://www.mediawiki.org/wiki/API:Imageinfo). No se usa la foto
general de la página: en la prueba real de Kodansha esa foto era su sede.

Evidencia: `tests/editoriales.php` (32 casos), `tests/editoriales_ui.py` (42 casos)
y `tests/registro_validacion_ui.py` (10 casos). Incluyen los dos caminos de
editorial ausente, conservación manual, concurrencia, fallo de fuente, subida
manual, validación de imágenes, reutilización y limpieza tras un error SQL.

### 5.6 · Resumen de la descripción

ComicVine devuelve descripciones de longitud muy desigual y la traducción es
**literal**, así que lo largo sigue largo. Medido en el catálogo: Watchmen tenía
**634 caracteres** frente a los ~320 del resto, y ocupaba el doble de líneas en
la ficha.

> **Aclaración:** el límite de 500 que aparece en `traducir.php` es el de
> **MyMemory por petición**, no de la base de datos: la columna `descripcion` es
> `TEXT`. El problema no era que no cupiera, sino que se leía mal.

Si el texto traducido supera **450 caracteres**, un modelo lo resume.

| Cómic | Antes | Después | Tiempo |
|---|---|---|---|
| Watchmen | 634 | **438** | 1.2 s |
| Berserk *(prueba)* | 604 | **442** | 0.8 s |
| Saga *(prueba)* | 531 | **371** | 0.6 s |
| Ultimate Spiderman | 77 | **sin tocar** | 0.04 s |

#### Cuatro validaciones en el servidor

No se confía en que el modelo cumpla las instrucciones. Un resumen se **descarta**
—y se usa el original— si:

| Comprobación | Por qué |
|---|---|
| Pasa de 500 caracteres | Recortarlo repetiría el problema que esto resuelve |
| No es más corto que el original | No resumió nada |
| Baja de 80 caracteres | Se quedó en nada útil |
| No parece español | El modelo ignoró el idioma |

La detección de idioma compara la frecuencia de palabras funcionales (`el`, `la`,
`de`, `que`…) contra las inglesas (`the`, `of`, `and`…). No es un detector
general: solo cubre el caso real de que responda en inglés.

#### El original nunca se pierde

El endpoint devuelve **siempre** el texto completo junto al resumen, y el modal
ofrece «Usar la descripción completa», que lo restaura sin pedir nada al
servidor. Un resumen es una estimación: se marca en ámbar y el campo es editable.

#### Si falla, el registro sigue

Comprobado retirando `GROQ_API_KEY` del `.env`: el endpoint devolvió `ok: true`
con el texto intacto y el aviso *«No se pudo resumir: se usa la descripción
completa»*. Una mejora opcional no puede volverse un requisito para registrar.

### 5.7 · Corrección de erratas

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

### 5.8 · Respuestas que llegan tarde

Al escribir se lanza una búsqueda por cada estado intermedio del título. Si una
de las primeras tarda más que la última, **su respuesta llega después y pisa el
resultado bueno**: el campo decía `watchmen` y debajo seguía el aviso de que no
se encontró `watcj`.

No era el buscador quedándose quieto, sino una carrera entre peticiones. Tres
medidas:

1. **Turnos.** Cada búsqueda lleva un número. Al volver la respuesta, si ya hay
   otra en marcha, llega tarde y se descarta.
2. **Aborto.** La petición anterior se cancela con `AbortController`, para no
   esperar por algo que de todos modos se ignorará.
3. **Limpieza inmediata.** El aviso del título anterior se borra al pulsar la
   tecla, no al terminar la búsqueda nueva.

Comprobado simulando la carrera con los tiempos invertidos:

```
SIN arreglo  →  No se encontró «watcj»      ← el fallo
CON arreglo  →  No se encontró «watchmen»   ← correcto
```

Un segundo fallo del mismo origen: `ultimaBusqueda` se fijaba **antes** de la
petición, así que un fallo de red dejaba ese título marcado como «ya buscado» y
no se reintentaba nunca. Ahora un fallo lo libera.

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
- **El género es siempre una estimación**, incluso vía MangaDex: sus etiquetas
  las ponen los usuarios. Se marca en ámbar por eso.
- **El orden de ediciones es una heurística.** Acierta en los cuatro casos
  probados, pero una serie cuya edición original tenga menos números que una
  recopilación extranjera podría ordenarse mal. No descarta nada, así que el
  coste de un fallo es tener que bajar un puesto en la lista.
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
| `Invincible` | 8 ediciones; Image 2003 (145 núms) primera, marcada `original` |
| `all star superman` | 8 ediciones; **DC 2006** primera, no la de Panini |
| `watchmen` | **DC 1986** primera, no la de Abril; género → Superhéroes estimado |
| `Berserk` | avisa que ya existe (Young Animal · 1989) |
| `Vagabond` | Manga, +18 **verificado** por MangaDex |
| `invicible` | sugiere «Invincible» |
| `xyzqw123` | sin resultados, registro manual |
| escribir rápido y corregir | el aviso corresponde **siempre** al título final |

### Endpoints por separado

```bash
# Metadatos
curl "http://localhost:8080/controlador/buscar_metadatos.php?titulo=Invincible"

# Clasificación verificada
curl "http://localhost:8080/controlador/buscar_clasificacion.php?titulo=Berserk&anio=1989"

# Estimación de clasificación Y género
curl -X POST --data-urlencode "titulo=Saga" --data-urlencode "tipo=Comic" \
     --data-urlencode "descripcion=Opera espacial con desnudos y violencia." \
     "http://localhost:8080/controlador/estimar_clasificacion.php"

# Con el género ya conocido: el modelo no lo estima
curl -X POST --data-urlencode "titulo=Berserk" --data-urlencode "tipo=Manga" \
     --data-urlencode "genero=Fantasía oscura" \
     --data-urlencode "descripcion=Guts lucha contra apóstoles demoníacos." \
     "http://localhost:8080/controlador/estimar_clasificacion.php"
```

Respuesta esperada del primero:

```json
{
  "ok": true,
  "datos": {
    "clasificacion": "+18",
    "origen":        "estimado",
    "fuente":        "qwen3.8-27b",
    "genero":        "Ciencia ficción",
    "genero_origen": "estimado"
  }
}
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
| `9581e60` | La estimación dejaba de borrarse al terminar la traducción |
| `914d421` | Pantalla de carga mientras llegan los datos |
| `709099e` | Editorial, estado de publicación y vista previa de portada |
| `247d4f9` | La portada rellena su hueco con un difuminado propio |
| `1d39420` | Las ediciones se ordenan: la original primero |
| `aee222e` | Una respuesta tardía ya no deja clavado el aviso |
| `162ccce` | El género se estima con el modelo cuando nada más lo resuelve |

---

*Todos los datos sobre las APIs de este documento se verificaron con consultas
reales, no con suposiciones. Donde una fuente no sirve, se indica la prueba que
lo demuestra.*

## 12 · Operación y cierre del plan de editoriales y capítulos

El plan `PLAN_Editoriales_Capitulos_Registro.md` completa registro obligatorio,
creación editorial, propuestas por edición, numeración, fechas parciales,
validación de comprimidos, lotes reanudables, logos y reemplazo explícito.
La [guía de operación y verificación](docs/OPERACION_Y_VERIFICACION.md) reúne
los pasos del administrador, las migraciones y las pruebas reproducibles.

La subida usa **una transacción por entrega**, con validación completa antes de
publicar imágenes. Los recibos de la migración 03 identifican datos y SHA-256 del
intento para reconocer respuestas perdidas. El manifiesto del lote conserva datos
en la pestaña y requiere volver a elegir los archivos pendientes tras una recarga.
Los fallos no eliminan los capítulos del lote ya guardados.

Los capítulos pueden guardar día/mes/año o solo mes/año mediante la migración 02.
Los tomos y grapas conservan numeraciones distintas; las ediciones de una fuente
no se mezclan. Cuando falta un título individual, un recopilatorio con contenido
confirmado puede aportar su nombre, expresamente identificado. Se conserva el
original y se señala la traducción orientativa; sin datos sigue el nombre
descriptivo revisable. Una coincidencia de nombre no verifica el contenido del CBR.

El reemplazo está en **Editar capítulo → Reemplazar archivo del capítulo**.
Mantiene los metadatos guardados y sustituye todas las páginas tras confirmación.
Prepara una carpeta nueva, elimina las filas anteriores antes de insertar y
confirma la operación con un recibo independiente de la migración 04. Las imágenes
antiguas se retiran después del commit, únicamente si no siguen referenciadas.
Un fallo revierte filas y conserva imágenes anteriores. Las revisiones y el bloqueo
de fila evitan sobrescrituras entre pestañas. Reintentar el mismo archivo recupera
el recibo de reemplazo sin duplicar páginas; la subida normal sigue bloqueando solapes.

La migración 04 está aplicada localmente y no altera capítulos existentes.
`tests/reemplazo_capitulo.py` aporta 37 comprobaciones con CBR/CBZ reales,
rollback, concurrencia, recibos, limpieza de archivos, cancelación y móvil.
Los fixtures se retiran y se conserva el catálogo del usuario.
La verificación de esta entrega suma 144 casos: reemplazo 37, subida 45,
lotes 33 e interfaz/edición de fechas 29. Los criterios del plan están marcados
con su evidencia; los asuntos fuera de alcance permanecen separados.
