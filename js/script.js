document.addEventListener("DOMContentLoaded", function () {
    document.querySelector(".search_button").addEventListener("click", function () {
        let busqueda = document.getElementById("buscar").value.trim();
        if (busqueda !== "") {
            consulta_buscador(busqueda, true);
        }
    });

    // Cerrar dropdown cuando se limpia el input con la X nativa del browser
    let inputBuscar = document.getElementById("buscar");
    if (inputBuscar) {
        inputBuscar.addEventListener("search", function () {
            if (this.value.trim() === "") {
                let cardBusqueda = document.getElementById("card_busqueda");
                if (cardBusqueda) {
                    cardBusqueda.style.opacity = "0";
                    cardBusqueda.style.visibility = "hidden";
                }
            }
        });

        inputBuscar.addEventListener("input", function () {
            if (this.value.trim() === "") {
                let cardBusqueda = document.getElementById("card_busqueda");
                if (cardBusqueda) {
                    cardBusqueda.style.opacity = "0";
                    cardBusqueda.style.visibility = "hidden";
                }
            }
        });
    }
});

function consulta_buscador(busqueda, redireccionar = false) {
    var dato = 'busca';
    var parametros = { "busqueda": busqueda, "dato": dato };

    $.ajax({
        data: parametros,
        url: '/auxiliar/codigo.php',
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
                        window.location.href = `/Comics/comics.php?id=${idComic}`;
                    }
                }
            }
        },
        error: function (data, error) {
            console.log('ALGO VA MAL');
        }
    });
    
}
