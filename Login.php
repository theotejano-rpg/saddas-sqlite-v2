<?php
session_start();
require_once 'db.php';

$nav_active = 'login';

if (!empty($_SESSION['student'])) { header('Location: Students.php'); exit; }
if (!empty($_SESSION['admin']))   { header('Location: admin/admin.php');    exit; }

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $role       = $_POST['role']       ?? 'student';
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password']   ?? '';
    $old        = ['identifier' => $identifier, 'role' => $role];

    if ($identifier === '') $errors['identifier'] = 'Please enter your ' . ($role === 'admin' ? 'Email' : 'Student ID or Email') . '.';
    if ($password   === '') $errors['password']   = 'Please enter your password.';

    if (empty($errors)) {
        $db = get_db();

        if ($role === 'admin') {
            $stmt = $db->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
            $stmt->execute([strtolower($identifier)]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin'] = [
                    'id'         => $admin['id'],
                    'first_name' => $admin['first_name'],
                    'last_name'  => $admin['last_name'],
                    'email'      => $admin['email'],
                ];
                header('Location: admin/admin.php');
                exit;
            } else {
                $errors['auth'] = 'Incorrect email or password.';
            }

        } else {
            $stmt = $db->prepare('SELECT * FROM students WHERE student_id = ? OR email = ? LIMIT 1');
            $stmt->execute([$identifier, strtolower($identifier)]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {
                $_SESSION['student'] = [
                    'id'          => $student['id'],
                    'first_name'  => $student['first_name'],
                    'middle_name' => $student['middle_name'],
                    'last_name'   => $student['last_name'],
                    'student_id'  => $student['student_id'],
                    'course'      => $student['course'],
                    'course_code' => $student['course_code'],
                    'level'       => $student['level'],
                    'email'       => $student['email'],
                    'address'     => $student['address'],
                    'sessions'    => $student['sessions'],
                    'used'        => $student['used'],
                ];
                header('Location: Students.php');
                exit;
            } else {
                $errors['auth'] = 'Incorrect Student ID / Email or password. '
                    . 'If you don\'t have an account yet, <a href="Register.php">register here</a>.';
            }
        }
    }
}

