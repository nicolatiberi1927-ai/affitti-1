const API_BASE = "/affitti/api";

const registrationForm = document.querySelector(
    "#registration-form"
);

const nameInput = document.querySelector("#name");
const emailInput = document.querySelector("#email");
const passwordInput = document.querySelector("#password");

const passwordConfirmationInput = document.querySelector(
    "#password-confirmation"
);

const roleSelect = document.querySelector("#role");
const messageContainer = document.querySelector("#message");

registrationForm.addEventListener(
    "submit",
    async (event) => {
        event.preventDefault();

        nascondiMessaggio();

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        const passwordConfirmation =
            passwordConfirmationInput.value;
        const role = roleSelect.value;

        if (name === "" || email === "" || password === "") {
            mostraMessaggio(
                "Compila tutti i campi obbligatori.",
                "error"
            );
            return;
        }

        if (password.length < 8) {
            mostraMessaggio(
                "La password deve contenere almeno 8 caratteri.",
                "error"
            );
            return;
        }

        if (password !== passwordConfirmation) {
            mostraMessaggio(
                "Le due password non coincidono.",
                "error"
            );
            return;
        }

        if (!["ospite", "host"].includes(role)) {
            mostraMessaggio(
                "Seleziona un tipo di account valido.",
                "error"
            );
            return;
        }

        const submitButton = registrationForm.querySelector(
            'button[type="submit"]'
        );

        submitButton.disabled = true;
        submitButton.textContent = "Registrazione in corso...";

        try {
            const response = await fetch(
                `${API_BASE}/auth/registrazione.php`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        nome: name,
                        email: email,
                        password: password,
                        ruolo: role,
                    }),
                }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.errore
                    || "Impossibile completare la registrazione."
                );
            }

            mostraMessaggio(
                "Registrazione completata. Ora puoi accedere.",
                "success"
            );

            registrationForm.reset();

            setTimeout(() => {
                window.location.href =
                    `login.html?email=${encodeURIComponent(email)}`;
            }, 1000);
        } catch (error) {
            mostraMessaggio(error.message, "error");
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = "Crea account";
        }
    }
);

function mostraMessaggio(text, type) {
    messageContainer.textContent = text;
    messageContainer.className = `message ${type}`;
}

function nascondiMessaggio() {
    messageContainer.textContent = "";
    messageContainer.className = "message hidden";
}