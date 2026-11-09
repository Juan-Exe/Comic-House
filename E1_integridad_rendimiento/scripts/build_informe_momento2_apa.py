# -*- coding: utf-8 -*-
"""
build_informe_momento2_apa.py - Informe del Momento evaluativo 2 en
formato APA.

Mismo contenido que build_informe_momento2.py; cambia solo la forma:
Times New Roman 12 en todo el documento, interlineado doble, sangria
de primera linea, y tablas con lineas horizontales y sin sombreado,
como pide APA 7. Sin colores, sin cursivas decorativas y sin cambios
de tamano entre secciones.

Es el mismo documento del Momento 1 (sin cambiar sus cifras, que eran
correctas en su fecha) mas una portada institucional y la seccion
nueva 09. Toda la paleta pasa a escala de grises.

Usa reportlab (platypus) para tener control tipografico y saltos de pagina
correctos. No depende de navegador ni de wkhtmltopdf.
"""
import os

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import (BaseDocTemplate, Frame, KeepTogether,
                                PageBreak, PageTemplate, Paragraph, Spacer,
                                Table, TableStyle)

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SALIDA = os.path.join(os.path.dirname(BASE),
                      'Informe_Momento2_Comic_House_APA.pdf')

# ------------------------------------------------------------------ paleta
TINTA = colors.HexColor('#000000')
ACENTO = colors.HexColor('#000000')      # todo en negro
GRIS = colors.HexColor('#000000')
GRIS_CLARO = colors.HexColor('#000000')
VERDE = colors.HexColor('#000000')
FONDO_COD = None

# ------------------------------------------------------------------ estilos
hojas = getSampleStyleSheet()


def estilo(nombre, **kw):
    base = kw.pop('parent', hojas['Normal'])
    return ParagraphStyle(nombre, parent=base, **kw)


# APA 7: Times New Roman 12 en todo el documento, interlineado doble
# (12 pt -> 24 pt de leading). Los niveles de titulo se distinguen por
# posicion y por negrita o cursiva, nunca por tamano ni por color.
FUENTE      = 'Times-Roman'
FUENTE_N    = 'Times-Bold'
FUENTE_C    = 'Times-Italic'
CUERPO_PT   = 12
DOBLE       = 24

# Titulo del documento: centrado y en negrita, del mismo tamano.
E_TITULO = estilo('titulo', fontName=FUENTE_N, fontSize=CUERPO_PT,
                  leading=DOBLE, textColor=TINTA, alignment=TA_CENTER,
                  spaceAfter=0)
E_SUBTIT = estilo('subtit', fontName=FUENTE, fontSize=CUERPO_PT,
                  leading=DOBLE, textColor=TINTA, alignment=TA_CENTER,
                  spaceAfter=0)
E_META = estilo('meta', fontName=FUENTE, fontSize=CUERPO_PT, leading=DOBLE,
                textColor=TINTA, alignment=TA_JUSTIFY)

# Nivel 1: centrado y en negrita. Nivel 2: al margen y en negrita.
E_H1 = estilo('h1', fontName=FUENTE_N, fontSize=CUERPO_PT, leading=DOBLE,
              textColor=TINTA, alignment=TA_CENTER,
              spaceBefore=DOBLE, spaceAfter=0)
E_H2 = estilo('h2', fontName=FUENTE_N, fontSize=CUERPO_PT, leading=DOBLE,
              textColor=TINTA, spaceBefore=DOBLE / 2, spaceAfter=0)

# Cuerpo: sangria de primera linea de media pulgada, sin espacio extra
# entre parrafos (el interlineado doble ya los separa).
E_CUERPO = estilo('cuerpo', fontName=FUENTE, fontSize=CUERPO_PT,
                  leading=DOBLE, textColor=TINTA, alignment=TA_JUSTIFY,
                  firstLineIndent=1.27 * cm, spaceAfter=0)
E_LISTA = estilo('lista', parent=E_CUERPO, firstLineIndent=0,
                 leftIndent=1.27 * cm, bulletIndent=0.6 * cm, spaceAfter=0,
                 bulletFontName=FUENTE, bulletFontSize=CUERPO_PT)

# El codigo se deja en monoespaciada, sin fondo: APA no admite
# sombreados, y la fuente ya lo distingue del cuerpo.
E_COD = estilo('cod', fontName='Courier', fontSize=10, leading=13,
               textColor=TINTA, leftIndent=1.27 * cm, spaceBefore=6,
               spaceAfter=12)

# Las tablas llevan interlineado sencillo, como permite APA.
E_CELDA = estilo('celda', fontName=FUENTE, fontSize=11, leading=14,
                 textColor=TINTA)
E_CELDA_B = estilo('celdab', parent=E_CELDA, fontName=FUENTE_N)
E_CELDA_COD = estilo('celdacod', parent=E_CELDA, fontName='Courier',
                     fontSize=10)

# Nota de tabla o de seccion: cursiva solo donde APA la pide.
E_NOTA = estilo('nota', fontName=FUENTE, fontSize=CUERPO_PT, leading=DOBLE,
                textColor=TINTA, alignment=TA_JUSTIFY,
                firstLineIndent=1.27 * cm, spaceAfter=0)
E_PIE = estilo('pie', fontName=FUENTE, fontSize=CUERPO_PT, textColor=TINTA,
               alignment=TA_CENTER)

hist = []


def p(txt, est=E_CUERPO):
    hist.append(Paragraph(txt, est))


def h1(txt):
    hist.append(Paragraph(txt, E_H1))


def h2(txt):
    hist.append(Paragraph(txt, E_H2))


def vinieta(txt):
    hist.append(Paragraph(txt, E_LISTA, bulletText='—'))


def codigo(txt):
    lineas = txt.strip('\n').split('\n')
    hist.append(Paragraph('<br/>'.join(
        l.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
        .replace(' ', '&nbsp;') for l in lineas), E_COD))


def espacio(h=6):
    hist.append(Spacer(1, h))


def tabla(datos, anchos, cabecera=True, resaltar=None):
    """Tabla en formato APA.

    APA 7 no admite lineas verticales ni sombreados: solo una linea
    sobre la cabecera, otra bajo ella y otra al cerrar la tabla. El
    parametro `resaltar` se ignora a proposito, porque el resaltado por
    color de fila no esta permitido.
    """
    filas = []
    for i, fila in enumerate(datos):
        est = E_CELDA_B if (cabecera and i == 0) else E_CELDA
        filas.append([c if not isinstance(c, str) else Paragraph(c, est)
                      for c in fila])

    t = Table(filas, colWidths=anchos, repeatRows=1 if cabecera else 0)
    cmds = [
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
    ]
    if cabecera:
        cmds += [
            ('LINEABOVE', (0, 0), (-1, 0), 0.9, TINTA),
            ('LINEBELOW', (0, 0), (-1, 0), 0.6, TINTA),
            ('LINEBELOW', (0, -1), (-1, -1), 0.9, TINTA),
        ]
    else:
        cmds += [
            ('LINEABOVE', (0, 0), (-1, 0), 0.9, TINTA),
            ('LINEBELOW', (0, -1), (-1, -1), 0.9, TINTA),
        ]
    t.setStyle(TableStyle(cmds))
    return t


