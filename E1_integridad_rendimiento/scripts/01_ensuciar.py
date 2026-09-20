# -*- coding: utf-8 -*-
"""
01_ensuciar.py - Genera el dataset SUCIO a partir de export_csv/comics.csv

Unico dataset intervenido: comics.csv
Replica el catalogo de defectos del laboratorio S04 (pedidos) trasladado al
dominio de Comic House. Cada defecto se inyecta de forma DETERMINISTA (sin
azar: el resultado es identico en cada ejecucion) y queda documentado en
defectos_inyectados.csv para poder verificar despues la limpieza.

Las tablas editoriales / capitulos / paginas NO se modifican: se usan solo
como referencia de integridad (editoriales define que editorial_id existen).
"""
import csv
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ORIGEN = os.path.join(os.path.dirname(BASE), 'export_csv')
DESTINO = os.path.join(BASE, 'dataset_sucio')


def leer(nombre):
    with open(os.path.join(ORIGEN, nombre), encoding='utf-8-sig', newline='') as f:
        return list(csv.DictReader(f))


def escribir(ruta, filas, campos):
    with open(ruta, 'w', encoding='utf-8', newline='') as f:
        w = csv.DictWriter(f, fieldnames=campos)
        w.writeheader()
        w.writerows(filas)


comics = leer('comics.csv')
editoriales = leer('editoriales.csv')  # solo lectura: catalogo de FK validas

campos = list(comics[0].keys())
registro = []  # bitacora de defectos inyectados


def anotar(clave, columna, defecto, valor):
    registro.append({'registro_id': clave, 'columna': columna,
                     'defecto': defecto, 'valor_sucio': valor})


# ---------------------------------------------------------------
# 0. Vincular editorial_id (venia vacio en el export) para que la
#    relacion comics -> editoriales tenga sentido antes de romperla.
# ---------------------------------------------------------------
mapa_edit = {e['nombre'].strip(): e['id'] for e in editoriales}
for c in comics:
    c['editorial_id'] = mapa_edit.get(c['editorial'].strip(), '')

# ---------------------------------------------------------------
# DEFECTOS DE FORMATO (correccion inequivoca -> se normalizan)
# ---------------------------------------------------------------

# (a) Mayusculas y espacios sobrantes en 'tipo'
comics[0]['tipo'] = ' MANGA '
anotar(comics[0]['id'], 'tipo', 'formato_mayusculas_espacios', ' MANGA ')
comics[1]['tipo'] = 'comic'
anotar(comics[1]['id'], 'tipo', 'formato_minusculas', 'comic')
comics[3]['tipo'] = 'COMIC '
anotar(comics[3]['id'], 'tipo', 'formato_mayusculas_espacios', 'COMIC ')

# (b) Espacios sobrantes en genero (ya existia uno real en el export)
comics[2]['genero'] = '  Superheroes  '
anotar(comics[2]['id'], 'genero', 'formato_espacios', '  Superheroes  ')

# ---------------------------------------------------------------
# DEFECTOS DE DOMINIO (no se inventan valores -> se rechazan)
# ---------------------------------------------------------------

# NOTA DE DISENO: los defectos de dominio se inyectan en filas ANADIDAS
# (id 9..13) y no en las 5 obras originales. Asi el dataset limpio conserva
# obras reales suficientes para que las consultas finales devuelvan resultados.
# Solo se altera una obra original (id=2) para mostrar que el problema tambien
# puede venir del catalogo existente, no solo de cargas nuevas.

# (c) Error tipografico en dominio cerrado (como "Entregadoo" del laboratorio)
fila_typo = dict(comics[2])
fila_typo['id'] = '9'
fila_typo['titulo'] = 'Invincible'
fila_typo['anio'] = '2003'
fila_typo['clasificacion'] = '+16'
fila_typo['editorial'] = 'Image Comics'
fila_typo['editorial_id'] = '2'
fila_typo['estado_publicacion'] = 'En publicacionn'
comics.append(fila_typo)
anotar('9', 'estado_publicacion', 'typo_dominio_cerrado', 'En publicacionn')

# (d) Clasificacion fuera del dominio permitido (+13/+16/+18/ATP)
fila_clasif = dict(comics[4])
fila_clasif['id'] = '10'
fila_clasif['titulo'] = 'Hellsing'
fila_clasif['anio'] = '1997'
fila_clasif['tipo'] = 'Manga'
fila_clasif['clasificacion'] = '18+'
fila_clasif['editorial_id'] = '1'
fila_clasif['estado_publicacion'] = 'Publicacion Terminada'
comics.append(fila_clasif)
anotar('10', 'clasificacion', 'dominio_invalido', '18+')

