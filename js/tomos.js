/* Propuestas de entregas. La identidad de la obra y la edición viajan juntas. */
let __tomosWiki = null;
let __turnoTomos = 0;
let __peticionTomos = null;

function cargarTomosWiki(titulo, edicion = '') {
    const turno = __turnoObra;
    const consulta = ++__turnoTomos;
    if (__peticionTomos) __peticionTomos.abort();
    __peticionTomos = new AbortController();
    __tomosWiki = null;
    const caja = document.getElementById('lista-tomos');
    caja.hidden = false;
    caja.innerHTML = '<p class="tomos-aviso" role="status">Buscando las entregas de esta obra…</p>';
    const obra = window.__obra || {};
    const parametros = new URLSearchParams({ titulo, editorial: obra.editorial || '',
        anio: obra.anio || '', tipo: obra.tipo || 'Comic', estado: obra.estado || '', edicion });
    return fetch('controlador/buscar_tomos_wiki.php?' + parametros,
                 { signal: __peticionTomos.signal })
        .then(r => r.json())
        .then(d => {
            if (turno !== __turnoObra || consulta !== __turnoTomos) return;
            if (!d.ok || !d.datos) {
                caja.innerHTML = '<p class="tomos-aviso" role="status">' +
                    esc(d.aviso || 'Puedes completar los datos a mano.') + '</p>';
                return;
            }
            __tomosWiki = d.datos;
            __tomosWiki.aviso = d.aviso || '';
            pintarTomos();
            aplicarTomo();
            document.dispatchEvent(new Event('entregas-actualizadas'));
        })
        .catch(err => {
            if (err.name === 'AbortError' || turno !== __turnoObra || consulta !== __turnoTomos) return;
            caja.innerHTML = '<p class="tomos-aviso" role="status">No se pudo consultar la fuente. Puedes escribir los datos a mano.</p>';
        });
}

function tomoDe(desde, hasta) {
    if (!__tomosWiki || __tomosWiki.requiere_edicion) return null;
    desde = Number(desde); hasta = Number(hasta || desde);
    if (!Number.isInteger(desde) || !Number.isInteger(hasta) || desde < 1 || hasta < desde || hasta > 9999) return null;
    const exacta = __tomosWiki.tomos.find(t => t.desde === desde && t.hasta === hasta);
    let entrega = exacta ? {...exacta} : null;
    if (!entrega && hasta > desde) {
        const numeros = new Map(__tomosWiki.tomos.filter(t => t.formato === 'grapa' && t.desde === t.hasta)
            .map(t => [Number(t.desde), t]));
        const partes = [];
        for (let n = desde; n <= hasta; n++) {
            if (!numeros.has(n)) return null; // No unir rangos con huecos ni tomos de manga.
            partes.push(numeros.get(n));
        }
        const nombreDe = t => (t.titulo || t.arco || t.recopilatorio?.titulo || '').trim();
        const titulos = [...new Set(partes.map(nombreDe).filter(Boolean))];
        const nombre = partes.every(t => nombreDe(t)) ? titulos.join(' · ') : '';
        const recopilatorios = partes.filter(t => !(t.titulo || t.arco || '').trim() && t.recopilatorio)
            .map(t => t.recopilatorio);
        entrega = {desde, hasta, tomo: desde, total: partes.length, formato: 'rango',
            titulo: nombre.length <= 255 ? nombre : '', arco: '',
            fecha: partes[0].fecha || '', fecha_mes: partes[0].fecha_mes || '',
            combinada: true, fecha_del_numero: desde};
        if (nombre && recopilatorios.length) {
            const propuesto = nombre + ' · números ' + desde + '–' + hasta;
            entrega.titulo = propuesto.length <= 255 ? propuesto : '';
            if (entrega.titulo) entrega.recopilatorios_usados = [...new Map(recopilatorios.map(c => [c.desde + ':' + c.hasta, c])).values()];
        }
    }
    if (entrega && !(entrega.titulo || entrega.arco || '').trim() && entrega.recopilatorio?.titulo) {
        const propuesto = entrega.recopilatorio.titulo + ' · número ' + desde;
        if (propuesto.length <= 255) {
            entrega.titulo = propuesto;
            entrega.recopilatorios_usados = [entrega.recopilatorio];
        }
    }
    if (entrega && !(entrega.titulo || entrega.arco || '').trim()) {
        const obra = String(window.__obra?.titulo || 'Entrega');
        entrega.titulo = (obra.length > 210 ? obra.slice(0, 207) + '…' : obra) +
            (desde === hasta ? ' · número ' + desde : ' · números ' + desde + '–' + hasta);
        entrega.titulo_generado = true;
    }
    return entrega;
}

