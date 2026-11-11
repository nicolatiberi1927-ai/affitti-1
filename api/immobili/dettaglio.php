<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET');

/*
 * Controlla che il parametro id sia presente.
 *
 * Esempio corretto:
 * dettaglio.php?id=1
 */
if (!isset($_GET['id'])) {
    jsonResponse(
        [
            'errore' => 'Il parametro id è obbligatorio.',
        ],
        400
    );
}

/*
 * Converte e valida l'identificativo.
 */
$immobileId = positiveInteger($_GET['id']);

if ($immobileId === null) {
    jsonResponse(
        [
            'errore' => 'Il parametro id deve essere un intero positivo.',
        ],
        400
    );
}

$connection = db();

/*
 * Recupera i dati principali dell'immobile.
 */
$sql = '
    SELECT
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
        localita.nome AS localita,
        utenti.id AS host_id,
        utenti.nome AS host
    FROM immobili
    INNER JOIN localita
        ON localita.id = immobili.localita_id
    INNER JOIN utenti
        ON utenti.id = immobili.host_id
    WHERE immobili.id = :immobile_id
      AND immobili.attivo = TRUE
';

$statement = $connection->prepare($sql);

$statement->execute(
    [
        'immobile_id' => $immobileId,
    ]
);

$immobile = $statement->fetch();

/*
 * Se fetch() restituisce false, l'immobile non esiste
 * oppure è stato disattivato.
 */
if ($immobile === false) {
    jsonResponse(
        [
            'errore' => 'Immobile non trovato.',
        ],
        404
    );
}

/*
 * Recupera i servizi collegati all'immobile.
 */
$sqlServizi = '
    SELECT
        servizi.id,
        servizi.nome
    FROM servizi
    INNER JOIN immobili_servizi
        ON immobili_servizi.servizio_id = servizi.id
    WHERE immobili_servizi.immobile_id = :immobile_id
    ORDER BY servizi.nome ASC
';

$statementServizi = $connection->prepare($sqlServizi);

$statementServizi->execute(
    [
        'immobile_id' => $immobileId,
    ]
);

$servizi = $statementServizi->fetchAll();

/*
 * Inserisce i servizi dentro i dati dell'immobile.
 */
$immobile['servizi'] = $servizi;

/*
 * Restituisce la risposta JSON.
 */
jsonResponse(
    [
        'successo' => true,
        'immobile' => $immobile,
    ]
);