const API_BASE = "/affitti/api";
let immobiliHost = [];
let modalitaModulo = "creazione";

const token = localStorage.getItem("access_token");
const user = leggiUtenteSalvato();

const hostDescription = document.querySelector(
    "#host-description"
);

const messageContainer = document.querySelector("#message");
const loadingContainer = document.querySelector("#loading");

const propertiesSection = document.querySelector(
    "#properties-section"
);

const bookingsSection = document.querySelector(
    "#bookings-section"
);

const propertyFormSection = document.querySelector(
    "#property-form-section"
);

const propertiesContainer = document.querySelector(
    "#properties-container"
);

const bookingsContainer = document.querySelector(
    "#host-bookings-container"
);

const propertiesCount = document.querySelector(
    "#properties-count"
);

const bookingsCount = document.querySelector(
    "#bookings-count"
);

const showPropertiesButton = document.querySelector(
    "#show-properties-button"
);

const showBookingsButton = document.querySelector(
    "#show-bookings-button"
);

const newPropertyButton = document.querySelector(
    "#new-property-button"
);

const closePropertyFormButton = document.querySelector(
    "#close-property-form-button"
);

const logoutButton = document.querySelector("#logout-button");

const propertyForm = document.querySelector(
    "#property-form"
);

const propertyFormTitle = document.querySelector(
    "#property-form-title"
);

const propertyFormMessage = document.querySelector(
    "#property-form-message"
);

const propertyIdInput = document.querySelector(
    "#property-id"
);

const propertyLocationSelect = document.querySelector(
    "#property-location"
);

const propertyTitleInput = document.querySelector(
    "#property-title"
);

const propertyDescriptionInput = document.querySelector(
    "#property-description"
);

const propertyAddressInput = document.querySelector(
    "#property-address"
);

const propertyBedsInput = document.querySelector(
    "#property-beds"
);

const propertyRoomsInput = document.querySelector(
    "#property-rooms"
);

const propertyBathroomsInput = document.querySelector(
    "#property-bathrooms"
);

const propertyPriceInput = document.querySelector(
    "#property-price"
);

const cancelPropertyFormButton = document.querySelector(
    "#cancel-property-form-button"
);

const savePropertyButton = document.querySelector(
    "#save-property-button"
);

const servicesSection = document.querySelector(
    "#services-section"
);

const servicesTitle = document.querySelector(
    "#services-title"
);

const servicesMessage = document.querySelector(
    "#services-message"
);

const servicesLoading = document.querySelector(
    "#services-loading"
);

const servicesForm = document.querySelector(
    "#services-form"
);

const servicesPropertyIdInput = document.querySelector(
    "#services-property-id"
);

const servicesContainer = document.querySelector(
    "#services-container"
);

const closeServicesButton = document.querySelector(
    "#close-services-button"
);

const cancelServicesButton = document.querySelector(
    "#cancel-services-button"
);

const saveServicesButton = document.querySelector(
    "#save-services-button"
);

document.addEventListener("DOMContentLoaded", async () => {
    if (!token || !user) {
        window.location.href = "login.html";
        return;
    }

    if (user.ruolo !== "host") {
        window.location.href = "prenotazioni.html";
        return;
    }

    hostDescription.textContent =
        `Account di ${user.nome} - ${user.email}`;

    await caricaImmobili();
});

showPropertiesButton.addEventListener("click", async () => {
    mostraSezione("properties");
    await caricaImmobili();
});

showBookingsButton.addEventListener("click", async () => {
    mostraSezione("bookings");
    await caricaPrenotazioni();
});

newPropertyButton.addEventListener("click", async () => {
    preparaModuloNuovoImmobile();
    mostraSezione("form");
    await caricaLocalitaNelModulo();
});

closePropertyFormButton.addEventListener("click", () => {
    propertyForm.reset();
    propertyIdInput.value = "";
    modalitaModulo = "creazione";

    nascondiMessaggioModulo();
    mostraSezione("properties");
});

