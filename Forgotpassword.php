<?php
session_start();
require_once 'db.php';

if (!empty($_SESSION['student'])) { header('Location: Students.php'); exit; }

$step    = $_GET['step'] ?? 'request'; // request → verify → reset
$error   = '';
$success = '';

$db = get_db();

// Step 1: Find account by email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!$email) {
        $error = 'Please enter your UC email address.';
    } else {
        $stmt = $db->prepare("SELECT * FROM students WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $student = $stmt->fetch();
        if (!$student) {
            $error = 'No account found with that email address.';
        } else {
            // Generate a 6-digit code and store in session
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['reset_code']       = $code;
            $_SESSION['reset_email']      = $email;
            $_SESSION['reset_student_id'] = $student['id'];
            $_SESSION['reset_expires']    = time() + 600; // 10 min

            // In a real app you'd email this. We'll show it on screen for demo.
            $success = $code;
            header('Location: ForgotPassword.php?step=verify');
            exit;
        }
    }
}

// Step 2: Verify code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'verify') {
    $code = trim($_POST['code'] ?? '');
    if (!$code) {
        $error = 'Please enter the verification code.';
    } elseif (!isset($_SESSION['reset_code']) || time() > ($_SESSION['reset_expires'] ?? 0)) {
        $error = 'Your code has expired. Please start over.';
        $step  = 'request';
    } elseif ($code !== $_SESSION['reset_code']) {
        $error = 'Incorrect code. Please try again.';
    } else {
        $_SESSION['reset_verified'] = true;
        header('Location: ForgotPassword.php?step=reset');
        exit;
    }
}

// Step 3: Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    if (empty($_SESSION['reset_verified'])) { header('Location: ForgotPassword.php'); exit; }

    $new_pass = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$new_pass) {
        $error = 'Please enter a new password.';
    } elseif (strlen($new_pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db->prepare("UPDATE students SET password = ? WHERE id = ?")
           ->execute([password_hash($new_pass, PASSWORD_DEFAULT), $_SESSION['reset_student_id']]);

        unset($_SESSION['reset_code'], $_SESSION['reset_email'], $_SESSION['reset_student_id'], $_SESSION['reset_expires'], $_SESSION['reset_verified']);
        header('Location: Login.php?msg=password_reset');
        exit;
    }
}

