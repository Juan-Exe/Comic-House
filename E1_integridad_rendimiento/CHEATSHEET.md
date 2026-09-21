# Hoja de referencia · Comic House

Todo verificado el 20-09-2026 con los contenedores arriba.

---

## 1. Arrancar / parar

```bash
cd "C:/Users/juand/Documents/JUAN DIEGO/PROYECTOS/Comic House/Comic-House"

docker compose up -d          # levantar
docker compose ps             # ver estado
docker compose logs -f web    # ver errores PHP en vivo
docker compose stop           # parar sin borrar datos
docker compose down -v        # BORRA la base de datos (cuidado)
```

Si Docker Desktop no está abierto, `docker compose up -d` falla.
Abrirlo primero y esperar ~40 s.

---

## 2. URLs de la aplicación · todas dan HTTP 200

### Públicas

| URL | Qué es |
|---|---|
| http://localhost:8080/ | Portada (slider + destacados) |
| http://localhost:8080/Login/login.php | Iniciar sesión |
| http://localhost:8080/Registro/registro.php | Registro |
| http://localhost:8080/P-comics/p-comics.php | Catálogo completo |
| http://localhost:8080/Biblioteca/ | Biblioteca del usuario |

### Con parámetros (datos reales)

| URL | Qué muestra |
|---|---|
| http://localhost:8080/Comics/comics.php?id=1 | Ficha de Berserk |
| http://localhost:8080/Comics/comics.php?id=3 | Ficha de Absolute Batman |
| http://localhost:8080/P-comics/p-comics.php?tipo=Manga | Solo mangas |
| http://localhost:8080/P-comics/p-comics.php?tipo=Comic | Solo cómics |
| http://localhost:8080/P-comics/p-comics.php?editorial=DC | Filtro por editorial |

### Lector (`capitulo_id` 1 a 8)

| URL | Capítulo | Páginas |
|---|---|---|
| http://localhost:8080/Lector/lector.php?capitulo_id=1 | Berserk #1-#3 | 219 |
| http://localhost:8080/Lector/lector.php?capitulo_id=2 | Berserk #4-#5 | 234 |
| http://localhost:8080/Lector/lector.php?capitulo_id=3 | Berserk #6-#9 | 225 |
| http://localhost:8080/Lector/lector.php?capitulo_id=4 | Saga #1 | 48 |
| http://localhost:8080/Lector/lector.php?capitulo_id=6 | Absolute Batman #1 | 42 |
| http://localhost:8080/Lector/lector.php?capitulo_id=7 | Cyberpunk 2077 | 57 |
| http://localhost:8080/Lector/lector.php?capitulo_id=8 | Bloodborne #1 | 31 |

### Administración (requieren sesión de admin)

| URL | Qué es |
|---|---|
| http://localhost:8080/dashboard.php | Panel principal |
| http://localhost:8080/crud.php | Gestión de cómics |
| http://localhost:8080/subir_capitulo.php | **Subida de CBR/CBZ (lo optimizado)** |
| http://localhost:8080/editar_capitulo.php | Editar capítulo |
| http://localhost:8080/modificar_comic.php | Modificar cómic |
| http://localhost:8080/editoriales.php | Editoriales |
| http://localhost:8080/destacar.php | Destacados del slider |
| http://localhost:8080/logout.php | Cerrar sesión |

**Usuario admin:** `admin` / `admin@comichouse.com` (es el único en la BD;
la contraseña está hasheada, si no la recuerdas hay que resetearla — ver §6).

---

## 3. Atajos de teclado

**La aplicación no tiene atajos de teclado implementados.**
Lo único ligado al teclado es el buscador, que filtra según escribes
(`onkeyup` en el navbar de cada página).

El lector se navega con scroll y con los controles en pantalla.
Si quieres flechas ←/→ para pasar página, hay que programarlo.

---

## 4. Benchmarks · los 4 de la exposición

```bash
cd "C:/Users/juand/Documents/JUAN DIEGO/PROYECTOS/Comic House/Comic-House"
```

### 1 · Inserción: antes vs después  ← el principal
```bash
docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/benchmark.php 220 5
```
Sale: 206 ms → 2.9 ms (**70.7x**). Tarda ~1 min.
Los argumentos son `[páginas] [repeticiones]`; con `220 3` va más rápido.

### 2 · Umbral: cuándo el motor decide usar el índice
```bash
docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/umbral.php
```
Sale: con 4 capítulos usa `ALL`, con 10 pasa a `ref`. Tarda ~3 min.

### 3 · Escalado: qué pasa si el volumen crece
```bash
docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/escalado.php
```
Sale: 0.48 → 75 ms sin índice vs 0.24 → 0.47 ms con índice (**161x**).
Tarda ~3 min.

### 4 · Verificación de integridad
```bash
docker exec comic_house_web php \
  /var/www/html/E1_integridad_rendimiento/benchmark/verificar_controlador.php
```
Sale: **7 correctas, 0 fallidas**. Tarda 2 s. Ideal para cerrar.