cancelPropertyFormButton.addEventListener("click", () => {
    propertyForm.reset();
    propertyIdInput.value = "";
    modalitaModulo = "creazione";

    nascondiMessaggioModulo();
    mostraSezione("properties");
});

propertyForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    await salvaImmobile();
});

logoutButton.addEventListener("click", async () => {
    await eseguiLogout();
});

function leggiUtenteSalvato() {
    const savedUser = localStorage.getItem("utente");

    if (!savedUser) {
        return null;
    }

    try {
        return JSON.parse(savedUser);
    } catch {
        pulisciAutenticazione();
        return null;
    }
}

function mostraSezione(sectionName) {
    propertiesSection.classList.toggle(
        "hidden",
        sectionName !== "properties"
    );

    bookingsSection.classList.toggle(
        "hidden",
        sectionName !== "bookings"
    );

    propertyFormSection.classList.toggle(
        "hidden",
        sectionName !== "form"
    );

    servicesSection.classList.toggle(
        "hidden",
        sectionName !== "services"
    );

    nascondiMessaggio();
}

async function caricaImmobili() {
    mostraCaricamento(true);
    nascondiMessaggio();
    propertiesContainer.innerHTML = "";

    try {
        const response = await fetch(
            `${API_BASE}/host/immobili.php`,
            {
                headers: {
                    "Authorization": `Bearer ${token}`,
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            gestisciErroreAutenticazione(response);

            throw new Error(
                data.errore
                || "Impossibile caricare gli immobili."
            );
        }

        mostraImmobili(data.immobili);
    } catch (error) {
        mostraMessaggio(error.message, "error");
    } finally {
        mostraCaricamento(false);
    }
}

function mostraImmobili(properties) {
    immobiliHost = properties;
    propertiesContainer.innerHTML = "";

    propertiesCount.textContent =
        properties.length === 1
            ? "1 immobile"
            : `${properties.length} immobili`;

    if (properties.length === 0) {
        const emptyState = document.createElement("div");

        emptyState.className = "empty-state";
        emptyState.textContent =
            "Non hai ancora pubblicato immobili.";

        propertiesContainer.appendChild(emptyState);
        return;
    }

    for (const property of properties) {
        propertiesContainer.appendChild(
            creaSchedaImmobileHost(property)
        );
    }
}

function creaSchedaImmobileHost(property) {
    const card = document.createElement("article");
    card.className = "host-property-card";

    if (!Boolean(Number(property.attivo))) {
        card.classList.add("host-property-disabled");
    }

    const header = document.createElement("div");
    header.className = "host-property-header";

    const titleGroup = document.createElement("div");

    const location = document.createElement("p");
    location.className = "property-location";
    location.textContent = property.localita;

    const title = document.createElement("h3");
    title.textContent = property.titolo;

    titleGroup.append(location, title);

    const status = document.createElement("span");

    if (Boolean(Number(property.attivo))) {
        status.className = "status-badge status-confermata";
        status.textContent = "Attivo";
    } else {
        status.className = "status-badge status-annullata";
        status.textContent = "Disattivato";
    }

    header.append(titleGroup, status);

    const description = document.createElement("p");
    description.className = "property-description";
    description.textContent = property.descrizione;

    const features = document.createElement("div");
    features.className = "property-features";

    features.append(
        creaCaratteristica(
            `${property.posti_letto} posti letto`
        ),
        creaCaratteristica(`${property.camere} camere`),
        creaCaratteristica(`${property.bagni} bagni`),
        creaCaratteristica(
            `${Number(property.prezzo_notte).toFixed(2)} € / notte`
        )
    );

    const actions = document.createElement("div");
    actions.className = "host-property-actions";

    const publicLink = document.createElement("a");
    publicLink.className = "secondary-button";
    publicLink.href = `dettaglio.html?id=${property.id}`;
    publicLink.textContent = "Vedi dettaglio";

    if (!Boolean(Number(property.attivo))) {
        publicLink.classList.add("hidden");
    }

    const editButton = document.createElement("button");
    editButton.type = "button";
    editButton.className = "secondary-button";
    editButton.textContent = "Modifica";

    editButton.addEventListener("click", async () => {
    await preparaModuloModificaImmobile(property);
    });

    const servicesButton = document.createElement("button");
    servicesButton.type = "button";
    servicesButton.className = "secondary-button";
    servicesButton.textContent = "Servizi";

    servicesButton.addEventListener("click", async () => {
    mostraSezione("services");

    await caricaServiziImmobile(
        property.id,
        property.titolo
    );
});

    actions.append(
        publicLink,
        editButton,
        servicesButton
    );

    if (Boolean(Number(property.attivo))) {
        const deactivateButton = document.createElement("button");

        deactivateButton.type = "button";
        deactivateButton.className = "danger-button";
        deactivateButton.textContent = "Disattiva";

        deactivateButton.addEventListener("click", async () => {
            await disattivaImmobile(
                property.id,
                property.titolo,
                deactivateButton
            );
        });

        actions.appendChild(deactivateButton);
    }

    card.append(header, description, features, actions);

    return card;
}

function creaCaratteristica(text) {
    const element = document.createElement("span");

    element.className = "property-feature";
    element.textContent = text;

    return element;
}

async function disattivaImmobile(
    propertyId,
    propertyTitle,
    button
) {
    const confirmed = window.confirm(
        `Vuoi disattivare "${propertyTitle}"?`
    );

    if (!confirmed) {
        return;
    }

    button.disabled = true;
    button.textContent = "Disattivazione...";

    try {
        const response = await fetch(
            `${API_BASE}/host/disattiva-immobile.php`,
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`,
                },
                body: JSON.stringify({
                    immobile_id: Number(propertyId),
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            gestisciErroreAutenticazione(response);

            throw new Error(
                data.errore
                || "Impossibile disattivare l'immobile."
            );
        }

        mostraMessaggio(
            "Immobile disattivato correttamente.",
            "success"
        );

        await caricaImmobili();
    } catch (error) {
        mostraMessaggio(error.message, "error");

        button.disabled = false;
        button.textContent = "Disattiva";
    }
}

async function caricaPrenotazioni() {
    mostraCaricamento(true);
    nascondiMessaggio();
    bookingsContainer.innerHTML = "";

    try {
        const response = await fetch(
            `${API_BASE}/host/prenotazioni.php`,
            {
                headers: {
                    "Authorization": `Bearer ${token}`,
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            gestisciErroreAutenticazione(response);

            throw new Error(
                data.errore
                || "Impossibile caricare le prenotazioni."
            );
        }

        mostraPrenotazioni(data.prenotazioni);
    } catch (error) {
        mostraMessaggio(error.message, "error");
    } finally {
        mostraCaricamento(false);
    }
}

function mostraPrenotazioni(bookings) {
    bookingsContainer.innerHTML = "";

    bookingsCount.textContent =
        bookings.length === 1
            ? "1 prenotazione"
            : `${bookings.length} prenotazioni`;

    if (bookings.length === 0) {
        const emptyState = document.createElement("div");

        emptyState.className = "empty-state";
        emptyState.textContent =
            "Non hai ancora ricevuto prenotazioni.";

        bookingsContainer.appendChild(emptyState);
        return;
    }

    for (const booking of bookings) {
        bookingsContainer.appendChild(
            creaSchedaPrenotazioneHost(booking)
        );
    }
}

function creaSchedaPrenotazioneHost(booking) {
    const card = document.createElement("article");
    card.className = "booking-list-card";

    const header = document.createElement("div");
    header.className = "booking-list-header";

    const titleGroup = document.createElement("div");

    const location = document.createElement("p");
    location.className = "property-location";
    location.textContent = booking.localita;

    const title = document.createElement("h2");
    title.textContent = booking.titolo_immobile;

    const guest = document.createElement("p");
    guest.className = "guest-information";
    guest.textContent =
        `${booking.nome_ospite} - ${booking.email_ospite}`;

    titleGroup.append(location, title, guest);

    const status = document.createElement("span");
    status.className =
        `status-badge status-${booking.stato}`;
    status.textContent = formatoStato(booking.stato);

    header.append(titleGroup, status);

    const details = document.createElement("div");
    details.className = "booking-details-grid";

    details.append(
        creaDettaglio(
            "Arrivo",
            formattaData(booking.data_arrivo)
        ),
        creaDettaglio(
            "Partenza",
            formattaData(booking.data_partenza)
        ),
        creaDettaglio(
            "Notti",
            String(booking.numero_notti)
        ),
        creaDettaglio(
            "Ospiti",
            String(booking.numero_ospiti)
        ),
        creaDettaglio(
            "Totale",
            `${Number(booking.totale).toFixed(2)} €`
        )
    );

    const footer = document.createElement("div");
    footer.className = "booking-list-footer";

    if (booking.stato === "in_attesa") {
        const confirmButton = document.createElement("button");

        confirmButton.type = "button";
        confirmButton.className = "primary-button";
        confirmButton.textContent = "Conferma";

        confirmButton.addEventListener("click", async () => {
            await aggiornaStatoPrenotazione(
                booking.id,
                "confermata",
                confirmButton
            );
        });

        closeServicesButton.addEventListener("click", () => {
    pulisciPannelloServizi();
    mostraSezione("properties");
});

cancelServicesButton.addEventListener("click", () => {
    pulisciPannelloServizi();
    mostraSezione("properties");
});

servicesForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    await salvaServiziImmobile();
});

        const cancelButton = document.createElement("button");

        cancelButton.type = "button";
        cancelButton.className = "danger-button";
        cancelButton.textContent = "Rifiuta";

        cancelButton.addEventListener("click", async () => {
            await aggiornaStatoPrenotazione(
                booking.id,
                "annullata",
                cancelButton
            );
        });

        footer.append(confirmButton, cancelButton);
    }

    card.append(header, details);

    if (footer.children.length > 0) {
        card.appendChild(footer);
    }

    return card;
}

function creaDettaglio(labelText, valueText) {
    const element = document.createElement("div");
    element.className = "booking-detail";

    const label = document.createElement("span");
    label.textContent = labelText;

    const value = document.createElement("strong");
    value.textContent = valueText;

    element.append(label, value);

    return element;
}

async function aggiornaStatoPrenotazione(
    bookingId,
    newStatus,
    button
) {
    const actionText =
        newStatus === "confermata"
            ? "confermare"
            : "rifiutare";

    const confirmed = window.confirm(
        `Vuoi ${actionText} questa prenotazione?`
    );

    if (!confirmed) {
        return;
    }

    button.disabled = true;
    button.textContent = "Aggiornamento...";

    try {
        const response = await fetch(
            `${API_BASE}/host/stato-prenotazione.php`,
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`,
                },
                body: JSON.stringify({
                    prenotazione_id: Number(bookingId),
                    stato: newStatus,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            gestisciErroreAutenticazione(response);

            throw new Error(
                data.errore
                || "Impossibile aggiornare la prenotazione."
            );
        }

        mostraMessaggio(
            "Stato della prenotazione aggiornato.",
            "success"
        );

        await caricaPrenotazioni();
    } catch (error) {
        mostraMessaggio(error.message, "error");

        button.disabled = false;

        button.textContent =
            newStatus === "confermata"
                ? "Conferma"
                : "Rifiuta";
    }
}

async function eseguiLogout() {
    logoutButton.disabled = true;
    logoutButton.textContent = "Uscita...";

    try {
        await fetch(
            `${API_BASE}/auth/logout.php`,
            {
                method: "POST",
                headers: {
                    "Authorization": `Bearer ${token}`,
                },
            }
        );
    } finally {
        pulisciAutenticazione();
        window.location.href = "index.html";
    }
}

function gestisciErroreAutenticazione(response) {
    if (response.status === 401) {
        pulisciAutenticazione();
        window.location.href = "login.html";
    }

    if (response.status === 403) {
        window.location.href = "prenotazioni.html";
    }
}

function pulisciAutenticazione() {
    localStorage.removeItem("access_token");
    localStorage.removeItem("utente");
    localStorage.removeItem("scadenza_token");
}

function formatoStato(status) {
    const labels = {
        in_attesa: "In attesa",
        confermata: "Confermata",
        annullata: "Annullata",
    };

    return labels[status] || status;
}

function formattaData(dateText) {
    const parts = dateText.split("-");

    if (parts.length !== 3) {
        return dateText;
    }

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function mostraMessaggio(text, type) {
    messageContainer.textContent = text;
    messageContainer.className = `message ${type}`;

    window.scrollTo({
        top: 0,
        behavior: "smooth",
    });
}

function nascondiMessaggio() {
    messageContainer.textContent = "";
    messageContainer.className = "message hidden";
}

function mostraCaricamento(show) {
    loadingContainer.classList.toggle("hidden", !show);
}

function preparaModuloNuovoImmobile() {
    modalitaModulo = "creazione";

    propertyForm.reset();
    propertyIdInput.value = "";

    propertyFormTitle.textContent =
        "Pubblica un immobile";

    savePropertyButton.textContent =
        "Pubblica immobile";

    nascondiMessaggioModulo();
}

async function preparaModuloModificaImmobile(property) {
    modalitaModulo = "modifica";

    propertyForm.reset();
    nascondiMessaggioModulo();

    propertyIdInput.value = String(property.id);

    propertyFormTitle.textContent =
        "Modifica immobile";

    savePropertyButton.textContent =
        "Salva modifiche";

    mostraSezione("form");

    await caricaLocalitaNelModulo();

    propertyLocationSelect.value =
        String(property.localita_id);

    propertyTitleInput.value =
        property.titolo;

    propertyDescriptionInput.value =
        property.descrizione;

    propertyAddressInput.value =
        property.indirizzo;

    propertyBedsInput.value =
        String(property.posti_letto);

    propertyRoomsInput.value =
        String(property.camere);

    propertyBathroomsInput.value =
        String(property.bagni);

    propertyPriceInput.value =
        Number(property.prezzo_notte).toFixed(2);

    window.scrollTo({
        top: 0,
        behavior: "smooth",
    });
}

async function caricaLocalitaNelModulo() {
    const selectedValue = propertyLocationSelect.value;

    propertyLocationSelect.innerHTML = "";

    const initialOption = document.createElement("option");
    initialOption.value = "";
    initialOption.textContent = "Seleziona una località";

    propertyLocationSelect.appendChild(initialOption);

    try {
        const response = await fetch(
            `${API_BASE}/localita/`
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.errore
                || "Impossibile caricare le località."
            );
        }

        for (const location of data.localita) {
            const option = document.createElement("option");

            option.value = String(location.id);
            option.textContent = location.nome;

            propertyLocationSelect.appendChild(option);
        }

        if (selectedValue !== "") {
            propertyLocationSelect.value = selectedValue;
        }
    } catch (error) {
        mostraMessaggioModulo(
            error.message,
            "error"
        );
    }
}

async function salvaImmobile() {
    nascondiMessaggioModulo();

    const propertyId = Number(
        propertyIdInput.value
    );

    const locationId = Number(
        propertyLocationSelect.value
    );

    const title = propertyTitleInput.value.trim();

    const description =
        propertyDescriptionInput.value.trim();

    const address =
        propertyAddressInput.value.trim();

    const beds = Number(propertyBedsInput.value);
    const rooms = Number(propertyRoomsInput.value);

    const bathrooms = Number(
        propertyBathroomsInput.value
    );

    const price = Number(propertyPriceInput.value);

    if (!Number.isInteger(locationId) || locationId < 1) {
        mostraMessaggioModulo(
            "Seleziona una località.",
            "error"
        );
        return;
    }

    if (
        title === ""
        || description === ""
        || address === ""
    ) {
        mostraMessaggioModulo(
            "Titolo, descrizione e indirizzo sono obbligatori.",
            "error"
        );
        return;
    }

    if (title.length > 150) {
        mostraMessaggioModulo(
            "Il titolo non può superare 150 caratteri.",
            "error"
        );
        return;
    }

    if (address.length > 200) {
        mostraMessaggioModulo(
            "L'indirizzo non può superare 200 caratteri.",
            "error"
        );
        return;
    }

    if (
        !Number.isInteger(beds)
        || beds < 1
        || !Number.isInteger(rooms)
        || rooms < 1
        || !Number.isInteger(bathrooms)
        || bathrooms < 1
    ) {
        mostraMessaggioModulo(
            "Posti letto, camere e bagni devono essere interi positivi.",
            "error"
        );
        return;
    }

    if (!Number.isFinite(price) || price <= 0) {
        mostraMessaggioModulo(
            "Il prezzo per notte deve essere maggiore di zero.",
            "error"
        );
        return;
    }

    const isModification =
        modalitaModulo === "modifica";

    if (
        isModification
        && (!Number.isInteger(propertyId) || propertyId < 1)
    ) {
        mostraMessaggioModulo(
            "Identificativo dell'immobile non valido.",
            "error"
        );
        return;
    }

    const endpoint = isModification
        ? `${API_BASE}/host/modifica-immobile.php`
        : `${API_BASE}/host/immobili.php`;

    const method = isModification
        ? "PATCH"
        : "POST";

    const requestBody = {
        localita_id: locationId,
        titolo: title,
        descrizione: description,
        indirizzo: address,
        posti_letto: beds,
        camere: rooms,
        bagni: bathrooms,
        prezzo_notte: price,
    };

    if (isModification) {
        requestBody.immobile_id = propertyId;
    }

    savePropertyButton.disabled = true;

    savePropertyButton.textContent = isModification
        ? "Salvataggio modifiche..."
        : "Pubblicazione in corso...";

    try {
        const response = await fetch(
            endpoint,
            {
                method: method,
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`,
                },
                body: JSON.stringify(requestBody),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            gestisciErroreAutenticazione(response);

            throw new Error(
                data.errore
                || (
                    isModification
                        ? "Impossibile modificare l'immobile."
                        : "Impossibile pubblicare l'immobile."
                )
            );
        }

        propertyForm.reset();
        propertyIdInput.value = "";

        mostraSezione("properties");

        mostraMessaggio(
            isModification
                ? `Immobile "${data.immobile.titolo}" modificato correttamente.`
                : `Immobile "${data.immobile.titolo}" pubblicato correttamente.`,
            "success"
        );

        await caricaImmobili();
    } catch (error) {
        mostraMessaggioModulo(
            error.message,
            "error"
        );
    } finally {
        savePropertyButton.disabled = false;

        savePropertyButton.textContent =
            modalitaModulo === "modifica"
                ? "Salva modifiche"
                : "Pubblica immobile";
    }
}

function mostraMessaggioModulo(text, type) {
    propertyFormMessage.textContent = text;
    propertyFormMessage.className =
        `message ${type}`;
}

function nascondiMessaggioModulo() {
    propertyFormMessage.textContent = "";
    propertyFormMessage.className =
        "message hidden";
}

async function caricaServiziImmobile(
    propertyId,
    propertyTitle
) {
    nascondiMessaggioServizi();

    servicesContainer.innerHTML = "";

    servicesPropertyIdInput.value =
        String(propertyId);

    servicesTitle.textContent =
        `Servizi: ${propertyTitle}`;

    mostraCaricamentoServizi(true);

    try {
        const response = await fetch(
            `${API_BASE}/host/servizi-immobile.php?immobile_id=${propertyId}`,
            {
                headers: {
                    "Authorization": `Bearer ${token}`,
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            gestisciErroreAutenticazione(response);

            throw new Error(
                data.errore
                || "Impossibile caricare i servizi."
            );
        }

        mostraServiziSelezionabili(data.servizi);
    } catch (error) {
        mostraMessaggioServizi(
            error.message,
            "error"
        );
    } finally {
        mostraCaricamentoServizi(false);
    }
}

function mostraServiziSelezionabili(services) {
    servicesContainer.innerHTML = "";

    if (!Array.isArray(services) || services.length === 0) {
        const emptyState = document.createElement("div");

        emptyState.className = "empty-state";
        emptyState.textContent =
            "Non sono presenti servizi configurati.";

        servicesContainer.appendChild(emptyState);
        return;
    }

    for (const service of services) {
        const label = document.createElement("label");

        label.className = "service-checkbox-card";

        const checkbox = document.createElement("input");

        checkbox.type = "checkbox";
        checkbox.name = "servizi_ids";
        checkbox.value = String(service.id);
        checkbox.checked =
    Number(service.selezionato) === 1
    || service.selezionato === true;

        const content = document.createElement("span");

        content.className = "service-checkbox-content";

        const name = document.createElement("strong");
        name.textContent = service.nome;

        const description = document.createElement("small");

        description.textContent = service.selezionato
            ? "Attualmente disponibile"
            : "Non associato";

        content.append(name, description);
        label.append(checkbox, content);

        checkbox.addEventListener("change", () => {
            description.textContent = checkbox.checked
                ? "Selezionato"
                : "Non associato";
        });

        servicesContainer.appendChild(label);
    }
}

async function salvaServiziImmobile() {
    nascondiMessaggioServizi();

    const propertyId = Number(
        servicesPropertyIdInput.value
    );

    if (!Number.isInteger(propertyId) || propertyId < 1) {
        mostraMessaggioServizi(
            "Identificativo dell'immobile non valido.",
            "error"
        );
        return;
    }

    const selectedCheckboxes =
        servicesContainer.querySelectorAll(
            'input[name="servizi_ids"]:checked'
        );

    const serviceIds = Array.from(
        selectedCheckboxes
    ).map((checkbox) => Number(checkbox.value));

    saveServicesButton.disabled = true;
    saveServicesButton.textContent =
        "Salvataggio in corso...";

    try {
        const response = await fetch(
            `${API_BASE}/host/servizi-immobile.php?immobile_id=${propertyId}`,
            {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`,
                },
                body: JSON.stringify({
                    servizi_ids: serviceIds,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            gestisciErroreAutenticazione(response);

            throw new Error(
                data.errore
                || "Impossibile aggiornare i servizi."
            );
        }

        await caricaServiziImmobile(
    propertyId,
    data.immobile.titolo
);

mostraMessaggioServizi(
    "Servizi aggiornati correttamente.",
    "success"
);
    } catch (error) {
        mostraMessaggioServizi(
            error.message,
            "error"
        );
    } finally {
        saveServicesButton.disabled = false;
        saveServicesButton.textContent =
            "Salva servizi";
    }
}

function pulisciPannelloServizi() {
    servicesPropertyIdInput.value = "";
    servicesContainer.innerHTML = "";

    servicesTitle.textContent =
        "Servizi dell'immobile";

    nascondiMessaggioServizi();
}

function mostraMessaggioServizi(text, type) {
    servicesMessage.textContent = text;
    servicesMessage.className =
        `message ${type}`;
}

function nascondiMessaggioServizi() {
    servicesMessage.textContent = "";
    servicesMessage.className =
        "message hidden";
}

function mostraCaricamentoServizi(show) {
    servicesLoading.classList.toggle(
        "hidden",
        !show
    );
}