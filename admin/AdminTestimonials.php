<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../Login.php");
    exit;
}

require_once __DIR__ . '/../db.php';
$db = get_db();
$nav_admin_active = 'testimonials';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int) $_POST['id'];
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    $stmt   = $db->prepare("UPDATE testimonials SET status = ? WHERE id = ?");
    $stmt->execute([$action, $id]);
    header("Location: AdminTestimonials.php");
    exit;
}

$pending = $db->query("
    SELECT t.*, s.firstname, s.lastname, s.idno
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC
")->fetchAll();

$all = $db->query("
    SELECT t.*, s.firstname, s.lastname, s.idno
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    ORDER BY t.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Testimonials &mdash; Admin</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <style>
    .at-page { max-width: 900px; margin: 40px auto; padding: 0 20px 60px; }

    .at-section-title {
      font-size: 1.3rem; font-weight: 800;
      color: #0a4d8c; margin-bottom: 6px;
    }
    .at-section-sub {
      font-size: 0.83rem; color: #9aa5b4; margin-bottom: 22px;
    }

    .at-table {
      width: 100%; border-collapse: collapse;
      font-size: 0.85rem; margin-bottom: 42px;
    }
    .at-table th {
      text-align: left; padding: 10px 14px;
      background: rgba(10,77,140,0.05);
      color: #0a4d8c; font-weight: 700;
      font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.6px;
    }
    .at-table td {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(204,222,237,0.4);
      color: #1a2535; vertical-align: top;
    }
    .at-table tr:last-child td { border-bottom: none; }
    .at-table tr:hover td { background: rgba(10,77,140,0.02); }

    .status-badge {
      display: inline-block; padding: 2px 10px;
      border-radius: 20px; font-size: 0.7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-badge.pending  { background: rgba(232,160,32,0.12); color: #a06010; }
    .status-badge.approved { background: rgba(34,197,94,0.12);  color: #15803d; }
    .status-badge.rejected { background: rgba(224,53,53,0.10);  color: #b91c1c; }

    .at-action-btn {
      border: none; border-radius: 7px;
      padding: 6px 14px; font-size: 0.78rem;
      font-weight: 700; cursor: pointer;
      transition: opacity 0.15s; margin-right: 5px;
    }
    .at-action-btn.approve { background: rgba(34,197,94,0.15); color: #15803d; }
    .at-action-btn.approve:hover { background: rgba(34,197,94,0.28); }
    .at-action-btn.reject  { background: rgba(224,53,53,0.12);  color: #b91c1c; }
    .at-action-btn.reject:hover  { background: rgba(224,53,53,0.22); }

    .at-empty { text-align: center; color: #9aa5b4; font-size: 0.85rem; padding: 30px 0; }
    .divider-line { border: none; border-top: 1px solid rgba(204,222,237,0.6); margin: 36px 0; }
  </style>
</head>
<body class="admin-page">
<?php include __DIR__ . '/nav_admin.php'; ?>

<div class="at-page">

  <div class="at-section-title">⏳ Pending Testimonials</div>
  <div class="at-section-sub">Review and approve or reject student testimonials before they go public.</div>

  <?php if (empty($pending)): ?>
    <div class="at-empty">No pending testimonials right now.</div>
  <?php else: ?>
    <table class="at-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>ID No.</th>
          <th>Message</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname']) ?></td>
          <td><?= htmlspecialchars($t['idno']) ?></td>
          <td><?= htmlspecialchars($t['message']) ?></td>
          <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
              <button type="submit" name="action" value="approve" class="at-action-btn approve">✓ Approve</button>
              <button type="submit" name="action" value="reject"  class="at-action-btn reject">✗ Reject</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <hr class="divider-line"/>

  <div class="at-section-title">📋 All Testimonials</div>
  <div class="at-section-sub">Full history of all submitted testimonials and their statuses.</div>

  <?php if (empty($all)): ?>
    <div class="at-empty">No testimonials submitted yet.</div>
  <?php else: ?>
    <table class="at-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>ID No.</th>
          <th>Message</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($all as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname']) ?></td>
          <td><?= htmlspecialchars($t['idno']) ?></td>
          <td><?= htmlspecialchars($t['message']) ?></td>
          <td><span class="status-badge <?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
          <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>