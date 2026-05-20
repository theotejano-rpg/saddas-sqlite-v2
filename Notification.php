<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['student'])) { header('Location: Login.php'); exit; }

$db = get_db();

$announcements = $db->query("SELECT * FROM announcements ORDER BY posted_at DESC")->fetchAll();

$tag_cls_map = ['Academics'=>'tag--blue','Sit-In'=>'tag--violet','Event'=>'tag--gold','General'=>'tag--blue'];

$nav_student_active = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Notifications</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <link rel="stylesheet" href="css/Students.css"/>
  <style>
    body.student-page a { text-decoration: none !important; }

    .notif-wrap {
      max-width: 780px;
      margin: 32px auto 0;
      padding: 0 28px 60px;
      flex: 1;
    }

    .notif-card {
      background: rgba(255,255,255,0.82);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(10,77,140,0.08);
      overflow: hidden;
    }

    .notif-card-header {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 16px 22px;
      background: var(--blue-deep);
      color: white;
      font-family: 'DM Serif Display', serif;
      font-size: 1rem;
    }

    .notif-item {
      padding: 18px 22px;
      border-bottom: 1px solid rgba(204,222,237,0.4);
      display: flex;
      gap: 16px;
      align-items: flex-start;
      transition: background 0.15s;
    }

    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: rgba(10,77,140,0.02); }

    .notif-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(10,77,140,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--blue-deep);
    }

    .notif-icon.gold   { background: rgba(232,160,32,0.12); color: #a06010; }
    .notif-icon.violet { background: rgba(107,33,200,0.1);  color: #6b21c8; }
    .notif-icon.blue   { background: rgba(10,77,140,0.08);  color: #0a4d8c; }

    .notif-content { flex: 1; }

    .notif-meta {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 4px;
    }

    .notif-tag {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding: 2px 8px;
      border-radius: 20px;
    }

    .notif-tag.tag--blue   { background: rgba(10,77,140,0.1);   color: #0a4d8c; }
    .notif-tag.tag--violet { background: rgba(107,33,200,0.1);  color: #6b21c8; }
    .notif-tag.tag--gold   { background: rgba(232,160,32,0.12); color: #a06010; }

    .notif-date {
      font-size: 0.72rem;
      color: var(--ink-soft);
      margin-left: auto;
    }

    .notif-title {
      font-weight: 600;
      font-size: 0.92rem;
      color: var(--ink);
      margin-bottom: 4px;
    }

    .notif-body {
      font-size: 0.82rem;
      color: var(--ink-soft);
      line-height: 1.55;
    }

    .notif-empty {
      padding: 60px 24px;
      text-align: center;
      color: var(--ink-soft);
    }

    .notif-empty svg { margin-bottom: 14px; opacity: 0.3; }
    .notif-empty p { font-size: 0.9rem; margin: 0; }
  </style>
</head>
<body class="student-page" style="display:flex;flex-direction:column;min-height:100vh;">

<?php include __DIR__ . '/nav_student.php'; ?>

<div class="notif-wrap">
  <span class="section-eyebrow">My Account</span>
  <h2 class="section-title">Notifications</h2>

  <div class="notif-card">
    <div class="notif-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Announcements & Notifications
      <span style="margin-left:auto;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:20px;font-size:0.72rem;"><?= count($announcements) ?> total</span>
    </div>

    <?php if (empty($announcements)): ?>
      <div class="notif-empty">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <p>No notifications yet. Check back later!</p>
      </div>
    <?php else: ?>
      <?php foreach ($announcements as $ann):
        $tag_cls = $tag_cls_map[$ann['tag']] ?? 'tag--blue';
        $icon_cls = $tag_cls === 'tag--gold' ? 'gold' : ($tag_cls === 'tag--violet' ? 'violet' : 'blue');
        $posted = date('M d, Y', strtotime($ann['posted_at']));
      ?>
      <div class="notif-item">
        <div class="notif-icon <?= $icon_cls ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
        </div>
        <div class="notif-content">
          <div class="notif-meta">
            <span class="notif-tag <?= $tag_cls ?>"><?= htmlspecialchars($ann['tag']) ?></span>
            <span class="notif-date"><?= $posted ?></span>
          </div>
          <div class="notif-title"><?= htmlspecialchars($ann['title']) ?></div>
          <div class="notif-body"><?= htmlspecialchars($ann['body']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>