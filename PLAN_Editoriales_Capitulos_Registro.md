# Plan · Editoriales, capítulos y registro

**Proyecto:** Comic House · Panel de administración
**Fecha:** 14 de noviembre de 2026
**Metodología:** historias de usuario + fases (Scrum)

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

> **Hallazgo durante el Sprint 1.** La validación del servidor **sí existía**,
> pero tenía un agujero: usaba `!empty()`, y `!empty('   ')` es `true` en PHP.
> Una editorial de solo espacios pasaba el filtro y se guardaba el cómic sin
> editorial real. Ahora se comprueba con `trim() === ''` sobre los siete campos.

### HU-A2 · Crear la editorial desde el propio registro

> **Como** administrador que registra una obra de una editorial nueva,
> **quiero** crearla sin salir del formulario,
> **para** no perder lo que ya llevo escrito.

- [ ] CA-A2.1 · Si la fuente trae una editorial que no está, se ofrece crearla.
- [ ] CA-A2.2 · **No** se ofrece «elige otra»: sería un dato falso *(plan 1 §3.2)*.
- [ ] CA-A2.3 · Al crearla queda seleccionada, sin recargar la página.
- [ ] CA-A2.4 · Si la fuente no trajo editorial, se puede escribir el nombre.

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
- [ ] CA-B3.4 · Si el número se sale del rango publicado, se avisa.
- [ ] CA-B3.5 · Si el archivo no es `.cbr`/`.cbz` válido o no tiene imágenes, se
      **rechaza**.
- [x] CA-B3.6 · Nunca se bloquea por la heurística del nombre: el administrador
      puede tener razón y el archivo llamarse como sea.

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

- [ ] CA-B4.1 · Se pueden elegir varios archivos a la vez.
- [ ] CA-B4.2 · Cada uno muestra su número deducido y sus avisos **antes** de
      subir nada.
- [ ] CA-B4.3 · Los que tengan error se pueden corregir o descartar sin
      cancelar el resto.
- [ ] CA-B4.4 · Si uno falla a mitad, los ya subidos **se conservan** y se dice
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
- [ ] CA-B5.3 · Si se reintenta tras un fallo a medias, se completa lo que
      falta en vez de empezar de cero. *(Sprint 5, con la subida múltiple.)*
- [ ] CA-B5.4 · Las páginas de un capítulo reemplazado **se borran** antes de
      insertar las nuevas: nada de mezclar dos ediciones.

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

- [ ] CA-C1.1 · Al escribir el nombre se busca el logo en Wikipedia.
- [ ] CA-C1.2 · Se muestra **una vista previa** antes de guardarlo.
- [ ] CA-C1.3 · Se puede **rechazar** y subir otro archivo.
- [ ] CA-C1.4 · Si no se encuentra, se dice y se ofrece la subida manual.
- [ ] CA-C1.5 · Solo se aceptan imágenes de dominios de Wikimedia *(mismo
      criterio que `descargar_portada.php`)*.
- [ ] CA-C1.6 · Se valida que sea una imagen real, con límite de tamaño.

### HU-C2 · No duplicar editoriales

> **Como** administrador,
> **quiero** que no se creen dos veces la misma editorial,
> **para** que el catálogo no tenga «DC» y «DC Comics» por separado.

- [ ] CA-C2.1 · Antes de crear se busca una existente ignorando mayúsculas y
      tildes.
- [ ] CA-C2.2 · Si ya existe, **se reutiliza** en vez de crear otra.
- [ ] CA-C2.3 · Si hay una parecida, se avisa antes de crear.

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
- **Traducir títulos de capítulo**, hasta ver si hace falta.

---

*Documento de trabajo. Las consultas a Fandom, MangaDex y la estructura de los
wikis se verificaron con peticiones reales el 14 de noviembre de 2026. Donde una
fuente no sirve, queda la prueba que lo demuestra.*
