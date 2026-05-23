<?php
session_start();
if (empty($_SESSION['student'])) {
    header("Location: Login.php");
    exit;
}

require_once __DIR__ . '/db.php';
$db         = get_db();
$student_id = $_SESSION['student']['id'];

// Ensure testimonials table exists
$db->exec("
    CREATE TABLE IF NOT EXISTS testimonials (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL REFERENCES students(id),
        message    TEXT    NOT NULL,
        status     TEXT    NOT NULL DEFAULT 'pending',
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )
");

$student = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
$student->execute([$student_id]);
$student = $student->fetch();

$nav_student_active = 'testimonials';
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (strlen($message) < 10) {
        $error_msg = 'Please write at least 10 characters.';
    } else {
        $stmt = $db->prepare("INSERT INTO testimonials (student_id, message, status, created_at) VALUES (?, ?, 'pending', datetime('now'))");
        $stmt->execute([$student_id, $message]);
        $success_msg = 'Your testimonial has been submitted and is pending approval!';
    }
}

$my_stmt = $db->prepare("SELECT * FROM testimonials WHERE student_id = ? ORDER BY created_at DESC");
$my_stmt->execute([$student_id]);
$my_testimonials = $my_stmt->fetchAll();

$approved = $db->query("
    SELECT t.*, s.first_name, s.last_name
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    WHERE t.status = 'approved'
    ORDER BY t.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Testimonials &mdash; UC CCS</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <style>
    .testi-page { max-width: 860px; margin: 40px auto; padding: 0 20px 60px; }

    .testi-section-title {
      font-size: 1.35rem; font-weight: 800;
      color: #0a4d8c; margin-bottom: 6px;
    }
    .testi-section-sub {
      font-size: 0.83rem; color: #9aa5b4; margin-bottom: 22px;
    }

    .testi-form-card {
      background: white;
      border: 1px solid rgba(204,222,237,0.7);
      border-radius: 16px;
      padding: 28px 28px 22px;
      margin-bottom: 38px;
      box-shadow: 0 4px 20px rgba(10,77,140,0.06);
    }
    .testi-form-card textarea {
      width: 100%; min-height: 110px;
      border: 1.5px solid rgba(204,222,237,0.9);
      border-radius: 10px; padding: 12px 14px;
      font-size: 0.92rem; font-family: inherit;
      color: #1a2535; resize: vertical;
      transition: border 0.18s;
      box-sizing: border-box;
    }
    .testi-form-card textarea:focus { outline: none; border-color: #0a4d8c; }
    .testi-submit-btn {
      margin-top: 12px;
      background: #0a4d8c; color: white;
      border: none; border-radius: 9px;
      padding: 10px 24px; font-size: 0.88rem;
      font-weight: 700; cursor: pointer;
      transition: background 0.18s;
    }
    .testi-submit-btn:hover { background: #083d70; }

    .testi-alert {
      padding: 11px 16px; border-radius: 9px;
      font-size: 0.83rem; font-weight: 600;
      margin-bottom: 16px;
    }
    .testi-alert.success { background: rgba(34,197,94,0.1); color: #15803d; }
    .testi-alert.error   { background: rgba(224,53,53,0.1);  color: #b91c1c; }

    .testi-my-table {
      width: 100%; border-collapse: collapse;
      margin-bottom: 38px; font-size: 0.85rem;
    }
    .testi-my-table th {
      text-align: left; padding: 10px 14px;
      background: rgba(10,77,140,0.05);
      color: #0a4d8c; font-weight: 700;
      font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.6px;
    }
    .testi-my-table td {
      padding: 11px 14px;
      border-bottom: 1px solid rgba(204,222,237,0.4);
      color: #1a2535; vertical-align: top;
    }
    .testi-my-table tr:last-child td { border-bottom: none; }

    .status-badge {
      display: inline-block; padding: 2px 10px;
      border-radius: 20px; font-size: 0.7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-badge.pending  { background: rgba(232,160,32,0.12); color: #a06010; }
    .status-badge.approved { background: rgba(34,197,94,0.12);  color: #15803d; }
    .status-badge.rejected { background: rgba(224,53,53,0.10);  color: #b91c1c; }

    .testi-cards-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 18px;
    }
    @media(max-width:600px){ .testi-cards-grid { grid-template-columns: 1fr; } }

    .testi-card {
      background: white;
      border: 1px solid rgba(204,222,237,0.7);
      border-radius: 14px; padding: 20px 22px;
      box-shadow: 0 3px 14px rgba(10,77,140,0.05);
    }
    .testi-card-msg {
      font-size: 0.88rem; color: #1a2535;
      line-height: 1.6; margin-bottom: 14px; font-style: italic;
    }
    .testi-card-msg::before { content: '\201C'; font-size: 1.4rem; color: #0a4d8c; line-height: 0; vertical-align: -6px; margin-right: 3px; }
    .testi-card-msg::after  { content: '\201D'; font-size: 1.4rem; color: #0a4d8c; line-height: 0; vertical-align: -6px; margin-left: 3px; }
    .testi-card-author { font-size: 0.78rem; font-weight: 700; color: #0a4d8c; }
    .testi-card-date   { font-size: 0.7rem; color: #9aa5b4; margin-top: 2px; }

    .testi-empty { text-align: center; color: #9aa5b4; font-size: 0.85rem; padding: 30px 0; }
    .divider-line { border: none; border-top: 1px solid rgba(204,222,237,0.6); margin: 36px 0; }
  </style>
</head>
<body class="student-page">
<?php include __DIR__ . '/nav_student.php'; ?>

<div class="testi-page">

  <div class="testi-section-title">📝 Share Your Testimonial</div>
  <div class="testi-section-sub">Tell us about your sit-in experience. Approved testimonials will be visible to all students.</div>

  <?php if ($success_msg): ?>
    <div class="testi-alert success">✓ <?= htmlspecialchars($success_msg) ?></div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
    <div class="testi-alert error">✗ <?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>

  <div class="testi-form-card">
    <form method="POST">
      <textarea name="message" placeholder="Write your experience here..." required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
      <br/>
      <button type="submit" class="testi-submit-btn">Submit Testimonial</button>
    </form>
  </div>

  <div class="testi-section-title">📋 My Submissions</div>
  <div class="testi-section-sub">Track the status of your submitted testimonials.</div>

  <?php if (empty($my_testimonials)): ?>
    <div class="testi-empty">You haven't submitted any testimonials yet.</div>
  <?php else: ?>
    <table class="testi-my-table">
      <thead>
        <tr>
          <th>Message</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($my_testimonials as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['message']) ?></td>
          <td><span class="status-badge <?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
          <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <hr class="divider-line"/>

  <div class="testi-section-title">💬 All Approved Testimonials</div>
  <div class="testi-section-sub">What fellow students are saying about the CCS Sit-In experience.</div>

  <?php if (empty($approved)): ?>
    <div class="testi-empty">No approved testimonials yet. Be the first!</div>
  <?php else: ?>
    <div class="testi-cards-grid">
      <?php foreach ($approved as $t): ?>
      <div class="testi-card">
        <div class="testi-card-msg"><?= htmlspecialchars($t['message']) ?></div>
        <div class="testi-card-author"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></div>
        <div class="testi-card-date"><?= date('M d, Y', strtotime($t['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>