const API_BASE = "/affitti/api";

const loginForm = document.querySelector("#login-form");
const emailInput = document.querySelector("#email");
const passwordInput = document.querySelector("#password");
const messageContainer = document.querySelector("#message");

document.addEventListener("DOMContentLoaded", () => {
        const parameters = new URLSearchParams(
        window.location.search
    );

    const suggestedEmail = parameters.get("email");

    if (suggestedEmail) {
        emailInput.value = suggestedEmail;
    }
    const existingToken = localStorage.getItem("access_token");
    const existingUser = leggiUtenteSalvato();

    if (existingToken && existingUser) {
        mostraMessaggio(
            `Hai già effettuato l'accesso come ${existingUser.nome}.`,
            "success"
        );
    }
});

loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    nascondiMessaggio();

    const email = emailInput.value.trim();
    const password = passwordInput.value;

    if (email === "" || password === "") {
        mostraMessaggio(
            "Inserisci email e password.",
            "error"
        );
        return;
    }

    const submitButton = loginForm.querySelector(
        'button[type="submit"]'
    );

    submitButton.disabled = true;
    submitButton.textContent = "Accesso in corso...";

    try {
        const response = await fetch(
            `${API_BASE}/auth/login.php`,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    email: email,
                    password: password,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.errore || "Impossibile effettuare l'accesso."
            );
        }

        localStorage.setItem(
            "access_token",
            data.token
        );

        localStorage.setItem(
            "utente",
            JSON.stringify(data.utente)
        );

        localStorage.setItem(
            "scadenza_token",
            data.scadenza
        );

        mostraMessaggio(
            "Accesso completato. Reindirizzamento in corso...",
            "success"
        );

        setTimeout(() => {
            if (data.utente.ruolo === "host") {
                window.location.href = "area-host.html";
                return;
            }

            window.location.href = "prenotazioni.html";
        }, 800);
    } catch (error) {
        mostraMessaggio(error.message, "error");
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = "Accedi";
    }
});

function leggiUtenteSalvato() {
    const savedUser = localStorage.getItem("utente");

    if (!savedUser) {
        return null;
    }

    try {
        return JSON.parse(savedUser);
    } catch {
        localStorage.removeItem("utente");
        return null;
    }
}

function mostraMessaggio(text, type) {
    messageContainer.textContent = text;
    messageContainer.className = `message ${type}`;
}

function nascondiMessaggio() {
    messageContainer.textContent = "";
    messageContainer.className = "message hidden";
}