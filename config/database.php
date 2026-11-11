<?php

declare(strict_types=1);

/**
 * Restituisce un'unica connessione PDO al database.
 */
function db(): PDO
{
    static $connection = null;

    if ($connection === null) {
        $host = 'localhost';
        $database = 'affitti_brevi';
        $username = 'affitti_user';
        $password = 'CambiaQuestaPassword_2026!';

        $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

        $connection = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $connection;
}