# ============================================================ PORTADA (hoja 1)
# Portada institucional centrada, siguiendo el modelo entregado por el
# docente. Usa la misma familia tipografica que el resto del informe
# (Times New Roman 12) para que el documento sea uniforme.
# Va en una hoja propia y sin pie de pagina.
E_PORT = estilo('port', fontName=FUENTE_N, fontSize=CUERPO_PT, leading=DOBLE,
                textColor=TINTA, alignment=TA_CENTER)
E_PORT_N = estilo('portn', fontName=FUENTE, fontSize=CUERPO_PT, leading=DOBLE,
                  textColor=TINTA, alignment=TA_CENTER)

espacio(58)
p('ACTIVIDAD MOMENTO EVALUATIVO 2', E_PORT)
espacio(34)
p('OPTATIVA II', E_PORT)
espacio(34)
p('Integrantes:', E_PORT)
espacio(10)
p('Juan Diego Arrieta Herrera', E_PORT_N)
espacio(108)
p('Docente:', E_PORT)
espacio(10)
p('Joseph David Gari Bustos', E_PORT_N)
espacio(118)
p('Universidad Cooperativa de Colombia', E_PORT)
espacio(16)
p('Ingeniería de Sistemas', E_PORT)
espacio(16)
p('Monteria 2026', E_PORT)

hist.append(PageBreak())

# ============================================================ ENCABEZADO (hoja 2)
# APA abre la segunda pagina con el titulo del trabajo centrado y en
# negrita, sin linea decorativa ni antetitulo en mayusculas.
p('Informe E1 · Comic House', E_TITULO)
p('Núcleo de datos: modelo, integridad y rendimiento', E_SUBTIT)
espacio(DOBLE / 2)

p('<b>Proyecto:</b> Comic House, plataforma web de lectura de cómics y mangas '
  '(PHP 8.2, Apache y MariaDB 11.4, desplegada en Docker). '
  '<b>Dataset de trabajo:</b> tabla <font face="Courier">comics</font>, '
  'exportada desde la base de datos real del proyecto. '
  '<b>Fecha de entrega:</b> 6 de septiembre de 2026. '
  '<b>Momento evaluativo 2:</b> 24 de octubre de 2026.', E_META)
espacio(DOBLE / 2)

h1('Resumen')
p('Este informe documenta el proceso completo de convertir el catálogo de Comic '
  'House en un núcleo de datos confiable. El trabajo partió de una pregunta que '
  'planteó la clase: <i>si la aplicación hoy no muestra datos duplicados, ¿qué '
  'impide que mañana entren?</i>')
p('Para responderla se degradó deliberadamente un dataset limpio, se diagnosticó '
  'el daño, se limpió aplicando reglas explícitas y finalmente se trasladaron esas '
  'reglas a la base de datos como restricciones verificables. El resultado es que '
  'la validez de los datos ya no depende de que alguien recuerde ejecutar un '
  'script: la garantiza la base misma.')

espacio(4)
resumen_tbl = tabla([
    ['Etapa', 'Entrada', 'Salida', 'Resultado'],
    ['Distorsión', '5 obras limpias', '13 filas', '13 defectos inyectados'],
    ['Diagnóstico', '13 filas', '9 tipos de anomalía', 'sin corregir nada'],
    ['Normalización', '5 formas de <font face="Courier">tipo</font>',
     '2 valores reales', 'solo formato'],
    ['Limpieza', '13 filas', '4 válidas + 7 rechazadas', 'reconciliación exacta'],
    ['Restricciones', '4 filas válidas', '9 constraints', '5 de 5 ataques rechazados'],
    ['Rendimiento', '250.000 páginas', 'índice compuesto', 'SCAN a SEARCH'],
], [3.1 * cm, 3.9 * cm, 4.3 * cm, 5.1 * cm])
hist.append(resumen_tbl)
espacio(6)
p('La sección 9 corresponde al Momento evaluativo 2: documenta las correcciones '
  'aplicadas, el traslado de las reglas a MariaDB y el prototipo funcionando '
  'sobre la base real del proyecto. Las secciones 1 a 11 conservan las cifras de '
  'la entrega original, que eran correctas en su fecha.', E_NOTA)

# ==================================================================== 1
h1('1 · Problema y datos')
p('Comic House necesita persistir un catálogo de obras con sus editoriales, '
  'capítulos y páginas. La base real contiene seis tablas; este entregable se '
  'concentra en <font face="Courier">comics</font>, que es la entidad central: '
  'de ella cuelgan los capítulos, y de estos las páginas.')

h2('Preguntas que el sistema debe responder')
vinieta('¿Cuántas obras hay por editorial y con qué clasificaciones por edad?')
vinieta('¿Qué obras siguen en publicación y cuáles ya terminaron?')
vinieta('¿Cuántas páginas tiene cada capítulo y cuáles son las obras más extensas?')

p('Estas preguntas no son decorativas: la primera alimenta la página de '
  'editoriales, la segunda distingue el catálogo activo del archivado, y la '
  'tercera sostiene el lector de páginas, que es la funcionalidad más usada de '
  'la aplicación.')

h2('Volumen real del proyecto')
hist.append(tabla([
    ['Tabla', 'Filas', 'Papel en el modelo'],
    ['comics', '5', 'entidad central del catálogo'],
    ['capitulos', '8', 'depende de comics'],
    ['paginas', '884', 'depende de capitulos — la que más crece'],
    ['editoriales', '5', 'catálogo de referencia (FK)'],
], [3.6 * cm, 2.2 * cm, 10.6 * cm]))
espacio(4)
p('Esta asimetría es importante y condicionó una decisión posterior: con 5 obras '
  'no se puede demostrar nada sobre rendimiento, pero con 884 páginas —y su '
  'crecimiento sostenido— sí.', E_NOTA)

# ==================================================================== 2
h1('2 · Distorsión deliberada del dataset')
p('Antes de limpiar hacía falta algo que limpiar. Se tomó el export real de '
  '<font face="Courier">comics.csv</font> (5 obras) y se le inyectaron 13 '
  'defectos de forma <b>determinista</b>: el script no usa azar, de modo que el '
  'dataset sucio es idéntico en cada ejecución y los resultados son reproducibles.')
p('Cada defecto quedó registrado en <font face="Courier">defectos_inyectados.csv</font>, '
  'lo que permite verificar después si la limpieza los atrapó todos. El catálogo '
  'de defectos replica el del laboratorio de clase, trasladado al dominio de '
  'cómics.')