$role_selected = $old['role'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Sign In</title>
  <script>
    (function(){ try { if(localStorage.getItem('saddas_theme')==='dark') document.documentElement.classList.add('dark-theme'); } catch(e){} })();
  </script>
  <link rel="stylesheet" href="css/Style.css"/>
  <style>
    html.dark-theme body.auth { background: #071026 !important; }
    html.dark-theme .site-nav { background: #0f1724 !important; border-bottom: 1px solid rgba(255,255,255,0.05) !important; backdrop-filter: none !important; }
    html.dark-theme .nav-left-text strong { color: #ffffff !important; }
    html.dark-theme .nav-left-text small  { color: #9aa5b4 !important; }
    html.dark-theme .nav-link    { color: #ffffff !important; }
    html.dark-theme .nav-btn.ghost { color: #ffffff !important; border-color: rgba(255,255,255,0.2) !important; background: transparent !important; }
    html.dark-theme .nav-btn.solid { background: #6b21c8 !important; color: #ffffff !important; }
    html.dark-theme .auth-card {
      background: #0f1724 !important;
      border-color: rgba(255,255,255,0.06) !important;
      box-shadow: 0 20px 60px rgba(2,6,23,0.7) !important;
    }
    html.dark-theme .auth-left  { background: #0f1724 !important; }
    html.dark-theme .auth-right { background: #0c1520 !important; }
    html.dark-theme .auth-tagline  { color: #9aa5b4 !important; }
    html.dark-theme .auth-title    { color: #ffffff !important; }
    html.dark-theme .auth-sub      { color: #9aa5b4 !important; }
    html.dark-theme .auth-back     { color: #9aa5b4 !important; }
    html.dark-theme .auth-switch   { color: #9aa5b4 !important; }
    html.dark-theme .auth-switch a { color: #93c5fd !important; }
    html.dark-theme .auth-forgot a { color: #93c5fd !important; }
    html.dark-theme .auth-right-name       { color: #ffffff !important; }
    html.dark-theme .auth-right-name small { color: #9aa5b4 !important; }
    html.dark-theme .auth-right-motto      { color: #c084fc !important; }
    html.dark-theme .auth-right-rule       { background: rgba(255,255,255,0.08) !important; }
    html.dark-theme .role-toggle { background: rgba(255,255,255,0.04) !important; }
    html.dark-theme .role-btn { color: #9aa5b4 !important; }
    html.dark-theme .role-btn.active { background: rgba(255,255,255,0.08) !important; color: #ffffff !important; box-shadow: none !important; }
    html.dark-theme .field label { color: #9aa5b4 !important; }
    html.dark-theme .field input {
      background: rgba(255,255,255,0.04) !important;
      border-color: rgba(255,255,255,0.08) !important;
      color: #ffffff !important;
    }
    html.dark-theme .field input::placeholder { color: rgba(255,255,255,0.25) !important; }
    html.dark-theme .field input:focus { border-color: rgba(74,163,255,0.4) !important; box-shadow: 0 0 0 3px rgba(74,163,255,0.08) !important; }
    html.dark-theme .pw-toggle { background: transparent !important; color: #9aa5b4 !important; }
    html.dark-theme .auth-btn { background: #1877c9 !important; color: #ffffff !important; }
    html.dark-theme footer { background: #0f1724 !important; color: #9aa5b4 !important; border-top-color: rgba(255,255,255,0.04) !important; }
    /* Landing theme toggle button */    .landing-theme-toggle {
      display: flex; align-items: center; justify-content: center;
      width: 34px; height: 34px; border-radius: 8px;
      background: transparent; border: none;
      cursor: pointer; color: var(--ink-soft); margin-left: 8px; flex-shrink: 0;
      padding: 6px 10px;
      transition: background 0.18s, color 0.18s;
    }
    .landing-theme-toggle:hover { background: rgba(10,77,140,0.08); color: var(--blue-deep); }
    .landing-theme-toggle svg { display: block; }
    html.dark-theme .landing-theme-toggle { background: transparent !important; border: none !important; color: #ffffff !important; }
    html.dark-theme .landing-theme-toggle:hover { background: rgba(255,255,255,0.08) !important; color: #fff !important; }
  </style>
  <style>
    .role-toggle {
      display: flex;
      background: rgba(10,77,140,0.06);
      border-radius: 10px;
      padding: 4px;
      gap: 4px;
      margin-bottom: 22px;
      width: 100%;
    }
    .role-btn {
      flex: 1;
      padding: 9px 12px;
      border: none;
      border-radius: 8px;
      background: transparent;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--ink-soft);
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
    }
    .role-btn.active {
      background: white;
      color: var(--blue-deep);
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(10,77,140,0.12);
    }
    .role-btn:hover:not(.active) {
      background: rgba(255,255,255,0.5);
      color: var(--ink);
    }
  </style>
</head>
<body class="auth" style="display:flex;flex-direction:column;min-height:100vh;">

<?php include __DIR__ . '/nav_landing.php'; ?>

  <div class="auth-wrap" style="flex:1;">
    <div class="auth-card">
      <div class="auth-left">
        <img src="images/uclogo-removebg-preview-removebg-preview.png" alt="University of Cebu" class="auth-uc-logo"/>
        <p class="auth-tagline">Student Portal</p>
        <h2 class="auth-title">Welcome back.</h2>
        <p class="auth-sub">Sign in to your CCS account.</p>

        <div class="role-toggle">
          <button type="button" class="role-btn <?= $role_selected === 'student' ? 'active' : '' ?>" onclick="setRole('student', this)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            Student
          </button>
          <button type="button" class="role-btn <?= $role_selected === 'admin' ? 'active' : '' ?>" onclick="setRole('admin', this)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Admin
          </button>
        </div>

        <?php if (!empty($errors['auth'])): ?>
          <div class="alert alert--error">
            <span class="alert-icon">&#10005;</span>
            <span><?= $errors['auth'] ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" action="Login.php" novalidate>
          <input type="hidden" name="role" id="role-input" value="<?= htmlspecialchars($role_selected) ?>"/>

          <div class="field<?= isset($errors['identifier']) ? ' field--error' : '' ?>">
            <label for="identifier" id="identifier-label">
              <?= $role_selected === 'admin' ? 'Admin Email' : 'Student ID or Email' ?>
            </label>
            <input type="text" id="identifier" name="identifier"
              placeholder="<?= $role_selected === 'admin' ? 'admin@uc.edu.ph' : 'UC-00001 or you@uc.edu.ph' ?>"
              value="<?= htmlspecialchars($old['identifier'] ?? '') ?>"
              autocomplete="username"/>
            <?php if (isset($errors['identifier'])): ?>
              <span class="field-msg"><?= htmlspecialchars($errors['identifier']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($errors['password']) ? ' field--error' : '' ?>">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input type="password" id="password" name="password"
                placeholder="Enter your password" autocomplete="current-password"/>
              <button type="button" class="pw-toggle" onclick="togglePw('password',this)">&#128065;</button>
            </div>
            <?php if (isset($errors['password'])): ?>
              <span class="field-msg"><?= htmlspecialchars($errors['password']) ?></span>
            <?php endif; ?>
          </div>

          <div class="auth-forgot"><a href="ForgotPassword.php">Forgot password?</a></div>
          <button class="auth-btn" type="submit">Sign In</button>
        </form>

        <p class="auth-switch" id="register-link" style="<?= $role_selected === 'admin' ? 'display:none' : '' ?>">
          No account yet? <a href="Register.php">Register here</a>
        </p>
        <a href="Landing.php" class="auth-back">&larr; Back to homepage</a>
      </div>

      <div class="auth-right">
        <img src="images/csmainlogo-removebg-preview-removebg-preview.png" alt="CCS" class="auth-ccs-logo"/>
        <div class="auth-right-name">
          College of Computer Studies
          <small>University of Cebu</small>
        </div>
        <div class="auth-right-rule"></div>
        <p class="auth-right-motto">Be Focused Be Devoted</p>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/footer.php'; ?>

<script>
function togglePw(fieldId, btn) {
  const input = document.getElementById(fieldId);
  if (input.type === 'password') { input.type = 'text';     btn.textContent = '\uD83D\uDE48'; }
  else                           { input.type = 'password'; btn.textContent = '\uD83D\uDC41'; }
}

function setRole(role, btn) {
  document.getElementById('role-input').value = role;
  document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const label   = document.getElementById('identifier-label');
  const input   = document.getElementById('identifier');
  const regLink = document.getElementById('register-link');
  if (role === 'admin') {
    label.textContent     = 'Admin Email';
    input.placeholder     = 'admin@uc.edu.ph';
    regLink.style.display = 'none';
  } else {
    label.textContent     = 'Student ID or Email';
    input.placeholder     = 'UC-00001 or you@uc.edu.ph';
    regLink.style.display = '';
  }
}
</script>

</body>
</html>