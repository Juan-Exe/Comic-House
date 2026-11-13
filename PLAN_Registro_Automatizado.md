# Plan · Registro automatizado de cómics

**Proyecto:** Comic House · Panel de administración
**Alcance:** únicamente la pantalla **Registrar cómic** (`crud.php`)
**Fecha:** 13 de noviembre de 2026

> Fuera de alcance por ahora: editar cómic, subir capítulos, editoriales,
> el buscador del sitio público. Se abordarán después, uno a uno.

---

## 1 · Problema

Registrar un cómic obliga a llenar nueve campos a mano, buscando los datos
en otras páginas y copiándolos. Es lento y propenso a errores: un año mal
tecleado, una editorial escrita de dos formas distintas, un título duplicado.

**Objetivo:** que registrar una obra *que ya existe* sea escribir el título,
revisar lo que el sistema encontró y pulsar guardar. Para una obra que no
existe en ninguna fuente, el formulario sigue funcionando como hoy.

---

## 2 · Qué puede y qué no puede resolverse solo

Verificado contra la API de ComicVine (clave propia, pruebas reales):

| Campo | Fuente | Resultado |
|---|---|---|
| Título | API `name` | exacto |
| Año | API `start_year` | exacto |
| Descripción | API `description` | **en inglés**, hay que traducir |
| Portada | API `image.medium_url` | URL, hay que descargarla |
| Capítulos | API `count_of_issues` | exacto |
| Editorial | API `publisher.name` | nombre suelto, hay que mapear |
| Tipo | **no existe** | se infiere de la editorial |
| Género | **no existe** | se sugiere desde la descripción |
| Clasificación | **no existe en ComicVine** | fuente secundaria verificada, o manual |

### Por qué faltan tres campos

Se comprobó en tres niveles antes de darlo por cerrado:

1. El recurso `volume` (la serie) no trae género ni clasificación.
2. El recurso `issue` (un número suelto) tampoco.
3. Los **20 recursos** de la API no incluyen ninguno sobre edad o madurez.

Se probó también el campo `concepts`, que parecía prometedor. Devuelve
etiquetas de catalogación interna —*Variant Cover*, *Homage Covers*,
*Crossover*— que no describen el contenido de la obra. **Descartado.**

---

## 3 · Principios de diseño

Estos principios se derivan del trabajo de E1 y de la guía *¿Regla o IA?*,
y explican por qué cada decisión es como es.

### 3.1 · Determinista antes que generativo

Todo lo automático se resuelve con **reglas**: consultar una fuente, comparar
texto, mapear a una columna. Nada interpreta ni redacta.

Razón: una regla devuelve siempre lo mismo con los mismos datos, responde al
instante y se puede auditar. Un modelo generativo podría inventar un año o una
editorial que no existen, y en un catálogo ese error es difícil de detectar.

La única excepción es la **traducción**, y aun así es determinista: un servicio
de traducción con el mismo texto devuelve el mismo resultado.

### 3.2 · Nunca inventar un valor para rellenar un hueco

Si una fuente no trae un dato, el campo queda vacío y se llena a mano.
Jamás se sustituye por algo parecido.

**Caso crítico — la editorial.** Si la API dice que una obra la publicó
*Saldapress* y esa editorial no está en el catálogo, la única salida válida es
**crearla**. Ofrecer «elige otra de la lista» invitaría a asignarle a un cómic
una editorial que no lo publicó: un dato falso guardado como verdadero.

### 3.3 · La persona confirma antes de guardar

El sistema propone, el administrador decide. Nada se guarda sin una
confirmación explícita.

### 3.4 · La clasificación no se infiere jamás

`ATP`, `+13`, `+16`, `+18` es control parental. Un error pone contenido adulto
frente a un menor. No hay fuente que lo provea y tampoco debería inferirse:
es el caso de «error grave → una persona decide».

---

## 4 · Flujo propuesto

```
El administrador escribe el título
            ↓
   ¿La fuente lo encuentra?
            ↓
    ┌───────┴────────┐
   SÍ                NO
    ↓                 ↓
Modal de        ¿Se parece a
confirmación    algo conocido?
    ↓            ┌────┴────┐
Revisa y        SÍ         NO
confirma         ↓          ↓
    ↓        Sugiere     Formulario
  Guarda     corrección   manual
```

### El modal de confirmación

Separa visualmente lo verificado de lo que requiere decisión:

