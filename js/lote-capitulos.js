/* Un formulario por entrega; una petición y una transacción por archivo. */
(function () {
    const $ = id => document.getElementById(id);
    const form = $('form-capitulo'), panel = $('lote-panel'), boton = $('subir-capitulos');
    if (!form || !panel) return;
    let filas = [], actual = null, ocupada = false, preparando = false, cargando = false;
    let version = 0, temporizador = null, consulta = null, revisando = false, errorRevision = '';
    let ultimoArchivo = null, nota = '';
    const idsCampos = ['cap-titulo', 'cap-formato', 'cap-desde', 'cap-hasta', 'cap-arco',
        'fecha-display', 'fecha-precision', 'fecha-hidden', 'fecha-mes-hidden'];
    const clave = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
    const seleccionada = () => filas.find(f => f.id === actual);
    const almacenamiento = () => 'comic-house-lote-v1:' + $('input-comic-id').value;
    const descriptor = file => ({name: file.name, size: file.size, lastModified: file.lastModified});
    const mismaFicha = (a, b) => a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;
    const firmaDatos = f => JSON.stringify([Object.keys(f.datos).sort().map(k => [k, f.datos[k]]),
        f.fechaTexto, f.precision, f.fechaError, f.auto.titulo, f.auto.arco, f.auto.fecha]);

    function leer() {
        return {datos: {titulo: $('cap-titulo').value.trim(), formato: $('cap-formato').value,
            numero_desde: $('cap-desde').value, numero_hasta: $('cap-hasta').value,
            arco: $('cap-arco').value.trim(), fecha_publicacion: $('fecha-hidden').value,
            fecha_publicacion_mes: $('fecha-mes-hidden').value},
            fechaTexto: $('fecha-display').value, precision: $('fecha-precision').value,
            fechaError: $('fecha-display').validationMessage,
            auto: {titulo: $('cap-titulo').dataset.delWiki === '1',
                arco: $('cap-arco').dataset.delWiki === '1', fecha: $('fecha-display').dataset.delWiki === '1'}};
    }

    function persistir() {
        if (!filas.length) return;
        try {
            // Los archivos no se guardan en el navegador: al recargar hay que volver a elegir los pendientes.
            sessionStorage.setItem(almacenamiento(), JSON.stringify(filas.map(({file, revision, ...fila}) => fila)));
        } catch (_) { /* El lote en memoria sigue funcionando si el navegador no permite almacenamiento. */ }
    }

    function guardar() {
        const fila = seleccionada();
        if (cargando || ocupada || !fila || fila.estado === 'subido') return false;
        const copia = leer();
        if (firmaDatos(copia) === firmaDatos(fila)) return false;
        Object.assign(fila, copia);
        fila.clave = clave();
        fila.estado = 'pendiente';
        fila.intentada = false;
        fila.error = '';
        fila.revision = null;
        persistir();
        return true;
    }

    function deducir(nombre) {
        const base = nombre.replace(/\.(cbr|cbz)$/i, '');
        const rango = base.match(/(?<!\d)(\d{1,4})\s*(?:-|a|to)\s*#?(\d{1,4})(?!\d)/i);
        if (rango && +rango[1] >= 1 && +rango[2] > +rango[1]) {
            return {formato: 'rango', numero_desde: String(+rango[1]), numero_hasta: String(+rango[2])};
        }
        const limpio = base.replace(/\(\s*(?:19|20)\d{2}\s*\)/g, ' ').replace(/\b\d{3,4}\s*x\s*\d{3,4}\b/gi, ' ');
        const numeros = [...limpio.matchAll(/(?<!\d)(\d{1,4})(?!\d)/g)].map(m => +m[1]).filter(n => n > 0);
        return {formato: 'grapa', numero_desde: numeros.length ? String(numeros.at(-1)) : '', numero_hasta: ''};
    }

    function proponer(fila) {
        if (fila.estado === 'subido' || fila.intentada) return;
        const datos = fila.datos;
        let tomo = null;
        if (__tomosWiki && !__tomosWiki.requiere_edicion) {
            const tomoNombre = fila.ficha.name.match(/\btomo\s*#?\s*(\d+)\b/i);
            if (fila.numeroAuto && tomoNombre) {
                tomo = __tomosWiki.tomos.find(t => Number(t.tomo) === +tomoNombre[1]);
                if (tomo) Object.assign(datos, {formato: tomo.formato,
                    numero_desde: String(tomo.desde), numero_hasta: tomo.hasta > tomo.desde ? String(tomo.hasta) : ''});
            }
            tomo = tomo || tomoDe(Number(datos.numero_desde),
                Number(datos.formato === 'rango' ? datos.numero_hasta : datos.numero_desde));
            if (!tomo && fila.numeroAuto && !datos.numero_desde && __tomosWiki.tomos.length === 1 &&
                __tomosWiki.tomos[0].formato === 'tomo') {
                tomo = __tomosWiki.tomos[0];
                Object.assign(datos, {formato: 'tomo', numero_desde: '1', numero_hasta: ''});
            }
        }
        const titulo = tomo?.titulo || tomo?.arco || (!__tomosWiki?.requiere_edicion
            ? __titulosWiki?.mapa[Number(datos.numero_desde)] : '') || '';
        fila.fuente = tomo ? __tomosWiki.fuente : (__titulosWiki?.fuente || '');
        fila.tituloGenerado = !!tomo?.titulo_generado;
        fila.nombreRecopilatorio = !!tomo?.recopilatorios_usados?.length;
        fila.combinada = !!tomo?.combinada;
        if (fila.auto.titulo) datos.titulo = titulo;
        if (fila.auto.arco) datos.arco = tomo?.arco || '';
        if (fila.auto.fecha) {
            datos.fecha_publicacion = tomo?.fecha || '';
            datos.fecha_publicacion_mes = datos.fecha_publicacion ? '' : (tomo?.fecha_mes || '');
            fila.precision = datos.fecha_publicacion_mes ? 'mes' : 'dia';
            fila.fechaTexto = mostrarFechaPublicacion(datos.fecha_publicacion, datos.fecha_publicacion_mes).replaceAll('/', ' / ');
            fila.fechaError = '';
        }
    }

    function nueva(file, inicial = null) {
        const fila = {id: clave(), clave: clave(), file, ficha: descriptor(file), estado: 'pendiente', error: '',
            datos: {titulo: '', arco: '', fecha_publicacion: '', fecha_publicacion_mes: '', ...deducir(file.name)},
            auto: {titulo: true, arco: true, fecha: true}, fechaTexto: '', fechaError: '', precision: 'dia', numeroAuto: true};
        if (inicial) {
            if (inicial.datos.numero_desde) {
                Object.assign(fila.datos, {formato: inicial.datos.formato, numero_desde: inicial.datos.numero_desde,
                    numero_hasta: inicial.datos.numero_hasta});
                fila.numeroAuto = false;
            }
            for (const campo of ['titulo', 'arco']) {
                if (inicial.datos[campo] && !inicial.auto[campo]) {
                    fila.datos[campo] = inicial.datos[campo]; fila.auto[campo] = false;
                }
            }
            if (inicial.fechaTexto && !inicial.auto.fecha) {
                Object.assign(fila, {precision: inicial.precision, fechaTexto: inicial.fechaTexto,
                    fechaError: inicial.fechaError});
                fila.datos.fecha_publicacion = inicial.datos.fecha_publicacion;
                fila.datos.fecha_publicacion_mes = inicial.datos.fecha_publicacion_mes;
                fila.auto.fecha = false;
            }
        }
        proponer(fila);
        return fila;
    }

    function editar(id, conservar = false) {
        if (!conservar) guardar();
        const fila = filas.find(f => f.id === id);
        if (!fila || fila.estado === 'subido' || ocupada) return;
        actual = id;
        cargando = true;
        idsCampos.forEach(campo => $(campo).disabled = false);
        const d = fila.datos;
        $('cap-formato').value = d.formato;
        $('cap-formato').dataset.tocado = '1';
        ajustarFormato();
        $('cap-desde').value = d.numero_desde;
        $('cap-hasta').value = d.numero_hasta;
        $('cap-titulo').value = d.titulo;
        $('cap-arco').value = d.arco;
        $('cap-titulo').dataset.delWiki = fila.auto.titulo ? '1' : '0';
        $('cap-arco').dataset.delWiki = fila.auto.arco ? '1' : '0';
        $('fecha-precision').value = fila.precision;
        ajustarPrecisionFecha(false);
        $('fecha-display').value = fila.fechaTexto;
        $('fecha-display').dataset.delWiki = fila.auto.fecha ? '1' : '0';
        maskFecha($('fecha-display'));
        $('num-pista').hidden = true;
        $('titulo-pista').hidden = true;
        $('tomo-pista').hidden = true;
        marcarEntrega(tomoDe(Number(d.numero_desde), Number(d.numero_hasta) || null));
        cargando = false;
        if (guardar()) programarRevision();
        else pintar();
    }

    const entrega = f => f.datos.formato === 'tomo' ? 'Tomo único' : (!f.datos.numero_desde ? 'Número por indicar'
        : f.datos.formato === 'rango' ? 'Capítulos ' + f.datos.numero_desde + '–' + (f.datos.numero_hasta || '?')
        : 'Capítulo ' + f.datos.numero_desde);
    const errores = f => [...(f.revision?.errores || []), ...(f.fechaError ? [f.fechaError] : []),
        ...(!f.file && f.estado !== 'subido' ? ['Vuelve a elegir este archivo para continuar.'] : [])];
    const lista = f => f.estado !== 'subido' && f.revision?.estado === 'listo' && !errores(f).length;

    function pintar() {
        panel.hidden = !filas.length;
        if (!filas.length) return;
        $('lote-lista').innerHTML = filas.map(f => {
            const problemas = errores(f), avisos = f.revision?.avisos || [];
            const estado = f.estado === 'subido' ? 'Subido' : f.estado === 'subiendo' ? 'Subiendo'
                : f.error ? 'Error' : problemas.length ? 'Revisar' : !f.revision ? 'Comprobando'
                : avisos.length ? 'Con avisos' : 'Listo';
            const mensajes = [...problemas, ...(f.error ? [f.error] : [])];
            return '<li class="lote-fila ' + (f.id === actual ? 'activa ' : '') + f.estado + '" data-id="' + f.id + '">' +
                '<button type="button" class="lote-elegir" aria-pressed="' + (f.id === actual) + '"' +
                (f.estado === 'subido' || ocupada ? ' disabled' : '') + '><span class="lote-entrega">' + esc(entrega(f)) +
                '</span><span class="lote-estado">' + estado + '</span><span class="lote-nombre">' + esc(f.ficha.name) + '</span>' +
                '<span class="lote-detalle">' + esc(f.datos.titulo || 'Título por completar') +
                (f.datos.fecha_publicacion || f.datos.fecha_publicacion_mes ? ' · ' + esc(mostrarFechaPublicacion(f.datos.fecha_publicacion, f.datos.fecha_publicacion_mes)) : '') + '</span>' +
                (f.fuente && (f.auto.titulo || f.auto.fecha) ? '<span class="lote-mensaje">Sugerido por ' + esc(f.fuente) + '</span>' : '') +
                (f.tituloGenerado && f.auto.titulo ? '<span class="lote-mensaje">Nombre descriptivo propuesto, no un título publicado.</span>' : '') +
                (f.nombreRecopilatorio && f.auto.titulo ? '<span class="lote-mensaje">Nombre del recopilatorio, no un título individual.</span>' : '') +
                (f.combinada && f.auto.fecha && (f.datos.fecha_publicacion || f.datos.fecha_publicacion_mes)
                    ? '<span class="lote-mensaje">Fecha del primer número incluido.</span>' : '') +
                mensajes.map(m => '<span class="lote-mensaje error">' + esc(m) + '</span>').join('') +
                avisos.map(a => '<span class="lote-mensaje">' + esc(a.mensaje) + '</span>').join('') + '</button>' +
                (f.estado !== 'subido' ? '<button type="button" class="lote-quitar" aria-label="Descartar ' + esc(f.ficha.name) + '"' +
                    (ocupada ? ' disabled' : '') + '><i class="bi bi-x-lg" aria-hidden="true"></i></button>' : '') + '</li>';
        }).join('');
        const subidos = filas.filter(f => f.estado === 'subido').length, listos = filas.filter(lista).length;
        form.classList.toggle('lote-terminado', subidos === filas.length);
        $('lote-resumen').textContent = [subidos + ' de ' + filas.length + ' subidos', listos + ' listos',
            filas.length - subidos - listos ? filas.length - subidos - listos + ' por revisar' : '',
            revisando ? 'Comprobando los datos…' : errorRevision, nota].filter(Boolean).join(' · ');
        $('lote-comprobar').hidden = !errorRevision;
        $('lote-cerrar').textContent = subidos === filas.length ? 'Cerrar lote' : 'Vaciar lista';
        $('lote-cerrar').disabled = ocupada;
        $('lote-comprobar').disabled = ocupada || revisando;
        const fila = seleccionada();
        const editable = !!fila && fila.estado !== 'subido';
        $('lote-edicion').hidden = !editable;
        if (fila) $('lote-actual').textContent = fila.ficha.name;
        idsCampos.forEach(campo => $(campo).disabled = !editable || ocupada);
        $('lote-reemplazar').disabled = ocupada;
        actualizarSelects();
        boton.disabled = ocupada || revisando || !!errorRevision || !listos;
        boton.innerHTML = '<i class="bi bi-cloud-upload"></i> ' +
            (subidos === filas.length ? 'Lote completado' :
                (subidos || filas.some(f => f.error) ? 'Continuar con ' : 'Subir ') + listos + ' archivo' + (listos === 1 ? '' : 's'));
        $('up-label').textContent = 'Añadir archivos al lote';
        $('up-label').style.display = '';
        $('up-count').style.display = 'none';
        $('archivo-cap').required = false;
        form.noValidate = true;
    }

    function solapes() {
        const pendientes = filas.filter(f => f.estado !== 'subido');
        for (let i = 0; i < pendientes.length; i++) {
            for (let j = i + 1; j < pendientes.length; j++) {
                const a = pendientes[i], b = pendientes[j];
                const rango = f => f.datos.formato === 'tomo' ? [1, 1] :
                    [+f.datos.numero_desde, +(f.datos.formato === 'rango' ? f.datos.numero_hasta : f.datos.numero_desde)];
                const [ad, ah] = rango(a), [bd, bh] = rango(b);
                if (ad > 0 && bd > 0 && ah >= ad && bh >= bd && ad <= bh && bd <= ah) {
                    for (const [fila, otra] of [[a, b], [b, a]]) {
                        if (fila.revision) fila.revision.errores.push('Se solapa con «' + otra.ficha.name + '» en este lote.');
                    }
                }
            }
        }
    }

    async function revisar() {
        clearTimeout(temporizador);
        const turno = ++version;
        if (consulta) consulta.abort();
        consulta = new AbortController();
        revisando = true; errorRevision = '';
        pintar();
        const pendientes = filas.filter(f => f.estado !== 'subido');
        try {
            const respuesta = await fetch('controlador/revisar_lote_capitulos.php', {method: 'POST',
                headers: {'Content-Type': 'application/json'}, signal: consulta.signal,
                body: JSON.stringify({comic_id: $('input-comic-id').value,
                    archivos: pendientes.map(f => ({id: f.id, clave: f.clave, nombre: f.ficha.name, tamano: f.ficha.size, datos: f.datos}))})});
            const d = await respuesta.json();
            if (turno !== version) return;
            if (!d.ok) throw new Error(d.mensaje || 'No se pudieron comprobar los archivos.');
            for (const resultado of d.archivos) {
                const fila = filas.find(f => f.id === resultado.id);
                if (!fila) continue;
                fila.revision = resultado;
                if (resultado.estado === 'subido') {
                    fila.estado = 'subido'; fila.error = ''; fila.paginas = resultado.paginas;
                }
            }
            solapes();
            persistir();
        } catch (e) {
            if (turno !== version || e.name === 'AbortError') return;
            errorRevision = 'No se pudo completar la revisión. Vuelve a comprobar antes de subir.';
        } finally {
            if (turno === version) { revisando = false; pintar(); }
        }
    }

    function programarRevision() {
        ++version;
        if (consulta) consulta.abort();
        clearTimeout(temporizador);
        filas.filter(f => f.estado !== 'subido').forEach(f => f.revision = null);
        revisando = true;
        pintar();
        temporizador = setTimeout(revisar, 220);
    }

    function recibir(input) {
        if (ocupada) return true;
        const archivos = Array.from(input.files || []);
        if (!filas.length && archivos.length < 2) { ultimoArchivo = archivos[0] || ultimoArchivo; return false; }
        if (!archivos.length) return true;
        guardar();
        const inicial = !filas.length ? leer() : null;
        if (!filas.length && ultimoArchivo && !archivos.some(f => mismaFicha(descriptor(f), descriptor(ultimoArchivo)))) {
            filas.push(nueva(ultimoArchivo, inicial));
        }
        for (const file of archivos) {
            const existente = filas.find(f => mismaFicha(f.ficha, descriptor(file)));
            if (existente) { existente.file = file; continue; }
            if (filas.length >= 200) { nota = 'El lote admite hasta 200 archivos; añade el resto después.'; break; }
            filas.push(nueva(file, !filas.length ? inicial : null));
        }
        input.value = '';
        $('upload-area').classList.remove('cargado');
        if (!actual || seleccionada()?.estado === 'subido') editar(filas.find(f => f.estado !== 'subido')?.id);
        persistir();
        programarRevision();
        return true;
    }

    function reiniciar() {
        ++version;
        clearTimeout(temporizador);
        consulta?.abort();
        filas = []; actual = null; ultimoArchivo = null; nota = ''; revisando = false; errorRevision = '';
        panel.hidden = true;
        form.classList.remove('lote-terminado');
        form.noValidate = false;
        form.dataset.enviando = '0';
        idsCampos.forEach(id => $(id).disabled = false);
        $('archivo-cap').required = true;
        $('archivo-cap').value = '';
        $('up-label').textContent = 'Elegir archivos .cbr o .cbz';
        $('up-label').style.display = '';
        $('up-count').style.display = 'none';
        boton.disabled = false;
        boton.innerHTML = '<i class="bi bi-cloud-upload"></i> Subir capítulo';
        actualizarSelects();
    }

    function vaciarEditor() {
        const comic = $('input-comic-id').value;
        form.reset();
        $('input-comic-id').value = comic;
        ['cap-titulo', 'cap-arco', 'fecha-display'].forEach(id => $(id).dataset.delWiki = '0');
        $('cap-formato').removeAttribute('data-tocado');
        $('upload-area').classList.remove('cargado');
        ajustarPrecisionFecha(false); ajustarFormato();
        $('tomo-pista').hidden = true; $('titulo-pista').hidden = true; $('num-pista').hidden = true;
    }

    function restaurar() {
        if (filas.length || !$('input-comic-id').value) return;
        try {
            const guardadas = JSON.parse(sessionStorage.getItem(almacenamiento()) || 'null');
            if (!Array.isArray(guardadas) || !guardadas.length || guardadas.length > 200) return;
            filas = guardadas.map(f => ({...f, file: null, estado: 'pendiente', revision: null}));
            nota = 'Lote recuperado. Vuelve a elegir los archivos pendientes; los ya guardados se comprobarán.';
            editar(filas[0].id);
            programarRevision();
        } catch (_) { reiniciar(); }
    }

    function confirmar(mensaje, tipo) {
        $('progress-overlay').classList.remove('visible');
        return new Promise(resolve => preguntarObra(mensaje, resolve, tipo === 'fuera_rango'));
    }

    function enviar(fila, confirmaciones, indice, total) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest(), fd = new FormData();
            fd.set('comic_id', $('input-comic-id').value);
            Object.entries(fila.datos).forEach(([k, v]) => fd.set(k, v));
            Object.entries(confirmaciones).forEach(([k, v]) => fd.set(k, v));
            fd.set('subida_clave', fila.clave);
            fd.set('cbr_file', fila.file, fila.ficha.name);
            $('progress-overlay').classList.add('visible');
            $('prog-titulo').textContent = 'Archivo ' + indice + ' de ' + total;
            $('prog-filename').textContent = fila.ficha.name;
            $('prog-fill').classList.remove('processing');
            $('prog-fill').style.width = '0%'; $('prog-pct').textContent = '0%';
            $('prog-status').textContent = 'Enviando archivo…';
            xhr.upload.onprogress = ev => {
                if (!ev.lengthComputable) return;
                const p = Math.round(ev.loaded / ev.total * 100);
                $('prog-fill').style.width = p + '%'; $('prog-pct').textContent = p + '%';
                if (p === 100) {
                    $('prog-fill').classList.add('processing');
                    $('prog-status').textContent = 'Comprobando y guardando las páginas…';
                }
            };
            xhr.onload = () => {
                try { resolve(JSON.parse(xhr.responseText)); }
                catch (_) { reject(new Error('No se recibió la confirmación. Se comprobará si el capítulo quedó guardado.')); }
            };
            xhr.onerror = () => reject(new Error('Se perdió la conexión. Se comprobará si el capítulo quedó guardado.'));
            xhr.ontimeout = () => reject(new Error('No se recibió la confirmación a tiempo. Vuelve a comprobar el lote.'));
            xhr.timeout = 360000;
            xhr.open('POST', 'controlador/procesar_capitulo.php');
            xhr.send(fd);
        });
    }

    async function subir() {
        if (ocupada || preparando) return;
        preparando = true;
        guardar();
        await revisar();
        preparando = false;
        if (errorRevision || revisando) return;
        const pendientes = filas.filter(lista);
        if (!pendientes.length) return;
        ocupada = true; nota = ''; form.dataset.enviando = '1';
        pintar();
        let pausa = false;
        try {
            for (const [i, fila] of pendientes.entries()) {
                const confirmaciones = {};
                for (const aviso of fila.revision.avisos) {
                    if (!await confirmar(aviso.mensaje, aviso.tipo)) { pausa = true; break; }
                    confirmaciones[aviso.tipo === 'fuera_rango' ? 'confirmar_rango' : 'confirmar_obra'] = '1';
                }
                if (pausa) break;
                fila.estado = 'subiendo'; fila.error = ''; fila.intentada = true; persistir(); pintar();
                try {
                    let respuesta;
                    // El servidor vuelve a validar aunque el catálogo cambie después de la revisión.
                    for (let intento = 0; intento < 3; intento++) {
                        respuesta = await enviar(fila, confirmaciones, i + 1, pendientes.length);
                        if (!['fuera_rango', 'posible_obra_distinta'].includes(respuesta.error)) break;
                        if (!await confirmar(respuesta.mensaje, respuesta.error)) { pausa = true; break; }
                        confirmaciones[respuesta.error === 'fuera_rango' ? 'confirmar_rango' : 'confirmar_obra'] = '1';
                    }
                    if (pausa) { fila.estado = 'pendiente'; break; }
                    if (!respuesta.ok) throw new Error(respuesta.mensaje || 'No se pudo guardar este archivo. Revisa sus datos.');
                    fila.estado = 'subido'; fila.paginas = respuesta.paginas;
                } catch (e) {
                    fila.estado = 'error'; fila.error = e.message;
                }
                persistir(); pintar();
            }
        } finally {
            $('progress-overlay').classList.remove('visible');
            $('prog-titulo').textContent = 'Subiendo capítulo';
            nota = pausa ? 'Lote en pausa. Los archivos subidos se conservan.' : 'Los archivos subidos se conservan. Puedes corregir y continuar con los pendientes.';
            await cargarCapitulos($('input-comic-id').value, window.__obra.titulo);
            await revisar();
            ocupada = false; form.dataset.enviando = '0';
            const pendiente = filas.find(f => f.estado !== 'subido');
            if (pendiente) editar(pendiente.id);
            persistir(); pintar();
        }
    }

    form.addEventListener('submit', e => {
        if (!filas.length) return;
        e.preventDefault(); e.stopImmediatePropagation();
        subir();
    }, true);
    ['input', 'change'].forEach(tipo => form.addEventListener(tipo, e => {
        if (!filas.length || cargando || ocupada || !idsCampos.includes(e.target.id)) return;
        if (['cap-desde', 'cap-hasta', 'cap-formato'].includes(e.target.id) && seleccionada()) seleccionada().numeroAuto = false;
        // Deja terminar la máscara de fecha, los select y las propuestas existentes.
        queueMicrotask(() => { if (guardar()) programarRevision(); });
    }));
    $('lote-lista').addEventListener('click', e => {
        if (ocupada) return;
        const li = e.target.closest('.lote-fila');
        if (!li) return;
        if (e.target.closest('.lote-quitar')) {
            guardar();
            filas = filas.filter(f => f.id !== li.dataset.id);
            if (!filas.length) { try { sessionStorage.removeItem(almacenamiento()); } catch (_) {} reiniciar(); vaciarEditor(); return; }
            if (actual === li.dataset.id) { actual = null; editar(filas.find(f => f.estado !== 'subido')?.id); }
            persistir(); programarRevision();
        } else editar(li.dataset.id);
    });
    $('lote-reemplazar').addEventListener('click', () => $('lote-archivo-nuevo').click());
    $('lote-archivo-nuevo').addEventListener('change', e => {
        const fila = seleccionada(), file = e.target.files[0];
        if (!fila || !file || ocupada) return;
        guardar();
        fila.file = file; fila.ficha = descriptor(file); fila.clave = clave();
        fila.estado = 'pendiente'; fila.error = ''; fila.revision = null; fila.intentada = false;
        e.target.value = ''; persistir(); programarRevision();
    });
    $('lote-comprobar').addEventListener('click', () => { guardar(); revisar(); });
    $('lote-cerrar').addEventListener('click', () => {
        if (ocupada) return;
        try { sessionStorage.removeItem(almacenamiento()); } catch (_) {}
        reiniciar(); vaciarEditor();
    });
    function actualizarPropuestas() {
        if (!filas.length || ocupada) return;
        if (seleccionada()?.intentada) editar(actual, true);
        else guardar();
        for (const fila of filas) {
            if (fila.estado === 'subido') continue;
            const antes = JSON.stringify(fila.datos);
            proponer(fila);
            if (antes !== JSON.stringify(fila.datos)) { fila.clave = clave(); fila.error = ''; }
        }
        if (actual) editar(actual);
        persistir(); programarRevision();
    }
    document.addEventListener('entregas-actualizadas', actualizarPropuestas);
    document.addEventListener('entrega-elegida', () => {
        if (!filas.length || ocupada) return;
        if (seleccionada()) seleccionada().numeroAuto = false;
        guardar(); programarRevision();
    });
    window.addEventListener('beforeunload', e => {
        if (ocupada) { e.preventDefault(); e.returnValue = ''; }
    });
    window.colaCapitulos = {recibir, reiniciar, restaurar, ocupada: () => ocupada || preparando};
    restaurar();
})();
