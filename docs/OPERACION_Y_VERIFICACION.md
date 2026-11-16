# Operación y verificación del registro y la subida

Estado al 15 de noviembre de 2026, America/Bogota. El código está en esta
aplicación PHP; no se necesita compilar JavaScript. Requiere Docker activo.

## Registrar una obra y su editorial

En el catálogo, escribe el título, elige el candidato correcto y revisa su año,
editorial, clasificación, género, descripción y portada antes de aplicar los
datos. El registro exige los siete campos obligatorios y la editorial real.
Las clasificaciones verificadas se distinguen de las estimaciones; los campos
manuales siguen disponibles si falla una fuente.

Si la editorial de la fuente no existe, **Crear** abre el asistente con ese
nombre. Si no se conoce, puedes escribirlo. Revisa el logo de Wikipedia/Wikimedia,
selecciónalo o sube otro manualmente. Desde el registro puedes crear sin logo y
completarlo después; el catálogo de editoriales exige imagen. La editorial nueva
queda seleccionada sin recargar y sin restablecer género o clasificación.

Se reutilizan nombres iguales, ignorando mayúsculas, tildes y espacios, y alias
conocidos. Un nombre parecido requiere revisar si es el mismo o confirmar que
es distinto. Los logos nuevos admiten PNG/JPG/WebP/GIF, hasta 5 MB y 16 megapíxeles,
y se guardan como PNG. Los SVG de Wikimedia requieren miniatura PNG. Una búsqueda
sin resultados permite la subida manual. No se sustituye la editorial por otra.

## Subir una entrega o un lote

Selecciona la obra y elige los CBR/CBZ. La numeración propuesta por el nombre es
editable. Las grapas conservan su número; un archivo `#1-2` conserva ambos y los
tomos de manga mantienen sus rangos. Revisa la edición de la fuente cuando haya
varias; no se mezclan relanzamientos ni editoriales para resolverla.

La fecha puede ser completa o solo mes/año, sin inventar el día 1. Los títulos
individuales publicados tienen prioridad. Si falta uno y se confirma el contenido
de su recopilatorio, se propone ese nombre indicando el origen, los números del
archivo y cualquier traducción orientativa. En ausencia de datos verificables se
propone un nombre descriptivo marcado. Se conservan las correcciones manuales.

En un lote, cada fila se revisa antes de enviarse. Corrige o descarta las que
tengan errores; los archivos se guardan de uno en uno y los éxitos se conservan
si falla otro. Puedes reanudar en la misma pestaña: el manifiesto guarda datos,
no bytes; vuelve a elegir únicamente los archivos pendientes. Los recibos evitan
duplicar un intento cuya respuesta se perdió. Vaciar el lote no borra capítulos.

Los solapes se bloquean. Una numeración fuera del rango registrado o un nombre
que parece de otra obra requieren revisión y confirmación. Son avisos revisables,
no identificación del contenido del cómic. Se tolera una letra distinta en
palabras largas como «Invencible»/«Invincible»; una coincidencia exacta con otra
obra del catálogo conserva el aviso. El comprimido se valida siempre: formato,
integridad, imágenes, orden natural y páginas dobles.

## Reemplazar páginas de un capítulo existente

Abre el lápiz de **Ya subidos**. Guarda primero cualquier cambio de título,
numeración o fecha. En **Reemplazar archivo del capítulo**, elige el archivo,
pulsa **Revisar reemplazo** y comprueba la confirmación, que identifica obra,
capítulo, archivo y páginas anteriores. Volver o Escape no modifica el capítulo.

El reemplazo mantiene el id y los metadatos guardados. El servidor valida y
extrae el archivo completo antes de tocar las filas antiguas. Prepara las imágenes
en una carpeta nueva y, en una transacción, retira todas las filas de páginas
anteriores antes de insertar las nuevas. Si falla, revierte y elimina solo los
archivos nuevos. Los archivos antiguos se retiran **después del commit**, cuando
no tengan referencias de otros capítulos. Así no se mezclan ediciones ni se
pierde el capítulo por un archivo dañado o un fallo SQL.