```
┌─ Datos encontrados ─────────────────────────────┐
│                                                 │
│  VERIFICADO EN LA FUENTE                        │
│  Título       Invincible                        │
│  Año          2003                              │
│  Editorial    Image → Image Comics  [confirmar] │
│  Capítulos    1 – 145                           │
│  Portada      [miniatura]                       │
│  Descripción  (traducida al español)            │
│                                                 │
│  ─────────────────────────────────────────────  │
│                                                 │
│  REQUIERE TU DECISIÓN                           │
│  Tipo         ● Cómic   ○ Manga                 │
│               sugerido por la editorial         │
│  Género       [ Superheroes ▾ ]                 │
│               sugerido por la descripción       │
│  Clasificación  ATP  +13  +16  +18              │
│               sin sugerencia: decides tú        │
│                                                 │
│           [ Cancelar ]  [ Usar estos datos ]    │
└─────────────────────────────────────────────────┘
```

---

## 5 · Reglas de los campos derivados

### 5.1 · Tipo (Cómic / Manga)

Regla por editorial conocida:

| Editorial | Tipo |
|---|---|
| Shueisha, Kodansha, Shogakukan, Hakusensha, Young Animal, Square Enix | Manga |
| DC, Marvel, Image, Dark Horse, IDW, Boom!, Vertigo | Cómic |
| cualquier otra | sin sugerencia |

Preseleccionado y editable. La editorial que publica determina el formato con
alta fiabilidad, pero el administrador puede corregirlo.

### 5.2 · Género

Se busca en la descripción (ya traducida) palabras asociadas a los géneros
que **ya existen en el catálogo**, para no multiplicar variantes del mismo
género:

| Si la descripción menciona | Sugiere |
|---|---|
| superhéroe, poderes, héroe, villano | Superheroes |
| espada, demonio, oscuro, medieval | Fantasia oscura |
| espacio, futuro, nave, cyberpunk | ciencia ficción |

Si ninguna coincide, el campo queda vacío y el administrador elige o crea uno.
Nunca se inventa un género que no describa la obra.

### 5.3 · Clasificación

**Sin sugerencia, siempre.** El modal lo indica de forma explícita para que no
parezca un olvido del sistema, sino una decisión de diseño.

**Se intentó automatizarla y se descartó con evidencia.** El campo `concepts`
de la API incluye a veces etiquetas como *Sexual Violence*, que parecían servir
para deducir la edad. Al probarlo contra obras de clasificación conocida:

| Obra | Clasificación real | Concepts devueltos |
|---|---|---|
| Berserk | +18 | **0** |
| The Walking Dead | +18 | **0** |
| Archie | ATP | 9, ninguno sobre edad |

El campo está vacío en la mayoría de las obras, incluidas las más explícitas.
Inferir de ahí marcaría contenido +18 como apto para todo público: exactamente
el error grave que este campo debe evitar.

**Solución: una segunda fuente, verificada.** MangaDex sí publica
`contentRating` y etiquetas de género. Pero al probarla devuelve obras
distintas con el mismo título, así que **no se usa sin comprobar**:

| Consulta | MangaDex devuelve | Decisión |
|---|---|---|
| Berserk (1989) | Berserk, 1989, `erotica` | ✅ se sugiere **+18** |
| Vagabond (1999) | Vagabond, 1999, `erotica` | ✅ se sugiere **+18** |
| Invincible (2003) | otra obra, 2015 | ❌ se descarta |
| Berserk (2018) | *Boushoku no Berserk* | ❌ se descarta |

**La verificación cruzada por año es lo que lo hace fiable.** Si el año que
devuelve MangaDex no coincide con el de ComicVine (±1), no es la misma obra y
no se sugiere nada.

Equivalencia de escalas: `safe`→ATP, `suggestive`→+13, `erotica` y
`pornographic`→+18.

Aun verificada, **la clasificación no se da por buena**: el modal muestra de
dónde viene («sugerido por MangaDex, título y año coinciden») y pide
confirmarla. Sigue siendo el campo donde un error es grave.

### Tres capas, de más fiable a menos

MangaDex solo acierta en manga. Para cómic occidental **no existe un dato
público estandarizado**: Marvel usa «Rated T+», DC usa «Teen», Image no marca
nada, y ninguna base lo recopila. Por eso se encadenan tres fuentes:

| Capa | Fuente | Origen del dato |
|---|---|---|
| 1 | MangaDex `contentRating`, verificado por año | **verificado** |
| 2 | Wikipedia: demografía editorial y sellos de madurez | **verificado** |
| 3 | Modelo de lenguaje sobre la sinopsis *(requiere clave)* | **estimado** |

