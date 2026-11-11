const API_BASE = "/affitti/api";

const searchForm = document.querySelector("#search-form");
const localitaSelect = document.querySelector("#localita");
const dataArrivoInput = document.querySelector("#data-arrivo");
const dataPartenzaInput = document.querySelector("#data-partenza");
const resetButton = document.querySelector("#reset-button");
const resultsContainer = document.querySelector("#results");
const resultsCount = document.querySelector("#results-count");
const messageContainer = document.querySelector("#message");
const loadingContainer = document.querySelector("#loading");

document.addEventListener("DOMContentLoaded", async () => {
    configuraDateMinime();
    await caricaLocalita();
    await cercaImmobili();
});

searchForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!validaDate()) {
        return;
    }

    await cercaImmobili();
});

resetButton.addEventListener("click", async () => {
    searchForm.reset();
    nascondiMessaggio();
    configuraDateMinime();
    await cercaImmobili();
});

dataArrivoInput.addEventListener("change", () => {
    if (dataArrivoInput.value !== "") {
        dataPartenzaInput.min = dataArrivoInput.value;

        if (
            dataPartenzaInput.value !== ""
            && dataPartenzaInput.value <= dataArrivoInput.value
        ) {
            dataPartenzaInput.value = "";
        }
    }
});

function configuraDateMinime() {
    const oggi = new Date();
    const oggiTesto = formattaDataInput(oggi);

    dataArrivoInput.min = oggiTesto;
    dataPartenzaInput.min = oggiTesto;
}

function formattaDataInput(data) {
    const anno = data.getFullYear();
    const mese = String(data.getMonth() + 1).padStart(2, "0");
    const giorno = String(data.getDate()).padStart(2, "0");

    return `${anno}-${mese}-${giorno}`;
}

async function caricaLocalita() {
    try {
        const response = await fetch(`${API_BASE}/localita/`);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.errore || "Impossibile caricare le località."
            );
        }

        for (const localita of data.localita) {
            const option = document.createElement("option");

            option.value = String(localita.id);
            option.textContent = localita.nome;

            localitaSelect.appendChild(option);
        }
    } catch (error) {
        mostraMessaggio(error.message, "error");
    }
}

function validaDate() {
    const dataArrivo = dataArrivoInput.value;
    const dataPartenza = dataPartenzaInput.value;

    if (
        (dataArrivo === "" && dataPartenza !== "")
        || (dataArrivo !== "" && dataPartenza === "")
    ) {
        mostraMessaggio(
            "Data di arrivo e data di partenza devono essere indicate insieme.",
            "error"
        );

        return false;
    }

    if (
        dataArrivo !== ""
        && dataPartenza !== ""
        && dataPartenza <= dataArrivo
    ) {
        mostraMessaggio(
            "La data di partenza deve essere successiva alla data di arrivo.",
            "error"
        );

        return false;
    }

    nascondiMessaggio();

    return true;
}

function creaParametriRicerca() {
    const formData = new FormData(searchForm);
    const parameters = new URLSearchParams();

    for (const [name, value] of formData.entries()) {
        const cleanValue = String(value).trim();

        if (cleanValue !== "") {
            parameters.set(name, cleanValue);
        }
    }

    return parameters;
}

async function cercaImmobili() {
    mostraCaricamento(true);
    nascondiMessaggio();
    resultsContainer.innerHTML = "";
    resultsCount.textContent = "Ricerca in corso...";

    const parameters = creaParametriRicerca();
    const url = `${API_BASE}/immobili/?${parameters.toString()}`;

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.errore || "Errore durante la ricerca."
            );
        }

        mostraImmobili(data.immobili);
    } catch (error) {
        resultsCount.textContent = "Ricerca non completata";
        mostraMessaggio(error.message, "error");
    } finally {
        mostraCaricamento(false);
    }
}

function mostraImmobili(immobili) {
    resultsContainer.innerHTML = "";

    const numeroRisultati = immobili.length;

    resultsCount.textContent =
        numeroRisultati === 1
            ? "1 alloggio trovato"
            : `${numeroRisultati} alloggi trovati`;

    if (numeroRisultati === 0) {
        const emptyState = document.createElement("div");

        emptyState.className = "empty-state";
        emptyState.textContent =
            "Nessun alloggio soddisfa i criteri inseriti.";

        resultsContainer.appendChild(emptyState);

        return;
    }

    for (const immobile of immobili) {
        resultsContainer.appendChild(
            creaSchedaImmobile(immobile)
        );
    }
}

function creaSchedaImmobile(immobile) {
    const card = document.createElement("article");
    card.className = "property-card";

    const placeholder = document.createElement("div");
    placeholder.className = "property-placeholder";
    placeholder.textContent = immobile.localita
        .slice(0, 1)
        .toUpperCase();

    const content = document.createElement("div");
    content.className = "property-content";

    const location = document.createElement("p");
    location.className = "property-location";
    location.textContent = immobile.localita;

    const title = document.createElement("h3");
    title.className = "property-title";
    title.textContent = immobile.titolo;

    const description = document.createElement("p");
    description.className = "property-description";
    description.textContent = immobile.descrizione;

    const features = document.createElement("div");
    features.className = "property-features";

    features.appendChild(
        creaCaratteristica(`${immobile.posti_letto} posti letto`)
    );

    features.appendChild(
        creaCaratteristica(`${immobile.camere} camere`)
    );

    features.appendChild(
        creaCaratteristica(`${immobile.bagni} bagni`)
    );

    const footer = document.createElement("div");
    footer.className = "property-footer";

    const price = document.createElement("p");
    price.className = "property-price";

    const numericPrice = Number(immobile.prezzo_notte);

    price.textContent = `${numericPrice.toFixed(2)} € `;

    const priceSuffix = document.createElement("span");
    priceSuffix.textContent = "/ notte";
    price.appendChild(priceSuffix);

    const detailsLink = document.createElement("a");
    detailsLink.className = "details-button";
    detailsLink.href = `dettaglio.html?id=${immobile.id}`;
    detailsLink.textContent = "Dettagli";

    footer.append(price, detailsLink);

    content.append(
        location,
        title,
        description,
        features,
        footer
    );

    card.append(placeholder, content);

    return card;
}

function creaCaratteristica(text) {
    const element = document.createElement("span");

    element.className = "property-feature";
    element.textContent = text;

    return element;
}

function mostraMessaggio(text, type) {
    messageContainer.textContent = text;
    messageContainer.className = `message ${type}`;
}

function nascondiMessaggio() {
    messageContainer.textContent = "";
    messageContainer.className = "message hidden";
}

function mostraCaricamento(show) {
    loadingContainer.classList.toggle("hidden", !show);
}