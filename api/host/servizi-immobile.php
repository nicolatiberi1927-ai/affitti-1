<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET', 'PUT');

$host = authenticatedUser('host');

$connection = db();

/*
 * Legge immobile_id dall'indirizzo.
 *
 * Esempio:
 * servizi-immobile.php?immobile_id=1
 */
$immobileId = positiveInteger(
    $_GET['immobile_id'] ?? null
);

if ($immobileId === null) {
    jsonResponse(
        [
            'errore' =>
                'immobile_id deve essere un intero positivo.',
        ],
        400
    );
}

/*
 * Controlla che l'immobile appartenga all'host.
 */
$statementImmobile = $connection->prepare(
    'SELECT
        id,
        titolo,
        attivo
     FROM immobili
     WHERE id = :immobile_id
       AND host_id = :host_id
     LIMIT 1'
);

$statementImmobile->execute([
    'immobile_id' => $immobileId,
    'host_id' => $host['id'],
]);

$immobile = $statementImmobile->fetch();

if ($immobile === false) {
    jsonResponse(
        [
            'errore' =>
                'Immobile non trovato o non appartenente all host autenticato.',
        ],
        404
    );
}

/*
 * GET:
 * restituisce tutti i servizi disponibili e indica
 * quali sono associati all'immobile.
 */
if (requestMethod() === 'GET') {
    $statementServizi = $connection->prepare(
        'SELECT
            servizi.id,
            servizi.nome,
            CASE
                WHEN immobili_servizi.immobile_id IS NULL
                    THEN FALSE
                ELSE TRUE
            END AS selezionato
         FROM servizi
         LEFT JOIN immobili_servizi
            ON immobili_servizi.servizio_id = servizi.id
           AND immobili_servizi.immobile_id = :immobile_id
         ORDER BY servizi.nome ASC'
    );

    $statementServizi->execute([
        'immobile_id' => $immobileId,
    ]);

    $servizi = $statementServizi->fetchAll();

    foreach ($servizi as &$servizio) {
        $servizio['id'] = (int) $servizio['id'];
        $servizio['selezionato'] =
            (bool) $servizio['selezionato'];
    }

    unset($servizio);

    jsonResponse(
        [
            'successo' => true,
            'immobile' => [
                'id' => (int) $immobile['id'],
                'titolo' => $immobile['titolo'],
            ],
            'servizi' => $servizi,
        ]
    );
}

/*
 * PUT:
 * sostituisce tutti i servizi dell'immobile.
 *
 * Corpo JSON:
 * {
 *     "servizi_ids": [1, 2, 3]
 * }
 */
$data = requestBody();

$serviziIdsRicevuti = $data['servizi_ids'] ?? null;

if (!is_array($serviziIdsRicevuti)) {
    jsonResponse(
        [
            'errore' =>
                'servizi_ids deve essere un array.',
        ],
        400
    );
}

/*
 * Converte gli ID in interi positivi
 * e rimuove eventuali duplicati.
 */
$serviziIds = [];

foreach ($serviziIdsRicevuti as $servizioIdRicevuto) {
    $servizioId = positiveInteger($servizioIdRicevuto);

    if ($servizioId === null) {
        jsonResponse(
            [
                'errore' =>
                    'Ogni elemento di servizi_ids deve essere un intero positivo.',
            ],
            400
        );
    }

    $serviziIds[] = $servizioId;
}

$serviziIds = array_values(
    array_unique($serviziIds)
);

try {
    $connection->beginTransaction();

    /*
     * Controlla che tutti gli ID ricevuti
     * corrispondano a servizi esistenti.
     */
    $serviziValidi = [];

    if (count($serviziIds) > 0) {
        $segnaposto = implode(
            ',',
            array_fill(0, count($serviziIds), '?')
        );

        $statementVerifica = $connection->prepare(
            "SELECT id, nome
             FROM servizi
             WHERE id IN ($segnaposto)
             ORDER BY nome ASC"
        );

        $statementVerifica->execute($serviziIds);

        $serviziValidi = $statementVerifica->fetchAll();

        if (count($serviziValidi) !== count($serviziIds)) {
            $connection->rollBack();

            jsonResponse(
                [
                    'errore' =>
                        'Uno o più servizi indicati non esistono.',
                ],
                404
            );
        }
    }

    /*
     * Elimina le associazioni precedenti.
     */
    $statementEliminazione = $connection->prepare(
        'DELETE FROM immobili_servizi
         WHERE immobile_id = :immobile_id'
    );

    $statementEliminazione->execute([
        'immobile_id' => $immobileId,
    ]);

    /*
     * Inserisce le nuove associazioni.
     */
    if (count($serviziIds) > 0) {
        $statementInserimento = $connection->prepare(
            'INSERT INTO immobili_servizi (
                immobile_id,
                servizio_id
            ) VALUES (
                :immobile_id,
                :servizio_id
            )'
        );

        foreach ($serviziIds as $servizioId) {
            $statementInserimento->execute([
                'immobile_id' => $immobileId,
                'servizio_id' => $servizioId,
            ]);
        }
    }

    $connection->commit();

    jsonResponse(
        [
            'successo' => true,
            'messaggio' =>
                'Servizi dell immobile aggiornati correttamente.',
            'immobile' => [
                'id' => (int) $immobile['id'],
                'titolo' => $immobile['titolo'],
            ],
            'servizi' => $serviziValidi,
        ]
    );
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}