function rotuloEntrega(t) {
    return t.formato === 'tomo' ? 'Tomo único'
        : (t.formato === 'grapa' ? 'Número ' : 'Tomo ') + t.tomo;
}

function rangoEntrega(t) {
    return Number(t.desde) === Number(t.hasta)
        ? 'Capítulo ' + t.desde : 'Capítulos ' + t.desde + '–' + t.hasta;
}

function marcarEntrega(t) {
    document.querySelectorAll('.fila-tomo').forEach(fila => {
        const elegida = !!t && (t.combinada
            ? Number(fila.dataset.d) >= t.desde && Number(fila.dataset.h) <= t.hasta
            : Number(fila.dataset.d) === Number(t.desde) && Number(fila.dataset.h) === Number(t.hasta));
        fila.classList.toggle('seleccionada', elegida);
        fila.setAttribute('aria-pressed', String(elegida));
        const icono = fila.querySelector('.ft-usar');
        if (icono) icono.className = 'bi ' + (elegida ? 'bi-check2' : 'bi-arrow-left-circle') + ' ft-usar';
    });
}

function aplicarTomo() {
    const cDesde = document.getElementById('cap-desde');
    const cHasta = document.getElementById('cap-hasta');
    const cTit = document.getElementById('cap-titulo');
    const cArco = document.getElementById('cap-arco');
    const cFecha = document.getElementById('fecha-display');
    const pista = document.getElementById('tomo-pista');
    if (!cDesde) return;
    const d = parseInt(cDesde.value, 10);
    const h = cHasta && cHasta.value ? parseInt(cHasta.value, 10) : null;
    const t = d ? tomoDe(d, h) : null;
    marcarEntrega(t);
    if (!t) {
        // Quita únicamente propuestas de una numeración anterior.
        ['cap-titulo', 'cap-arco', 'fecha-display'].forEach(id => {
            const campo = document.getElementById(id);
            if (campo.dataset.delWiki === '1') { campo.value = ''; campo.dataset.delWiki = '0'; }
        });
        if (!cFecha.value) maskFecha(cFecha);
        pista.hidden = true;
        return;
    }
    const titulo = t.titulo || t.arco || '';
    if (cTit && (!cTit.value.trim() || cTit.dataset.delWiki === '1')) {
        cTit.value = titulo;
        cTit.dataset.delWiki = '1';
        if (cArco) { cArco.value = t.arco || ''; cArco.dataset.delWiki = '1'; }
    }
    if (cFecha && (t.fecha || t.fecha_mes) && (!cFecha.value.trim() || cFecha.dataset.delWiki === '1')) {
        document.getElementById('fecha-precision').value = t.fecha ? 'dia' : 'mes';
        ajustarPrecisionFecha(false);
        cFecha.value = (t.fecha || t.fecha_mes).split('-').reverse().join(' / ');
        cFecha.dataset.delWiki = '1';
        maskFecha(cFecha);
    } else if (cFecha && cFecha.dataset.delWiki === '1' && !t.fecha && !t.fecha_mes) {
        cFecha.value = '';
        maskFecha(cFecha);
    }
    pista.hidden = false;
    const referencia = t.combinada ? rangoEntrega(t) : t.formato === 'tomo' ? 'Tomo único'
        : (Number(t.desde) === Number(t.hasta) ? rangoEntrega(t) : rotuloEntrega(t) + ' · ' + rangoEntrega(t));
    const fecha = mostrarFechaPublicacion(t.fecha, t.fecha_mes);
    const fuente = (__tomosWiki.fuente || '').split(' · ')[0];
    pista.innerHTML = '<i class="bi bi-info-circle sugerencia-icono" aria-hidden="true"></i>' +
        '<span class="sugerencia-datos"><span class="sugerencia-principal"><strong>' + esc(referencia) + '</strong>' +
        (fecha ? '<span class="sugerencia-fecha">' + esc(fecha) + '</span>' : '') + '</span>' +
        (fuente ? '<span class="sugerencia-fuente">Sugerido por ' + esc(fuente) + '</span>' : '') +
        (t.titulo_generado ? '<span class="sugerencia-nota">Nombre descriptivo propuesto, no un título publicado por la fuente. Puedes cambiarlo.</span>' : '') +
        (t.recopilatorios_usados ? '<span class="sugerencia-nota">Nombre del recopilatorio; no es un título individual. ' +
            t.recopilatorios_usados.map(c => esc(c.titulo) + ' reúne los números ' + c.desde + '–' + c.hasta +
                ' (' + esc(c.fuente || 'fuente de recopilatorios') + ')' +
                (c.titulo_original && c.titulo_original !== c.titulo ? '. Original: ' + esc(c.titulo_original) + '; traducción orientativa' : '')).join('; ') + '.</span>' : '') +
        (t.combinada ? '<span class="sugerencia-nota">Datos de ' + t.total + ' números; ' +
            ((t.fecha || t.fecha_mes) ? 'fecha del primero (#' + t.desde + ').' : 'sin fecha publicada para el primero.') + '</span>' : '') +
        (t.numeracion_normalizada ? '<span class="sugerencia-nota">Numeración en la fuente: ' + esc(t.numeracion_fuente) + '</span>' : '') + '</span>';
}

