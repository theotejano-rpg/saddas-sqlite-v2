<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }
$db = get_db();

$records = $db->query("
    SELECT sl.*, s.first_name, s.last_name, s.student_id as sid, s.course_code
    FROM sitin_logs sl JOIN students s ON sl.student_id = s.id
    WHERE sl.date_out IS NOT NULL AND sl.date_out != ''
    ORDER BY sl.date_out DESC
")->fetchAll();

$nav_admin_active = 'history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Sit-In History</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }
    .records-table { width:100%; border-collapse:collapse; font-size:0.82rem; table-layout:auto; }
    .records-table th {
      padding:10px 12px; text-align:left;
      background:rgba(10,77,140,0.06); color:var(--blue-deep);
      font-weight:600; font-size:0.70rem; text-transform:uppercase;
      letter-spacing:0.5px; border-bottom:2px solid rgba(10,77,140,0.1);
      white-space:nowrap;
    }
    .records-table td {
      padding:10px 12px; color:var(--ink);
      border-bottom:1px solid rgba(204,222,237,0.4);
      vertical-align:middle; white-space:normal; word-break:break-word;
    }
    .records-table tr:last-child td { border-bottom:none; }
    .records-table tr:hover td { background:rgba(10,77,140,0.02); }
    .filter-bar {
      display:flex; align-items:flex-end; flex-wrap:wrap; gap:12px;
      padding:16px 20px; border-bottom:1px solid rgba(204,222,237,0.4);
      background:rgba(10,77,140,0.02);
    }
    .filter-group { display:flex; flex-direction:column; gap:4px; }
    .filter-group label { font-size:0.68rem; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:var(--ink-soft); }
    .filter-group input, .filter-group select {
      padding:8px 12px; border:1.5px solid #ccdeed; border-radius:8px;
      font-family:'DM Sans',sans-serif; font-size:0.82rem;
      outline:none; transition:border-color 0.2s; background:var(--surface,#fff); color:var(--ink);
      min-width:140px;
    }
    .filter-group input:focus, .filter-group select:focus { border-color:#1877c9; }
    .filter-apply-btn {
      padding:8px 22px; background:#1877c9; color:#fff;
      border:none; border-radius:8px; font-family:'DM Sans',sans-serif;
      font-size:0.84rem; font-weight:600; cursor:pointer;
      transition:background 0.18s; white-space:nowrap; align-self:flex-end;
    }
    .filter-apply-btn:hover { background:#0a4d8c; }
    .records-table-wrap { padding:0 0 4px; overflow-x:auto; }

    .feedback-view-btn {
      display:inline-flex; align-items:center; gap:5px;
      padding:5px 12px;
      background:rgba(10,77,140,0.08); color:var(--blue-deep);
      border:1.5px solid rgba(10,77,140,0.18); border-radius:6px;
      font-family:'DM Sans',sans-serif; font-size:0.75rem; font-weight:600;
      cursor:pointer; transition:background 0.18s, transform 0.15s;
    }
    .feedback-view-btn:hover { background:rgba(10,77,140,0.15); transform:translateY(-1px); }
    .no-feedback { color:#ccc; font-size:0.75rem; font-style:italic; }

    .modal-overlay {
      position:fixed; inset:0; background:rgba(0,0,0,0.45);
      display:none; align-items:center; justify-content:center;
      z-index:999; backdrop-filter:blur(4px);
    }
    .modal-overlay.open { display:flex; animation:fadeIn 0.2s ease; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    .modal-box {
      background:white; border-radius:18px; width:480px; max-width:95vw;
      box-shadow:0 20px 60px rgba(0,0,0,0.2); overflow:hidden;
      animation:slideUp 0.25s ease;
    }
    @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
    .modal-header {
      background:var(--blue-deep); padding:20px 24px;
      display:flex; align-items:center; justify-content:space-between;
    }
    .modal-title {
      font-family:'DM Serif Display',serif; font-size:1.1rem; color:white;
      display:flex; align-items:center; gap:8px;
    }
    .modal-close {
      background:rgba(255,255,255,0.2); border:none; color:white;
      width:28px; height:28px; border-radius:50%; font-size:1rem;
      cursor:pointer; display:flex; align-items:center; justify-content:center;
      transition:background 0.15s;
    }
    .modal-close:hover { background:rgba(255,255,255,0.35); }
    .modal-body { padding:24px; }
    .modal-info-box {
      background:rgba(10,77,140,0.05); border:1px solid rgba(10,77,140,0.12);
      border-radius:10px; padding:12px 16px; margin-bottom:16px;
      font-size:0.83rem; color:var(--ink); line-height:1.6;
    }
    .modal-info-box strong { color:var(--blue-deep); }
    .modal-feedback-text {
      background:#f8fafd; border:1.5px solid #ccdeed; border-radius:10px;
      padding:14px 16px; font-size:0.88rem; color:var(--ink);
      line-height:1.65; white-space:pre-wrap; min-height:80px;
    }
    .modal-footer { padding:0 24px 20px; display:flex; justify-content:flex-end; }
    .modal-close-btn {
      padding:9px 22px; background:transparent;
      border:1.5px solid #ccdeed; border-radius:10px;
      font-family:'DM Sans',sans-serif; font-size:0.88rem;
      color:var(--ink-soft); cursor:pointer; transition:background 0.18s;
    }
    .modal-close-btn:hover { background:#f0f5fb; }
  </style>
  <style>
    /* ── Shared dark theme (matches AdminSoftware design) ── */
    :root {
      --ds-bg: #0b1220; --ds-surface: #0f1724; --ds-panel: #0c1622;
      --ds-muted: #9aa5b4; --ds-text: #ffffff;
      --ds-border: rgba(255,255,255,0.04); --accent-soft: rgba(74,163,255,0.06);
    }
    html.dark-theme, .dark-theme {
      background-color: var(--ds-bg) !important;
      color: var(--ds-text) !important;
    }
    .dark-theme .admin-main { background: transparent; color: var(--ds-text); }

    /* Cards */
    .dark-theme .admin-card,
    .dark-theme .mini-card,
    .dark-theme .fb-stat,
    .dark-theme .report-stat,
    .dark-theme .profile-result-card,
    .dark-theme .modal-box,
    .dark-theme .pc-modal,
    .dark-theme .at-table,
    .dark-theme .at-empty {
      background: linear-gradient(180deg, var(--ds-panel), var(--ds-surface)) !important;
      color: var(--ds-text) !important;
      border-color: var(--ds-border) !important;
      box-shadow: 0 8px 30px rgba(2,6,23,0.6) !important;
    }

    /* All table headers */
    .dark-theme .records-table th,
    .dark-theme .res-table th,
    .dark-theme .sitin-table th,
    .dark-theme .pending-mini-table th,
    .dark-theme .history-table th,
    .dark-theme .fb-table th,
    .dark-theme .at-table th {
      background: rgba(255,255,255,0.03) !important;
      color: var(--ds-text) !important;
      border-bottom-color: rgba(255,255,255,0.04) !important;
    }

    /* All table cells */
    .dark-theme .records-table td,
    .dark-theme .res-table td,
    .dark-theme .sitin-table td,
    .dark-theme .pending-mini-table td,
    .dark-theme .history-table td,
    .dark-theme .fb-table td,
    .dark-theme .at-table td {
      background: transparent !important;
      color: var(--ds-text) !important;
      border-bottom-color: rgba(255,255,255,0.03) !important;
    }

    /* Table row hover */
    .dark-theme .records-table tr:hover td,
    .dark-theme .res-table tr:hover td,
    .dark-theme .sitin-table tr:hover td,
    .dark-theme .pending-mini-table tr:hover td,
    .dark-theme .history-table tr:hover td,
    .dark-theme .fb-table tr:hover td,
    .dark-theme .at-table tr:hover td {
      background: rgba(255,255,255,0.02) !important;
    }

    /* Toolbars / filter bars */
    .dark-theme .records-toolbar,
    .dark-theme .filter-bar,
    .dark-theme .res-toolbar,
    .dark-theme .inline-search-wrap,
    .dark-theme .history-toolbar,
    .dark-theme .fb-filter-bar,
    .dark-theme .sw-filter-bar,
    .dark-theme .sw-add-form {
      border-color: rgba(255,255,255,0.03) !important;
    }

    /* All inputs / selects */
    .dark-theme input,
    .dark-theme select,
    .dark-theme textarea {
      background: rgba(255,255,255,0.04) !important;
      color: var(--ds-text) !important;
      border-color: rgba(255,255,255,0.07) !important;
    }
    .dark-theme input::placeholder { color: rgba(255,255,255,0.3) !important; }
    .dark-theme input:focus,
    .dark-theme select:focus {
      border-color: rgba(74,163,255,0.35) !important;
      outline: none;
    }

    /* Stat numbers/labels */
    .dark-theme .report-stat-num,
    .dark-theme .fb-stat-num { color: var(--ds-text) !important; }
    .dark-theme .report-stat-label,
    .dark-theme .fb-stat-label,
    .dark-theme .records-toolbar label,
    .dark-theme .res-toolbar label,
    .dark-theme .inline-search-wrap label { color: var(--ds-muted) !important; }

    /* Student cards */
    .dark-theme .student-card {
      background: rgba(255,255,255,0.02) !important;
      border-color: rgba(255,255,255,0.04) !important;
      color: var(--ds-text) !important;
      box-shadow: 0 2px 10px rgba(2,6,23,0.4) !important;
    }
    .dark-theme .student-card:hover {
      background: rgba(255,255,255,0.04) !important;
      box-shadow: 0 6px 20px rgba(2,6,23,0.5) !important;
    }

    /* Mini cards (Sitin) */
    .dark-theme .mini-card {
      background: rgba(255,255,255,0.02) !important;
      border-color: rgba(255,255,255,0.04) !important;
    }

    /* Modal */
    .dark-theme .modal-box {
      background: var(--ds-surface) !important;
      border: 1px solid rgba(255,255,255,0.04) !important;
    }
    .dark-theme .modal-body { background: var(--ds-surface) !important; color: var(--ds-text) !important; }
    .dark-theme .modal-info-box {
      background: rgba(74,163,255,0.05) !important;
      border-color: rgba(74,163,255,0.1) !important;
      color: var(--ds-text) !important;
    }
    .dark-theme .modal-feedback-text {
      background: rgba(255,255,255,0.03) !important;
      border-color: rgba(255,255,255,0.06) !important;
      color: var(--ds-text) !important;
    }
    .dark-theme .modal-footer { background: var(--ds-surface) !important; }
    .dark-theme .modal-close-btn {
      background: transparent !important;
      border-color: rgba(255,255,255,0.08) !important;
      color: var(--ds-muted) !important;
    }
    .dark-theme .modal-close-btn:hover { background: rgba(255,255,255,0.04) !important; }

    /* Search page */
    .dark-theme .search-hint {
      background: rgba(74,163,255,0.04) !important;
      border-color: rgba(74,163,255,0.1) !important;
      color: var(--ds-muted) !important;
    }
    .dark-theme .profile-result-card { overflow: hidden; }
    .dark-theme .profile-result-details { background: var(--ds-surface) !important; color: var(--ds-text) !important; }
    .dark-theme .profile-detail-row { border-color: rgba(255,255,255,0.03) !important; color: var(--ds-text) !important; }

    /* Initiate student info (Sitin) */
    .dark-theme .initiate-student-info {
      background: rgba(74,163,255,0.04) !important;
      border-color: rgba(74,163,255,0.08) !important;
    }
    .dark-theme .initiate-student-name { color: var(--ds-text) !important; }
    .dark-theme .initiate-student-meta { color: var(--ds-muted) !important; }
    .dark-theme .initiate-form-row select { background: rgba(255,255,255,0.03) !important; color: var(--ds-text) !important; border-color: rgba(255,255,255,0.06) !important; }

    /* Badges (keep color but adjust bg opacity for dark) */
    .dark-theme .badge-pending,  .dark-theme .status-badge.pending  { background: rgba(232,160,32,0.18) !important; color: #fbbf24 !important; }
    .dark-theme .badge-active,   .dark-theme .status-badge.approved { background: rgba(34,197,94,0.15) !important; color: #86efac !important; }
    .dark-theme .badge-completed { background: rgba(74,163,255,0.12) !important; color: #93c5fd !important; }
    .dark-theme .badge-cancelled, .dark-theme .status-badge.rejected { background: rgba(220,38,38,0.15) !important; color: #fca5a5 !important; }

    /* Action buttons */
    .dark-theme .at-action-btn.approve { background: rgba(34,197,94,0.12) !important; color: #86efac !important; }
    .dark-theme .at-action-btn.reject  { background: rgba(220,38,38,0.1) !important; color: #fca5a5 !important; }
    .dark-theme .feedback-view-btn { background: rgba(74,163,255,0.08) !important; color: #93c5fd !important; border-color: rgba(74,163,255,0.12) !important; }

    /* Fb filter submit */
    .dark-theme .fb-filter-submit { background: #1877c9 !important; }
    .dark-theme .fb-filter-reset { border-color: rgba(255,255,255,0.08) !important; color: var(--ds-muted) !important; }

    /* Section headings / eyebrows */
    .dark-theme .at-section-title { color: var(--ds-text) !important; }
    .dark-theme .at-section-sub { color: var(--ds-muted) !important; }

    /* Divider */
    .dark-theme .divider-line { border-color: rgba(255,255,255,0.04) !important; }

    /* Toast */
    .dark-theme .toast-fixed { background: rgba(255,255,255,0.06) !important; color: var(--ds-text) !important; box-shadow: 0 6px 24px rgba(2,6,23,0.6) !important; }

    /* Smooth transitions */
    .dark-theme * { transition: background-color 180ms ease, color 180ms ease, border-color 180ms ease; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Sit-In History</h2>

  <div class="admin-card">
    <div class="admin-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
        <polyline points="10 9 9 9 8 9"/>
      </svg>
      Completed Sit-In Sessions
    </div>

    <div class="filter-bar">
      <div class="filter-group">
        <label>From Date</label>
        <input type="date" id="filterFrom" />
      </div>
      <div class="filter-group">
        <label>To Date</label>
        <input type="date" id="filterTo" />
      </div>
      <div class="filter-group">
        <label>Lab</label>
        <select id="filterLab">
          <option value="">All Labs</option>
          <?php
            $labs = $db->query("SELECT DISTINCT lab_room FROM sitin_logs ORDER BY lab_room")->fetchAll(PDO::FETCH_COLUMN);
            foreach($labs as $lab) echo "<option value=\"".htmlspecialchars($lab)."\">".htmlspecialchars($lab)."</option>";
          ?>
        </select>
      </div>
      <button class="filter-apply-btn" onclick="applyFilters()">Apply Filters</button>
    </div>

    <div class="records-table-wrap">
      <table class="records-table" id="recordsTable">
        <thead>
          <tr>
            <th>Sit ID</th>
            <th>ID Number</th>
            <th>Name</th>
            <th>Course</th>
            <th>Purpose</th>
            <th>Lab</th>
            <th>PC</th>
            <th>Date In</th>
            <th>Date Out</th>
            <th>Feedback</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['sid']) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['course_code']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= htmlspecialchars($r['lab_room']) ?></td>
            <td><?= $r['pc_number'] ? 'PC #'.htmlspecialchars($r['pc_number']) : '<span style="color:#aaa;">—</span>' ?></td>
            <td><?php $d = new DateTime(str_replace('T',' ',$r['date_in'])); echo $d->format('M d, Y h:i A'); ?></td>
            <td><?php if($r['date_out']){ $d = new DateTime(str_replace('T',' ',$r['date_out'])); echo $d->format('M d, Y h:i A'); } else { echo '<span style="color:#aaa;font-style:italic;">—</span>'; } ?></td>
            <td>
              <?php if (!empty($r['feedback'])): ?>
                <button class="feedback-view-btn" onclick="viewFeedback(
                  <?= htmlspecialchars(json_encode($r['first_name'].' '.$r['last_name'])) ?>,
                  <?= htmlspecialchars(json_encode($r['sid'])) ?>,
                  <?= htmlspecialchars(json_encode($r['lab_room'])) ?>,
                  <?= htmlspecialchars(json_encode($r['purpose'])) ?>,
                  <?= htmlspecialchars(json_encode($r['date_in'])) ?>,
                  <?= htmlspecialchars(json_encode($r['feedback'])) ?>
                )">
                  &#128172; View Feedback
                </button>
              <?php else: ?>
                <span class="no-feedback">No feedback</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($records)): ?>
            <tr><td colspan="10" class="empty-state">No completed sit-in sessions yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <div id="pagination" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:16px 0 4px;"></div>
    </div>
  </div>
</main>

<!-- Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Student Feedback
      </div>
      <button class="modal-close" onclick="closeModal()">&#10005;</button>
    </div>
    <div class="modal-body">
      <div class="modal-info-box" id="modalInfo"></div>
      <div class="modal-feedback-text" id="modalFeedbackText"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-close-btn" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
<script>
function initPagination(tableId, paginationId, perPage) {
  const tbody = document.querySelector('#' + tableId + ' tbody');
  const pagination = document.getElementById(paginationId);
  let currentPage = 1;
  let filteredRows = [];

  function getVisibleRows() {
    return Array.from(tbody.querySelectorAll('tr')).filter(r => r.dataset.hidden !== 'true');
  }

  function renderPage(page) {
    currentPage = page;
    const rows = getVisibleRows();
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
    currentPage = Math.min(currentPage, totalPages);
    rows.forEach((r, i) => {
      r.style.display = (i >= (currentPage-1)*perPage && i < currentPage*perPage) ? '' : 'none';
    });
    renderControls(totalPages);
  }

  function renderControls(totalPages) {
    pagination.innerHTML = '';
    if (totalPages <= 1) return;
    const btn = (label, page, disabled, active) => {
      const b = document.createElement('button');
      b.innerHTML = label;
      b.disabled = disabled;
      b.style.cssText = `padding:6px 12px;border-radius:8px;border:1px solid ${active?'#0a4d8c':'rgba(10,77,140,0.2)'};background:${active?'#0a4d8c':'transparent'};color:${active?'#fff':'var(--ink-soft)'};cursor:${disabled?'default':'pointer'};font-size:0.8rem;font-weight:600;transition:all 0.15s;`;
      if (!disabled) b.onclick = () => renderPage(page);
      return b;
    };
    pagination.appendChild(btn('&#8592;', currentPage-1, currentPage===1, false));
    for (let i = 1; i <= totalPages; i++) {
      pagination.appendChild(btn(i, i, false, i===currentPage));
    }
    pagination.appendChild(btn('&#8594;', currentPage+1, currentPage===totalPages, false));
  }

  window._repaginate = () => renderPage(1);
  renderPage(1);
}

function applyFilters() {
  const from = document.getElementById('filterFrom').value;
  const to   = document.getElementById('filterTo').value;
  const lab  = document.getElementById('filterLab').value.toLowerCase();

  document.querySelectorAll('#recordsTable tbody tr').forEach(r => {
    const cells = r.querySelectorAll('td');
    if (!cells.length) return;
    const rowLab     = (cells[5]?.textContent || '').toLowerCase();
    const rowDateRaw = cells[7]?.textContent || '';
    const rowDate    = rowDateRaw ? new Date(rowDateRaw).toISOString().slice(0,10) : '';

    let show = true;
    if (from && rowDate && rowDate < from) show = false;
    if (to   && rowDate && rowDate > to)   show = false;
    if (lab  && !rowLab.includes(lab))     show = false;

    r.dataset.hidden = show ? 'false' : 'true';
    if (!show) r.style.display = 'none';
  });
  window._repaginate && window._repaginate();
}

initPagination('recordsTable', 'pagination', 10);

function viewFeedback(name, sid, lab, purpose, dateIn, feedback) {
  document.getElementById('modalInfo').innerHTML =
    '<strong>Student:</strong> ' + name + ' (' + sid + ')<br>' +
    '<strong>Lab:</strong> ' + lab + ' &bull; <strong>Purpose:</strong> ' + purpose + '<br>' +
    '<strong>Date In:</strong> ' + dateIn;
  document.getElementById('modalFeedbackText').textContent = feedback;
  document.getElementById('feedbackModal').classList.add('open');
}

function closeModal() {
  document.getElementById('feedbackModal').classList.remove('open');
}

document.getElementById('feedbackModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>