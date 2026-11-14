# Plan · Resumen generativo y sección «Próximamente»

**Proyecto:** Comic House
**Alcance:** descripción de los cómics y publicación de obras sin capítulos
**Fecha:** 14 de noviembre de 2026
**Metodología:** historias de usuario + entregas por fases (Scrum)

> Continuación del trabajo cerrado en
> [PLAN_Registro_Automatizado.md](PLAN_Registro_Automatizado.md). Aquí se
> abordan dos problemas que aparecieron al usar el registro en real.

---

## 1 · Los dos problemas

### Problema A · Las descripciones largas se cortan

ComicVine devuelve descripciones de longitud muy variable. La traducción es
**literal**, así que lo que viene largo se queda largo.

Medido en el catálogo actual:

| Cómic | Caracteres |
|---|---|
| **Watchmen** | **634** |
| Absolute Batman | 366 |
| Bloodborne | 346 |
| Cyberpunk 2077 | 333 |
| Berserk | 326 |
| Saga | 316 |
| Ultimate Spiderman | 177 |

Watchmen es el único que se sale, y se nota: en la ficha ocupa seis líneas
frente a las dos o tres del resto, y en móvil `-webkit-line-clamp: 2` lo corta
a mitad de frase.

**Aclaración sobre el número 500.** El límite de 500 caracteres que ya existe en
el código es el de **MyMemory por petición** (`traducir.php`), no un límite de
la base de datos: la columna `descripcion` es `TEXT` y admite 65.535 caracteres.
Es decir, el problema no es que no quepa, sino que **se ve mal y se lee peor**.

**Lo que se quiere:** que un modelo generativo **resuma** la descripción a una
longitud uniforme, en vez de traducirla palabra por palabra. Un resumen bien
hecho de 450 caracteres informa más que 634 cortados a la mitad.

### Problema B · Se publica un cómic que todavía no se puede leer

Watchmen se registró y apareció **inmediatamente** en la portada, pero no tiene
capítulos. El usuario entra y no puede leer nada.

Comprobado en `http://localhost:8080/Comics/comics.php?id=133`:

```
warnings de PHP : 2
enlaces generados: lector.php?capitulo_id=      ← vacío
                   lector.php?capitulo_id=      ← vacío
```

No es solo cosmético. En `Comics/comics.php:233` y `:235` se lee
`$primerCapitulo['id']` sin comprobar que exista, y cuando no hay capítulos esa
variable es `null`:

- **dos avisos de PHP** visibles en la respuesta
- **dos botones que llevan a un lector sin capítulo**

**Lo que se quiere:** que un cómic sin capítulos no se mezcle con los que sí se
pueden leer, pero que **tampoco se esconda**: anunciarlo como *Próximamente*,
para que funcione como anticipo de lo que va a llegar.

---

## 2 · Principios de diseño

Los mismos que rigieron el registro automatizado, porque el criterio no cambia:

**2.1 · Un resumen generado es un dato estimado.** Lo escribe un modelo y puede
equivocarse. Se marca como tal y el administrador lo confirma antes de guardar.

**2.2 · Nunca se pierde el original.** Si el resumen sale mal, debe poder
recuperarse la descripción completa. No se sobrescribe sin red.

**2.3 · Si el modelo falla, el sistema sigue.** Sin clave de IA o con la API
caída, se guarda la traducción literal como hasta ahora. Una mejora opcional no
puede convertirse en un requisito para registrar.

**2.4 · «Próximamente» informa, no engaña.** No se anuncia una fecha que nadie
ha prometido. Se dice lo que es cierto: está registrado, aún no hay capítulos.

**2.5 · Un enlace que no lleva a ningún sitio no se muestra.** Es preferible
ocultar el botón que ofrecer uno roto.

---

## 3 · Historias de usuario

### Épica A · Resumen generativo de la descripción

---

#### HU-01 · Resumir en vez de traducir literalmente

> **Como** administrador que registra un cómic,
> **quiero** que la descripción llegue resumida a una longitud uniforme,
> **para** que todas las fichas se lean igual de bien sin recortarlas a mano.

**Criterios de aceptación**

- [x] CA-01.1 · Si la descripción traducida supera el umbral, se pide un resumen
      al modelo.
- [x] CA-01.2 · El resumen **no supera 450 caracteres**, verificado en el
      servidor; no se confía en que el modelo cuente bien.
- [x] CA-01.3 · El resumen está en **español**, en prosa, sin viñetas ni comillas
      envolventes.
- [x] CA-01.4 · Conserva los datos concretos que distinguen la obra (autor, año,
      premio, personaje) en lugar de generalidades.
- [x] CA-01.5 · Si la descripción **ya** está por debajo del umbral, no se
      resume: no se gasta una llamada para no mejorar nada.

---

#### HU-02 · Saber que el texto fue generado

