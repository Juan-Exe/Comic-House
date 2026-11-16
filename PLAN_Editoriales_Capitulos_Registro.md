# Plan · Editoriales, capítulos y registro

**Proyecto:** Comic House · Panel de administración
**Fecha:** 14 de noviembre de 2026
**Metodología:** historias de usuario + fases (Scrum)

**Estado al 15/11/2026:** implementación y cierre documental completados.
La evidencia final está al final del documento. Las notas de continuidad previas
conservan el estado que tenía cada entrega en su fecha.

> Tercer plan de la serie. Los anteriores:
> [PLAN_Registro_Automatizado.md](PLAN_Registro_Automatizado.md) ·
> [PLAN_Resumen_y_Proximamente.md](PLAN_Resumen_y_Proximamente.md)

Tres secciones independientes. Cada una se puede entregar por separado.

| Sección | Problema |
|---|---|
| **A · Registro** | Deja guardar un cómic sin editorial |
| **B · Capítulos** | Subir capítulos es manual y nada impide subirlos al cómic equivocado |
| **C · Editoriales** | Crear una editorial obliga a buscar el logo a mano |

---

# 0 · Lo que se verificó antes de planear

**La pregunta era si Fandom sirve como fuente de capítulos.** Se consultó su API
con peticiones reales antes de escribir nada.

### Fandom SÍ tiene API

Es MediaWiki estándar. Responde sin clave:

```
https://watchmen.fandom.com/api.php?action=query&list=search&srsearch=Watchmen
→ OK: Watchmen · Watchmen Wiki · Watchmen (movie)
```

### Pero NO sirve como fuente automática fiable

Cada wiki la mantiene su propia comunidad y **organiza las cosas como quiere**.
Comprobado en dos wikis distintos:

| Wiki | `Category:Issues` | `Category:Chapters` | Resultado |
|---|---|---|---|
| watchmen.fandom.com | **0 miembros** | no existe | ✗ |
| kimetsu-no-yaiba.fandom.com | no existe | **12 miembros** ✓ | parcial |

En el wiki de Watchmen las categorías que existen son del tipo
`Before Watchmen: Comedian Issues`, y al consultarla devuelve **un solo
miembro**: una página llamada *Smile*. No es una lista de capítulos.

Y al leer la ficha de un capítulo que sí existe (*Chapter 1* de Demon Slayer),
la infobox trae:

```
romaji   = Zankoku
volume   = [[Volume 1|1]]
japanese = 残酷
```

Hay título romanizado y tomo, pero **no una fecha de publicación en formato
usable**, y los nombres de los campos cambian de un wiki a otro.

**Se probó MangaDex como alternativa** y para *Kimetsu no Yaiba* devolvió
primero un doujinshi, con **0 capítulos** en el feed.

### Conclusión

> **Fandom no puede alimentar los capítulos automáticamente sin supervisión.**
> No hay un esquema común: lo que funciona en un wiki falla en el siguiente.

Esto **no** significa descartarlo. Significa cambiar el rol que se le da:

| Enfoque | Veredicto |
|---|---|
| Fandom rellena los capítulos solo | ✗ Descartado por la evidencia de arriba |
| Fandom **propone** y el administrador confirma | ✓ Lo que hace este plan |
| El sistema deduce la numeración del archivo | ✓ Determinista, sin depender de nadie |

Es el mismo criterio que ya rige la clasificación: **lo que no se puede
verificar se propone, no se guarda**.

---

# SECCIÓN A · Registro de cómics

## A.1 · El problema

El formulario deja llegar hasta el final sin editorial. El `<select>` arranca en
«Elegir editorial» y nada obliga a cambiarlo.

Verificado en `controlador/registro_comcis.php`: la comprobación de campos
obligatorios **no incluye** `editorial`.

Peor aún: como la editorial debe ser **la que publicó la obra** (principio ya
fijado en el plan 1, §3.2), dejar elegir otra cualquiera permite guardar un dato
falso.

## A.2 · Historias de usuario

### HU-A1 · No poder guardar sin editorial

> **Como** administrador,
> **quiero** que el formulario no me deje registrar sin editorial,
> **para** no guardar un cómic incompleto.

- [x] CA-A1.1 · `registro_comcis.php` **rechaza** el alta sin editorial.
- [x] CA-A1.2 · El `<select>` es `required`, con validación nativa del navegador.
- [x] CA-A1.3 · El botón «Registrar cómic» se ve **desactivado** mientras falte.
- [x] CA-A1.4 · El campo se marca en rojo con el motivo al intentar enviar.
- [x] CA-A1.5 · La validación del servidor es la que manda: desactivar el botón
      es ayuda visual, no seguridad.
- [x] CA-A1.6 · Confirmar los metadatos recalcula los campos pendientes y el
      botón inmediatamente, sin tener que tocar otro campo del formulario.

> **Corrección tras la revisión del registro.** Con Invincible, los siete
> campos quedaban completos pero el aviso conservaba los faltantes anteriores:
> asignar `.value` desde el modal no dispara `input` ni `change`. Ahora se emite
> un `change` del formulario al terminar de aplicar los datos confirmados.
> Además, el estilo del aviso respeta `hidden` y no deja una caja roja vacía.
> Verificado con **10 comprobaciones en Edge** en
> `tests/registro_validacion_ui.py`: autocompletado completo e incompleto,
> corrección manual y valores que se enviarían. No registra obras, no descarga
> portadas y comprueba que el catálogo se conserva intacto. La validación PHP
> sigue vigente; no se adelanta el Sprint 6.

> **Hallazgo durante el Sprint 1.** La validación del servidor **sí existía**,
> pero tenía un agujero: usaba `!empty()`, y `!empty('   ')` es `true` en PHP.
> Una editorial de solo espacios pasaba el filtro y se guardaba el cómic sin
> editorial real. Ahora se comprueba con `trim() === ''` sobre los siete campos.

### HU-A2 · Crear la editorial desde el propio registro

> **Como** administrador que registra una obra de una editorial nueva,
> **quiero** crearla sin salir del formulario,
> **para** no perder lo que ya llevo escrito.

- [x] CA-A2.1 · Si la fuente trae una editorial que no está, se ofrece crearla.
- [x] CA-A2.2 · **No** se ofrece «elige otra»: sería un dato falso *(plan 1 §3.2)*.
- [x] CA-A2.3 · Al crearla queda seleccionada, sin recargar la página.
- [x] CA-A2.4 · Si la fuente no trajo editorial, se puede escribir el nombre.

---

# SECCIÓN B · Capítulos

## B.1 · Los dos problemas

**Primero:** subir capítulos es teclear título, numeración y fecha uno por uno.
Watchmen son 12.

**Segundo, y más grave:** nada impide subir un capítulo de Berserk a Watchmen.
No hay ninguna comprobación entre el archivo y el cómic de destino.

