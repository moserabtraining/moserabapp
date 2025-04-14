//
//  db_connect.php
//  Moserab
//
//  Created by Von Lemberg Tatjana on 14.04.25.
//
// backend/db_connect.php

require_once 'config.php';

function getDbConnection() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Logging (nicht im Produktionssystem die tatsächliche Fehlermeldung ausgeben)
        error_log('Database connection error: ' . $e->getMessage());
        return null;
    }
}

?>
