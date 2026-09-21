<?php
/**
 * index.php - Presentacion E1 con estetica de Comic House.
 *
 * Cada "pagina" es una vineta de comic. Los comandos se ejecutan de verdad
 * contra ejecutar.php, que corre docker/git/mariadb en esta maquina.
 */
$BASE = '../..';   // raiz del proyecto, para reutilizar sus assets
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comic House · Rendimiento medible</title>
    <link rel="stylesheet" href="<?= $BASE ?>/fonts.css">
    <link rel="icon" href="<?= $BASE ?>/ico.ico">
    <style>
        :root {
            --rojo: #ce4646;
            --rojo-vivo: #ef4444;
            --rojo-hondo: #c8102e;
            --tinta: #1a1a1a;
            --papel: #f4f1ea;
            --borde: #1a1a1a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Montserrat, system-ui, sans-serif;
            background: var(--tinta);
            color: var(--tinta);
            overflow: hidden;
            height: 100vh;
        }

        /* ---------- barra superior ---------- */
        .barra {
            position: fixed; top: 0; left: 0; right: 0; height: 58px;
            background: var(--rojo);
            display: flex; align-items: center; gap: 22px;
            padding: 0 22px; z-index: 50;
            border-bottom: 3px solid var(--tinta);
        }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo img { height: 34px; }
        .logo span {
            color: #fff; font-weight: 800; font-size: 15px;
            letter-spacing: .5px; line-height: 1.05;
        }
        .progreso { flex: 1; display: flex; gap: 5px; align-items: center; }
        .pip {
            height: 7px; flex: 1; background: rgba(0,0,0,.28);
            border-radius: 4px; cursor: pointer; transition: background .2s;
        }
        .pip.visto { background: rgba(255,255,255,.55); }
        .pip.actual { background: #fff; box-shadow: 0 0 0 2px rgba(0,0,0,.35); }
        .contador {
            color: #fff; font-weight: 700; font-size: 13px;
            min-width: 62px; text-align: right;
        }

        /* ---------- lienzo ---------- */
        .lienzo {
            position: absolute; top: 58px; bottom: 0; left: 0; right: 0;
            overflow: hidden;
        }
        .pista { display: flex; height: 100%; transition: transform .45s cubic-bezier(.4,0,.2,1); }

        .pagina {
            min-width: 100%; height: 100%;
            padding: 26px 46px;
            display: flex; flex-direction: column; gap: 14px;
            overflow-y: auto;
            background:
                radial-gradient(circle at 12% 12%, rgba(255,255,255,.05) 0 2px, transparent 2px) 0 0/16px 16px,
                var(--tinta);
        }
        .pagina::-webkit-scrollbar { width: 10px; }
        .pagina::-webkit-scrollbar-thumb { background: var(--rojo); border-radius: 5px; }

        /* ---------- vinetas ---------- */
        .vineta {
            background: var(--papel);
            border: 3px solid var(--borde);
            border-radius: 3px;
            box-shadow: 7px 7px 0 rgba(0,0,0,.55);
            padding: 20px 24px;
        }
        .vineta.ancha { flex: 1; display: flex; flex-direction: column; min-height: 0; }

        .rotulo {
            /* align-self evita que el flex de .vineta.ancha lo estire a todo el ancho */
            display: inline-block; align-self: flex-start; width: fit-content;
            background: var(--rojo-hondo); color: #fff;
            font-weight: 800; font-size: 10.5px; letter-spacing: 1.4px;
            padding: 4px 11px; border: 2px solid var(--borde);
            transform: rotate(-1.5deg); margin-bottom: 11px;
        }
        h1 {
            font-size: 31px; line-height: 1.12; font-weight: 800;
            margin-bottom: 9px; letter-spacing: -.5px;
        }
        h2 { font-size: 20px; font-weight: 800; margin-bottom: 8px; }
        p  { font-size: 14.5px; line-height: 1.62; margin-bottom: 9px; }
        .apunte, .apunta { font-size: 13px; color: #55505c; font-style: italic; }
        strong { font-weight: 800; }

        /* globo de dialogo */
        .globo {
            position: relative; background: #fff; border: 3px solid var(--borde);
            border-radius: 20px; padding: 15px 20px; margin: 13px 0;
            font-size: 15px; line-height: 1.55;
        }
        .globo::after {
            content: ''; position: absolute; bottom: -17px; left: 42px;
            border: 9px solid transparent; border-top-color: var(--borde);
        }
        .globo::before {
            content: ''; position: absolute; bottom: -12px; left: 45px;
            border: 7px solid transparent; border-top-color: #fff; z-index: 1;
        }

        /* onomatopeya */
        .pow {
            font-size: 44px; font-weight: 900; color: var(--rojo-hondo);
            -webkit-text-stroke: 2.5px var(--tinta);
            transform: rotate(-4deg); letter-spacing: -1.5px; line-height: 1;
        }

        /* ---------- consola ---------- */
        .consola { flex: 1; display: flex; flex-direction: column; min-height: 0; }
        .cmd-fila {
            display: flex; align-items: center; gap: 11px;
            background: var(--tinta); border: 3px solid var(--borde);
            padding: 10px 13px; flex-wrap: wrap;
        }
        .cmd-txt {
            font-family: 'Courier New', monospace; color: #7ee787;
            font-size: 13px; flex: 1; min-width: 210px; word-break: break-all;
        }
        .cmd-txt::before { content: '$ '; color: var(--rojo-vivo); font-weight: 700; }

        .boton {
            background: var(--rojo-hondo); color: #fff; border: 2px solid #fff;
            font-family: Montserrat, sans-serif; font-weight: 800; font-size: 12px;
            letter-spacing: .6px; padding: 8px 17px; cursor: pointer;
            text-transform: uppercase; white-space: nowrap;
            transition: transform .12s, background .2s;
        }
        .boton:hover:not(:disabled) { background: var(--rojo-vivo); transform: translateY(-2px); }
        .boton:disabled { opacity: .55; cursor: wait; }

        .salida {
            flex: 1; min-height: 120px; margin-top: 0;
            background: #0d0d14; border: 3px solid var(--borde); border-top: none;
            padding: 14px 16px; overflow: auto;
            font-family: 'Courier New', monospace; font-size: 12.4px;
            line-height: 1.55; color: #e6e6ef; white-space: pre-wrap;
        }
        .salida::-webkit-scrollbar { width: 10px; height: 10px; }
        .salida::-webkit-scrollbar-thumb { background: #3a3a48; border-radius: 5px; }
        .salida .vacio { color: #6a6a7a; font-style: italic; }

        /* coloreado */
        .ln-add { color: #56d364; }
        .ln-del { color: #f85149; }
        .ln-hdr { color: #79c0ff; font-weight: 700; }
        .ln-ok  { color: #56d364; font-weight: 700; }
        .ln-err { color: #ff7b72; font-weight: 700; }
        .ln-num { color: #ffa657; }
        .ln-key { color: #d2a8ff; }

        .estado {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 12px; font-weight: 700; padding: 3px 11px;
            border: 2px solid var(--borde);
        }
        .estado.ok   { background: #1b7f4f; color: #fff; }
        .estado.mal  { background: var(--rojo-hondo); color: #fff; }
        .estado.espera { background: #4a4a58; color: #fff; }

        /* metricas */
        .cifras { display: flex; gap: 13px; flex-wrap: wrap; margin: 11px 0; }
        .cifra {
            background: #fff; border: 3px solid var(--borde);
            box-shadow: 4px 4px 0 rgba(0,0,0,.5);
            padding: 11px 17px; min-width: 122px;
        }
        .cifra .n { font-size: 26px; font-weight: 900; color: var(--rojo-hondo); line-height: 1; }
        .cifra .r { font-size: 10.5px; font-weight: 700; letter-spacing: .6px;
                    text-transform: uppercase; color: #55505c; margin-top: 5px; }

        table.datos { width: 100%; border-collapse: collapse; font-size: 13px; margin: 9px 0; }
        table.datos th {
            background: var(--tinta); color: #fff; text-align: left;
            padding: 7px 10px; font-size: 11px; letter-spacing: .5px; text-transform: uppercase;
        }
        table.datos td { padding: 6px 10px; border-bottom: 1px solid #ddd6c8; }
        table.datos tr:nth-child(even) td { background: rgba(0,0,0,.03); }

        /* navegacion */
        .flecha {
            position: fixed; top: 50%; transform: translateY(-50%);
            width: 46px; height: 76px; background: var(--rojo);
            border: 3px solid var(--tinta); color: #fff;
            font-size: 26px; font-weight: 900; cursor: pointer; z-index: 40;
            display: flex; align-items: center; justify-content: center;
        }
        .flecha:hover { background: var(--rojo-vivo); }
        .flecha.izq { left: 8px; }
        .flecha.der { right: 8px; }
        .flecha:disabled { opacity: .25; cursor: default; }

        .ayuda {
            position: fixed; bottom: 11px; left: 50%; transform: translateX(-50%);
            background: rgba(0,0,0,.72); color: #fff; font-size: 11px;
            padding: 5px 14px; border-radius: 13px; z-index: 40;
        }
        kbd {
            background: #fff; color: var(--tinta); border-radius: 3px;
            padding: 1px 5px; font-size: 10px; font-weight: 700;
            font-family: Montserrat, sans-serif;
        }

        .dos-col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; flex: 1; min-height: 0; }
        @media (max-width: 900px) { .dos-col { grid-template-columns: 1fr; } }
    </style>
</head>

<body>

<div class="barra">
    <div class="logo">
        <img src="<?= $BASE ?>/Imagenes/Logo-Comic-Huse.png" alt="Comic House">
        <span>COMIC&nbsp;HOUSE.<br><small style="font-weight:600;font-size:10px;opacity:.85">RENDIMIENTO MEDIBLE</small></span>
    </div>
    <div class="progreso" id="progreso"></div>
    <div class="contador" id="contador">1 / 12</div>
</div>

<div class="lienzo">
    <div class="pista" id="pista">

        <!-- ========== 1 · PORTADA ========== -->
        <section class="pagina">
            <div class="vineta" style="text-align:center;padding:40px 24px">
                <div class="rotulo">G11 · COMIC HOUSE · TEMA 7</div>
                <h1 style="font-size:46px">Rendimiento medible</h1>
                <p style="font-size:17px;max-width:660px;margin:0 auto 6px">
                    Cómo la subida de un capítulo pasó de <strong>206 ms a 2.9 ms</strong>
                    sin cambiar una sola imagen.
                </p>
                <div class="pow" style="margin-top:14px">¡71× MÁS RÁPIDO!</div>
            </div>

            <div class="vineta ancha">
                <div class="rotulo">ANTES DE EMPEZAR · COMPROBACIÓN EN VIVO</div>
                <p>Todo lo que verás se ejecuta <strong>de verdad</strong> en este computador.
                   Empecemos comprobando que el entorno está arriba.</p>
                <div class="consola">
                    <div class="cmd-fila" data-cmd="estado">
                        <span class="cmd-txt">SELECT VERSION(); SHOW TABLES;</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Ejecutar</button>
                    </div>
                    <div class="salida"><span class="vacio">Pulsa «Ejecutar» para correr el comando…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 2 · EL PROYECTO ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">01 · EL PROBLEMA</div>
                <h1>¿Qué guarda Comic House?</h1>
                <div class="globo">
                    Un cómic tiene capítulos. Un capítulo tiene páginas.
                    Cuando subes un <strong>CBR</strong>, el servidor lo descomprime y
                    registra <strong>una fila por página</strong>.
                </div>
                <p>El catálogo es pequeño, pero <strong>las páginas no</strong>: cada capítulo
                   añade entre 30 y 240 filas. Veamos el volumen real ahora mismo.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="conteo">
                        <span class="cmd-txt">SELECT COUNT(*) FROM comics, capitulos, paginas</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Ejecutar</button>
                    </div>
                    <div class="salida"><span class="vacio">Consulta la base de datos real…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 3 · EL CUELLO DE BOTELLA ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">02 · EL CUELLO DE BOTELLA</div>
                <h1>Un INSERT por página</h1>
                <p>El controlador insertaba <strong>dentro del bucle</strong> que recorre las
                   imágenes. Con 220 páginas: <strong>220 viajes</strong> a MariaDB, y como
                   mysqli va en <em>autocommit</em>, <strong>220 commits</strong> a disco.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="codigo_antes">
                        <span class="cmd-txt">sed -n '99,113p' procesar_capitulo.ANTES.php</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Ver código original</button>
                    </div>
                    <div class="salida"><span class="vacio">El archivo original está guardado en benchmark/antes/…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 4 · EL DIFF ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">03 · ANTES vs DESPUÉS</div>
                <h1>El cambio, línea por línea</h1>
                <p>Git conserva el código original. Este es el <strong>diff real</strong>
                   entre el commit anterior y el optimizado.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="diff">
                        <span class="cmd-txt">git diff 4d1fee7 1875069 -- controlador/procesar_capitulo.php</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Ver diff</button>
                    </div>
                    <div class="salida"><span class="vacio">Rojo = lo que se quitó · Verde = lo que entró…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 5 · LA MEDICIÓN ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">04 · LA EVIDENCIA</div>
                <h1>Medir, no suponer</h1>
                <div class="cifras">
                    <div class="cifra"><div class="n">206</div><div class="r">ms · antes</div></div>
                    <div class="cifra"><div class="n">2.9</div><div class="r">ms · después</div></div>
                    <div class="cifra"><div class="n">71×</div><div class="r">más rápido</div></div>
                </div>
                <p class="apunte apunta">Ojo al escenario intermedio: <strong>solo envolver en una
                   transacción ya da 12×</strong>. El costo no estaba en el INSERT, sino en
                   el commit por fila.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="benchmark">
                        <span class="cmd-txt">php benchmark.php 220 3</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Medir ahora (~40 s)</button>
                    </div>
                    <div class="salida"><span class="vacio">Inserta 220 páginas 3 veces con cada estrategia…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 6 · EXPLAIN ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">05 · PLAN DE CONSULTA</div>
                <h1>¿Qué hace el motor?</h1>
                <p>La consulta del lector es <em>«dame las páginas del capítulo N en orden»</em>.
                   Veamos qué plan elige MariaDB con los datos actuales.</p>
            </div>

            <div class="dos-col">
                <div class="vineta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="explain_antes">
                            <span class="cmd-txt">EXPLAIN SELECT … ORDER BY orden</span>
                            <span class="estado espera">sin ejecutar</span>
                            <button class="boton">Plan actual</button>
                        </div>
                        <div class="salida"><span class="vacio">Busca type y Extra…</span></div>
                    </div>
                </div>
                <div class="vineta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="explain_forzado">
                            <span class="cmd-txt">EXPLAIN … FORCE INDEX (idx_paginas_cap_orden)</span>
                            <span class="estado espera">sin ejecutar</span>
                            <button class="boton">Forzando índice</button>
                        </div>
                        <div class="salida"><span class="vacio">El mismo dato, otra estrategia…</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== 7 · POR QUÉ LO IGNORA ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">06 · EL HALLAZGO</div>
                <h1>El índice existe… y el motor lo ignora</h1>
                <div class="globo">
                    No es un error. El capítulo 1 es el <strong>24.8 % de la tabla</strong>.
                    Cuando vas a leer una cuarta parte de las filas, <strong>recorrerlas todas
                    sale más barato</strong> que saltar del índice a los datos una por una.
                </div>
                <p>El optimizador lo calcula y decide. Comprobémoslo con los datos reales.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="proporcion">
                        <span class="cmd-txt">SELECT capitulo_id, COUNT(*), % del total …</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Ver proporción</button>
                    </div>
                    <div class="salida"><span class="vacio">¿Cuánto pesa un capítulo dentro de la tabla?</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 8 · ESCALADO ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">07 · SI EL VOLUMEN CRECE</div>
                <h1>Dos curvas distintas</h1>
                <div class="cifras">
                    <div class="cifra"><div class="n">75 ms</div><div class="r">sin índice · 880k filas</div></div>
                    <div class="cifra"><div class="n">0.47</div><div class="r">ms con índice</div></div>
                    <div class="cifra"><div class="n">161×</div><div class="r">diferencia</div></div>
                </div>
                <p>Sin índice el tiempo crece con la tabla — <strong>O(n)</strong>. Con índice
                   se mantiene casi plano — <strong>O(log n)</strong>.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="escalado">
                        <span class="cmd-txt">php escalado.php</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Proyectar (~3 min)</button>
                    </div>
                    <div class="salida"><span class="vacio">Genera hasta 880.000 filas y compara. Tarda unos 3 minutos…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 9 · INTEGRIDAD ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">08 · INTEGRIDAD</div>
                <h1>Las reglas, dentro de la base</h1>
                <p>De nada sirve ser rápido si los datos se corrompen. Estas restricciones
                   impiden que entre lo que nunca debió entrar.</p>
            </div>

            <div class="dos-col">
                <div class="vineta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="restricciones">
                            <span class="cmd-txt">SHOW INDEX FROM paginas</span>
                            <span class="estado espera">sin ejecutar</span>
                            <button class="boton">Índices</button>
                        </div>
                        <div class="salida"><span class="vacio">…</span></div>
                    </div>
                </div>
                <div class="vineta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="fk">
                            <span class="cmd-txt">SELECT … KEY_COLUMN_USAGE</span>
                            <span class="estado espera">sin ejecutar</span>
                            <button class="boton">Claves foráneas</button>
                        </div>
                        <div class="salida"><span class="vacio">…</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== 10 · RECHAZO ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">09 · PREVENCIÓN</div>
                <h1>Intentemos romperla</h1>
                <div class="globo">
                    Vamos a insertar una página con <strong>orden = −5</strong>.
                    Si el error aparece, <strong>el sistema funciona</strong>.
                </div>
                <p class="apunte apunta">Aquí un error es la respuesta correcta: significa que la base
                   protege la regla sin depender de que el PHP esté bien escrito.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="rechazo">
                        <span class="cmd-txt">INSERT INTO paginas … VALUES (1, '…', -5, 0)</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Intentar romper</button>
                    </div>
                    <div class="salida"><span class="vacio">Debería aparecer un CHECK constraint failed…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 11 · BATERÍA ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">10 · VERIFICACIÓN COMPLETA</div>
                <h1>Siete pruebas sobre la base real</h1>
                <p>Orden correcto, UNIQUE, CHECK, ROLLBACK y borrado en cascada.
                   Se ejecuta contra el catálogo real y <strong>limpia todo</strong> al terminar.</p>
            </div>

            <div class="vineta ancha">
                <div class="consola">
                    <div class="cmd-fila" data-cmd="verificar">
                        <span class="cmd-txt">php verificar_controlador.php</span>
                        <span class="estado espera">sin ejecutar</span>
                        <button class="boton">Ejecutar las 7 pruebas</button>
                    </div>
                    <div class="salida"><span class="vacio">Resultado esperado: 7 correctas, 0 fallidas…</span></div>
                </div>
            </div>
        </section>

        <!-- ========== 12 · CIERRE ========== -->
        <section class="pagina">
            <div class="vineta">
                <div class="rotulo">11 · CONSULTAS DEL PROYECTO</div>
                <h1>Preguntas reales, no SELECT *</h1>
            </div>

            <div class="dos-col">
                <div class="vineta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="consulta2">
                            <span class="cmd-txt">Páginas por capítulo</span>
                            <span class="estado espera">sin ejecutar</span>
                            <button class="boton">Consultar</button>
                        </div>
                        <div class="salida"><span class="vacio">…</span></div>
                    </div>
                </div>
                <div class="vineta ancha">
                    <div class="consola">
                        <div class="cmd-fila" data-cmd="consulta3">
                            <span class="cmd-txt">Destacados aptos para menores</span>
                            <span class="estado espera">sin ejecutar</span>
                            <button class="boton">Consultar</button>
                        </div>
                        <div class="salida"><span class="vacio">…</span></div>
                    </div>
                </div>
            </div>

            <div class="vineta">
                <div class="pow" style="font-size:33px">FIN.</div>
                <p style="margin-top:9px">
                    <strong>Se midió, no se supuso.</strong> 71× en la carga, 161× en la lectura
                    proyectada, y 7 de 7 pruebas de integridad.
                </p>
            </div>
        </section>

    </div>
</div>

<button class="flecha izq" id="ant">‹</button>
<button class="flecha der" id="sig">›</button>
<div class="ayuda"><kbd>←</kbd> <kbd>→</kbd> pasar página · <kbd>Enter</kbd> ejecutar · <kbd>F</kbd> pantalla completa</div>

<script>
const pista   = document.getElementById('pista');
const paginas = document.querySelectorAll('.pagina');
const total   = paginas.length;
let actual = 0;

// ---------- barra de progreso ----------
const barra = document.getElementById('progreso');
for (let i = 0; i < total; i++) {
    const pip = document.createElement('div');
    pip.className = 'pip';
    pip.onclick = () => ir(i);
    barra.appendChild(pip);
}
const pips = barra.querySelectorAll('.pip');

function ir(n) {
    actual = Math.max(0, Math.min(total - 1, n));
    pista.style.transform = `translateX(-${actual * 100}%)`;
    document.getElementById('contador').textContent = `${actual + 1} / ${total}`;
    pips.forEach((p, i) => {
        p.classList.toggle('actual', i === actual);
        p.classList.toggle('visto', i < actual);
    });
    document.getElementById('ant').disabled = actual === 0;
    document.getElementById('sig').disabled = actual === total - 1;
}

document.getElementById('ant').onclick = () => ir(actual - 1);
document.getElementById('sig').onclick = () => ir(actual + 1);

document.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); ir(actual + 1); }
    if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   { e.preventDefault(); ir(actual - 1); }
    if (e.key === 'Home') ir(0);
    if (e.key === 'End')  ir(total - 1);
    if (e.key === 'f' || e.key === 'F') {
        document.fullscreenElement ? document.exitFullscreen()
                                   : document.documentElement.requestFullscreen();
    }
    if (e.key === 'Enter') {
        const btn = paginas[actual].querySelector('.cmd-fila button:not(:disabled)');
        if (btn) btn.click();
    }
});

// ---------- coloreado de salida ----------
function escapar(t) {
    return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function colorear(texto, tipo) {
    return escapar(texto).split('\n').map(l => {
        if (tipo === 'diff') {
            if (/^\+\+\+|^---|^diff |^index /.test(l)) return `<span class="ln-hdr">${l}</span>`;
            if (/^@@/.test(l))  return `<span class="ln-key">${l}</span>`;
            if (/^\+/.test(l))  return `<span class="ln-add">${l}</span>`;
            if (/^-/.test(l))   return `<span class="ln-del">${l}</span>`;
            return l;
        }
        if (/\[OK\]|✅|correctas/i.test(l))                      return `<span class="ln-ok">${l}</span>`;
        if (/\[FALLA\]|ERROR|failed|fallidas|denied/i.test(l))   return `<span class="ln-err">${l}</span>`;
        if (/SEARCH|USING INDEX|ref\b/.test(l))                  return `<span class="ln-ok">${l}</span>`;
        if (/\bALL\b|filesort|SCAN/.test(l))                     return `<span class="ln-num">${l}</span>`;
        if (/^=+$|^-+$|^\s*[A-ZÁÉÍÓÚ][A-ZÁÉÍÓÚ \·]+$/.test(l))   return `<span class="ln-hdr">${l}</span>`;
        if (/\d+\.\d+\s*(ms|x)|\d+x\b/.test(l))                  return `<span class="ln-num">${l}</span>`;
        return l;
    }).join('\n');
}

// ---------- ejecutar comandos ----------
document.querySelectorAll('.cmd-fila').forEach(fila => {
    const clave  = fila.dataset.cmd;
    const boton  = fila.querySelector('button');
    const estado = fila.querySelector('.estado');
    const salida = fila.parentElement.querySelector('.salida');

    boton.addEventListener('click', async () => {
        const rotulo = boton.textContent;
        boton.disabled = true;
        boton.textContent = 'Ejecutando…';
        estado.className = 'estado espera';
        estado.textContent = 'corriendo';
        salida.innerHTML = '<span class="vacio">Ejecutando en tu máquina…</span>';

        const t0 = performance.now();
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

            // Un INSERT rechazado es el resultado DESEADO en esa diapositiva
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
