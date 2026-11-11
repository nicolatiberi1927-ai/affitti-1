<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

/**
 * Invia una risposta JSON e termina l'esecuzione.
 */
function jsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Legge e valida il corpo JSON della richiesta.
 */
function requestBody(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    try {
        $data = json_decode(
            $rawBody,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        jsonResponse(
            ['errore' => 'Il corpo della richiesta non contiene JSON valido.'],
            400
        );
    }

    if (!is_array($data)) {
        jsonResponse(
            ['errore' => 'Il corpo JSON deve essere un oggetto.'],
            400
        );
    }

    return $data;
}

/**
 * Restituisce il metodo HTTP della richiesta.
 */
function requestMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Interrompe la richiesta se il metodo HTTP non è consentito.
 */
function requireMethod(string ...$allowedMethods): void
{
    $currentMethod = requestMethod();

    if (!in_array($currentMethod, $allowedMethods, true)) {
        header('Allow: ' . implode(', ', $allowedMethods));

        jsonResponse(
            [
                'errore' => 'Metodo HTTP non consentito.',
                'metodi_consentiti' => $allowedMethods,
            ],
            405
        );
    }
}

/**
 * Converte un valore in un intero positivo.
 */
function positiveInteger(mixed $value): ?int
{
    $result = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    if ($result === false) {
        return null;
    }

    return $result;
}

/**
 * Gestisce gli errori non previsti senza mostrare dettagli sensibili.
 */
set_exception_handler(
    function (Throwable $exception): void {
        error_log(
            sprintf(
                '[%s] %s in %s:%d',
                date('Y-m-d H:i:s'),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            )
        );

        jsonResponse(
            ['errore' => 'Si è verificato un errore interno del server.'],
            500
        );
    }
);

function bearerToken(): ?string
{
    $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if ($authorizationHeader === '' && function_exists('getallheaders')) {
        $headers = getallheaders();

        $authorizationHeader =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? '';
    }

    if (
        !preg_match(
            '/^Bearer\s+([A-Fa-f0-9]{64})$/',
            trim($authorizationHeader),
            $matches
        )
    ) {
        return null;
    }

    return $matches[1];
}

function authenticatedUser(?string $requiredRole = null): array
{
    $token = bearerToken();

    if ($token === null) {
        jsonResponse(
            ['errore' => 'Token di accesso mancante o non valido.'],
            401
        );
    }

    $tokenHash = hash('sha256', $token);

    $connection = db();

    $statement = $connection->prepare(
        'SELECT
            utenti.id,
            utenti.nome,
            utenti.email,
            utenti.ruolo,
            token_accesso.scadenza
         FROM token_accesso
         INNER JOIN utenti
            ON utenti.id = token_accesso.utente_id
         WHERE token_accesso.token_hash = :token_hash
           AND token_accesso.scadenza > NOW()
         LIMIT 1'
    );

    $statement->execute([
        'token_hash' => $tokenHash,
    ]);

    $utente = $statement->fetch();

    if ($utente === false) {
        jsonResponse(
            ['errore' => 'Token di accesso scaduto o non valido.'],
            401
        );
    }

    if (
        $requiredRole !== null
        && $utente['ruolo'] !== $requiredRole
    ) {
        jsonResponse(
            ['errore' => 'Non hai i permessi per questa operazione.'],
            403
        );
    }

    return [
        'id' => (int) $utente['id'],
        'nome' => $utente['nome'],
        'email' => $utente['email'],
        'ruolo' => $utente['ruolo'],
        'scadenza_token' => $utente['scadenza'],
    ];
}