## B.2 · Qué se puede verificar y qué no

Esta es la parte delicada del plan, así que conviene separarlo con claridad:

| Dato | ¿Se puede comprobar solo? | Cómo |
|---|---|---|
| Nº de capítulo | **Sí** | Se extrae del nombre del archivo |
| Duplicado | **Sí** | Ya existe ese número en ese cómic |
| Fuera de rango | **Sí** | Contra la columna `capitulos` (`1 - 12`) |
| Archivo legible | **Sí** | Se abre y se cuentan las páginas |
| **Que sea de ESE cómic** | **No con certeza** | Solo indicios |

> **Esto hay que decirlo sin adornos:** un `.cbr` es un comprimido de imágenes.
> No lleva dentro el nombre de la obra. El sistema puede sospechar, pero **no
> puede saber** que `cap01.cbr` es de Watchmen y no de Berserk.

Lo que sí se puede hacer es buscar indicios en el **nombre del archivo**:

```
watchmen-01.cbr        → coincide con el cómic de destino     ✓
berserk_c01.cbz        → menciona OTRO cómic del catálogo     ⚠ aviso fuerte
cap01.cbr              → no dice nada                          · sin opinión
```

Si el nombre menciona un cómic del catálogo **distinto** al de destino, se
avisa y se exige confirmación explícita. Es una heurística y se presenta como
tal, nunca como una verificación.

## B.3 · Historias de usuario

### HU-B1 · Ver los capítulos que faltan

> **Como** administrador,
> **quiero** ver qué capítulos tiene la obra y cuáles me faltan por subir,
> **para** saber por dónde voy.

- [x] CA-B1.1 · La pantalla lista los capítulos **ya subidos**.
- [x] CA-B1.2 · Muestra el rango publicado de la columna `capitulos` (`1 - 12`).
- [x] CA-B1.3 · Señala los números **que faltan** (en Watchmen: del 1 al 12).
- [x] CA-B1.4 · Distingue «publicados por la obra» de «subidos al sitio»: son
      cosas distintas *(ya documentado en DOC §5.4)*.
- [x] CA-B1.5 · La lista admite tomos con rangos, números sueltos y tomos únicos;
      no depende de un único wiki ni de que la obra sea Berserk.
- [x] CA-B1.6 · Si la fuente llega antes que los capítulos subidos, espera a
      conocer estos últimos para calcular los pendientes.
- [x] CA-B1.7 · El último número publicado no se deduce de la cantidad de
      fichas: un número cero o especial no desplaza el final de la numeración.

### HU-B2 · Proponer los datos del capítulo desde el archivo

> **Como** administrador,
> **quiero** que al elegir el archivo se rellenen número y título,
> **para** no teclear doce veces lo mismo.

- [x] CA-B2.1 · El número se extrae del nombre del archivo
      (`watchmen-07.cbr` → 7; `cap_012.cbz` → 12).
- [x] CA-B2.2 · Si no se puede deducir, el campo queda vacío **sin inventar**.
- [x] CA-B2.3 · Todo lo propuesto es **editable**.
- [x] CA-B2.4 · Si Fandom tiene una ficha de ese capítulo, se ofrece su título
      como sugerencia marcada en ámbar *(§0: propone, no rellena)*.
- [x] CA-B2.5 · Si Fandom no responde o no tiene la ficha, no pasa nada: se
      escribe a mano.
- [x] CA-B2.6 · Dos encarnaciones de una serie no se mezclan. Si los datos del
      catálogo no permiten distinguirlas, el administrador elige la edición.
- [x] CA-B2.7 · La caché distingue obra, editorial, año y edición; una respuesta
      atrasada de otra obra no cambia la selección actual.
- [x] CA-B2.8 · Solo se rellena una fecha completa si la fuente publica el día;
      un mes/año se rellena y se guarda con esa precisión, sin inventar el día 1.
- [x] CA-B2.9 · Al reiniciar la numeración de episodios, se conserva la notación
      del wiki y se propone un rango continuo que no solape los episodios anteriores.

### HU-B3 · No subir un capítulo al cómic equivocado

> **Como** administrador,
> **quiero** que el sistema me avise si el archivo parece de otra obra,
> **para** no ensuciar el catálogo.

- [x] CA-B3.1 · Si el nombre del archivo menciona **otro cómic del catálogo**,
      se avisa y se exige confirmación explícita.
- [x] CA-B3.2 · El aviso dice que es una **sospecha por el nombre**, no una
      comprobación del contenido.
- [x] CA-B3.3 · Si el número ya existe en ese cómic, se **bloquea** salvo que se
      pida reemplazar.
- [x] CA-B3.4 · Si el número se sale del rango publicado, se avisa.
- [x] CA-B3.5 · Si el archivo no es `.cbr`/`.cbz` válido o no tiene imágenes, se
      **rechaza**.
- [x] CA-B3.6 · Nunca se bloquea por la heurística del nombre: el administrador
      puede tener razón y el archivo llamarse como sea.
- [x] CA-B3.7 · Una diferencia de una letra en palabras de al menos siete
      caracteres no provoca un falso aviso («Invencible»/«Invincible»).
      La coincidencia exacta con otra obra conserva prioridad; no se aplica
      esta tolerancia a nombres cortos ni a diferencias mayores.

> **Hallazgo durante la prueba real.** Se intentó subir `Invencible #1-2.cbr`
> a Berserk y **solo se detuvo porque esos números ya estaban ocupados**. Con
> números libres habría entrado sin una palabra.
>
> Comparar el nombre contra los títulos del catálogo no bastaba: «Invencible»
> no está entre los 7 registrados, así que no tenía con qué chocar. Por eso la
> detección usa **dos señales**: que el nombre mencione otra obra del catálogo,
> y que **ninguna** de sus palabras aparezca en el título de destino.
>
> Verificado con 20 nombres reales, 0 fallos: avisa en `Invencible #1-2.cbr`,
> `Watchmen 01.cbr` y `Demon Slayer 12.cbz`; calla en `Berserk #10.cbr`,
> `cap01.cbr`, `chapter12.cbz` y `Cyberpunk 2077 01.cbr`.

### HU-B4 · Subir varios capítulos de una vez

> **Como** administrador con doce archivos,
> **quiero** subirlos juntos,
> **para** no repetir el formulario doce veces.

- [x] CA-B4.1 · Se pueden elegir varios archivos a la vez.
- [x] CA-B4.2 · Cada uno muestra su número deducido y sus avisos **antes** de
      subir nada.
- [x] CA-B4.3 · Los que tengan error se pueden corregir o descartar sin
      cancelar el resto.
- [x] CA-B4.4 · Si uno falla a mitad, los ya subidos **se conservan** y se dice
      cuáles fueron: nada de dejar el estado a medias en silencio.

