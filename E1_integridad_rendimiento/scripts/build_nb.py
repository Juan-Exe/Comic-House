# -*- coding: utf-8 -*-
"""Construye E1_comic_house_integridad_rendimiento.ipynb celda por celda."""
import json
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SALIDA = os.path.join(BASE, 'E1_comic_house_integridad_rendimiento.ipynb')

celdas = []


def _lineas(texto):
    """Divide en lineas conservando el \\n final de cada una (formato ipynb)."""
    return texto.strip('\n').splitlines(keepends=True)


def md(texto):
    celdas.append({'cell_type': 'markdown', 'metadata': {},
                   'source': _lineas(texto)})


def code(texto):
    celdas.append({'cell_type': 'code', 'metadata': {}, 'execution_count': None,
                   'outputs': [], 'source': _lineas(texto)})


# =====================================================================
md("""
# E1 · Núcleo de datos de **Comic House**
### Modelo, integridad y rendimiento · Optativa II

Este notebook transfiere la lógica del laboratorio S04 (dataset de pedidos) al
proyecto propio: **Comic House**, una plataforma de lectura de cómics y mangas.

**Regla de trabajo (la misma del laboratorio):** no corregir datos silenciosamente.
Primero identificar el problema, después decidir qué regla del sistema debería impedirlo.

---

### Problema y datos (punto 01 del enunciado)

Comic House necesita persistir un catálogo de cómics con sus editoriales,
capítulos y páginas. El dataset de trabajo es `comics`, exportado desde la base
MariaDB real del proyecto.

**Preguntas que el sistema debe poder responder:**

1. ¿Cuántas obras hay por editorial y cuáles son sus clasificaciones por edad?
2. ¿Qué obras siguen *en publicación* y cuáles ya terminaron?
3. ¿Cuántas páginas tiene cada capítulo y cuáles son las obras más extensas?

**Ejecuta las celdas en orden.** Cada una hace *una sola cosa*.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 01 · Cargar el dataset sucio y reconocerlo

Cargamos `comics_sucio.csv`. Todavía **no** corregimos nada: solo miramos qué llegó.
""")

code("""
import pandas as pd
from pathlib import Path

ARCHIVO = Path('dataset_sucio/comics_sucio.csv')

if not ARCHIVO.exists():
    try:
        from google.colab import files
        print('Sube el archivo:', ARCHIVO.name)
        files.upload()
        ARCHIVO = Path(ARCHIVO.name)
    except Exception:
        raise FileNotFoundError(f'No se encontro {ARCHIVO}. Ejecuta antes scripts/01_ensuciar.py')

df = pd.read_csv(ARCHIVO)

COLUMNAS_VISTA = ['id', 'titulo', 'clasificacion', 'anio', 'tipo',
                  'estado_publicacion', 'editorial_id', 'destacado']

print('Dimensiones:', df.shape)
print('Columnas:', len(df.columns))
df[COLUMNAS_VISTA]
""")

md("""
**Resultado esperado:** `(13, 16)` — 13 filas y 16 columnas.

El catálogo real tiene 5 obras; el dataset sucio trae 13 filas porque se
inyectaron registros defectuosos. Si ves otra dimensión, vuelve a generar el
dataset con `scripts/01_ensuciar.py`.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 02 · Diagnóstico de integridad

Buscamos señales de datos que volverían poco confiable la aplicación.
**Solo contamos: todavía no se corrige nada.**

Reglas del dominio Comic House:

| Regla | Justificación |
|---|---|
| `id` único y positivo | En MariaDB es `INT UNSIGNED AUTO_INCREMENT` |
| `titulo` obligatorio | Sin título la obra no se puede mostrar ni buscar |
| `titulo` único | Dos obras con el mismo nombre son el mismo registro duplicado |
| `anio` entre 1900 y el año actual | Un cómic no puede publicarse en el futuro |
| `clasificacion` ∈ {ATP, +13, +16, +18} | Control parental del lector |
| `estado_publicacion` ∈ {En publicacion, Publicacion Terminada} | Dominio cerrado de la app |
| `editorial_id` debe existir en `editoriales` | Integridad referencial |
| `destacado` ∈ {0, 1} | Es un booleano en la portada |
""")

