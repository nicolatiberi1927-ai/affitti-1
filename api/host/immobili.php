<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET', 'POST');

$host = authenticatedUser('host');

$connection = db();

/*
 * GET:
 * restituisce gli immobili appartenenti all'host autenticato.
 */
if (requestMethod() === 'GET') {
    $statement = $connection->prepare(
        'SELECT
            immobili.id,
            immobili.titolo,
            immobili.descrizione,
            immobili.indirizzo,
            immobili.posti_letto,
            immobili.camere,
            immobili.bagni,
            immobili.prezzo_notte,
            immobili.attivo,
            immobili.created_at,
            localita.id AS localita_id,
            localita.nome AS localita
         FROM immobili
         INNER JOIN localita
            ON localita.id = immobili.localita_id
         WHERE immobili.host_id = :host_id
         ORDER BY immobili.id DESC'
    );

    $statement->execute([
        'host_id' => $host['id'],
    ]);

    $immobili = $statement->fetchAll();

    jsonResponse(
        [
            'successo' => true,
            'numero_risultati' => count($immobili),
            'immobili' => $immobili,
        ]
    );
}

/*
 * Da questo punto viene gestita la richiesta POST.
 */
$data = requestBody();

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

/*
 * Validazione della localita.
 */
if ($localitaId === null) {
    jsonResponse(
        ['errore' => 'localita_id deve essere un intero positivo.'],
        400
    );
}

/*
 * Validazione del titolo.
 */
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

/*
 * Validazione della descrizione.
 */
if ($descrizione === '') {
    jsonResponse(
        ['errore' => 'La descrizione è obbligatoria.'],
        400
    );
}

/*
 * Validazione dell'indirizzo.
 */
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

/*
 * Validazione dei valori numerici.
 */
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

/*
 * Controlla che la localita indicata esista.
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
    jsonResponse(
        ['errore' => 'La località indicata non esiste.'],
        404
    );
}

/*
 * Inserisce il nuovo immobile.
 *
 * host_id non arriva dal JSON:
 * viene ricavato dal token dell'host.
 */
$statementInserimento = $connection->prepare(
    'INSERT INTO immobili (
        host_id,
        localita_id,
        titolo,
        descrizione,
        indirizzo,
        posti_letto,
        camere,
        bagni,
        prezzo_notte,
        attivo
    ) VALUES (
        :host_id,
        :localita_id,
        :titolo,
        :descrizione,
        :indirizzo,
        :posti_letto,
        :camere,
        :bagni,
        :prezzo_notte,
        TRUE
    )'
);

$statementInserimento->execute([
    'host_id' => $host['id'],
    'localita_id' => $localitaId,
    'titolo' => $titolo,
    'descrizione' => $descrizione,
    'indirizzo' => $indirizzo,
    'posti_letto' => $postiLetto,
    'camere' => $camere,
    'bagni' => $bagni,
    'prezzo_notte' => $prezzoNotte,
]);

$immobileId = (int) $connection->lastInsertId();

jsonResponse(
    [
        'successo' => true,
        'messaggio' => 'Immobile creato correttamente.',
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
            'attivo' => true,
        ],
    ],
    201
);