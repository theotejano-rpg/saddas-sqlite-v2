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

        // Auto-create notification_rules table and seed default rules if missing
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notification_rules (
                type    TEXT PRIMARY KEY,
                link    TEXT NOT NULL,
                label   TEXT NOT NULL
            )
        ");
        $existing = $pdo->query("SELECT COUNT(*) FROM notification_rules")->fetchColumn();
        if ($existing == 0) {
            $seed = $pdo->prepare("INSERT OR IGNORE INTO notification_rules (type, link, label) VALUES (?,?,?)");
            foreach ([
                ['reservation',          'AdminReservation.php',  'Reservation'],
                ['testimonial',          'AdminTestimonials.php', 'Testimonial Submitted'],
                ['testimonial_decision', 'AdminTestimonials.php', 'Testimonial Decision'],
                ['registration',         'AdminStudents.php',     'New Student Registration'],
                ['sitin_approved',       'AdminSitin.php',        'Sit-in Approved'],
                ['sitin_rejected',       'AdminSitin.php',        'Sit-in Rejected'],
                ['general',              'admin.php',             'General'],
            ] as $r) {
                $seed->execute($r);
            }
        }

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

/**
 * log_notification($type, $message)
 * Logs a notification. The redirect link is looked up automatically
 * from the notification_rules table — no hardcoding needed.
 *
 * Usage: log_notification('reservation', "John (UC-00001) reserved PC #3.");
 */
function log_notification(string $type, string $message): void
{
    $db = get_db();
    $rule = $db->prepare("SELECT link FROM notification_rules WHERE type = ? LIMIT 1");
    $rule->execute([$type]);
    $link = $rule->fetchColumn() ?: 'admin.php';
    $db->prepare("INSERT INTO notifications (type, message, link) VALUES (?, ?, ?)")
       ->execute([$type, $message, $link]);
}