code("""
ANIO_ACTUAL = 2026
CLASIFICACIONES = ['ATP', '+13', '+16', '+18']
ESTADOS = ['En publicacion', 'Publicacion Terminada']

editoriales = pd.read_csv('../export_csv/editoriales.csv')
IDS_EDITORIAL = set(editoriales['id'])

print('Editoriales validas:', sorted(IDS_EDITORIAL))
print('-' * 55)

anio = pd.to_numeric(df['anio'], errors='coerce')
# un titulo nulo NO cuenta como duplicado de otro nulo: se mide aparte
titulo_norm = df['titulo'].str.strip().str.lower()
titulo_vacio = df['titulo'].isna() | (df['titulo'].fillna('').str.strip() == '')

print('Filas totales                :', len(df))
print('PK duplicadas (id)           :', int(df['id'].duplicated().sum()))
print('PK no positivas (id <= 0)    :', int((df['id'] <= 0).sum()))
print('Titulos vacios o nulos       :', int(titulo_vacio.sum()))
print('Titulos duplicados (logico)  :', int((titulo_norm.duplicated() & titulo_norm.notna()).sum()))
print('Anios futuros (>', ANIO_ACTUAL, ')     :', int((anio > ANIO_ACTUAL).sum()))
print('Clasificacion fuera dominio  :', int((~df['clasificacion'].isin(CLASIFICACIONES)).sum()))
print('Estado fuera de dominio      :', int((~df['estado_publicacion'].isin(ESTADOS)).sum()))
print('Editorial inexistente (FK)   :', int((~df['editorial_id'].isin(IDS_EDITORIAL)).sum()))
print('Destacado no booleano        :', int((~df['destacado'].isin([0, 1])).sum()))

print()
print('Valores encontrados en "tipo":')
print(sorted(df['tipo'].astype(str).unique()))
print()
print('Valores encontrados en "estado_publicacion":')
print(sorted(df['estado_publicacion'].astype(str).unique()))
""")

md("""
### Comprobación · estas son las anomalías que deberías encontrar

| Anomalía | Registro | Cantidad |
|---|---|---|
| PK duplicada | `id=3` repetido | 1 |
| PK no positiva | `id=-4` (Sandman) | 1 |
| Título vacío | `id=6` | 1 |
| Título duplicado lógico | `berserk` y `Absolute Batman` | 2 |
| Año futuro (`2099`) | `id=2` (Saga) | 1 |
| Clasificación inválida (`18+`) | `id=10` (Hellsing) | 1 |
| Estado con typo (`En publicacionn`) | `id=9` (Invincible) | 1 |
| Editorial inexistente (`999`) | `id=11` (Blame!) | 1 |
| Destacado no booleano (`5`) | `id=7` (Hellboy) | 1 |

El conteo de **títulos duplicados es 2**: `berserk` (duplicado lógico de
`Berserk`, distinto uso de mayúsculas) y `Absolute Batman`, que se repite como
efecto de la fila con PK duplicada. Esto muestra que **un duplicado de clave
primaria arrastra también una violación de la clave natural**.

Además, `tipo` llega en 5 formas distintas (`' MANGA '`, `'comic'`, `'COMIC '`,
`'Comic'`, `'Manga'`) que en realidad son solo 2 valores reales.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 03 · Normalizar SOLO lo inequívoco

Aquí corregimos **formato**, no contenido. Quitar espacios y unificar
mayúsculas es reversible y no inventa información: `' MANGA '` y `'Manga'`
son indiscutiblemente el mismo valor.

**No se toca** ningún dato cuyo valor correcto no podamos deducir con evidencia.
""")

