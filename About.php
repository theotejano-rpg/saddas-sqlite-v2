<?php
$nav_active = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>About &mdash; UC CCS Student Portal</title>

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

    /* About dark mode */
    html.dark-theme .about-hero      { background: #0c1520 !important; border-bottom-color: rgba(255,255,255,0.04) !important; }
    html.dark-theme .about-hero h1   { color: #ffffff !important; }
    html.dark-theme .about-hero p    { color: #9aa5b4 !important; }
    html.dark-theme .about-page      { background: #071026 !important; }
    html.dark-theme .about-text-block h3 { color: #93c5fd !important; }
    html.dark-theme .about-text-block p  { color: #9aa5b4 !important; }
    html.dark-theme .about-fact,
    html.dark-theme .about-step {
      background: #0c1520 !important;
      border-color: rgba(255,255,255,0.06) !important;
      box-shadow: 0 6px 24px rgba(2,6,23,0.4) !important;
    }
    html.dark-theme .about-fact-val        { color: #93c5fd !important; }
    html.dark-theme .about-fact-lbl        { color: #6b7a8d !important; }
    html.dark-theme .about-step-body strong { color: #ffffff !important; }
    html.dark-theme .about-step-body p      { color: #9aa5b4 !important; }
    html.dark-theme .about-step-num         { background: #1e3a5f !important; color: #93c5fd !important; }
    html.dark-theme .about-divider          { border-color: rgba(255,255,255,0.06) !important; }
    html.dark-theme .about-section-title    { color: #93c5fd !important; }
  </style>

  <style>
    /* ── Page hero ── */
    .about-hero {
      background: rgba(10,77,140,0.03);
      border-bottom: 1px solid rgba(204,222,237,0.4);
      padding: 52px 24px 44px;
      text-align: center;
    }
    .about-hero .eyebrow {
      font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
      letter-spacing: 1.2px; color: #0a4d8c; margin-bottom: 10px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .about-hero .eyebrow::before,
    .about-hero .eyebrow::after {
      content: ''; display: inline-block; width: 28px; height: 1.5px; background: #0a4d8c; border-radius: 2px;
    }
    .about-hero h1 {
      font-size: 2.2rem; font-weight: 900; color: #0a4d8c;
      margin: 0 0 12px; letter-spacing: -0.4px;
    }
    .about-hero p {
      font-size: 0.95rem; color: #6b7a8d; max-width: 520px; margin: 0 auto; line-height: 1.6;
    }

    /* ── Page body ── */
    .about-page {
      max-width: 980px; margin: 0 auto; padding: 52px 24px 64px;
    }

    .about-section-title {
      font-size: 1.1rem; font-weight: 800; color: #0a4d8c;
      margin: 0 0 20px; letter-spacing: -0.2px;
    }

    /* Intro layout */
    .about-layout {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 40px;
      align-items: start;
      margin-bottom: 48px;
    }
    .about-text-block h3 { font-size: 1.15rem; font-weight: 800; color: #0a4d8c; margin: 0 0 12px; }
    .about-text-block p  { font-size: 0.88rem; color: #4a5568; line-height: 1.7; margin: 0 0 10px; }

    /* Fact tiles */
    .about-facts {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      min-width: 240px;
    }
    .about-fact {
      background: white;
      border-radius: 14px;
      padding: 16px 18px;
      border: 1.5px solid rgba(204,222,237,0.5);
      box-shadow: 0 3px 12px rgba(10,77,140,0.06);
      text-align: center;
    }
    .about-fact-val { display: block; font-size: 1.4rem; font-weight: 900; color: #0a4d8c; line-height: 1.1; }
    .about-fact-lbl { display: block; font-size: 0.65rem; color: #9aa5b4; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

    /* Divider */
    .about-divider { border: none; border-top: 1.5px solid rgba(204,222,237,0.45); margin: 0 0 40px; }

    /* How it works steps */
    .about-steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }
    .about-step {
      display: flex;
      gap: 14px;
      align-items: flex-start;
      background: white;
      border-radius: 14px;
      padding: 20px 18px;
      border: 1.5px solid rgba(204,222,237,0.45);
      box-shadow: 0 3px 12px rgba(10,77,140,0.06);
    }
    .about-step-num {
      flex-shrink: 0;
      width: 32px; height: 32px;
      border-radius: 50%;
      background: #0a4d8c;
      color: white;
      font-size: 0.85rem; font-weight: 900;
      display: flex; align-items: center; justify-content: center;
    }
    .about-step-body strong { font-size: 0.9rem; color: #1a2535; display: block; margin-bottom: 4px; }
    .about-step-body p      { font-size: 0.8rem; color: #6b7a8d; margin: 0; line-height: 1.5; }

    @media (max-width: 640px) {
      .about-hero h1   { font-size: 1.6rem; }
      .about-layout    { grid-template-columns: 1fr; }
      .about-facts     { grid-template-columns: repeat(4, 1fr); }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/nav_landing.php'; ?>

<div class="about-hero">
  <div class="eyebrow">CCS Student Portal</div>
  <h1>📖 About SADDAS</h1>
  <p>Everything you need to know about the sit-in management system.</p>
</div>

<div class="about-page">

  <!-- What is SADDAS -->
  <div class="about-layout">
    <div class="about-text-block">
      <h3>What is SADDAS?</h3>
      <p>
        <strong>SADDAS</strong> (Student and Department Data and Attendance System) is the official sit-in
        management portal of the <strong>University of Cebu — College of Computer Studies</strong>.
        It streamlines the process of scheduling, tracking, and managing student sit-in sessions
        in the CCS computer laboratories.
      </p>
      <p>
        Students can log in, reserve a lab seat, view their session history, and monitor
        their points — all in one place. Lab administrators get a powerful dashboard to
        manage attendance records, approve reservations, and generate reports.
      </p>
      <p>
        Built specifically for CCS students, SADDAS promotes accountability, productivity,
        and healthy competition through its points-based leaderboard system.
      </p>
    </div>
    <div class="about-facts">
      <div class="about-fact">
        <span class="about-fact-val">20</span>
        <span class="about-fact-lbl">Points per session</span>
      </div>
      <div class="about-fact">
        <span class="about-fact-val">CCS</span>
        <span class="about-fact-lbl">College of Computer Studies</span>
      </div>
      <div class="about-fact">
        <span class="about-fact-val">UC</span>
        <span class="about-fact-lbl">University of Cebu</span>
      </div>
      <div class="about-fact">
        <span class="about-fact-val">1983</span>
        <span class="about-fact-lbl">Est. in Cebu City, PH</span>
      </div>
    </div>
  </div>

  <hr class="about-divider"/>

  <!-- How it works -->
  <p class="about-section-title">How it works</p>
  <div class="about-steps">
    <div class="about-step">
      <div class="about-step-num">1</div>
      <div class="about-step-body">
        <strong>Register an account</strong>
        <p>Sign up with your student ID and course details to get started.</p>
      </div>
    </div>
    <div class="about-step">
      <div class="about-step-num">2</div>
      <div class="about-step-body">
        <strong>Reserve a lab slot</strong>
        <p>Choose your preferred date, time, and lab to book a sit-in session.</p>
      </div>
    </div>
    <div class="about-step">
      <div class="about-step-num">3</div>
      <div class="about-step-body">
        <strong>Attend &amp; earn points</strong>
        <p>Show up, complete your session, and watch your leaderboard rank rise.</p>
      </div>
    </div>
    <div class="about-step">
      <div class="about-step-num">4</div>
      <div class="about-step-body">
        <strong>Track your history</strong>
        <p>Review all past sessions and feedback through your personal dashboard.</p>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>