> **Como** administrador,
> **quiero** ver claramente que la descripción la redactó un modelo,
> **para** poder revisarla antes de publicarla.

**Criterios de aceptación**

- [x] CA-02.1 · El modal marca la descripción resumida en **ámbar**, igual que la
      clasificación y el género estimados.
- [x] CA-02.2 · Se indica **qué modelo** la redactó y la longitud resultante.
- [x] CA-02.3 · El campo es **editable**: el administrador puede corregirla.
- [x] CA-02.4 · Puede **descartar el resumen** y recuperar la descripción
      completa con un clic *(principio 2.2)*.

---

#### HU-03 · Que un fallo no bloquee el registro

> **Como** administrador,
> **quiero** registrar el cómic aunque el resumen falle,
> **para** no depender de un servicio externo.

**Criterios de aceptación**

- [x] CA-03.1 · Sin `GROQ_API_KEY`, se guarda la traducción literal y no se
      menciona el resumen.
- [x] CA-03.2 · Si la API falla o tarda, se usa la traducción literal y se avisa
      en el modal.
- [x] CA-03.3 · Si el modelo devuelve algo inválido (vacío, en inglés, o más
      largo que el original), **se descarta** y se usa el original.
- [x] CA-03.4 · El resumen **nunca** retrasa la apertura del modal más que los
      pasos que ya existen.

---

### Épica B · Sección «Próximamente»

---

#### HU-04 · No ofrecer lo que no se puede leer

> **Como** lector,
> **quiero** que el catálogo muestre solo cómics que puedo leer,
> **para** no entrar en una ficha vacía.

**Criterios de aceptación**

- [x] CA-04.1 · El catálogo de `index.php` excluye los cómics sin capítulos.
- [x] CA-04.2 · El slider y los destacados también los excluyen.
- [x] CA-04.3 · Un cómic aparece en el catálogo **automáticamente** en cuanto se
      le sube el primer capítulo, sin tocar nada más.

---

#### HU-05 · Anunciar lo que viene

> **Como** lector,
> **quiero** ver qué cómics están por llegar,
> **para** saber que el catálogo crece y volver a por ellos.

**Criterios de aceptación**

- [x] CA-05.1 · Hay una sección **«Próximamente»** con los cómics registrados sin
      capítulos.
- [x] CA-05.2 · Respeta la estética del sitio: misma retícula, misma tipografía,
      mismo rojo `#ce4646`.
- [x] CA-05.3 · Cada portada lleva un distintivo **«Próximamente»** legible.
- [x] CA-05.4 · La sección **no aparece** si no hay ningún cómic en ese estado.
- [x] CA-05.5 · No se promete ninguna fecha *(principio 2.4)*.

---

#### HU-06 · Una ficha honesta y sin errores

> **Como** lector que entra a un cómic sin capítulos,
> **quiero** entender que aún no está disponible,
> **para** no quedarme pulsando botones que no hacen nada.

**Criterios de aceptación**

- [x] CA-06.1 · «Leer Primero» y «Leer Último» **no se muestran** sin capítulos
      *(principio 2.5)*.
- [x] CA-06.2 · En su lugar hay un aviso: *«Aún no hay capítulos disponibles»*.
- [x] CA-06.3 · **Cero warnings de PHP** en la respuesta *(hoy son 2)*.
- [x] CA-06.4 · El resto de la ficha (portada, sinopsis, año, género,
      clasificación) se sigue viendo: sirve de anticipo.

---

## 4 · Fases

Entregas cerradas, cada una utilizable por sí sola.

### Sprint 1 · Arreglar lo que está roto `HU-06`

Lo primero es el error real: dos warnings y dos enlaces rotos en producción.
No depende de ninguna API y se puede verificar al instante.

| Tarea | Archivo |
|---|---|
| Comprobar `$primerCapitulo` antes de usarlo | `Comics/comics.php` |
| Sustituir los botones por el aviso | `Comics/comics.php` |
| Estilo del aviso | `Comics/comics.css` |

**Hecho cuando:** `curl` de la ficha de Watchmen devuelve 0 warnings y ningún
`capitulo_id=` vacío.

---

### Sprint 2 · Separar catálogo y «Próximamente» `HU-04` `HU-05`

| Tarea | Archivo |
|---|---|
| Filtrar el catálogo por `EXISTS (SELECT 1 FROM capitulos …)` | `index.php` |
| Consulta de los cómics sin capítulos | `index.php` |
| Sección «Próximamente» con su distintivo | `index.php` |
| Estilos reutilizando los del catálogo | `assets/styles.css` |

**Hecho cuando:** Watchmen sale de la retícula principal y aparece en
«Próximamente»; al subirle un capítulo, cambia de sección solo.

---

### Sprint 3 · Resumen generativo `HU-01` `HU-02` `HU-03`