code("""
df_norm = df.copy()

# Texto: quitar espacios sobrantes
for col in ['titulo', 'genero', 'tipo', 'clasificacion', 'estado_publicacion', 'editorial']:
    df_norm[col] = df_norm[col].astype(str).str.strip()

# 'tipo' es un dominio cerrado: unificamos a Capitalizado
df_norm['tipo'] = df_norm['tipo'].str.capitalize()

# 'genero' se guarda capitalizado para evitar 'ciencia ficcion' vs 'Ciencia ficcion'
df_norm['genero'] = df_norm['genero'].str.capitalize()

print('ANTES  ->', sorted(df['tipo'].astype(str).unique()))
print('DESPUES->', sorted(df_norm['tipo'].unique()))
print()
print('Generos normalizados:', sorted(df_norm['genero'].unique()))
""")

md("""
**Observa:** `tipo` pasó de 5 formas textuales a 2 valores reales (`Comic`, `Manga`).

Fíjate en lo que **no** hicimos: no cambiamos `En publicacionn` por
`En publicacion`. Aunque *parece* un typo evidente, corregirlo sería suponer la
intención del dato. Ese registro se **rechaza** en la siguiente celda.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 04 · Aplicar reglas de unicidad y separar lo inválido

Dos pasos distintos, en este orden:

1. **Duplicados** → se conserva la primera ocurrencia (`keep='first'`).
2. **Reglas de dominio** → lo que no se puede validar se aparta a `df_rechazados`.

Los registros rechazados **no se borran**: se guardan para poder auditarlos.
""")

code("""
df_limpio = df_norm.copy()

# OJO: al normalizar con .astype(str), un titulo nulo (NaN) se convierte en el
# texto 'nan', que NO es cadena vacia. Lo devolvemos a nulo real para que la
# regla de obligatoriedad pueda detectarlo.
df_limpio['titulo'] = df_limpio['titulo'].replace(['nan', 'None', ''], pd.NA)

# --- Paso 1: unicidad ---
antes = len(df_limpio)
df_limpio = df_limpio.drop_duplicates(subset='id', keep='first')
dup_pk = antes - len(df_limpio)

antes = len(df_limpio)
titulo_key = df_limpio['titulo'].str.strip().str.lower()
# los nulos no se consideran duplicados entre si: se rechazan en el paso 2
df_limpio = df_limpio.loc[~(titulo_key.duplicated(keep='first') & titulo_key.notna())]
dup_titulo = antes - len(df_limpio)

# --- Paso 2: reglas de dominio ---
anio_num = pd.to_numeric(df_limpio['anio'], errors='coerce')

reglas_validas = (
    (df_limpio['id'] > 0)
    & df_limpio['titulo'].notna()
    & (df_limpio['titulo'].fillna('').str.strip() != '')
    & anio_num.between(1900, ANIO_ACTUAL)
    & df_limpio['clasificacion'].isin(CLASIFICACIONES)
    & df_limpio['estado_publicacion'].isin(ESTADOS)
    & df_limpio['editorial_id'].isin(IDS_EDITORIAL)
    & df_limpio['destacado'].isin([0, 1])
)

df_rechazados = df_limpio.loc[~reglas_validas].copy()
df_limpio = df_limpio.loc[reglas_validas].copy()

print('RECONCILIACION')
print('  Filas iniciales          :', len(df))
print('  - PK duplicada apartada  :', dup_pk)
print('  - Titulo duplicado       :', dup_titulo)
print('  - Invalidos apartados    :', len(df_rechazados))
print('  = Filas validas          :', len(df_limpio))
print()
print('Registros rechazados y su motivo:')
df_rechazados[['id', 'titulo', 'clasificacion', 'anio', 'estado_publicacion',
               'editorial_id', 'destacado']]
""")

