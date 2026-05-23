<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();

$lab_rooms    = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530'];
$all_software = ['C / C++', 'Java', 'Python', 'PHP / Web Development', 'Database (SQL)', 'Networking', 'Research / Thesis', 'Other'];

$selected_lab = $_GET['lab'] ?? $lab_rooms[0];
if (!in_array($selected_lab, $lab_rooms)) $selected_lab = $lab_rooms[0];

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_pc') {
        $lab = $_POST['lab_room'] ?? '';
        $pc  = (int)($_POST['pc_number'] ?? 0);
        $val = (int)($_POST['is_enabled'] ?? 1);
        if (in_array($lab, $lab_rooms) && $pc >= 1 && $pc <= 50) {
            $db->prepare("INSERT INTO lab_pcs (lab_room, pc_number, is_enabled) VALUES (?,?,?)
                ON CONFLICT(lab_room, pc_number) DO UPDATE SET is_enabled=excluded.is_enabled")
               ->execute([$lab, $pc, $val]);
            echo json_encode(['ok' => true]);
        }
        exit;
    }

    if ($action === 'toggle_software') {
        $lab = $_POST['lab_room'] ?? '';
        $pc  = (int)($_POST['pc_number'] ?? 0);
        $sw  = $_POST['software'] ?? '';
        $val = (int)($_POST['is_enabled'] ?? 1);
        if (in_array($lab, $lab_rooms) && $pc >= 1 && $pc <= 50 && in_array($sw, $all_software)) {
            $db->prepare("INSERT INTO pc_software (lab_room, pc_number, software, is_enabled) VALUES (?,?,?,?)
                ON CONFLICT(lab_room, pc_number, software) DO UPDATE SET is_enabled=excluded.is_enabled")
               ->execute([$lab, $pc, $sw, $val]);
            echo json_encode(['ok' => true]);
        }
        exit;
    }
    exit;
}

// Fetch PC enabled states for selected lab
$pc_states = [];
$rows = $db->prepare("SELECT pc_number, is_enabled FROM lab_pcs WHERE lab_room = ?");
$rows->execute([$selected_lab]);
foreach ($rows->fetchAll() as $r) $pc_states[$r['pc_number']] = (int)$r['is_enabled'];

// Fetch software states for selected lab
$sw_states = [];
$rows2 = $db->prepare("SELECT pc_number, software, is_enabled FROM pc_software WHERE lab_room = ?");
$rows2->execute([$selected_lab]);
foreach ($rows2->fetchAll() as $r) $sw_states[$r['pc_number']][$r['software']] = (int)$r['is_enabled'];

// Fetch occupied (active) and pending PCs for selected lab
$occupied_pcs = [];
$occ = $db->prepare("SELECT pc_number FROM sitin_logs WHERE lab_room=? AND status='active' AND pc_number IS NOT NULL");
$occ->execute([$selected_lab]);
foreach ($occ->fetchAll() as $r) $occupied_pcs[] = (int)$r['pc_number'];

$pending_pcs = [];
$pen = $db->prepare("SELECT pc_number FROM sitin_logs WHERE lab_room=? AND status='pending' AND pc_number IS NOT NULL");
$pen->execute([$selected_lab]);
foreach ($pen->fetchAll() as $r) $pending_pcs[] = (int)$r['pc_number'];