### HU-B5 · Idempotencia

> **Como** administrador que no sabe si la subida terminó,
> **quiero** poder repetirla sin duplicar nada,
> **para** no tener que revisar a mano.

- [x] CA-B5.1 · Subir **el mismo archivo al mismo capítulo** dos veces no crea
      dos filas.
- [x] CA-B5.2 · La base impide dos capítulos con el mismo número en el mismo
      cómic (`UNIQUE (comic_id, numero)`). **Ya existía** — ver hallazgo abajo.
- [x] CA-B5.5 · Dos capítulos del mismo cómic **no pueden solapar rangos**.
- [x] CA-B5.3 · Si se reintenta tras un fallo a medias, se completa lo que
      falta en vez de empezar de cero. *(Ver evidencia del Sprint 5 al final.)*
- [x] CA-B5.4 · Las páginas de un capítulo reemplazado **se borran** antes de
      insertar las nuevas: nada de mezclar dos ediciones.
- [x] CA-B5.6 · El reemplazo exige confirmación, conserva id y metadatos y
      restaura filas y archivos anteriores si falla el comprimido o el guardado.
- [x] CA-B5.7 · Un reintento del mismo reemplazo recupera su recibo; una revisión
      anterior no sobrescribe cambios recientes ni reemplazos de otra pestaña.

> **Hallazgo del Sprint 2.** Al inspeccionar la base **antes** de tocarla
> resultó que las restricciones ya existían, así que el riesgo que preocupaba
> (que `UNIQUE` fallara por duplicados previos) no era real:
>
> ```
> uq_capitulo_por_comic  UNIQUE (comic_id, numero)        ya existía
> uq_capitulos_desde     UNIQUE (comic_id, numero_desde)  ya existía
> duplicados actuales    0
> ```
>
> Ambas se probaron con inserciones reales y **rechazaron** el duplicado.
>
> Pero la prueba destapó un hueco distinto: **los rangos pueden solaparse**.
> Berserk tiene un capítulo `#1 - #3` y la base aceptó insertar `#2 - #4`, con
> lo que los números 2 y 3 quedaban en dos capítulos a la vez. Ninguna
> restricción de columna puede evitarlo, porque depende de comparar con las
> demás filas. Eso es lo que resuelve CA-B5.5.

---

### HU-B6 · Conservar la precisión de la fecha de publicación

> **Como** administrador,
> **quiero** guardar día/mes/año o solamente mes/año según los datos disponibles,
> **para** aprovechar la fuente sin inventar información.

- [x] CA-B6.1 · La sugerencia selecciona automáticamente «Día, mes y año» o
      «Mes y año» y rellena el valor disponible.
- [x] CA-B6.2 · La subida acepta una fecha mensual; almacena `YYYY-MM` en
      `fecha_publicacion_mes` y deja `fecha_publicacion` en NULL.
- [x] CA-B6.3 · Se rechazan fechas imposibles, futuras y la recepción simultánea
      de ambas precisiones, en servidor y en la interfaz según corresponda.
- [x] CA-B6.4 · Editar recupera la precisión guardada y permite cambiarla;
      al guardar una se elimina el valor de la otra.
- [x] CA-B6.5 · La ficha pública y la lista de subidos muestran `MM/AAAA`
      cuando no hay día, manteniendo `DD/MM/AAAA` en fechas completas.
- [x] CA-B6.6 · La migración conserva intactas las fechas de capítulos existentes.
- [x] CA-B6.7 · Si la plantilla publica `Day`, `Month` y `Year`, se aprovecha
      el día real; un día ausente o inválido conserva únicamente mes/año.

### HU-B7 · Propuestas y controles coherentes con la página

> **Como** administrador,
> **quiero** controles con el diseño de Comic House y propuestas con información útil,
> **para** revisar los datos sin opciones vacías ni desplegables ajenos a la página.

- [x] CA-B7.1 · Edición, numeración y precisión de fecha usan desplegables propios,
      con fondos oscuros, Montserrat y estados en rojo.
- [x] CA-B7.2 · Las opciones y la lista de entregas tienen desplazamiento con
      los colores del panel, sin la barra blanca del navegador.
- [x] CA-B7.3 · Los controles permiten elegir con teclado y el menú permanece
      dentro de la pantalla móvil.
- [x] CA-B7.4 · Se muestran también las entregas sin título/arco y se indica
      «Sin título publicado» cuando tampoco hay un recopilatorio verificado.
      **Preferencia actualizada por el usuario** al
      revisar Invincible; sustituye la petición anterior de ocultarlas.
- [x] CA-B7.5 · La búsqueda no exige que la obra pertenezca a las siete del
      catálogo inicial: se comprobó con Invincible, no registrado localmente.
- [x] CA-B7.6 · El valor de fecha y su precisión forman un único campo, alineado
      con el título, sin duplicar cajas ni recortar las opciones.
- [x] CA-B7.7 · Una entrega individual se muestra como «Capítulo 4»; solo un
      rango real muestra ambos extremos, como «Capítulos 10–14».
- [x] CA-B7.8 · Las sugerencias usan tonos neutros, identifican la fuente y
      marcan discretamente la fila elegida; una fecha inválida conserva su aviso de error.
- [x] CA-B7.9 · Un archivo que reúne varios números consulta todos los incluidos
      sin cambiar el rango indicado, unir huecos o atribuirle un tomo completo.
- [x] CA-B7.10 · Si falta un título, el nombre descriptivo propuesto se distingue
      de un título publicado; se conservan los cambios manuales. En un archivo
      combinado se identifica expresamente la fecha del primer número.
- [x] CA-B7.11 · Si falta el título individual, se puede proponer el nombre de
      su recopilatorio cuando la fuente confirme los números incluidos. Se indica
      ese origen, el rango propio del archivo y si el nombre se tradujo; no cambia
      fechas ni títulos manuales. **Opción elegida expresamente por el usuario.**

---

# SECCIÓN C · Editoriales

## C.1 · El problema

Crear una editorial obliga a buscar el logo en Wikipedia, descargarlo y
subirlo. Es el paso más lento de registrar una obra de una editorial nueva.

## C.2 · Lo que hay que comprobar antes

Wikipedia **sí** expone los logos por API (`prop=pageimages`), pero hay dos
cosas que verificar en el Sprint, no ahora:

1. Que la imagen que devuelve sea **el logo** y no una foto de la sede.
2. Que el formato sea utilizable (muchos logos son SVG, no PNG).

Si no se cumplen, se deja la subida manual, que ya funciona.

## C.3 · Historias de usuario

### HU-C1 · Buscar el logo por el nombre

> **Como** administrador que da de alta una editorial,
> **quiero** escribir «Kodansha» y que el sistema busque su logo,
> **para** no ir a Wikipedia a descargarlo.