md("""
### Comprobación

```
13 filas iniciales − 1 PK duplicada − 1 título duplicado − 7 inválidos = 4 válidas
```

Los duplicados se apartan en un **paso distinto** al de las reglas de dominio;
por eso no forman parte del conteo de los 7 inválidos.

Sobreviven **4 obras reales**: Berserk, Absolute Batman, Cyberpunk 2077 y
Bloodborne. Los 7 rechazos son, uno por regla violada:

| id | Obra | Regla violada |
|---|---|---|
| 2 | Saga | año futuro (2099) |
| 6 | *(sin título)* | título obligatorio (`NOT NULL`) |
| 7 | Hellboy | `destacado = 5` no es booleano |
| 9 | Invincible | estado `En publicacionn` |
| 10 | Hellsing | clasificación `18+` |
| 11 | Blame! | editorial 999 inexistente |
| −4 | Sandman | id negativo |

> **Detalle de implementación:** al normalizar con `.astype(str)` en la CELDA 03,
> un título nulo se convierte en el **texto** `'nan'`, que no es cadena vacía y
> por tanto *pasaría* el filtro. Por eso la celda lo devuelve a nulo real antes
> de aplicar las reglas. Es un error silencioso clásico: la fila inválida se
> cuela sin que nada falle.

Fíjate en **Sandman**: todos sus demás campos son correctos. Lo único que lo
invalida es la PK negativa — por eso aísla exactamente esa regla.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 05 · Guardar el dataset limpio

Persistimos ambos resultados: lo válido **y** lo rechazado. Un pipeline que
descarta silenciosamente los errores no es auditable.
""")

code("""
from pathlib import Path

Path('dataset_limpio').mkdir(exist_ok=True)

df_limpio.to_csv('dataset_limpio/comics_limpio.csv', index=False, encoding='utf-8')
df_rechazados.to_csv('dataset_limpio/comics_rechazados.csv', index=False, encoding='utf-8')

print('Guardados:')
print('  dataset_limpio/comics_limpio.csv     ', len(df_limpio), 'filas')
print('  dataset_limpio/comics_rechazados.csv ', len(df_rechazados), 'filas')
""")

# ---------------------------------------------------------------------
md("""
## CELDA 06 · Convertir las reglas en restricciones de base de datos

Hasta aquí la limpieza dependía de que *alguien ejecutara este notebook*.
Ahora la **base de datos** se vuelve responsable de impedir los datos inválidos.

| Regla del negocio | Restricción SQL |
|---|---|
| `id` no se repite | `PRIMARY KEY` |
| `id` es positivo | `CHECK (id > 0)` |
| dos obras no comparten título | `UNIQUE (titulo)` |
| el título es obligatorio | `NOT NULL` |
| la editorial debe existir | `FOREIGN KEY` |
| el año no puede ser futuro | `CHECK` |
| clasificación de un conjunto fijo | `CHECK ... IN` |
| destacado es booleano | `CHECK IN (0,1)` |
""")

