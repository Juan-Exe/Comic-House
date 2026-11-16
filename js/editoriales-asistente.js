/* Búsqueda, revisión y guardado comunes al catálogo y al registro de cómics. */
(function () {
    function montar(form, opciones = {}) {
        const nombre = form.querySelector('[name="nombre"]');
        const archivo = form.querySelector('[name="imagen"]');
        const guardar = form.querySelector('button[type="submit"]');
        if (!nombre || !archivo || !guardar) return;
        const originalBoton = guardar.innerHTML;
        form.noValidate = true;
        const panel = document.createElement('div');
        panel.className = 'ed-asistente';
        panel.innerHTML = '<p class="ed-estado" role="status" aria-live="polite"></p>' +
            '<div class="ed-parecida" hidden></div><div class="ed-logos"></div><p class="ed-fuente"></p>' +
            '<button type="button" class="ed-descartar" hidden>Descartar propuesta y subir otra imagen</button>' +
            '<p class="ed-error" role="alert" hidden></p>';
        nombre.closest('.campo, .ed-campo')?.append(panel);
        const estado = panel.querySelector('.ed-estado'), logos = panel.querySelector('.ed-logos');
        const parecida = panel.querySelector('.ed-parecida'), fuente = panel.querySelector('.ed-fuente');
        const error = panel.querySelector('.ed-error'), descartar = panel.querySelector('.ed-descartar');
        let turno = 0, reloj, peticion, elegido = '', revision = null, confirmado = false, ocupada = false, destruido = false;
        let vistaManual = '';
        const aviso = texto => { error.textContent = texto; error.hidden = !texto; };
        const limpiarLogo = () => {
            elegido = ''; descartar.hidden = true;
            logos.querySelectorAll('button').forEach(b => { b.classList.remove('seleccionada'); b.setAttribute('aria-pressed','false'); });
        };
        function cambiarNombre() {
            if (ocupada) return;
            ++turno; clearTimeout(reloj); peticion?.abort();
            confirmado = false; revision = null; limpiarLogo(); aviso('');
            logos.replaceChildren(); fuente.replaceChildren(); parecida.hidden = true;
            guardar.innerHTML = originalBoton;
            estado.textContent = nombre.value.trim() ? 'Buscando la editorial y su logo…' : 'Escribe el nombre para buscar su logo.';
            if (nombre.value.trim().length >= 2) reloj = setTimeout(buscar, 450);
        }
        async function buscar() {
            clearTimeout(reloj);
            const consulta = ++turno;
            peticion?.abort(); peticion = new AbortController();
            const texto = nombre.value.trim();
            if (!texto) return;
            try {
                const r = await fetch('controlador/buscar_logo_editorial.php?' + new URLSearchParams({nombre:texto, confirmar:confirmado?'1':'0'}),
                    {signal:peticion.signal});
                const d = await r.json();
                if (consulta !== turno || destruido) return;
                if (!d.ok) throw new Error(d.aviso || 'No se pudo consultar el logo.');
                revision = d;
                logos.replaceChildren(); fuente.replaceChildren(); parecida.replaceChildren(); parecida.hidden = true;
                if (d.estado === 'existente') {
                    estado.textContent = '«' + d.editorial.nombre + '» ya está registrada. Se usará esa editorial sin duplicarla.';
                    guardar.textContent = 'Usar editorial existente';
                    return;
                }
                guardar.innerHTML = originalBoton;
                if (d.estado === 'parecida' && !confirmado) {
                    estado.textContent = 'Comprueba el nombre antes de crear otra editorial.';
                    parecida.hidden = false;
                    const texto = document.createElement('p');
                    texto.textContent = 'Se parece a «' + d.sugerencia.nombre + '». Si es la misma editorial, usa ese nombre; si comprobaste que es otra, conserva el que escribiste.';
                    const usar = document.createElement('button'); usar.type = 'button';
                    usar.textContent = d.sugerencia.id ? 'Es la misma: usar «' + d.sugerencia.nombre + '»' : 'Corregir a «' + d.sugerencia.nombre + '»';
                    usar.onclick = () => { nombre.value = d.sugerencia.nombre; cambiarNombre(); buscar(); };
                    const mantener = document.createElement('button'); mantener.type = 'button';
                    mantener.textContent = 'Es otra: conservar «' + nombre.value.trim() + '»';
                    mantener.onclick = () => { confirmado = true; parecida.hidden = true; estado.textContent = 'Nombre confirmado. Buscando su logo…'; buscar(); };
                    parecida.append(texto, usar, mantener);
                    return;
                }
                estado.textContent = d.logos.length ? 'Revisa las propuestas y elige el logo correcto. También puedes subir otra imagen.'
                    : 'No se encontró un logo verificable. Puedes subir una imagen manualmente.';
                d.logos.forEach(logo => {
                    let url;
                    try { url = new URL(logo.url); } catch (_) { return; }
                    if (url.protocol !== 'https:' || !['upload.wikimedia.org','thumb.wikimedia.org'].includes(url.hostname)) return;
                    const b = document.createElement('button'); b.type = 'button'; b.className = 'ed-propuesta'; b.disabled = true;
                    b.setAttribute('aria-pressed','false');
                    const img = document.createElement('img'); img.alt = 'Propuesta de logo: ' + nombre.value.trim();
                    const rotulo = document.createElement('span'); rotulo.textContent = logo.titulo.replace(/^File:/,'');
                    img.onload = () => { if (!destruido) b.disabled = ocupada; };
                    img.onerror = () => { rotulo.textContent = 'No se pudo mostrar esta propuesta. Sube una imagen manualmente.'; };
                    b.onclick = () => {
                        if (ocupada || !img.naturalWidth) return;
                        limpiarLogo(); elegido = logo.url;
                        archivo.value = '';
                        const previa = form.querySelector('.ed-manual-previa'); if (previa) previa.style.display = 'none';
                        form.querySelector('#icono-subir')?.style.setProperty('display','');
                        const textoManual = form.querySelector('#texto-subir'); if (textoManual) textoManual.textContent = 'Elegir otra imagen';
                        b.classList.add('seleccionada'); b.setAttribute('aria-pressed','true'); descartar.hidden = false;
                        estado.textContent = 'Logo seleccionado. Se guardará al confirmar la editorial.'; aviso('');
                        fuente.replaceChildren();
                        const enlace = document.createElement('a');
                        try {
                            const u = new URL(logo.fuente);
                            if (u.protocol === 'https:' && ['en.wikipedia.org','es.wikipedia.org','commons.wikimedia.org'].includes(u.hostname)) enlace.href = u.href;
                        } catch (_) {}
                        enlace.textContent = 'Ver la fuente en Wikipedia / Wikimedia'; enlace.target = '_blank'; enlace.rel = 'noopener noreferrer';
                        fuente.append(enlace);
                    };
                    img.src = logo.url; b.append(img, rotulo); logos.append(b);
                });
            } catch (e) {
                if (consulta !== turno || e.name === 'AbortError' || destruido) return;
                revision = null;
                estado.textContent = 'No se pudo consultar el logo. Puedes subir una imagen manualmente y continuar.';
            }
        }
        archivo.addEventListener('change', () => {
            limpiarLogo(); aviso('');
            const file = archivo.files[0];
            const previa = form.querySelector('.ed-manual-previa');
            if (vistaManual) URL.revokeObjectURL(vistaManual);
            if (file) {
                estado.textContent = 'Imagen manual elegida: ' + file.name + '. Revísala antes de guardar.';
                if (file.size > 5 * 1024 * 1024) { aviso('El logo admite hasta 5 MB.'); if (previa) previa.style.display = 'none'; return; }
                if (previa) { vistaManual = URL.createObjectURL(file); previa.src = vistaManual; previa.style.display = 'block'; }
                form.querySelector('#icono-subir')?.style.setProperty('display','none');
                const textoManual = form.querySelector('#texto-subir'); if (textoManual) textoManual.textContent = file.name;
            } else if (previa) previa.style.display = 'none';
        });
        descartar.onclick = () => {
            limpiarLogo(); fuente.replaceChildren();
            estado.textContent = 'Propuesta descartada. Elige un archivo para usar otra imagen.';
            archivo.click();
        };
        nombre.addEventListener('input', cambiarNombre);
        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (ocupada) return;
            if (!nombre.value.trim() || nombre.value.trim().length > 100) { aviso('Escribe un nombre de hasta 100 caracteres.'); nombre.focus(); return; }
            if (revision?.estado === 'parecida' && !confirmado) { aviso('Revisa la editorial parecida antes de continuar.'); return; }
            if (opciones.exigirLogo && revision?.estado !== 'existente' && !elegido && !archivo.files.length) {
                aviso('Elige una propuesta o sube una imagen para el logo.'); return;
            }
            const fd = new FormData(form);
            fd.set('logo_url', elegido); fd.set('confirmar', confirmado?'1':'0');
            fd.set('exigir_logo', opciones.exigirLogo?'1':'0');
            ocupada = true; guardar.disabled = true; guardar.textContent = 'Guardando…'; aviso('');
            try {
                const r = await fetch('controlador/crear_editorial.php', {method:'POST', body:fd});
                const d = await r.json();
                if (!d.ok) {
                    if (d.error === 'parecida') { revision = d; confirmado = false; buscar(); }
                    throw new Error(d.aviso || 'No se pudo guardar la editorial.');
                }
                opciones.alGuardar?.(d);
            } catch (e) { aviso(e.message || 'No se pudo guardar la editorial. Puedes reintentarlo.'); }
            finally {
                ocupada = false;
                if (!destruido) {
                    guardar.disabled = false; guardar.innerHTML = originalBoton;
                    logos.querySelectorAll('button').forEach(b=>{ b.disabled = !b.querySelector('img')?.naturalWidth; });
                }
            }
        });
        cambiarNombre();
        return {ocupada:()=>ocupada, destruir() { destruido = true; ++turno; clearTimeout(reloj); peticion?.abort(); if (vistaManual) URL.revokeObjectURL(vistaManual); }};
    }

    function seleccionarRegistro(d) {
        const sel = document.getElementById('f-editorial');
        if (!sel) return;
        let opcion = Array.from(sel.options).find(o => o.value === d.nombre);
        if (!opcion) { opcion = new Option(d.nombre, d.nombre); sel.append(opcion); }
        opcion.dataset.id = d.id;
        sel.value = d.nombre;
        sel.dispatchEvent(new Event('change', {bubbles:true}));
    }

    function abrir(opciones = {}) {
        const dialogo = document.createElement('dialog'); dialogo.className = 'editorial-dialogo';
        dialogo.setAttribute('aria-labelledby','ed-dialogo-titulo');
        dialogo.innerHTML = '<div class="ed-dialogo-cab"><h3 id="ed-dialogo-titulo">Registrar editorial</h3><button type="button" aria-label="Cerrar">×</button></div>' +
            '<p class="ed-ayuda">Revisa el nombre y el logo. Los datos del cómic se conservarán.</p>' +
            '<form enctype="multipart/form-data"><div class="ed-campo"><label for="ed-dialogo-nombre">Nombre de la editorial</label>' +
            '<input id="ed-dialogo-nombre" name="nombre" type="text" maxlength="100" required autocomplete="off"></div>' +
            '<div class="ed-campo"><label for="ed-dialogo-imagen">Subir otro logo (opcional)</label>' +
            '<input id="ed-dialogo-imagen" name="imagen" type="file" accept="image/png,image/jpeg,image/webp,image/gif">' +
            '<img class="ed-manual-previa" alt="Vista previa del logo elegido"></div>' +
            '<p class="ed-ayuda">PNG, JPG, WebP o GIF · hasta 5 MB. Puedes crearla sin logo y añadirlo después.</p>' +
            '<div class="ed-dialogo-pie"><button type="button" class="ed-cancelar">Cancelar</button><button type="submit">Guardar editorial</button></div></form>';
        const nombre = dialogo.querySelector('[name="nombre"]');
        nombre.value = opciones.nombre || ''; nombre.readOnly = !!opciones.fijo;
        document.body.append(dialogo);
        const foco = document.activeElement;
        let control;
        const cerrar = () => { if (control?.ocupada()) return; dialogo.close(); };
        const teclado = e => {
            if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); cerrar(); }
        };
        control = montar(dialogo.querySelector('form'), {alGuardar:d => {
            seleccionarRegistro(d); opciones.alGuardar?.(d);
            control.destruir(); dialogo.close();
        }});
        dialogo.querySelector('.ed-dialogo-cab button').onclick = cerrar;
        dialogo.querySelector('.ed-cancelar').onclick = cerrar;
        document.addEventListener('keydown',teclado,true);
        dialogo.addEventListener('cancel',e=>{e.preventDefault();cerrar();});
        dialogo.addEventListener('close',()=>{
            control?.destruir(); document.removeEventListener('keydown',teclado,true); dialogo.remove();
            if (foco?.isConnected) foco.focus({preventScroll:true});
            else document.getElementById('meta-usar')?.focus({preventScroll:true});
        },{once:true});
        dialogo.showModal();
    }
    window.EditorEditorial = {abrir, montar, seleccionarRegistro};
    document.addEventListener('DOMContentLoaded', () => {
        const alta = document.getElementById('alta-editorial');
        if (alta) montar(alta, {exigirLogo:true, alGuardar:d=>{
            location.href = 'editoriales.php?ok=' + (d.reutilizada?'reutilizada':'creada');
        }});
        document.getElementById('registro-crear-editorial')?.addEventListener('click',()=>abrir());
    });
})();
