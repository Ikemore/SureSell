<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

if (!is_fully_verified($user)) {
  flash_set('error', 'You must verify your email and phone, then receive admin approval for your ID before you can sell on SureSell.');
    redirect('dashboard.php');
}

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];
$old = ['title' => '', 'description' => '', 'price' => '', 'town' => $user['town'], 'region' => $user['region'] ?? '', 'category_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (too_many_recent_actions($user['id'], 'post_listing', 8, 60)) {
        $errors[] = 'You are posting too quickly. Please wait a while before adding another listing.';
    }

    $old['title']       = clean_str($_POST['title'] ?? '');
    $old['description'] = clean_str($_POST['description'] ?? '');
    $old['price']       = clean_str($_POST['price'] ?? '');
    $old['town']        = clean_str($_POST['town'] ?? '');
    $old['region']      = clean_str($_POST['region'] ?? '');
    $old['category_id'] = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $negotiable         = !empty($_POST['negotiable']) ? 1 : 0;

    if (mb_strlen($old['title']) < 5 || mb_strlen($old['title']) > 140) $errors[] = 'Title should be 5–140 characters.';
    if (mb_strlen($old['description']) < 20 || mb_strlen($old['description']) > 1500) $errors[] = 'Description should be 20–1500 characters.';
    if (!is_numeric($old['price']) || (float)$old['price'] <= 0 || (float)$old['price'] > 10000000) $errors[] = 'Please enter a valid price.';
    if (mb_strlen($old['town']) < 2) $errors[] = 'Please enter a town/location.';
    if (!valid_ghana_region($old['region'])) $errors[] = 'Please choose the listing region.';
    if (!$old['category_id']) $errors[] = 'Please choose a category.';
    if ($old['category_id']) {
      $categoryCheck = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
      $categoryCheck->execute([$old['category_id']]);
      if (!$categoryCheck->fetchColumn()) $errors[] = 'Please choose a valid category.';
    }

    $imagePaths = [];
    if (!$errors && !empty($_FILES['images']['name'][0])) {
        $count = count($_FILES['images']['name']);
        if ($count > 5) {
            $errors[] = 'You can upload up to 5 photos.';
        } else {
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $file = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i],
                ];
                $uploadError = null;
                $path = handle_image_upload($file, 'listings', $uploadError);
                if ($uploadError) {
                    $errors[] = $uploadError;
                    break;
                }
                if ($path) $imagePaths[] = $path;
            }
        }
    }

    if (!$errors) {
      try {
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO listings (user_id, category_id, title, description, price, negotiable, town, region, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active")');
        $stmt->execute([$user['id'], $old['category_id'], $old['title'], $old['description'], $old['price'], $negotiable, $old['town'], $old['region']]);
        $listingId = (int) $pdo->lastInsertId();

        foreach ($imagePaths as $i => $path) {
          $pdo->prepare('INSERT INTO listing_images (listing_id, image_path, sort_order) VALUES (?, ?, ?)')
            ->execute([$listingId, $path, $i]);
        }
        $pdo->commit();

        flash_set('success', 'Your listing is live!');
        redirect('listing.php?id=' . $listingId);
      } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('Listing creation failed: ' . $e->getMessage());
        $errors[] = 'We could not publish this listing. Please check the details and try again.';
      }
    }
}

$pageTitle = 'Sell an Item';
$__page = 'add-listing';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card wide">
    <h1>List something for sale</h1>
    <p style="color:#666;margin-bottom:20px;">Clear photos and an honest description build trust faster than anything else.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" maxlength="140" required>
      </div>
      <div class="field">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
          <option value="">Choose a category</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $old['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="description">Description <span id="descCounter" style="font-weight:400;color:#999;"></span></label>
        <textarea id="description" name="description" maxlength="1500" required><?= e($old['description']) ?></textarea>
      </div>
      <div class="listing-location-fields">
        <div class="field">
          <label for="price">Price (GH₵)</label>
          <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?= e($old['price']) ?>" required>
        </div>
        <div class="field">
          <label for="town">Town / Location</label>
          <input type="text" id="town" name="town" value="<?= e($old['town']) ?>" required>
        </div>
        <div class="field">
          <label for="region">Region</label>
          <select id="region" name="region" required>
            <option value="">Choose a region</option>
            <?php foreach (ghana_regions() as $region): ?>
              <option value="<?= e($region) ?>" <?= $old['region'] === $region ? 'selected' : '' ?>><?= e($region) ?> Region</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
          <input type="checkbox" name="negotiable" style="width:auto;"> Price is negotiable
        </label>
      </div>
      <div class="field">
        <label for="images">Photos (up to 5)</label>
        <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
        <div class="hint">JPG, PNG or WEBP. Max 4MB each. Location data is automatically stripped for your safety.</div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Publish listing</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
