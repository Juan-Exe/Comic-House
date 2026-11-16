(function () {
    const form = document.getElementById('form-reemplazo');
    if (!form) return;
    const archivo = document.getElementById('archivo-reemplazo');
    const estado = document.getElementById('reemplazo-estado');
    const dialogo = document.getElementById('dialogo-reemplazo');
    const revisar = form.querySelector('button[type=submit]');
    const confirmar = document.getElementById('confirmar-reemplazo');
    let clave = '', enviando = false, confirmarObra = false;
    const mensaje = (texto, error = false) => { estado.textContent = texto; estado.classList.toggle('error',error); };
    const cerrar = () => { if (!enviando) { confirmarObra = false; dialogo.close(); } };
    document.getElementById('cancelar-reemplazo').onclick = cerrar;
    dialogo.addEventListener('cancel',e => { e.preventDefault(); cerrar(); });
    archivo.addEventListener('change',() => { clave = ''; confirmarObra = false; mensaje(''); });
    form.addEventListener('submit',e => {
        e.preventDefault();
        const file = archivo.files[0];
        if (enviando || !file) return;
        if (!/\.(cbr|cbz)$/i.test(file.name) || !file.size || file.size > 512 * 1024 * 1024) {
            mensaje('Elige un CBR o CBZ de hasta 512 MB que no esté vacío.',true); return;
        }
        document.getElementById('reemplazo-resumen').textContent = 'Nuevo archivo: «' + file.name + '». Se sustituirán las ' + form.dataset.paginas + ' páginas actuales.';
        dialogo.showModal();
    });
    confirmar.onclick = async () => {
        if (enviando) return;
        if (!clave) clave = Array.from(crypto.getRandomValues(new Uint8Array(16)),n => n.toString(16).padStart(2,'0')).join('');
        const datos = new FormData(form);
        datos.set('capitulo_id',form.dataset.capitulo); datos.set('version',form.dataset.version);
        datos.set('clave',clave); datos.set('confirmar_reemplazo','1');
        datos.set('confirmar_obra',confirmarObra?'1':'0');
        enviando = true; confirmar.disabled = revisar.disabled = archivo.disabled = true;
        dialogo.close(); mensaje('Validando el archivo y reemplazando las páginas…');
        try {
            const r = await fetch('controlador/reemplazar_capitulo.php',{method:'POST',body:datos});
            const d = await r.json();
            if (d.error === 'posible_obra_distinta') {
                confirmarObra = true;
                document.getElementById('reemplazo-resumen').textContent = d.mensaje + ' Es una sospecha por el nombre del archivo. Si comprobaste que corresponde, confirma el reemplazo.';
                dialogo.showModal(); mensaje('Revisa el nombre del archivo antes de continuar.'); return;
            }
            if (!d.ok) throw new Error(d.mensaje || 'No se pudo completar el reemplazo.');
            location.href = 'editar_capitulo.php?id=' + form.dataset.capitulo + '&reemplazado=1';
        } catch (e) {
            mensaje(e instanceof TypeError || e instanceof SyntaxError
                ? 'No se recibió la respuesta. Reintenta con el mismo archivo; se comprobará si ya se completó.'
                : (e.message || 'No se pudo completar el reemplazo.'),true);
        } finally { enviando = false; confirmar.disabled = revisar.disabled = archivo.disabled = false; }
    };
})();