- [x] CA-C1.1 · Al escribir el nombre se busca el logo en Wikipedia.
- [x] CA-C1.2 · Se muestra **una vista previa** antes de guardarlo.
- [x] CA-C1.3 · Se puede **rechazar** y subir otro archivo.
- [x] CA-C1.4 · Si no se encuentra, se dice y se ofrece la subida manual.
- [x] CA-C1.5 · Solo se aceptan imágenes de dominios de Wikimedia *(mismo
      criterio que `descargar_portada.php`)*.
- [x] CA-C1.6 · Se valida que sea una imagen real, con límite de tamaño.

### HU-C2 · No duplicar editoriales

> **Como** administrador,
> **quiero** que no se creen dos veces la misma editorial,
> **para** que el catálogo no tenga «DC» y «DC Comics» por separado.

- [x] CA-C2.1 · Antes de crear se busca una existente ignorando mayúsculas y
      tildes.
- [x] CA-C2.2 · Si ya existe, **se reutiliza** en vez de crear otra.
- [x] CA-C2.3 · Si hay una parecida, se avisa antes de crear.

---

# Fases

| Sprint | Contenido | Por qué en este orden |
|---|---|---|
| **1** | HU-A1, HU-A2 | Es un bug de integridad y no depende de nada externo |
| **2** | HU-B5 + restricción en la base | La idempotencia es la red de seguridad del resto |
| **3** | HU-B1, HU-B2 | Lo que más tiempo ahorra |
| **4** | HU-B3 | Las comprobaciones, una vez existe el flujo |
| **5** | HU-B4 | La subida múltiple, sobre un flujo ya verificado |
| **6** | HU-C1, HU-C2 | Lo menos crítico |
| **7** | Documentación | Cerrar en el DOC |

> El Sprint 2 va **antes** que la subida porque añadir `UNIQUE (comic_id,
> numero)` con datos ya cargados puede fallar si hay duplicados. Conviene
> descubrirlo pronto y con 9 capítulos, no con 200.

---

# Contradicciones a resolver

Puntos donde dos cosas razonables chocan. Se anotan aquí **antes** de
programar, para decidirlos en frío:

| # | Contradicción | Decisión |
|---|---|---|
| 1 | Verificar que el archivo es del cómic correcto **vs.** no poder saberlo | Se avisa por el nombre, nunca se bloquea. El aviso dice que es una sospecha |
| 2 | Fandom como fuente **vs.** cada wiki con su estructura | Propone en ámbar; su ausencia no rompe nada |
| 3 | Rango publicado (`1 - 12`) **vs.** capítulos subidos | Son columnas distintas; subir el 13 avisa pero no bloquea: la columna puede estar desactualizada |
| 4 | Subida múltiple **vs.** atomicidad | Cada capítulo es su propia transacción. Fallar el 7 no debe tirar los 6 anteriores |
| 5 | Reemplazar un capítulo **vs.** idempotencia | Reemplazar es explícito y borra las páginas viejas primero |
| 6 | Editorial obligatoria **vs.** fuente que no la trae | Si la fuente no la da, se escribe a mano; lo que no se permite es dejarla vacía |
| 7 | Logo automático **vs.** derechos de imagen | Solo dominios de Wikimedia, igual que las portadas |

---

# Riesgos

| Riesgo | Mitigación |
|---|---|
| `UNIQUE (comic_id, numero)` falla por duplicados ya existentes | Se comprueba **antes** de aplicar la restricción |
| Fandom cambia su estructura | Ya se asume: es sugerencia, no dependencia |
| Un `.cbr` corrupto rompe la subida | Se valida antes de insertar; la transacción revierte |
| La deducción del número se equivoca | Siempre es editable y se ve antes de subir |
| Wikipedia devuelve una imagen que no es el logo | Vista previa obligatoria antes de guardar |
| Subir 12 capítulos agota el tiempo de PHP | Cada uno es una petición propia |

---

# Fuera de alcance

- **Leer el contenido del `.cbr`** para identificar la obra (OCR de portadas).
  Es otro proyecto.
- **Descargar capítulos** de ninguna fuente: el sistema organiza archivos que
  pone el administrador.
- **Editar cómic**, que sigue pendiente del plan 1.
- **Traducir títulos de capítulo** dejó de estar fuera de alcance al solicitarse
  los títulos/arcos en español. La traducción es opcional y conserva el original.

---

# Continuación · 15 de noviembre de 2026

**Punto retomado:** la petición «solo funciona con Berserk, tiene que buscar con
todos», dentro de HU-B1/HU-B2. No se da por cerrado el plan completo.

El último cambio de Claude podía mezclar distintas series de Ultimate Spider-Man:
indexaba los resultados por número antes de identificar la encarnación. Además,
el formulario no enviaba el año ni la editorial, y la caché solo usaba el título.
El filtro por una ventana de 25 años no resolvía esos problemas.

## Resultado de esta ampliación del Sprint 3

| Obra del catálogo | Fuente y resultado comprobado |
|---|---|
| Berserk | Fandom: 43 tomos con rangos y fechas; los tres ya subidos se excluyen de pendientes |
| Saga | Image Comics Wiki: 52 números; mes/año cuando no hay día publicado |
| Absolute Batman | DC Wiki: 25 números; títulos cuando la ficha los publica |
| Cyberpunk 2077: Big City Dreams | Fandom: tomo único; fecha de la edición de 2022, 12/01/2022 |
| Watchmen | DC Wiki: 12 números con títulos y fechas con la precisión disponible |
| Ultimate Spiderman | Marvel Wiki: elección entre las series de 2000, 2009 y 2024; 134, 26 y 24 números respectivamente, sin mezclarlas |
| Bloodborne | Respaldo ComicVine: permite elegir Titan (16 números) o Urban (3 recopilatorios numerados por esa fuente) |

Son los resultados de las fuentes en la revisión, no una garantía de exhaustividad
ni una sincronización del catálogo. Ultimate Spiderman figura como **1980** en la
base local, y Bloodborne está registrado bajo **Norma Editorial**, mientras las
fuentes ofrecen otras ediciones. Se pide elegir: no se corrigen esos datos ni se
sustituye la editorial local automáticamente. Revisar si el archivo contiene una
grapa o un recopilatorio de la edición elegida sigue siendo responsabilidad del
administrador.

### Reinicio de episodios en Berserk

Elegir «la serie con más episodios» perdía los dos episodios del prólogo del tomo 5
y hacía que otros tomos parecieran ya subidos. Ahora se conservan todos: el prólogo
`0-1…0-16` ocupa las posiciones 1…16 y los episodios siguientes continúan desde 17.
El tomo 5 propone **15–22** y muestra también **0-15–0-16, 1–6** del wiki; el tomo 6
propone **23–32**. Los tres rangos existentes 1–3, 4–5 y 6–9 no cambian.

