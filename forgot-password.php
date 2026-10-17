<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$email = '';
$phone = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = clean_str($_POST['email'] ?? '');
    $phone = clean_str($_POST['phone'] ?? '');

    if (too_many_recent_actions(0, 'password_reset_request', 5, 60)) {
        $errors[] = 'Too many reset requests. Please wait before trying again.';
    } elseif (!valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!valid_phone($phone)) {
        $errors[] = 'Please enter the Ghana phone number used when creating the account.';
    } else {
        $stmt = db()->prepare('SELECT id, phone FROM users WHERE email = ? AND phone = ? AND status = "active" LIMIT 1');
        $stmt->execute([$email, normalize_phone($phone)]);
        $account = $stmt->fetch();

        // Give the same response whether or not an account exists to prevent account enumeration.
        if ($account) {
            $code = (string) random_int(100000, 999999);
            db()->prepare('UPDATE otp_codes SET used = 1 WHERE user_id = ? AND purpose = "password_reset" AND used = 0')
                ->execute([$account['id']]);
            $useSomame = somame_sms_enabled();
            $providerOtpId = $useSomame
                ? request_somame_otp($account['phone'], 'password_reset', ['userId' => (string) $account['id']])
                : null;
            $deliveryOk = $useSomame
                ? $providerOtpId !== null
                : (APP_ENV !== 'production' && send_sms_code($account['phone'], $code, 'password_reset'));
            if ($deliveryOk) {
                $storedOtp = $providerOtpId !== null ? 'somame:' . $providerOtpId : password_hash($code, PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO otp_codes (user_id, code_hash, purpose, expires_at) VALUES (?, ?, "password_reset", ?)')
                    ->execute([$account['id'], $storedOtp, date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60)]);
                $_SESSION['password_reset_user'] = (int) $account['id'];
            } else {
                error_log('Password-reset SMS could not be delivered for user ' . $account['id']);
            }
        }

        flash_set('success', 'If those details match an active account, a six-digit reset code has been sent to its registered phone.');
        redirect('reset-password.php');
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap"><div class="form-card">
  <h1>Reset your password</h1>
  <p>Enter the email address and Ghana phone number used at registration. We will send a one-time reset code to that registered phone.</p>
  <div class="contact-security-notice" role="alert"><strong>Keep your details current.</strong> Password reset works only when both details match the account you created.</div>
  <?php foreach ($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($email) ?>" required></div>
    <div class="field"><label for="phone">Registered phone number</label><input id="phone" name="phone" type="tel" value="<?= e($phone) ?>" placeholder="024xxxxxxx" required></div>
    <button class="btn btn-primary btn-block" type="submit">Send code to my phone</button>
  </form>
  <p class="form-foot"><a href="login.php">Back to log in</a></p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