$nav_active = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Forgot Password</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <style>
    .fp-wrap {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 24px;
    }

    .fp-card {
      background: rgba(255,255,255,0.88);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 20px;
      box-shadow: 0 16px 48px rgba(10,77,140,0.12);
      width: 100%;
      max-width: 440px;
      overflow: hidden;
    }

    .fp-header {
      background: linear-gradient(135deg, #0a4d8c, #1877c9);
      padding: 32px;
      text-align: center;
    }

    .fp-header-icon {
      width: 56px;
      height: 56px;
      background: rgba(255,255,255,0.15);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 14px;
    }

    .fp-header h2 {
      font-family: 'DM Serif Display', serif;
      font-size: 1.3rem;
      color: white;
      margin: 0 0 6px;
    }

    .fp-header p {
      font-size: 0.82rem;
      color: rgba(255,255,255,0.7);
      margin: 0;
    }

    .fp-steps {
      display: flex;
      justify-content: center;
      gap: 8px;
      padding: 16px 32px 0;
    }

    .fp-step {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.72rem;
      color: var(--ink-soft);
    }

    .fp-step-dot {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #e0e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.68rem;
      font-weight: 700;
      color: var(--ink-soft);
      transition: all 0.3s;
    }

    .fp-step.active .fp-step-dot {
      background: var(--blue-deep);
      color: white;
    }

    .fp-step.done .fp-step-dot {
      background: #27ae60;
      color: white;
    }

    .fp-step-line {
      width: 32px;
      height: 2px;
      background: #e0e8f0;
      border-radius: 2px;
    }

    .fp-body { padding: 28px 32px 32px; }

    .fp-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 16px;
    }

    .fp-field label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--blue-deep);
    }

    .fp-field input {
      padding: 12px 14px;
      background: #f5f8fb;
      border: 1.5px solid #ccdeed;
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .fp-field input:focus {
      border-color: #1877c9;
      background: white;
      box-shadow: 0 0 0 3px rgba(24,119,201,0.1);
    }

    .fp-btn {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #0a4d8c, #1877c9);
      border: none;
      border-radius: 10px;
      color: white;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.18s, box-shadow 0.18s;
      margin-top: 8px;
    }

    .fp-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(10,77,140,0.28);
    }

    .fp-error {
      background: rgba(208,49,45,0.08);
      border: 1px solid rgba(208,49,45,0.2);
      color: #c0392b;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 0.83rem;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .fp-code-box {
      background: linear-gradient(135deg, rgba(10,77,140,0.05), rgba(24,119,201,0.08));
      border: 1.5px dashed rgba(10,77,140,0.2);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      margin-bottom: 20px;
    }

    .fp-code-box p { font-size: 0.78rem; color: var(--ink-soft); margin: 0 0 10px; }

    .fp-code-value {
      font-family: 'DM Serif Display', serif;
      font-size: 2.2rem;
      letter-spacing: 8px;
      color: var(--blue-deep);
    }

    .fp-code-note {
      font-size: 0.7rem;
      color: var(--ink-soft);
      margin-top: 8px;
    }

    .fp-back {
      display: block;
      text-align: center;
      margin-top: 16px;
      font-size: 0.82rem;
      color: var(--ink-soft);
      text-decoration: none;
    }

    .fp-back:hover { color: var(--blue-deep); }

    .pw-wrap-inner {
      position: relative;
    }

    .pw-wrap-inner input {
      width: 100%;
      padding-right: 44px;
    }

    .pw-wrap-inner button {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--ink-soft);
      display: flex;
      align-items: center;
    }

    .strength-bars {
      display: flex;
      gap: 4px;
      margin-top: 8px;
    }

    .sbar {
      flex: 1;
      height: 4px;
      border-radius: 4px;
      background: #e0e8f0;
      transition: background 0.3s;
    }

    .strength-text {
      font-size: 0.7rem;
      font-weight: 600;
      margin-top: 4px;
    }
  </style>
</head>
<body class="auth" style="display:flex;flex-direction:column;min-height:100vh;">

<?php include __DIR__ . '/nav_landing.php'; ?>

