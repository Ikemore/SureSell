<?php
require_once __DIR__ . '/includes/functions.php';

$userId = $_SESSION['pending_verify_user'] ?? null;
if (!$userId) {
    redirect('login.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (too_many_recent_actions((int)$userId, 'otp_attempt', 6, 10)) {
        $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $code = clean_str($_POST['otp'] ?? '');

        $stmt = db()->prepare('SELECT id, code_hash, expires_at, used, attempts FROM otp_codes
                                WHERE user_id = ? AND purpose = "phone_verify"
                                ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $otpRow = $stmt->fetch();

        if (!$otpRow || $otpRow['used']) {
            $errors[] = 'No pending verification found. Please request a new code.';
        } elseif (strtotime($otpRow['expires_at']) < time()) {
            $errors[] = 'That code has expired. Please request a new one.';
        } elseif ($otpRow['attempts'] >= 5) {
            $errors[] = 'Too many incorrect attempts. Please request a new code.';
        } elseif (!password_verify($code, $otpRow['code_hash'])) {
            db()->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$otpRow['id']]);
            $errors[] = 'That code is incorrect. Please check and try again.';
        } else {
            db()->prepare('UPDATE otp_codes SET used = 1 WHERE id = ?')->execute([$otpRow['id']]);
            db()->prepare('UPDATE users SET phone_verified = 1 WHERE id = ?')->execute([$userId]);
            unset($_SESSION['pending_verify_user']);
            $_SESSION['user_id'] = $userId;
            regenerate_session();
            flash_set('success', 'Phone verified! Your trader account is ready.');
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Verify your phone';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card">
    <h1>Verify your number</h1>
    <p style="color:#666;">We sent a 6-digit code to your phone. Enter it below to activate your account.</p>

    <?php if (!empty($_SESSION['demo_otp_notice'])): ?>
      <div class="alert alert-success">
        <strong>Local demo mode:</strong> no SMS gateway is wired up yet, so your code is
        <strong style="font-size:1.1em;letter-spacing:2px;"><?= e($_SESSION['demo_otp_notice']) ?></strong>.
        See the README to connect a real SMS provider.
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
      <button type="submit" class="btn btn-primary btn-block">Verify my number</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
