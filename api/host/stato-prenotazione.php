<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('PATCH');

$host = authenticatedUser('host');

$data = requestBody();

$prenotazioneId = positiveInteger(
    $data['prenotazione_id'] ?? null
);

$nuovoStato = trim(
    (string) ($data['stato'] ?? '')
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

$statiConsentiti = [
    'confermata',
    'annullata',
];

if (!in_array($nuovoStato, $statiConsentiti, true)) {
    jsonResponse(
        [
            'errore' =>
                'Lo stato deve essere confermata oppure annullata.',
        ],
        400
    );
}

$connection = db();

try {
    $connection->beginTransaction();

    /*
     * Recupera la prenotazione solamente se l'immobile
     * appartiene all'host autenticato.
     */
    $statement = $connection->prepare(
        'SELECT
            prenotazioni.id,
            prenotazioni.immobile_id,
            prenotazioni.ospite_id,
            prenotazioni.data_arrivo,
            prenotazioni.data_partenza,
            prenotazioni.stato,
            immobili.titolo AS titolo_immobile,
            immobili.host_id
         FROM prenotazioni
         INNER JOIN immobili
            ON immobili.id = prenotazioni.immobile_id
         WHERE prenotazioni.id = :prenotazione_id
           AND immobili.host_id = :host_id
         FOR UPDATE'
    );

    $statement->execute([
        'prenotazione_id' => $prenotazioneId,
        'host_id' => $host['id'],
    ]);

    $prenotazione = $statement->fetch();

    if ($prenotazione === false) {
        $connection->rollBack();

        jsonResponse(
            [
                'errore' =>
                    'Prenotazione non trovata o non relativa a un tuo immobile.',
            ],
            404
        );
    }

    /*
     * Una prenotazione annullata non può essere riattivata.
     */
    if ($prenotazione['stato'] === 'annullata') {
        $connection->rollBack();

        jsonResponse(
            [
                'errore' =>
                    'Una prenotazione annullata non può essere modificata.',
            ],
            409
        );
    }

    /*
     * Evita un aggiornamento inutile.
     */
    if ($prenotazione['stato'] === $nuovoStato) {
        $connection->rollBack();

        jsonResponse(
            [
                'errore' =>
                    'La prenotazione possiede già lo stato richiesto.',
            ],
            409
        );
    }

    /*
     * Una prenotazione già iniziata non può essere modificata.
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
                    'Non è possibile modificare una prenotazione già iniziata.',
            ],
            422
        );
    }

    $statoPrecedente = $prenotazione['stato'];

    $statementAggiornamento = $connection->prepare(
        'UPDATE prenotazioni
         SET stato = :stato
         WHERE id = :prenotazione_id'
    );

    $statementAggiornamento->execute([
        'stato' => $nuovoStato,
        'prenotazione_id' => $prenotazioneId,
    ]);

    $connection->commit();

    jsonResponse(
        [
            'successo' => true,
            'messaggio' =>
                'Stato della prenotazione aggiornato correttamente.',
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
                    $statoPrecedente,
                'stato' =>
                    $nuovoStato,
            ],
        ]
    );
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}