<div class="fp-wrap">
  <div class="fp-card">

    <div class="fp-header">
      <div class="fp-header-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <h2>Forgot Password</h2>
      <p>
        <?php if ($step === 'request'): ?>Recover access to your CCS account
        <?php elseif ($step === 'verify'): ?>Enter the verification code
        <?php else: ?>Create a new password<?php endif; ?>
      </p>
    </div>

    <!-- Step indicators -->
    <div class="fp-steps">
      <div class="fp-step <?= $step === 'request' ? 'active' : 'done' ?>">
        <div class="fp-step-dot"><?= $step === 'request' ? '1' : '✓' ?></div>
        <span>Email</span>
      </div>
      <div class="fp-step-line"></div>
      <div class="fp-step <?= $step === 'verify' ? 'active' : ($step === 'reset' ? 'done' : '') ?>">
        <div class="fp-step-dot"><?= $step === 'reset' ? '✓' : '2' ?></div>
        <span>Verify</span>
      </div>
      <div class="fp-step-line"></div>
      <div class="fp-step <?= $step === 'reset' ? 'active' : '' ?>">
        <div class="fp-step-dot">3</div>
        <span>Reset</span>
      </div>
    </div>

    <div class="fp-body">

      <?php if ($error): ?>
        <div class="fp-error">&#10005; <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($step === 'request'): ?>
        <form method="POST" action="ForgotPassword.php?step=request">
          <div class="fp-field">
            <label>UC Email Address</label>
            <input type="email" name="email" placeholder="you@uc.edu.ph" autofocus
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
          </div>
          <button type="submit" class="fp-btn">Send Verification Code</button>
        </form>

      <?php elseif ($step === 'verify'): ?>
        <?php
          // Show the code on screen (demo mode — in production you'd email it)
          $demo_code = $_SESSION['reset_code'] ?? '';
          $reset_email = $_SESSION['reset_email'] ?? '';
        ?>
        <div class="fp-code-box">
          <p>Your verification code for <strong><?= htmlspecialchars($reset_email) ?></strong></p>
          <div class="fp-code-value"><?= htmlspecialchars($demo_code) ?></div>
          <div class="fp-code-note">This code expires in 10 minutes.</div>
        </div>
        <form method="POST" action="ForgotPassword.php?step=verify">
          <div class="fp-field">
            <label>Enter Verification Code</label>
            <input type="text" name="code" placeholder="000000" maxlength="6"
              style="text-align:center;font-size:1.4rem;letter-spacing:8px;font-family:'DM Serif Display',serif;"
              autofocus/>
          </div>
          <button type="submit" class="fp-btn">Verify Code</button>
        </form>

      <?php elseif ($step === 'reset'): ?>
        <?php if (empty($_SESSION['reset_verified'])): ?>
          <script>window.location='ForgotPassword.php';</script>
        <?php else: ?>
        <form method="POST" action="ForgotPassword.php?step=reset">
          <div class="fp-field">
            <label>New Password</label>
            <div class="pw-wrap-inner">
              <input type="password" id="fp_pw" name="new_password"
                placeholder="Min. 8 characters" oninput="fpStrength(this.value)"/>
              <button type="button" onclick="fpToggle('fp_pw', this)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="strength-bars" id="fp-bars" style="display:none;">
              <div class="sbar" id="fsb1"></div>
              <div class="sbar" id="fsb2"></div>
              <div class="sbar" id="fsb3"></div>
              <div class="sbar" id="fsb4"></div>
            </div>
            <span class="strength-text" id="fp-strength-label"></span>
          </div>
          <div class="fp-field">
            <label>Confirm New Password</label>
            <div class="pw-wrap-inner">
              <input type="password" id="fp_cpw" name="confirm_password"
                placeholder="Repeat password" oninput="fpCheckMatch()"/>
              <button type="button" onclick="fpToggle('fp_cpw', this)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="fp-btn">Reset Password</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>

      <a href="Login.php" class="fp-back">&larr; Back to Sign In</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<script>
function fpToggle(id, btn) {
  const input = document.getElementById(id);
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.innerHTML = isHidden
    ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
    : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function fpStrength(val) {
  const bars   = [document.getElementById('fsb1'), document.getElementById('fsb2'), document.getElementById('fsb3'), document.getElementById('fsb4')];
  const barsWrap = document.getElementById('fp-bars');
  const label  = document.getElementById('fp-strength-label');
  if (!val) { barsWrap.style.display = 'none'; label.textContent = ''; return; }
  barsWrap.style.display = 'flex';
  let score = 0;
  if (val.length >= 8)          score++;
  if (/[A-Z]/.test(val))        score++;
  if (/[0-9]/.test(val))        score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const configs = [
    { color:'#e74c3c', text:'Weak' },
    { color:'#e8a020', text:'Fair' },
    { color:'#3498db', text:'Good' },
    { color:'#27ae60', text:'Strong' },
  ];
  bars.forEach((b, i) => b.style.background = i < score ? configs[score-1].color : '#e0e8f0');
  label.textContent = configs[score-1]?.text || '';
  label.style.color = configs[score-1]?.color || '';
}

function fpCheckMatch() {
  const pw  = document.getElementById('fp_pw').value;
  const cpw = document.getElementById('fp_cpw');
  cpw.style.borderColor = cpw.value ? (cpw.value === pw ? '#27ae60' : '#e74c3c') : '';
}
</script>
</body>
</html>