code("""
import sqlite3

conn = sqlite3.connect(':memory:')
conn.execute('PRAGMA foreign_keys = ON;')

conn.executescript(\"\"\"
CREATE TABLE editoriales (
    id     INTEGER PRIMARY KEY,
    nombre TEXT NOT NULL UNIQUE,
    imagen TEXT
);

CREATE TABLE comics (
    id                 INTEGER PRIMARY KEY,
    titulo             TEXT    NOT NULL UNIQUE,
    clasificacion      TEXT    NOT NULL,
    descripcion        TEXT,
    capitulos          TEXT,
    anio               INTEGER NOT NULL,
    genero             TEXT,
    portada            TEXT,
    editorial          TEXT,
    tipo               TEXT    NOT NULL,
    destacado          INTEGER NOT NULL DEFAULT 0,
    estado_publicacion TEXT    NOT NULL,
    imagen_slider      TEXT,
    logo_slider        TEXT,
    editorial_id       INTEGER,
    imprescindible     INTEGER NOT NULL DEFAULT 0,

    CONSTRAINT fk_editorial FOREIGN KEY (editorial_id) REFERENCES editoriales(id),
    CONSTRAINT ck_id_positivo   CHECK (id > 0),
    CONSTRAINT ck_titulo_vacio  CHECK (TRIM(titulo) <> ''),
    CONSTRAINT ck_anio          CHECK (anio BETWEEN 1900 AND 2026),
    CONSTRAINT ck_clasificacion CHECK (clasificacion IN ('ATP','+13','+16','+18')),
    CONSTRAINT ck_estado        CHECK (estado_publicacion IN ('En publicacion','Publicacion Terminada')),
    CONSTRAINT ck_tipo          CHECK (tipo IN ('Comic','Manga')),
    CONSTRAINT ck_destacado     CHECK (destacado IN (0,1)),
    CONSTRAINT ck_imprescindible CHECK (imprescindible IN (0,1))
);
\"\"\")

editoriales.to_sql('editoriales', conn, if_exists='append', index=False)
df_limpio.to_sql('comics', conn, if_exists='append', index=False)

filas = conn.execute('SELECT COUNT(*) FROM comics').fetchone()[0]
fk_on = conn.execute('PRAGMA foreign_keys').fetchone()[0]

print('Filas almacenadas          :', filas)
print('Integridad referencial ON  :', fk_on)
""")

md("""
`Integridad referencial ON: 1` significa **activada** (es un booleano de SQLite),
no que exista una sola clave foránea.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 07 · Intentar romper la base

La pregunta no es si el código *funciona*, sino si el sistema
**rechaza lo que nunca debió aceptar**. Probamos las 4 restricciones una por una.
""")

code("""
intentos = [
    ('PK negativa',
     \"(-99,'Prueba Negativa','+18',2020,'Comic',0,'En publicacion',1,0)\"),
    ('Anio futuro',
     \"(101,'Prueba Futuro','+18',2099,'Comic',0,'En publicacion',1,0)\"),
    ('Clasificacion invalida',
     \"(102,'Prueba Clasif','18+',2020,'Comic',0,'En publicacion',1,0)\"),
    ('Editorial inexistente',
     \"(103,'Prueba FK','+18',2020,'Comic',0,'En publicacion',999,0)\"),
    ('Titulo duplicado',
     \"(104,'Berserk','+18',2020,'Comic',0,'En publicacion',1,0)\"),
]

COLS = ('id,titulo,clasificacion,anio,tipo,destacado,'
        'estado_publicacion,editorial_id,imprescindible')

for nombre, valores in intentos:
    try:
        conn.execute(f'INSERT INTO comics ({COLS}) VALUES {valores}')
        conn.commit()
        print(f'[FALLO] {nombre}: el dato invalido ENTRO. Algo esta mal.')
    except sqlite3.IntegrityError as e:
        print(f'[OK] {nombre} rechazado -> {e}')
""")

md("""
**Los errores aquí son el resultado deseado.** Cada `IntegrityError` demuestra que
la base está protegiendo una regla del negocio *sin depender de este notebook*.

Esto es la diferencia entre **limpieza** (arreglar lo que ya entró) y
**prevención** (impedir que entre).
""")

# ---------------------------------------------------------------------
md("""
## CELDA 08 · Transacción: todo o nada

En Comic House, publicar un capítulo son **dos cambios coordinados**:
insertar el capítulo y actualizar el contador de la obra.

Si una parte falla, **ninguna debe quedar aplicada**.
""")