# (e) Anio futuro (regla: no puede superar el anio actual, 2026)
#     Este SI se aplica sobre una obra original, para evidenciar que el
#     catalogo existente tambien puede contener datos invalidos.
comics[1]['anio'] = '2099'
anotar(comics[1]['id'], 'anio', 'anio_futuro', '2099')

# (f) FK rota: editorial inexistente (equivalente a cliente_id=999 del lab)
fila_fk = dict(comics[3])
fila_fk['id'] = '11'
fila_fk['titulo'] = 'Blame!'
fila_fk['anio'] = '1997'
fila_fk['tipo'] = 'Manga'
fila_fk['clasificacion'] = '+16'
fila_fk['estado_publicacion'] = 'Publicacion Terminada'
fila_fk['editorial'] = 'Editorial Fantasma'
fila_fk['editorial_id'] = '999'
comics.append(fila_fk)
anotar('11', 'editorial_id', 'fk_inexistente', '999')

# (g) NOT NULL violado: titulo vacio
fila_sin_titulo = dict(comics[4])
fila_sin_titulo['id'] = '6'
fila_sin_titulo['titulo'] = ''
fila_sin_titulo['clasificacion'] = '+18'   # el unico defecto es el titulo
fila_sin_titulo['portada'] = 'sin_portada.jpg'
comics.append(fila_sin_titulo)
anotar('6', 'titulo', 'not_null_violado', '(vacio)')

# (h) Booleano fuera de rango (destacado debe ser 0/1)
fila_bool = dict(comics[0])
fila_bool['id'] = '7'
fila_bool['titulo'] = 'Hellboy'
fila_bool['destacado'] = '5'
fila_bool['anio'] = '1993'
fila_bool['tipo'] = 'Comic'
fila_bool['clasificacion'] = '+16'
fila_bool['estado_publicacion'] = 'En publicacion'
fila_bool['editorial'] = 'Dark Horse'
fila_bool['editorial_id'] = '4'
comics.append(fila_bool)
anotar('7', 'destacado', 'booleano_invalido', '5')

# ---------------------------------------------------------------
# DEFECTOS DE UNICIDAD
# ---------------------------------------------------------------

# (i) PK duplicada: se repite el comic id=3 (como P0010 del laboratorio)
comics.append(dict(comics[2]))
anotar('3', 'id', 'pk_duplicada', '3 (segunda ocurrencia)')

# (j) Duplicado logico: mismo titulo con distinto id y distinto uso de
#     mayusculas (viola la clave natural UNIQUE sobre titulo)
dup_logico = dict(comics[0])
dup_logico['id'] = '8'
dup_logico['titulo'] = 'berserk'
dup_logico['tipo'] = 'Manga'
comics.append(dup_logico)
anotar('8', 'titulo', 'duplicado_logico_case', 'berserk')

# ---------------------------------------------------------------
# DEFECTO DE IDENTIFICADOR
# ---------------------------------------------------------------

# (k) PK negativa: el id es un INT UNSIGNED AUTO_INCREMENT, por lo que
#     un identificador negativo es imposible en el modelo real.
fila_id_negativo = dict(comics[1])
fila_id_negativo['id'] = '-4'
fila_id_negativo['titulo'] = 'Sandman'
fila_id_negativo['anio'] = '1989'
fila_id_negativo['tipo'] = 'Comic'
fila_id_negativo['clasificacion'] = '+18'
fila_id_negativo['estado_publicacion'] = 'Publicacion Terminada'
fila_id_negativo['genero'] = 'Fantasia oscura'
fila_id_negativo['editorial'] = 'DC'
fila_id_negativo['editorial_id'] = '3'
fila_id_negativo['destacado'] = '0'
comics.append(fila_id_negativo)
anotar('-4', 'id', 'pk_negativa', '-4')

# ---------------------------------------------------------------
# Salida
# ---------------------------------------------------------------
escribir(os.path.join(DESTINO, 'comics_sucio.csv'), comics, campos)
escribir(os.path.join(DESTINO, 'defectos_inyectados.csv'), registro,
         ['registro_id', 'columna', 'defecto', 'valor_sucio'])

print('DATASET SUCIO GENERADO (solo comics)')
print('  origen : export_csv/comics.csv        ', 5, 'filas limpias')
print('  salida : dataset_sucio/comics_sucio.csv', len(comics), 'filas')
print('  defectos inyectados                   :', len(registro))
print()
print('  Reconciliacion esperada en la limpieza:')
print('   ', len(comics), 'filas iniciales - 1 duplicada PK - 1 duplicado logico =',
      len(comics) - 2, 'unicas')
print('    de esas', len(comics) - 2, 'las que violen reglas de dominio se apartan.')
