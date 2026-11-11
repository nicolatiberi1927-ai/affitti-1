<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

requireMethod('GET');

$connection = db();

/*
 * Valori iniziali dei filtri.
 *
 * null significa che il filtro non è stato indicato.
 */
$localitaId = null;
$numeroOspiti = null;
$prezzoMassimo = null;
$dataArrivo = null;
$dataPartenza = null;


/*
 * Controllo di localita_id.
 *
 * Esempio:
 * ?localita_id=1
 */
if (isset($_GET['localita_id'])) {
    $localitaId = positiveInteger($_GET['localita_id']);

    if ($localitaId === null) {
        jsonResponse(
            [
                'errore' =>
                    'Il parametro localita_id deve essere un intero positivo.',
            ],
            400
        );
    }
}


/*
 * Controllo di numero_ospiti.
 *
 * Esempio:
 * ?numero_ospiti=4
 */
if (isset($_GET['numero_ospiti'])) {
    $numeroOspiti = positiveInteger($_GET['numero_ospiti']);

    if ($numeroOspiti === null) {
        jsonResponse(
            [
                'errore' =>
                    'Il parametro numero_ospiti deve essere un intero positivo.',
            ],
            400
        );
    }
}


/*
 * Controllo di prezzo_massimo.
 *
 * Sono accettati numeri interi e decimali
 * maggiori di zero.
 */
if (isset($_GET['prezzo_massimo'])) {
    $prezzoMassimo = filter_var(
        $_GET['prezzo_massimo'],
        FILTER_VALIDATE_FLOAT
    );

    if ($prezzoMassimo === false || $prezzoMassimo <= 0) {
        jsonResponse(
            [
                'errore' =>
                    'Il parametro prezzo_massimo deve essere un numero maggiore di zero.',
            ],
            400
        );
    }
}


/*
 * Le date devono essere fornite insieme.
 *
 * Non è consentito indicare soltanto la data di arrivo
 * oppure soltanto la data di partenza.
 */
$dataArrivoPresente = isset($_GET['data_arrivo']);
$dataPartenzaPresente = isset($_GET['data_partenza']);

if ($dataArrivoPresente !== $dataPartenzaPresente) {
    jsonResponse(
        [
            'errore' =>
                'I parametri data_arrivo e data_partenza devono essere indicati insieme.',
        ],
        400
    );
}


/*
 * Controllo delle date.
 */
if ($dataArrivoPresente && $dataPartenzaPresente) {
    $dataArrivo = $_GET['data_arrivo'];
    $dataPartenza = $_GET['data_partenza'];

    /*
     * Controlla che data_arrivo abbia realmente
     * il formato AAAA-MM-GG.
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
     * Controlla che data_partenza abbia realmente
     * il formato AAAA-MM-GG.
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
     * Non si può prenotare una data precedente a oggi.
     */
    $oggi = new DateTimeImmutable('today');

    if ($oggettoDataArrivo < $oggi) {
        jsonResponse(
            [
                'errore' =>
                    'La data di arrivo non può essere nel passato.',
            ],
            400
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
            400
        );
    }
}


/*
 * Query SQL di base.
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
        localita.id AS localita_id,
        localita.nome AS localita,
        utenti.id AS host_id,
        utenti.nome AS host
    FROM immobili
    INNER JOIN localita
        ON localita.id = immobili.localita_id
    INNER JOIN utenti
        ON utenti.id = immobili.host_id
    WHERE immobili.attivo = TRUE
';

$parameters = [];


/*
 * Filtro per località.
 */
if ($localitaId !== null) {
    $sql .= '
        AND immobili.localita_id = :localita_id
    ';

    $parameters['localita_id'] = $localitaId;
}


/*
 * Filtro per numero di ospiti.
 */
if ($numeroOspiti !== null) {
    $sql .= '
        AND immobili.posti_letto >= :numero_ospiti
    ';

    $parameters['numero_ospiti'] = $numeroOspiti;
}


/*
 * Filtro per prezzo massimo.
 */
if ($prezzoMassimo !== null) {
    $sql .= '
        AND immobili.prezzo_notte <= :prezzo_massimo
    ';

    $parameters['prezzo_massimo'] = $prezzoMassimo;
}


/*
 * Filtro per disponibilità.
 *
 * NOT EXISTS significa che non deve esistere
 * una prenotazione sovrapposta alle date richieste.
 *
 * Le prenotazioni annullate non bloccano l'immobile.
 */
if ($dataArrivo !== null && $dataPartenza !== null) {
    $sql .= '
        AND NOT EXISTS (
            SELECT 1
            FROM prenotazioni
            WHERE prenotazioni.immobile_id = immobili.id
              AND prenotazioni.stato IN (
                  \'in_attesa\',
                  \'confermata\'
              )
              AND prenotazioni.data_arrivo < :data_partenza
              AND prenotazioni.data_partenza > :data_arrivo
        )
    ';

    $parameters['data_arrivo'] = $dataArrivo;
    $parameters['data_partenza'] = $dataPartenza;
}


/*
 * Ordinamento dal prezzo più basso.
 */
$sql .= '
    ORDER BY immobili.prezzo_notte ASC, immobili.id ASC
';


/*
 * Preparazione ed esecuzione sicura della query.
 */
$statement = $connection->prepare($sql);
$statement->execute($parameters);

$immobili = $statement->fetchAll();


/*
 * Risposta JSON.
 */
jsonResponse(
    [
        'successo' => true,
        'numero_risultati' => count($immobili),
        'filtri' => [
            'localita_id' => $localitaId,
            'numero_ospiti' => $numeroOspiti,
            'prezzo_massimo' => $prezzoMassimo,
            'data_arrivo' => $dataArrivo,
            'data_partenza' => $dataPartenza,
        ],
        'immobili' => $immobili,
    ]
);