Se detiene en la primera que responde, y **el origen viaja con el dato**: un
«verificado» sale de una fuente que lo publica; un «estimado» lo deduce un
modelo y puede equivocarse. El modal los distingue —el estimado va en ámbar y
avisa «no es un dato verificado»— porque presentarlos igual sería engañoso.

**Sobre la capa 2 y los falsos positivos.** El primer intento buscaba palabras
sueltas y fallaba de forma peligrosa:

| Obra | Palabra hallada | De dónde salía |
|---|---|---|
| Invincible | «Teen» | *Teen Team*, un grupo de personajes |
| Saga | «explicit» | *explicitly addressed*, sobre etnia |

Exigiendo contexto (`seinen manga`, `rated M`, `for mature readers`) los falsos
positivos bajaron a cero. Verificado: Berserk y Vagabond → +18 por «seinen
manga»; Invincible, Saga y Bone → sin sugerencia, que es la respuesta honesta.

**La capa 3 es opcional.** Sin `GROQ_API_KEY` configurada, el sistema se
comporta como antes: sugiere lo verificable y nada más. Con clave, estima la
clasificación de los cómics occidentales marcándola claramente como estimación.
Se pide al modelo `temperature: 0`, solo una etiqueta de la escala, y elegir la
opción **más restrictiva** ante la duda. Si devuelve algo fuera de la escala se
descarta en vez de adaptarlo.

---

## 6 · Historias de usuario y criterios de aceptación

El usuario es el **administrador** de Comic House.

---

### HU-01 · Registrar una obra conocida con un clic

> Como administrador, quiero escribir el título de un cómic conocido y que el
> sistema traiga sus datos, para registrarlo sin buscar información en otras
> páginas.

**Criterios de aceptación**

- [x] **CA-01.1** Al escribir 3 o más caracteres, la búsqueda se dispara sola
      600 ms después de dejar de teclear, sin pulsar ningún botón.
- [x] **CA-01.2** ~~Si hay coincidencia exacta de título, se abre el modal~~
      **Corregido tras probarlo:** un mismo título tiene varias ediciones
      («Invincible» existe en Image, Kodansha, Delcourt, Cross Cult…), así que
      el modal **nunca se abre solo**. Se listan las ediciones encontradas,
      con su editorial destacada, y el administrador elige cuál corresponde.
- [x] **CA-01.3** El modal muestra título, año, editorial, capítulos, portada
      y descripción traducida.
- [x] **CA-01.4** Al confirmar, los campos del formulario quedan rellenos y el
      administrador solo pulsa «Registrar».
- [x] **CA-01.5** La operación completa (escribir → elegir edición → confirmar
      → guardar) no exige abrir ninguna otra página.
- [x] **CA-01.6** Cuando un título tiene varias ediciones, se muestran todas
      con su editorial y año, porque es lo que las distingue.
- [x] **CA-01.7** El tipo se infiere de la editorial de **la edición elegida**,
      no de la primera: elegir la de Kodansha sugiere Manga; la de Image,
      Cómic.

---

### HU-02 · Revisar antes de guardar

> Como administrador, quiero ver qué encontró el sistema y de dónde salió cada
> dato, para no guardar información equivocada en el catálogo.

**Criterios de aceptación**

- [x] **CA-02.1** El modal distingue visualmente lo verificado en la fuente de
      lo que requiere decisión.
- [x] **CA-02.2** Cada campo derivado indica en qué se basó la sugerencia
      («sugerido por la editorial», «sugerido por la descripción»).
- [x] **CA-02.3** Todos los campos son editables antes de confirmar.
- [x] **CA-02.4** «Cancelar» cierra el modal sin tocar el formulario.
- [x] **CA-02.5** Ningún dato se guarda sin confirmación explícita.
- [x] **CA-02.6** Ni el modal ni la lista abren barras de desplazamiento
      propias: crecen con su contenido y la página se desplaza con
      normalidad.

---

### HU-03 · Registrar una obra que no está en la fuente

> Como administrador, quiero poder registrar un cómic aunque la fuente externa
> no lo conozca, porque el catálogo no depende de una API ajena.

**Criterios de aceptación**

- [x] **CA-03.1** Si no hay resultados, el formulario sigue funcionando
      exactamente como antes, con todos los campos vacíos y editables.
- [x] **CA-03.2** Se muestra un aviso legible explicando que no se encontró,
      sin bloquear el registro manual.
