<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('PATCH');

$host = authenticatedUser('host');

$data = requestBody();

$immobileId = positiveInteger(
    $data['immobile_id'] ?? null
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

$connection = db();

try {
    $connection->beginTransaction();

    /*
     * Cerca l'immobile solamente tra quelli
     * appartenenti all'host autenticato.
     */
    $statement = $connection->prepare(
        'SELECT
            id,
            titolo,
            attivo
         FROM immobili
         WHERE id = :immobile_id
           AND host_id = :host_id
         FOR UPDATE'
    );

    $statement->execute([
        'immobile_id' => $immobileId,
        'host_id' => $host['id'],
    ]);

    $immobile = $statement->fetch();

    if ($immobile === false) {
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
     * Se è già disattivato, non eseguiamo
     * un secondo aggiornamento inutile.
     */
    if (!(bool) $immobile['attivo']) {
        $connection->rollBack();

        jsonResponse(
            ['errore' => 'L immobile è già disattivato.'],
            409
        );
    }

    /*
     * Disattivazione logica.
     *
     * La riga rimane nel database, ma l'immobile
     * non appare più nelle ricerche pubbliche.
     */
    $statementAggiornamento = $connection->prepare(
        'UPDATE immobili
         SET attivo = FALSE
         WHERE id = :immobile_id
           AND host_id = :host_id'
    );

    $statementAggiornamento->execute([
        'immobile_id' => $immobileId,
        'host_id' => $host['id'],
    ]);

    $connection->commit();

    jsonResponse(
        [
            'successo' => true,
            'messaggio' => 'Immobile disattivato correttamente.',
            'immobile' => [
                'id' => (int) $immobile['id'],
                'titolo' => $immobile['titolo'],
                'attivo' => false,
            ],
        ]
    );
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}
