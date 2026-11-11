const API_BASE = "/affitti/api";

const urlParameters = new URLSearchParams(window.location.search);
const propertyId = urlParameters.get("id");

const loadingContainer = document.querySelector("#loading");
const messageContainer = document.querySelector("#message");
const detailContainer = document.querySelector("#property-detail");

const initialElement = document.querySelector("#property-initial");
const locationElement = document.querySelector("#property-location");
const titleElement = document.querySelector("#property-title");
const addressElement = document.querySelector("#property-address");
const descriptionElement = document.querySelector(
    "#property-description"
);
const featuresElement = document.querySelector("#property-features");
const servicesElement = document.querySelector("#property-services");
const hostElement = document.querySelector("#property-host");
const priceElement = document.querySelector("#property-price");

const bookingForm = document.querySelector("#booking-form");
const arrivalInput = document.querySelector("#booking-arrival");
const departureInput = document.querySelector("#booking-departure");
const guestsInput = document.querySelector("#booking-guests");

const bookingSummary = document.querySelector("#booking-summary");
const summaryNights = document.querySelector("#summary-nights");
const summaryTotal = document.querySelector("#summary-total");
const bookingMessage = document.querySelector("#booking-message");

let currentProperty = null;

document.addEventListener("DOMContentLoaded", async () => {
    configuraDateMinime();

    if (!propertyId || !/^[1-9]\d*$/.test(propertyId)) {
        mostraErrorePagina("Identificativo dell'immobile non valido.");
        return;
    }

    await caricaDettaglio();
});

arrivalInput.addEventListener("change", () => {
    if (arrivalInput.value !== "") {
        departureInput.min = arrivalInput.value;

        if (
            departureInput.value !== ""
            && departureInput.value <= arrivalInput.value
        ) {
            departureInput.value = "";
        }
    }

    aggiornaRiepilogo();
});

departureInput.addEventListener("change", aggiornaRiepilogo);
guestsInput.addEventListener("input", aggiornaRiepilogo);

bookingForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    await creaPrenotazione();
});

function configuraDateMinime() {
    const today = new Date();
    const todayText = formattaDataInput(today);

    arrivalInput.min = todayText;
    departureInput.min = todayText;
}

function formattaDataInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

async function caricaDettaglio() {
    try {
        const response = await fetch(
            `${API_BASE}/immobili/dettaglio.php?id=${propertyId}`
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.errore || "Immobile non trovato."
            );
        }

        currentProperty = data.immobile;
        mostraDettaglio(currentProperty);
    } catch (error) {
        mostraErrorePagina(error.message);
    } finally {
        loadingContainer.classList.add("hidden");
    }
}

function mostraDettaglio(property) {
    document.title = `${property.titolo} - Affitti Brevi`;

    initialElement.textContent = property.localita
        .slice(0, 1)
        .toUpperCase();

    locationElement.textContent = property.localita;
    titleElement.textContent = property.titolo;
    addressElement.textContent = property.indirizzo;
    descriptionElement.textContent = property.descrizione;
    hostElement.textContent = property.host;

    const price = Number(property.prezzo_notte);

    priceElement.textContent = `${price.toFixed(2)} €`;

    guestsInput.max = String(property.posti_letto);
    guestsInput.placeholder =
        `Massimo ${property.posti_letto}`;

    featuresElement.innerHTML = "";

    featuresElement.append(
        creaCaratteristica(`${property.posti_letto} posti letto`),
        creaCaratteristica(`${property.camere} camere`),
        creaCaratteristica(`${property.bagni} bagni`)
    );

    mostraServizi(property.servizi);

    detailContainer.classList.remove("hidden");
}

function creaCaratteristica(text) {
    const element = document.createElement("span");

    element.className = "property-feature";
    element.textContent = text;

    return element;
}

