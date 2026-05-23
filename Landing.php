<?php
require_once __DIR__ . '/db.php';
$db = get_db();
$nav_active = 'home';

// 20 points per sit-in session
$leaders = $db->query("
    SELECT first_name, last_name, course_code, level, used,
           (used * 20) as points,
           ROW_NUMBER() OVER (ORDER BY used DESC) as rank
    FROM students
    WHERE used > 0
    ORDER BY used DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Student Portal</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <style>
    /* ── Leaderboard section ───────────────────────────────────── */
    .lb-section {
      max-width: 980px;
      margin: 56px auto 64px;
      padding: 0 24px;
    }

    .lb-heading {
      text-align: center;
      margin-bottom: 36px;
    }
    .lb-heading h2 {
      font-size: 1.6rem;
      font-weight: 900;
      color: #0a4d8c;
      margin: 0 0 6px;
      letter-spacing: -0.3px;
    }
    .lb-heading p {
      font-size: 0.85rem;
      color: #9aa5b4;
      margin: 0;
    }

    /* Top-3 podium cards */
    .lb-podium {
      display: flex;
      gap: 18px;
      justify-content: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .lb-card {
      background: white;
      border-radius: 20px;
      padding: 28px 26px 22px;
      flex: 1;
      min-width: 200px;
      max-width: 280px;
      box-shadow: 0 6px 24px rgba(10,77,140,0.08);
      border: 2px solid rgba(204,222,237,0.5);
      transition: transform 0.2s;
      position: relative;
      overflow: hidden;
    }
    .lb-card:hover { transform: translateY(-4px); }

    /* Rank-specific accent colours */
    .lb-card.rank-1 {
      border-color: #f6c90e;
      box-shadow: 0 8px 32px rgba(246,201,14,0.22);
    }
    .lb-card.rank-1::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 4px;
      background: linear-gradient(90deg, #f6c90e, #ffe066);
    }
    .lb-card.rank-2 {
      border-color: #b0b8c4;
      box-shadow: 0 6px 24px rgba(176,184,196,0.2);
    }
    .lb-card.rank-2::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 4px;
      background: linear-gradient(90deg, #a0aec0, #cbd5e0);
    }
    .lb-card.rank-3 {
      border-color: #e07b39;
      box-shadow: 0 6px 24px rgba(224,123,57,0.18);
    }
    .lb-card.rank-3::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 4px;
      background: linear-gradient(90deg, #e07b39, #f6ad6e);
    }

    .lb-card-icon {
      font-size: 2.2rem;
      line-height: 1;
      margin-bottom: 10px;
    }
    .lb-card-rank {
      font-size: 0.7rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 6px;
    }
    .lb-card-rank.gold   { color: #b8960c; }
    .lb-card-rank.silver { color: #6b7a8d; }
    .lb-card-rank.bronze { color: #b05a1e; }

    .lb-card-name {
      font-size: 1.05rem;
      font-weight: 800;
      color: #1a2535;
      margin-bottom: 3px;
      line-height: 1.25;
    }
    .lb-card-course {
      font-size: 0.74rem;
      color: #9aa5b4;
      margin-bottom: 18px;
    }
    .lb-card-stats {
      display: flex;
      justify-content: space-between;
      gap: 10px;
    }
    .lb-card-stat { }
    .lb-card-stat-val {
      font-size: 1.5rem;
      font-weight: 900;
      color: #0a4d8c;
      line-height: 1;
    }
    .lb-card-stat-lbl {
      font-size: 0.62rem;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #9aa5b4;
      margin-top: 3px;
    }

    /* Ranks 4–10 list */
    .lb-list {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 18px rgba(10,77,140,0.07);
      border: 1.5px solid rgba(204,222,237,0.45);
    }
    .lb-list-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 22px;
      border-bottom: 1px solid rgba(204,222,237,0.35);
      transition: background 0.15s;
    }
    .lb-list-row:last-child { border-bottom: none; }
    .lb-list-row:hover { background: rgba(10,77,140,0.03); }

    .lb-list-icon  { font-size: 1.4rem; line-height: 1; flex-shrink: 0; }
    .lb-list-rank  { width: 32px; font-weight: 800; color: #9aa5b4; font-size: 0.85rem; flex-shrink: 0; }
    .lb-list-name  { flex: 1; font-weight: 700; color: #1a2535; font-size: 0.9rem; }
    .lb-list-course{ font-size: 0.74rem; color: #9aa5b4; min-width: 56px; flex-shrink: 0; }
    .lb-list-sess  { font-size: 0.75rem; color: #9aa5b4; min-width: 76px; text-align: right; flex-shrink: 0; }
    .lb-list-pts   { font-weight: 800; color: #0a4d8c; font-size: 0.9rem; min-width: 68px; text-align: right; flex-shrink: 0; }

    .lb-empty {
      text-align: center;
      color: #9aa5b4;
      padding: 48px 20px;
      font-size: 0.9rem;
    }
    .lb-empty-icon { font-size: 2.4rem; margin-bottom: 12px; }
  </style>
</head>
<body class="landing">

<?php include __DIR__ . '/nav_landing.php'; ?>

  <section class="hero">
    <div class="hero-text">
      <p class="hero-eyebrow">College of Computer Studies</p>
      <h1>Have Project?<br/><em>Sitin Do Organize.</em></h1>
      <p>This is a SitIn website that allows CCS students to plan sitin schedule.</p>
      <div class="hero-actions">
        <a href="Register.php" class="cta-main">Create your account</a>
        <a href="Login.php"    class="cta-sub">Already enrolled? Sign in &rarr;</a>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-badge">
        <img class="logo-uc"  src="images/uclogo-removebg-preview-removebg-preview.png" alt="UC"/>
        <div class="badge-divider"></div>
        <img class="logo-ccs" src="images/csmainlogo-removebg-preview-removebg-preview.png" alt="CCS"/>
        <div class="badge-label">
          College of Computer Studies
          <small>Cebu City, Philippines &middot; Est. 1983</small>
        </div>
        <span class="badge-motto">Be Focused Be Devoted</span>
      </div>
    </div>
  </section>

  <!-- ── TOP STUDENTS LEADERBOARD (replaces features strip) ─────────────── -->
  <section class="lb-section" id="leaderboard">

    <div class="lb-heading">
      <h2>🏆 Top Students Leaderboard</h2>
      <p>Students earn <strong>20 points</strong> for every completed sit-in session.</p>
    </div>

    <?php if (empty($leaders)): ?>
      <div class="lb-list">
        <div class="lb-empty">
          <div class="lb-empty-icon">🏅</div>
          No sit-in sessions recorded yet. Be the first to earn points!
        </div>
      </div>

    <?php else: ?>

      <!-- Top 3 podium ──────────────────────────────────── -->
      <?php
        $rankIcons  = ['🏆', '🥈', '🥉'];
        $rankLabels = ['gold', 'silver', 'bronze'];
        $rankClass  = ['rank-1', 'rank-2', 'rank-3'];
        $top3 = array_slice($leaders, 0, 3);
        $rest = array_slice($leaders, 3);
      ?>
      <div class="lb-podium">
        <?php foreach ($top3 as $i => $s): ?>
        <div class="lb-card <?= $rankClass[$i] ?? '' ?>">
          <div class="lb-card-icon"><?= $rankIcons[$i] ?? '🏅' ?></div>
          <div class="lb-card-rank <?= $rankLabels[$i] ?? '' ?>">#<?= $s['rank'] ?> Place</div>
          <div class="lb-card-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
          <div class="lb-card-course"><?= htmlspecialchars($s['course_code'] ?: ($s['level'] ?? '')) ?></div>
          <div class="lb-card-stats">
            <div class="lb-card-stat">
              <div class="lb-card-stat-val"><?= $s['used'] ?></div>
              <div class="lb-card-stat-lbl">Sessions</div>
            </div>
            <div class="lb-card-stat" style="text-align:right;">
              <div class="lb-card-stat-val"><?= $s['points'] ?></div>
              <div class="lb-card-stat-lbl">Points</div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Rank 4+ list ──────────────────────────────────── -->
      <?php if (!empty($rest)): ?>
      <div class="lb-list">
        <?php foreach ($rest as $s): ?>
        <div class="lb-list-row">
          <div class="lb-list-icon">👏</div>
          <div class="lb-list-rank">#<?= $s['rank'] ?></div>
          <div class="lb-list-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
          <div class="lb-list-course"><?= htmlspecialchars($s['course_code'] ?: '') ?></div>
          <div class="lb-list-sess"><?= $s['used'] ?> sessions</div>
          <div class="lb-list-pts"><?= $s['points'] ?> pts</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </section>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>