- [x] **CA-03.3** Ningún campo se rellena con un valor inventado.
- [x] **CA-03.4** Si la API está caída o sin clave, el comportamiento es el
      mismo: aviso y registro manual. **Probado** con clave inválida
      (HTTP 401) y con clave vacía: ambos avisan y dejan registrar a mano.

---

### HU-04 · Corregir un error de tipeo

> Como administrador, quiero que el sistema me avise si escribí mal el título,
> para no registrarlo a mano creyendo que la obra no existe.

**Criterios de aceptación**

- [x] **CA-04.1** Si no hay coincidencia exacta pero sí un título parecido, se
      sugiere: «No se encontró X. ¿Querías decir Y?».
- [x] **CA-04.2** La sugerencia se aplica con un clic y relanza la búsqueda.
- [x] **CA-04.3** Un texto sin parecido real (`xyzqw123`) **no** genera
      sugerencia: se admite que no existe.
- [x] **CA-04.4** La comparación es por similitud de texto, no por
      interpretación de significado.

*Estado: implementado y verificado — `invicible → Invincible`,
`berserck → Berserk`, `xyzqw123 → sin sugerencia`.*

---

### HU-05 · Editorial que no está en el catálogo

> Como administrador, quiero crear la editorial que publicó la obra cuando no
> exista, porque no puedo asignarle una editorial que no le corresponde.

**Criterios de aceptación**

- [x] **CA-05.1** Si la editorial no está en el catálogo, se ofrece **crearla**
      con el nombre que devolvió la fuente.
- [x] **CA-05.2** **No** se ofrece sustituirla por otra del catálogo.
- [x] **CA-05.3** Al crearla, queda seleccionada y disponible para futuras obras.
- [x] **CA-05.4** Si la fuente da un nombre parcial («Image») y existe uno
      equivalente («Image Comics»), se muestra la equivalencia para que el
      administrador la confirme; no se asume.
- [x] **CA-05.5** El cómic guardado queda con `editorial_id` apuntando a una
      fila real, no a texto suelto.

---

### HU-06 · Descripción en español

> Como administrador, quiero que la descripción llegue en español, para que el
> catálogo sea consistente sin reescribir cada sinopsis.

**Criterios de aceptación**

- [x] **CA-06.1** La descripción en inglés se traduce automáticamente.
- [x] **CA-06.2** Los textos de más de 500 caracteres se parten por frases y se
      traducen en bloques (límite verificado del servicio).
- [x] **CA-06.3** Si la traducción falla, se muestra el texto original con un
      aviso, en vez de dejar el campo vacío.
- [x] **CA-06.4** El texto traducido es editable antes de guardar.
- [x] **CA-06.5** Se limpia el HTML que trae la fuente.
- [x] **CA-06.6** Si el texto ya está en español, no se traduce: se detecta
      por marcas del idioma y se deja tal cual.

---

### HU-07 · Portada automática

> Como administrador, quiero que la portada se descargue sola, para no buscar
> y subir la imagen a mano.

**Criterios de aceptación**

- [x] **CA-07.1** La portada se descarga al servidor, en `uploads/`, como
      cualquier otra imagen del catálogo.
- [x] **CA-07.2** El nombre del archivo se genera sin colisiones.
- [x] **CA-07.3** Se valida que sea una imagen real antes de guardarla
      (`getimagesizefromstring`, no solo la extensión de la URL).
- [x] **CA-07.4** Si la descarga falla, el campo de subida manual queda activo.
- [x] **CA-07.5** El modal muestra una miniatura antes de confirmar.
- [x] **CA-07.6** Solo se descargan imágenes `https` del dominio de la fuente:
      la ruta no sirve para traer cualquier archivo de internet al servidor.

---

### HU-08 · No duplicar obras

> Como administrador, quiero que el sistema me avise si el cómic ya está
> registrado, antes de perder tiempo llenando el formulario.

**Criterios de aceptación**

- [x] **CA-08.1** Si el título ya existe en el catálogo, se avisa al abrirlo,
      diciendo **qué edición** se tiene ya registrada.
- [x] **CA-08.2** En la lista de coincidencias, las ya registradas se marcan,
      y al pasar el cursor se indica si es la misma edición u otra.
- [x] **CA-08.3** La comparación ignora mayúsculas y espacios sobrantes.
      **Probado**: «berserk», «BERSERK» y «Berserk» dan el mismo resultado.
- [x] **CA-08.4** El aviso no bloquea: los datos se rellenan igual y el
      administrador decide. El `UNIQUE` del esquema es la barrera final.

