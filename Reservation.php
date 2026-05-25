<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['student'])) { header('Location: Login.php'); exit; }

$db   = get_db();
$stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['student']['id']]);
$student = $stmt->fetch();

if (!$student) { session_destroy(); header('Location: Login.php'); exit; }

$remaining = $student['sessions'] - $student['used'];
$errors    = [];
$success   = '';

// Pull lab rooms dynamically from DB so new labs added by admin appear automatically
$lab_rows  = $db->query("SELECT lab_room, is_enabled FROM lab_settings ORDER BY lab_room")->fetchAll();
$lab_rooms = array_column($lab_rows, 'lab_room');
$lab_settings = [];
foreach ($lab_rows as $row) {
    $lab_settings[$row['lab_room']] = $row['is_enabled'] == 1 ? 'open' : 'closed';
}
// Only show globally enabled software as purposes
$sw_enabled = $db->query("SELECT DISTINCT software FROM pc_software WHERE is_enabled=1 ORDER BY software")->fetchAll(PDO::FETCH_COLUMN);
// Fallback hardcoded list for non-software purposes always shown
$non_sw_purposes = ['Research / Thesis', 'Other'];
$all_purposes = array_values(array_unique(array_merge($sw_enabled ?: ['C / C++','Java','Python','PHP / Web Development','Database (SQL)','Networking'], $non_sw_purposes)));

