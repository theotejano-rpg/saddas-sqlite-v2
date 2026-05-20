<?php
$nav_active = $nav_active ?? 'home';
?>
<nav class="site-nav">
  <div class="nav-left">
    <img src="images/uclogo-removebg-preview-removebg-preview.png" alt="University of Cebu"/>
    <div class="nav-left-text">
      <strong>CCS Student Portal</strong>
      <small>University of Cebu</small>
    </div>
  </div>

  <div class="nav-links">
    <a href="Landing.php" class="nav-link <?= $nav_active === 'home' ? 'active' : '' ?>">Home</a>
    <a href="Landing.php#community" class="nav-link">Community</a>
    <a href="Landing.php#about" class="nav-link">About</a>
  </div>

  <div class="nav-right">
    <a href="Login.php"    class="nav-btn ghost <?= $nav_active === 'login'    ? 'active' : '' ?>">Log In</a>
    <a href="Register.php" class="nav-btn solid <?= $nav_active === 'register' ? 'active' : '' ?>">Register</a>
  </div>
</nav>