<?php
$nav_admin_active = $nav_admin_active ?? 'home';
?>
<style>
  body.admin-page a { text-decoration: none !important; }
  body.admin-page .section-title { text-decoration: none !important; }
  body.admin-page .section-eyebrow { text-decoration: none !important; }

  .admin-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    color: var(--ink-soft);
    text-decoration: none !important;
    position: relative;
    transition: background 0.18s, color 0.18s;
  }

  .admin-icon-btn:hover {
    background: rgba(10,77,140,0.08);
    color: var(--blue-deep);
  }

  .admin-icon-btn.active {
    background: rgba(10,77,140,0.1);
    color: var(--blue-deep);
  }

  .admin-icon-btn .tooltip {
    position: absolute;
    top: 42px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--ink);
    color: white;
    font-size: 0.7rem;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
    z-index: 999;
  }

  .admin-icon-btn .tooltip::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 4px solid transparent;
    border-bottom-color: var(--ink);
  }

  .admin-icon-btn:hover .tooltip {
    opacity: 1;
  }
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
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      <span class="tooltip">Home</span>
    </a>

    <a href="AdminSearch.php" class="admin-icon-btn <?= $nav_admin_active==='search' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <span class="tooltip">Search</span>
    </a>

    <a href="AdminStudents.php" class="admin-icon-btn <?= $nav_admin_active==='students' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
      <span class="tooltip">Students</span>
    </a>

    <a href="AdminSitin.php" class="admin-icon-btn <?= $nav_admin_active==='sitin' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
        <line x1="8" y1="21" x2="16" y2="21"/>
        <line x1="12" y1="17" x2="12" y2="21"/>
      </svg>
      <span class="tooltip">Sit-In</span>
    </a>

    <a href="AdminRecords.php" class="admin-icon-btn <?= $nav_admin_active==='records' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="12 8 12 12 14 14"/>
        <path d="M3.05 11a9 9 0 1 1 .5 4"/>
        <polyline points="3 21 3 16 8 16"/>
      </svg>
      <span class="tooltip">View Sit-in Records</span>
    </a>

    <a href="AdminReports.php" class="admin-icon-btn <?= $nav_admin_active==='reports' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="20" x2="18" y2="10"/>
        <line x1="12" y1="20" x2="12" y2="4"/>
        <line x1="6"  y1="20" x2="6"  y2="14"/>
      </svg>
      <span class="tooltip">Sit-in Reports</span>
    </a>

    <a href="AdminFeedback.php" class="admin-icon-btn <?= $nav_admin_active==='feedback' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      </svg>
      <span class="tooltip">Feedback Report</span>
    </a>

    <a href="AdminReservation.php" class="admin-icon-btn <?= $nav_admin_active==='reservation' ? 'active':'' ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8"  y1="2" x2="8"  y2="6"/>
        <line x1="3"  y1="10" x2="21" y2="10"/>
      </svg>
      <span class="tooltip">Reservation</span>
    </a>

    <div class="sn-divider"></div>

    <a href="../Logout.php" class="nav-btn solid logout-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Log Out
    </a>
  </div>
</nav>