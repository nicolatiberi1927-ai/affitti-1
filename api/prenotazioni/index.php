<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET', 'POST');

$utente = authenticatedUser('ospite');

$connection = db();

/*
 * Richiesta GET:
 * restituisce le prenotazioni dell'ospite autenticato.
 */
if (requestMethod() === 'GET') {
    $statement = $connection->prepare(
        'SELECT
            prenotazioni.id,
            prenotazioni.immobile_id,
            immobili.titolo AS titolo_immobile,
            localita.nome AS localita,
            prenotazioni.data_arrivo,
            prenotazioni.data_partenza,
            DATEDIFF(
                prenotazioni.data_partenza,
                prenotazioni.data_arrivo
            ) AS numero_notti,
            prenotazioni.numero_ospiti,
            prenotazioni.totale,
            prenotazioni.stato,
            prenotazioni.created_at
         FROM prenotazioni
         INNER JOIN immobili
            ON immobili.id = prenotazioni.immobile_id
         INNER JOIN localita
            ON localita.id = immobili.localita_id
         WHERE prenotazioni.ospite_id = :ospite_id
         ORDER BY
            prenotazioni.data_arrivo DESC,
            prenotazioni.id DESC'
    );

    $statement->execute([
        'ospite_id' => $utente['id'],
    ]);

    $prenotazioni = $statement->fetchAll();

    jsonResponse(
        [
            'successo' => true,
            'numero_risultati' => count($prenotazioni),
            'prenotazioni' => $prenotazioni,
        ]
    );
}

/*
 * Da questo punto continua la richiesta POST
 * per creare una nuova prenotazione.
 */
$data = requestBody();

$immobileId = positiveInteger($data['immobile_id'] ?? null);
$numeroOspiti = positiveInteger($data['numero_ospiti'] ?? null);
$dataArrivo = trim((string) ($data['data_arrivo'] ?? ''));
$dataPartenza = trim((string) ($data['data_partenza'] ?? ''));

if ($immobileId === null) {
    jsonResponse(
        ['errore' => 'immobile_id deve essere un intero positivo.'],
        400
    );
}

if ($numeroOspiti === null) {
    jsonResponse(
        ['errore' => 'numero_ospiti deve essere un intero positivo.'],
        400
    );
}

/*
 * Converte e controlla la data di arrivo.
 */
$oggettoDataArrivo = DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $dataArrivo
);

$dataArrivoValida =
    $oggettoDataArrivo !== false
    && $oggettoDataArrivo->format('Y-m-d') === $dataArrivo;

if (!$dataArrivoValida) {
    jsonResponse(
        [
            'errore' =>
                'data_arrivo deve essere una data valida nel formato AAAA-MM-GG.',
        ],
        400
    );
}

/*
 * Converte e controlla la data di partenza.
 */
$oggettoDataPartenza = DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $dataPartenza
);

$dataPartenzaValida =
    $oggettoDataPartenza !== false
    && $oggettoDataPartenza->format('Y-m-d') === $dataPartenza;

if (!$dataPartenzaValida) {
    jsonResponse(
        [
            'errore' =>
                'data_partenza deve essere una data valida nel formato AAAA-MM-GG.',
        ],
        400
    );
}

/*
 * L'arrivo non può essere nel passato.
 */
$oggi = new DateTimeImmutable('today');

if ($oggettoDataArrivo < $oggi) {
    jsonResponse(
        ['errore' => 'La data di arrivo non può essere nel passato.'],
        422
    );
}

/*
 * La partenza deve essere successiva all'arrivo.
 */
if ($oggettoDataPartenza <= $oggettoDataArrivo) {
    jsonResponse(
        [
            'errore' =>
                'La data di partenza deve essere successiva alla data di arrivo.',
        ],
        422
    );
}

/*
 * Calcola il numero di notti.
 */
$numeroNotti = $oggettoDataArrivo
    ->diff($oggettoDataPartenza)
    ->days;

if ($numeroNotti === false || $numeroNotti < 1) {
    jsonResponse(
        ['errore' => 'Il soggiorno deve durare almeno una notte.'],
        422
    );
}

