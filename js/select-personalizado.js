/* Mejora los select existentes conservando sus valores, eventos y envío del formulario. */
(function () {
    let abierto = null;
    let consecutivo = 0;
    const instancias = new WeakMap();
    class Selector {
        constructor(origen) {
            this.origen = origen;
            this.id = 'opciones-panel-' + (++consecutivo);
            this.caja = document.createElement('div');
            this.caja.className = 'select-personalizado';
            origen.before(this.caja);
            this.caja.append(origen);
            origen.classList.add('select-origen');
            origen.tabIndex = -1;
            origen.setAttribute('aria-hidden', 'true');
            this.boton = document.createElement('button');
            this.boton.type = 'button';
            this.boton.className = 'select-disparador';
            this.boton.setAttribute('role', 'combobox');
            this.boton.setAttribute('aria-haspopup', 'listbox');
            this.boton.setAttribute('aria-expanded', 'false');
            this.boton.setAttribute('aria-controls', this.id);
            this.texto = document.createElement('span');
            const icono = document.createElement('i');
            icono.className = 'bi bi-chevron-down';
            icono.setAttribute('aria-hidden', 'true');
            this.boton.append(this.texto, icono);
            this.caja.append(this.boton);
            this.menu = document.createElement('div');
            this.menu.className = 'seleccion-lista';
            this.menu.id = this.id;
            this.menu.setAttribute('role', 'listbox');
            const etiqueta = origen.labels?.[0];
            if (etiqueta) {
                if (!etiqueta.id) etiqueta.id = this.id + '-etiqueta';
                this.boton.setAttribute('aria-labelledby', etiqueta.id);
                this.menu.setAttribute('aria-labelledby', etiqueta.id);
                etiqueta.addEventListener('click', e => { e.preventDefault(); this.boton.focus(); });
            } else if (origen.getAttribute('aria-label')) {
                this.boton.setAttribute('aria-label', origen.getAttribute('aria-label'));
                this.menu.setAttribute('aria-label', origen.getAttribute('aria-label'));
            }
            this.boton.addEventListener('click', () => abierto === this ? this.cerrar() : this.abrir());
            this.boton.addEventListener('keydown', e => this.tecla(e));
            origen.addEventListener('change', () => this.sincronizar());
            this.sincronizar();
        }
        sincronizar() {
            const opcion = this.origen.selectedOptions[0];
            this.texto.textContent = opcion?.dataset.resumen || opcion?.textContent || 'Elegir';
            this.boton.classList.toggle('sin-valor', !this.origen.value);
            this.opciones = Array.from(this.origen.options).filter(o => !o.hidden && !o.disabled);
            this.boton.disabled = this.origen.disabled || !this.opciones.length;
            this.activa = Math.max(0, this.opciones.findIndex(o => o.selected));
            if (abierto === this) this.pintar();
        }
        pintar() {
            this.menu.replaceChildren();
            this.opciones.forEach((opcion, i) => {
                const fila = document.createElement('div');
                fila.id = this.id + '-' + i;
                fila.className = 'seleccion-opcion';
                fila.setAttribute('role', 'option');
                fila.setAttribute('aria-selected', String(opcion.selected));
                fila.classList.toggle('activa', i === this.activa);
                const texto = document.createElement('span');
                texto.textContent = opcion.textContent;
                const marca = document.createElement('i');
                marca.className = 'bi bi-check2';
                marca.setAttribute('aria-hidden', 'true');
                fila.append(texto, marca);
                fila.addEventListener('mousedown', e => e.preventDefault());
                fila.addEventListener('click', () => this.elegir(i));
                this.menu.append(fila);
            });
            const activa = this.menu.children[this.activa];
            if (activa) {
                this.boton.setAttribute('aria-activedescendant', activa.id);
                activa.scrollIntoView({ block: 'nearest' });
            }
        }
        abrir() {
            if (this.boton.disabled) return;
            if (abierto) abierto.cerrar();
            abierto = this;
            this.sincronizar();
            document.body.append(this.menu);
            const r = this.boton.getBoundingClientRect();
            this.ancla = { top: r.top, left: r.left };
            const ancho = Math.min(Math.max(r.width, Number(this.origen.dataset.menuAncho) || 0), innerWidth - 24);
            const alto = Math.min(this.menu.scrollHeight, 260);
            const abajo = innerHeight - r.bottom - 12;
            this.menu.style.width = ancho + 'px';
            const izquierda = this.origen.dataset.menuAlinear === 'derecha' ? r.right - ancho : r.left;
            this.menu.style.left = Math.max(12, Math.min(izquierda, innerWidth - ancho - 12)) + 'px';
            this.menu.style.maxHeight = Math.max(80, Math.min(260, abajo >= alto ? abajo : r.top - 12)) + 'px';
            this.menu.style.top = (abajo >= alto ? r.bottom + 5 : Math.max(12, r.top - Math.min(alto, r.top - 12) - 5)) + 'px';
            this.boton.setAttribute('aria-expanded', 'true');
            this.boton.focus({ preventScroll: true });
            this.menu.children[this.activa]?.scrollIntoView({ block: 'nearest' });
        }
        cerrar() {
            this.menu.remove();
            this.boton.setAttribute('aria-expanded', 'false');
            this.boton.removeAttribute('aria-activedescendant');
            if (abierto === this) abierto = null;
        }
        elegir(i) {
            const opcion = this.opciones[i];
            if (!opcion) return;
            this.origen.value = opcion.value;
            this.cerrar();
            this.sincronizar();
            this.origen.dispatchEvent(new Event('change', { bubbles: true }));
        }
        tecla(e) {
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(e.key)) {
                e.preventDefault();
                if (abierto !== this) {
                    this.abrir();
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') return;
                }
                this.activa = e.key === 'Home' ? 0 : e.key === 'End' ? this.opciones.length - 1
                    : (this.activa + (e.key === 'ArrowDown' ? 1 : -1) + this.opciones.length) % this.opciones.length;
                this.pintar();
            } else if (e.key === 'Escape') { e.preventDefault(); this.cerrar(); }
            else if (e.key === 'Tab') this.cerrar();
            else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (abierto === this) this.elegir(this.activa); else this.abrir();
            } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
                if (abierto !== this) this.abrir();
                const indice = this.opciones.findIndex(o => o.textContent.trim().toLowerCase().startsWith(e.key.toLowerCase()));
                if (indice >= 0) { this.activa = indice; this.pintar(); }
            }
        }
    }
    window.actualizarSelects = function (raiz = document) {
        if (abierto && !abierto.origen.isConnected) abierto.cerrar();
        raiz.querySelectorAll('select[data-personalizado]').forEach(select => {
            if (!instancias.has(select)) instancias.set(select, new Selector(select));
            else instancias.get(select).sincronizar();
        });
    };
    document.addEventListener('pointerdown', e => {
        if (abierto && !abierto.caja.contains(e.target) && !abierto.menu.contains(e.target)) abierto.cerrar();
    });
    addEventListener('resize', () => abierto?.cerrar());
    addEventListener('scroll', e => {
        if (!abierto || abierto.menu.contains(e.target)) return;
        const r = abierto.boton.getBoundingClientRect();
        // Un evento pendiente del desplazamiento anterior al clic no debe cerrar
        // el menú que se acaba de abrir. Solo cierra si su campo se ha movido.
        if (Math.abs(r.top - abierto.ancla.top) > 1 || Math.abs(r.left - abierto.ancla.left) > 1) abierto.cerrar();
    }, true);
    document.addEventListener('DOMContentLoaded', () => actualizarSelects());
})();