h2('Los 13 defectos inyectados')
hist.append(tabla([
    ['id', 'Columna', 'Valor sucio', 'Tipo de problema'],
    ['1', 'tipo', "' MANGA '", 'formato: mayúsculas y espacios'],
    ['2', 'tipo', "'comic'", 'formato: minúsculas'],
    ['4', 'tipo', "'COMIC '", 'formato: mayúsculas y espacios'],
    ['3', 'genero', "'  Superheroes  '", 'formato: espacios'],
    ['9', 'estado_publicacion', "'En publicacionn'", 'typo en dominio cerrado'],
    ['10', 'clasificacion', "'18+'", 'valor fuera del dominio'],
    ['2', 'anio', "2099", 'año futuro'],
    ['11', 'editorial_id', "999", 'clave foránea inexistente'],
    ['6', 'titulo', "(vacío)", 'obligatorio ausente'],
    ['7', 'destacado', "5", 'booleano fuera de rango'],
    ['3', 'id', "3 repetido", 'clave primaria duplicada'],
    ['8', 'titulo', "'berserk'", 'duplicado lógico (mayúsculas)'],
    ['-4', 'id', "-4", 'clave primaria negativa'],
], [1.2 * cm, 3.5 * cm, 4.0 * cm, 7.7 * cm]))

espacio(6)
h2('Una decisión de diseño que hubo que corregir')
p('El primer intento inyectó los defectos sobre las 5 obras originales. El '
  'resultado fue que <b>solo sobrevivía una obra</b> a la limpieza, y las '
  'consultas finales devolvían tablas vacías: evidencia inservible.')
p('Se rebalanceó el ensuciado moviendo los defectos de dominio a filas '
  '<i>añadidas</i> (Invincible, Hellsing, Blame!, Sandman), dejando intactas las '
  'obras reales. Solo <i>Saga</i> se dañó a propósito, para mostrar que el '
  'catálogo existente también puede contener datos inválidos, no solo las cargas '
  'nuevas.')
p('El caso de <b>Sandman</b> se diseñó con especial cuidado: todos sus campos son '
  'correctos salvo el <font face="Courier">id = -4</font>. Al aislar una sola '
  'regla, permite demostrar que <font face="Courier">PRIMARY KEY</font> por sí '
  'sola no impide un identificador negativo.', E_NOTA)

# ==================================================================== 3
h1('3 · Diagnóstico: observar sin corregir')
p('La regla de trabajo fue explícita: <b>no corregir datos silenciosamente</b>. '
  'Primero identificar el problema, después decidir qué regla del sistema debería '
  'impedirlo. El diagnóstico solo cuenta.')

codigo("""Filas totales                : 13
PK duplicadas (id)           : 1
PK no positivas (id <= 0)    : 1
Titulos vacios o nulos       : 1
Titulos duplicados (logico)  : 2
Anios futuros (> 2026)       : 1
Clasificacion fuera dominio  : 1
Estado fuera de dominio      : 1
Editorial inexistente (FK)   : 1
Destacado no booleano        : 1""")

p('Un detalle que merece atención: los <b>títulos duplicados son 2</b>, no 1. '
  'Uno es <font face="Courier">berserk</font>, el duplicado lógico inyectado a '
  'propósito. El otro es <i>Absolute Batman</i>, que aparece repetido como efecto '
  'secundario de la fila con clave primaria duplicada.')
p('Esto no es un error de conteo: demuestra que <b>un duplicado de clave primaria '
  'arrastra también una violación de la clave natural</b>. Un solo defecto se '
  'propaga a varias dimensiones de la calidad del dato.')

# ==================================================================== 4
h1('4 · Limpieza: normalizar no es lo mismo que rechazar')
p('Esta es la distinción conceptual central de todo el trabajo. Ante un dato '
  'defectuoso hay dos respuestas posibles, y elegir mal tiene consecuencias '
  'opuestas.')

espacio(2)
comp = tabla([
    ['NORMALIZAR', 'RECHAZAR'],
    ['Cuando la corrección es <b>inequívoca</b> y no añade información nueva.',
     'Cuando el valor correcto <b>no puede deducirse</b> con la evidencia disponible.'],
    ["<font face='Courier'>' MANGA '</font> a <font face='Courier'>'Manga'</font><br/>"
     'Quitar espacios y unificar mayúsculas es reversible y no inventa nada.',
     "<font face='Courier'>'En publicacionn'</font> a <b>se aparta</b><br/>"
     'Parece un typo evidente, pero corregirlo sería suponer la intención.'],
], [8.2 * cm, 8.2 * cm])
hist.append(comp)
espacio(8)

p('La tentación de «arreglar» <font face="Courier">En publicacionn</font> es '
  'fuerte, porque a simple vista es obvio. Pero si el sistema empieza a adivinar '
  'intenciones, deja de ser auditable: nadie puede distinguir después qué dato '
  'vino del origen y cuál lo inventó el pipeline. Por eso ese registro se rechaza.')

h2('Resultado de la normalización')
codigo("""ANTES   -> [' MANGA ', 'COMIC ', 'Comic', 'Manga', 'comic']
DESPUES -> ['Comic', 'Manga']""")
p('Cinco formas textuales que en realidad eran dos valores reales.')

h2('Reconciliación de la limpieza')
codigo("""RECONCILIACION
  Filas iniciales          : 13
  - PK duplicada apartada  :  1
  - Titulo duplicado       :  1
  - Invalidos apartados    :  7
  = Filas validas          :  4""")
p('Los duplicados se apartan en un <b>paso distinto</b> al de las reglas de '
  'dominio, por eso no forman parte del conteo de los 7 inválidos. La aritmética '
  'cierra exactamente: 13 - 1 - 1 - 7 = 4.')

espacio(4)
hist.append(KeepTogether([
    Paragraph('Los 7 registros rechazados y su motivo', E_H2),
    tabla([
        ['id', 'Obra', 'Regla violada'],
        ['2', 'Saga', 'año futuro (2099)'],
        ['6', '(sin título)', 'título obligatorio — NOT NULL'],
        ['7', 'Hellboy', 'destacado = 5 no es booleano'],
        ['9', 'Invincible', 'estado «En publicacionn» fuera del dominio'],
        ['10', 'Hellsing', 'clasificación «18+» fuera del dominio'],
        ['11', 'Blame!', 'editorial 999 no existe'],
        ['-4', 'Sandman', 'identificador negativo'],
    ], [1.4 * cm, 4.0 * cm, 11.0 * cm]),
]))

espacio(6)
p('Los rechazados <b>no se borran</b>: se guardan en '
  '<font face="Courier">comics_rechazados.csv</font>. Un pipeline que descarta '
  'errores en silencio no es auditable; si nadie puede revisar qué se descartó y '
  'por qué, la limpieza se vuelve un acto de fe.')

# ==================================================================== 5
h1('5 · Un error silencioso que apareció al ejecutar')
p('Al correr el notebook completo el resultado fue 5 filas válidas en lugar de 4. '
  'La fila sin título se había colado hasta la base de datos <b>sin que nada '
  'fallara</b>.')

h2('Causa')
p('Al normalizar el texto con <font face="Courier">.astype(str)</font>, un valor '
  'nulo de pandas no se convierte en cadena vacía sino en el <b>texto literal</b> '
  "<font face='Courier'>'nan'</font>. Y <font face='Courier'>'nan'</font> tiene "
  'tres caracteres, así que la validación de obligatoriedad lo daba por bueno:')

