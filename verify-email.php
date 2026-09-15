<?php
require_once __DIR__ . '/includes/functions.php';

$userId = $_SESSION['pending_verify_email_user'] ?? $_SESSION['pending_verify_user'] ?? null;
if (!$userId) {
    redirect('login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (too_many_recent_actions((int)$userId, 'otp_attempt', 6, 10)) {
        $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $code = clean_str($_POST['otp'] ?? '');

        $stmt = db()->prepare('SELECT id, code_hash, expires_at, used, attempts FROM otp_codes
                                WHERE user_id = ? AND purpose = "email_verify"
                                ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $otpRow = $stmt->fetch();

        if (!$otpRow || $otpRow['used']) {
            $errors[] = 'No pending email verification found. Please request a new code.';
        } elseif (strtotime($otpRow['expires_at']) < time()) {
            $errors[] = 'That code has expired. Please request a new one.';
        } elseif ($otpRow['attempts'] >= 5) {
            $errors[] = 'Too many incorrect attempts. Please request a new code.';
        } elseif (!password_verify($code, $otpRow['code_hash'])) {
            db()->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$otpRow['id']]);
            $errors[] = 'That code is incorrect. Please check and try again.';
        } else {
            db()->prepare('UPDATE otp_codes SET used = 1 WHERE id = ?')->execute([$otpRow['id']]);
            db()->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')->execute([$userId]);
            unset($_SESSION['pending_verify_email_user']);
            $_SESSION['user_id'] = $userId;
            regenerate_session();
            flash_set('success', 'Email verified! Please verify your phone number to finish setting up your account.');
            redirect('verify-phone.php');
        }
    }
}

$pageTitle = 'Verify your email';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card">
    <h1>Verify your email</h1>
    <p style="color:#666;">We sent a 6-digit code to your email address. Enter it below to continue with your trader account setup.</p>

    <?php if (!empty($_SESSION['demo_email_otp_notice'])): ?>
      <div class="alert alert-success">
        <strong>Local demo mode:</strong> no mail gateway is wired up yet, so your email code is
        <strong style="font-size:1.1em;letter-spacing:2px;"><?= e($_SESSION['demo_email_otp_notice']) ?></strong>.
      </div>
    <?php endif; ?>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="otp">6-digit code</label>
        <input type="text" id="otp" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Verify my email</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
