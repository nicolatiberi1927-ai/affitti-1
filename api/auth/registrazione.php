<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('POST');

$data = requestBody();

/*
 * Preleva e ripulisce i valori ricevuti.
 */
$nome = trim((string) ($data['nome'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$ruolo = trim((string) ($data['ruolo'] ?? ''));

/*
 * Controllo del nome.
 */
if ($nome === '') {
    jsonResponse(
        ['errore' => 'Il nome è obbligatorio.'],
        400
    );
}

if (mb_strlen($nome) > 100) {
    jsonResponse(
        ['errore' => 'Il nome non può superare 100 caratteri.'],
        400
    );
}

/*
 * Controllo dell'indirizzo email.
 */
if ($email === '') {
    jsonResponse(
        ['errore' => 'L’indirizzo email è obbligatorio.'],
        400
    );
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(
        ['errore' => 'L’indirizzo email non è valido.'],
        400
    );
}

if (mb_strlen($email) > 190) {
    jsonResponse(
        ['errore' => 'L’indirizzo email è troppo lungo.'],
        400
    );
}

/*
 * Controllo della password.
 */
if ($password === '') {
    jsonResponse(
        ['errore' => 'La password è obbligatoria.'],
        400
    );
}

if (strlen($password) < 8) {
    jsonResponse(
        ['errore' => 'La password deve contenere almeno 8 caratteri.'],
        400
    );
}

if (strlen($password) > 72) {
    jsonResponse(
        ['errore' => 'La password non può superare 72 caratteri.'],
        400
    );
}

/*
 * Controllo del ruolo.
 */
$ruoliConsentiti = ['host', 'ospite'];

if (!in_array($ruolo, $ruoliConsentiti, true)) {
    jsonResponse(
        [
            'errore' =>
                'Il ruolo deve essere host oppure ospite.',
        ],
        400
    );
}

$connection = db();

/*
 * Controlla se l'indirizzo email è già registrato.
 */
$statementEmail = $connection->prepare(
    '
        SELECT id
        FROM utenti
        WHERE email = :email
        LIMIT 1
    '
);

$statementEmail->execute(
    [
        'email' => $email,
    ]
);

if ($statementEmail->fetch() !== false) {
    jsonResponse(
        ['errore' => 'Esiste già un utente con questa email.'],
        409
    );
}

/*
 * Crea un hash sicuro della password.
 *
 * La password originale non viene salvata nel database.
 */
$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($passwordHash === false) {
    throw new RuntimeException(
        'Impossibile generare l’hash della password.'
    );
}

/*
 * Inserisce il nuovo utente.
 */
$statementInserimento = $connection->prepare(
    '
        INSERT INTO utenti (
            nome,
            email,
            password_hash,
            ruolo
        )
        VALUES (
            :nome,
            :email,
            :password_hash,
            :ruolo
        )
    '
);

$statementInserimento->execute(
    [
        'nome' => $nome,
        'email' => $email,
        'password_hash' => $passwordHash,
        'ruolo' => $ruolo,
    ]
);

$utenteId = (int) $connection->lastInsertId();

/*
 * Risposta finale.
 *
 * Non restituiamo mai password o password_hash.
 */
jsonResponse(
    [
        'successo' => true,
        'messaggio' => 'Registrazione completata.',
        'utente' => [
            'id' => $utenteId,
            'nome' => $nome,
            'email' => $email,
            'ruolo' => $ruolo,
        ],
    ],
    201
);