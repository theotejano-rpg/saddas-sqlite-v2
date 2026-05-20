<?php
$nav_student_active = $nav_student_active ?? 'home';

// Fetch latest 5 announcements for the dropdown
$announcements_nav = [];
$notif_count = 0;
if (isset($db)) {
    $announcements_nav = $db->query("SELECT * FROM announcements ORDER BY posted_at DESC LIMIT 5")->fetchAll();
    $notif_count = count($announcements_nav);
}

// Track seen announcements via session
$latest_id = !empty($announcements_nav) ? $announcements_nav[0]['id'] : 0;
$last_seen_id = $_SESSION['last_seen_announcement'] ?? 0;
$has_unread = $latest_id > $last_seen_id;

// If student clicks mark as seen (GET param), mark all as seen
if (isset($_GET['mark_notif_seen'])) {
    $_SESSION['last_seen_announcement'] = $latest_id;
    $has_unread = false;
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect");
    exit;
}

$tag_colors = [
    'Academics' => ['bg'=>'rgba(10,77,140,0.10)',  'color'=>'#0a4d8c'],
    'Sit-In'    => ['bg'=>'rgba(107,33,200,0.10)', 'color'=>'#6b21c8'],
    'Event'     => ['bg'=>'rgba(232,160,32,0.12)', 'color'=>'#a06010'],
    'General'   => ['bg'=>'rgba(10,77,140,0.10)',  'color'=>'#0a4d8c'],
];
?>
<style>
body.student-page a { text-decoration: none !important; }

/* Bell wrapper */
.notif-wrapper { position: relative; }