function pintarTomos() {
    const caja = document.getElementById('lista-tomos');
    if (!caja || !__tomosWiki) return;
    const capitulosListos = Array.isArray(window.__capitulos);
    const cubierto = new Set();
    (window.__capitulos || []).forEach(c => {
        const d = parseInt(c.numero_desde, 10), h = parseInt(c.numero_hasta, 10);
        if (!isNaN(d) && !isNaN(h)) for (let i = d; i <= h; i++) cubierto.add(i);
    });
    const faltan = (__tomosWiki.tomos || []).filter(t => {
        for (let i = t.desde; i <= t.hasta; i++) if (!cubierto.has(i)) return true;
        return false;
    });
    // Se muestran todos los números: un título ausente no borra una entrega.
    const propuestas = faltan;
    const opciones = __tomosWiki.ediciones || [];
    const seleccion = __tomosWiki.edicion?.id || '';
    const requiere = __tomosWiki.requiere_edicion;
    const fuente = (__tomosWiki.fuente || '').split(' · ')[0];
    const tieneGrapas = (__tomosWiki.tomos || []).some(t => t.formato === 'grapa');
    const nombreLista = tieneGrapas ? 'Números pendientes' : 'Tomos pendientes';
    const elegir = opciones.length > 1 || requiere;
    const selector = elegir ? '<div class="tomos-opciones"><label for="edicion-tomos">Edición de la fuente</label>' +
        '<select id="edicion-tomos" data-personalizado><option value="" disabled' + (!seleccion ? ' selected' : '') + '>Elegir edición</option>' +
        opciones.map(e => '<option value="' + esc(e.id) + '"' + (e.id === seleccion ? ' selected' : '') + '>' +
            esc([e.serie, e.anio || 'sin año', e.editorial, e.fuente].filter(Boolean).join(' · ')) +
            '</option>').join('') + '</select></div>' : '';
    const contenido = requiere
        ? '<p class="tomos-aviso">' + esc(__tomosWiki.aviso) + '</p>'
        : (!capitulosListos
            ? '<p class="tomos-aviso">' + (window.__errorCapitulos
                ? 'No se pudieron comprobar los capítulos subidos.' : 'Comprobando los capítulos subidos.') +
                ' Puedes completar el formulario a mano.</p>'
            : (!faltan.length ? '<p class="tomos-aviso">Todas las entregas de esta lista ya están subidas.</p>'
                : ''));
    caja.hidden = false;
    if (requiere) caja.classList.add('abierto');
    caja.innerHTML = '<button type="button" class="tomos-cab" id="tomos-cab" aria-expanded="' +
        caja.classList.contains('abierto') + '">' +
        '<i class="bi bi-chevron-right tomos-flecha"></i>' +
        '<span class="tomos-tit">' + (requiere ? 'Elegir edición' : nombreLista) + '</span>' +
        (!requiere && capitulosListos ? '<span class="tomos-cuenta">' + propuestas.length + '</span>' : '') +
        '<span class="tomos-fuente">' + esc(fuente) + '</span></button>' + selector +
        '<div class="tomos-cuerpo" id="tomos-cuerpo">' + contenido +
        (capitulosListos ? propuestas.map(t => {
            const solapa = Array.from({length: t.hasta - t.desde + 1}, (_, i) => t.desde + i)
                .some(n => cubierto.has(n));
            const fecha = mostrarFechaPublicacion(t.fecha, t.fecha_mes);
            const usaRecopilatorio = !(t.titulo || t.arco || '').trim() && !!t.recopilatorio?.titulo;
            const detalle = [Number(t.desde) !== Number(t.hasta) ? rangoEntrega(t) : '',
                usaRecopilatorio ? 'Recopilatorio · sin título individual' : '', fecha].filter(Boolean).join(' · ');
            return '<button type="button" class="fila-tomo' + (!(t.titulo || t.arco || t.recopilatorio?.titulo || '').trim() ? ' sin-titulo' : '') + '" data-d="' + t.desde +
                '" data-h="' + t.hasta + '"' + (solapa ? ' disabled title="Este rango se solapa con capítulos ya subidos"' : '') + '>' +
                '<span class="ft-num">' + rotuloEntrega(t) + '</span>' +
                '<span class="ft-centro"><span class="ft-arco">' +
                esc(t.titulo || t.arco || t.recopilatorio?.titulo || 'Sin título publicado') + '</span>' +
                '<span class="ft-meta">' + esc(detalle) +
                (t.numeracion_normalizada ? ' · fuente: ' + esc(t.numeracion_fuente) : '') +
                (solapa ? ' · rango parcialmente subido' : '') + '</span></span>' +
                '<i class="bi bi-arrow-left-circle ft-usar" aria-hidden="true"></i></button>';
        }).join('') : '') + '</div>';
    const selectorEdicion = document.getElementById('edicion-tomos');
    if (selectorEdicion) selectorEdicion.addEventListener('change', () => {
        ['cap-titulo', 'cap-arco', 'fecha-display'].forEach(id => {
            const campo = document.getElementById(id);
            if (campo.dataset.delWiki === '1') { campo.value = ''; campo.dataset.delWiki = '0'; }
        });
        maskFecha(document.getElementById('fecha-display'));
        document.getElementById('tomo-pista').hidden = true;
        cargarTomosWiki(window.__obra.titulo, selectorEdicion.value);
    });
    const cab = document.getElementById('tomos-cab');
    cab.addEventListener('click', () => {
        caja.classList.toggle('abierto');
        cab.setAttribute('aria-expanded', String(caja.classList.contains('abierto')));
    });
    caja.querySelectorAll('.fila-tomo:not(:disabled)').forEach(b => {
        b.addEventListener('click', () => usarTomo(+b.dataset.d, +b.dataset.h));
    });
    actualizarSelects(caja);
    marcarEntrega(tomoDe(Number(document.getElementById('cap-desde').value), Number(document.getElementById('cap-hasta').value) || null));
}

function usarTomo(d, h) {
    const sel = document.getElementById('cap-formato');
    const cDesde = document.getElementById('cap-desde');
    const cHasta = document.getElementById('cap-hasta');
    if (sel) {
        sel.value = tomoDe(d, h)?.formato || (h > d ? 'rango' : 'grapa');
        sel.dataset.tocado = '1';
        ajustarFormato();
    }
    cDesde.value = d;
    if (cHasta) cHasta.value = h > d ? h : '';
    aplicarTomo();
    proponerTitulo();
    cDesde.focus();
    document.dispatchEvent(new Event('entrega-elegida'));
}

['cap-desde', 'cap-hasta'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', aplicarTomo);
});
['cap-titulo', 'cap-arco', 'fecha-display'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => { el.dataset.delWiki = '0'; });
});
