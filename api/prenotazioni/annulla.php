<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('PATCH');

$utente = authenticatedUser('ospite');

$data = requestBody();

$prenotazioneId = positiveInteger(
    $data['prenotazione_id'] ?? null
);

if ($prenotazioneId === null) {
    jsonResponse(
        [
            'errore' =>
                'prenotazione_id deve essere un intero positivo.',
        ],
        400
    );
}

$connection = db();

try {
    $connection->beginTransaction();

    /*
     * Cerca la prenotazione e blocca temporaneamente la riga.
     *
     * Il controllo ospite_id impedisce a un utente
     * di annullare prenotazioni appartenenti ad altri.
     */
    $statement = $connection->prepare(
        'SELECT
            prenotazioni.id,
            prenotazioni.ospite_id,
            prenotazioni.immobile_id,
            prenotazioni.data_arrivo,
            prenotazioni.data_partenza,
            prenotazioni.stato,
            immobili.titolo AS titolo_immobile
         FROM prenotazioni
         INNER JOIN immobili
            ON immobili.id = prenotazioni.immobile_id
         WHERE prenotazioni.id = :prenotazione_id
           AND prenotazioni.ospite_id = :ospite_id
         FOR UPDATE'
    );

    $statement->execute([
        'prenotazione_id' => $prenotazioneId,
        'ospite_id' => $utente['id'],
    ]);

    $prenotazione = $statement->fetch();

    if ($prenotazione === false) {
        $connection->rollBack();

        jsonResponse(
            [
                'errore' =>
                    'Prenotazione non trovata o non appartenente all utente autenticato.',
            ],
            404
        );
    }

    /*
     * Una prenotazione già annullata non deve essere
     * modificata nuovamente.
     */
    if ($prenotazione['stato'] === 'annullata') {
        $connection->rollBack();

        jsonResponse(
            ['errore' => 'La prenotazione è già annullata.'],
            409
        );
    }

    /*
     * Impedisce di annullare una prenotazione già iniziata.
     */
    $oggi = new DateTimeImmutable('today');

    $dataArrivo = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $prenotazione['data_arrivo']
    );

    if (
        $dataArrivo === false
        || $dataArrivo <= $oggi
    ) {
        $connection->rollBack();

        jsonResponse(
            [
                'errore' =>
                    'Non è possibile annullare una prenotazione già iniziata.',
            ],
            422
        );
    }

    /*
     * Aggiornamento logico.
     *
     * La prenotazione rimane nel database,
     * ma non blocca più la disponibilità dell'immobile.
     */
    $statementAggiornamento = $connection->prepare(
        'UPDATE prenotazioni
         SET stato = \'annullata\'
         WHERE id = :prenotazione_id
           AND ospite_id = :ospite_id'
    );

    $statementAggiornamento->execute([
        'prenotazione_id' => $prenotazioneId,
        'ospite_id' => $utente['id'],
    ]);

    $connection->commit();

    jsonResponse(
        [
            'successo' => true,
            'messaggio' => 'Prenotazione annullata correttamente.',
            'prenotazione' => [
                'id' => (int) $prenotazione['id'],
                'immobile_id' =>
                    (int) $prenotazione['immobile_id'],
                'titolo_immobile' =>
                    $prenotazione['titolo_immobile'],
                'data_arrivo' =>
                    $prenotazione['data_arrivo'],
                'data_partenza' =>
                    $prenotazione['data_partenza'],
                'stato_precedente' =>
                    $prenotazione['stato'],
                'stato' => 'annullata',
            ],
        ]
    );
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}