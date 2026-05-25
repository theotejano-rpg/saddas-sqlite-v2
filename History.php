<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['student'])) { header('Location: Login.php'); exit; }

$db = get_db();
$student = $_SESSION['student'];

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_log_id'])) {
    $log_id  = (int)$_POST['feedback_log_id'];
    $feedback = trim($_POST['feedback_text'] ?? '');
    if ($feedback) {
        $db->prepare("UPDATE sitin_logs SET feedback = ? WHERE id = ? AND student_id = ?")
           ->execute([$feedback, $log_id, $student['id']]);
    }
    header('Location: History.php?msg=feedback_sent'); exit;
}

$logs = $db->prepare("SELECT * FROM sitin_logs WHERE student_id = ? ORDER BY date_in DESC");
$logs->execute([$student['id']]);
$logs = $logs->fetchAll();

$total     = count($logs);
$completed = count(array_filter($logs, fn($l) => $l['status'] === 'completed'));
$active    = count(array_filter($logs, fn($l) => $l['status'] === 'active'));
$pending   = count(array_filter($logs, fn($l) => $l['status'] === 'pending'));
$cancelled = count(array_filter($logs, fn($l) => $l['status'] === 'cancelled'));

$msg = $_GET['msg'] ?? '';
$nav_student_active = 'history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>UC CCS &mdash; My Sit-In History</title>
<link rel="stylesheet" href="css/Style.css"/>
<link rel="stylesheet" href="css/Students.css"/>
<style>
body.student-page a { text-decoration: none !important; }
.history-wrap {
    max-width: 1050px;
    margin: 32px auto 0;
    padding: 0 28px 60px;
    flex: 1;
}
.history-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
.history-stat {
    background: rgba(255,255,255,0.82);
    border: 1px solid rgba(255,255,255,0.9);
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 4px 16px rgba(10,77,140,0.07);
    text-align: center;
}
.history-stat-num {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    color: var(--blue-deep);
    line-height: 1;
}
.history-stat-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--ink-soft);
    margin-top: 4px;
}
.history-card {
    background: rgba(255,255,255,0.82);
    border: 1px solid rgba(255,255,255,0.9);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(10,77,140,0.08);
    overflow: hidden;
}
.history-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 22px;
    background: var(--blue-deep);
    color: white;
    font-family: 'DM Serif Display', serif;
    font-size: 1rem;
}
.history-filter select {
    padding: 5px 10px;
    border: 1.5px solid rgba(255,255,255,0.3);
    border-radius: 6px;
    background: rgba(255,255,255,0.15);
    color: white;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem;
    outline: none;
}
.history-filter select option { color: var(--ink); background: white; }
.history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
}
.history-table th {
    padding: 12px 16px;
    text-align: left;
    background: rgba(10,77,140,0.05);
    color: var(--blue-deep);
    font-weight: 600;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid rgba(10,77,140,0.08);
    white-space: nowrap;
}
.history-table td {
    padding: 13px 16px;
    color: var(--ink);
    border-bottom: 1px solid rgba(204,222,237,0.4);
    vertical-align: middle;
    white-space: nowrap;
}
.history-table tr:last-child td { border-bottom: none; }
.history-table tr:hover td { background: rgba(10,77,140,0.02); }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; }
.badge-pending   { background: rgba(232,160,32,0.12);  color: #a06010; }
.badge-active    { background: rgba(26,140,78,0.12);   color: #1a7a42; }
.badge-completed { background: rgba(10,77,140,0.1);    color: #0a4d8c; }
.badge-cancelled { background: rgba(208,49,45,0.1);    color: #c0392b; }
.feedback-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: #e8a020;
    color: white;
    border: none;
    border-radius: 6px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.18s, transform 0.15s;
}
.feedback-btn:hover { background: #c88010; transform: translateY(-1px); }
.feedback-btn.done {
    background: rgba(26,140,78,0.12);
    color: #1a7a42;
    cursor: default;
}
.feedback-btn.done:hover { transform: none; background: rgba(26,140,78,0.12); }

/* Feedback Modal */
.feedback-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999;
    backdrop-filter: blur(4px);
}
.feedback-modal-overlay.open { display: flex; animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.feedback-modal {
    background: white;
    border-radius: 18px;
    width: 480px;
    max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    animation: slideUp 0.25s ease;
    overflow: hidden;
}
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
.feedback-modal-header {
    background: linear-gradient(135deg, #e8a020, #c88010);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.feedback-modal-title { font-family: 'DM Serif Display', serif; font-size: 1.1rem; color: white; }
.feedback-modal-close {
    background: rgba(255,255,255,0.2);
    border: none; color: white;
    width: 28px; height: 28px;
    border-radius: 50%; font-size: 1rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.feedback-modal-close:hover { background: rgba(255,255,255,0.35); }
.feedback-modal-body { padding: 24px; }
.feedback-session-info {
    background: rgba(232,160,32,0.08);
    border: 1px solid rgba(232,160,32,0.2);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 0.83rem;
    color: var(--ink);
}
.feedback-session-info strong { color: var(--blue-deep); }
.feedback-textarea {
    width: 100%;
    min-height: 120px;
    padding: 12px 14px;
    border: 1.5px solid #ccdeed;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    color: var(--ink);
    resize: vertical;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}
.feedback-textarea:focus {
    border-color: #e8a020;
    box-shadow: 0 0 0 3px rgba(232,160,32,0.12);
}
.feedback-modal-footer {
    padding: 0 24px 24px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.feedback-cancel-btn {
    padding: 10px 20px;
    background: transparent;
    border: 1.5px solid #ccdeed;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    color: var(--ink-soft);
    cursor: pointer;
    transition: all 0.18s;
}
.feedback-cancel-btn:hover { background: #f5f8fb; }
.feedback-submit-btn {
    padding: 10px 24px;
    background: linear-gradient(135deg, #e8a020, #c88010);
    border: none;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: transform 0.18s, box-shadow 0.18s;
}
.feedback-submit-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(232,160,32,0.35); }
.alert-success {
    background: rgba(26,140,78,0.08);
    border: 1px solid rgba(26,140,78,0.25);
    color: #1a7a42;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 0.84rem;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.empty-history { padding: 60px 24px; text-align: center; color: var(--ink-soft); }
.empty-history svg { margin-bottom: 14px; opacity: 0.3; }
.empty-history p { font-size: 0.9rem; margin: 0 0 8px; }
@media (max-width: 700px) {
    .history-stats { grid-template-columns: repeat(2, 1fr); }
}
</style>
</head>
<body class="student-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_student.php'; ?>

<div class="history-wrap">
    <span class="section-eyebrow">My Account</span>
    <h2 class="section-title">Sit-In History</h2>

    <?php if ($msg === 'feedback_sent'): ?>
    <div class="alert-success">&#10003; Your feedback has been submitted. Thank you!</div>
    <?php endif; ?>

    <div class="history-stats">
        <div class="history-stat">
            <div class="history-stat-num"><?= $total ?></div>
            <div class="history-stat-label">Total Sessions</div>
        </div>
        <div class="history-stat">
            <div class="history-stat-num" style="color:#1a7a42;"><?= $completed ?></div>
            <div class="history-stat-label">Completed</div>
        </div>
        <div class="history-stat">
            <div class="history-stat-num" style="color:#a06010;"><?= $pending ?></div>
            <div class="history-stat-label">Pending</div>
        </div>
        <div class="history-stat">
            <div class="history-stat-num" style="color:#c0392b;"><?= $cancelled ?></div>
            <div class="history-stat-label">Cancelled</div>
        </div>
    </div>

    <div class="history-card">
        <div class="history-card-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="12 8 12 12 14 14"/>
                    <path d="M3.05 11a9 9 0 1 1 .5 4"/>
                    <polyline points="3 21 3 16 8 16"/>
                </svg>
                All Sit-In Records
            </div>
            <div class="history-filter">
                <select id="statusFilter" onchange="filterHistory()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        <?php if (empty($logs)): ?>
        <div class="empty-history">
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="12 8 12 12 14 14"/>
                <path d="M3.05 11a9 9 0 1 1 .5 4"/>
            </svg>
            <p>No sit-in records yet.</p>
        </div>
        <?php else: ?>
        <table class="history-table" id="historyTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Lab Room</th>
                    <th>PC</th>
                    <th>Purpose</th>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Status</th>
                    <th>Feedback</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $i => $log):
                $date_in  = new DateTime($log['date_in']);
                $date_out = $log['date_out'] ? new DateTime($log['date_out']) : null;
                $has_feedback = !empty($log['feedback']);
            ?>
            <tr data-status="<?= $log['status'] ?>">
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($log['lab_room']) ?></strong></td>
                <td><?= $log['pc_number'] ? 'PC #'.htmlspecialchars($log['pc_number']) : '<span style="color:#aaa;">—</span>' ?></td>
                <td><?= htmlspecialchars($log['purpose']) ?></td>
                <td><?= $date_in->format('M d, Y') ?></td>
                <td><?= $date_in->format('h:i A') ?></td>
                <td><?= $date_out ? $date_out->format('h:i A') : '<span style="color:#aaa;font-style:italic;">—</span>' ?></td>
                <td><span class="badge badge-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></span></td>
                <td>
                    <?php if ($log['status'] === 'completed'): ?>
                        <?php if ($has_feedback): ?>
                            <button class="feedback-btn done" onclick="viewFeedback(<?= htmlspecialchars(json_encode($log['feedback'])) ?>)">
                                &#10003; Submitted
                            </button>
                        <?php else: ?>
                            <button class="feedback-btn" onclick="openFeedback(<?= $log['id'] ?>, <?= htmlspecialchars(json_encode($log['lab_room'])) ?>, <?= htmlspecialchars(json_encode($log['purpose'])) ?>, <?= htmlspecialchars(json_encode($date_in->format('M d, Y h:i A'))) ?>)">
                                &#9998; Feedback
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#ccc;font-size:0.75rem;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback Submit Modal -->
<div class="feedback-modal-overlay" id="feedbackModal">
    <div class="feedback-modal">
        <div class="feedback-modal-header">
            <div class="feedback-modal-title">&#9998; Session Feedback</div>
            <button class="feedback-modal-close" onclick="closeFeedback()">&#10005;</button>
        </div>
        <form method="POST" action="History.php">
            <input type="hidden" name="feedback_log_id" id="feedback_log_id"/>
            <div class="feedback-modal-body">
                <div class="feedback-session-info" id="feedback_session_info"></div>
                <label style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--blue-deep);display:block;margin-bottom:8px;">Your Feedback</label>
                <textarea class="feedback-textarea" name="feedback_text" placeholder="Share your experience about this sit-in session..." required></textarea>
            </div>
            <div class="feedback-modal-footer">
                <button type="button" class="feedback-cancel-btn" onclick="closeFeedback()">Cancel</button>
                <button type="submit" class="feedback-submit-btn">Submit Feedback</button>
            </div>
        </form>
    </div>
</div>

<!-- View Submitted Feedback Modal -->
<div class="feedback-modal-overlay" id="viewFeedbackModal">
    <div class="feedback-modal">
        <div class="feedback-modal-header">
            <div class="feedback-modal-title">&#10003; Submitted Feedback</div>
            <button class="feedback-modal-close" onclick="document.getElementById('viewFeedbackModal').classList.remove('open')">&#10005;</button>
        </div>
        <div class="feedback-modal-body">
            <div id="viewFeedbackText" style="font-size:0.9rem;color:var(--ink);line-height:1.6;white-space:pre-wrap;"></div>
        </div>
        <div class="feedback-modal-footer">
            <button type="button" class="feedback-cancel-btn" onclick="document.getElementById('viewFeedbackModal').classList.remove('open')">Close</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<script>
function filterHistory() {
    const filter = document.getElementById('statusFilter').value;
    document.querySelectorAll('#historyTable tbody tr').forEach(row => {
        row.style.display = !filter || row.dataset.status === filter ? '' : 'none';
    });
}

function openFeedback(logId, lab, purpose, date) {
    document.getElementById('feedback_log_id').value = logId;
    document.getElementById('feedback_session_info').innerHTML =
        `<strong>Lab:</strong> ${lab} &bull; <strong>Purpose:</strong> ${purpose} &bull; <strong>Date:</strong> ${date}`;
    document.getElementById('feedbackModal').classList.add('open');
}

function closeFeedback() {
    document.getElementById('feedbackModal').classList.remove('open');
}

function viewFeedback(text) {
    document.getElementById('viewFeedbackText').textContent = text;
    document.getElementById('viewFeedbackModal').classList.add('open');
}

document.getElementById('feedbackModal').addEventListener('click', function(e) {
    if (e.target === this) closeFeedback();
});
document.getElementById('viewFeedbackModal').addEventListener('click', function(e) {
    if (e.target === this) document.getElementById('viewFeedbackModal').classList.remove('open');
});
</script>
</body>
</html>