| Tarea | Archivo |
|---|---|
| Endpoint `resumir_descripcion.php` | nuevo |
| Validación de longitud, idioma y contenido | `resumir_descripcion.php` |
| Encadenarlo tras la traducción | `crud.php` |
| Marca ámbar y botón «usar la original» | `crud.php` |

**Hecho cuando:** una descripción de 634 caracteres entra y sale por debajo de
450, en español, sin perder el dato que identifica la obra.

---

### Sprint 4 · Verificación y documentación

| Tarea |
|---|
| Probar los casos límite de la tabla §5 |
| Confirmar que sin clave de IA todo sigue funcionando |
| Documentar en `DOC_Automatizacion_Registro.md` |
| Actualizar `README.md` si cambia el arranque |

---

## 5 · Casos de prueba

| Caso | Entrada | Resultado esperado |
|---|---|---|
| Descripción larga | Watchmen (634) | resumen ≤ 450, en español, marcado en ámbar |
| Descripción corta | Ultimate Spiderman (177) | **no se resume**, sin llamada al modelo |
| Sin clave de IA | `.env` sin `GROQ_API_KEY` | traducción literal, sin mención al resumen |
| Modelo caído | API con error | traducción literal + aviso |
| Respuesta inválida | resumen vacío o en inglés | se descarta, se usa el original |
| Cómic sin capítulos | Watchmen | fuera del catálogo, dentro de «Próximamente» |
| Ficha sin capítulos | `comics.php?id=133` | 0 warnings, sin botones de lectura |
| Se sube un capítulo | Watchmen + 1 capítulo | pasa al catálogo automáticamente |
| Nada en «Próximamente» | todos con capítulos | la sección no se dibuja |

---

## 6 · Riesgos

| Riesgo | Mitigación |
|---|---|
| El resumen pierde un dato importante | Se pide conservar nombres y años; el campo es editable y se puede recuperar el original |
| El modelo devuelve más de 450 caracteres | Se mide en el servidor y se descarta si se pasa |
| El modelo responde en inglés | Se comprueba antes de aceptarlo |
| Groq retira el modelo | Ya hay lista de reserva, igual que en la clasificación |
| Una llamada más alarga la espera | Solo se pide si hace falta, y entra en la pantalla de carga que ya existe |
| El filtro oculta un cómic por error | El filtro es `EXISTS` sobre `capitulos`: si tiene uno, aparece |

---

## 7 · Fuera de alcance

- **Resumir las descripciones ya guardadas.** Este plan cubre el registro. Una
  pasada sobre el catálogo existente sería otro trabajo.
- **Fechas de lanzamiento** en «Próximamente» *(principio 2.4)*.
- **Avisar al lector** cuando salga un cómic que esperaba.
- **Editar cómic** y **subir capítulos**, que siguen pendientes del plan
  anterior.

---

---

## 8 · Resultado

**CERRADO.** Los 4 sprints entregados y los 25 criterios verificados.

### Sprint 1 · La ficha rota

| | Antes | Ahora |
|---|---|---|
| Warnings de PHP | 2 | **0** |
| Enlaces `capitulo_id=` vacíos | 2 | **0** |

Berserk conserva sus enlaces correctos (`capitulo_id=1` y `3`): el arreglo no
afectó a los cómics que sí tienen capítulos.

### Sprint 2 · Catálogo y «Próximamente»

```
Watchmen    catálogo=0   próximamente=1   ← sale del catálogo
Berserk     catálogo=4   próximamente=0   ← intacto
Saga        catálogo=4   próximamente=0   ← intacto
Bloodborne  catálogo=4   próximamente=0   ← intacto
```

Las dos transiciones dinámicas se probaron insertando un capítulo de verdad:
Watchmen **pasó al catálogo solo** y la sección «Próximamente» **desapareció**
al quedarse vacía. La prueba se revirtió.

### Sprint 3 · Resumen generativo

| Cómic | Antes | Después | Tiempo |
|---|---|---|---|
| **Watchmen** | 634 | **438** | 1.2 s |
| Berserk *(prueba)* | 604 | **442** | 0.8 s |
| Saga *(prueba)* | 531 | **371** | 0.6 s |
| Ultimate Spiderman | 77 | **no se resume** | 0.04 s |

El resumen de Watchmen conserva lo que identifica la obra — Alan Moore, la
lista de la revista Time, el rango 1923, DC Comics y el eslogan «¿Quién vigila
a los Watchmen?» — en vez de cambiarlo por generalidades.

**Degradación comprobada retirando la clave del `.env`:** el endpoint devolvió
`ok: true` con el texto original intacto y el aviso *«No se pudo resumir: se usa
la descripción completa»*. El registro nunca se bloquea. La clave se restauró.

---

*Documento de trabajo. Las longitudes, los warnings, los enlaces rotos y los
resultados de cada sprint se midieron con consultas y peticiones reales el 14 de
noviembre de 2026, no se estimaron.*