.notif-bell-btn {
  position: relative;
  display: flex; align-items: center; justify-content: center;
  width: 38px; height: 38px;
  border-radius: 10px;
  color: var(--ink-soft, #6b7a8d);
  transition: background 0.18s, color 0.18s;
  cursor: pointer;
  text-decoration: none !important;
}
.notif-bell-btn:hover, .notif-bell-btn.active {
  background: rgba(10,77,140,0.08);
  color: var(--blue-deep, #0a4d8c);
}

.notif-dot {
  position: absolute;
  top: 5px; right: 5px;
  width: 8px; height: 8px;
  background: #e03535;
  border-radius: 50%;
  border: 2px solid white;
}

/* Dropdown */
.notif-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: -10px;
  width: 320px;
  background: white;
  border-radius: 14px;
  box-shadow: 0 12px 40px rgba(10,77,140,0.15), 0 2px 8px rgba(0,0,0,0.08);
  border: 1px solid rgba(204,222,237,0.6);
  opacity: 0;
  pointer-events: none;
  transform: translateY(8px);
  transition: opacity 0.2s ease, transform 0.2s ease;
  z-index: 9999;
  overflow: hidden;
}
.notif-wrapper:hover .notif-dropdown,
.notif-dropdown.open {
  opacity: 1;
  pointer-events: all;
  transform: translateY(0);
}
.notif-dropdown::before {
  content: '';
  position: absolute;
  top: -6px; right: 18px;
  width: 12px; height: 12px;
  background: white;
  border-left: 1px solid rgba(204,222,237,0.6);
  border-top: 1px solid rgba(204,222,237,0.6);
  transform: rotate(45deg);
  border-radius: 2px 0 0 0;
}

.notif-dropdown-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 13px 16px 10px;
  border-bottom: 1px solid rgba(204,222,237,0.5);
}
.notif-dropdown-title {
  font-weight: 700; font-size: 0.82rem;
  color: var(--blue-deep, #0a4d8c);
}
.notif-mark-read-btn {
  font-size: 0.7rem; color: #e03535;
  background: rgba(224,53,53,0.07);
  border: none; border-radius: 6px;
  padding: 3px 8px; cursor: pointer;
  font-weight: 600; transition: background 0.15s;
  text-decoration: none !important;
}
.notif-mark-read-btn:hover { background: rgba(224,53,53,0.14); }

.notif-dropdown-list { max-height: 310px; overflow-y: auto; }
.notif-dropdown-list::-webkit-scrollbar { width: 4px; }
.notif-dropdown-list::-webkit-scrollbar-thumb { background: rgba(10,77,140,0.15); border-radius: 4px; }

.notif-drop-item {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(204,222,237,0.3);
  transition: background 0.15s;
  cursor: pointer;
  text-decoration: none !important;
  display: block; color: inherit;
}
.notif-drop-item:last-child { border-bottom: none; }
.notif-drop-item:hover { background: rgba(10,77,140,0.03); }

.notif-drop-meta { display: flex; align-items: center; gap: 7px; margin-bottom: 4px; }
.notif-drop-tag {
  font-size: 0.62rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.8px;
  padding: 2px 7px; border-radius: 20px;
}
.notif-drop-date { font-size: 0.68rem; color: #9aa5b4; margin-left: auto; }
.notif-drop-title { font-size: 0.82rem; font-weight: 600; color: #1a2535; margin-bottom: 2px; line-height: 1.3; }
.notif-drop-body {
  font-size: 0.75rem; color: #6b7a8d; line-height: 1.45;
  display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}

.notif-dropdown-footer {
  padding: 10px 16px;
  border-top: 1px solid rgba(204,222,237,0.5);
  text-align: center;
}
.notif-view-all-link {
  font-size: 0.77rem; font-weight: 600;
  color: var(--blue-deep, #0a4d8c);
  text-decoration: none !important;
}
.notif-view-all-link:hover { opacity: 0.7; }
.notif-empty-drop { padding: 24px 16px; text-align: center; color: #9aa5b4; font-size: 0.8rem; }

/* Other icon buttons */
.sn-icon-btn {
  position: relative; display: flex; align-items: center; justify-content: center;
  width: 38px; height: 38px; border-radius: 10px;
  color: var(--ink-soft, #6b7a8d);
  transition: background 0.18s, color 0.18s;
  text-decoration: none !important;
}
.sn-icon-btn:hover, .sn-icon-btn.active {
  background: rgba(10,77,140,0.08);
  color: var(--blue-deep, #0a4d8c);
}
.sn-icon-btn .tooltip {
  position: absolute; top: 42px; left: 50%; transform: translateX(-50%);
  background: var(--ink, #1a2535); color: white;
  font-size: 0.7rem; font-weight: 500; padding: 4px 8px;
  border-radius: 6px; white-space: nowrap;
  opacity: 0; pointer-events: none; transition: opacity 0.15s; z-index: 999;
}
.sn-icon-btn .tooltip::before {
  content: ''; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
  border: 4px solid transparent; border-bottom-color: var(--ink, #1a2535);
}
.sn-icon-btn:hover .tooltip { opacity: 1; }
</style>

<nav class="site-nav">
  <div class="nav-left">
    <img src="images/uclogo-removebg-preview-removebg-preview.png" alt="University of Cebu"/>
    <div class="nav-left-text">
      <strong>CCS Student Sitin</strong>
      <small>University of Cebu</small>
    </div>
  </div>

  <div class="nav-links">
    <a href="Students.php"  class="nav-link <?= $nav_student_active === 'home'      ? 'active' : '' ?>">Home</a>
    <a href="Community.php" class="nav-link <?= $nav_student_active === 'community' ? 'active' : '' ?>">Community</a>
    <a href="About.php"     class="nav-link <?= $nav_student_active === 'about'     ? 'active' : '' ?>">About</a>
  </div>

  <div class="nav-right student-nav-right">

    <!-- Notification Bell -->
    <div class="notif-wrapper" id="notifWrapper">
      <a href="Notification.php"
         class="notif-bell-btn <?= $nav_student_active === 'notifications' ? 'active' : '' ?>"
         id="notifBell">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="notif-dot" id="notifDot" style="display:<?= $has_unread ? 'block' : 'none' ?>;"></span>
      </a>

      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-header">
          <span class="notif-dropdown-title">
            🔔 Notifications
            <?php if ($notif_count > 0): ?>
              <span style="background:rgba(10,77,140,0.1);color:var(--blue-deep);font-size:0.65rem;padding:1px 7px;border-radius:20px;margin-left:4px;"><?= $notif_count ?></span>
            <?php endif; ?>
          </span>
          <button class="notif-mark-read-btn" id="markReadBtn" style="display:<?= $has_unread ? 'inline-block' : 'none' ?>;">
            ✓ Mark read
          </button>
        </div>

        <div class="notif-dropdown-list">
          <?php if (empty($announcements_nav)): ?>
            <div class="notif-empty-drop">No announcements yet.</div>
          <?php else: ?>
            <?php foreach ($announcements_nav as $ann):
              $tc = $tag_colors[$ann['tag']] ?? $tag_colors['General'];
              $posted = date('M d', strtotime($ann['posted_at']));
              $is_new = $ann['id'] > $last_seen_id;
            ?>
            <a href="Notification.php" class="notif-drop-item" style="<?= $is_new ? 'background:rgba(10,77,140,0.03);' : '' ?>">
              <?php if ($is_new): ?>
                <div style="display:flex;align-items:center;gap:5px;margin-bottom:4px;">
                  <span style="width:6px;height:6px;background:#e03535;border-radius:50%;display:inline-block;"></span>
                  <span style="font-size:0.62rem;color:#e03535;font-weight:700;">NEW</span>
                </div>
              <?php endif; ?>
              <div class="notif-drop-meta">
                <span class="notif-drop-tag" style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;"><?= htmlspecialchars($ann['tag']) ?></span>
                <span class="notif-drop-date"><?= $posted ?></span>
              </div>
              <div class="notif-drop-title"><?= htmlspecialchars($ann['title']) ?></div>
              <div class="notif-drop-body"><?= htmlspecialchars($ann['body']) ?></div>
            </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="notif-dropdown-footer">
          <a href="Notification.php" class="notif-view-all-link">View all notifications →</a>
        </div>
      </div>
    </div>
    <!-- End Notification Bell -->

    <a href="Students.php" class="sn-icon-btn <?= $nav_student_active === 'home' ? 'active' : '' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      <span class="tooltip">Home</span>
    </a>

    <a href="EditProfile.php" class="sn-icon-btn <?= $nav_student_active === 'profile' ? 'active' : '' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
      <span class="tooltip">Edit Profile</span>
    </a>

    <a href="History.php" class="sn-icon-btn <?= $nav_student_active === 'history' ? 'active' : '' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="12 8 12 12 14 14"/>
        <path d="M3.05 11a9 9 0 1 1 .5 4"/>
        <polyline points="3 21 3 16 8 16"/>
      </svg>
      <span class="tooltip">History</span>
    </a>

    <a href="Reservation.php" class="sn-icon-btn <?= $nav_student_active === 'reservation' ? 'active' : '' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8"  y1="2" x2="8"  y2="6"/>
        <line x1="3"  y1="10" x2="21" y2="10"/>
      </svg>
      <span class="tooltip">Reservation</span>
    </a>

    <div class="sn-divider"></div>

    <a href="Logout.php" class="nav-btn solid logout-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Log Out
    </a>
  </div>
</nav>

<script>
(function () {
  const wrapper  = document.getElementById('notifWrapper');
  const bell     = document.getElementById('notifBell');
  const dropdown = document.getElementById('notifDropdown');
  const dot      = document.getElementById('notifDot');
  const markBtn  = document.getElementById('markReadBtn');

  function markRead() {
    if (dot)     dot.style.display = 'none';
    if (markBtn) markBtn.style.display = 'none';
    document.querySelectorAll('.notif-drop-item').forEach(item => {
      item.style.background = '';
      const badge = item.querySelector('div:first-child');
      if (badge && badge.textContent.includes('NEW')) badge.remove();
    });
    fetch(window.location.pathname + '?mark_notif_seen=1');
  }

  // Hover keeps dropdown open
  wrapper.addEventListener('mouseenter', () => dropdown.classList.add('open'));
  wrapper.addEventListener('mouseleave', () => {
    setTimeout(() => { if (!wrapper.matches(':hover')) dropdown.classList.remove('open'); }, 150);
  });

  // Click bell: mark read + go to Notification page
  bell.addEventListener('click', function (e) {
    e.preventDefault();
    markRead();
    setTimeout(() => { window.location.href = 'Notification.php'; }, 100);
  });

  // Click "Mark read" button: just hide the dot, stay on page
  if (markBtn) {
    markBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      markRead();
    });
  }

  // Close on outside click
  document.addEventListener('click', function (e) {
    if (!wrapper.contains(e.target)) dropdown.classList.remove('open');
  });
})();
</script>