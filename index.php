<?php
require_once 'includes/functions.php';

// Handle dark mode toggle from query string
if (isset($_GET['dark'])) {
    $_SESSION['dark_mode'] = ($_GET['dark'] === '1');
    header('Location: index.php');
    exit;
}

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = post('email');
    $password = post('password');

    if ($email && $password) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Support plain-text demo passwords OR bcrypt
        $valid = $user && (
            password_verify($password, $user['password']) ||
            $password === $user['password']
        );

        if ($valid) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_avatar']= $user['avatar'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$dark = isDark();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <div class="icon">💧</div>
      <h1><?= APP_NAME ?></h1>
      <p><?= APP_TAGLINE ?></p>
    </div>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label">Email Address *</label>
        <input type="email" name="email" class="form-control" placeholder="admin@barangay.gov" required value="<?= h(post('email')) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Password *</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <?php if ($error): ?>
        <div class="danger-box"><?= h($error) ?></div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">Sign In</button>
    </form>

    <div class="login-demo">
      <strong>Demo Accounts:</strong><br>
      👤 admin@barangay.gov / <code>admin123</code> (Admin)<br>
      👤 staff@barangay.gov / <code>staff123</code> (Staff)
    </div>

    <a href="?dark=<?= $dark ? '0' : '1' ?>" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:12px">
      <?= $dark ? '☀️ Light Mode' : '🌙 Dark Mode' ?>
    </a>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
