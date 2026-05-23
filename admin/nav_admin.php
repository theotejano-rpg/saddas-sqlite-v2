<?php
$nav_admin_active = $nav_admin_active ?? 'home';
$db = get_db();
$unread_count = $db->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
?>
<style>
  body.admin-page a { text-decoration: none !important; }
  body.admin-page .section-title { text-decoration: none !important; }
  body.admin-page .section-eyebrow { text-decoration: none !important; }

  .admin-icon-btn {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 8px;
    color: var(--ink-soft); text-decoration: none !important;
    position: relative; transition: background 0.18s, color 0.18s;
  }
  .admin-icon-btn:hover { background: rgba(10,77,140,0.08); color: var(--blue-deep); }
  .admin-icon-btn.active { background: rgba(10,77,140,0.1); color: var(--blue-deep); }
  .admin-icon-btn .tooltip {
    position: absolute; top: 42px; left: 50%; transform: translateX(-50%);
    background: var(--ink); color: white; font-size: 0.7rem; font-weight: 500;
    padding: 4px 8px; border-radius: 6px; white-space: nowrap;
    opacity: 0; pointer-events: none; transition: opacity 0.15s; z-index: 999;
  }
  .admin-icon-btn .tooltip::before {
    content: ''; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
    border: 4px solid transparent; border-bottom-color: var(--ink);
  }
  .admin-icon-btn:hover .tooltip { opacity: 1; }

  .notif-bell-wrap { position: relative; }
  .notif-badge {
    position: absolute; top: -4px; right: -4px;
    background: #e53e3e; color: white;
    font-size: 0.6rem; font-weight: 700;
    min-width: 16px; height: 16px;
    border-radius: 20px; display: flex;
    align-items: center; justify-content: center;
    padding: 0 3px; border: 2px solid white;
    pointer-events: none;
  }
  .notif-dropdown {
    display: none; position: absolute;
    top: 46px; right: 0; width: 320px;
    background: white; border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    z-index: 9999; overflow: hidden;
    border: 1px solid rgba(204,222,237,0.6);
  }
  .notif-dropdown.open { display: block; }
  .notif-dropdown-header {
    padding: 12px 16px; font-size: 0.82rem; font-weight: 700;
    color: var(--blue-deep); border-bottom: 1px solid rgba(204,222,237,0.5);
    display: flex; align-items: center; justify-content: space-between;
  }
  .notif-mark-all {
    font-size: 0.72rem; color: #1877c9; cursor: pointer;
    font-weight: 500; text-decoration: none;
    background: none; border: none; padding: 0;
  }
  .notif-mark-all:hover { text-decoration: underline; }
  .notif-list { max-height: 320px; overflow-y: auto; }
  .notif-item {
    display: flex; gap: 10px; padding: 12px 16px;
    border-bottom: 1px solid rgba(204,222,237,0.3);
    cursor: pointer; transition: background 0.15s;
    text-decoration: none !important;
  }
  .notif-item:hover { background: rgba(10,77,140,0.04); }
  .notif-item.unread { background: rgba(24,119,201,0.06); }
  .notif-item:last-child { border-bottom: none; }
  .notif-dot { width: 8px; height: 8px; border-radius: 50%; background: #1877c9; flex-shrink: 0; margin-top: 5px; }
  .notif-dot.read { background: transparent; }
  .notif-item-text { font-size: 0.78rem; color: #2d3748; line-height: 1.4; flex: 1; }
  .notif-item-time { font-size: 0.68rem; color: #a0aec0; margin-top: 3px; }
  .notif-empty { padding: 24px 16px; text-align: center; font-size: 0.8rem; color: #a0aec0; }
</style>

<nav class="site-nav admin-site-nav">
  <div class="nav-left">
    <img src="../images/uclogo-removebg-preview-removebg-preview.png" alt="UC"/>
    <div class="nav-left-text">
      <strong>CCS Admin Portal</strong>
      <small>College of Computer Studies</small>
    </div>
  </div>

  <div class="nav-right admin-nav-right">

    <a href="admin.php" class="admin-icon-btn <?= $nav_admin_active==='home' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span class="tooltip">Home</span>
    </a>

    <a href="AdminSearch.php" class="admin-icon-btn <?= $nav_admin_active==='search' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <span class="tooltip">Search</span>
    </a>

    <a href="AdminStudents.php" class="admin-icon-btn <?= $nav_admin_active==='students' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span class="tooltip">Students</span>
    </a>

    <a href="AdminSitin.php" class="admin-icon-btn <?= $nav_admin_active==='sitin' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      <span class="tooltip">Sit-In</span>
    </a>

    <a href="AdminRecords.php" class="admin-icon-btn <?= $nav_admin_active==='records' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 1 .5 4"/><polyline points="3 21 3 16 8 16"/></svg>
      <span class="tooltip">View Sit-in Records</span>
    </a>

    <a href="AdminReports.php" class="admin-icon-btn <?= $nav_admin_active==='reports' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span class="tooltip">Sit-in Reports</span>
    </a>

    <a href="AdminFeedback.php" class="admin-icon-btn <?= $nav_admin_active==='feedback' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span class="tooltip">Feedback Report</span>
    </a>

    <a href="AdminReservation.php" class="admin-icon-btn <?= $nav_admin_active==='reservation' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span class="tooltip">Reservation</span>
    </a>

    <!-- SOFTWARE TAB -->
    <a href="AdminSoftware.php" class="admin-icon-btn <?= $nav_admin_active==='software' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
        <line x1="8" y1="21" x2="16" y2="21"/>
        <line x1="12" y1="17" x2="12" y2="21"/>
        <polyline points="9 9 12 6 15 9"/>
        <line x1="12" y1="6" x2="12" y2="13"/>
      </svg>
      <span class="tooltip">Software Management</span>
    </a>

    <!-- TESTIMONIALS TAB -->
    <a href="AdminTestimonials.php" class="admin-icon-btn <?= $nav_admin_active==='testimonials' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="13" y2="13"/></svg>
      <span class="tooltip">Testimonials</span>
    </a>

    <!-- NOTIFICATION BELL -->
    <div class="notif-bell-wrap" id="notif_wrap">
  <button type="button" class="admin-icon-btn" onclick="toggleNotif(event)" id="notif_btn">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <?php if ($unread_count > 0): ?>
          <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
        <?php endif; ?>
        <span class="tooltip">Notifications</span>
      </button>

  <div class="notif-dropdown" id="notif_dropdown">
        <div class="notif-dropdown-header">
          Notifications
          <?php if ($unread_count > 0): ?>
            <a href="mark_notifications_read.php" class="notif-mark-all">Mark all as read</a>
          <?php endif; ?>
        </div>
        <div class="notif-list">
          <?php
          $notifs = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
          if (empty($notifs)): ?>
            <div class="notif-empty">No notifications yet.</div>
          <?php else: foreach ($notifs as $n): ?>
        <?php $return = htmlspecialchars($n['link']); ?>
        <a href="mark_notifications_read.php?mark_notif=<?= $n['id'] ?>&return=<?= urlencode($return) ?>"
          class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
              <div class="notif-dot <?= $n['is_read'] ? 'read' : '' ?>"></div>
              <div>
                <div class="notif-item-text"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-item-time"><?= htmlspecialchars($n['created_at']) ?></div>
              </div>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="sn-divider"></div>

    <a href="../Logout.php" class="nav-btn solid logout-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
    <!-- THEME TOGGLE (to the right of logout) -->
    <button id="themeToggleAdmin" class="admin-icon-btn theme-toggle" title="Toggle theme" aria-pressed="false">
      <svg id="themeIconAdmin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="4"></circle>
      </svg>
      <span class="tooltip">Toggle theme</span>
    </button>
  </div>
</nav>
<script>
function toggleNotif(e) {
  e.stopPropagation();
  const d = document.getElementById('notif_dropdown');
  d.classList.toggle('open');
  // If opening, mark all notifications read via AJAX and remove badge
  if (d.classList.contains('open')) {
    fetch('mark_notifications_read.php?mark_all=1').then(() => {
      const b = document.querySelector('.notif-badge'); if (b) b.remove();
      document.querySelectorAll('.notif-item.unread').forEach(it => it.classList.remove('unread'));
      document.querySelectorAll('.notif-dot').forEach(dot => dot.classList.add('read'));
    }).catch(()=>{});
  }
}
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('notif_wrap');
  if (wrap && !wrap.contains(e.target))
    document.getElementById('notif_dropdown').classList.remove('open');
});

// Theme toggle: persist theme in localStorage and apply .dark-theme to documentElement
(function () {
  const key = 'saddas_theme';
  const btn = document.getElementById('themeToggleAdmin');
  const icon = document.getElementById('themeIconAdmin');
  const sunSVG = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.93 19.07l1.41-1.41"/><path d="M17.66 6.34l1.41-1.41"/></svg>';
  const moonSVG = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';

  function applyTheme(theme) {
    if (theme === 'dark') { document.documentElement.classList.add('dark-theme'); document.body.classList.add('dark-theme'); } else { document.documentElement.classList.remove('dark-theme'); document.body.classList.remove('dark-theme'); }
    if (icon) icon.innerHTML = theme === 'dark' ? moonSVG : sunSVG;
    if (btn) btn.setAttribute('aria-pressed', theme === 'dark');
    try { localStorage.setItem(key, theme); } catch (e) {}
    // Also set inline background/text styles on body to override heavy global gradients
    try {
      if (theme === 'dark') {
        document.documentElement.style.background = 'var(--dark-bg)';
        document.documentElement.style.color = 'var(--text-soft)';
        document.body.style.background = 'var(--dark-bg)';
        document.body.style.color = 'var(--text-soft)';
        // force main content background transparent so panels show dark panels from CSS
        const main = document.querySelector('.admin-main'); if (main) main.style.background = 'transparent';
      } else {
        document.documentElement.style.background = '';
        document.documentElement.style.color = '';
        document.body.style.background = '';
        document.body.style.color = '';
        const main = document.querySelector('.admin-main'); if (main) main.style.background = '';
      }
    } catch (e) {}
  }

  // init
  try {
    const saved = localStorage.getItem(key) || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);
  } catch (e) { applyTheme('light'); }

  if (btn) btn.addEventListener('click', function (e) {
    e.preventDefault();
    const isDark = document.documentElement.classList.contains('dark-theme');
    applyTheme(isDark ? 'light' : 'dark');
  });
})();
</script>

<style>
/* Comfortable dark theme overrides (admin) */
:root { --dark-bg: #0b1220; --dark-panel: #0f1724; --dark-surface: #0c1320; --muted: #9aa5b4; --text-soft: #dbeafe; --accent: #4aa3ff; }
html, body { transition: background-color 200ms ease, color 200ms ease; }
.dark-theme, html.dark-theme {
  background-color: var(--dark-bg) !important;
  color: var(--text-soft) !important;
}
/* Force solid dark navbar (override other gradients/transparency) */
html.dark-theme .site-nav, .dark-theme .site-nav {
  background-color: var(--dark-panel) !important;
  background-image: none !important;
  box-shadow: 0 6px 24px rgba(2,6,23,0.6) !important;
  border-bottom: 1px solid rgba(255,255,255,0.04) !important;
  z-index: 9999 !important;
}
/* Ensure navbar children use readable colors */
html.dark-theme .site-nav .nav-left-text strong,
html.dark-theme .site-nav .nav-left-text small,
html.dark-theme .site-nav a,
html.dark-theme .site-nav .admin-icon-btn,
html.dark-theme .site-nav .nav-btn {
  color: var(--text-soft) !important;
}
/* Remove any backdrop filter so underlying gradient doesn't show through */
html.dark-theme .site-nav, html.dark-theme .site-nav * { backdrop-filter: none !important; }
.dark-theme .nav-left-text small { color: var(--muted); }
.dark-theme .admin-icon-btn, .dark-theme .nav-btn { color: var(--text-soft); }
.dark-theme .admin-icon-btn:hover, .dark-theme .sn-icon-btn:hover { background: rgba(255,255,255,0.03); }
.dark-theme .notif-dropdown { background: var(--dark-panel); color: var(--text-soft); border-color: rgba(255,255,255,0.03); box-shadow: 0 10px 30px rgba(2,6,23,0.6); }
.dark-theme .notif-item { border-bottom-color: rgba(255,255,255,0.02); }
.dark-theme .notif-item.unread { background: rgba(74,163,255,0.06); }
.dark-theme .notif-badge { background: #ff6b6b; border: 2px solid var(--dark-bg); color: white; }
.theme-toggle svg { display: block; }
</style>