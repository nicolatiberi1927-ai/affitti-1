<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('POST');

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
    'DELETE FROM token_accesso
     WHERE token_hash = :token_hash'
);

$statement->execute([
    'token_hash' => $tokenHash,
]);

if ($statement->rowCount() === 0) {
    jsonResponse(
        ['errore' => 'Token di accesso scaduto o non valido.'],
        401
    );
}

jsonResponse(
    [
        'successo' => true,
        'messaggio' => 'Logout completato correttamente.',
    ]
);