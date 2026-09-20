# -*- coding: utf-8 -*-
"""
build_informe.py - Genera el informe PDF del entregable E1.

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
                                PageTemplate, Paragraph, Spacer, Table,
                                TableStyle)

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SALIDA = os.path.join(BASE, 'Informe_E1_Comic_House.pdf')

# ------------------------------------------------------------------ paleta
TINTA = colors.HexColor('#1a1a2e')
ACENTO = colors.HexColor('#c8102e')      # rojo comic
GRIS = colors.HexColor('#5a5a68')
GRIS_CLARO = colors.HexColor('#e8e8ee')
VERDE = colors.HexColor('#1b7f4f')
FONDO_COD = colors.HexColor('#f4f4f8')

# ------------------------------------------------------------------ estilos
hojas = getSampleStyleSheet()


def estilo(nombre, **kw):
    base = kw.pop('parent', hojas['Normal'])
    return ParagraphStyle(nombre, parent=base, **kw)


E_TITULO = estilo('titulo', fontName='Helvetica-Bold', fontSize=26, leading=30,
                  textColor=TINTA, spaceAfter=4)
E_SUBTIT = estilo('subtit', fontName='Helvetica', fontSize=13, leading=17,
                  textColor=GRIS, spaceAfter=2)
E_META = estilo('meta', fontName='Helvetica', fontSize=9.5, leading=13,
                textColor=GRIS)
E_H1 = estilo('h1', fontName='Helvetica-Bold', fontSize=15, leading=19,
              textColor=TINTA, spaceBefore=16, spaceAfter=7)
E_H2 = estilo('h2', fontName='Helvetica-Bold', fontSize=11.5, leading=15,
              textColor=ACENTO, spaceBefore=11, spaceAfter=5)
E_CUERPO = estilo('cuerpo', fontName='Helvetica', fontSize=9.8, leading=14.5,
                  textColor=TINTA, alignment=TA_JUSTIFY, spaceAfter=7)
E_LISTA = estilo('lista', parent=E_CUERPO, leftIndent=13, bulletIndent=3,
                 spaceAfter=3.5)
E_COD = estilo('cod', fontName='Courier', fontSize=8.3, leading=11.8,
               textColor=TINTA, backColor=FONDO_COD, borderPadding=7,
               leftIndent=3, rightIndent=3, spaceBefore=4, spaceAfter=8)
E_CELDA = estilo('celda', fontName='Helvetica', fontSize=8.3, leading=11,
                 textColor=TINTA)
E_CELDA_B = estilo('celdab', parent=E_CELDA, fontName='Helvetica-Bold')
E_CELDA_COD = estilo('celdacod', parent=E_CELDA, fontName='Courier',
                     fontSize=7.8)
E_NOTA = estilo('nota', fontName='Helvetica-Oblique', fontSize=8.8, leading=12.5,
                textColor=GRIS, leftIndent=10, spaceAfter=8)
E_PIE = estilo('pie', fontName='Helvetica', fontSize=7.8, textColor=GRIS,
               alignment=TA_CENTER)

hist = []


def p(txt, est=E_CUERPO):
    hist.append(Paragraph(txt, est))


def h1(txt):
    hist.append(Paragraph(txt, E_H1))


def h2(txt):
    hist.append(Paragraph(txt, E_H2))


def vinieta(txt):
    hist.append(Paragraph(txt, E_LISTA, bulletText='•'))


def codigo(txt):
    lineas = txt.strip('\n').split('\n')
    hist.append(Paragraph('<br/>'.join(
        l.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
        .replace(' ', '&nbsp;') for l in lineas), E_COD))


def espacio(h=6):
    hist.append(Spacer(1, h))


def tabla(datos, anchos, cabecera=True, resaltar=None):
    filas = []
    for i, fila in enumerate(datos):
        est = E_CELDA_B if (cabecera and i == 0) else E_CELDA
        filas.append([c if not isinstance(c, str) else Paragraph(c, est)
                      for c in fila])
    t = Table(filas, colWidths=anchos, repeatRows=1 if cabecera else 0)
    cmds = [
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
        ('LINEBELOW', (0, 0), (-1, -2), 0.4, GRIS_CLARO),
    ]
    if cabecera:
        cmds += [('BACKGROUND', (0, 0), (-1, 0), TINTA),
                 ('LINEBELOW', (0, 0), (-1, 0), 0.8, TINTA)]
        for j in range(len(datos[0])):
            filas[0][j] = Paragraph(
                '<font color="#ffffff"><b>%s</b></font>' % datos[0][j], E_CELDA)
        t = Table(filas, colWidths=anchos, repeatRows=1)
    if resaltar:
        for r in resaltar:
            cmds.append(('BACKGROUND', (0, r), (-1, r), colors.HexColor('#fff6f7')))
    t.setStyle(TableStyle(cmds))
    return t


# ==================================================================== PORTADA
hist.append(Paragraph('OPTATIVA II · INGENIERÍA DE SISTEMAS · 2026-II',
                      estilo('kicker', fontName='Helvetica-Bold', fontSize=8.5,
                             textColor=ACENTO, spaceAfter=10)))
p('Informe E1 · Comic House', E_TITULO)
p('Núcleo de datos: modelo, integridad y rendimiento', E_SUBTIT)
espacio(10)

linea = Table([['']], colWidths=[16.4 * cm], rowHeights=[2.2])
linea.setStyle(TableStyle([('BACKGROUND', (0, 0), (-1, -1), ACENTO)]))
hist.append(linea)
espacio(12)

p('<b>Proyecto:</b> Comic House — plataforma web de lectura de cómics y mangas '
  '(PHP 8.2 + Apache + MariaDB 11.4, desplegada en Docker).<br/>'
  '<b>Dataset de trabajo:</b> tabla <font face="Courier">comics</font>, exportada '
  'desde la base de datos real del proyecto.<br/>'
  '<b>Fecha:</b> 6 de septiembre de 2026', E_META)
espacio(14)

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
    ['Rendimiento', '250.000 páginas', 'índice compuesto', 'SCAN → SEARCH'],
], [3.1 * cm, 3.9 * cm, 4.3 * cm, 5.1 * cm])
hist.append(resumen_tbl)

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
    ["<font face='Courier'>' MANGA '</font> → <font face='Courier'>'Manga'</font><br/>"
     'Quitar espacios y unificar mayúsculas es reversible y no inventa nada.',
     "<font face='Courier'>'En publicacionn'</font> → <b>se aparta</b><br/>"
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
  'cierra exactamente: 13 − 1 − 1 − 7 = 4.')

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
vinieta('<font face="Courier">SCAN</font> → <font face="Courier">SEARCH</font>: '
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
  'en este informe provienen de esa ejecución, no de estimaciones.', E_NOTA)


# ============================================================ construcción PDF
def pie(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(GRIS_CLARO)
    canvas.setLineWidth(0.5)
    canvas.line(2.3 * cm, 1.55 * cm, A4[0] - 2.3 * cm, 1.55 * cm)
    canvas.setFont('Helvetica', 7.6)
    canvas.setFillColor(GRIS)
    canvas.drawString(2.3 * cm, 1.1 * cm, 'Informe E1 · Comic House')
    canvas.drawRightString(A4[0] - 2.3 * cm, 1.1 * cm, 'Página %d' % doc.page)
    canvas.restoreState()


doc = BaseDocTemplate(SALIDA, pagesize=A4,
                      leftMargin=2.3 * cm, rightMargin=2.3 * cm,
                      topMargin=2.1 * cm, bottomMargin=2.1 * cm,
                      title='Informe E1 - Comic House',
                      author='Juan Diego')
marco = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='n')
doc.addPageTemplates([PageTemplate(id='base', frames=[marco], onPage=pie)])
doc.build(hist)

print('PDF generado:', os.path.basename(SALIDA))
print('Tamano:', round(os.path.getsize(SALIDA) / 1024, 1), 'KB')
