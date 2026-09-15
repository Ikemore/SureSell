<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['update_profile'])) {
        $businessName = clean_str($_POST['business_name'] ?? '');
        $bio          = clean_str($_POST['bio'] ?? '');
        $town         = clean_str($_POST['town'] ?? '');
        $region       = clean_str($_POST['region'] ?? '');

        if (mb_strlen($town) < 2) $errors[] = 'Please enter a valid town.';
        if (!valid_ghana_region($region)) $errors[] = 'Please choose a valid region.';
        if (mb_strlen($bio) > 500) $errors[] = 'Bio must be under 500 characters.';

        $avatarPath = null;
        if (!empty($_FILES['avatar']['name'])) {
            $uploadError = null;
            $avatarPath = handle_image_upload($_FILES['avatar'], 'avatars', $uploadError);
            if ($uploadError) $errors[] = $uploadError;
        }

        if (!$errors) {
            if ($avatarPath) {
                db()->prepare('UPDATE users SET business_name=?, bio=?, town=?, region=?, avatar_path=? WHERE id=?')
                    ->execute([$businessName ?: null, $bio ?: null, $town, $region, $avatarPath, $user['id']]);
            } else {
                db()->prepare('UPDATE users SET business_name=?, bio=?, town=?, region=? WHERE id=?')
                    ->execute([$businessName ?: null, $bio ?: null, $town, $region, $user['id']]);
            }
            flash_set('success', 'Profile updated.');
            redirect('account-settings.php');
        }
    }

    if (isset($_POST['change_password'])) {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_new_password'] ?? '');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (!valid_password($new)) {
            $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters with a letter and a number.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash_set('success', 'Password changed successfully.');
            redirect('account-settings.php');
        }
    }
}

$pageTitle = 'Account Settings';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card wide">
    <h1>Account settings</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <h3>Profile</h3>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field"><label>Business name (optional)</label>
        <input type="text" name="business_name" value="<?= e($user['business_name'] ?? '') ?>" maxlength="120"></div>
      <div class="field"><label>Town</label>
        <input type="text" name="town" value="<?= e($user['town']) ?>" required></div>
      <div class="field"><label for="region">Region</label>
        <select id="region" name="region" required>
          <option value="">Choose your region</option>
          <?php foreach (ghana_regions() as $ghanaRegion): ?>
            <option value="<?= e($ghanaRegion) ?>" <?= ($user['region'] ?? '') === $ghanaRegion ? 'selected' : '' ?>><?= e($ghanaRegion) ?> Region</option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label>Bio (optional)</label>
        <textarea name="bio" maxlength="500"><?= e($user['bio'] ?? '') ?></textarea></div>
      <div class="field"><label>Profile photo</label>
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></div>
      <button type="submit" name="update_profile" value="1" class="btn btn-primary">Save profile</button>
    </form>

    <hr style="margin:32px 0;border:none;border-top:1px solid var(--line);">

    <h3>Change password</h3>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label>Current password</label><input type="password" name="current_password" required></div>
      <div class="field"><label>New password</label><input type="password" name="new_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
      <div class="field"><label>Confirm new password</label><input type="password" name="confirm_new_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
      <button type="submit" name="change_password" value="1" class="btn btn-outline">Change password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
