<?php
require_once 'includes/functions.php';
requireLogin();
if (isset($_GET['dark'])) { $_SESSION['dark_mode'] = ($_GET['dark']==='1'); header('Location: profile.php'); exit; }

$pdo = getDB();
$u   = currentUser();

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'update_profile') {
        $name  = post('name');
        $email = post('email');
        if (!$name || !$email) { setFlash('Name and email are required.','error'); }
        else {
            // Check email not taken by another user
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $dup->execute([$email, $u['id']]);
            if ($dup->fetch()) { setFlash('Email already in use by another account.','error'); }
            else {
                $avatar = strtoupper(substr($name,0,1));
                $pdo->prepare("UPDATE users SET name=?,email=?,avatar=? WHERE id=?")
                    ->execute([$name,$email,$avatar,$u['id']]);
                $_SESSION['user_name']   = $name;
                $_SESSION['user_email']  = $email;
                $_SESSION['user_avatar'] = $avatar;
                setFlash('Profile updated successfully!');
            }
        }
    } elseif ($action === 'change_password') {
        $current = post('current_password');
        $new     = post('new_password');
        $confirm = post('confirm_password');

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$u['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash) && $current !== $hash) {
            setFlash('Current password is incorrect.','error');
        } elseif (strlen($new) < 6) {
            setFlash('New password must be at least 6 characters.','error');
        } elseif ($new !== $confirm) {
            setFlash('New passwords do not match.','error');
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            setFlash('Password changed successfully!');
        }
    } elseif ($action === 'update_settings') {
        $_SESSION['dark_mode'] = post('dark_mode') === '1';
        setFlash('Settings saved!');
    }

    header('Location: profile.php');
    exit;
}

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$u['id']]);
$userData = $stmt->fetch();

// System stats for admin
$systemStats = [];
if ($u['role'] === 'Admin') {
    $systemStats = [
        'customers' => (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
        'readings'  => (int)$pdo->query("SELECT COUNT(*) FROM readings")->fetchColumn(),
        'bills'     => (int)$pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn(),
        'payments'  => (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
        'users'     => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    ];
}

require_once 'includes/header.php';
renderHeader('My Profile', 'profile');
?>

<div style="max-width:860px;margin:0 auto">
  <!-- Profile Header Card -->
  <div class="card mb-3" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
    <div style="width:72px;height:72px;border-radius:50%;background:var(--accent);color:#fff;
                display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:900;flex-shrink:0">
      <?= h($userData['avatar']) ?>
    </div>
    <div style="flex:1">
      <h2 style="margin:0;font-size:22px;color:var(--accent)"><?= h($userData['name']) ?></h2>
      <div style="color:var(--muted);font-size:13px;margin-top:4px"><?= h($userData['email']) ?></div>
      <div style="margin-top:8px">
        <span class="badge badge-<?= strtolower(h($userData['role'])) ?>"><?= h($userData['role']) ?></span>
        <span style="font-size:11px;color:var(--muted);margin-left:10px">
          Member since <?= date('F Y', strtotime($userData['created_at'])) ?>
        </span>
      </div>
    </div>
    <?php if ($u['role'] === 'Admin' && !empty($systemStats)): ?>
    <div style="display:flex;gap:20px;flex-wrap:wrap">
      <?php foreach ($systemStats as $label => $val): ?>
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:900;color:var(--accent)"><?= $val ?></div>
        <div style="font-size:10px;color:var(--muted);text-transform:capitalize"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="two-col">
    <!-- Update Profile -->
    <div class="card">
      <div class="card-title">✏️ Update Profile</div>
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" value="<?= h($userData['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" value="<?= h($userData['email']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <input type="text" class="form-control" value="<?= h($userData['role']) ?>" disabled style="opacity:.6">
          <div class="form-hint">Role can only be changed by an Admin.</div>
        </div>
        <button type="submit" class="btn btn-primary">Save Profile</button>
      </form>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-title">🔑 Change Password</div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label class="form-label">Current Password *</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">New Password * <span class="fs-xs text-muted">(min 6 chars)</span></label>
          <input type="password" name="new_password" class="form-control" required minlength="6" id="new_pw">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password *</label>
          <input type="password" name="confirm_password" class="form-control" required id="confirm_pw"
                 oninput="checkPwMatch()">
          <div class="form-hint" id="pw-match-hint"></div>
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
      </form>
    </div>
  </div>

  <!-- Preferences -->
  <div class="card mt-2">
    <div class="card-title">⚙️ Preferences</div>
    <form method="POST" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
      <input type="hidden" name="action" value="update_settings">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;font-size:14px">
        <input type="checkbox" name="dark_mode" value="1" <?= isDark()?'checked':'' ?>
               onchange="this.form.submit()" style="width:18px;height:18px;cursor:pointer">
        🌙 Dark Mode
      </label>
      <span class="text-muted fs-sm">Theme preference is saved per session.</span>
    </form>
  </div>

  <?php if ($u['role'] === 'Admin'): ?>
  <!-- System Info -->
  <div class="card mt-2">
    <div class="card-title">🛠️ System Information</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
      <?php
      $info = [
        'PHP Version'    => PHP_VERSION,
        'Server'         => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Database'       => 'MySQL / MariaDB',
        'App Version'    => 'AquaBill v1.0',
        'Timezone'       => date_default_timezone_get(),
        'Current Time'   => date('Y-m-d H:i:s'),
      ];
      foreach ($info as $k => $v): ?>
      <div style="background:var(--surface-alt);border-radius:8px;padding:10px 14px">
        <div class="fs-xs text-muted"><?= h($k) ?></div>
        <div class="fw-bold fs-sm" style="margin-top:3px;word-break:break-all"><?= h($v) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function checkPwMatch() {
  const a = document.getElementById('new_pw').value;
  const b = document.getElementById('confirm_pw').value;
  const h = document.getElementById('pw-match-hint');
  if (!b) { h.textContent = ''; return; }
  if (a === b) { h.textContent = '✅ Passwords match'; h.style.color = 'var(--success)'; }
  else         { h.textContent = '❌ Passwords do not match'; h.style.color = 'var(--danger)'; }
}
</script>

<?php renderFooter(); ?>