codigo("""# La regla parecia correcta...
df['titulo'].str.strip() != ''      # 'nan' != ''  ->  True  (pasa!)

# Correccion: devolver el texto 'nan' a nulo real antes de validar
df['titulo'] = df['titulo'].replace(['nan', 'None', ''], pd.NA)
df['titulo'].notna()                # ahora si detecta el vacio""")

p('Este caso ilustra por qué la validación en el código de la aplicación no basta. '
  'El error no lanzó ninguna excepción: simplemente dejó pasar una obra sin título '
  'que después habría llegado a la interfaz. La restricción '
  '<font face="Courier">NOT NULL</font> de la base lo habría impedido sin depender '
  'de la corrección del pipeline.')

# ==================================================================== 6
h1('6 · De reglas del negocio a restricciones de la base')
p('Hasta este punto la validez dependía de que alguien ejecutara el notebook. El '
  'paso decisivo fue trasladar cada regla a una garantía técnica que la base hace '
  'cumplir siempre, sin intervención humana.')

hist.append(tabla([
    ['Regla del negocio', 'Restricción SQL', 'Por qué'],
    ['El id no se repite', 'PRIMARY KEY', 'identifica unívocamente la obra'],
    ['El id es positivo', 'CHECK (id &gt; 0)',
     'en MariaDB es INT UNSIGNED AUTO_INCREMENT'],
    ['Dos obras no comparten título', 'UNIQUE (titulo)',
     'mismo nombre = registro duplicado'],
    ['El título es obligatorio', 'NOT NULL',
     'sin título no se puede mostrar ni buscar'],
    ['La editorial debe existir', 'FOREIGN KEY',
     'integridad referencial con editoriales'],
    ['El año no puede ser futuro', 'CHECK (anio BETWEEN 1900 AND 2026)',
     'un cómic no se publica en el futuro'],
    ['Clasificación de un conjunto fijo', "CHECK ... IN ('ATP','+13','+16','+18')",
     'control parental del lector'],
    ['Estado de un conjunto fijo', 'CHECK ... IN (...)', 'dominio cerrado de la app'],
    ['Destacado es booleano', 'CHECK IN (0,1)', 'controla el carrusel de portada'],
], [4.5 * cm, 5.6 * cm, 6.3 * cm]))

espacio(6)
p('<b>Hallazgo importante:</b> <font face="Courier">PRIMARY KEY</font> no rechaza '
  'un identificador negativo. SQLite acepta enteros negativos como clave primaria '
  'sin protestar. Para impedir el <font face="Courier">-4</font> hizo falta un '
  '<font face="Courier">CHECK</font> explícito. La restricción obvia no siempre '
  'cubre lo que uno supone.', E_NOTA)

h1('7 · Prueba de rechazo: intentar romper la base')
p('La pregunta no es si el código funciona, sino si el sistema <b>rechaza lo que '
  'nunca debió aceptar</b>. Se lanzaron cinco inserciones inválidas, una por cada '
  'tipo de restricción:')

codigo("""[OK] PK negativa            rechazado -> CHECK constraint failed: ck_id_positivo
[OK] Anio futuro           rechazado -> CHECK constraint failed: ck_anio
[OK] Clasificacion invalida rechazado -> CHECK constraint failed: ck_clasificacion
[OK] Editorial inexistente rechazado -> FOREIGN KEY constraint failed
[OK] Titulo duplicado      rechazado -> UNIQUE constraint failed: comics.titulo""")

p('<b>Cinco de cinco rechazados.</b> Aquí los errores son el resultado deseado: '
  'cada <font face="Courier">IntegrityError</font> demuestra que la base protege '
  'una regla del negocio por su cuenta. Esta es la diferencia entre <i>limpieza</i> '
  '(arreglar lo que ya entró) y <i>prevención</i> (impedir que entre).')

h1('8 · Atomicidad: todo o nada')
p('En Comic House, publicar un capítulo son dos cambios coordinados: insertar el '
  'capítulo y actualizar el contador de la obra. Si una parte falla, ninguna debe '
  'quedar aplicada.')
p('Se simuló una transacción con un primer INSERT válido seguido de uno inválido '
  '(número de capítulo negativo):')

codigo("""Capitulos antes: 0
Fallo una operacion y se ejecuto ROLLBACK: CHECK constraint failed: ck_numero
Capitulos despues: 0""")

p('El primer INSERT era perfectamente válido, pero el <font face="Courier">ROLLBACK'
  '</font> lo deshizo junto con el segundo. Sin transacción, la base habría quedado '
  'con un capítulo registrado y un contador sin actualizar: un estado incoherente '
  'que ninguna restricción individual detectaría.')

# ==================================================================== 9
h1('9 · Rendimiento: evidencia, no intuición')
p('Con 4 obras en el catálogo todo parece instantáneo. El rendimiento solo aparece '
  'como problema cuando la búsqueda deja de ser trivial, así que medirlo sobre '
  '<font face="Courier">comics</font> no habría demostrado nada.')

h2('Por qué el índice va sobre paginas y no sobre comics')
p('La tabla <font face="Courier">paginas</font> tiene 884 filas reales y es la que '
  'más crece: cada capítulo suma entre 30 y 240 páginas. Además concentra la '
  'consulta más frecuente del lector: <i>«dame las páginas del capítulo N en '
  'orden»</i>. Se escaló a 250.000 filas para observar el comportamiento del motor.')

h2('Plan de ejecución antes del índice')
codigo("""(3,  0, 0, 'SCAN paginas_grande')
(14, 0, 0, 'USE TEMP B-TREE FOR ORDER BY')""")
p('<font face="Courier">SCAN</font>: el motor recorre las 250.000 filas una por '
  'una. Y además construye un árbol temporal solo para ordenar el resultado.')

h2('Plan después del índice compuesto')
codigo("""CREATE INDEX idx_paginas_capitulo ON paginas_grande(capitulo_id, orden);

(4, 0, 0, 'SEARCH paginas_grande USING INDEX
           idx_paginas_capitulo (capitulo_id=?)')""")

p('Cambiaron <b>dos cosas</b>, no una:')
vinieta('<font face="Courier">SCAN</font> a <font face="Courier">SEARCH</font>: '
        'el motor ya no recorre toda la tabla, salta directo a las filas buscadas.')
vinieta('Desapareció <font face="Courier">USE TEMP B-TREE FOR ORDER BY</font>: '
        'como el índice es compuesto e incluye <font face="Courier">orden</font>, '
        'las filas ya salen ordenadas y el ordenamiento extra sobra.')

p('El índice es <b>compuesto por diseño</b>: <font face="Courier">capitulo_id</font> '
  'sirve para filtrar y <font face="Courier">orden</font> para ordenar. Un índice '
  'solo sobre <font face="Courier">capitulo_id</font> habría eliminado el SCAN pero '
  'no el ordenamiento temporal.', E_NOTA)

p('Esto responde al criterio del enunciado: un índice sin una consulta que lo '
  'necesite y sin evidencia antes/después es decoración técnica. Aquí hay una '
  'consulta real, un plan previo, un cambio y un plan posterior.')

