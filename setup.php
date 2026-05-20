<?php
/**
 * setup.php
 * Run this ONCE in your browser: http://localhost/saddas-main/setup.php
 * It creates all tables in database.sqlite and adds a default admin account.
 * DELETE this file after setup is complete.
 */
require_once __DIR__ . '/db.php';
$db = get_db();

// ── Create tables ─────────────────────────────────────────────────────────────
$db->exec("
CREATE TABLE IF NOT EXISTS students (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id  TEXT    NOT NULL UNIQUE,
    first_name  TEXT    NOT NULL,
    middle_name TEXT    DEFAULT '',
    last_name   TEXT    NOT NULL,
    course      TEXT    NOT NULL,
    course_code TEXT    DEFAULT '',
    level       TEXT    NOT NULL,
    email       TEXT    NOT NULL UNIQUE,
    address     TEXT    DEFAULT '',
    password    TEXT    NOT NULL,
    profile_pic TEXT    DEFAULT NULL,
    sessions    INTEGER NOT NULL DEFAULT 30,
    used        INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS admins (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT    NOT NULL UNIQUE,
    password   TEXT    NOT NULL,
    first_name TEXT    NOT NULL DEFAULT 'Admin',
    last_name  TEXT    NOT NULL DEFAULT 'User'
);

CREATE TABLE IF NOT EXISTS sitin_logs (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL REFERENCES students(id),
    lab_room   TEXT    NOT NULL,
    purpose    TEXT    NOT NULL,
    date_in    TEXT    NOT NULL DEFAULT (datetime('now')),
    date_out   TEXT    DEFAULT NULL,
    status     TEXT    NOT NULL DEFAULT 'pending',
    feedback   TEXT    DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS announcements (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    title     TEXT NOT NULL,
    body      TEXT NOT NULL,
    tag       TEXT DEFAULT NULL,
    posted_at TEXT NOT NULL DEFAULT (datetime('now'))
);
");

// ── Seed default admin (only if none exists) ──────────────────────────────────
$count = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ((int)$count === 0) {
    $hash = password_hash('admin1234', PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO admins (email, password, first_name, last_name) VALUES (?, ?, ?, ?)")
       ->execute(['admin@uc.edu.ph', $hash, 'Admin', 'User']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Setup — SADDAS</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .card { background: #fff; border-radius: 14px; padding: 40px 36px; max-width: 520px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,.1); }
    h2 { color: #27ae60; margin-top: 0; }
    code { background: #f0f0f0; padding: 2px 8px; border-radius: 4px; font-size: .9em; }
    .warn { background: #fff8e1; border-left: 4px solid #f39c12; border-radius: 6px; padding: 14px 18px; margin-top: 24px; font-size: .9em; }
    a.btn { display: inline-block; margin-top: 20px; padding: 10px 22px; background: #2980b9; color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; }
    ul { line-height: 2; }
  </style>
</head>
<body>
<div class="card">
  <h2>✅ Database setup complete!</h2>
  <p>SQLite database created: <code>database.sqlite</code> inside your project folder.</p>

  <strong>Tables created:</strong>
  <ul>
    <li>students</li>
    <li>admins</li>
    <li>sitin_logs</li>
    <li>announcements</li>
  </ul>

  <strong>Default Admin Account:</strong>
  <ul>
    <li>Email: <code>admin@uc.edu.ph</code></li>
    <li>Password: <code>admin1234</code></li>
  </ul>

  <a class="btn" href="Login.php">Go to Login →</a>

  <div class="warn">
    ⚠️ <strong>Delete <code>setup.php</code></strong> from your folder after setup — anyone who visits it can reset the database!
  </div>
</div>
</body>
</html>
