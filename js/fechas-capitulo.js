/* Una fecha mensual conserva su precisión desde el formulario hasta la BD. */
function ajustarPrecisionFecha(limpiar = true) {
    const input = document.getElementById('fecha-display');
    const mensual = document.getElementById('fecha-precision').value === 'mes';
    input.placeholder = mensual ? 'mm / aaaa' : 'dd / mm / aaaa';
    input.maxLength = mensual ? 9 : 14;
    if (limpiar) {
        input.value = '';
        input.dataset.delWiki = '0';
    }
    maskFecha(input);
    if (typeof actualizarSelects === 'function') actualizarSelects();
}

function maskFecha(input) {
    const mensual = document.getElementById('fecha-precision')?.value === 'mes';
    const maximo = mensual ? 6 : 8;
    const digitos = input.value.replace(/\D/g, '').slice(0, maximo);
    const trozos = mensual
        ? [digitos.slice(0, 2), digitos.slice(2, 6)]
        : [digitos.slice(0, 2), digitos.slice(2, 4), digitos.slice(4, 8)];
    input.value = trozos.filter(Boolean).join(' / ');
    const diaOculto = document.getElementById('fecha-hidden');
    const mesOculto = document.getElementById('fecha-mes-hidden');
    const aviso = document.getElementById('fecha-aviso');
    diaOculto.value = '';
    mesOculto.value = '';
    input.classList.remove('date-ok', 'date-mal');
    input.setCustomValidity('');
    if (aviso) { aviso.textContent = ''; aviso.hidden = true; }
    if (!digitos) return;
    function error(mensaje, mostrar = true) {
        input.setCustomValidity(mensaje);
        input.classList.toggle('date-mal', mostrar);
        if (aviso && mostrar) { aviso.textContent = mensaje; aviso.hidden = false; }
    }
    if (digitos.length !== maximo) {
        error(mensual ? 'Completa el mes y el año.' : 'Completa el día, el mes y el año.', false);
        return;
    }
    const d = mensual ? 1 : Number(digitos.slice(0, 2));
    const m = Number(digitos.slice(mensual ? 0 : 2, mensual ? 2 : 4));
    const y = Number(digitos.slice(mensual ? 2 : 4));
    const fecha = new Date(y, m - 1, d);
    if (y < 1900 || fecha.getFullYear() !== y || fecha.getMonth() !== m - 1 || fecha.getDate() !== d) {
        error(mensual ? 'Revisa el mes y el año.' : 'Esa fecha no existe.');
        return;
    }
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    if (fecha > hoy) { error('La publicación no puede estar en el futuro.'); return; }
    const pad = n => String(n).padStart(2, '0');
    if (mensual) mesOculto.value = `${y}-${pad(m)}`;
    else diaOculto.value = `${y}-${pad(m)}-${pad(d)}`;
    input.classList.add('date-ok');
}

function mostrarFechaPublicacion(dia, mes) {
    return (dia || mes || '').split('-').reverse().join('/');
}

document.addEventListener('DOMContentLoaded', () => {
    const precision = document.getElementById('fecha-precision');
    if (precision) ajustarPrecisionFecha(false);
});