if ($numeroNotti > 30) {
    jsonResponse(
        ['errore' => 'Il soggiorno non può superare 30 notti.'],
        422
    );

}

/*
 * Connessione al database.
 */


try {
    /*
     * La transazione impedisce che due richieste concorrenti
     * prenotino lo stesso immobile nello stesso intervallo.
     */
    $connection->beginTransaction();

    /*
     * FOR UPDATE blocca temporaneamente la riga dell'immobile
     * fino alla fine della transazione.
     */
    $statementImmobile = $connection->prepare(
        'SELECT
            id,
            titolo,
            posti_letto,
            prezzo_notte,
            attivo
         FROM immobili
         WHERE id = :immobile_id
         FOR UPDATE'
    );

    $statementImmobile->execute([
        'immobile_id' => $immobileId,
    ]);

    $immobile = $statementImmobile->fetch();

    if ($immobile === false || !(bool) $immobile['attivo']) {
        $connection->rollBack();

        jsonResponse(
            ['errore' => 'Immobile non trovato o non disponibile.'],
            404
        );
    }

    /*
     * Controlla la capienza dell'immobile.
     */
    if ($numeroOspiti > (int) $immobile['posti_letto']) {
        $connection->rollBack();

        jsonResponse(
            [
                'errore' =>
                    'Il numero di ospiti supera i posti letto disponibili.',
                'posti_letto' => (int) $immobile['posti_letto'],
            ],
            422
        );
    }

    /*
     * Cerca una prenotazione sovrapposta.
     * Una prenotazione annullata non blocca l'immobile.
     */
    $statementSovrapposizione = $connection->prepare(
        'SELECT id
         FROM prenotazioni
         WHERE immobile_id = :immobile_id
           AND stato IN (
               \'in_attesa\',
               \'confermata\'
           )
           AND data_arrivo < :data_partenza
           AND data_partenza > :data_arrivo
         LIMIT 1
         FOR UPDATE'
    );

    $statementSovrapposizione->execute([
        'immobile_id' => $immobileId,
        'data_arrivo' => $dataArrivo,
        'data_partenza' => $dataPartenza,
    ]);

    if ($statementSovrapposizione->fetch() !== false) {
        $connection->rollBack();

        jsonResponse(
            ['errore' => 'L\'immobile non è disponibile nelle date richieste.'],
            409
        );
    }

    /*
     * Il totale viene calcolato dal server.
     */
    $prezzoNotte = (float) $immobile['prezzo_notte'];
    $totale = round($numeroNotti * $prezzoNotte, 2);

    /*
     * Inserisce la prenotazione con stato iniziale in_attesa.
     */
    $statementInserimento = $connection->prepare(
        'INSERT INTO prenotazioni (
            immobile_id,
            ospite_id,
            data_arrivo,
            data_partenza,
            numero_ospiti,
            totale,
            stato
        ) VALUES (
            :immobile_id,
            :ospite_id,
            :data_arrivo,
            :data_partenza,
            :numero_ospiti,
            :totale,
            \'in_attesa\'
        )'
    );

    $statementInserimento->execute([
        'immobile_id' => $immobileId,
        'ospite_id' => $utente['id'],
        'data_arrivo' => $dataArrivo,
        'data_partenza' => $dataPartenza,
        'numero_ospiti' => $numeroOspiti,
        'totale' => $totale,
    ]);

    $prenotazioneId = (int) $connection->lastInsertId();

    $connection->commit();

    jsonResponse(
        [
            'successo' => true,
            'messaggio' => 'Prenotazione creata correttamente.',
            'prenotazione' => [
                'id' => $prenotazioneId,
                'immobile_id' => $immobileId,
                'titolo_immobile' => $immobile['titolo'],
                'ospite_id' => $utente['id'],
                'data_arrivo' => $dataArrivo,
                'data_partenza' => $dataPartenza,
                'numero_notti' => $numeroNotti,
                'numero_ospiti' => $numeroOspiti,
                'prezzo_notte' => $prezzoNotte,
                'totale' => $totale,
                'stato' => 'in_attesa',
            ],
        ],
        201
    );

} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}
