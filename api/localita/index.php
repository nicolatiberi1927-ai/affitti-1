<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET');

$connection = db();

$statement = $connection->query(
    'SELECT
        id,
        nome
     FROM localita
     ORDER BY nome ASC'
);

$localita = $statement->fetchAll();

foreach ($localita as &$elemento) {
    $elemento['id'] = (int) $elemento['id'];
}

unset($elemento);

jsonResponse(
    [
        'successo' => true,
        'numero_risultati' => count($localita),
        'localita' => $localita,
    ]
);