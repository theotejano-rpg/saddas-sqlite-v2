<?php
$nav_active = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Student Portal</title>
  <link rel="stylesheet" href="css/Style.css"/>
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

  <div class="features-strip">
    <div class="feature">
      <div class="feature-num">01</div>
      <h3>Plan A SitIn</h3>
      <p>View available rooms to SitIn.</p>
    </div>
    <div class="feature">
      <div class="feature-num">02</div>
      <h3>Permission</h3>
      <p>Process Permission of student's application.</p>
    </div>
    <div class="feature">
      <div class="feature-num">03</div>
      <h3>Observe SitIn Trials</h3>
      <p>Student's Recording System, how many times they can still SitIn.</p>
    </div>
    <div class="feature">
      <div class="feature-num">04</div>
      <h3>Announcements</h3>
      <p>Stay updated with official notices from the department, dean's office, and council.</p>
    </div>
  </div>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>