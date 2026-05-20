<?php
/**
 * db.php — SQLite3 database connection (PDO)
 * The database file lives inside the project folder,
 * so it travels with you to any PC — no MySQL/phpMyAdmin needed.
 */

define('DB_PATH', __DIR__ . '/database.sqlite');

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

        $pdo->exec("PRAGMA journal_mode = WAL");
        $pdo->exec("PRAGMA foreign_keys = ON");
        $pdo->exec("PRAGMA synchronous = NORMAL");

    } catch (PDOException $e) {
        die('<div style="font-family:sans-serif;padding:40px;color:#c0392b;max-width:600px;margin:auto;">
            <h2>&#9888; Database Connection Failed</h2>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <p>Make sure the project folder is <strong>writable</strong>.</p>
            <p>&#8594; Run <a href="setup.php"><strong>setup.php</strong></a> first.</p>
        </div>');
    }

    return $pdo;
}
