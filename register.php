<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'town' => '', 'region' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['full_name'] = clean_str($_POST['full_name'] ?? '');
    $old['email']     = clean_str($_POST['email'] ?? '');
    $old['phone']     = clean_str($_POST['phone'] ?? '');
    $old['town']      = clean_str($_POST['town'] ?? '');
    $old['region']    = clean_str($_POST['region'] ?? '');
    $password         = (string)($_POST['password'] ?? '');
    $confirm          = (string)($_POST['confirm_password'] ?? '');
    $contactConfirmed = !empty($_POST['contact_confirm']);

    if (mb_strlen($old['full_name']) < 2)               $errors[] = 'Please enter your full name.';
    if (!valid_email($old['email']))                    $errors[] = 'Please enter a valid email address.';
    if (!valid_phone($old['phone']))                     $errors[] = 'Please enter a valid Ghanaian phone number, e.g. 024xxxxxxx.';
    if (mb_strlen($old['town']) < 2)                     $errors[] = 'Please tell us your town/district.';
    if (!valid_ghana_region($old['region']))              $errors[] = 'Please choose your region.';
    if (!valid_password($password))                      $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters and include a letter and a number.';
    if ($password !== $confirm)                          $errors[] = 'Passwords do not match.';
    if (!$contactConfirmed)                              $errors[] = 'Please confirm that you can access this email address and phone number.';

    $phoneNorm = normalize_phone($old['phone']);

    if (!$errors) {
        // Check uniqueness (prepared statements — no injection risk)
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $stmt->execute([$old['email'], $phoneNorm]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email or phone number already exists.';
        }
    }

    if (!$errors) {
        $avatarPath = null;
        if (!empty($_FILES['avatar']['name'])) {
            $uploadError = null;
            $avatarPath = handle_image_upload($_FILES['avatar'], 'avatars', $uploadError);
            if ($uploadError) $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password_hash, town, region, avatar_path, role)
                                VALUES (?, ?, ?, ?, ?, ?, ?, "trader")');
        $stmt->execute([$old['full_name'], $old['email'], $phoneNorm, $hash, $old['town'], $old['region'], $avatarPath]);
        $userId = (int) db()->lastInsertId();

        // Generate + store OTPs for phone and email verification.
        $phoneOtp = (string) random_int(100000, 999999);
        $emailOtp = (string) random_int(100000, 999999);
        $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

        $phoneHash = password_hash($phoneOtp, PASSWORD_DEFAULT);
        $emailHash = password_hash($emailOtp, PASSWORD_DEFAULT);

        $insPhone = db()->prepare('INSERT INTO otp_codes (user_id, code_hash, purpose, expires_at) VALUES (?, ?, "phone_verify", ?)');
        $insPhone->execute([$userId, $phoneHash, $expires]);

        $insEmail = db()->prepare('INSERT INTO otp_codes (user_id, code_hash, purpose, expires_at) VALUES (?, ?, "email_verify", ?)');
        $insEmail->execute([$userId, $emailHash, $expires]);

        // In production, send both codes via the appropriate delivery channels.
        // For local/demo use we surface them on-screen.
        $_SESSION['pending_verify_user'] = $userId;
        $_SESSION['pending_verify_email_user'] = $userId;
        $_SESSION['demo_otp_notice'] = $phoneOtp;
        $_SESSION['demo_email_otp_notice'] = $emailOtp;

        regenerate_session();
        redirect('verify-email.php');
    }
}

$pageTitle = 'Join as a Trader';
$__page = 'register';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card wide">
    <h1>Join SureSell</h1>
    <p style="color:#666;margin-bottom:24px;">Every trader here verifies their email and phone number before they can list a single item. That's what makes your listing worth trusting.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="contact-security-notice" role="alert">
      <strong>Use contact details you can access.</strong>
      Your registered email address and phone number are used to confirm password-reset requests. Use your own valid details so you do not lose access to your account.
    </div>
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required maxlength="100">
      </div>
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190">
      </div>
      <div class="field">
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="024xxxxxxx" required>
        <div class="hint">Use a working Ghana number. It is checked again for password recovery.</div>
      </div>
      <div class="field">
        <label for="town">Town / District</label>
        <input type="text" id="town" name="town" value="<?= e($old['town']) ?>" placeholder="e.g. Nkawie" required maxlength="100">
      </div>
      <div class="field">
        <label for="region">Region</label>
        <select id="region" name="region" required>
          <option value="">Choose your region</option>
          <?php foreach (ghana_regions() as $region): ?>
            <option value="<?= e($region) ?>" <?= $old['region'] === $region ? 'selected' : '' ?>><?= e($region) ?> Region</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="avatar">Profile picture (optional)</label>
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
        <div class="hint">JPG, PNG or WEBP, up to 4MB. You can update it later in account settings.</div>
      </div>
      <div class="field contact-confirmation">
        <label><input type="checkbox" name="contact_confirm" value="1" required> I confirm that this email address and phone number belong to me and I can receive account-recovery messages.</label>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
        <div class="hint">At least <?= PASSWORD_MIN_LENGTH ?> characters, with a letter and a number.</div>
      </div>
      <div class="field">
        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create my trader account</button>
    </form>
    <p class="form-foot">Already have an account? <a href="login.php" style="color:var(--indigo);font-weight:700;">Log in</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
