<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('PATCH');

$host = authenticatedUser('host');

$data = requestBody();

$immobileId = positiveInteger(
    $data['immobile_id'] ?? null
);

$localitaId = positiveInteger(
    $data['localita_id'] ?? null
);

$titolo = trim(
    (string) ($data['titolo'] ?? '')
);

$descrizione = trim(
    (string) ($data['descrizione'] ?? '')
);

$indirizzo = trim(
    (string) ($data['indirizzo'] ?? '')
);

$postiLetto = positiveInteger(
    $data['posti_letto'] ?? null
);

$camere = positiveInteger(
    $data['camere'] ?? null
);

$bagni = positiveInteger(
    $data['bagni'] ?? null
);

$prezzoNotte = filter_var(
    $data['prezzo_notte'] ?? null,
    FILTER_VALIDATE_FLOAT
);

if ($immobileId === null) {
    jsonResponse(
        ['errore' => 'immobile_id deve essere un intero positivo.'],
        400
    );
}

if ($localitaId === null) {
    jsonResponse(
        ['errore' => 'localita_id deve essere un intero positivo.'],
        400
    );
}

if ($titolo === '') {
    jsonResponse(
        ['errore' => 'Il titolo è obbligatorio.'],
        400
    );
}

if (mb_strlen($titolo) > 150) {
    jsonResponse(
        ['errore' => 'Il titolo non può superare 150 caratteri.'],
        400
    );
}

if ($descrizione === '') {
    jsonResponse(
        ['errore' => 'La descrizione è obbligatoria.'],
        400
    );
}

if ($indirizzo === '') {
    jsonResponse(
        ['errore' => 'L indirizzo è obbligatorio.'],
        400
    );
}

if (mb_strlen($indirizzo) > 200) {
    jsonResponse(
        ['errore' => 'L indirizzo non può superare 200 caratteri.'],
        400
    );
}

if ($postiLetto === null) {
    jsonResponse(
        ['errore' => 'posti_letto deve essere un intero positivo.'],
        400
    );
}

if ($camere === null) {
    jsonResponse(
        ['errore' => 'camere deve essere un intero positivo.'],
        400
    );
}

if ($bagni === null) {
    jsonResponse(
        ['errore' => 'bagni deve essere un intero positivo.'],
        400
    );
}

if ($prezzoNotte === false || $prezzoNotte <= 0) {
    jsonResponse(
        ['errore' => 'prezzo_notte deve essere maggiore di zero.'],
        400
    );
}

$connection = db();

try {
    $connection->beginTransaction();

    /*
     * Controlla che l'immobile appartenga all'host autenticato.
     */
    $statementImmobile = $connection->prepare(
        'SELECT
            id,
            titolo,
            attivo
         FROM immobili
         WHERE id = :immobile_id
           AND host_id = :host_id
         FOR UPDATE'
    );

    $statementImmobile->execute([
        'immobile_id' => $immobileId,
        'host_id' => $host['id'],
    ]);

    $immobileEsistente = $statementImmobile->fetch();

    if ($immobileEsistente === false) {
        $connection->rollBack();

        jsonResponse(
            [
                'errore' =>
                    'Immobile non trovato o non appartenente all host autenticato.',
            ],
            404
        );
    }

    /*
     * Controlla che la località esista.
     */
    $statementLocalita = $connection->prepare(
        'SELECT id, nome
         FROM localita
         WHERE id = :localita_id
         LIMIT 1'
    );

    $statementLocalita->execute([
        'localita_id' => $localitaId,
    ]);

    $localita = $statementLocalita->fetch();

    if ($localita === false) {
        $connection->rollBack();

        jsonResponse(
            ['errore' => 'La località indicata non esiste.'],
            404
        );
    }

    /*
     * Aggiorna l'immobile.
     */
    $statementAggiornamento = $connection->prepare(
        'UPDATE immobili
         SET
            localita_id = :localita_id,
            titolo = :titolo,
            descrizione = :descrizione,
            indirizzo = :indirizzo,
            posti_letto = :posti_letto,
            camere = :camere,
            bagni = :bagni,
            prezzo_notte = :prezzo_notte
         WHERE id = :immobile_id
           AND host_id = :host_id'
    );

    $statementAggiornamento->execute([
        'localita_id' => $localitaId,
        'titolo' => $titolo,
        'descrizione' => $descrizione,
        'indirizzo' => $indirizzo,
        'posti_letto' => $postiLetto,
        'camere' => $camere,
        'bagni' => $bagni,
        'prezzo_notte' => $prezzoNotte,
        'immobile_id' => $immobileId,
        'host_id' => $host['id'],
    ]);

    $connection->commit();

    jsonResponse(
        [
            'successo' => true,
            'messaggio' => 'Immobile modificato correttamente.',
            'immobile' => [
                'id' => $immobileId,
                'host_id' => $host['id'],
                'localita_id' => $localitaId,
                'localita' => $localita['nome'],
                'titolo' => $titolo,
                'descrizione' => $descrizione,
                'indirizzo' => $indirizzo,
                'posti_letto' => $postiLetto,
                'camere' => $camere,
                'bagni' => $bagni,
                'prezzo_notte' => $prezzoNotte,
                'attivo' => (bool) $immobileEsistente['attivo'],
            ],
        ]
    );
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}