code("""
conn.executescript(\"\"\"
DROP TABLE IF EXISTS capitulos_demo;

CREATE TABLE capitulos_demo (
    id       INTEGER PRIMARY KEY,
    comic_id INTEGER NOT NULL,
    titulo   TEXT NOT NULL,
    numero   INTEGER NOT NULL,
    CONSTRAINT fk_comic  FOREIGN KEY (comic_id) REFERENCES comics(id),
    CONSTRAINT ck_numero CHECK (numero > 0)
);
\"\"\")

conn.commit()
print('Capitulos antes:', conn.execute('SELECT COUNT(*) FROM capitulos_demo').fetchone()[0])

try:
    conn.execute('BEGIN')
    # 1) capitulo valido
    conn.execute(\"INSERT INTO capitulos_demo VALUES (1, 1, 'Arco del Espadachin Negro', 1)\")
    # 2) capitulo con numero invalido -> debe abortar TODA la operacion
    conn.execute(\"INSERT INTO capitulos_demo VALUES (2, 1, 'Capitulo Roto', -5)\")
    conn.commit()
    print('Se confirmo la transaccion (no deberia)')
except sqlite3.IntegrityError as e:
    conn.rollback()
    print('Fallo una operacion y se ejecuto ROLLBACK:', e)

print('Capitulos despues:', conn.execute('SELECT COUNT(*) FROM capitulos_demo').fetchone()[0])
""")

md("""
**Evidencia correcta: `Capitulos despues: 0`.**

El primer `INSERT` era válido, pero el `ROLLBACK` lo deshizo junto con el segundo.
La operación es **atómica**: o se aplica completa, o no se aplica nada.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 09 · Cambio de escala y plan de consulta

Con 4 cómics **todo parece rápido**. El rendimiento aparece como problema cuando
la búsqueda deja de ser trivial.

La tabla `paginas` del proyecto real tiene **884 filas** y es la que más crece:
cada capítulo suma entre 30 y 240 páginas. La consulta más frecuente del lector es
*"dame las páginas del capítulo N en orden"*.

La escalamos a **250.000 páginas** para observar qué hace el motor.
""")

code("""
import numpy as np

N = 250_000
rng = np.random.default_rng(7)

paginas_grande = pd.DataFrame({
    'id': np.arange(1, N + 1),
    'capitulo_id': rng.integers(1, 5001, size=N),
    'imagen_url': ['paginas/p' + str(i) + '.jpg' for i in range(1, N + 1)],
    'orden': rng.integers(1, 240, size=N),
})

paginas_grande.to_sql('paginas_grande', conn, if_exists='replace', index=False)

CONSULTA = 'SELECT * FROM paginas_grande WHERE capitulo_id = 3512 ORDER BY orden'

plan_antes = conn.execute('EXPLAIN QUERY PLAN ' + CONSULTA).fetchall()

print('Filas:', conn.execute('SELECT COUNT(*) FROM paginas_grande').fetchone()[0])
print()
print('Plan ANTES del indice:')
for fila in plan_antes:
    print(' ', fila)
""")

md("""
**Resultado esperado:** aparece la palabra **`SCAN`**.

El motor recorre las 250.000 filas una por una porque no tiene ninguna
estructura que le permita saltar directo al capítulo buscado.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 10 · Crear el índice y volver a mirar

No declaramos "optimizado" porque sí: comparamos el **plan antes y después**.

El índice es sobre `(capitulo_id, orden)` — las dos columnas que usa la consulta:
una para filtrar y otra para ordenar.
""")

code("""
conn.execute('CREATE INDEX idx_paginas_capitulo ON paginas_grande(capitulo_id, orden)')

plan_despues = conn.execute('EXPLAIN QUERY PLAN ' + CONSULTA).fetchall()

print('Plan DESPUES del indice:')
for fila in plan_despues:
    print(' ', fila)

print()
print('ANTES  :', plan_antes[0][-1])
print('DESPUES:', plan_despues[0][-1])
""")

md("""
**Resultado esperado:** `SEARCH ... USING INDEX idx_paginas_capitulo (capitulo_id=?)`.

Dos cambios, no uno:

1. `SCAN` → `SEARCH`: el motor ya no recorre toda la tabla.
2. Desaparece el paso de ordenamiento (`USE TEMP B-TREE FOR ORDER BY`), porque el
   índice ya entrega las filas ordenadas por `orden`.

**Justificación (punto 07):** este índice responde a una consulta real y frecuente
del lector, no es decoración. Un índice sin una consulta que lo necesite solo
ocupa espacio y ralentiza las escrituras.
""")

