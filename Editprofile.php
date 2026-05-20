<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['student'])) { header('Location: Login.php'); exit; }

$db   = get_db();
$stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['student']['id']]);
$student = $stmt->fetch();

if (!$student) { session_destroy(); header('Location: Login.php'); exit; }

$errors  = [];
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

    $first_name  = trim($_POST['first_name']  ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $level       = trim($_POST['level']       ?? '');
    $course      = trim($_POST['course']      ?? '');
    $address     = trim($_POST['address']     ?? '');
    $new_pass    = $_POST['new_password']     ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if ($first_name === '') $errors['first_name'] = 'First name is required.';
    if ($last_name  === '') $errors['last_name']  = 'Last name is required.';
    if ($address    === '') $errors['address']    = 'Address is required.';
    if ($level      === '') $errors['level']      = 'Please select your year level.';
    if ($course     === '') $errors['course']     = 'Please select your course.';

    if ($new_pass !== '' && strlen($new_pass) < 8)
        $errors['new_password'] = 'Password must be at least 8 characters.';
    if ($new_pass !== '' && $new_pass !== $confirm)
        $errors['confirm_password'] = 'Passwords do not match.';

    // Handle profile picture upload
    $profile_pic = $student['profile_pic'] ?? null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK && $_FILES['profile_pic']['size'] > 0) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        $mime    = mime_content_type($_FILES['profile_pic']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $errors['profile_pic'] = 'Only JPG, PNG, GIF or WEBP images are allowed.';
        } elseif ($_FILES['profile_pic']['size'] > 2 * 1024 * 1024) {
            $errors['profile_pic'] = 'Image must be under 2MB.';
        } else {
            $ext      = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $student['id'] . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $filename);
            $profile_pic = 'uploads/profiles/' . $filename;
        }
    }

    if (empty($errors)) {
        // Resolve course name and code
        if (array_key_exists($course, $course_options)) {
            $course_name = $course_options[$course];
            $course_code = $course;
        } else {
            $course_name = $course;
            $course_code = array_search($course, $course_options) ?: $student['course_code'];
        }

        if ($new_pass !== '') {
            $db->prepare("UPDATE students SET first_name=?,middle_name=?,last_name=?,level=?,course=?,course_code=?,address=?,password=?,profile_pic=? WHERE id=?")
               ->execute([$first_name,$middle_name,$last_name,$level,$course_name,$course_code,$address,password_hash($new_pass,PASSWORD_DEFAULT),$profile_pic,$student['id']]);
        } else {
            $db->prepare("UPDATE students SET first_name=?,middle_name=?,last_name=?,level=?,course=?,course_code=?,address=?,profile_pic=? WHERE id=?")
               ->execute([$first_name,$middle_name,$last_name,$level,$course_name,$course_code,$address,$profile_pic,$student['id']]);
        }

        // Refresh session
        $stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
        $stmt->execute([$student['id']]);
        $student = $stmt->fetch();
        $_SESSION['student'] = array_merge($_SESSION['student'], [
            'first_name'  => $student['first_name'],
            'middle_name' => $student['middle_name'],
            'last_name'   => $student['last_name'],
            'course'      => $student['course'],
            'course_code' => $student['course_code'],
            'level'       => $student['level'],
            'address'     => $student['address'],
        ]);

        $success = 'Profile updated successfully!';
    }
}

