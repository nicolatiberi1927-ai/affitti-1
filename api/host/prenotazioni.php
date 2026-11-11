<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET');

$host = authenticatedUser('host');

$connection = db();

$statement = $connection->prepare(
    'SELECT
        prenotazioni.id,
        prenotazioni.immobile_id,
        immobili.titolo AS titolo_immobile,
        localita.nome AS localita,
        prenotazioni.ospite_id,
        utenti.nome AS nome_ospite,
        utenti.email AS email_ospite,
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
     INNER JOIN utenti
        ON utenti.id = prenotazioni.ospite_id
     WHERE immobili.host_id = :host_id
     ORDER BY
        prenotazioni.data_arrivo DESC,
        prenotazioni.id DESC'
);

$statement->execute([
    'host_id' => $host['id'],
]);

$prenotazioni = $statement->fetchAll();

jsonResponse(
    [
        'successo' => true,
        'host' => [
            'id' => $host['id'],
            'nome' => $host['nome'],
        ],
        'numero_risultati' => count($prenotazioni),
        'prenotazioni' => $prenotazioni,
    ]
);