Una versión del capítulo y un bloqueo de fila impiden que una pestaña antigua
sobrescriba cambios recientes. Los recibos de reemplazo son independientes de
la subida inicial: un reintento del mismo archivo recupera su resultado, y una
clave con otro archivo o una edición posterior se rechaza. El reemplazo invalida
el recibo de la subida inicial, cuyo archivo ya no está vigente. Un nombre que
parece de otra obra conserva su confirmación adicional. Exige admin y token de sesión.

La tabla de recibos conserva rutas pendientes de limpiar si falla la retirada
física. Un reemplazo posterior o el reintento del recibo vuelve a comprobarlas.
La limpieza solo actúa dentro de `paginas/` y no elimina imágenes compartidas
que todavía tengan referencias. Las filas publicadas ya apuntan a la edición nueva.

## Instalación y migraciones

Una base nueva usa `database/init/01_schema.sql`. Sobre una base existente,
aplica en orden las migraciones que falten. Cambiar el esquema inicial no migra
un volumen Docker ya creado.

| Migración | Cambia |
|---|---|
| `migracion_02_fechas_parciales.sql` | Precisión mensual y comprobaciones de fecha |
| `migracion_03_subida_lotes.sql` | Clave de intento y SHA-256 de subida |
| `migracion_04_reemplazos.sql` | Recibos independientes y retirada pendiente de páginas antiguas |

Las tres están aplicadas localmente. La 04 solo añade una tabla; no modifica
capítulos existentes. No requiere claves de APIs nuevas. Mantén la configuración
en `.env`; no copies sus valores en documentos ni mensajes.

## Pruebas reproducibles

Ejecuta desde el repositorio. PHP corre dentro de `comic_house_web`; Python usa
Playwright y Edge/Chrome local. Los scripts que necesitan el catálogo real lo
consultan; los que suben o reemplazan contenido crean fixtures propios y los
eliminan con sus imágenes y sesiones al terminar. Ejecuta las suites con
escrituras **secuencialmente**, porque comparan instantáneas del catálogo.

| Comando | Comprueba |
|---|---|
| `docker exec comic_house_web php tests/tomos_fuentes.php` | Identidad, fechas y recopilatorios |
| `docker exec comic_house_web php tests/fechas_capitulo.php` | Validación de precisión de fechas |
| `docker exec comic_house_web php tests/editoriales.php` | Identidad editorial y logos |
| `python tests/tomos_ui.py` | Propuestas, edición de fechas, fuentes y móvil |
| `python tests/rangos_grapas_ui.py` | Invincible, rangos y nombres de recopilatorios |
| `python tests/subida_capitulo.py` | Archivo real, atomicidad y validación |
| `python tests/lote_capitulos.py` | Cola, fallos y reanudación de doce entregas |
| `python tests/registro_validacion_ui.py` | Registro después de confirmar metadatos |
| `python tests/editoriales_ui.py` | Logos reales, creación desde registro y concurrencia |
| `python tests/nombre_archivo.py` | Avisos por nombre sin falsos positivos reportados |
| `python tests/reemplazo_capitulo.py` | Sustitución, rollback, concurrencia, recibos y móvil |

El plan conserva la evidencia de cada fase en
[PLAN_Editoriales_Capitulos_Registro.md](../PLAN_Editoriales_Capitulos_Registro.md).
El mapa técnico está en [ARQUITECTURA_Y_CONTEXTO.md](ARQUITECTURA_Y_CONTEXTO.md).

## Límites

- Los datos externos pueden faltar o estar incompletos; son propuestas revisables.
- El archivo no se identifica por OCR. Una similitud de nombre es una heurística.
- Una traducción de nombre no equivale a un título oficial de la edición española.
- El manifiesto de un lote depende de la pestaña y no conserva los comprimidos.
- Reemplazar páginas no cambia la numeración de la obra ni reorganiza capítulos.
- El estado de publicación inferido de ComicVine es una estimación; debe revisarse.
- Editar la ficha de un cómic y descargar capítulos externos permanecen fuera
  del alcance de este plan.