### Implementación

- `controlador/buscar_tomos_wiki.php`: coordinación, elección de edición y caché de siete días.
- `controlador/tomos_fuentes.php`: descubrimiento, parsers, fechas, traducción y respaldo ComicVine.
- `subir_capitulo.php` y `js/tomos.js`: datos de la obra, selector de edición, propuestas y protección contra respuestas atrasadas.
- Se usan páginas de una sola serie y paginación `allpages`, después de descubrir
  la identidad de esa serie. La [documentación de MediaWiki](https://www.mediawiki.org/wiki/API:Prefixsearch)
  aclara que `prefixsearch` puede aplicar heurísticas: no se toma como inventario exhaustivo.
- Si falta un título, se indica; si falla la fuente, se mantiene el formulario manual.
  La fecha de los capítulos ya subidos se muestra sin convertirla a UTC y restar un día.

### Verificación ejecutada

Esta fue la verificación inicial de la ampliación del Sprint 3. La entrega de
fechas parciales y controles personalizados se registra al final del documento.

- **28 comprobaciones PHP**, sin red ni base de datos: identidad de ediciones,
  año incorrecto, selección explícita, fechas, enlaces wiki, reinicios y separación de caché.
- **18 comprobaciones en Edge** con sesión efímera local: propuestas reales,
  edición de Ultimate, respaldo Bloodborne, respeto al trabajo manual, respuestas
  fuera de orden, cálculo de pendientes y ancho móvil. Sin errores de JavaScript.
- Sintaxis PHP y JavaScript y `git diff --check` correctos.
- No se subieron archivos ni se modificaron registros: se conservaron **7 cómics,
  9 capítulos y 930 páginas**. La sesión de pruebas se eliminó al terminar.

Comandos, desde la raíz del repositorio y con Docker disponible:

```powershell
docker exec comic_house_web php /var/www/html/tests/tomos_fuentes.php
python tests/tomos_ui.py
```

La prueba de navegador requiere Python con Playwright y Edge/Chrome local (o un
Chromium de Playwright). Está diseñada para el catálogo de siete obras e ids de
esta revisión. Puede consultar las fuentes externas si su resultado no está cacheado.

## Próximos criterios del plan

Esta ampliación deja HU-B1/HU-B2 verificados para el catálogo revisado. HU-A2 sigue
por completar/verificar. Los Sprints 4 y 5 se cerraron en las entregas documentadas
al final. El siguiente es **Sprint 6 (HU-C1 y HU-C2: editoriales)**. El reemplazo
explícito de capítulos (CA-B5.4) y el cierre documental también siguen pendientes.

## Ajustes de fecha e interfaz · entrega verificada

Tras la revisión del usuario se incorporaron **HU-B6 y HU-B7**. Sus once criterios
están marcados arriba con las comprobaciones de esta entrega:

| Criterios | Evidencia |
|---|---|
| B6.1–B6.3 | Fecha mensual propuesta, entrada manual, 20 casos PHP de validación/BD y petición real al procesador |
| B6.2 | Subida de un CBZ temporal de una página: guarda `2012-09`, deja DATE nulo y genera la página correctamente |
| B6.4–B6.5 | Edición real de registro temporal, cambio mes→día→mes y consulta de ficha pública con `07/2012` |
| B6.6 | Hash de todos los campos previos de capítulos igual antes/después de la migración; pruebas de BD con rollback |
| B7.1–B7.3 | Opciones personalizadas seleccionadas con ratón y teclado en Edge, revisión visual de escritorio/móvil y menú dentro de la pantalla |
| B7.4 | Saga omite sus filas sin nombre; conserva las seis tituladas encontradas en la fuente. Bloodborne omite también los números sin título |
| B7.5 | Consulta real de Invincible, ausente del catálogo local: 144 números desde Image Comics Wiki, sin añadir reglas para esa obra |

**Pruebas actuales:** 28 de fuentes + 20 de fechas en PHP; **29 comprobaciones en
Edge**, incluidas subida y edición reales de fixtures temporales. Los registros y
la carpeta del CBZ de prueba se eliminaron. Se conservan los 7 cómics, 9 capítulos
y 930 páginas originales.

La migración [migracion_02_fechas_parciales.sql](database/migracion_02_fechas_parciales.sql)
está aplicada a la base local y se incorpora al SQL de inicialización. Añade la
columna mensual y restricciones para impedir precisiones simultáneas o meses
imposibles; no rellena días ficticios ni modifica las fechas anteriores. Otras
instalaciones existentes necesitan aplicar esa migración antes de usar esta versión.

Los controles compartidos están en `admin/controles.css` y
`js/select-personalizado.js`; la validación de fecha en `controlador/fecha_capitulo.php`
y `js/fechas-capitulo.js`. Se revisó el patrón de
[combobox de selección de WAI-ARIA](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/examples/combobox-select-only/)
para los roles, el foco y la navegación por teclado.

Para repetir la verificación de fechas:

```powershell
docker exec comic_house_web php /var/www/html/tests/fechas_capitulo.php --bd
python tests/tomos_ui.py
```

La prueba de navegador ahora crea registros y un CBZ propios y los limpia al
terminar. No modifica cómics ni capítulos del usuario. Los criterios abiertos de
otros sprints se mantienen abiertos; no se confunde esta entrega con el cierre
del plan completo.

### Pulido visual tras la revisión del usuario

Se completaron B7.6–B7.8 antes de continuar con los siguientes sprints:

- Fecha y precisión en una sola línea, tanto al subir como al editar. El menú
  mantiene «Día, mes y año» y «Mes y año», con una etiqueta compacta en el campo.
- Se eliminaron «capítulos 4–4» y el mensaje «se rellenó título y fecha». La
  sugerencia muestra capítulo/tomo, fecha y fuente en gris, con el rango solo si existe.
- Las filas individuales muestran número, título y fecha sin repetir el número
  en la segunda línea. La selección se indica con fondo neutro y una marca discreta.
- La fecha válida y los campos enfocados usan bordes neutros; las fechas imposibles
  mantienen el borde rojo y su validación.

Verificación visual y de interacción en Edge a 1440, 1366 y 390 px: título y fecha
alineados a la misma altura, texto sin recortar, menú de precisión operativo,
fecha mensual/completa conservadas, «Capítulo 4» frente a «Capítulos 10–14» y cero
errores JavaScript. Esta revisión de frontend no hizo escrituras en la base de datos.

---

## Sprint 4 · HU-B3 completada y verificada

- **CA-B3.4:** una entrega anterior al inicio o posterior al final registrado
  requiere confirmación en el modal de la página. Se permite continuar porque
  el catálogo puede estar desactualizado. Los rangos abiertos no inventan un
  límite. El aviso de nombre de otra obra conserva su confirmación independiente.
  Cancelar o pulsar Escape conserva los campos y el archivo sin registrar nada.
- **CA-B3.5:** se comprueba extensión, firma, integridad del comprimido y
  decodificación de las imágenes. Se rechazan archivos truncados, renombrados,
  protegidos, sin páginas o con imágenes dañadas, con un mensaje concreto.
  Se ignoran metadatos como `ComicInfo.xml` y `__MACOSX`; se conservan carpetas,
  orden natural y páginas dobles. Cada imagen admite hasta 64 MB y 40 megapíxeles
  para no agotar la memoria de PHP.
- Se extrae primero a un directorio privado temporal. Capítulo y páginas se
  guardan en una sola transacción, manteniendo lotes de 100 páginas. Si falla
  el guardado, se revierte la base y se elimina solo la carpeta de esa petición.
  Los archivos previos no se reemplazan ni se reutilizan.
- Se cambió `unrar-free` por UnRAR en Docker: un RAR con la imagen intacta pero
  CRC incorrecto pasaba con el extractor anterior. La dependencia está declarada
  en el Dockerfile y documentada en README; no se añadieron migraciones SQL.

Evidencia: **45 comprobaciones de subida reales** en
`tests/subida_capitulo.py`: CBZ y CBR, CRC incorrectos, imágenes dañadas, ausencia
de imágenes, orden, lectura HTTP de páginas, duplicados, confirmaciones y
cancelación en el navegador. Un trigger temporal limitado al cómic de prueba
provoca un fallo al insertar páginas y verifica rollback y limpieza; después
se prueba el reintento. Se eliminan trigger, sesión, registros y carpetas de
prueba al terminar. También pasan las 28 pruebas de fuentes, 20 de fechas y
29 de interfaz existentes. El catálogo original conserva 7 obras, 9 capítulos
y 930 páginas.

```powershell
python tests/subida_capitulo.py
```

La siguiente fase de aquella entrega fue **Sprint 5 · HU-B4**, elección de varios archivos, revisión
individual antes de subir, corrección/descarte y conservación de los éxitos
si falla otro archivo. CA-B5.3 (reanudar) se verifica con ese flujo. Los demás
criterios abiertos del plan conservan su estado. Su resultado se registra a continuación.

## Sprint 5 · subida múltiple y reanudación completadas

Se completaron **CA-B4.1–B4.4 y CA-B5.3**, sin adelantar el reemplazo ni la
automatización de editoriales.

- El selector admite varios CBR/CBZ y mantiene el flujo individual al elegir
  uno. El lote muestra número/rango, nombre, título, fecha y fuente, errores
  y avisos antes de enviar archivos. Admite hasta 200 entregas por lote.
- Elegir una fila carga sus datos en el formulario existente. Se pueden
  corregir títulos, numeración, formato y fechas; sustituir un archivo dañado
  conserva los datos editados. También se puede descartar una fila o añadir
  archivos. Se conservan los cambios manuales al llegar propuestas nuevas.
- La revisión previa es una petición de metadatos, sin subir comprimidos ni
  escribir en la base. Comparte las reglas PHP de numeración, fechas, obra y
  rango; además detecta solapes entre los archivos del propio lote. Los que
  necesitan corrección no impiden enviar otras entregas listas.
- Se envía una petición por archivo, en secuencia. Cada una usa la validación
  y transacción del Sprint 4. Un error muestra su motivo en la fila y no
  revierte los éxitos ni cancela las entregas siguientes. Cancelar un aviso
  pausa el lote y conserva lo guardado.
- Un identificador de intento y el SHA-256 del comprimido permiten recuperar
  un éxito cuya respuesta se perdió. Repetir ese intento exige la misma obra,
  numeración, datos y contenido. Los duplicados ajenos a ese intento siguen
  bloqueados; no se reemplazan capítulos automáticamente.
- `sessionStorage` conserva los datos y recibos del lote por obra en la misma
  pestaña. Después de recargar se consulta qué quedó guardado; hay que volver
  a elegir los archivos pendientes porque sus bytes no se almacenan allí.
  El botón continúa solo con los pendientes, y «Cerrar lote» limpia la lista
  conservando la obra elegida y los capítulos ya guardados.
- «Ya subidos» usa orden numérico, también después del 9. Se revisaron las
  listas y controles en escritorio y móvil y se corrigió un cierre prematuro
  de los select por un evento de desplazamiento anterior al clic.

Archivos principales: `js/lote-capitulos.js`, `admin/lote-capitulos.css`,
`controlador/revisar_lote_capitulos.php` y `controlador/recibo_subida.php`.
La **migración 03 está aplicada**: añade `subida_clave` y `archivo_sha256`,
ambas opcionales, y un índice único de intento. Los capítulos anteriores
conservan sus datos; las instalaciones nuevas incluyen estas columnas en
`database/init/01_schema.sql`.

### Evidencia de esta entrega

**33 comprobaciones de lotes**, mediante `tests/lote_capitulos.py`, con registros
y CBR/CBZ temporales y subidas HTTP reales desde Edge:

- Selección y revisión sin escrituras; deducción, ausencia de número, fuente,
  fechas, correcciones, descarte y solapes dentro de la lista.
- Doce entregas: la cuarta corrupta se rechaza y las otras once se conservan.
  Se pierde deliberadamente la respuesta del séptimo después del commit;
  su recibo permite reconocerlo sin volver a enviar ese archivo.
- Tras recargar, se recuperan los once éxitos y los datos pendientes. Cambiar
  el archivo dañado y continuar envía únicamente la cuarta entrega.
- Repetir un recibo no duplica; reutilizarlo con otros datos o bytes se rechaza.
- Avisos antes del envío y pausa al cancelar, conservación de datos manuales,
  pantalla móvil, dos rangos de manga (CBZ y CBR), orden numérico, autenticación
  y rechazo de una revisión malformada. Sin errores JavaScript.

También pasan las **122 comprobaciones anteriores**: 28 de fuentes, 20 de fechas,
29 de interfaz y 45 de subida. **Total: 155**. Los fixtures, sesiones y carpetas
de pruebas se eliminan al terminar. El catálogo original conserva **7 obras,
9 capítulos y 930 páginas**.

```powershell
python tests/lote_capitulos.py
```

Próxima fase: **Sprint 6 · logos y reutilización de editoriales (HU-C1/HU-C2)**.
CA-B5.4 permanece abierto: este sprint permite reintentar, no reemplazar un
capítulo existente. HU-A2 y los demás criterios abiertos mantienen su estado.

## Corrección de Invincible · números sin título y archivos con rangos

El usuario registró Invincible y eligió `Invencible #1-2.cbr`. La numeración
del archivo se reconocía, pero el título quedaba vacío y la lista parecía
comenzar en el 9. La fuente contiene **144 números (1–144)**: solo **46** tienen
título; los primeros ocho no. La petición anterior de ocultar filas sin título
explicaba el inicio visible en el 9. El usuario decidió ahora **mostrar todos**,
indicando cuándo no hay título publicado. CA-B7.4 recoge esa decisión.

- `tomoDe()` mantiene las coincidencias exactas de tomos/grapas y admite combinar
  grapas consecutivas de la misma edición para un archivo como `#1-2`. No une
  números ausentes ni asigna el arco de un tomo entero a un fragmento.
- Si todos los números tienen títulos, se combinan sin repetir los iguales.
  Si faltan títulos o el resultado excede 255 caracteres, se propone un nombre
  descriptivo, por ejemplo **«Invincible · números 1–2»**, marcado como tal.
  No se presenta como título publicado ni se inventa una historia.
- Se usa la fecha del primer número del archivo y se indica ese origen.
  Las plantillas `Day/Month/Year` ya se leen completas: #1, 22/01/2003;
  #2, 19/02/2003. La precisión mensual sigue disponible cuando no hay día.
- La misma regla se usa al elegir un archivo y en la cola de subida múltiple.
  Una fecha completa no rellena a la vez el campo mensual independiente.
  Se respetan los datos escritos a mano y ambos números aparecen seleccionados.
- La caché de entregas pasa a **v6** para no reutilizar fechas incompletas del
  parser anterior. No se borran cachés de otras funciones.
- En `buscar_metadatos.php` el final de la numeración sale de `last_issue`, no
  de `count_of_issues`: las 145 fichas de Invincible incluyen el #0. La ficha
  local recién creada (id 157) se corrigió de **1–145 a 1–144**, con condiciones
  sobre identidad y valor anterior, sin tocar capítulos ni archivos subidos.
  [Image Comics identifica el #144 como último número](https://imagecomics.com/comics/releases/invincible-144).

**Verificación:** `tests/rangos_grapas_ui.py`, **24 comprobaciones** con la fuente
real de Invincible y casos en memoria: lista desde el 1, rangos de uno/dos
números, nombres, fechas, límites, ausencia de huecos, mangas, edición pendiente,
conservación manual y móvil. No envía archivos ni altera registros.
`tests/tomos_fuentes.php` suma **31 casos**, incluidos los nuevos de día publicado.
Las pruebas anteriores de interfaz se ajustaron a la nueva preferencia y al
catálogo actual (ya no exigen exactamente siete obras).

Pasan **192 comprobaciones** en total: 31 de fuentes, 29 de interfaz, 33 de
lotes, 45 de subida, 20 de fechas, 10 de registro y 24 de rangos. Se conserva
el catálogo actual de **8 obras, 9 capítulos y 930 páginas**, aparte de la
corrección explícita del rango de Invincible. No se implementó el Sprint 6.

```powershell
python tests/rangos_grapas_ui.py
```

---

*Documento de trabajo. Las consultas a Fandom, MangaDex y la estructura de los
wikis se verificaron con peticiones reales el 14 de noviembre de 2026. Donde una
fuente no sirve, queda la prueba que lo demuestra.*

## Sprint 6 completado · logos y editoriales desde el registro

Se verificaron y marcaron **CA-A2.1–A2.4, CA-C1.1–C1.6 y CA-C2.1–C2.3**.
El asistente de `js/editoriales-asistente.js` comparte la búsqueda, revisión y
guardado entre `editoriales.php` y `crud.php`, con los estilos del panel.

- Al escribir el nombre se buscan logos explícitos de Wikipedia/Wikidata,
  nunca la primera fotografía de la página. El administrador revisa la
  propuesta, consulta su fuente y la selecciona antes de guardarla. Puede
  descartarla y subir una imagen manual; una fuente sin resultados no bloquea.
- Las descargas aceptan exclusivamente HTTPS de `upload.wikimedia.org` y
  `thumb.wikimedia.org`, incluidos los saltos de redirección. Los nuevos logos
  manuales admiten PNG, JPG, WebP y GIF, hasta **5 MB y 16 megapíxeles**.
  Se decodifican y guardan como PNG. Los SVG remotos se usan mediante su versión
  PNG de Wikimedia; no se admiten nuevos SVG crudos. Los logos previos se conservan.
- Se reutilizan nombres iguales sin distinguir mayúsculas, tildes o espacios,
  y alias conocidos como «DC Comics»/«DC». Un nombre parecido exige revisar si
  es la misma editorial o confirmar expresamente que es otra. Un bloqueo SQL
  evita duplicados en altas simultáneas; un fallo de inserción retira el logo nuevo.
- Desde el candidato de una obra se crea su editorial real con el nombre de la
  fuente. Cuando no existe ese dato se puede escribir. El nuevo registro queda
  seleccionado sin recargar ni perder género, clasificación y campos manuales.
  Cancelar el diálogo conserva el candidato abierto. En este flujo se permite
  crear sin logo y completarlo después; el catálogo de editoriales exige imagen.
- El alta clásica y la edición también comprueban identidad e imagen en el
  servidor. Reutilizar una editorial no sobrescribe su logo. Editar su nombre
  mantiene sincronizado el texto en sus cómics, dentro de una transacción.

**Evidencia:** `tests/editoriales.php`, **32 comprobaciones** de identidad,
orígenes, selección del campo logo y decodificación; `tests/editoriales_ui.py`,
**42 comprobaciones** de API y navegador: fuente real de Kodansha, descarga real
de su logo, propuestas controladas para fixtures, fallback manual, rechazo,
duplicados, concurrencia, limpieza tras fallo SQL, alta clásica, edición y ambos
caminos de HU-A2. Se revisó la interfaz a 1440 px y 390 px. Las pruebas eliminan
sus propias editoriales, logos y sesiones y comparan el catálogo del usuario.
La regresión `tests/registro_validacion_ui.py` mantiene sus **10 comprobaciones**.

```powershell
docker exec comic_house_web php tests/editoriales.php
python tests/editoriales_ui.py
python tests/registro_validacion_ui.py
```

Se comprobaron **84 casos en esta entrega**. Los 74 nuevos se suman a los
192 documentados anteriormente; las otras suites no se repitieron en este sprint.
No se requieren migraciones. El catálogo conserva **8 obras, 9 capítulos y
930 páginas** y las editoriales del usuario permanecen intactas.

**Pendiente:** CA-B5.4, reemplazo explícito de capítulos y retirada de sus páginas
anteriores; después, Sprint 7 para el cierre documental completo del plan.

## Corrección de títulos ausentes · recopilatorios verificados

Invincible #1 y #2 tienen `StoryTitle1` vacío en Fandom. La búsqueda sí devolvía
sus números y fechas, pero la propuesta era «Invincible · números 1–2». El usuario
eligió usar el nombre del recopilatorio cuando esté confirmada la pertenencia.

`recopilatorios_fuentes.php` lee tablas explícitas de contenido recopilado en
Wikipedia, exige identidad de obra y año de inicio y acepta solo rangos continuos
sin celdas combinadas ni ambigüedades. No asigna números adicionales al archivo.
La editorial confirma que [Family Matters reúne #1–4](https://imagecomics.com/comics/releases/invincible-vol-1-family-matters).
La API conserva ese dato como `recopilatorio`, separado del título individual
ausente; reutiliza la traducción opcional y conserva el original.

Para `Invencible #1-2.cbr` ahora propone **«Asuntos familiares · números 1–2»**
con la traducción obtenida en esta revisión. La leyenda identifica el recopilatorio
1–4, Wikipedia, el original «Family Matters» y la traducción orientativa. La
numeración sigue 1–2 y la fecha sigue siendo la del #1. Los títulos individuales
publicados conservan prioridad; sin datos verificables sigue el nombre descriptivo.
La regla se comparte con la cola múltiple. La caché pasa a **v7**.

Evidencia: `tests/tomos_fuentes.php` (44 casos), `tests/rangos_grapas_ui.py`
(31 casos, incluida la fuente real y la combinación individual/múltiple).
Se comprueban también identidad, huecos, ambigüedad, títulos existentes, mezcla
de colecciones, conservación manual y móvil. No se suben archivos ni se alteran
registros durante la prueba de rangos. CA-B5.4 continúa pendiente.
La regresión general `tests/tomos_ui.py` mantiene sus 29 comprobaciones:
Berserk, Ultimate Spider-Man, Saga, Bloodborne, fechas parciales, fallos de fuente
y móvil. **104 comprobaciones aprobadas** en las tres suites de esta corrección.

## Corrección del aviso de obra · Invencible / Invincible

La propuesta de título funcionaba, pero el aviso por nombre comparaba palabras
literalmente. `Invencible #1-2.cbr` no coincidía con el título local «Invincible»
y pedía confirmar una sospecha innecesaria. `archivoPareceDeOtraObra()` admite
ahora una distancia de una edición en palabras de al menos siete caracteres.
No modifica títulos ni nombres de archivo. Una coincidencia exacta con otra obra
registrada se comprueba primero y mantiene el aviso.

Se añadió CA-B3.7 con evidencia: `python tests/nombre_archivo.py`, **30 casos**
de revisión de lotes, procesador individual y navegador real. Incluye el archivo
reportado para Invincible, Berserk y Watchmen, tildes, mayúsculas, una letra
omitida, nombres neutros, dos letras distintas y el nombre corto «Sago»/«Saga».
El mismo archivo sigue pidiendo revisar la obra al seleccionar Watchmen/Berserk.
Los comprimidos de prueba son inválidos a propósito: se comprueba que el flujo
correcto llega a validar el archivo y lo rechaza antes de guardar nada. Se conserva
el título manual, el rango 1–2 y la instantánea completa de obras y capítulos,
además de la cantidad de páginas. No se alteran registros ni archivos del usuario.

## Reemplazo explícito completado · CA-B5.4, B5.6 y B5.7

Desde el lápiz de **Ya subidos**, `editar_capitulo.php` ofrece **Reemplazar
archivo del capítulo**. Su formulario es independiente del guardado de metadatos.
La confirmación identifica obra, capítulo, archivo nuevo y cantidad de páginas
anteriores; cancelar conserva también los datos escritos sin guardar. Mantiene
Montserrat, colores y cabecera del panel, con adaptación a 390 px.

`reemplazar_capitulo.php` exige admin, token de sesión y confirmación expresa.
`reemplazo_capitulo.php` valida el CBR/CBZ con el mismo extractor de la subida,
comprueba la revisión del capítulo y bloquea su fila antes de publicar. Prepara
las páginas en una carpeta nueva, elimina **todas las filas antiguas antes del
INSERT** y confirma páginas, hash y recibo en una sola transacción. Los archivos
antiguos se retiran después del commit para conservarlos si hay rollback. No se
eliminan imágenes aún referenciadas por otro capítulo ni rutas fuera de `paginas/`.

La migración **04** añade `reemplazos_capitulo`; está aplicada y está incorporada
al esquema inicial. Guarda claves únicas, versiones anterior/nueva, SHA-256,
cantidad de páginas y rutas pendientes de limpieza. Un reintento con el mismo
archivo recupera el recibo; otras claves con versión antigua se rechazan. El
capítulo mantiene id, cómic, título, número, formato, rango, arco y fecha guardados.
Se invalida su recibo de subida inicial porque ese comprimido dejó de ser el vigente.
Un nombre sospechoso conserva la confirmación adicional de HU-B3.

**Evidencia:** `tests/reemplazo_capitulo.py`, **37 comprobaciones**. Usa dos
capítulos propios y ZIP/RAR reales, pruebas de fallo SQL después del DELETE y al
guardar el recibo, un trigger que exige retirar las filas antiguas antes del INSERT,
concurrencia con claves iguales/distintas, pestaña obsoleta, cambios de metadatos,
archivo compartido, rechazo de rutas ajenas y navegador móvil. Pierde una respuesta
después del commit y repite desde la interfaz sin volver a cambiar páginas.
Cancela y confirma el diálogo real y compara todas las obras/capítulos/páginas
del usuario antes y después. Los fixtures, carpetas y sesiones se eliminan.

## Sprint 7 · cierre documental

- [x] Operación de registro, editoriales, fechas, propuestas, lotes, recibos y
      reemplazo descrita en el DOC y en la guía de operación.
- [x] Migraciones 02, 03 y 04 enlazadas desde el README y explicadas en la guía.
- [x] Comandos de pruebas, fuentes, límites y decisiones documentados.
- [x] Mapa de arquitectura actualizado y criterios contrastados con evidencia.

Guía consolidada: [docs/OPERACION_Y_VERIFICACION.md](docs/OPERACION_Y_VERIFICACION.md).
Los criterios de las secciones A, B y C están completos. Las historias añadidas
durante las pruebas del usuario también conservan sus criterios y evidencia.
Los asuntos listados en **Fuera de alcance** no se incorporan a este cierre.

**Verificación final de esta entrega:** 37 comprobaciones de reemplazo, 45 de
subida individual, 33 de lotes y 29 de interfaz/edición de fechas: **144 casos**.
Se ajustó el selector de la prueba de fechas para apuntar al formulario de
metadatos, porque la pantalla ahora tiene también el formulario de reemplazo.
Los PHP modificados pasan el análisis de sintaxis y el diff no tiene errores
de espacios. Las suites ajenas a esta entrega mantienen la evidencia anterior.
El catálogo del usuario conserva **8 obras, 10 capítulos y 979 páginas**: incluye
las 49 páginas de Invincible que el usuario subió antes de este sprint.