# ==================================================================== 10
h1('10 · Consultas que responden preguntas reales')
p('Las tres consultas corresponden a las preguntas declaradas al inicio, no son '
  '<font face="Courier">SELECT *</font> por cumplir.')

hist.append(tabla([
    ['Pregunta del proyecto', 'Resultado obtenido'],
    ['Obras por editorial y clasificación',
     'Young Animal 1 (+18) · Norma Editorial 1 (+18) · Dark Horse 1 (+18) · DC 1 (+13)'],
    ['Estado del catálogo',
     'En publicación: 3 obras (año prom. 2011) · Terminadas: 1 (2022)'],
    ['Destacados aptos para menores de 18',
     'Absolute Batman (+13, 2025) — alimenta el carrusel con control parental'],
], [5.6 * cm, 10.8 * cm]))

espacio(6)
p('La tercera consulta es la más relevante para el producto: combina el filtro de '
  'destacados con el de clasificación por edad, que es exactamente lo que necesita '
  'la portada para respetar el control parental.')

# ==================================================================== 11
h1('11 · Conclusiones y trabajo futuro')

h2('Lo aprendido')
vinieta('<b>Una API puede responder 200 OK y devolver datos incorrectos.</b> El '
        'código HTTP habla del transporte, no de la calidad del dato.')
vinieta('<b>Normalizar y rechazar son decisiones distintas.</b> Corregir formato es '
        'seguro; adivinar el valor correcto de un dato ambiguo destruye la '
        'trazabilidad.')
vinieta('<b>Validar en la aplicación no basta.</b> El bug del texto '
        "<font face='Courier'>'nan'</font> no lanzó ninguna excepción: solo una "
        'restricción en la base lo habría detenido.')
vinieta('<b>La restricción obvia no siempre alcanza.</b> PRIMARY KEY no impidió un '
        'id negativo; hizo falta un CHECK explícito.')
vinieta('<b>Un defecto se propaga.</b> La clave primaria duplicada arrastró consigo '
        'una violación de la clave natural.')
vinieta('<b>Optimizar exige evidencia.</b> El plan de consulta antes/después '
        'convierte una intuición en una demostración.')

h2('Límites reconocidos')
vinieta('El catálogo real es pequeño (5 obras), por eso la evidencia de rendimiento '
        'se construyó sobre <font face="Courier">paginas</font> y con datos '
        'sintéticos escalados a 250.000 filas.')
vinieta('Las restricciones se demostraron en SQLite en memoria. El esquema MariaDB '
        'del proyecto todavía valida varias de estas reglas solo desde PHP.')

h2('Trabajo futuro')
vinieta('Llevar las nueve restricciones al esquema MariaDB de producción, para que '
        'la garantía no dependa de la capa de aplicación.')
vinieta('Normalizar <font face="Courier">genero</font> a su propia tabla con clave '
        'foránea, en lugar de texto libre.')
vinieta('Añadir <font face="Courier">UNIQUE (capitulo_id, orden)</font> en '
        '<font face="Courier">paginas</font>, para impedir dos páginas con el mismo '
        'número dentro de un capítulo.')
vinieta('Registrar los rechazos en una tabla de auditoría en lugar de un CSV, con '
        'fecha y motivo, para poder analizar la calidad del origen a lo largo del '
        'tiempo.')

# ==================================================================== 9
hist.append(PageBreak())
h1('9 · Momento 2: la aplicación conectada a su base')

p('El Momento 1 demostró las reglas de integridad sobre SQLite en memoria y dejó '
  'escrito, como límite reconocido, que el esquema MariaDB del proyecto todavía '
  'validaba varias de ellas solo desde PHP. Esta sección cierra esa brecha: las '
  'reglas ya viven en la base de producción y la aplicación opera contra ella.')

p('Todas las cifras que siguen provienen de ejecuciones reales contra el '
  'contenedor <font face="Courier">comic_house_db</font> el 24 de octubre de '
  '2026, no de estimaciones.')

# -------------------------------------------------------------------- 9.1
h2('9.1 · Correcciones del Momento 1')

p('<b>a) Normalizar el título al insertar, no con TRIM dentro del CHECK.</b>')
p('Un <font face="Courier">CHECK (TRIM(titulo) &lt;&gt; \'\')</font> acepta el dato '
  'sucio y lo guarda con los espacios intactos: la restricción disimula el '
  'problema en vez de impedirlo, y dos títulos que solo difieren en espacios '
  'burlarían el <font face="Courier">UNIQUE</font>. La normalización se movió al '
  'momento de la escritura, en '
  '<font face="Courier">controlador/registro_comcis.php</font>, y el CHECK quedó '
  'reducido a comprobar que el título no esté vacío:')
codigo("""// controlador/registro_comcis.php
$titulo = trim($_POST["titulo"]);   // se normaliza AL ESCRIBIR

-- database/init/01_schema.sql
CONSTRAINT ck_comics_titulo_no_vacio CHECK (CHAR_LENGTH(titulo) > 0)""")

p('<b>b) Incluir la llave foránea hacia editoriales.</b>')
p('La columna <font face="Courier">editorial_id</font> existía en la tabla pero '
  'estaba vacía en las seis filas y sin clave foránea: la relación con la '
  'editorial se sostenía únicamente sobre el texto libre de '
  '<font face="Courier">comics.editorial</font>. Se pobló por nombre —los seis '
  'valores coincidían exactamente con las seis filas de '
  '<font face="Courier">editoriales</font>, así que no hubo pérdida— y después se '
  'exigió la restricción:')
codigo("""UPDATE comics c JOIN editoriales e ON e.nombre = TRIM(c.editorial)
   SET c.editorial_id = e.id WHERE c.editorial_id IS NULL;

ALTER TABLE comics MODIFY editorial_id INT UNSIGNED NOT NULL;
ALTER TABLE comics ADD CONSTRAINT fk_comics_editorial
  FOREIGN KEY (editorial_id) REFERENCES editoriales (id)
  ON DELETE RESTRICT ON UPDATE CASCADE;""")
p('Se eligió <font face="Courier">ON DELETE RESTRICT</font> y no '
  '<font face="Courier">CASCADE</font>: borrar una editorial no debe arrastrar su '
  'catálogo de obras. El sistema obliga a reasignarlas primero.', E_NOTA)

p('<b>c) Guardar los capítulos en la base del proyecto, no en memoria.</b>')
p('La prueba de atomicidad del Momento 1 (sección 8) creaba su tabla de capítulos '
  'en una base SQLite en memoria, de modo que demostraba el concepto pero no el '
  'comportamiento del motor real. Las cuatro operaciones del prototipo y las '
  'pruebas de rechazo de esta sección se ejecutan contra '
  '<font face="Courier">comic_house</font> en MariaDB, sobre las tablas que usa '
  'la aplicación en funcionamiento.')