# ---------------------------------------------------------------------
md("""
## CELDA 11 · Consultas que responden preguntas del proyecto

Tres preguntas reales de Comic House — no `SELECT *` por cumplir.
""")

code("""
consultas = {
    '1. Obras por editorial y clasificacion':
        \"\"\"
        SELECT e.nombre AS editorial,
               COUNT(*) AS obras,
               GROUP_CONCAT(c.clasificacion, ', ') AS clasificaciones
        FROM comics c
        JOIN editoriales e ON e.id = c.editorial_id
        GROUP BY e.nombre
        ORDER BY obras DESC;
        \"\"\",

    '2. Estado de publicacion del catalogo':
        \"\"\"
        SELECT estado_publicacion,
               COUNT(*) AS obras,
               ROUND(AVG(anio), 0) AS anio_promedio
        FROM comics
        GROUP BY estado_publicacion;
        \"\"\",

    '3. Obras destacadas aptas para menores de 18':
        \"\"\"
        SELECT titulo, clasificacion, anio, tipo
        FROM comics
        WHERE destacado = 1
          AND clasificacion <> '+18'
        ORDER BY anio DESC;
        \"\"\",
}

for titulo, sql in consultas.items():
    print('\\n' + '=' * 60)
    print(titulo)
    print('=' * 60)
    display(pd.read_sql_query(sql, conn))
""")

md("""
Cada consulta responde una pregunta declarada al inicio del notebook:

1. **Catálogo por editorial** — alimenta la página de editoriales de la app.
2. **Estado de publicación** — distingue obras en curso de las terminadas.
3. **Destacados con filtro de edad** — es la consulta del carrusel de la portada,
   respetando el control parental.
""")

# ---------------------------------------------------------------------
md("""
## Cierre · Decisiones, límites y mejoras

**Decisiones tomadas**

- Se normalizó **solo** lo inequívoco (espacios y mayúsculas). `En publicacionn`
  *no* se corrigió a mano: se rechazó, porque adivinar la intención del dato
  es inventar información.
- Los registros inválidos se **guardan** en `comics_rechazados.csv`, no se borran.
- `PRIMARY KEY` no basta para impedir un `id` negativo: hizo falta un `CHECK`
  explícito.

**Límites reconocidos**

- El catálogo real es pequeño (5 obras), por eso la evidencia de rendimiento se
  hace sobre `paginas`, que sí tiene volumen real (884 filas) y crecimiento
  sostenido.
- El índice se demostró sobre datos sintéticos (250.000 filas) porque el volumen
  actual no permite observar diferencia de plan.

**Qué mejoraría después de la retroalimentación**

- Llevar estas restricciones al esquema MariaDB del proyecto (hoy varias reglas
  se validan solo en PHP, del lado de la aplicación).
- Normalizar `genero` a su propia tabla con clave foránea, en vez de texto libre.
- Añadir `UNIQUE (capitulo_id, orden)` en `paginas` para impedir dos páginas con
  el mismo número dentro de un capítulo.
""")

# =====================================================================
nb = {
    'cells': celdas,
    'metadata': {
        'kernelspec': {'display_name': 'Python 3', 'language': 'python',
                       'name': 'python3'},
        'language_info': {'name': 'python', 'version': '3.10'},
    },
    'nbformat': 4,
    'nbformat_minor': 5,
}

with open(SALIDA, 'w', encoding='utf-8') as f:
    json.dump(nb, f, ensure_ascii=False, indent=1)

n_md = sum(1 for c in celdas if c['cell_type'] == 'markdown')
n_code = sum(1 for c in celdas if c['cell_type'] == 'code')
print('Notebook generado:', os.path.basename(SALIDA))
print('  celdas markdown:', n_md)
print('  celdas codigo  :', n_code)