---

### HU-09 · El sistema nunca inventa

> Como administrador, quiero confiar en que ningún dato fue inventado, para
> poder responder por el contenido del catálogo.

**Criterios de aceptación**

- [x] **CA-09.1** La clasificación por edad **nunca** se sugiere.
- [x] **CA-09.2** El género solo se sugiere si la descripción contiene
      evidencia; si no, queda vacío.
- [x] **CA-09.3** El año se descarta si cae fuera de 1900–año actual.
- [x] **CA-09.4** Ningún campo se rellena con un valor «aproximado».
- [x] **CA-09.5** Cada sugerencia indica su origen.

---

## 7 · Orden de implementación

| Fase | Qué incluye | Historias |
|---|---|---|
| ~~0~~ | ~~Búsqueda automática y corrección de tipeo~~ | ~~HU-04~~ ✓ hecho |
| ~~1~~ | ~~Modal de confirmación con los datos verificados~~ | ~~HU-01, HU-02~~ ✓ hecho |
| ~~2~~ | ~~Traducción de la descripción~~ | ~~HU-06~~ ✓ hecho |
| ~~3~~ | ~~Descarga de la portada~~ | ~~HU-07~~ ✓ hecho |
| 4 | Creación de editorial faltante | HU-05 |
| 5 | Sugerencia de tipo y género | HU-09 |
| ~~6~~ | ~~Repaso de casos límite~~ | ~~HU-03, HU-08~~ ✓ hecho |

Cada fase se verifica contra sus criterios antes de pasar a la siguiente.

---

## 7.1 · Lo aprendido en la Fase 1

**Un título no identifica una obra.** «Invincible» devuelve 8 ediciones de
editoriales distintas. Abrir el modal con la primera era una suposición
equivocada: el sistema no puede saber cuál quiere el administrador. La lista
de ediciones dejó de ser un extra para casos ambiguos y pasó a ser el paso
normal del flujo.

**La editorial es la clave de todo.** Distingue una edición de otra y además
determina el tipo (Kodansha → Manga, Image → Cómic). Por eso se muestra
destacada en la lista, no como dato secundario.

**Las barras de desplazamiento internas rompen la estética.** El modal y la
lista ahora crecen con su contenido.

---

## 8 · Riesgos conocidos

| Riesgo | Mitigación |
|---|---|
| La API deja de responder o se bloquea en la red | El formulario manual sigue intacto (HU-03) |
| El traductor tiene límite de 500 caracteres | Partir por frases (CA-06.2) |
| El traductor tiene cuota diaria | Si falla, mostrar el original (CA-06.3) |
| La clave de API queda expuesta | Vive en `.env`, fuera del repositorio |
| Una editorial se crea mal escrita | El administrador confirma el nombre (CA-05.4) |
| Las portadas ocupan disco | Se descarga el tamaño `medium`, no el original |

---

## 9 · Qué queda fuera y por qué

- **Buscar en internet de forma generativa.** Sería más completo, pero tarda
  segundos, puede inventar datos y no es reproducible. Contradice el principio
  3.1. Si en el futuro hace falta, iría como *sugerencia* claramente marcada.
- **Clasificación automática por edad.** No hay fuente, no hay estándar común
  y el error es grave.
- **Otras pantallas.** Editar cómic, subir capítulos y editoriales se abordarán
  después, aplicando los mismos principios.

---

## 10 · Estado final

**Las 6 fases implementadas y los 47 criterios verificados.**

De los 9 campos del formulario:

| Origen | Campos |
|---|---|
| Automático desde ComicVine | título, año, editorial, capítulos, portada, descripción (traducida) |
| Fuente secundaria verificada | clasificación, género *(solo cuando el año coincide)* |
| Regla propia | tipo *(se infiere de la editorial)* |

Lo que **nunca** hace el sistema: inventar un valor para rellenar un hueco,
sustituir una editorial por otra, o dar por buena una clasificación sin que el
administrador la confirme.

### Pendiente para más adelante

- Aplicar los mismos principios a **editar cómic** y **subir capítulos**.
- Revisar si conviene cachear las respuestas de la API, hoy se consulta en cada
  búsqueda.
- La equivalencia `suggestive → +13` es una interpretación de una escala a otra;
  conviene revisarla con casos reales según crezca el catálogo.

---

*Documento de trabajo. Los datos sobre las APIs se verificaron con consultas
reales entre el 13 de noviembre de 2026 y el cierre del plan.*