h2('Restricciones trasladadas a MariaDB')
p('El esquema versionado '
  '(<font face="Courier">database/init/01_schema.sql</font>) estaba además '
  'desactualizado respecto a la base real: le faltaban la tabla '
  '<font face="Courier">editoriales</font> y las columnas '
  '<font face="Courier">destacado</font>, '
  '<font face="Courier">imprescindible</font> y '
  '<font face="Courier">es_doble</font>, que se habían creado a mano. Se '
  'reescribió para que un despliegue desde cero reproduzca la base completa, '
  'con las restricciones incluidas.')

hist.append(tabla([
    ['Restricción', 'Regla del negocio que protege'],
    ['uq_comics_titulo', 'dos obras no comparten título'],
    ['fk_comics_editorial', 'la editorial debe existir en el catálogo'],
    ['ck_comics_titulo_no_vacio', 'sin título no se puede mostrar ni buscar'],
    ['ck_comics_clasificacion', 'control parental del lector (ATP/+13/+16/+18)'],
    ['ck_comics_anio', 'un cómic no se publica en el futuro (1900–2026)'],
    ['ck_comics_tipo', 'dominio cerrado: Comic o Manga'],
    ['ck_comics_destacado', 'alimenta el carrusel de portada (0/1)'],
    ['ck_comics_imprescindible', 'sección «Imprescindibles» (0/1)'],
    ['uq_paginas_cap_orden', 'dos páginas no ocupan la misma posición'],
    ['ck_paginas_orden', 'el orden de página es positivo'],
], [6.0 * cm, 10.4 * cm]))
espacio(5)
p('Diez restricciones activas en la base del proyecto, verificadas con '
  '<font face="Courier">information_schema</font>. En el Momento 1 eran nueve, '
  'pero demostradas en SQLite.', E_NOTA)

# -------------------------------------------------------------------- 9.2
h2('9.2 · Operación conectada: registrar un cómic de una editorial')

p('<b>Qué hace la persona.</b> Entra al panel de administración, abre «Nuevo '
  'cómic» y completa el formulario: título, tipo, género, clasificación, año, '
  'editorial —elegida de la lista de editoriales existentes— y portada.')

p('<b>Qué revisa el programa.</b> Comprueba que ningún campo esté vacío, recorta '
  'los espacios del título, convierte el año a entero y resuelve el nombre de la '
  'editorial a su identificador. Si ese nombre no existe, detiene la operación '
  'antes de tocar <font face="Courier">comics</font>. Después arma una sentencia '
  'preparada: los valores viajan como parámetros, nunca concatenados en el texto '
  'de la consulta.')

p('<b>Qué guarda la base.</b> Una fila en <font face="Courier">comics</font> con '
  '<font face="Courier">editorial_id</font> apuntando a la editorial real. Antes '
  'de aceptarla verifica por su cuenta las diez restricciones de la tabla '
  'anterior.')

p('<b>Qué ve la persona.</b> Si todo es correcto, vuelve al listado con el aviso '
  '«Cómic registrado correctamente» y la obra ya aparece en el catálogo. Si la '
  'base rechaza la fila, el controlador traduce el código de error del motor a un '
  'mensaje concreto:')

hist.append(tabla([
    ['Error del motor', 'Restricción', 'Lo que lee la persona'],
    ['1062', 'uq_comics_titulo', '«Ya existe un cómic con ese título»'],
    ['1452', 'fk_comics_editorial', '«La editorial indicada no existe»'],
    ['4025', 'ck_comics_anio / ck_comics_clasificacion',
     '«Año o clasificación fuera de los valores permitidos»'],
], [2.6 * cm, 6.4 * cm, 7.4 * cm]))
espacio(5)
p('La diferencia importa: un «Error al registrar el cómic» genérico obliga a '
  'adivinar. Nombrar la regla que frenó la operación le dice a la persona qué '
  'tiene que cambiar.', E_NOTA)

# -------------------------------------------------------------------- 9.3
h2('9.3 · Dónde guardar las páginas')

p('Las páginas siguen en una tabla relacional, y la decisión se sostiene en cómo '
  'se las consulta. La pregunta que hace el lector es siempre la misma: «dame las '
  'páginas del capítulo N en orden». Es un acceso por clave con un orden '
  'definido, exactamente lo que un índice compuesto resuelve en un solo salto '
  '—la evidencia está en 9.8—, y además permite que '
  '<font face="Courier">UNIQUE (capitulo_id, orden)</font> impida dos páginas en '
  'la misma posición.')

p('<b>Por qué no la otra opción.</b> La alternativa natural sería guardar las '
  'páginas como un documento o un JSON dentro de la fila del capítulo, en vez de '
  '930 filas sueltas. Se descartó por tres razones concretas:')
vinieta('<b>Se pierde la restricción.</b> Dentro de un JSON, el motor no puede '
        'exigir unicidad de <font face="Courier">orden</font>; volvería a '
        'depender de que el bucle de PHP no tenga errores, que es justo lo que el '
        'Momento 1 demostró insuficiente.')
vinieta('<b>Lectura parcial.</b> El lector carga páginas a demanda. Con filas se '
        'pide un rango; con un documento hay que traer el capítulo entero '
        '—hasta 234 páginas— para mostrar una.')
vinieta('<b>Escritura.</b> Añadir o corregir una página sería reescribir el '
        'documento completo, perdiendo la inserción por lotes que dio el 71× '
        'del Momento 1.')

p('<b>Qué se cede.</b> Una tabla con cientos de miles de filas pesa más en índices '
  'que un documento compacto, y reconstruir el capítulo completo exige un '
  '<font face="Courier">ORDER BY</font> en vez de leer un bloque ya ordenado. Es '
  'un costo aceptable: se paga espacio para ganar garantías que el motor hace '
  'cumplir solo.')

# -------------------------------------------------------------------- 9.4
h2('9.4 · Calidad: qué se revisa y por qué se rechaza')

hist.append(tabla([
    ['Qué se revisa', 'Quién lo frena', 'Causa del rechazo'],
    ['Título vacío', 'ck_comics_titulo_no_vacio',
     'sin título la obra no se puede mostrar ni buscar'],
    ['Título repetido', 'uq_comics_titulo',
     'mismo nombre significa registro duplicado'],
    ['Año futuro', 'ck_comics_anio',
     'un cómic no puede publicarse en el futuro'],
    ['Clasificación no permitida', 'ck_comics_clasificacion',
     'el filtro parental del lector asume un dominio cerrado'],
    ['Editorial inexistente', 'fk_comics_editorial',
     'la página de editoriales quedaría con una obra huérfana'],
], [4.0 * cm, 5.0 * cm, 7.4 * cm]))

espacio(6)
p('Cada rechazo se verificó lanzando la operación inválida contra la base real. '
  'Aquí los errores son el resultado deseado:')
codigo("""[OK]   CHECK ck_comics_clasificacion rechaza '21+'   -> errno 4025
[OK]   FOREIGN KEY fk_comics_editorial rechaza id=999 -> errno 1452
[OK]   UNIQUE uq_comics_titulo rechaza titulo repetido -> errno 1062
[OK]   CHECK ck_comics_anio rechaza anio=2099          -> errno 4025""")

