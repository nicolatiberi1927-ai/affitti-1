<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET');

$utente = authenticatedUser();

jsonResponse(
    [
        'successo' => true,
        'messaggio' => 'Utente autenticato correttamente.',
        'utente' => $utente,
    ]
);