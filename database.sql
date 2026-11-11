USE affitti_brevi;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS token_accesso;
DROP TABLE IF EXISTS immobili_servizi;
DROP TABLE IF EXISTS prenotazioni;
DROP TABLE IF EXISTS servizi;
DROP TABLE IF EXISTS immobili;
DROP TABLE IF EXISTS localita;
DROP TABLE IF EXISTS utenti;

SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE utenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    ruolo ENUM('host', 'ospite') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE localita (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;


CREATE TABLE immobili (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id INT UNSIGNED NOT NULL,
    localita_id INT UNSIGNED NOT NULL,
    titolo VARCHAR(150) NOT NULL,
    descrizione TEXT NOT NULL,
    indirizzo VARCHAR(200) NOT NULL,
    posti_letto TINYINT UNSIGNED NOT NULL,
    camere TINYINT UNSIGNED NOT NULL,
    bagni TINYINT UNSIGNED NOT NULL,
    prezzo_notte DECIMAL(10, 2) NOT NULL,
    attivo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_immobili_host
        FOREIGN KEY (host_id)
        REFERENCES utenti(id),

    CONSTRAINT fk_immobili_localita
        FOREIGN KEY (localita_id)
        REFERENCES localita(id),

    INDEX idx_immobili_ricerca (
        localita_id,
        attivo,
        prezzo_notte
    )
) ENGINE=InnoDB;



CREATE TABLE servizi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;


CREATE TABLE immobili_servizi (
    immobile_id INT UNSIGNED NOT NULL,
    servizio_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (immobile_id, servizio_id),

    CONSTRAINT fk_immobili_servizi_immobile
        FOREIGN KEY (immobile_id)
        REFERENCES immobili(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_immobili_servizi_servizio
        FOREIGN KEY (servizio_id)
        REFERENCES servizi(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE prenotazioni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    immobile_id INT UNSIGNED NOT NULL,
    ospite_id INT UNSIGNED NOT NULL,
    data_arrivo DATE NOT NULL,
    data_partenza DATE NOT NULL,
    numero_ospiti TINYINT UNSIGNED NOT NULL,
    totale DECIMAL(10, 2) NOT NULL,
    stato ENUM(
        'in_attesa',
        'confermata',
        'annullata'
    ) NOT NULL DEFAULT 'in_attesa',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_prenotazioni_immobile
        FOREIGN KEY (immobile_id)
        REFERENCES immobili(id),

    CONSTRAINT fk_prenotazioni_ospite
        FOREIGN KEY (ospite_id)
        REFERENCES utenti(id),

    INDEX idx_prenotazioni_disponibilita (
        immobile_id,
        data_arrivo,
        data_partenza,
        stato
    ),

    INDEX idx_prenotazioni_ospite (
        ospite_id
    )
) ENGINE=InnoDB;



CREATE TABLE token_accesso (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utente_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    scadenza DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_token_utente
        FOREIGN KEY (utente_id)
        REFERENCES utenti(id)
        ON DELETE CASCADE,

    INDEX idx_token_verifica (
        token_hash,
        scadenza
    )
) ENGINE=InnoDB;


INSERT INTO localita (nome)
VALUES
    ('Perugia'),
    ('Assisi'),
    ('Spoleto'),
    ('Orvieto'),
    ('Terni');



INSERT INTO utenti (
    nome,
    email,
    password_hash,
    ruolo
)
VALUES
    (
        'Host Uno',
        'host1@example.com',
        '$2y$12$ODvlK5xq0reU/PNsXwThkOAncKvI4dQ4v5Drqc1LnC.TdSfpPkRYa',
        'host'
    ),
    (
        'Host Due',
        'host2@example.com',
        '$2y$12$ODvlK5xq0reU/PNsXwThkOAncKvI4dQ4v5Drqc1LnC.TdSfpPkRYa',
        'host'
    ),
    (
        'Ospite Uno',
        'ospite1@example.com',
        '$2y$12$ODvlK5xq0reU/PNsXwThkOAncKvI4dQ4v5Drqc1LnC.TdSfpPkRYa',
        'ospite'
    ),
    (
        'Ospite Due',
        'ospite2@example.com',
        '$2y$12$ODvlK5xq0reU/PNsXwThkOAncKvI4dQ4v5Drqc1LnC.TdSfpPkRYa',
        'ospite'
    );


INSERT INTO immobili (
    host_id,
    localita_id,
    titolo,
    descrizione,
    indirizzo,
    posti_letto,
    camere,
    bagni,
    prezzo_notte
)
VALUES
    (
        1,
        1,
        'Monolocale nel centro di Perugia',
        'Monolocale vicino al centro storico, adatto a una o due persone.',
        'Via Roma 1, Perugia',
        2,
        1,
        1,
        45.00
    ),
    (
        1,
        2,
        'Casa vicino alla Basilica',
        'Casa luminosa situata a breve distanza dal centro di Assisi.',
        'Via San Francesco 10, Assisi',
        4,
        2,
        1,
        90.00
    ),
    (
        1,
        3,
        'Appartamento nel centro storico',
        'Appartamento adatto a una coppia o a una piccola famiglia.',
        'Corso Mazzini 3, Spoleto',
        3,
        2,
        1,
        75.00
    ),
    (
        2,
        1,
        'Villa con giardino',
        'Villa spaziosa con giardino e parcheggio privato.',
        'Via del Verde 8, Perugia',
        8,
        4,
        3,
        180.00
    ),
    (
        2,
        4,
        'Stanza panoramica a Orvieto',
        'Stanza con vista, bagno privato e aria condizionata.',
        'Via degli Ulivi 4, Orvieto',
        2,
        1,
        1,
        55.00
    ),
    (
        2,
        5,
        'Casa per famiglie',
        'Casa con cucina completa e spazi adatti alle famiglie.',
        'Via del Centro 20, Terni',
        6,
        3,
        2,
        130.00
    ); 



INSERT INTO servizi (nome)
VALUES
    ('Wi-Fi'),
    ('Parcheggio'),
    ('Cucina'),
    ('Aria condizionata'),
    ('Animali ammessi');


INSERT INTO immobili_servizi (
    immobile_id,
    servizio_id
)
VALUES
    (1, 1),
    (1, 3),

    (2, 1),
    (2, 2),
    (2, 3),

    (3, 1),
    (3, 3),

    (4, 1),
    (4, 2),
    (4, 3),
    (4, 4),
    (4, 5),

    (5, 1),
    (5, 4),

    (6, 1),
    (6, 2),
    (6, 3);  


INSERT INTO prenotazioni (
    immobile_id,
    ospite_id,
    data_arrivo,
    data_partenza,
    numero_ospiti,
    totale,
    stato
)
VALUES
    (
        1,
        3,
        '2027-06-10',
        '2027-06-15',
        2,
        225.00,
        'confermata'
    ),
    (
        1,
        4,
        '2027-06-15',
        '2027-06-18',
        1,
        135.00,
        'in_attesa'
    ),
    (
        2,
        3,
        '2027-07-01',
        '2027-07-04',
        3,
        270.00,
        'annullata'
    ),
    (
        4,
        4,
        '2027-08-10',
        '2027-08-17',
        4,
        1260.00,
        'confermata'
    );