p('El Momento 1 ya había dejado escrito el criterio: normalizar cuando la '
  'corrección es inequívoca, rechazar cuando el valor correcto no se deduce de la '
  'evidencia. Un año 2099 no se «arregla» a 2026, porque eso sería inventar el '
  'dato; se rechaza y se le dice a la persona qué pasó.', E_NOTA)

# -------------------------------------------------------------------- 9.5
h2('9.5 · El proceso de datos, de principio a fin')

p('El recorrido completo —archivo original, limpieza, validación y escritura en '
  'las tablas del proyecto— está documentado en las secciones 2 a 7 de este mismo '
  'informe. Su resultado, ya conocido, es el siguiente:')

hist.append(tabla([
    ['Etapa', 'Registros', 'Detalle'],
    ['Entran', '13', 'dataset sucio con 13 defectos inyectados'],
    ['Se aceptan', '4', 'superaron las nueve reglas'],
    ['Se rechazan', '9', '1 PK duplicada + 1 título duplicado + 7 inválidos'],
], [4.2 * cm, 2.8 * cm, 9.4 * cm]))

espacio(5)
p('<b>Si se corre dos veces, no se duplica nada.</b> Esta es la propiedad que '
  'pedía comprobarse, y se verificó ejecutando tres veces seguidas la batería de '
  'operaciones contra la base real. El guion borra sus propios datos de prueba al '
  'empezar y al terminar, de modo que el estado final es idéntico al inicial:')
codigo("""Ejecucion 1 -> 11 correctas, 0 fallidas | 6 comics, 9 capitulos, 930 paginas
Ejecucion 2 -> 11 correctas, 0 fallidas | 6 comics, 9 capitulos, 930 paginas
Ejecucion 3 -> 11 correctas, 0 fallidas | 6 comics, 9 capitulos, 930 paginas""")

p('Las once comprobaciones cubren las cuatro operaciones del prototipo: registrar '
  'un cómic con su editorial, leer las páginas de un capítulo en orden, '
  'actualizar una clasificación y los cuatro rechazos. El catálogo real queda '
  'intacto en las tres pasadas.')

# -------------------------------------------------------------------- 9.6
h2('9.6 · Volumen: hoy y a futuro')

p('Entre el Momento 1 y hoy el catálogo creció, y conviene señalarlo porque es '
  'crecimiento real de uso, no datos sintéticos:')

hist.append(tabla([
    ['Tabla', 'Momento 1 (6 sep)', 'Hoy (24 oct)', 'Proyección a 1 año'],
    ['comics', '5', '6', '~60'],
    ['capitulos', '8', '9', '~700'],
    ['paginas', '884', '930', '~150.000'],
    ['editoriales', '5', '6', '~15'],
], [3.4 * cm, 4.2 * cm, 3.4 * cm, 5.4 * cm]))

espacio(5)
p('<b>Cómo se estimó.</b> En siete semanas se añadieron un cómic y un capítulo. '
  'A ese ritmo serían unos 60 cómics al año, pero la proyección de capítulos es '
  'deliberadamente más agresiva: una obra en publicación acumula capítulos de '
  'forma continua, y basta con que una decena de obras se mantengan activas para '
  'llegar a varios centenares. Con el promedio actual de 103 páginas por capítulo '
  '—930 entre 9— setecientos capítulos dan del orden de 150.000 páginas.')

p('Esa cifra es la que importa para el diseño, y cae de lleno en el rango donde '
  'la sección 6 de este informe midió el comportamiento: sin índice, leer un '
  'capítulo entre 110.000 filas cuesta 10,1 ms; con índice, 0,32 ms. El índice '
  'deja de ser una previsión y pasa a ser necesario dentro del horizonte de un '
  'año.')

# -------------------------------------------------------------------- 9.7
h2('9.7 · Qué valida la base y qué valida el programa')

p('El reparto no es arbitrario. La base defiende lo que debe cumplirse siempre, '
  'venga el dato de donde venga; el programa se ocupa de lo que depende del '
  'contexto de la interfaz.')

hist.append(tabla([
    ['Lo revisa', 'Reglas', 'Por qué ahí'],
    ['La base',
     'unicidad del título · existencia de la editorial · dominios de '
     'clasificación y tipo · rango del año · orden de página positivo y único',
     'se cumplen aunque el dato entre por otra vía: un script de carga, la '
     'consola de MariaDB o un error de programación'],
    ['El programa',
     'campos obligatorios del formulario · normalización del título · formato y '
     'tamaño de la portada · sesión y rol de administrador · máximo de cuatro '
     'cómics imprescindibles',
     'dependen de la interfaz, necesitan explicar el problema a la persona, o '
     'son reglas de presentación que no describen la validez del dato'],
], [2.6 * cm, 6.6 * cm, 7.2 * cm]))

espacio(6)
p('<b>Qué se gana.</b> La garantía deja de depender de que quien escriba el '
  'próximo controlador recuerde la regla. El Momento 1 mostró el caso extremo: el '
  'texto <font face="Courier">\'nan\'</font> pasó una validación de PHP sin lanzar '
  'ninguna excepción, y solo una restricción en la base lo habría detenido.')

p('<b>Qué se cede.</b> Tres costos reales, asumidos a conciencia:')
vinieta('<b>Rigidez.</b> Añadir mañana la clasificación «+21» ya no es cambiar un '
        '<font face="Courier">if</font>: exige un '
        '<font face="Courier">ALTER TABLE</font> sobre la tabla en producción.')
vinieta('<b>Mensajes peores.</b> El motor devuelve «errno 1452», no una frase '
        'útil. Hay que traducir cada código, y esa traducción es código que '
        'mantener.')
vinieta('<b>Costo de escritura.</b> Cada <font face="Courier">INSERT</font> paga '
        'la verificación del <font face="Courier">UNIQUE</font> y de la clave '
        'foránea. Es irrelevante al volumen actual y seguirá siéndolo: se escribe '
        'un cómic de vez en cuando y se lee constantemente.')

p('El intercambio es favorable porque los datos sobreviven al código. La '
  'aplicación se reescribirá; el catálogo seguirá siendo el mismo.', E_NOTA)

# -------------------------------------------------------------------- 9.8
h2('9.8 · Punto propio: leer las páginas en orden inverso')

p('<b>La pregunta.</b> El lector permite avanzar y retroceder. Si una vista '
  'necesitara las páginas de un capítulo en orden descendente, ¿sirve el índice '
  '<font face="Courier">(capitulo_id, orden)</font> que ya existe, o hace falta '
  'crear uno descendente?')

p('<b>La respuesta es que sirve el mismo</b>, y la evidencia lo muestra en dos '
  'niveles. Primero, el plan de ejecución sobre la tabla real, forzando el índice '
  'para aislar su comportamiento:')
codigo("""ASC  : type=ref  key=idx_paginas_cap_orden  rows=219  Extra=Using where
DESC : type=ref  key=idx_paginas_cap_orden  rows=219  Extra=Using where""")

p('Son idénticos, y en ninguno aparece '
  '<font face="Courier">Using filesort</font>: el motor no ordena aparte en '
  'ningún sentido.')