// --- Build per-lab PC data (occupied, disabled, software) ---
$lab_data = [];
foreach ($lab_rooms as $room) {
    // Occupied PCs
    $s = $db->prepare("SELECT pc_number FROM sitin_logs WHERE lab_room=? AND status IN ('active','pending') AND pc_number IS NOT NULL");
    $s->execute([$room]);
    $occupied = array_column($s->fetchAll(), 'pc_number');

    // Disabled PCs — use status column (authoritative)
    $d = $db->prepare("SELECT pc_number FROM lab_pcs WHERE lab_room=? AND status='disabled'");
    $d->execute([$room]);
    $disabled = array_column($d->fetchAll(), 'pc_number');

    // Maintenance PCs
    $m = $db->prepare("SELECT pc_number FROM lab_pcs WHERE lab_room=? AND status='maintenance'");
    $m->execute([$room]);
    $maintenance = array_column($m->fetchAll(), 'pc_number');

    // Software per PC — only ENABLED ones
    $sw = $db->prepare("SELECT pc_number, software FROM pc_software WHERE lab_room=? AND is_enabled=1");
    $sw->execute([$room]);
    $sw_map = [];
    foreach ($sw->fetchAll() as $r) {
        $sw_map[$r['pc_number']][] = $r['software'];
    }

    $lab_data[$room] = [
        'occupied'    => $occupied,
        'disabled'    => $disabled,
        'maintenance' => $maintenance,
        'software'    => $sw_map,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_room  = trim($_POST['lab_room']  ?? '');
    $purpose   = trim($_POST['purpose']   ?? '');
    $date_in   = trim($_POST['date_in']   ?? '');
    $pc_number = isset($_POST['pc_number']) && $_POST['pc_number'] !== '' ? (int)$_POST['pc_number'] : null;

    if ($lab_room  === '') $errors['lab_room']  = 'Please select a lab room.';
    if ($purpose   === '') $errors['purpose']   = 'Please select a purpose.';
    if ($date_in   === '') $errors['date_in']   = 'Please select a date and time.';
    if ($pc_number === null) $errors['pc_number'] = 'Please select a PC seat.';

    if (!isset($errors['lab_room']) && !in_array($lab_room, $lab_rooms))
        $errors['lab_room'] = 'Invalid lab room.';

    // Check if lab is disabled by admin
    if (!isset($errors['lab_room']) && $lab_room !== '') {
        $lab_check = $db->prepare("SELECT is_enabled FROM lab_settings WHERE lab_room = ? LIMIT 1");
        $lab_check->execute([$lab_room]);
        $lab_row = $lab_check->fetch();
        if ($lab_row && $lab_row['is_enabled'] == 0) {
            $errors['lab_room'] = $lab_room . ' is currently closed for reservations.';
        }
    }
    if ($pc_number !== null && ($pc_number < 1 || $pc_number > 50))
        $errors['pc_number'] = 'Invalid PC number.';

    // Check PC disabled
    if (empty($errors) && $pc_number !== null) {
        $dis = $db->prepare("SELECT id FROM lab_pcs WHERE lab_room=? AND pc_number=? AND status='disabled' LIMIT 1");
        $dis->execute([$lab_room, $pc_number]);
        if ($dis->fetch()) $errors['pc_number'] = 'PC #'.$pc_number.' is currently disabled by the admin. Please select another PC.';
    }

    // Check PC under maintenance
    if (empty($errors) && $pc_number !== null) {
        $mnt = $db->prepare("SELECT id FROM lab_pcs WHERE lab_room=? AND pc_number=? AND status='maintenance' LIMIT 1");
        $mnt->execute([$lab_room, $pc_number]);
        if ($mnt->fetch()) $errors['pc_number'] = 'PC #'.$pc_number.' is currently under maintenance. Please select another PC.';
    }

    // Check PC occupied
    if (empty($errors) && $pc_number !== null) {
        $taken = $db->prepare("SELECT id FROM sitin_logs WHERE lab_room=? AND pc_number=? AND status IN ('active','pending') LIMIT 1");
        $taken->execute([$lab_room, $pc_number]);
        if ($taken->fetch()) $errors['pc_number'] = 'That PC is already taken. Please choose another.';
    }

    // Check software is not disabled for the chosen PC
    if (empty($errors) && $pc_number !== null && $purpose !== '') {
        $sw_check = $db->prepare("SELECT is_enabled FROM pc_software WHERE lab_room=? AND pc_number=? AND software=? LIMIT 1");
        $sw_check->execute([$lab_room, $pc_number, $purpose]);
        $sw_row = $sw_check->fetch();
        if ($sw_row && $sw_row['is_enabled'] == 0) {
            $errors['purpose'] = '"'.$purpose.'" is not available on that PC. Please choose another software.';
        }
    }

    if (empty($errors) && $remaining <= 0)
        $errors['general'] = 'You have no remaining sit-in sessions this semester.';

    if (empty($errors)) {
        $active = $db->prepare("SELECT id FROM sitin_logs WHERE student_id=? AND status IN ('active','pending') LIMIT 1");
        $active->execute([$student['id']]);
        if ($active->fetch()) $errors['general'] = 'You already have an active sit-in session.';
    }

    if (empty($errors)) {
        $db->prepare("INSERT INTO sitin_logs (student_id, lab_room, purpose, date_in, status, pc_number) VALUES (?,?,?,?,'pending',?)")
           ->execute([$student['id'], $lab_room, $purpose, $date_in, $pc_number]);

  // NOTE: session deduction now happens when the admin ends the sit-in (AdminSitin.php)
  // previously we incremented 'used' here on reservation creation; remove that so
  // students keep their session until admin marks the session completed.
        $notif_msg = "{$student['first_name']} {$student['last_name']} ({$student['student_id']}) reserved PC #{$pc_number} in {$lab_room} for {$purpose}.";
        log_notification('reservation', $notif_msg);

        $success = "Reservation submitted! PC #$pc_number in $lab_room. Please wait for admin approval.";

        $stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
        $stmt->execute([$student['id']]);
        $student   = $stmt->fetch();
        $remaining = $student['sessions'] - $student['used'];

        // Refresh lab data
        foreach ($lab_rooms as $room) {
            $s = $db->prepare("SELECT pc_number FROM sitin_logs WHERE lab_room=? AND status IN ('active','pending') AND pc_number IS NOT NULL");
            $s->execute([$room]);
            $lab_data[$room]['occupied'] = array_column($s->fetchAll(), 'pc_number');
        }
    }
}

$reservations = $db->prepare("SELECT * FROM sitin_logs WHERE student_id=? ORDER BY date_in DESC LIMIT 10");
$reservations->execute([$student['id']]);
$reservations = $reservations->fetchAll();

$nav_student_active = 'reservation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Reservation</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <link rel="stylesheet" href="css/Students.css"/>
  <link rel="stylesheet" href="css/Reservation.css"/>
  <style>
    .pc-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; align-items:center; justify-content:center; }
    .pc-modal-overlay.active { display:flex; }
    .pc-modal { background:#fff; border-radius:16px; padding:32px; width:90%; max-width:640px; box-shadow:0 8px 40px rgba(0,0,0,.18); animation:modalIn .2s ease; }
    @keyframes modalIn { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
    .pc-modal-title { font-size:1.1rem; font-weight:700; margin-bottom:4px; color:#1a202c; }
    .pc-modal-sub   { font-size:.83rem; color:#718096; margin-bottom:16px; }
    .pc-legend { display:flex; gap:16px; margin-bottom:14px; flex-wrap:wrap; }
    .pc-legend-item { display:flex; align-items:center; gap:6px; font-size:.78rem; color:#4a5568; }
    .pc-legend-dot { width:14px; height:14px; border-radius:3px; }
    .pc-legend-dot.available    { background:#ebf8ff; border:2px solid #90cdf4; }
    .pc-legend-dot.occupied     { background:#fff5f5; border:2px solid #fc8181; }
    .pc-legend-dot.disabled     { background:#f7fafc; border:2px solid #cbd5e0; }
    .pc-legend-dot.maintenance  { background:#fffbeb; border:2px solid #f6ad55; }
    .pc-legend-dot.selected     { background:#2b6cb0; border:2px solid #2b6cb0; }
    .pc-grid { display:grid; grid-template-columns:repeat(10,1fr); gap:6px; margin-bottom:20px; }
    .pc-seat { aspect-ratio:1; border-radius:7px; border:2px solid #90cdf4; background:#ebf8ff; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:pointer; transition:all .15s; font-size:.62rem; font-weight:600; color:#2b6cb0; user-select:none; position:relative; }
    .pc-seat:hover:not(.occupied):not(.disabled-seat):not(.maintenance-seat) { background:#bee3f8; transform:scale(1.08); }
    .pc-seat.occupied      { background:#fff5f5; border-color:#fc8181; color:#c53030; cursor:not-allowed; }
    .pc-seat.disabled-seat { background:#f7fafc; border-color:#cbd5e0; color:#a0aec0; cursor:not-allowed; }
    .pc-seat.maintenance-seat { background:#fffbeb; border-color:#f6ad55; color:#b7791f; cursor:not-allowed; }
    .pc-seat.selected      { background:#2b6cb0; border-color:#2b6cb0; color:#fff; transform:scale(1.08); }
    .pc-seat svg { width:12px; height:12px; margin-bottom:1px; }

    /* Tooltip on hover */
    .pc-seat .pc-tooltip {
      display:none; position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%);
      background:#1a202c; color:#fff; font-size:0.7rem; font-weight:500;
      padding:5px 10px; border-radius:7px; white-space:nowrap; z-index:999;
      pointer-events:none; box-shadow:0 4px 12px rgba(0,0,0,0.2);
    }
    .pc-seat .pc-tooltip::after {
      content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%);
      border:5px solid transparent; border-top-color:#1a202c;
    }
    .pc-seat:hover .pc-tooltip { display:block; }

    /* Software panel inside modal */
    .pc-sw-panel { background:#f7fafc; border-radius:10px; padding:12px 14px; margin-bottom:16px; display:none; }
    .pc-sw-panel.visible { display:block; }
    .pc-sw-title { font-size:.75rem; font-weight:700; color:#2b6cb0; margin-bottom:8px; }
    .pc-sw-list  { display:flex; flex-wrap:wrap; gap:6px; }
    .pc-sw-tag   { padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:600; background:#ebf8ff; color:#2b6cb0; border:1px solid #90cdf4; }
    .pc-sw-empty { font-size:.75rem; color:#a0aec0; }

    .pc-modal-actions { display:flex; gap:10px; justify-content:flex-end; }
    .pc-btn-cancel  { padding:9px 20px; border-radius:8px; border:2px solid #e2e8f0; background:#fff; color:#4a5568; font-weight:600; cursor:pointer; font-size:.88rem; }
    .pc-btn-confirm { padding:9px 20px; border-radius:8px; border:none; background:#2b6cb0; color:#fff; font-weight:600; cursor:pointer; font-size:.88rem; opacity:.4; pointer-events:none; }
    .pc-btn-confirm.ready { opacity:1; pointer-events:all; }
    .res-pc-trigger { margin-top:8px; padding:10px 16px; border-radius:8px; border:2px dashed #90cdf4; background:#ebf8ff; color:#2b6cb0; font-weight:600; cursor:pointer; font-size:.88rem; width:100%; text-align:left; display:none; transition:background .15s; }
    .res-pc-trigger:hover { background:#bee3f8; }
    .res-pc-trigger.visible { display:block; }
  </style>
</head>
<body class="student-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_student.php'; ?>
<main class="reservation-main" style="flex:1;">
  <div class="res-page-header">
    <div class="res-page-header-text">
      <span class="section-eyebrow">SitIn Management</span>
      <h1 class="res-page-title">Reserve a Session</h1>
      <p class="res-page-sub">Book your laboratory sit-in slot below. You have <strong><?= $remaining ?></strong> session<?= $remaining !== 1 ? 's' : '' ?> remaining.</p>
    </div>
    <div class="res-session-badge">
      <span class="rsb-num"><?= $remaining ?></span>
      <span class="rsb-label">Sessions Left</span>
    </div>
  </div>

  <div class="res-layout">
    <div class="res-form-col">
      <div class="res-card">
        <div class="res-card-header">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span>New Reservation</span>
        </div>

        <?php if (!empty($errors['general'])): ?><div class="res-alert res-alert--error"><span>&#10005;</span><?= htmlspecialchars($errors['general']) ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="res-alert res-alert--success"><span>&#10003;</span><?= $success ?></div><?php endif; ?>

        <?php if ($remaining <= 0): ?>
          <div class="res-alert res-alert--warning"><span>&#9888;</span>You have used all your sit-in sessions for this semester.</div>
        <?php else: ?>
        <form method="POST" action="Reservation.php" class="res-form" novalidate>
          <input type="hidden" name="pc_number" id="pc_number_input" value="<?= htmlspecialchars($_POST['pc_number'] ?? '') ?>"/>

          <!-- Lab Room -->
          <div class="res-field <?= isset($errors['lab_room']) ? 'res-field--error' : '' ?>">
            <label for="lab_room">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              Lab Room
            </label>
            <select id="lab_room" name="lab_room" onchange="onLabRoomChange(this.value)">
              <option value="">— Select a room —</option>
              <?php foreach ($lab_rooms as $room): ?>
                <?php $isClosed = $lab_settings[$room] === 'closed'; ?>
                <option value="<?= $room ?>"
                  <?= ($_POST['lab_room'] ?? '') === $room ? 'selected' : '' ?>
                  <?= $isClosed ? 'disabled' : '' ?>>
                  <?= htmlspecialchars($room) ?><?= $isClosed ? ' — Closed by Admin' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['lab_room'])): ?><span class="res-field-msg"><?= htmlspecialchars($errors['lab_room']) ?></span><?php endif; ?>
            <span id="lab_status_msg" style="display:none;color:#b7791f;background:#fffbeb;border:1px solid #f6ad55;border-radius:8px;padding:7px 12px;font-size:0.82rem;font-weight:500;margin-top:6px;display:none;"></span>
          </div>

          <!-- PC Seat -->
          <div class="res-field <?= isset($errors['pc_number']) ? 'res-field--error' : '' ?>">
            <label>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              PC Seat
            </label>
            <button type="button" class="res-pc-trigger <?= ($_POST['lab_room'] ?? '') !== '' ? 'visible' : '' ?>" id="pc_trigger" onclick="openPcModal()">
              🖥️ <span id="pc_trigger_text">Click to select a PC seat</span>
            </button>
            <?php if (isset($errors['pc_number'])): ?><span class="res-field-msg"><?= htmlspecialchars($errors['pc_number']) ?></span><?php endif; ?>
          </div>

          <!-- Purpose (dynamic based on selected PC) -->
          <div class="res-field <?= isset($errors['purpose']) ? 'res-field--error' : '' ?>">
            <label for="purpose">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
              Purpose / Language
            </label>
            <select id="purpose" name="purpose">
              <option value="">— Select a PC seat first —</option>
            </select>
            <?php if (isset($errors['purpose'])): ?><span class="res-field-msg"><?= htmlspecialchars($errors['purpose']) ?></span><?php endif; ?>
          </div>

          <!-- Date -->
          <div class="res-field <?= isset($errors['date_in']) ? 'res-field--error' : '' ?>">
            <label for="date_in">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Date &amp; Time
            </label>
            <input type="datetime-local" id="date_in" name="date_in"
              value="<?= htmlspecialchars($_POST['date_in'] ?? '') ?>"
              min="<?= date('Y-m-d\TH:i') ?>"/>
            <?php if (isset($errors['date_in'])): ?><span class="res-field-msg"><?= htmlspecialchars($errors['date_in']) ?></span><?php endif; ?>
          </div>

          <div class="res-info-box">
            <strong>Reminders:</strong>
            <ul>
              <li>Use lab time responsibly; vacate once your work is complete.</li>
              <li>Bring your valid <strong>UC Student ID</strong>.</li>
              <li>Arrive on time — late arrivals may forfeit the slot.</li>
            </ul>
          </div>

          <button type="submit" class="res-submit-btn">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Confirm Reservation
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="res-history-col">
      <div class="res-card">
        <div class="res-card-header">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 1 .5 4"/><polyline points="3 21 3 16 8 16"/></svg>
          <span>My Recent Reservations</span>
        </div>
        <?php if (empty($reservations)): ?>
          <div class="res-empty">
            <p>No reservations yet.</p>
            <small>Your sit-in history will appear here.</small>
          </div>
        <?php else: ?>
          <div class="res-history-list">
            <?php foreach ($reservations as $log): ?>
            <div class="res-history-item">
              <div class="rhi-top">
                <span class="rhi-room"><?= htmlspecialchars($log['lab_room']) ?></span>
                <span class="rhi-status rhi-status--<?= $log['status'] ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span>
              </div>
              <div class="rhi-purpose"><?= htmlspecialchars($log['purpose']) ?><?php if ($log['pc_number']): ?> &mdash; PC #<?= $log['pc_number'] ?><?php endif; ?></div>
              <div class="rhi-date">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= htmlspecialchars($log['date_in']) ?>
                <?php if ($log['date_out']): ?> &rarr; <?= htmlspecialchars($log['date_out']) ?><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <a href="History.php" class="res-view-all">View full history &rarr;</a>
        <?php endif; ?>
      </div>

      <div class="res-summary-card">
        <div class="rsc-row"><span class="rsc-label">Total Sessions</span><span class="rsc-val"><?= $student['sessions'] ?></span></div>
        <div class="rsc-divider"></div>
        <div class="rsc-row"><span class="rsc-label">Sessions Used</span><span class="rsc-val used"><?= $student['used'] ?></span></div>
        <div class="rsc-divider"></div>
        <div class="rsc-row"><span class="rsc-label">Sessions Remaining</span><span class="rsc-val remaining"><?= $remaining ?></span></div>
        <div class="rsc-bar-wrap">
          <div class="rsc-bar">
            <div class="rsc-bar-fill" style="width:<?= ($student['sessions'] > 0) ? round(($student['used']/$student['sessions'])*100) : 0 ?>%"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- PC Picker Modal -->
<div class="pc-modal-overlay" id="pc_modal">
  <div class="pc-modal">
    <div class="pc-modal-title">🖥️ Select Your PC Seat</div>
    <div class="pc-modal-sub" id="pc_modal_sub">Choose an available seat in the selected lab room.</div>
    <div class="pc-legend">
      <div class="pc-legend-item"><div class="pc-legend-dot available"></div> Available</div>
      <div class="pc-legend-item"><div class="pc-legend-dot occupied"></div> Occupied</div>
      <div class="pc-legend-item"><div class="pc-legend-dot disabled"></div> Disabled</div>
      <div class="pc-legend-item"><div class="pc-legend-dot maintenance"></div> Under Maintenance</div>
      <div class="pc-legend-item"><div class="pc-legend-dot selected"></div> Your Selection</div>
    </div>
    <div class="pc-grid" id="pc_grid"></div>

    <!-- Software panel for selected PC -->
    <div class="pc-sw-panel" id="pc_sw_panel">
      <div class="pc-sw-title">🛠️ Available Software on PC <span id="pc_sw_num"></span></div>
      <div class="pc-sw-list" id="pc_sw_list"></div>
    </div>

    <div class="pc-modal-actions">
      <button type="button" class="pc-btn-cancel" onclick="closePcModal()">Cancel</button>
      <button type="button" class="pc-btn-confirm" id="pc_confirm_btn" onclick="confirmPcSelection()">Confirm Seat</button>
    </div>
  </div>
</div>

<?php
$labDataJson     = json_encode($lab_data);
$allPurposes     = json_encode($all_purposes);
$labSettingsJson = json_encode($lab_settings);
?>
<script>
const labData     = <?= $labDataJson ?>;
const allPurposes = <?= $allPurposes ?>;
const labSettings = <?= $labSettingsJson ?>;
let selectedPc  = null;
let currentRoom = '';

function onLabRoomChange(room) {
  selectedPc = null;
  currentRoom = room;
  document.getElementById('pc_number_input').value = '';
  document.getElementById('pc_trigger_text').textContent = 'Click to select a PC seat';
  document.getElementById('pc_trigger').classList.toggle('visible', !!room);

  // Show lab closed warning inline
  const labMsg = document.getElementById('lab_status_msg');
  if (labMsg) {
    if (room && labSettings[room] === 'closed') {
      labMsg.textContent = '⚠️ ' + room + ' is currently closed by the admin. Please choose another lab.';
      labMsg.style.display = 'block';
      document.getElementById('pc_trigger').classList.remove('visible');
    } else {
      labMsg.style.display = 'none';
    }
  }
  updatePurposeDropdown(null);
}

function openPcModal() {
  currentRoom = document.getElementById('lab_room').value;
  if (!currentRoom) return;
  if (labSettings[currentRoom] === 'closed') {
    alert('⚠️ ' + currentRoom + ' is currently closed by the admin. Please select a different lab room.');
    return;
  }
  document.getElementById('pc_modal_sub').textContent = 'Choose an available seat in ' + currentRoom + '.';
  buildGrid();
  document.getElementById('pc_modal').classList.add('active');
}

function closePcModal() {
  document.getElementById('pc_modal').classList.remove('active');
}

function buildGrid() {
  const grid        = document.getElementById('pc_grid');
  const data        = labData[currentRoom] || {};
  const occupied    = data.occupied    || [];
  const disabled    = data.disabled    || [];
  const maintenance = data.maintenance || [];
  grid.innerHTML = '';

  for (let i = 1; i <= 50; i++) {
    const isOccupied    = occupied.includes(i);
    const isDisabled    = disabled.includes(i);
    const isMaintenance = maintenance.includes(i);
    const isSelected    = selectedPc === i;

    const seat = document.createElement('div');
    let cls = 'pc-seat';
    if (isOccupied)    cls += ' occupied';
    if (isDisabled)    cls += ' disabled-seat';
    if (isMaintenance) cls += ' maintenance-seat';
    if (isSelected)    cls += ' selected';
    seat.className = cls;
    seat.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>${i}`;

    let tooltipMsg = '';
    if (isOccupied)         tooltipMsg = `PC ${i} — Currently in use by another student`;
    else if (isDisabled)    tooltipMsg = `PC ${i} — Disabled by admin, unavailable`;
    else if (isMaintenance) tooltipMsg = `PC ${i} — Under maintenance, please choose another`;
    else if (isSelected)    tooltipMsg = `PC ${i} — Your selected seat`;
    else                    tooltipMsg = `PC ${i} — Click to select`;

    const tooltip = document.createElement('span');
    tooltip.className = 'pc-tooltip';
    tooltip.textContent = tooltipMsg;
    seat.appendChild(tooltip);

    if (!isOccupied && !isDisabled && !isMaintenance) {
      seat.onclick = () => selectSeat(i);
    }

    grid.appendChild(seat);
  }
}

function selectSeat(num) {
  selectedPc = num;
  buildGrid();
  document.getElementById('pc_confirm_btn').classList.add('ready');

  // Show software panel
  const data   = labData[currentRoom] || {};
  const swMap  = data.software || {};
  const swList = swMap[num] || [];

  document.getElementById('pc_sw_num').textContent = num;
  const listEl = document.getElementById('pc_sw_list');
  listEl.innerHTML = '';
  if (swList.length === 0) {
    listEl.innerHTML = '<span class="pc-sw-empty">All software available</span>';
  } else {
    swList.forEach(sw => {
      const tag = document.createElement('span');
      tag.className = 'pc-sw-tag';
      tag.textContent = sw;
      listEl.appendChild(tag);
    });
  }
  document.getElementById('pc_sw_panel').classList.add('visible');
}

function confirmPcSelection() {
  if (!selectedPc) return;
  document.getElementById('pc_number_input').value = selectedPc;
  document.getElementById('pc_trigger_text').textContent = '✅ PC #' + selectedPc + ' selected — click to change';
  updatePurposeDropdown(selectedPc);
  closePcModal();
}

function updatePurposeDropdown(pcNum) {
  const sel  = document.getElementById('purpose');
  const data = labData[currentRoom] || {};
  const swMap = data.software || {};

  let available = [];
  if (pcNum && swMap[pcNum] && swMap[pcNum].length > 0) {
    available = swMap[pcNum];
  } else {
    available = allPurposes;
  }

  const prev = sel.value;
  sel.innerHTML = '<option value="">— Select a purpose —</option>';
  available.forEach(sw => {
    const opt = document.createElement('option');
    opt.value = sw; opt.textContent = sw;
    if (sw === prev) opt.selected = true;
    sel.appendChild(opt);
  });
}

document.getElementById('pc_modal').addEventListener('click', function(e) {
  if (e.target === this) closePcModal();
});

window.addEventListener('DOMContentLoaded', () => {
  const room = document.getElementById('lab_room').value;
  if (room) { currentRoom = room; document.getElementById('pc_trigger').classList.add('visible'); }
  const existingPc = parseInt(document.getElementById('pc_number_input').value);
  if (existingPc) {
    selectedPc = existingPc;
    document.getElementById('pc_trigger_text').textContent = '✅ PC #' + existingPc + ' selected — click to change';
    updatePurposeDropdown(existingPc);
  } else if (room) {
    updatePurposeDropdown(null);
  }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>