function mostraServizi(services) {
    servicesElement.innerHTML = "";

    if (!Array.isArray(services) || services.length === 0) {
        const emptyElement = document.createElement("p");

        emptyElement.textContent =
            "Nessun servizio specificato per questo immobile.";

        servicesElement.appendChild(emptyElement);
        return;
    }

    for (const service of services) {
        const serviceElement = document.createElement("span");

        serviceElement.className = "service-item";
        serviceElement.textContent = service.nome;

        servicesElement.appendChild(serviceElement);
    }
}

function aggiornaRiepilogo() {
    const arrival = arrivalInput.value;
    const departure = departureInput.value;

    if (
        currentProperty === null
        || arrival === ""
        || departure === ""
        || departure <= arrival
    ) {
        bookingSummary.classList.add("hidden");
        return;
    }

    const arrivalDate = new Date(`${arrival}T00:00:00`);
    const departureDate = new Date(`${departure}T00:00:00`);

    const millisecondsPerDay = 24 * 60 * 60 * 1000;

    const nights = Math.round(
        (departureDate - arrivalDate) / millisecondsPerDay
    );

    if (nights < 1) {
        bookingSummary.classList.add("hidden");
        return;
    }

    const estimatedTotal =
        nights * Number(currentProperty.prezzo_notte);

    summaryNights.textContent = String(nights);
    summaryTotal.textContent = `${estimatedTotal.toFixed(2)} €`;

    bookingSummary.classList.remove("hidden");
}

async function creaPrenotazione() {
    nascondiMessaggioPrenotazione();

    if (currentProperty === null) {
        mostraMessaggioPrenotazione(
            "Impossibile recuperare i dati dell'immobile.",
            "error"
        );
        return;
    }

    const arrival = arrivalInput.value;
    const departure = departureInput.value;
    const guests = Number(guestsInput.value);

    if (arrival === "" || departure === "") {
        mostraMessaggioPrenotazione(
            "Indica data di arrivo e data di partenza.",
            "error"
        );
        return;
    }

    if (departure <= arrival) {
        mostraMessaggioPrenotazione(
            "La partenza deve essere successiva all'arrivo.",
            "error"
        );
        return;
    }

    if (
        !Number.isInteger(guests)
        || guests < 1
        || guests > Number(currentProperty.posti_letto)
    ) {
        mostraMessaggioPrenotazione(
            `Il numero di ospiti deve essere compreso tra 1 e ${currentProperty.posti_letto}.`,
            "error"
        );
        return;
    }

    const token = localStorage.getItem("access_token");

    if (!token) {
        mostraMessaggioPrenotazione(
            "Devi accedere come ospite prima di prenotare.",
            "error"
        );
        return;
    }

    const submitButton = bookingForm.querySelector(
        'button[type="submit"]'
    );

    submitButton.disabled = true;
    submitButton.textContent = "Prenotazione in corso...";

    try {
        const response = await fetch(
            `${API_BASE}/prenotazioni/`,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`,
                },
                body: JSON.stringify({
                    immobile_id: Number(propertyId),
                    data_arrivo: arrival,
                    data_partenza: departure,
                    numero_ospiti: guests,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 401) {
                localStorage.removeItem("access_token");
                localStorage.removeItem("utente");
            }

            throw new Error(
                data.errore || "Impossibile creare la prenotazione."
            );
        }

        mostraMessaggioPrenotazione(
            `Prenotazione creata. Totale: ${Number(
                data.prenotazione.totale
            ).toFixed(2)} €. Stato: in attesa.`,
            "success"
        );

        bookingForm.reset();
        configuraDateMinime();
        bookingSummary.classList.add("hidden");
    } catch (error) {
        mostraMessaggioPrenotazione(
            error.message,
            "error"
        );
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = "Prenota";
    }
}

function mostraErrorePagina(text) {
    messageContainer.textContent = text;
    messageContainer.className = "message error";

    loadingContainer.classList.add("hidden");
    detailContainer.classList.add("hidden");
}

function mostraMessaggioPrenotazione(text, type) {
    bookingMessage.textContent = text;
    bookingMessage.className = `message ${type}`;
}

function nascondiMessaggioPrenotazione() {
    bookingMessage.textContent = "";
    bookingMessage.className = "message hidden";
}