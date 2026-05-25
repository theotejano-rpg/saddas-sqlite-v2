<?php
$nav_active = 'community';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Community &mdash; UC CCS Student Portal</title>

  <script>
    (function(){
      try {
        var t = localStorage.getItem('saddas_theme') ||
          (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (t === 'dark') document.documentElement.className = 'dark-theme';
      } catch(e){}
    })();
  </script>

  <link rel="stylesheet" href="css/Style.css"/>

  <style id="dark-overrides">
    html.dark-theme body { background: #071026 !important; color: #ffffff !important; }
    html.dark-theme .site-nav {
      background: #0f1724 !important;
      border-bottom: 1px solid rgba(255,255,255,0.05) !important;
    }
    html.dark-theme .nav-left-text strong { color: #ffffff !important; }
    html.dark-theme .nav-left-text small  { color: #9aa5b4 !important; }
    html.dark-theme .nav-link             { color: #ffffff !important; }
    html.dark-theme .nav-btn.ghost        { color: #ffffff !important; border-color: rgba(255,255,255,0.2) !important; background: transparent !important; }
    html.dark-theme .nav-btn.solid        { background: #6b21c8 !important; color: #ffffff !important; }
    html.dark-theme footer                { background: #0f1724 !important; color: #9aa5b4 !important; border-top-color: rgba(255,255,255,0.04) !important; }
    html.dark-theme footer a              { color: #9aa5b4 !important; }
    .landing-theme-toggle {
      border: none !important; box-shadow: none !important; outline: none !important; background: transparent !important;
    }
    .landing-theme-toggle:hover { background: rgba(10,77,140,0.08) !important; }
    html.dark-theme .landing-theme-toggle:hover { background: rgba(255,255,255,0.08) !important; }
    html.dark-theme .landing-theme-toggle svg { stroke: #ffffff !important; }

    /* Community dark mode */
    html.dark-theme .community-hero     { background: #0c1520 !important; border-bottom-color: rgba(255,255,255,0.04) !important; }
    html.dark-theme .community-hero h1  { color: #ffffff !important; }
    html.dark-theme .community-hero p   { color: #9aa5b4 !important; }
    html.dark-theme .community-section  { background: #071026 !important; }
    html.dark-theme .community-card {
      background: #0c1520 !important;
      border-color: rgba(255,255,255,0.06) !important;
      box-shadow: 0 6px 24px rgba(2,6,23,0.4) !important;
    }
    html.dark-theme .community-card h3 { color: #93c5fd !important; }
    html.dark-theme .community-card p  { color: #9aa5b4 !important; }
    html.dark-theme .community-cta     { background: #0c1520 !important; border-color: rgba(255,255,255,0.06) !important; }
    html.dark-theme .community-cta h2  { color: #ffffff !important; }
    html.dark-theme .community-cta p   { color: #9aa5b4 !important; }
  </style>

  <style>
    /* ── Page hero ── */
    .community-hero {
      background: rgba(10,77,140,0.03);
      border-bottom: 1px solid rgba(204,222,237,0.4);
      padding: 52px 24px 44px;
      text-align: center;
    }
    .community-hero .eyebrow {
      font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
      letter-spacing: 1.2px; color: #0a4d8c; margin-bottom: 10px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .community-hero .eyebrow::before,
    .community-hero .eyebrow::after {
      content: ''; display: inline-block; width: 28px; height: 1.5px; background: #0a4d8c; border-radius: 2px;
    }
    .community-hero h1 {
      font-size: 2.2rem; font-weight: 900; color: #0a4d8c;
      margin: 0 0 12px; letter-spacing: -0.4px;
    }
    .community-hero p {
      font-size: 0.95rem; color: #6b7a8d; max-width: 520px; margin: 0 auto; line-height: 1.6;
    }

    /* ── Cards ── */
    .community-section {
      max-width: 980px; margin: 0 auto; padding: 52px 24px 64px;
    }
    .community-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 20px;
    }
    .community-card {
      background: white;
      border-radius: 18px;
      padding: 28px 24px;
      border: 1.5px solid rgba(204,222,237,0.5);
      box-shadow: 0 4px 18px rgba(10,77,140,0.07);
      transition: transform 0.2s;
    }
    .community-card:hover { transform: translateY(-4px); }
    .community-card-icon { font-size: 2rem; margin-bottom: 12px; }
    .community-card h3   { font-size: 1rem; font-weight: 800; color: #0a4d8c; margin: 0 0 8px; }
    .community-card p    { font-size: 0.85rem; color: #6b7a8d; line-height: 1.6; margin: 0; }

    /* ── CTA banner ── */
    .community-cta {
      max-width: 980px; margin: 0 auto 64px;
      padding: 36px 40px;
      background: white;
      border-radius: 20px;
      border: 1.5px solid rgba(204,222,237,0.5);
      box-shadow: 0 4px 18px rgba(10,77,140,0.07);
      display: flex; align-items: center; justify-content: space-between;
      gap: 24px; flex-wrap: wrap;
    }
    .community-cta h2 { font-size: 1.15rem; font-weight: 800; color: #0a4d8c; margin: 0 0 4px; }
    .community-cta p  { font-size: 0.85rem; color: #6b7a8d; margin: 0; }
    .community-cta .cta-btns { display: flex; gap: 12px; flex-shrink: 0; flex-wrap: wrap; }
    .cta-main {
      background: #0a4d8c; color: white; font-size: 0.85rem; font-weight: 700;
      padding: 10px 22px; border-radius: 8px; text-decoration: none;
      transition: background 0.2s;
    }
    .cta-main:hover { background: #083d6e; }
    .cta-ghost {
      background: transparent; color: #0a4d8c; font-size: 0.85rem; font-weight: 700;
      padding: 10px 22px; border-radius: 8px; text-decoration: none;
      border: 1.5px solid rgba(10,77,140,0.25);
      transition: background 0.2s;
    }
    .cta-ghost:hover { background: rgba(10,77,140,0.06); }

    @media (max-width: 640px) {
      .community-hero h1 { font-size: 1.6rem; }
      .community-cta { flex-direction: column; padding: 28px 24px; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/nav_landing.php'; ?>

<div class="community-hero">
  <div class="eyebrow">CCS Student Portal</div>
  <h1>🤝 Our Community</h1>
  <p>Connect, collaborate, and grow with fellow College of Computer Studies students.</p>
</div>

<div class="community-section">
  <div class="community-grid">
    <div class="community-card">
      <div class="community-card-icon">💻</div>
      <h3>Collaborative Learning</h3>
      <p>Work side-by-side with classmates during sit-in sessions. Share ideas, debug together, and build projects that matter.</p>
    </div>
    <div class="community-card">
      <div class="community-card-icon">📅</div>
      <h3>Organized Scheduling</h3>
      <p>Reserve your lab slot in advance and never miss a session. The portal keeps track of your sit-in history and remaining sessions.</p>
    </div>
    <div class="community-card">
      <div class="community-card-icon">🏆</div>
      <h3>Earn &amp; Compete</h3>
      <p>Every completed session earns you <strong>20 points</strong>. Climb the leaderboard and show the community what you're made of.</p>
    </div>
    <div class="community-card">
      <div class="community-card-icon">🔔</div>
      <h3>Stay in the Loop</h3>
      <p>Get real-time notifications about your reservations, session approvals, and announcements from the CCS laboratory staff.</p>
    </div>
    <div class="community-card">
      <div class="community-card-icon">🌟</div>
      <h3>Student Recognition</h3>
      <p>Top performers are spotlighted on the public leaderboard, celebrating dedication and consistent academic effort.</p>
    </div>
    <div class="community-card">
      <div class="community-card-icon">🛡️</div>
      <h3>Supervised Environment</h3>
      <p>All sessions are monitored by faculty and lab staff, ensuring a safe, productive, and distraction-free workspace for everyone.</p>
    </div>
  </div>
</div>

<div class="community-cta" style="padding-left:40px;padding-right:40px;margin-left:auto;margin-right:auto;max-width:980px;">
  <div>
    <h2>Ready to join the community?</h2>
    <p>Create your account and start reserving lab sessions today.</p>
  </div>
  <div class="cta-btns">
    <a href="Register.php" class="cta-main">Create Account</a>
    <a href="Login.php"    class="cta-ghost">Sign In</a>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>