---

## 5. EXPLAIN en vivo · para proyectar en la exposición

### El plan actual (884 filas: ignora el índice)
```bash
docker exec comic_house_db mariadb -ucomic_user -pcomic_pass_123 comic_house -e \
"EXPLAIN SELECT id, imagen_url, orden, es_doble
 FROM paginas WHERE capitulo_id=1 ORDER BY orden\G"
```
→ `type=ALL  key=NULL  rows=884  Using where; Using filesort`

### Forzando el índice (demuestra que existe y sirve)
```bash
docker exec comic_house_db mariadb -ucomic_user -pcomic_pass_123 comic_house -e \
"EXPLAIN SELECT id, imagen_url, orden
 FROM paginas FORCE INDEX (idx_paginas_cap_orden)
 WHERE capitulo_id=1 ORDER BY orden\G"
```
→ `type=ref  key=idx_paginas_cap_orden  rows=219` (y desaparece el filesort)

### Por qué lo ignora: el capítulo es el 24.8% de la tabla
```bash
docker exec comic_house_db mariadb -ucomic_user -pcomic_pass_123 comic_house -e \
"SELECT capitulo_id, COUNT(*) n,
        ROUND(COUNT(*)*100/(SELECT COUNT(*) FROM paginas),1) pct
 FROM paginas GROUP BY capitulo_id ORDER BY n DESC LIMIT 3;"
```

### Ver los índices aplicados
```bash
docker exec comic_house_db mariadb -ucomic_user -pcomic_pass_123 comic_house -e \
"SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA='comic_house' AND TABLE_NAME='paginas';"
```

---

## 6. Base de datos

### Entrar a la consola
```bash
docker exec -it comic_house_db mariadb -ucomic_user -pcomic_pass_123 comic_house
```

### Conteo rápido
```bash
docker exec comic_house_db mariadb -ucomic_user -pcomic_pass_123 comic_house -e \
"SELECT (SELECT COUNT(*) FROM comics) comics,
        (SELECT COUNT(*) FROM capitulos) capitulos,
        (SELECT COUNT(*) FROM paginas) paginas;"
```
→ debe dar `5 · 8 · 884`

### Resetear la contraseña del admin a `admin123`
```bash
docker exec comic_house_web php -r \
'echo password_hash("admin123", PASSWORD_DEFAULT);'
# copiar el hash y pegarlo aqui:
docker exec comic_house_db mariadb -ucomic_user -pcomic_pass_123 comic_house -e \
"UPDATE usuarios SET Contraseña='<HASH>' WHERE usuario='admin';"
```

### Cliente gráfico externo (DBeaver, HeidiSQL…)
```
Host: localhost    Puerto: 3307
Base: comic_house  Usuario: comic_user   Clave: comic_pass_123
```

---

## 7. Git · el antes y después

```bash
cd "C:/Users/juand/Documents/JUAN DIEGO/PROYECTOS/Comic House/Comic-House"

git log --oneline -3                      # 1875069 es la optimización
git show 1875069 --stat                   # qué archivos cambiaron

# EL DIFF DE LA EXPOSICIÓN: antes vs después del controlador
git diff 4d1fee7 1875069 -- controlador/procesar_capitulo.php

# Solo el bucle optimizado
git diff 4d1fee7 1875069 -- controlador/procesar_capitulo.php | grep -A20 "OPTIMIZACIÓN"

git push origin main                      # subir a GitHub (aún NO hecho)
```

También está la copia literal del original en:
`E1_integridad_rendimiento/benchmark/antes/procesar_capitulo.ANTES.php`

---

## 8. Orden sugerido para exponer (5 min)

1. **Abrir la app** → http://localhost:8080/subir_capitulo.php
   «Esta es la operación que optimicé»
2. **Mostrar el problema** → `git diff` con el `foreach` y el INSERT dentro
3. **Correr el benchmark 1** → 206 ms → 2.9 ms, 70.7x
4. **Señalar el escenario B2** → solo la transacción ya da 12x:
   el costo estaba en el commit, no en el INSERT
5. **EXPLAIN en vivo** → `type=ALL`, y explicar por qué el motor lo ignora
   (24.8% de la tabla) — no es un fallo, es la decisión correcta hoy
6. **Correr el benchmark 3** → sin índice crece O(n), con índice plano: 161x
7. **Cerrar con la verificación** → 7/7, nada se rompió

---

## 9. Si algo falla

| Problema | Solución |
|---|---|
| `error during connect ... pipe` | Docker Desktop cerrado → abrirlo y esperar |
| La web no carga | `docker compose up -d` y `docker compose logs web` |
| «Table doesn't exist» | Las tablas `*_bench` se borran solas al terminar |
| El benchmark va lento | Usar `benchmark.php 220 3` en vez de `5` |
| Quedaron restos de prueba | `DELETE FROM capitulos WHERE titulo LIKE 'ZZZ_PRUEBA%';` |
