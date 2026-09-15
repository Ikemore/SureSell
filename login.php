<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$oldEmail = '';

if (!empty($_GET['suspended'])) {
    $errors[] = 'Your account has been suspended. Contact support if you believe this is a mistake.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $oldEmail = clean_str($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    // Throttle login attempts per browser session regardless of which account is targeted
    if (too_many_recent_actions(0, 'login_ip', 15, 10)) {
        $errors[] = 'Too many login attempts. Please wait a few minutes and try again.';
    } elseif (!valid_email($oldEmail) || $password === '') {
        $errors[] = 'Please enter a valid email and password.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash, status, failed_logins, locked_until FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$oldEmail]);
        $user = $stmt->fetch();

        if (!$user) {
            log_login_attempt(null, $oldEmail, false);
            $errors[] = 'Incorrect email or password.';
        } elseif (is_account_locked($user)) {
            $errors[] = 'This account is temporarily locked due to repeated failed attempts. Try again in a few minutes.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            register_failed_login((int)$user['id']);
            log_login_attempt((int)$user['id'], $oldEmail, false);
            $errors[] = 'Incorrect email or password.';
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'This account is not active. Contact support for help.';
        } else {
            clear_failed_logins((int)$user['id']);
            log_login_attempt((int)$user['id'], $oldEmail, true);
            $_SESSION['user_id'] = (int) $user['id'];
            regenerate_session();
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Log in';
$__page = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card">
    <h1>Welcome back</h1>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($oldEmail) ?>" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="form-foot"><a href="forgot-password.php" style="color:var(--indigo);font-weight:700;">Forgot your password?</a></p>
    <p class="form-foot">New to SureSell? <a href="register.php" style="color:var(--indigo);font-weight:700;">Join as a trader</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
