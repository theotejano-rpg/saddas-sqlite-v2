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
    <!-- Theme toggle on landing -->
    <button id="themeToggleLanding" class="nav-btn ghost theme-toggle" title="Toggle theme" aria-pressed="false" style="margin-left:8px;">
      <svg id="themeIconLanding" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle></svg>
    </button>
  </div>
</nav>

<script>
/* Landing page theme toggle + init (persists to localStorage) */
(function () {
  const key = 'saddas_theme';
  const btn = document.getElementById('themeToggleLanding');
  const icon = document.getElementById('themeIconLanding');
  const sunSVG = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.93 19.07l1.41-1.41"/><path d="M17.66 6.34l1.41-1.41"/></svg>';
  const moonSVG = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';

  function applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.classList.add('dark-theme');
      document.body.classList.add('dark-theme');
    } else {
      document.documentElement.classList.remove('dark-theme');
      document.body.classList.remove('dark-theme');
    }
    if (icon) icon.innerHTML = theme === 'dark' ? moonSVG : sunSVG;
    if (btn) btn.setAttribute('aria-pressed', theme === 'dark');
    try { localStorage.setItem(key, theme); } catch (e) {}
    try {
      if (theme === 'dark') {
        document.documentElement.style.background = 'var(--dark-bg)';
        document.documentElement.style.color = '#ffffff';
        document.body.style.background = 'var(--dark-bg)';
        document.body.style.color = '#ffffff';
        const main = document.querySelector('.hero, .main, .admin-main'); if (main) main.style.background = 'transparent';
        enforceContrast(true);
      } else {
        document.documentElement.style.background = '';
        document.documentElement.style.color = '';
        document.body.style.background = '';
        document.body.style.color = '';
        const main = document.querySelector('.hero, .main, .admin-main'); if (main) main.style.background = '';
        enforceContrast(false);
      }
    } catch (e) {}
  }

  // Inline style enforcer: sets inline colors/backgrounds for selectors that stubbornly remain low-contrast.
  function enforceContrast(enable) {
    const textSelectors = ['.hero-text h1', '.hero-text h1 em', '.hero-text p', '.hero-eyebrow', '.lb-heading h2', '.lb-heading p', '.lb-card-name', '.lb-card-course', '.lb-list-name', '.lb-list-course', '.lb-list-sess', '.lb-list-pts', '.nav-link', '.nav-right .nav-btn'];
    const bgSelectors = ['.nav-right .nav-btn', '.hero-badge', '.lb-card', '.lb-list'];
    try {
      textSelectors.forEach(sel => document.querySelectorAll(sel).forEach(el => {
        if (enable) { el.style.setProperty('color', '#ffffff', 'important'); el.style.setProperty('textShadow', '0 1px 2px rgba(0,0,0,0.6)', 'important'); }
        else { el.style.removeProperty('color'); el.style.removeProperty('textShadow'); }
      }));
      bgSelectors.forEach(sel => document.querySelectorAll(sel).forEach(el => {
        if (enable) { el.style.setProperty('backgroundColor', 'rgba(0,0,0,0.35)', 'important'); el.style.setProperty('borderColor', 'rgba(255,255,255,0.06)', 'important'); }
        else { el.style.removeProperty('backgroundColor'); el.style.removeProperty('borderColor'); }
      }));
      // Ensure SVGs inherit color
      document.querySelectorAll('svg').forEach(svg => { if (enable) { svg.style.setProperty('color', '#ffffff', 'important'); svg.style.setProperty('fill', 'currentColor', 'important'); svg.style.setProperty('stroke', 'currentColor', 'important'); } else { svg.style.removeProperty('color'); svg.style.removeProperty('fill'); svg.style.removeProperty('stroke'); } });
    } catch (e) {}
  }

  // init
  try {
    const saved = localStorage.getItem(key) || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);
  } catch (e) { applyTheme('light'); }

  if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); const isDark = document.documentElement.classList.contains('dark-theme'); applyTheme(isDark ? 'light' : 'dark'); });
})();
</script>