$nav_admin_active = 'software';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS — Software Management</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }

    .lab-tabs { display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap; }
    .lab-tab {
      padding:16px 40px; border-radius:14px; font-size:1.05rem; font-weight:700;
      cursor:pointer; border:2px solid rgba(10,77,140,0.15);
      background:white; color:var(--ink-soft); transition:all 0.18s;
      text-decoration:none !important;
    }
    .lab-tab:hover { border-color:#1877c9; color:#1877c9; }
    .lab-tab.active { background:#1877c9; color:white; border-color:#1877c9; }

    /* PC Grid */
    .pc-grid-admin {
      display: grid;
      grid-template-columns: repeat(10, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }

    .pc-admin-seat {
      aspect-ratio: unset;
      min-height: 90px;
      border-radius: 10px;
      border: 2px solid #90cdf4;
      background: #ebf8ff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all .18s;
      font-size: 1.1rem;
      font-weight: 700;
      color: #2b6cb0;
      user-select: none;
      position: relative;
    }
    .pc-admin-seat:hover { background:#bee3f8; transform:scale(1.07); box-shadow:0 4px 12px rgba(10,77,140,0.15); }
    .pc-admin-seat.pc-occupied { background:rgba(229,62,62,0.12); border-color:#fc8181; color:#c53030; cursor:pointer; }
    .pc-admin-seat.pc-occupied:hover { background:rgba(229,62,62,0.2); transform:scale(1.07); box-shadow:0 4px 12px rgba(229,62,62,0.2); }
    .pc-admin-seat.pc-pending  { background:rgba(232,160,32,0.15); border-color:#f6ad55; color:#a06010; cursor:pointer; }
    .pc-admin-seat.pc-pending:hover  { background:rgba(232,160,32,0.25); transform:scale(1.07); box-shadow:0 4px 12px rgba(232,160,32,0.2); }
    .pc-admin-seat.pc-off { cursor:pointer; }
    .pc-admin-seat.pc-off { background:#f7fafc; border-color:#cbd5e0; color:#a0aec0; }
    .pc-admin-seat.pc-off:hover { background:#edf2f7; }
    .pc-admin-seat svg { width:34px; height:34px; margin-bottom:8px; }

    .pc-sw-badge {
      position:absolute; top:-5px; right:-5px;
      background:#27ae60; color:white;
      font-size:0.65rem; font-weight:700;
      min-width:20px; height:20px;
      border-radius:20px; display:flex;
      align-items:center; justify-content:center;
      padding:0 3px; border:2px solid white;
    }
    .pc-sw-badge.partial { background:#e8a020; }
    .pc-sw-badge.none    { background:#cbd5e0; color:#718096; }

    /* Modal */
    .sw-modal-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.5); z-index:1000;
      align-items:center; justify-content:center;
    }
    .sw-modal-overlay.active { display:flex; }
    .sw-modal {
      background:#fff; border-radius:16px; padding:28px 32px;
      width:90%; max-width:480px;
      box-shadow:0 8px 40px rgba(0,0,0,.18);
      animation:modalIn .2s ease;
    }
    @keyframes modalIn { from{transform:translateY(24px);opacity:0} to{transform:translateY(0);opacity:1} }

    .sw-modal-header {
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:6px;
    }
    .sw-modal-title { font-size:1.05rem; font-weight:700; color:#1a202c; }
    .sw-modal-close {
      width:30px; height:30px; border-radius:8px; border:none;
      background:rgba(10,77,140,0.07); color:#4a5568;
      font-size:1.1rem; cursor:pointer; display:flex;
      align-items:center; justify-content:center;
      transition:background .15s;
    }
    .sw-modal-close:hover { background:rgba(10,77,140,0.15); }
    .sw-modal-sub { font-size:.8rem; color:#718096; margin-bottom:20px; }

    /* PC Enable toggle */
    .sw-modal-pc-toggle {
      display:flex; align-items:center; justify-content:space-between;
      padding:12px 16px; border-radius:10px;
      background:rgba(10,77,140,0.04);
      border:1.5px solid rgba(10,77,140,0.1);
      margin-bottom:18px;
    }
    .sw-modal-pc-label { font-size:.88rem; font-weight:700; color:var(--blue-deep); }

    /* Software list */
    .sw-modal-list { display:flex; flex-direction:column; gap:8px; margin-bottom:22px; }
    .sw-modal-item {
      display:flex; align-items:center; justify-content:space-between;
      padding:10px 14px; border-radius:9px;
      border:1.5px solid rgba(204,222,237,0.6);
      background:#fafcff; transition:background .15s;
    }
    .sw-modal-item:hover { background:#f0f7ff; }
    .sw-modal-item.sw-off { opacity:.5; }
    .sw-name { font-size:.85rem; font-weight:500; color:#2d3748; }

    /* Toggle switch */
    .toggle-switch { position:relative; width:42px; height:24px; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider {
      position:absolute; inset:0; background:#cbd5e0;
      border-radius:24px; cursor:pointer; transition:background .2s;
    }
    .toggle-slider::before {
      content:''; position:absolute;
      width:18px; height:18px; left:3px; top:3px;
      background:white; border-radius:50%;
      transition:transform .2s;
      box-shadow:0 1px 3px rgba(0,0,0,.2);
    }
    .toggle-switch input:checked + .toggle-slider { background:#27ae60; }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(18px); }
    .toggle-switch input:disabled + .toggle-slider { opacity:.5; cursor:not-allowed; }

    .sw-modal-footer {
      display:flex; gap:10px; justify-content:flex-end;
    }
    .sw-btn {
      padding:9px 20px; border-radius:8px; font-size:.85rem;
      font-weight:600; cursor:pointer; border:2px solid; transition:all .15s;
    }
    .sw-btn.outline { border-color:#e2e8f0; background:#fff; color:#4a5568; }
    .sw-btn.outline:hover { background:#f7fafc; }
    .sw-btn.green  { border-color:#27ae60; background:#27ae60; color:white; }
    .sw-btn.green:hover { background:#219150; }

    .save-toast {
      position:fixed; bottom:24px; right:24px;
      background:#27ae60; color:white;
      padding:10px 18px; border-radius:10px;
      font-size:.82rem; font-weight:600;
      opacity:0; transition:opacity .3s;
      pointer-events:none; z-index:9999;
      box-shadow:0 4px 16px rgba(39,174,96,.3);
    }
    .save-toast.show { opacity:1; }

    .lab-legend { display:flex; gap:16px; margin-bottom:16px; flex-wrap:wrap; }
    .lab-legend-item { display:flex; align-items:center; gap:6px; font-size:.78rem; color:#4a5568; }
    .lab-legend-dot { width:14px; height:14px; border-radius:4px; }
    .lab-legend-dot.on  { background:#ebf8ff; border:2px solid #90cdf4; }
    .lab-legend-dot.off { background:#f7fafc; border:2px solid #cbd5e0; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Software &amp; PC Management</h2>

  <!-- Lab Tabs -->
  <div class="lab-tabs">
    <?php foreach ($lab_rooms as $room): ?>
      <a href="AdminSoftware.php?lab=<?= urlencode($room) ?>"
         class="lab-tab <?= $selected_lab === $room ? 'active' : '' ?>">
        <?= htmlspecialchars($room) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="admin-card">
    <div class="admin-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      <?= htmlspecialchars($selected_lab) ?> — Click a PC to manage its software
    </div>
    <div style="padding:20px;">

      <div class="lab-legend">
        <div class="lab-legend-item"><div class="lab-legend-dot on"></div> Available</div>
        <div class="lab-legend-item"><div class="lab-legend-dot off"></div> Disabled</div>
        <div class="lab-legend-item"><div class="lab-legend-dot" style="background:rgba(229,62,62,0.12);border:2px solid #fc8181;"></div> Occupied (Active)</div>
        <div class="lab-legend-item"><div class="lab-legend-dot" style="background:rgba(232,160,32,0.15);border:2px solid #f6ad55;"></div> Pending (Reserved)</div>
      </div>

      <div class="pc-grid-admin" id="pcGrid">
        <?php for ($pc = 1; $pc <= 50; $pc++):
          $is_enabled  = $pc_states[$pc] ?? 1;
          $pc_sw       = $sw_states[$pc] ?? [];
          $enabled_sw  = array_filter($pc_sw, fn($v) => $v === 1);
          $total_sw    = count($all_software);
          $enabled_cnt = count($enabled_sw);
          // Badge: if no sw config, assume all enabled
          $has_config  = !empty($pc_sw);
          $badge_class = !$has_config ? '' : ($enabled_cnt === $total_sw ? '' : ($enabled_cnt === 0 ? 'none' : 'partial'));
          $badge_label = !$has_config ? 'All' : ($enabled_cnt === 0 ? '0' : $enabled_cnt);
        ?>
        <?php
          $seat_extra = '';
          if (in_array($pc, $occupied_pcs)) $seat_extra = 'pc-occupied';
          elseif (in_array($pc, $pending_pcs)) $seat_extra = 'pc-pending';
          elseif (!$is_enabled) $seat_extra = 'pc-off';
          $seat_title = in_array($pc, $occupied_pcs) ? "PC $pc — Occupied (Active)" : (in_array($pc, $pending_pcs) ? "PC $pc — Pending Reservation" : "PC $pc — Click to manage software");
        ?>
        <div class="pc-admin-seat <?= $seat_extra ?>"
             id="pc-seat-<?= $pc ?>"
             onclick="openSwModal(<?= $pc ?>)"
             title="<?= $seat_title ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
          </svg>
          <?= $pc ?>
          <span class="pc-sw-badge <?= $badge_class ?>"><?= $badge_label ?></span>
        </div>
        <?php endfor; ?>
      </div>

    </div>
  </div>
</main>

<!-- Software Modal -->
<div class="sw-modal-overlay" id="swModal">
  <div class="sw-modal">
    <div class="sw-modal-header">
      <div class="sw-modal-title" id="swModalTitle">PC #1 — Software</div>
      <button class="sw-modal-close" onclick="closeSwModal()">&#10005;</button>
    </div>
    <div class="sw-modal-sub" id="swModalSub">Toggle software availability for this PC.</div>

    <!-- PC Enable/Disable -->
    <div class="sw-modal-pc-toggle">
      <span class="sw-modal-pc-label">PC Status</span>
      <label class="toggle-switch">
        <input type="checkbox" id="pcEnableToggle" onchange="togglePcEnabled(this.checked)"/>
        <span class="toggle-slider"></span>
      </label>
    </div>

    <!-- Software List -->
    <div class="sw-modal-list" id="swModalList"></div>

    <div class="sw-modal-footer">
      <button class="sw-btn outline" onclick="closeSwModal()">Close</button>
      <button class="sw-btn green" onclick="enableAllSoftware()">Enable All</button>
    </div>
  </div>
</div>

<div class="save-toast" id="saveToast">✓ Saved!</div>

<?php
echo '<script>';
echo 'const OCCUPIED = <?= json_encode($occupied_pcs) ?>;
const PENDING  = <?= json_encode($pending_pcs) ?>;
const LAB = ' . json_encode($selected_lab) . ';';
echo 'const ALL_SW = ' . json_encode($all_software) . ';';
echo 'const pcStates = ' . json_encode($pc_states) . ';';
echo 'const swStates = ' . json_encode($sw_states) . ';';
echo '</script>';
?>
<script>
let currentPc = null;

function showToast() {
  const t = document.getElementById('saveToast');
  t.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => t.classList.remove('show'), 1800);
}

function post(data) {
  return fetch('AdminSoftware.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams(data)
  }).then(r => r.json());
}

function openSwModal(pc) {
  currentPc = pc;
  document.getElementById('swModalTitle').textContent = '🖥️ PC #' + pc + ' — ' + LAB;
  document.getElementById('swModalSub').textContent   = 'Enable or disable software available on PC #' + pc + '.';

  // PC enable toggle
  const isEnabled = pcStates[pc] !== undefined ? pcStates[pc] : 1;
  document.getElementById('pcEnableToggle').checked = !!isEnabled;

  // Build software list
  buildSwList(pc, !!isEnabled);
  document.getElementById('swModal').classList.add('active');
}

function buildSwList(pc, pcEnabled) {
  const list = document.getElementById('swModalList');
  list.innerHTML = '';
  ALL_SW.forEach(sw => {
    const swEnabled = swStates[pc] && swStates[pc][sw] !== undefined ? swStates[pc][sw] : 1;
    const item = document.createElement('div');
    item.className = 'sw-modal-item' + (swEnabled ? '' : ' sw-off');
    item.id = 'sw-item-' + pc + '-' + ALL_SW.indexOf(sw);
    item.innerHTML = `
      <span class="sw-name">${sw}</span>
      <label class="toggle-switch">
        <input type="checkbox" ${swEnabled ? 'checked' : ''} ${!pcEnabled ? 'disabled' : ''}
          onchange="toggleSoftware(${pc}, ${JSON.stringify(sw)}, this.checked, ${ALL_SW.indexOf(sw)})"/>
        <span class="toggle-slider"></span>
      </label>`;
    list.appendChild(item);
  });
}

function closeSwModal() {
  document.getElementById('swModal').classList.remove('active');
  currentPc = null;
}

function togglePcEnabled(enabled) {
  const val = enabled ? 1 : 0;
  const seat = document.getElementById('pc-seat-' + currentPc);
  if (seat) seat.classList.toggle('pc-off', !enabled);

  // disable all software toggles if PC is off
  document.querySelectorAll('#swModalList input[type=checkbox]').forEach(cb => cb.disabled = !enabled);

  // Update local state
  pcStates[currentPc] = val;

  post({ action: 'toggle_pc', lab_room: LAB, pc_number: currentPc, is_enabled: val })
    .then(() => showToast());
}

function toggleSoftware(pc, sw, enabled, idx) {
  const item = document.getElementById('sw-item-' + pc + '-' + idx);
  if (item) item.classList.toggle('sw-off', !enabled);

  // Update local state
  if (!swStates[pc]) swStates[pc] = {};
  swStates[pc][sw] = enabled ? 1 : 0;

  updatePcBadge(pc);

  post({ action: 'toggle_software', lab_room: LAB, pc_number: pc, software: sw, is_enabled: enabled ? 1 : 0 })
    .then(() => showToast());
}

function enableAllSoftware() {
  ALL_SW.forEach((sw, idx) => {
    if (!swStates[currentPc]) swStates[currentPc] = {};
    swStates[currentPc][sw] = 1;
    post({ action: 'toggle_software', lab_room: LAB, pc_number: currentPc, software: sw, is_enabled: 1 });
    const item = document.getElementById('sw-item-' + currentPc + '-' + idx);
    if (item) {
      item.classList.remove('sw-off');
      item.querySelector('input[type=checkbox]').checked = true;
    }
  });
  updatePcBadge(currentPc);
  showToast();
}

function updatePcBadge(pc) {
  const seat = document.getElementById('pc-seat-' + pc);
  if (!seat) return;
  const badge = seat.querySelector('.pc-sw-badge');
  if (!badge) return;

  const pcSw = swStates[pc] || {};
  const enabledCnt = Object.values(pcSw).filter(v => v === 1).length;
  const total = ALL_SW.length;

  badge.textContent = Object.keys(pcSw).length === 0 ? 'All' : enabledCnt;
  badge.className = 'pc-sw-badge';
  if (Object.keys(pcSw).length > 0) {
    if (enabledCnt === 0) badge.classList.add('none');
    else if (enabledCnt < total) badge.classList.add('partial');
  }
}

// Close on overlay click
document.getElementById('swModal').addEventListener('click', function(e) {
  if (e.target === this) closeSwModal();
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>