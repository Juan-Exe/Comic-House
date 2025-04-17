document.addEventListener("DOMContentLoaded", function () {
    document.querySelector(".search_button").addEventListener("click", function () {
        let busqueda = document.getElementById("buscar").value.trim();
        if (busqueda !== "") {
            consulta_buscador(busqueda, true);
        }
    });
});

function consulta_buscador(busqueda, redireccionar = false) {
    var dato = 'busca';
    var parametros = { "busqueda": busqueda, "dato": dato };

    $.ajax({
        data: parametros,
        url: '/Sexto_Semestre/Comics-House/auxiliar/codigo.php',  // Ruta absoluta
        type: 'POST',
        beforeSend: function () {
            console.log('ESTOY EN ELLO');
        },
        success: function (data) {
            console.log('TODO OK');
            let cardBusqueda = document.getElementById("card_busqueda");
            let resultadosBusqueda = document.getElementById("resultados_busqueda_nav");
    
            if (busqueda.trim() === '') {
                cardBusqueda.style.opacity = "0";
                cardBusqueda.style.visibility = "hidden";
            } else {
                cardBusqueda.style.opacity = "1";
                cardBusqueda.style.visibility = "visible";
            }
    
            resultadosBusqueda.innerHTML = data;
    
            if (redireccionar) {
                let primerResultado = resultadosBusqueda.querySelector("tr");
                if (primerResultado) {
                    let idComic = primerResultado.getAttribute("data-id");
                    if (idComic) {
                        window.location.href = `/Sexto_Semestre/Comics-House/Comics/comics.php?id=${idComic}`;
                    }
                }
            }
        },
        error: function (data, error) {
            console.log('ALGO VA MAL');
        }
    });
    
}
