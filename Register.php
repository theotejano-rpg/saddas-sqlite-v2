<?php
session_start();
require_once 'db.php';

$nav_active = 'register';

$errors  = [];
$old     = [];
$success = '';

$course_options = [
    'BSIT'  => 'BS Information Technology',
    'BSCS'  => 'BS Computer Science',
    'BSIS'  => 'BS Information Systems',
    'BSECE' => 'BS Electronics Engineering',
    'ACT'   => 'Associate in Computer Technology',
];
$level_options = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name       = trim($_POST['first_name']       ?? '');
    $middle_name      = trim($_POST['middle_name']      ?? '');
    $last_name        = trim($_POST['last_name']        ?? '');
    $student_id       = strtoupper(trim($_POST['student_id'] ?? ''));
    $course           = trim($_POST['course']           ?? '');
    $level            = trim($_POST['level']            ?? '');
    $email            = strtolower(trim($_POST['email'] ?? ''));
    $address          = trim($_POST['address']          ?? '');
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';

    $old = compact('first_name','middle_name','last_name','student_id','course','level','email','address');

    if ($first_name  === '') $errors['first_name']  = 'First name is required.';
    if ($last_name   === '') $errors['last_name']   = 'Last name is required.';
    if ($student_id  === '') $errors['student_id']  = 'Student ID is required.';
    if ($course      === '') $errors['course']      = 'Please select your course.';
    if ($level       === '') $errors['level']       = 'Please select your year level.';
    if ($email       === '') $errors['email']       = 'UC Email address is required.';
    if ($address     === '') $errors['address']     = 'Address is required.';
    if ($password    === '') $errors['password']    = 'Please create a password.';
    if ($confirm_password === '') $errors['confirm_password'] = 'Please confirm your password.';

    if (!isset($errors['student_id']) && !preg_match('/^UC-\d{5}$/', $student_id))
        $errors['student_id'] = 'Format must be UC-00001 (e.g. UC-00123).';

    if (!isset($errors['course']) && !array_key_exists($course, $course_options))
        $errors['course'] = 'Please select a valid course.';
    if (!isset($errors['level']) && !in_array($level, $level_options, true))
        $errors['level'] = 'Please select a valid year level.';
    if (!isset($errors['email']) && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Please enter a valid email address.';
    if (!isset($errors['email']) && !str_ends_with($email, '@uc.edu.ph'))
        $errors['email'] = 'Please use your UC email (e.g. you@uc.edu.ph).';
    if (!isset($errors['password']) && strlen($password) < 8)
        $errors['password'] = 'Password must be at least 8 characters.';
    if (!isset($errors['confirm_password']) && !isset($errors['password']) && $password !== $confirm_password)
        $errors['confirm_password'] = 'Passwords do not match. Please try again.';

    if (empty($errors)) {
        $db  = get_db();
        $chk = $db->prepare('SELECT student_id, email FROM students WHERE student_id = ? OR email = ? LIMIT 1');
        $chk->execute([$student_id, $email]);
        $existing = $chk->fetch();
        if ($existing) {
            if ($existing['student_id'] === $student_id) $errors['student_id'] = 'This Student ID is already registered.';
            if ($existing['email']      === $email)      $errors['email']       = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        $db = get_db();
        $db->prepare("
            INSERT INTO students
                (first_name,middle_name,last_name,student_id,course,course_code,level,email,address,password)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $first_name, $middle_name, $last_name, $student_id,
            $course_options[$course], $course, $level, $email,
            $address, password_hash($password, PASSWORD_DEFAULT),
        ]);
        $success = "Account created for {$first_name} {$last_name}! You can now <a href=\"Login.php\">sign in</a>.";
        $old = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Register</title>
  <link rel="stylesheet" href="css/Style.css"/>
</head>
<body class="auth" style="display:flex;flex-direction:column;min-height:100vh;">

<?php include __DIR__ . '/nav_landing.php'; ?>

<?php if (!empty($success)): ?>
  <div class="toast toast--success" id="toast">
    <span class="toast-icon">&
    <span><?= $success ?></span>
  </div>
<?php endif; ?>

  <div class="auth-wrap" style="flex:1;">
    <div class="auth-card">
      <div class="auth-left">
        <img src="images/uclogo-removebg-preview-removebg-preview.png" alt="University of Cebu" class="auth-uc-logo"/>
        <p class="auth-tagline">Student Portal</p>
        <h2 class="auth-title">Create an account.</h2>
        <p class="auth-sub">Fill in your details to get started with UC CCS.</p>

        <?php if (!empty($errors)): ?>
          <div class="alert alert--error">
            <span class="alert-icon">&#10005;</span>
            <span>Please fix the highlighted fields before continuing.</span>
          </div>
        <?php endif; ?>

        <form method="POST" action="Register.php" novalidate>

          <div class="field-row">
            <div class="field<?= isset($errors['first_name']) ? ' field--error' : '' ?>">
              <label for="first_name">First Name</label>
              <input type="text" id="first_name" name="first_name" placeholder="Juan"
                value="<?= htmlspecialchars($old['first_name'] ?? '') ?>" autocomplete="given-name"/>
              <?php if (isset($errors['first_name'])): ?>
                <span class="field-msg"><?= htmlspecialchars($errors['first_name']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field">
              <label for="middle_name">Middle Name <span class="field-optional">(optional)</span></label>
              <input type="text" id="middle_name" name="middle_name" placeholder="Santos"
                value="<?= htmlspecialchars($old['middle_name'] ?? '') ?>" autocomplete="additional-name"/>
            </div>
          </div>

          <div class="field<?= isset($errors['last_name']) ? ' field--error' : '' ?>">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" placeholder="dela Cruz"
              value="<?= htmlspecialchars($old['last_name'] ?? '') ?>" autocomplete="family-name"/>
            <?php if (isset($errors['last_name'])): ?>
              <span class="field-msg"><?= htmlspecialchars($errors['last_name']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($errors['student_id']) ? ' field--error' : '' ?>">
            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id" placeholder="UC-00001"
              value="<?= htmlspecialchars($old['student_id'] ?? '') ?>"
              oninput="formatStudentId(this)" maxlength="8"/>
            <?php if (isset($errors['student_id'])): ?>
              <span class="field-msg"><?= htmlspecialchars($errors['student_id']) ?></span>
            <?php else: ?>
              <span class="field-msg" style="color:#4a6278;">Format: UC-00001 (your school-issued ID number)</span>
            <?php endif; ?>
          </div>



          <div class="field-row">
            <div class="field<?= isset($errors['course']) ? ' field--error' : '' ?>">
              <label for="course">Course</label>
              <select id="course" name="course">
                <option value="">-- Select course --</option>
                <?php foreach ($course_options as $val => $label): ?>
                  <option value="<?= $val ?>"<?= ($old['course'] ?? '') === $val ? ' selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['course'])): ?>
                <span class="field-msg"><?= htmlspecialchars($errors['course']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field<?= isset($errors['level']) ? ' field--error' : '' ?>">
              <label for="level">Year Level</label>
              <select id="level" name="level">
                <option value="">-- Select level --</option>
                <?php foreach ($level_options as $opt): ?>
                  <option value="<?= $opt ?>"<?= ($old['level'] ?? '') === $opt ? ' selected' : '' ?>>
                    <?= htmlspecialchars($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['level'])): ?>
                <span class="field-msg"><?= htmlspecialchars($errors['level']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="field<?= isset($errors['email']) ? ' field--error' : '' ?>">
            <label for="email">UC Email Address</label>
            <input type="email" id="email" name="email" placeholder="you@uc.edu.ph"
              value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email"/>
            <?php if (isset($errors['email'])): ?>
              <span class="field-msg"><?= htmlspecialchars($errors['email']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($errors['address']) ? ' field--error' : '' ?>">
            <label for="address">Address</label>
            <input type="text" id="address" name="address" placeholder="Barangay, City, Province"
              value="<?= htmlspecialchars($old['address'] ?? '') ?>" autocomplete="street-address"/>
            <?php if (isset($errors['address'])): ?>
              <span class="field-msg"><?= htmlspecialchars($errors['address']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field-row">
            <div class="field<?= isset($errors['password']) ? ' field--error' : '' ?>">
              <label for="password">Password</label>
              <div class="input-wrap">
                <input type="password" id="password" name="password"
                  placeholder="Min. 8 characters" autocomplete="new-password"
                  oninput="checkStrength(this.value)"/>
                <button type="button" class="pw-toggle" onclick="togglePw('password',this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <?php if (isset($errors['password'])): ?>
                <span class="field-msg"><?= htmlspecialchars($errors['password']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field<?= isset($errors['confirm_password']) ? ' field--error' : '' ?>">
              <label for="confirm_password">Confirm Password</label>
              <div class="input-wrap">
                <input type="password" id="confirm_password" name="confirm_password"
                  placeholder="Repeat password" autocomplete="new-password"
                  oninput="checkMatch()"/>
                <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <?php if (isset($errors['confirm_password'])): ?>
                <span class="field-msg"><?= htmlspecialchars($errors['confirm_password']) ?></span>
              <?php endif; ?>
              <span class="field-msg" id="match-msg" style="display:none;color:#e74c3c;">Passwords do not match.</span>
            </div>
          </div>

          <!-- Password strength meter -->
          <div id="pw-strength-wrap" style="margin-top:-8px;margin-bottom:12px;display:none;">
            <div style="display:flex;gap:4px;margin-bottom:4px;">
              <div class="strength-bar" id="sb1"></div>
              <div class="strength-bar" id="sb2"></div>
              <div class="strength-bar" id="sb3"></div>
              <div class="strength-bar" id="sb4"></div>
            </div>
            <span id="strength-label" style="font-size:0.72rem;font-weight:600;"></span>
          </div>

          <button class="auth-btn" type="submit">Create Account</button>
        </form>

        <p class="auth-switch">Already registered? <a href="Login.php">Sign in here</a></p>
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

<style>
.strength-bar {
  flex: 1;
  height: 5px;
  border-radius: 4px;
  background: #e0e8f0;
  transition: background 0.3s;
}
</style>

<script>
function formatStudentId(input) {
  let val = input.value.replace(/[^0-9UCuc-]/g, '').toUpperCase();
  if (val.length >= 2 && !val.startsWith('UC')) val = 'UC' + val.replace('UC','');
  if (val.length > 2 && val[2] !== '-') val = val.slice(0,2) + '-' + val.slice(2);
  input.value = val.slice(0, 8);
}

function togglePw(fieldId, btn) {
  const input = document.getElementById(fieldId);
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.innerHTML = isHidden
    ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
    : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function checkStrength(val) {
  const wrap = document.getElementById('pw-strength-wrap');
  const bars = [document.getElementById('sb1'), document.getElementById('sb2'), document.getElementById('sb3'), document.getElementById('sb4')];
  const label = document.getElementById('strength-label');

  if (!val) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'block';

  let score = 0;
  if (val.length >= 8)          score++;
  if (/[A-Z]/.test(val))        score++;
  if (/[0-9]/.test(val))        score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const configs = [
    { color: '#e74c3c', text: 'Weak',      textColor: '#e74c3c' },
    { color: '#e8a020', text: 'Fair',      textColor: '#e8a020' },
    { color: '#3498db', text: 'Good',      textColor: '#3498db' },
    { color: '#27ae60', text: 'Strong',    textColor: '#27ae60' },
  ];

  bars.forEach((bar, i) => {
    bar.style.background = i < score ? configs[score - 1].color : '#e0e8f0';
  });

  label.textContent  = configs[score - 1]?.text || '';
  label.style.color  = configs[score - 1]?.textColor || '';
  checkMatch();
}

function checkMatch() {
  const pw  = document.getElementById('password').value;
  const cpw = document.getElementById('confirm_password').value;
  const msg = document.getElementById('match-msg');
  const confirmField = document.getElementById('confirm_password');
  if (!cpw) { msg.style.display = 'none'; confirmField.style.borderColor = ''; return; }
  if (pw === cpw) {
    msg.style.display = 'none';
    confirmField.style.borderColor = '#27ae60';
  } else {
    msg.style.display = 'block';
    confirmField.style.borderColor = '#e74c3c';
  }
}
</script>
</body>
</html>