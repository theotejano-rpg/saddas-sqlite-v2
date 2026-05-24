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
    <button id="themeToggleLanding" class="landing-theme-toggle" title="Toggle theme" aria-pressed="false" style="border:none!important;background:transparent!important;box-shadow:none!important;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
    </button>
  </div>
</nav>

<script>
(function () {
  var KEY = 'saddas_theme';
  var sunSVG  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>';
  var moonSVG = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

  function getSaved() {
    try { return localStorage.getItem(KEY); } catch(e) { return null; }
  }

  function applyTheme(theme) {
    var isDark = theme === 'dark';

    // Toggle class on both html and body
    document.documentElement.classList.toggle('dark-theme', isDark);
    document.body.classList.toggle('dark-theme', isDark);

    // Wipe ALL inline styles that old enforceContrast() may have set
    var all = document.querySelectorAll('*');
    for (var i = 0; i < all.length; i++) {
      var el = all[i];
      el.style.removeProperty('color');
      el.style.removeProperty('background');
      el.style.removeProperty('background-color');
      el.style.removeProperty('background-image');
      el.style.removeProperty('border-color');
      el.style.removeProperty('text-shadow');
      el.style.removeProperty('fill');
      el.style.removeProperty('stroke');
    }

    // Update button icon
    var btn = document.getElementById('themeToggleLanding');
    if (btn) {
      btn.innerHTML = isDark ? moonSVG : sunSVG;
      btn.setAttribute('aria-pressed', String(isDark));
    }

    try { localStorage.setItem(KEY, theme); } catch(e) {}
  }

  // Run after DOM is fully ready
  function init() {
    var saved = getSaved() || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);

    var btn = document.getElementById('themeToggleLanding');
    if (btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        var isDark = document.documentElement.classList.contains('dark-theme');
        applyTheme(isDark ? 'light' : 'dark');
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>