$nav_student_active = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Edit Profile</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <link rel="stylesheet" href="css/Students.css"/>
  <style>
    .edit-profile-wrap {
      max-width: 860px;
      margin: 32px auto 0;
      padding: 0 28px 60px;
      flex: 1;
    }

    .ep-card {
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(10,77,140,0.08);
      overflow: hidden;
    }

    .ep-header {
      background: linear-gradient(135deg, var(--blue-deep), #1877c9);
      padding: 32px 32px 80px;
      position: relative;
    }

    .ep-header-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.3rem;
      color: white;
    }

    .ep-header-sub {
      font-size: 0.82rem;
      color: rgba(255,255,255,0.7);
      margin-top: 4px;
    }

    .ep-avatar-wrap {
      position: absolute;
      bottom: -48px;
      left: 32px;
      display: flex;
      align-items: flex-end;
      gap: 16px;
    }

    .ep-avatar {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      border: 4px solid white;
      background: linear-gradient(135deg, var(--blue-deep), #1877c9);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
      position: relative;
      cursor: pointer;
    }

    .ep-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .ep-avatar-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.4);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.2s;
    }

    .ep-avatar:hover .ep-avatar-overlay { opacity: 1; }

    .ep-avatar-label {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.85);
      font-weight: 500;
      margin-bottom: 8px;
    }

    .ep-body {
      padding: 64px 32px 32px;
    }

    .ep-section-title {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: var(--blue-mid);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .ep-section-title::before {
      content: '';
      width: 20px;
      height: 2px;
      background: var(--blue-mid);
      border-radius: 2px;
    }

    .ep-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 24px;
    }

    .ep-grid-full { grid-column: 1 / -1; }

    .ep-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .ep-field label {
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--blue-deep);
    }

    .ep-field input,
    .ep-field select {
      padding: 11px 14px;
      background: #f5f8fb;
      border: 1.5px solid var(--rule);
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      color: var(--ink);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .ep-field input:focus,
    .ep-field select:focus {
      border-color: var(--blue-mid);
      background: white;
      box-shadow: 0 0 0 3px rgba(24,119,201,0.1);
    }

    .ep-field.error input,
    .ep-field.error select { border-color: #d0312d; background: #fdf0f0; }

    .ep-field-msg {
      font-size: 0.72rem;
      color: #d0312d;
      font-weight: 500;
    }

    .ep-divider {
      height: 1px;
      background: var(--rule);
      margin: 24px 0;
    }

    .ep-submit-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 13px 32px;
      background: linear-gradient(135deg, var(--blue-deep), #1877c9);
      border: none;
      border-radius: 10px;
      color: white;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.18s, box-shadow 0.18s;
    }

    .ep-submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(10,77,140,0.28);
    }

    .ep-alert-success {
      background: rgba(26,140,78,0.08);
      border: 1px solid rgba(26,140,78,0.25);
      color: #1a7a42;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.85rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .ep-alert-error {
      background: rgba(208,49,45,0.08);
      border: 1px solid rgba(208,49,45,0.2);
      color: #c0392b;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.85rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .ep-readonly {
      padding: 11px 14px;
      background: rgba(10,77,140,0.04);
      border: 1.5px solid rgba(10,77,140,0.1);
      border-radius: 10px;
      font-size: 0.9rem;
      color: var(--ink-soft);
      font-family: 'DM Sans', sans-serif;
    }
  </style>
</head>
<body class="student-page" style="display:flex;flex-direction:column;min-height:100vh;">

<?php include __DIR__ . '/nav_student.php'; ?>

<div class="edit-profile-wrap">

  <div class="ep-card">
    <div class="ep-header">
      <div class="ep-header-title">Edit Profile</div>
      <div class="ep-header-sub">Update your personal information and profile picture</div>

      <div class="ep-avatar-wrap">
        <label for="profile_pic_input" class="ep-avatar" title="Click to change photo">
          <?php if (!empty($student['profile_pic']) && file_exists(__DIR__ . '/' . $student['profile_pic'])): ?>
            <img src="<?= htmlspecialchars($student['profile_pic']) ?>" alt="Profile"/>
          <?php else: ?>
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          <?php endif; ?>
          <div class="ep-avatar-overlay">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
          </div>
        </label>
        <div class="ep-avatar-label">Click photo to change</div>
      </div>
    </div>

    <form method="POST" action="EditProfile.php" enctype="multipart/form-data">
      <input type="file" id="profile_pic_input" name="profile_pic" accept="image/*" style="display:none" onchange="previewImage(this)"/>

      <div class="ep-body">

        <?php if ($success): ?>
          <div class="ep-alert-success">&#10003; <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="ep-alert-error">&#10005; <?= implode(', ', array_keys($errors)) ?> — <?= implode(' | ', $errors) ?></div>
        <?php endif; ?>

        <div class="ep-section-title">Personal Information</div>

        <div class="ep-grid">
          <div class="ep-field <?= isset($errors['first_name']) ? 'error' : '' ?>">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($student['first_name']) ?>" placeholder="Juan"/>
            <?php if (isset($errors['first_name'])): ?><span class="ep-field-msg"><?= htmlspecialchars($errors['first_name']) ?></span><?php endif; ?>
          </div>
          <div class="ep-field">
            <label>Middle Name <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input type="text" name="middle_name" value="<?= htmlspecialchars($student['middle_name']) ?>" placeholder="Santos"/>
          </div>
          <div class="ep-field ep-grid-full <?= isset($errors['last_name']) ? 'error' : '' ?>">
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($student['last_name']) ?>" placeholder="dela Cruz"/>
            <?php if (isset($errors['last_name'])): ?><span class="ep-field-msg"><?= htmlspecialchars($errors['last_name']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="ep-section-title">Academic Information</div>

        <div class="ep-grid">
          <div class="ep-field <?= isset($errors['course']) ? 'error' : '' ?>">
            <label>Course</label>
            <select name="course">
              <option value="">-- Select course --</option>
              <?php foreach ($course_options as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($student['course_code'] === $val || $student['course'] === $label) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['course'])): ?><span class="ep-field-msg"><?= htmlspecialchars($errors['course']) ?></span><?php endif; ?>
          </div>
          <div class="ep-field <?= isset($errors['level']) ? 'error' : '' ?>">
            <label>Year Level</label>
            <select name="level">
              <option value="">-- Select level --</option>
              <?php foreach ($level_options as $opt): ?>
                <option value="<?= $opt ?>" <?= $student['level'] === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['level'])): ?><span class="ep-field-msg"><?= htmlspecialchars($errors['level']) ?></span><?php endif; ?>
          </div>
          <div class="ep-field ep-grid-full">
            <label>Student ID</label>
            <div class="ep-readonly"><?= htmlspecialchars($student['student_id']) ?></div>
          </div>
          <div class="ep-field ep-grid-full">
            <label>Email Address</label>
            <div class="ep-readonly"><?= htmlspecialchars($student['email']) ?></div>
          </div>
          <div class="ep-field ep-grid-full <?= isset($errors['address']) ? 'error' : '' ?>">
            <label>Address</label>
            <input type="text" name="address" value="<?= htmlspecialchars($student['address']) ?>" placeholder="Barangay, City, Province"/>
            <?php if (isset($errors['address'])): ?><span class="ep-field-msg"><?= htmlspecialchars($errors['address']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="ep-divider"></div>
        <div class="ep-section-title">Change Password <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:0.75rem;">(leave blank to keep current)</span></div>

        <div class="ep-grid">
          <div class="ep-field <?= isset($errors['new_password']) ? 'error' : '' ?>">
            <label>New Password</label>
            <div style="position:relative;">
              <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters" style="width:100%;padding-right:40px;"/>
              <button type="button" onclick="togglePw('new_password',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;">&#128065;</button>
            </div>
            <?php if (isset($errors['new_password'])): ?><span class="ep-field-msg"><?= htmlspecialchars($errors['new_password']) ?></span><?php endif; ?>
          </div>
          <div class="ep-field <?= isset($errors['confirm_password']) ? 'error' : '' ?>">
            <label>Confirm New Password</label>
            <div style="position:relative;">
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" style="width:100%;padding-right:40px;"/>
              <button type="button" onclick="togglePw('confirm_password',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;">&#128065;</button>
            </div>
            <?php if (isset($errors['confirm_password'])): ?><span class="ep-field-msg"><?= htmlspecialchars($errors['confirm_password']) ?></span><?php endif; ?>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:8px;">
          <a href="Students.php" style="padding:13px 24px;background:transparent;border:1.5px solid var(--rule);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:500;color:var(--ink-soft);text-decoration:none;transition:all 0.2s;">Cancel</a>
          <button type="submit" class="ep-submit-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
              <polyline points="17 21 17 13 7 13 7 21"/>
              <polyline points="7 3 7 8 15 8"/>
            </svg>
            Save Changes
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<script>
function togglePw(id, btn) {
  const input = document.getElementById(id);
  if (input.type === 'password') { input.type = 'text'; btn.textContent = '\uD83D\uDE48'; }
  else { input.type = 'password'; btn.textContent = '\uD83D\uDC41'; }
}

function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const avatar = document.querySelector('.ep-avatar');
      avatar.innerHTML = `
        <img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"/>
        <div class="ep-avatar-overlay">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </div>`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

const confirmInput = document.getElementById('confirm_password');
const newInput     = document.getElementById('new_password');
confirmInput.addEventListener('input', () => {
  if (!confirmInput.value) { confirmInput.style.borderColor = ''; return; }
  confirmInput.style.borderColor = confirmInput.value === newInput.value ? '#27ae60' : '#e74c3c';
});
</script>
</body>
</html>