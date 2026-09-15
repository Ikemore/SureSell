<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$resetUserId = (int) ($_SESSION['password_reset_user'] ?? 0);
if (!$resetUserId) {
    flash_set('error', 'Request a new password-reset code first.');
    redirect('forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!preg_match('/^\d{6}$/', $code)) $errors[] = 'Enter the six-digit code from your email.';
    if (!valid_password($password)) $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters and include a letter and a number.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id, code_hash FROM otp_codes WHERE user_id = ? AND purpose = "password_reset" AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$resetUserId]);
        $reset = $stmt->fetch();
        if (!$reset || !password_verify($code, $reset['code_hash'])) {
            $errors[] = 'That code is invalid or has expired. Request a new one and try again.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $resetUserId]);
                $pdo->prepare('UPDATE otp_codes SET used = 1 WHERE id = ?')->execute([$reset['id']]);
                $pdo->commit();
                unset($_SESSION['password_reset_user'], $_SESSION['demo_password_reset_code']);
                flash_set('success', 'Password reset. You can now log in with your new password.');
                redirect('login.php');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Password-reset completion failed: ' . $exception->getMessage());
                $errors[] = 'We could not reset your password. Please try again.';
            }
        }
    }
}

$pageTitle = 'Confirm Password Reset';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap"><div class="form-card">
  <h1>Enter your reset code</h1>
  <p>Enter the six-digit code we sent, then choose a new password.</p>
  <?php if (APP_ENV !== 'production' && !empty($_SESSION['demo_password_reset_code'])): ?><div class="alert alert-success">Local development code: <strong><?= e($_SESSION['demo_password_reset_code']) ?></strong></div><?php endif; ?>
  <?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label for="code">Six-digit code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus></div>
    <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required></div>
    <div class="field"><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required></div>
    <button class="btn btn-primary btn-block" type="submit">Reset password</button>
  </form>
  <p class="form-foot"><a href="forgot-password.php">Send a new code</a></p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
