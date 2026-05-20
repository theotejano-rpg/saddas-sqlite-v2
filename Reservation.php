<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['student'])) {
    header('Location: Login.php');
    exit;
}

$db   = get_db();
$stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['student']['id']]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header('Location: Login.php');
    exit;
}

$remaining = $student['sessions'] - $student['used'];
$errors    = [];
$success   = '';

$lab_rooms = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530'];
$purposes  = [
    'C / C++', 'Java', 'Python', 'PHP / Web Development',
    'Database (SQL)', 'Networking', 'Research / Thesis',
    'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_room = trim($_POST['lab_room'] ?? '');
    $purpose  = trim($_POST['purpose']  ?? '');
    $date_in  = trim($_POST['date_in']  ?? '');

    if ($lab_room === '') $errors['lab_room'] = 'Please select a lab room.';
    if ($purpose  === '') $errors['purpose']  = 'Please select a purpose.';
    if ($date_in  === '') $errors['date_in']  = 'Please select a date and time.';

    if (!isset($errors['lab_room']) && !in_array($lab_room, $lab_rooms))
        $errors['lab_room'] = 'Please select a valid lab room.';
    if (!isset($errors['purpose']) && !in_array($purpose, $purposes))
        $errors['purpose'] = 'Please select a valid purpose.';

    if (empty($errors) && $remaining <= 0) {
        $errors['general'] = 'You have no remaining sit-in sessions this semester.';
    }

    if (empty($errors)) {
        $active = $db->prepare("SELECT id FROM sitin_logs WHERE student_id = ? AND status IN ('active','pending') LIMIT 1");
        $active->execute([$student['id']]);
        if ($active->fetch()) {
            $errors['general'] = 'You already have an active sit-in session. Please complete it before making a new reservation.';
        }
    }

    if (empty($errors)) {
        $ins = $db->prepare("
            INSERT INTO sitin_logs (student_id, lab_room, purpose, date_in, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $ins->execute([$student['id'], $lab_room, $purpose, $date_in]);

        $db->prepare('UPDATE students SET used = used + 1 WHERE id = ?')
           ->execute([$student['id']]);

        $success = "Reservation submitted! Please wait for admin approval before your sit-in begins.";

        $stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
        $stmt->execute([$student['id']]);
        $student   = $stmt->fetch();
        $remaining = $student['sessions'] - $student['used'];
    }
}

$reservations = $db->prepare("
    SELECT * FROM sitin_logs
    WHERE student_id = ?
    ORDER BY date_in DESC
    LIMIT 10
");
$reservations->execute([$student['id']]);
$reservations = $reservations->fetchAll();

$nav_student_active = 'reservation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Reservation</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <link rel="stylesheet" href="css/Students.css"/>
  <link rel="stylesheet" href="css/Reservation.css"/>
</head>
<body class="student-page" style="display:flex;flex-direction:column;min-height:100vh;">

<?php include __DIR__ . '/nav_student.php'; ?>

<main class="reservation-main" style="flex:1;">

  
  <div class="res-page-header">
    <div class="res-page-header-text">
      <span class="section-eyebrow">SitIn Management</span>
      <h1 class="res-page-title">Reserve a Session</h1>
      <p class="res-page-sub">Book your laboratory sit-in slot below. You have <strong><?= $remaining ?></strong> session<?= $remaining !== 1 ? 's' : '' ?> remaining this semester.</p>
    </div>
    <div class="res-session-badge">
      <span class="rsb-num"><?= $remaining ?></span>
      <span class="rsb-label">Sessions Left</span>
    </div>
  </div>

  <div class="res-layout">

    
    <div class="res-form-col">
      <div class="res-card">
        <div class="res-card-header">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8"  y1="2" x2="8"  y2="6"/>
            <line x1="3"  y1="10" x2="21" y2="10"/>
          </svg>
          <span>New Reservation</span>
        </div>

        <?php if (!empty($errors['general'])): ?>
          <div class="res-alert res-alert--error">
            <span>&#10005;</span>
            <?= htmlspecialchars($errors['general']) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="res-alert res-alert--success">
            <span>&#10003;</span>
            <?= $success ?>
          </div>
        <?php endif; ?>

        <?php if ($remaining <= 0): ?>
          <div class="res-alert res-alert--warning">
            <span>&#9888;</span>
            You have used all your sit-in sessions for this semester.
          </div>
        <?php else: ?>

        <form method="POST" action="Reservation.php" class="res-form" novalidate>

          <div class="res-field <?= isset($errors['lab_room']) ? 'res-field--error' : '' ?>">
            <label for="lab_room">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
              </svg>
              Lab Room
            </label>
            <select id="lab_room" name="lab_room">
              <option value="">— Select a room —</option>
              <?php foreach ($lab_rooms as $room): ?>
                <option value="<?= $room ?>"<?= ($_POST['lab_room'] ?? '') === $room ? ' selected' : '' ?>>
                  <?= htmlspecialchars($room) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['lab_room'])): ?>
              <span class="res-field-msg"><?= htmlspecialchars($errors['lab_room']) ?></span>
            <?php endif; ?>
          </div>

          <div class="res-field <?= isset($errors['purpose']) ? 'res-field--error' : '' ?>">
            <label for="purpose">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="16 18 22 12 16 6"/>
                <polyline points="8 6 2 12 8 18"/>
              </svg>
              Purpose / Language
            </label>
            <select id="purpose" name="purpose">
              <option value="">— Select a purpose —</option>
              <?php foreach ($purposes as $p): ?>
                <option value="<?= $p ?>"<?= ($_POST['purpose'] ?? '') === $p ? ' selected' : '' ?>>
                  <?= htmlspecialchars($p) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['purpose'])): ?>
              <span class="res-field-msg"><?= htmlspecialchars($errors['purpose']) ?></span>
            <?php endif; ?>
          </div>

          <div class="res-field <?= isset($errors['date_in']) ? 'res-field--error' : '' ?>">
            <label for="date_in">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
              Date &amp; Time
            </label>
            <input type="datetime-local" id="date_in" name="date_in"
              value="<?= htmlspecialchars($_POST['date_in'] ?? '') ?>"
              min="<?= date('Y-m-d\TH:i') ?>"/>
            <?php if (isset($errors['date_in'])): ?>
              <span class="res-field-msg"><?= htmlspecialchars($errors['date_in']) ?></span>
            <?php endif; ?>
          </div>

          <div class="res-info-box">
            <strong>Reminders:</strong>
            <ul>
              <li>Each session is limited to <strong>3 hours</strong>.</li>
              <li>Bring your valid <strong>UC Student ID</strong>.</li>
              <li>Arrive on time — late arrivals may forfeit the slot.</li>
            </ul>
          </div>

          <button type="submit" class="res-submit-btn">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            Confirm Reservation
          </button>

        </form>
        <?php endif; ?>
      </div>
    </div>

    
    <div class="res-history-col">
      <div class="res-card">
        <div class="res-card-header">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="12 8 12 12 14 14"/>
            <path d="M3.05 11a9 9 0 1 1 .5 4"/>
            <polyline points="3 21 3 16 8 16"/>
          </svg>
          <span>My Recent Reservations</span>
        </div>

        <?php if (empty($reservations)): ?>
          <div class="res-empty">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" color="var(--rule)">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8"  y1="2" x2="8"  y2="6"/>
              <line x1="3"  y1="10" x2="21" y2="10"/>
            </svg>
            <p>No reservations yet.</p>
            <small>Your sit-in history will appear here.</small>
          </div>
        <?php else: ?>
          <div class="res-history-list">
            <?php foreach ($reservations as $log): ?>
            <div class="res-history-item">
              <div class="rhi-top">
                <span class="rhi-room"><?= htmlspecialchars($log['lab_room']) ?></span>
                <span class="rhi-status rhi-status--<?= $log['status'] ?>">
                  <?= ucfirst(htmlspecialchars($log['status'])) ?>
                </span>
              </div>
              <div class="rhi-purpose"><?= htmlspecialchars($log['purpose']) ?></div>
              <div class="rhi-date">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                <?= htmlspecialchars($log['date_in']) ?>
                <?php if ($log['date_out']): ?>
                  &rarr; <?= htmlspecialchars($log['date_out']) ?>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <a href="History.php" class="res-view-all">View full history &rarr;</a>
        <?php endif; ?>
      </div>

      
      <div class="res-summary-card">
        <div class="rsc-row">
          <span class="rsc-label">Total Sessions</span>
          <span class="rsc-val"><?= $student['sessions'] ?></span>
        </div>
        <div class="rsc-divider"></div>
        <div class="rsc-row">
          <span class="rsc-label">Sessions Used</span>
          <span class="rsc-val used"><?= $student['used'] ?></span>
        </div>
        <div class="rsc-divider"></div>
        <div class="rsc-row">
          <span class="rsc-label">Sessions Remaining</span>
          <span class="rsc-val remaining"><?= $remaining ?></span>
        </div>
        <div class="rsc-bar-wrap">
          <div class="rsc-bar">
            <div class="rsc-bar-fill" style="width:<?= ($student['sessions'] > 0) ? round(($student['used']/$student['sessions'])*100) : 0 ?>%"></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>