p('Segundo, la medición sobre 110.000 filas —500 capítulos de 220 páginas—, que '
  'es donde el optimizador elige el índice por su cuenta:')

hist.append(tabla([
    ['Consulta', 'Sin índice', 'Con índice', 'Factor', 'Plan con índice'],
    ['ORDER BY orden', '10,393 ms', '0,422 ms', '24,6×', 'ref · sin filesort'],
    ['ORDER BY orden DESC', '10,431 ms', '0,312 ms', '33,4×', 'ref · sin filesort'],
], [4.0 * cm, 2.6 * cm, 2.6 * cm, 2.0 * cm, 5.2 * cm]))

espacio(5)
p('<b>Por qué.</b> Un índice B-Tree es una lista doblemente enlazada en sus hojas: '
  'el motor puede recorrerla desde el final hacia el principio con el mismo costo '
  'que al derecho. La diferencia entre ambos sentidos fue de 0,110 ms, dentro del '
  'ruido de medición. Un índice descendente sería una copia redundante: ocuparía '
  'espacio y haría más lentas las escrituras sin acelerar ninguna lectura.')

p('<b>Nota sobre el plan en la tabla real.</b> Sin forzar el índice, la consulta '
  'sobre las 930 páginas actuales sigue eligiendo '
  '<font face="Courier">type=ALL</font>, por la misma razón explicada en la '
  'sección 4 de este informe: el capítulo 1 concentra 219 de 930 filas, casi una '
  'cuarta parte de la tabla, y a esa proporción recorrerla entera sale más barato '
  'que saltar del índice a los datos. No es un fallo del índice sino la decisión '
  'correcta a este volumen, y el umbral medido en el Momento 1 —entre 4 y 10 '
  'capítulos— ya quedó atrás con los 9 actuales.', E_NOTA)

# -------------------------------------------------------------------- 9.9
h2('9.9 · Límite conocido y siguiente paso')

p('<b>El límite.</b> La columna <font face="Courier">comics.editorial</font> '
  'sigue existiendo como texto libre al lado de '
  '<font face="Courier">editorial_id</font>. Hoy ambas coinciden, pero nada '
  'obliga a que sigan coincidiendo: un '
  '<font face="Courier">UPDATE</font> podría cambiar el texto sin tocar el '
  'identificador y dejar la fila diciendo dos cosas distintas sobre la misma '
  'editorial. Es un dato duplicado, y un dato duplicado acaba divergiendo.')

p('Se mantuvo a propósito en esta entrega porque varias vistas del catálogo '
  'todavía filtran por ese texto, y cambiarlas excedía el alcance del Momento 2. '
  'La restricción importante —que la editorial exista— ya está garantizada por la '
  'clave foránea.')

p('<b>El siguiente paso, para el Momento 3.</b> Eliminar la columna redundante y '
  'dejar que la editorial se obtenga siempre por '
  '<font face="Courier">JOIN</font>. Esto exige reescribir las consultas de '
  '<font face="Courier">index.php</font>, '
  '<font face="Courier">P-comics/p-comics.php</font> y '
  '<font face="Courier">crud.php</font> para unir contra '
  '<font face="Courier">editoriales</font>, y el índice '
  '<font face="Courier">idx_comics_editorial_id</font> ya está creado para '
  'sostener ese filtro.')

p('La necesidad futura que lo justifica es concreta: cuando una editorial cambie '
  'de nombre —algo que ocurre en el sector—, hoy habría que actualizar una fila '
  'en <font face="Courier">editoriales</font> y además todas las de '
  '<font face="Courier">comics</font> que repitan el texto. Con la columna '
  'eliminada, el cambio es una sola escritura y se propaga solo.')


h1('Anexo · Artefactos entregados')
hist.append(tabla([
    ['Archivo', 'Contenido'],
    ['E1_comic_house_integridad_rendimiento.ipynb',
     'Notebook con 11 celdas de código y sus salidas ejecutadas'],
    ['dataset_sucio/comics_sucio.csv', '13 filas con los defectos inyectados'],
    ['dataset_sucio/defectos_inyectados.csv', 'Bitácora de los 13 defectos'],
    ['dataset_limpio/comics_limpio.csv', '4 obras que superaron todas las reglas'],
    ['dataset_limpio/comics_rechazados.csv', '7 registros apartados, auditables'],
    ['scripts/01_ensuciar.py', 'Generador determinista del dataset sucio'],
], [6.5 * cm, 9.9 * cm]))

espacio(8)
p('El notebook se ejecutó de principio a fin sin errores; todas las cifras citadas '
  'en las secciones 1 a 11 provienen de esa ejecución, no de estimaciones.', E_NOTA)

h2('Añadidos en el Momento 2')
hist.append(tabla([
    ['Archivo', 'Contenido'],
    ['database/init/01_schema.sql',
     'Esquema completo y actualizado: incluye editoriales, las diez '
     'restricciones y los índices'],
    ['benchmark/momento2_integridad.sql',
     'Migración aplicada a la base real: FK a editoriales, UNIQUE de título '
     'y los CHECK de dominio'],
    ['benchmark/momento2_operaciones.php',
     'Las cuatro operaciones del prototipo con consultas parametrizadas '
     '(11 comprobaciones)'],
    ['benchmark/momento2_orden_inverso.php',
     'Medición del punto 9.8: ASC contra DESC, con y sin índice'],
    ['controlador/registro_comcis.php',
     'Registro de cómics con sentencias preparadas y traducción de los '
     'rechazos del motor'],
], [6.5 * cm, 9.9 * cm]))

espacio(8)
p('Para reproducir las comprobaciones del Momento 2, con los contenedores '
  'levantados:', E_CUERPO)
codigo("""docker exec -i comic_house_db mariadb -ucomic_user -pcomic_pass_123 \
  comic_house < E1_integridad_rendimiento/benchmark/momento2_integridad.sql

docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/momento2_operaciones.php

docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/momento2_orden_inverso.php""")


# ============================================================ construcción PDF
def pie(canvas, doc):
    """Numero de pagina arriba a la derecha, como pide APA 7.

    La portada no se numera visiblemente, aunque cuenta como pagina 1.
    """
    if doc.page == 1:
        return
    canvas.saveState()
    canvas.setFont(FUENTE, CUERPO_PT)
    canvas.setFillColor(TINTA)
    canvas.drawRightString(A4[0] - 2.5 * cm, A4[1] - 1.6 * cm, str(doc.page))
    canvas.restoreState()


# APA pide 2,54 cm (una pulgada) en los cuatro margenes.
doc = BaseDocTemplate(SALIDA, pagesize=A4,
                      leftMargin=2.54 * cm, rightMargin=2.54 * cm,
                      topMargin=2.54 * cm, bottomMargin=2.54 * cm,
                      title='Informe Momento 2 - Comic House',
                      author='Juan Diego Arrieta Herrera')
marco = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='n')
doc.addPageTemplates([PageTemplate(id='base', frames=[marco], onPage=pie)])
doc.build(hist)

print('PDF generado:', os.path.basename(SALIDA))
print('Tamano:', round(os.path.getsize(SALIDA) / 1024, 1), 'KB')
