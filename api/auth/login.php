<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('POST');

$data = requestBody();

$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($email === '') {
    jsonResponse(
        ['errore' => 'L indirizzo email e obbligatorio.'],
        400
    );
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(
        ['errore' => 'L indirizzo email non e valido.'],
        400
    );
}

if ($password === '') {
    jsonResponse(
        ['errore' => 'La password e obbligatoria.'],
        400
    );
}

$connection = db();

$statement = $connection->prepare(
    'SELECT
        id,
        nome,
        email,
        password_hash,
        ruolo
     FROM utenti
     WHERE email = :email
     LIMIT 1'
);

$statement->execute([
    'email' => $email,
]);

$utente = $statement->fetch();

if (
    $utente === false
    || !password_verify($password, $utente['password_hash'])
) {
    jsonResponse(
        ['errore' => 'Email o password non corrette.'],
        401
    );
}

$token = bin2hex(random_bytes(32));

$tokenHash = hash('sha256', $token);

$scadenza = new DateTimeImmutable('+24 hours');

$statementEliminazione = $connection->prepare(
    'DELETE FROM token_accesso
     WHERE utente_id = :utente_id
       AND scadenza <= NOW()'
);

$statementEliminazione->execute([
    'utente_id' => $utente['id'],
]);

$statementToken = $connection->prepare(
    'INSERT INTO token_accesso (
        utente_id,
        token_hash,
        scadenza
    ) VALUES (
        :utente_id,
        :token_hash,
        :scadenza
    )'
);

$statementToken->execute([
    'utente_id' => $utente['id'],
    'token_hash' => $tokenHash,
    'scadenza' => $scadenza->format('Y-m-d H:i:s'),
]);

jsonResponse(
    [
        'successo' => true,
        'messaggio' => 'Login completato.',
        'token' => $token,
        'scadenza' => $scadenza->format(DATE_ATOM),
        'utente' => [
            'id' => (int) $utente['id'],
            'nome' => $utente['nome'],
            'email' => $utente['email'],
            'ruolo' => $utente['ruolo'],
        ],
    ]
);