<?php
/**
 * index.php - Presentacion E1.
 *
 * Reutiliza la estetica REAL de Comic House: el header rojo con buscador,
 * la navbar #ef4444, el fondo blanco, los titulos en mayusculas con lineas
 * rojas a los lados y las tarjetas con borde rojo de 3px.
 *
 * Los comandos se ejecutan de verdad contra ejecutar.php.
 */
$BASE = '../..';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House · Rendimiento medible</title>
    <link rel="stylesheet" href="<?= $BASE ?>/fonts.css">
    <link rel="icon" href="<?= $BASE ?>/ico.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== paleta tomada de styles.css ===== */
        :root {
            --busqueda: #ce4646;   /* .search_bar   */
            --navbar:   #ef4444;   /* .navbar       */
            --acento:   #c8102e;
            --tinta:    #1a1a1a;
            --gris:     #6b6b74;
            --linea:    #e4e4ea;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Montserrat, system-ui, sans-serif;
            background: #fff;
            color: var(--tinta);
            overflow: hidden;
            height: 100vh;
        }

        /* ===== header identico al de la app ===== */
        /* franja superior: conserva el rojo del header de la app, pero sin
           un buscador falso — en una presentacion no busca nada. */
        .search_bar {
            background: var(--busqueda);
            width: 100%; height: 40px;
            display: flex; align-items: center; justify-content: center;
            position: relative; z-index: 200;
        }
        .franja-titulo {
            color: #fff; font-size: 12.5px; font-weight: 700;
            letter-spacing: 2.4px; text-transform: uppercase;
            opacity: .95;
        }

        .navbar {
            width: 100%; height: 72px;
            background: var(--navbar); color: #fff;
            display: grid; grid-template-columns: 1fr auto 1fr;
            align-items: center; padding: 0 34px;
            position: relative; z-index: 100;
        }
        .navbar .logo-m img { height: 44px; display: block; }
        .navbar .centro {
            display: flex; gap: 10px; align-items: center;
        }
        .navbar .paso {
            font-weight: 700; font-size: 13px; letter-spacing: .4px;
            opacity: .82;
        }
        .navbar .derecha { justify-self: end; display: flex; align-items: center; gap: 14px; }
        .navbar .contador { font-weight: 800; font-size: 15px; }

        /* pips de progreso */
        .pips { display: flex; gap: 4px; }
        .pip {
            width: 26px; height: 5px; border-radius: 3px;
            background: rgba(255,255,255,.34); cursor: pointer;
            transition: background .2s;
        }
        .pip.visto  { background: rgba(255,255,255,.7); }
        .pip.actual { background: #fff; width: 38px; }

        /* ===== lienzo ===== */
        .lienzo { position: absolute; top: 112px; bottom: 0; left: 0; right: 0; overflow: hidden; }
        .pista  { display: flex; height: 100%; transition: transform .42s cubic-bezier(.4,0,.2,1); }

        .pagina {
            min-width: 100%; height: 100%;
            padding: 26px 54px 34px;
            overflow-y: auto; background: #fff;
            display: flex; flex-direction: column; gap: 16px;
        }
        .pagina::-webkit-scrollbar { width: 9px; }
        .pagina::-webkit-scrollbar-thumb { background: var(--navbar); border-radius: 5px; }

        /* ===== titulo de seccion, como .editoriales de la app ===== */
        .titulo-seccion {
            display: flex; align-items: center; justify-content: center;
            gap: 22px; margin: 2px 0 6px;
        }
        .titulo-seccion .raya { flex: 1; height: 3px; background: var(--navbar); }
        .titulo-seccion h2 {
            margin: 0; font-size: 23px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .5px; text-align: center;
        }

        .kicker {
            text-align: center; color: var(--acento);
            font-size: 11.5px; font-weight: 800; letter-spacing: 2px;
            text-transform: uppercase; margin-bottom: 2px;
        }

        h1 {
            font-size: 40px; font-weight: 800; line-height: 1.1;
            margin: 0 0 12px; letter-spacing: -.8px; text-align: center;
        }
        h3 { font-size: 16px; font-weight: 800; margin: 0 0 8px; }
        p  { font-size: 14.5px; line-height: 1.65; margin: 0 0 10px; color: #33333c; }
        .centrado { text-align: center; }
        .apunte { font-size: 12.8px; color: var(--gris); font-style: italic; }
        strong { font-weight: 800; color: var(--tinta); }

        /* ===== tarjeta, como .sly-content: borde rojo 3px radio 10 ===== */
        .tarjeta {
            border: 3px solid var(--busqueda); border-radius: 10px;
            padding: 20px 24px; background: #fff;
        }
        .tarjeta.gris { background: #fafafc; border-color: var(--linea); }
        .tarjeta.ancha { flex: 1; display: flex; flex-direction: column; min-height: 0; }

        .dos { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .dos.crece { flex: 1; min-height: 0; }
        @media (max-width: 950px) { .dos { grid-template-columns: 1fr; } }

        /* ===== cifras ===== */
        .cifras { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }
        .cifra {
            border: 3px solid var(--busqueda); border-radius: 10px;
            padding: 14px 22px; min-width: 132px; text-align: center; background: #fff;
        }
        .cifra.fuerte { background: var(--busqueda); border-color: var(--busqueda); }
        .cifra .n { font-size: 30px; font-weight: 800; color: var(--acento); line-height: 1; }
        .cifra.fuerte .n { color: #fff; }
        .cifra .r {
            font-size: 10.5px; font-weight: 700; letter-spacing: .7px;
            text-transform: uppercase; color: var(--gris); margin-top: 6px;
        }
        .cifra.fuerte .r { color: rgba(255,255,255,.92); }

        .flecha-cifra { align-self: center; font-size: 24px; color: var(--navbar); font-weight: 800; }

        /* ===== lista con vinetas rojas ===== */
        ul.puntos { list-style: none; padding: 0; margin: 6px 0; }
        ul.puntos li {
            position: relative; padding-left: 22px; margin-bottom: 8px;
            font-size: 14px; line-height: 1.55; color: #33333c;
        }
        ul.puntos li::before {
            content: ''; position: absolute; left: 4px; top: 8px;
            width: 8px; height: 8px; background: var(--navbar); border-radius: 2px;
        }

        /* ===== consola ===== */
        .consola { flex: 1; display: flex; flex-direction: column; min-height: 0; margin-top: 4px; }
        .cmd-fila {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
            background: #23232b; border-radius: 10px 10px 0 0;
            padding: 11px 15px;
        }
        .cmd-txt {
            font-family: 'Courier New', monospace; color: #8ef0a3;
            font-size: 12.8px; flex: 1; min-width: 200px; word-break: break-all;
        }
        .cmd-txt::before { content: '$ '; color: #ff8080; font-weight: 700; }

        .boton {
            background: var(--acento); color: #fff; border: none; border-radius: 6px;
            font-family: Montserrat; font-weight: 800; font-size: 11.5px;
            letter-spacing: .6px; padding: 9px 18px; cursor: pointer;
            text-transform: uppercase; white-space: nowrap; transition: background .18s;
        }
        .boton:hover:not(:disabled) { background: #e01235; }
        .boton:disabled { opacity: .5; cursor: wait; }

        .estado {
            font-size: 11.5px; font-weight: 700; padding: 4px 11px; border-radius: 20px;
            background: #3a3a45; color: #c8c8d4;
        }
        .estado.ok  { background: #1b7f4f; color: #fff; }
        .estado.mal { background: var(--acento); color: #fff; }

        .salida {
            flex: 1; min-height: 96px; max-height: 340px;
            background: #16161d; border-radius: 0 0 10px 10px;
            padding: 15px 17px; overflow: auto;
            font-family: 'Courier New', monospace; font-size: 12.3px;
            line-height: 1.58; color: #e6e6ef; white-space: pre-wrap;
        }
        /* la consola no debe empujar el contenido: crece solo si hace falta */
        .tarjeta.ancha { flex: 0 1 auto; }
        .dos.crece .tarjeta.ancha { flex: 1; }
        .salida::-webkit-scrollbar { width: 9px; height: 9px; }
        .salida::-webkit-scrollbar-thumb { background: #41414f; border-radius: 5px; }
        .salida .vacio { color: #6a6a7a; font-style: italic; }

        .ln-add { color: #56d364; } .ln-del { color: #f85149; }
        .ln-hdr { color: #79c0ff; font-weight: 700; }
        .ln-ok  { color: #56d364; font-weight: 700; }
        .ln-err { color: #ff7b72; font-weight: 700; }
        .ln-num { color: #ffa657; } .ln-key { color: #d2a8ff; }

        /* ===== tabla ===== */
        table.t { width: 100%; border-collapse: collapse; font-size: 13.2px; }
        table.t th {
            background: var(--busqueda); color: #fff; text-align: left;
            padding: 9px 12px; font-size: 11px; letter-spacing: .6px; text-transform: uppercase;
        }
        table.t th:first-child { border-top-left-radius: 8px; }
        table.t th:last-child  { border-top-right-radius: 8px; }
        table.t td { padding: 8px 12px; border-bottom: 1px solid var(--linea); }
        table.t tr:last-child td { border-bottom: none; }
        table.t .destaca { background: #fdf0f2; font-weight: 800; }
        table.t .num { font-family: 'Courier New', monospace; }

        /* ===== bloque de codigo ===== */
        pre.codigo {
            background: #16161d; color: #e6e6ef; border-radius: 10px;
            padding: 14px 16px; font-family: 'Courier New', monospace;
            font-size: 12.3px; line-height: 1.55; overflow: auto; margin: 6px 0;
        }
        pre.codigo .mal { color: #ff7b72; }
        pre.codigo .bien { color: #56d364; }
        pre.codigo .com { color: #7a7a8c; font-style: italic; }

        .cita {
            border-left: 5px solid var(--navbar); background: #fdf0f2;
            padding: 13px 18px; border-radius: 0 8px 8px 0;
            font-size: 14.5px; line-height: 1.6; margin: 8px 0;
        }

        /* ===== navegacion ===== */
        /* sin flechas: se navega deslizando (rueda, arrastre o gesto tactil) */
        .pista.arrastrando { transition: none; cursor: grabbing; }
        .pagina { cursor: grab; user-select: none; }
        .pagina.sin-cursor { cursor: default; }
    </style>
</head>

<body>

<header>
    <div class="search_bar">
        <span class="franja-titulo">
            Optativa&nbsp;II · E1 · Núcleo de datos del proyecto
        </span>
    </div>

    <nav class="navbar">
        <div class="logo-m"><img src="<?= $BASE ?>/Imagenes/Logo-Comic-Huse.png" alt="Comic House"></div>
        <div class="centro"><div class="pips" id="pips"></div></div>
        <div class="derecha">
            <span class="paso" id="paso">Portada</span>
            <span class="contador" id="contador">1/14</span>
        </div>
    </nav>
</header>

<div class="lienzo">
    <div class="pista" id="pista">

        <!-- ============ 1 · PORTADA ============ -->
        <section class="pagina" data-paso="Portada" style="justify-content:center">
            <div class="kicker">Optativa II · Ingeniería de Sistemas · 2026-II</div>
            <h1>Rendimiento medible</h1>
            <p class="centrado" style="font-size:17px;max-width:720px;margin:0 auto 20px">
                Cómo la subida de un capítulo en <strong>Comic House</strong> pasó de
                <strong>206 ms a 2.9 ms</strong> — sin tocar una sola imagen.
            </p>

            <div class="cifras">
                <div class="cifra"><div class="n">206 ms</div><div class="r">antes</div></div>
                <div class="flecha-cifra">→</div>
                <div class="cifra fuerte"><div class="n">2.9 ms</div><div class="r">después</div></div>
                <div class="flecha-cifra">=</div>
                <div class="cifra"><div class="n">71×</div><div class="r">más rápido</div></div>
            </div>

            <div class="tarjeta gris" style="max-width:860px;margin:22px auto 0">
                <p style="margin:0" class="centrado">
                    <strong>G11 · Comic House</strong> · Juan Diego Arrieta &nbsp;·&nbsp;
                    Todo lo que verás a continuación <strong>se ejecuta en vivo</strong> sobre
                    la base de datos real del proyecto.
                </p>
            </div>
        </section>

        <!-- ============ 2 · AGENDA ============ -->
        <section class="pagina" data-paso="Agenda">
            <div class="titulo-seccion"><span class="raya"></span><h2>Qué vamos a ver</h2><span class="raya"></span></div>

            <div class="dos">
                <div class="tarjeta">
                    <h3>1 · El punto de partida · <span style="color:var(--acento)">E1</span></h3>
                    <ul class="puntos">
                        <li>Del laboratorio de pedidos a los datos propios</li>
                        <li>Ensuciar un dataset limpio <em>a propósito</em>: 13 defectos</li>
                        <li>Normalizar vs. rechazar, y la reconciliación 13 → 4</li>
                        <li>Qué guarda Comic House y cómo se relacionan sus tablas</li>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3>2 · El problema</h3>
                    <ul class="puntos">
                        <li>Un <code>INSERT</code> por página dentro del bucle</li>
                        <li>220 viajes a la base y 220 commits</li>
                        <li>El código, en vivo, con <code>git diff</code></li>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3>3 · La solución y su evidencia</h3>
                    <ul class="puntos">
                        <li>Transacción + inserción por lotes</li>
                        <li>Medición antes/después con tiempos reales</li>
                        <li><code>EXPLAIN</code>: qué plan elige el motor</li>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3>4 · Integridad y cierre</h3>
                    <ul class="puntos">
                        <li>Las reglas del negocio como restricciones</li>
                        <li>Intentar romper la base a propósito</li>
                        <li>Qué pasa si el volumen crece ×1000</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ============ 3 · DE DÓNDE VIENE E1 ============ -->
        <section class="pagina" data-paso="Origen">
            <div class="titulo-seccion"><span class="raya"></span><h2>De dónde viene este trabajo</h2><span class="raya"></span></div>

            <div class="cita">
                En clase trabajamos un dataset de <strong>pedidos</strong> con defectos
                intencionales: 138 filas, de las que 131 sobrevivían a la limpieza.
                La consigna de E1 fue <strong>transferir esa lógica al proyecto propio</strong>,
                donde las decisiones ya no vienen dadas: hay que justificarlas.
            </div>

            <div class="dos">
                <div class="tarjeta gris">
                    <h3>El laboratorio · pedidos</h3>
                    <ul class="puntos">
                        <li>Dataset entregado, ya preparado por el profesor</li>
                        <li>Reglas dadas: estados y métodos de pago fijos</li>
                        <li>Clientes válidos del 1 al 40</li>
                        <li>138 filas → 131 válidas</li>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3>E1 · Comic House</h3>
                    <ul class="puntos">
                        <li>Dataset propio, exportado de la base real del proyecto</li>
                        <li><strong>Yo defino las reglas</strong> y tengo que justificarlas</li>
                        <li>Editoriales válidas del 1 al 5</li>
                        <li>13 filas → 4 válidas</li>
                    </ul>
                </div>
            </div>

            <div class="tarjeta gris">
                <h3>La pregunta que abrió todo</h3>
                <p style="margin:0">Si la aplicación hoy <strong>no muestra datos duplicados</strong>,
                   ¿qué impide que mañana entren? Responderla es lo que lleva de «limpiar datos»
                   a «diseñar un sistema que no los acepte».</p>
            </div>
        </section>

        <!-- ============ 4 · ENSUCIAR ============ -->
        <section class="pagina" data-paso="Dataset sucio">
            <div class="titulo-seccion"><span class="raya"></span><h2>Ensuciar el dataset a propósito</h2><span class="raya"></span></div>

            <p class="centrado">Antes de limpiar hacía falta algo que limpiar. Se tomó el export
               real de <code>comics.csv</code> — <strong>5 obras</strong> — y se le inyectaron
               <strong>13 defectos</strong> de forma determinista: sin azar, el resultado es
               idéntico en cada ejecución.</p>

            <div class="dos">
                <div class="tarjeta">
                    <h3>Formato · se normalizan</h3>
                    <p class="apunte" style="margin-bottom:8px">La corrección es inequívoca y no inventa información.</p>
                    <ul class="puntos">
                        <li><code>' MANGA '</code> · mayúsculas y espacios</li>
                        <li><code>'comic'</code> · minúsculas</li>
                        <li><code>'COMIC '</code> · espacio sobrante</li>
                        <li><code>'  Superheroes  '</code> · espacios</li>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3>Dominio y unicidad · se rechazan</h3>
                    <p class="apunte" style="margin-bottom:8px">El valor correcto no puede deducirse con evidencia.</p>
                    <ul class="puntos">
                        <li><code>2099</code> año futuro · <code>18+</code> fuera de dominio</li>
                        <li><code>En publicacionn</code> typo · <code>999</code> FK inexistente</li>
                        <li>título vacío · <code>destacado = 5</code> no booleano</li>
                        <li>PK duplicada · título duplicado · <code>id = -4</code></li>
                    </ul>
                </div>
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="sucio">
                        <span class="cmd-txt">cat dataset_sucio/defectos_inyectados.csv</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Ver la bitácora de defectos</button>
                    </div>
                    <div class="salida"><span class="vacio">Cada defecto quedó registrado para poder verificar después si la limpieza lo atrapó…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 5 · DIAGNÓSTICO ============ -->
        <section class="pagina" data-paso="Diagnóstico">
            <div class="titulo-seccion"><span class="raya"></span><h2>Diagnóstico: observar sin corregir</h2><span class="raya"></span></div>

            <div class="cita">
                Regla de trabajo: <strong>no corregir datos silenciosamente.</strong>
                Primero identificar el problema, después decidir qué regla del sistema
                debería impedirlo. En esta fase solo se cuenta.
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="tabla_sucia">
                        <span class="cmd-txt">dataset_sucio/comics_sucio.csv · 13 filas</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Ver el dataset sucio</button>
                    </div>
                    <div class="salida"><span class="vacio">Cada fila con la regla que viola. Las marcadas ✓ son las que sobrevivirán…</span></div>
                </div>
            </div>

            <p class="apunte centrado" style="margin:0">
                Un detalle: <em>Absolute Batman</em> aparece con dos problemas a la vez.
                La fila con PK duplicada arrastra también una violación de la clave natural
                — <strong>un solo defecto se propaga</strong>.
            </p>
        </section>

        <!-- ============ 6 · LIMPIEZA ============ -->
        <section class="pagina" data-paso="Limpieza">
            <div class="titulo-seccion"><span class="raya"></span><h2>Normalizar no es lo mismo que rechazar</h2><span class="raya"></span></div>

            <div class="dos">
                <div class="tarjeta">
                    <h3>Normalizar</h3>
                    <p style="margin-bottom:6px">Cuando la corrección es <strong>inequívoca</strong>.</p>
                    <pre class="codigo"><span class="mal">' MANGA '  'comic'  'COMIC '</span>
              ↓
<span class="bien">        'Manga'  ·  'Comic'</span></pre>
                    <p class="apunte" style="margin:0">Cinco formas textuales que en realidad
                       eran dos valores. Quitar espacios y unificar mayúsculas es reversible.</p>
                </div>
                <div class="tarjeta">
                    <h3>Rechazar</h3>
                    <p style="margin-bottom:6px">Cuando el valor correcto <strong>no se puede deducir</strong>.</p>
                    <pre class="codigo"><span class="mal">'En publicacionn'</span>
              ↓
<span class="com">      se aparta, no se corrige</span></pre>
                    <p class="apunte" style="margin:0">Parece un typo evidente, pero corregirlo
                       sería <strong>suponer la intención</strong> del dato. Si el sistema empieza
                       a adivinar, deja de ser auditable.</p>
                </div>
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="limpio">
                        <span class="cmd-txt">dataset_limpio/comics_limpio.csv + comics_rechazados.csv</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Ver la reconciliación</button>
                    </div>
                    <div class="salida"><span class="vacio">13 filas iniciales − 2 duplicadas − 7 inválidas = 4 válidas…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 7 · EL PROYECTO ============ -->
        <section class="pagina" data-paso="Contexto">
            <div class="titulo-seccion"><span class="raya"></span><h2>El modelo de datos</h2><span class="raya"></span></div>

            <div class="dos">
                <div class="tarjeta">
                    <h3>Tres niveles encadenados</h3>
                    <pre class="codigo">editoriales
     ↓  (1 : N)
   comics        ← el catálogo que ve el lector
     ↓  (1 : N)
 capitulos       ← cada entrega publicada
     ↓  (1 : N)
  paginas        ← una fila POR IMAGEN del CBR</pre>
                    <p class="apunte">Al subir un capítulo de 220 páginas se crean
                       <strong>1 fila</strong> en <code>capitulos</code> y
                       <strong>220</strong> en <code>paginas</code>.</p>
                </div>

                <div class="tarjeta">
                    <h3>Preguntas que el sistema debe responder</h3>
                    <ul class="puntos">
                        <li>¿Cuántas obras hay por editorial y con qué clasificación?</li>
                        <li>¿Qué obras siguen en publicación y cuáles terminaron?</li>
                        <li><strong>¿Cuáles son las páginas del capítulo N, en orden?</strong>
                            — la consulta del lector, la más frecuente de todas</li>
                    </ul>
                    <p class="apunte">La tercera es la que se optimizó: se ejecuta cada vez
                       que alguien abre un capítulo.</p>
                </div>
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="conteo">
                        <span class="cmd-txt">SELECT COUNT(*) FROM comics, capitulos, paginas</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Ver volumen real</button>
                    </div>
                    <div class="salida"><span class="vacio">Consulta la base de datos del proyecto…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 4 · EL PROBLEMA ============ -->
        <section class="pagina" data-paso="El problema">
            <div class="titulo-seccion"><span class="raya"></span><h2>El cuello de botella</h2><span class="raya"></span></div>

            <div class="cita">
                El controlador insertaba <strong>dentro del bucle</strong> que recorre las
                imágenes extraídas del CBR. Con 220 páginas: <strong>220 viajes</strong> a
                MariaDB. Y como mysqli trabaja en <em>autocommit</em>, también
                <strong>220 commits</strong> sincronizados a disco.
            </div>

            <div class="dos crece">
                <div class="tarjeta ancha">
                    <h3>Así estaba escrito</h3>
                    <pre class="codigo"><span class="com">// procesar_capitulo.php · líneas 100-113</span>
foreach ($imagenes as $idx => $img) {
    ...
<span class="mal">    $conexion->query("INSERT INTO paginas
        (capitulo_id, imagen_url, orden, es_doble)
        VALUES ('$capitulo_id', '$ruta_web',
                '$orden', '$es_doble')");</span>
}</pre>
                    <p class="apunte">Además concatenaba variables en el SQL, sin sentencia preparada.</p>
                </div>

                <div class="tarjeta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="codigo_antes">
                            <span class="cmd-txt">cat benchmark/antes/procesar_capitulo.ANTES.php</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Ver original</button>
                        </div>
                        <div class="salida"><span class="vacio">El archivo original está guardado, no reconstruido…</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ 5 · LA SOLUCIÓN ============ -->
        <section class="pagina" data-paso="La solución">
            <div class="titulo-seccion"><span class="raya"></span><h2>Qué se cambió</h2><span class="raya"></span></div>

            <div class="dos">
                <div class="tarjeta">
                    <h3>Una transacción, lotes de 100</h3>
                    <pre class="codigo"><span class="bien">$conexion->begin_transaction();</span>
try {
    for ($i = 0; $i &lt; $total; $i += 100) {
        <span class="com">// 100 filas en UNA sola consulta</span>
        $conexion->query("INSERT INTO paginas
            (...) VALUES " . implode(',', $tuplas));
    }
    <span class="bien">$conexion->commit();</span>
} catch (Throwable $e) {
    <span class="bien">$conexion->rollback();</span>
}</pre>
                </div>

                <div class="tarjeta">
                    <h3>Tres mejoras en un solo cambio</h3>
                    <ul class="puntos">
                        <li><strong>Velocidad:</strong> 220 consultas → 3, y 220 commits → 1</li>
                        <li><strong>Atomicidad:</strong> si falla la página 150, el
                            <code>ROLLBACK</code> deshace todo y borra el capítulo. Antes
                            quedaba media carga sin forma de detectarla</li>
                        <li><strong>Seguridad:</strong> el <code>INSERT</code> del capítulo
                            pasó a sentencia preparada</li>
                    </ul>
                    <p class="apunte">Optimizar y dar correctitud resultaron ser la misma acción.</p>
                </div>
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="diff">
                        <span class="cmd-txt">git diff 4d1fee7 1875069 -- controlador/procesar_capitulo.php</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Ver el diff real</button>
                    </div>
                    <div class="salida"><span class="vacio">Rojo = lo que se quitó · Verde = lo que entró…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 6 · LA MEDICIÓN ============ -->
        <section class="pagina" data-paso="Medición">
            <div class="titulo-seccion"><span class="raya"></span><h2>La evidencia: medir, no suponer</h2><span class="raya"></span></div>

            <table class="t">
                <tr><th>Escenario</th><th>Media</th><th>Mejor</th><th>Factor</th></tr>
                <tr><td>A · un INSERT por página <em>(código original)</em></td>
                    <td class="num">206.47 ms</td><td class="num">196.17 ms</td><td>—</td></tr>
                <tr><td>B2 · sentencia preparada + transacción</td>
                    <td class="num">17.22 ms</td><td class="num">14.16 ms</td><td class="num">12×</td></tr>
                <tr class="destaca"><td>B · inserción por lotes + transacción</td>
                    <td class="num">2.92 ms</td><td class="num">1.56 ms</td><td class="num">71×</td></tr>
            </table>

            <div class="cita">
                Fíjate en el escenario intermedio: <strong>solo envolver los 220 INSERT en una
                transacción ya da 12×</strong>, sin cambiar la forma de la consulta. El costo
                dominante no era el INSERT — era <strong>el commit por fila</strong>.
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="benchmark">
                        <span class="cmd-txt">php benchmark.php 220 3</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Medir ahora (~25 s)</button>
                    </div>
                    <div class="salida"><span class="vacio">Inserta 220 páginas tres veces con cada estrategia. Los números variarán un poco según la carga de la máquina…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 7 · EXPLAIN ============ -->
        <section class="pagina" data-paso="EXPLAIN">
            <div class="titulo-seccion"><span class="raya"></span><h2>¿Qué decide el motor?</h2><span class="raya"></span></div>
            <p class="centrado" style="margin-bottom:4px">
                La consulta del lector: <code>SELECT … FROM paginas WHERE capitulo_id = 1 ORDER BY orden</code>
            </p>

            <div class="dos crece">
                <div class="tarjeta ancha">
                    <h3>Plan que elige por su cuenta</h3>
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="explain_antes">
                            <span class="cmd-txt">EXPLAIN SELECT … ORDER BY orden</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Ejecutar</button>
                        </div>
                        <div class="salida"><span class="vacio">Mira los campos type, key y Extra…</span></div>
                    </div>
                </div>

                <div class="tarjeta ancha">
                    <h3>Forzando el índice compuesto</h3>
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="explain_forzado">
                            <span class="cmd-txt">EXPLAIN … FORCE INDEX (idx_paginas_cap_orden)</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Ejecutar</button>
                        </div>
                        <div class="salida"><span class="vacio">El mismo dato, otra estrategia…</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ 8 · EL HALLAZGO ============ -->
        <section class="pagina" data-paso="Hallazgo">
            <div class="titulo-seccion"><span class="raya"></span><h2>El índice existe… y el motor lo ignora</h2><span class="raya"></span></div>

            <div class="cita">
                <strong>No es un error.</strong> El capítulo 1 tiene 219 de las 884 páginas:
                es el <strong>24.8 % de la tabla</strong>. Cuando una consulta va a devolver
                una cuarta parte de las filas, recorrer la tabla entera de forma secuencial
                sale <strong>más barato</strong> que saltar del índice a los datos fila por
                fila. El optimizador lo calcula y descarta el índice.
            </div>

            <div class="dos crece">
                <div class="tarjeta ancha">
                    <h3>La proporción, en datos</h3>
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="proporcion">
                            <span class="cmd-txt">SELECT capitulo_id, COUNT(*), % del total</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Comprobar</button>
                        </div>
                        <div class="salida"><span class="vacio">¿Cuánto pesa un capítulo dentro de la tabla?</span></div>
                    </div>
                </div>

                <div class="tarjeta ancha">
                    <h3>El umbral medido</h3>
                    <table class="t">
                        <tr><th>Capítulos</th><th>Filas</th><th>Plan</th></tr>
                        <tr><td>4</td><td class="num">880</td><td>ALL + filesort</td></tr>
                        <tr class="destaca"><td>10</td><td class="num">2.200</td><td>ref · usa el índice</td></tr>
                        <tr><td>1.000</td><td class="num">220.000</td><td>ref</td></tr>
                        <tr><td>4.000</td><td class="num">880.000</td><td>ref</td></tr>
                    </table>
                    <p class="apunte" style="margin-top:10px">El punto de quiebre está entre 4 y 10
                       capítulos. <strong>Comic House tiene 8</strong>: con el próximo que se
                       suba, el índice empieza a usarse solo.</p>
                </div>
            </div>
        </section>

        <!-- ============ 9 · ESCALADO ============ -->
        <section class="pagina" data-paso="Escalado">
            <div class="titulo-seccion"><span class="raya"></span><h2>¿Y si el volumen crece?</h2><span class="raya"></span></div>

            <table class="t">
                <tr><th>Capítulos</th><th>Filas</th><th>Sin índice</th><th>Con índice</th><th>Factor</th></tr>
                <tr><td>10</td><td class="num">2.200</td><td class="num">0.484 ms</td><td class="num">0.237 ms</td><td>2×</td></tr>
                <tr><td>100</td><td class="num">22.000</td><td class="num">2.261 ms</td><td class="num">0.331 ms</td><td>7×</td></tr>
                <tr><td>500</td><td class="num">110.000</td><td class="num">10.140 ms</td><td class="num">0.318 ms</td><td>32×</td></tr>
                <tr><td>2.000</td><td class="num">440.000</td><td class="num">38.657 ms</td><td class="num">0.376 ms</td><td>103×</td></tr>
                <tr class="destaca"><td>4.000</td><td class="num">880.000</td><td class="num">75.379 ms</td><td class="num">0.469 ms</td><td>161×</td></tr>
            </table>

            <div class="dos">
                <div class="tarjeta">
                    <h3>Sin índice · O(n)</h3>
                    <p>El tiempo crece con la tabla: de 0.48 a 75 ms, unas <strong>156 veces</strong>.
                       Cada capítulo nuevo hace más lenta la lectura de <em>todos</em> los demás.</p>
                </div>
                <div class="tarjeta">
                    <h3>Con índice · O(log n)</h3>
                    <p>Se mantiene plano: de 0.24 a 0.47 ms. Multiplicar los datos por
                       <strong>400</strong> apenas duplica el tiempo.</p>
                </div>
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="escalado">
                        <span class="cmd-txt">php escalado.php</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Proyectar (~3 min)</button>
                    </div>
                    <div class="salida"><span class="vacio">Genera hasta 880.000 filas y mide. Tarda unos 3 minutos — mejor lanzarlo y seguir explicando…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 10 · INTEGRIDAD ============ -->
        <section class="pagina" data-paso="Integridad">
            <div class="titulo-seccion"><span class="raya"></span><h2>Las reglas, dentro de la base</h2><span class="raya"></span></div>
            <p class="centrado">De nada sirve ser rápido si los datos se corrompen.
               Cada regla del negocio se convirtió en una restricción verificable.</p>

            <table class="t">
                <tr><th>Regla del negocio</th><th>Restricción</th></tr>
                <tr><td>Dos páginas no ocupan la misma posición en un capítulo</td>
                    <td><code>UNIQUE (capitulo_id, orden)</code></td></tr>
                <tr><td>El orden de página es positivo</td>
                    <td><code>CHECK (orden &gt; 0)</code> + <code>INT UNSIGNED</code></td></tr>
                <tr><td>Una página pertenece a un capítulo que existe</td>
                    <td><code>FOREIGN KEY</code> + <code>ON DELETE CASCADE</code></td></tr>
                <tr><td>Leer un capítulo en orden debe ser rápido</td>
                    <td><code>INDEX (capitulo_id, orden)</code></td></tr>
            </table>

            <div class="dos crece">
                <div class="tarjeta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="restricciones">
                            <span class="cmd-txt">SHOW INDEX FROM paginas</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Índices</button>
                        </div>
                        <div class="salida"><span class="vacio">…</span></div>
                    </div>
                </div>
                <div class="tarjeta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="fk">
                            <span class="cmd-txt">SELECT … KEY_COLUMN_USAGE</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Claves foráneas</button>
                        </div>
                        <div class="salida"><span class="vacio">…</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ 11 · ROMPERLA ============ -->
        <section class="pagina" data-paso="Prevención">
            <div class="titulo-seccion"><span class="raya"></span><h2>Intentemos romperla</h2><span class="raya"></span></div>

            <div class="cita">
                Vamos a insertar una página con <strong>orden = −5</strong> directamente en
                la base, saltándonos el PHP. <strong>Si aparece un error, el sistema
                funciona.</strong>
            </div>

            <p class="apunte centrado">Aquí el error es el resultado deseado: demuestra que la
               regla se cumple aunque el código de la aplicación falle.</p>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="rechazo">
                        <span class="cmd-txt">INSERT INTO paginas (…) VALUES (1, 'prueba/invalida.jpg', -5, 0)</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Intentar romper la base</button>
                    </div>
                    <div class="salida"><span class="vacio">Debería aparecer un error de la base rechazando el dato…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 12 · BATERÍA ============ -->
        <section class="pagina" data-paso="Verificación">
            <div class="titulo-seccion"><span class="raya"></span><h2>Siete pruebas sobre la base real</h2><span class="raya"></span></div>

            <div class="dos">
                <div class="tarjeta">
                    <h3>Qué comprueba</h3>
                    <ul class="puntos">
                        <li>Inserta 220 páginas con el método nuevo</li>
                        <li>Verifica que quedaron en orden 1..220</li>
                        <li><code>UNIQUE</code> rechaza una posición repetida</li>
                        <li><code>CHECK</code> rechaza orden cero</li>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3>Y además</h3>
                    <ul class="puntos">
                        <li><code>ROLLBACK</code> deshace una carga a medias</li>
                        <li><code>ON DELETE CASCADE</code> borra las páginas huérfanas</li>
                        <li><strong>Limpia todo</strong> lo que creó al terminar</li>
                    </ul>
                    <p class="apunte">Corre contra el catálogo real: 5 cómics, 8 capítulos, 884 páginas.</p>
                </div>
            </div>

            <div class="tarjeta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="verificar">
                        <span class="cmd-txt">php verificar_controlador.php</span>
                        <span class="estado">sin ejecutar</span>
                        <button class="boton">Ejecutar las 7 pruebas</button>
                    </div>
                    <div class="salida"><span class="vacio">Resultado esperado: 7 correctas, 0 fallidas…</span></div>
                </div>
            </div>
        </section>

        <!-- ============ 13 · CONSULTAS + DEUDA ============ -->
        <section class="pagina" data-paso="Consultas">
            <div class="titulo-seccion"><span class="raya"></span><h2>Consultas que responden preguntas</h2><span class="raya"></span></div>

            <div class="dos crece">
                <div class="tarjeta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="consulta2">
                            <span class="cmd-txt">Páginas por capítulo</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Consultar</button>
                        </div>
                        <div class="salida"><span class="vacio">JOIN de comics, capitulos y paginas…</span></div>
                    </div>
                </div>
                <div class="tarjeta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="fk_huerfana">
                            <span class="cmd-txt">SELECT titulo, editorial, editorial_id FROM comics</span>
                            <span class="estado">sin ejecutar</span>
                            <button class="boton">Una deuda encontrada</button>
                        </div>
                        <div class="salida"><span class="vacio">Al preparar esto apareció algo que conviene arreglar…</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ 14 · CIERRE ============ -->
        <section class="pagina" data-paso="Conclusiones">
            <div class="titulo-seccion"><span class="raya"></span><h2>Lo que aprendí</h2><span class="raya"></span></div>

            <div class="dos">
                <div class="tarjeta">
                    <h3>Conclusiones</h3>
                    <ul class="puntos">
                        <li><strong>El costo estaba en los commits</strong>, no en los INSERT.
                            Solo la transacción ya daba 12×.</li>
                        <li><strong>Un índice puede ser correcto y no usarse todavía.</strong>
                            A 884 filas el optimizador lo descarta con razón.</li>
                        <li><strong>Optimizar dio correctitud de regalo:</strong> la misma
                            transacción que acelera evita capítulos a medio subir.</li>
                        <li><strong>Medir cambia la conversación:</strong> «va más rápido» no
                            es un argumento; 206 → 2.9 ms sí lo es.</li>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3>Límites y siguiente paso</h3>
                    <ul class="puntos">
                        <li>Los volúmenes grandes son datos sintéticos; el catálogo real
                            tiene 884 páginas.</li>
                        <li>Solo se midió el tiempo de base de datos: descomprimir el CBR
                            sigue siendo lo más lento en términos absolutos.</li>
                        <li><code>editorial_id</code> está NULL: la relación va por nombre
                            de texto y debería ser clave foránea.</li>
                        <li>Mover la descompresión a segundo plano para que el usuario no
                            espere.</li>
                    </ul>
                </div>
            </div>

            <div class="cifras" style="margin-top:6px">
                <div class="cifra fuerte"><div class="n">71×</div><div class="r">carga de capítulo</div></div>
                <div class="cifra fuerte"><div class="n">161×</div><div class="r">lectura proyectada</div></div>
                <div class="cifra fuerte"><div class="n">7/7</div><div class="r">pruebas de integridad</div></div>
            </div>

            <p class="centrado" style="margin-top:14px;font-size:16px">
                <strong>Se midió, no se supuso.</strong> Gracias.
            </p>
        </section>

    </div>
</div>


<script>
const pista   = document.getElementById('pista');
const paginas = document.querySelectorAll('.pagina');
const total   = paginas.length;
let actual = 0;

const cajaPips = document.getElementById('pips');
for (let i = 0; i < total; i++) {
    const d = document.createElement('div');
    d.className = 'pip';
    d.title = paginas[i].dataset.paso || ('Página ' + (i + 1));
    d.onclick = () => ir(i);
    cajaPips.appendChild(d);
}
const pips = cajaPips.querySelectorAll('.pip');

function ir(n) {
    actual = Math.max(0, Math.min(total - 1, n));
    pista.style.transform = `translateX(-${actual * 100}%)`;
    document.getElementById('contador').textContent = `${actual + 1}/${total}`;
    document.getElementById('paso').textContent = paginas[actual].dataset.paso || '';
    pips.forEach((p, i) => {
        p.classList.toggle('actual', i === actual);
        p.classList.toggle('visto', i < actual);
    });
    paginas[actual].scrollTop = 0;
}

// ---------- navegacion por deslizamiento ----------
const lienzo = document.querySelector('.lienzo');

/** ¿el puntero está sobre algo que tiene su propio scroll (una consola)? */
function sobreZonaConScroll(destino) {
    let n = destino;
    while (n && n !== lienzo) {
        if (n.classList && n.classList.contains('salida')) return true;
        if (n.tagName === 'PRE') return true;
        n = n.parentElement;
    }
    return false;
}

// --- rueda del ratón / trackpad ---
let ruedaOcupada = false;
lienzo.addEventListener('wheel', e => {
    if (sobreZonaConScroll(e.target)) return;       // deja leer la salida
    const pag = paginas[actual];
    const horizontal = Math.abs(e.deltaX) > Math.abs(e.deltaY);
    // si la diapositiva tiene scroll vertical propio, respetarlo primero
    if (!horizontal && pag.scrollHeight > pag.clientHeight) {
        const arriba = pag.scrollTop <= 0;
        const abajo  = pag.scrollTop + pag.clientHeight >= pag.scrollHeight - 1;
        if (!(arriba && e.deltaY < 0) && !(abajo && e.deltaY > 0)) return;
    }
    const delta = horizontal ? e.deltaX : e.deltaY;
    if (Math.abs(delta) < 12 || ruedaOcupada) return;
    e.preventDefault();
    ruedaOcupada = true;
    ir(actual + (delta > 0 ? 1 : -1));
    setTimeout(() => { ruedaOcupada = false; }, 480);
}, { passive: false });

// --- arrastre con el ratón y deslizamiento táctil ---
let x0 = null, y0 = null, arrastrando = false, ancho = 0;

function inicio(x, y, destino) {
    if (sobreZonaConScroll(destino)) return;
    x0 = x; y0 = y; arrastrando = false;
    ancho = lienzo.clientWidth;
}

function mover(x, y, e) {
    if (x0 === null) return;
    const dx = x - x0, dy = y - y0;
    if (!arrastrando) {
        if (Math.abs(dx) < 12 || Math.abs(dx) < Math.abs(dy)) return;  // es scroll vertical
        arrastrando = true;
        pista.classList.add('arrastrando');
    }
    if (e && e.cancelable) e.preventDefault();
    // resistencia en los extremos
    let corr = dx;
    if ((actual === 0 && dx > 0) || (actual === total - 1 && dx < 0)) corr = dx * 0.32;
    pista.style.transform = `translateX(calc(-${actual * 100}% + ${corr}px))`;
}

function fin(x) {
    if (x0 === null) return;
    const dx = x - x0;
    pista.classList.remove('arrastrando');
    if (arrastrando && Math.abs(dx) > ancho * 0.16) {
        ir(actual + (dx < 0 ? 1 : -1));
    } else {
        ir(actual);   // vuelve a su sitio
    }
    x0 = null; y0 = null; arrastrando = false;
}

lienzo.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    inicio(e.clientX, e.clientY, e.target);
});
window.addEventListener('mousemove', e => mover(e.clientX, e.clientY, e));
window.addEventListener('mouseup',   e => fin(e.clientX));

lienzo.addEventListener('touchstart', e => {
    inicio(e.touches[0].clientX, e.touches[0].clientY, e.target);
}, { passive: true });
lienzo.addEventListener('touchmove', e => {
    mover(e.touches[0].clientX, e.touches[0].clientY, e);
}, { passive: false });
lienzo.addEventListener('touchend', e => fin(e.changedTouches[0].clientX));

// que arrastrar no seleccione texto ni arrastre imagenes
lienzo.addEventListener('dragstart', e => e.preventDefault());

document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT') return;
    if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); ir(actual + 1); }
    if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   { e.preventDefault(); ir(actual - 1); }
    if (e.key === 'Home') ir(0);
    if (e.key === 'End')  ir(total - 1);
    if (e.key === 'f' || e.key === 'F') {
        document.fullscreenElement ? document.exitFullscreen()
                                   : document.documentElement.requestFullscreen();
    }
    if (e.key === 'Enter') {
        const b = paginas[actual].querySelector('.cmd-fila button:not(:disabled)');
        if (b) b.click();
    }
});

function escapar(t) {
    return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function colorear(texto, tipo) {
    return escapar(texto).split('\n').map(l => {
        if (tipo === 'diff') {
            if (/^\+\+\+|^---|^diff |^index /.test(l)) return `<span class="ln-hdr">${l}</span>`;
            if (/^@@/.test(l)) return `<span class="ln-key">${l}</span>`;
            if (/^\+/.test(l)) return `<span class="ln-add">${l}</span>`;
            if (/^-/.test(l))  return `<span class="ln-del">${l}</span>`;
            return l;
        }
        if (/\[OK\]|✅|correctas|RECHAZÓ/i.test(l))            return `<span class="ln-ok">${l}</span>`;
        if (/\[FALLA\]|errno|ERROR|failed|fallidas/i.test(l))  return `<span class="ln-err">${l}</span>`;
        if (/SEARCH|USING INDEX|type=ref|: ref/.test(l))       return `<span class="ln-ok">${l}</span>`;
        if (/\bALL\b|filesort|SCAN|NULL/.test(l))              return `<span class="ln-num">${l}</span>`;
        if (/^\s*[-+|]{3,}|^\s*\*{5,}/.test(l))                return `<span class="ln-hdr">${l}</span>`;
        if (/HALLAZGO|RECONCILIACIÓN/.test(l))                 return `<span class="ln-key">${l}</span>`;
        if (/\d+\.\d+\s*(ms|x)|\d+x\b/.test(l))                return `<span class="ln-num">${l}</span>`;
        return l;
    }).join('\n');
}

document.querySelectorAll('.cmd-fila').forEach(fila => {
    const clave  = fila.dataset.cmd;
    const boton  = fila.querySelector('button');
    const estado = fila.querySelector('.estado');
    const salida = fila.parentElement.querySelector('.salida');

    boton.addEventListener('click', async () => {
        const rotulo = boton.textContent;
        boton.disabled = true;
        boton.textContent = 'Ejecutando…';
        estado.className = 'estado';
        estado.textContent = 'corriendo';
        salida.innerHTML = '<span class="vacio">Ejecutando en esta máquina…</span>';

        try {
            const r = await fetch('ejecutar.php?cmd=' + encodeURIComponent(clave));
            const d = await r.json();

            if (!d.ok) {
                estado.className = 'estado mal';
                estado.textContent = 'error';
                salida.innerHTML = `<span class="ln-err">${escapar(d.error || 'fallo')}</span>`;
                return;
            }

            salida.innerHTML = colorear(d.salida, d.tipo);
            const exito = d.esperaba_error ? (d.codigo !== 0) : (d.codigo === 0);
            estado.className = 'estado ' + (exito ? 'ok' : 'mal');
            estado.textContent = d.esperaba_error
                ? (d.codigo !== 0 ? '✓ rechazado' : '✗ no rechazó')
                : (d.codigo === 0 ? `✓ ${d.ms} ms` : `✗ código ${d.codigo}`);
        } catch (err) {
            estado.className = 'estado mal';
            estado.textContent = 'sin respuesta';
            salida.innerHTML = `<span class="ln-err">No se pudo contactar ejecutar.php\n${escapar(err.message)}</span>`;
        } finally {
            boton.disabled = false;
            boton.textContent = rotulo;
        }
    });
});

ir(0);
</script>

</body>
</html>
