const API_BASE = "/affitti/api";

const token = localStorage.getItem("access_token");
const user = leggiUtenteSalvato();

const userDescription = document.querySelector(
    "#user-description"
);

const messageContainer = document.querySelector("#message");
const loadingContainer = document.querySelector("#loading");

const bookingsContainer = document.querySelector(
    "#bookings-container"
);

const logoutButton = document.querySelector("#logout-button");

document.addEventListener("DOMContentLoaded", async () => {
    if (!token || !user) {
        window.location.href = "login.html";
        return;
    }

    if (user.ruolo !== "ospite") {
        window.location.href = "area-host.html";
        return;
    }

    userDescription.textContent =
        `Account di ${user.nome} - ${user.email}`;

    await caricaPrenotazioni();
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

async function caricaPrenotazioni() {
    mostraCaricamento(true);
    nascondiMessaggio();
    bookingsContainer.innerHTML = "";

    try {
        const response = await fetch(
            `${API_BASE}/prenotazioni/`,
            {
                headers: {
                    "Authorization": `Bearer ${token}`,
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 401) {
                pulisciAutenticazione();

                window.location.href = "login.html";
                return;
            }

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

function mostraPrenotazioni(prenotazioni) {
    bookingsContainer.innerHTML = "";

    if (
        !Array.isArray(prenotazioni)
        || prenotazioni.length === 0
    ) {
        const emptyState = document.createElement("div");

        emptyState.className = "empty-state";

        const title = document.createElement("h2");
        title.textContent = "Non hai ancora prenotazioni";

        const description = document.createElement("p");
        description.textContent =
            "Cerca un alloggio e crea la prima prenotazione.";

        const link = document.createElement("a");
        link.className = "primary-button";
        link.href = "index.html";
        link.textContent = "Cerca un alloggio";

        emptyState.append(title, description, link);
        bookingsContainer.appendChild(emptyState);

        return;
    }

    for (const booking of prenotazioni) {
        bookingsContainer.appendChild(
            creaSchedaPrenotazione(booking)
        );
    }
}

function creaSchedaPrenotazione(booking) {
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

    titleGroup.append(location, title);

    const status = document.createElement("span");
    status.className =
        `status-badge status-${booking.stato}`;

    status.textContent = formatoStato(booking.stato);

    header.append(titleGroup, status);

    const details = document.createElement("div");
    details.className = "booking-details-grid";

    details.append(
        creaDettaglio("Arrivo", formattaData(booking.data_arrivo)),
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

    const detailLink = document.createElement("a");
    detailLink.className = "secondary-button";
    detailLink.href =
        `dettaglio.html?id=${booking.immobile_id}`;
    detailLink.textContent = "Vedi immobile";

    footer.appendChild(detailLink);

    if (booking.stato !== "annullata") {
        const cancelButton = document.createElement("button");

        cancelButton.type = "button";
        cancelButton.className = "danger-button";
        cancelButton.textContent = "Annulla prenotazione";

        cancelButton.addEventListener("click", async () => {
            await annullaPrenotazione(
                booking.id,
                booking.titolo_immobile,
                cancelButton
            );
        });

        footer.appendChild(cancelButton);
    }

    card.append(header, details, footer);

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

async function annullaPrenotazione(
    bookingId,
    propertyTitle,
    button
) {
    const confirmed = window.confirm(
        `Vuoi annullare la prenotazione per "${propertyTitle}"?`
    );

    if (!confirmed) {
        return;
    }

    button.disabled = true;
    button.textContent = "Annullamento...";

    try {
        const response = await fetch(
            `${API_BASE}/prenotazioni/annulla.php`,
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${token}`,
                },
                body: JSON.stringify({
                    prenotazione_id: Number(bookingId),
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 401) {
                pulisciAutenticazione();

                window.location.href = "login.html";
                return;
            }

            throw new Error(
                data.errore
                || "Impossibile annullare la prenotazione."
            );
        }

        mostraMessaggio(
            "Prenotazione annullata correttamente.",
            "success"
        );

        await caricaPrenotazioni();
    } catch (error) {
        mostraMessaggio(error.message, "error");

        button.disabled = false;
        button.textContent = "Annulla prenotazione";
    }
}

async function eseguiLogout() {
    logoutButton.disabled = true;
    logoutButton.textContent = "Uscita...";

    try {
        if (token) {
            await fetch(
                `${API_BASE}/auth/logout.php`,
                {
                    method: "POST",
                    headers: {
                        "Authorization": `Bearer ${token}`,
                    },
                }
            );
        }
    } finally {
        pulisciAutenticazione();
        window.location.href = "index.html";
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