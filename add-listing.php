<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

if (!is_fully_verified($user)) {
  flash_set('error', 'Verify your email address and phone number before you can sell on SureSell. Ghana Card verification is optional.');
    redirect('dashboard.php');
}

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];
$old = ['title' => '', 'description' => '', 'price' => '', 'town' => $user['town'], 'region' => $user['region'] ?? '', 'category_id' => ''];
$oldNegotiable = false;

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
    $oldNegotiable      = (bool) $negotiable;

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
<div class="wrap sell-wrap">
  <div class="sell-heading"><div><span class="sell-eyebrow">SELL ON SURESELL</span><h1>Give your item a great first impression.</h1><p>A few clear details help nearby buyers find the right item and feel confident reaching out.</p></div><div class="sell-progress" aria-label="Listing steps"><span class="sell-progress-active"><b>1</b> Item details</span><i></i><span><b>2</b> Photos &amp; location</span></div></div>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

   <div class="sell-layout"><section class="sell-form-card">
    <form method="post" enctype="multipart/form-data" novalidate id="sellForm">
      <?= csrf_field() ?>
      <div class="sell-section-head"><span>01</span><div><h2>What are you selling?</h2><p>Be specific so buyers know exactly what to expect.</p></div></div>
      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" maxlength="140" placeholder="e.g. Samsung Galaxy A54, 128GB" data-preview-title required>
        <div class="hint">Include the brand, model, or a detail buyers will search for.</div>
      </div>
      <div class="field">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" data-preview-category required>
          <option value="">Choose a category</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $old['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="description">Description <span id="descCounter" class="sell-counter">0 / 1500</span></label>
        <textarea id="description" name="description" maxlength="1500" placeholder="Share the condition, age, included accessories, and anything else a buyer should know." required><?= e($old['description']) ?></textarea>
      </div>
      <div class="sell-section-head sell-section-spaced"><span>02</span><div><h2>Price &amp; location</h2><p>Help local buyers understand the price and where to find you.</p></div></div>
      <div class="listing-location-fields">
        <div class="field">
          <label for="price">Price (GH₵)</label>
          <input type="number" id="price" name="price" step="0.01" min="0.01" max="10000000" value="<?= e($old['price']) ?>" placeholder="0.00" data-preview-price required>
        </div>
        <div class="field">
          <label for="town">Town / Location</label>
          <input type="text" id="town" name="town" value="<?= e($old['town']) ?>" placeholder="e.g. Osu" data-preview-town required>
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
        <label class="sell-check-label"><input type="checkbox" name="negotiable" value="1" <?= $oldNegotiable ? 'checked' : '' ?> data-preview-negotiable> <span><strong>Price is negotiable</strong><small>Let buyers know you are open to offers.</small></span></label>
      </div>
      <div class="sell-section-head sell-section-spaced"><span>03</span><div><h2>Add photos</h2><p>Listings with bright, clear photos are easier to trust.</p></div></div>
      <div class="field">
        <label class="sell-upload" for="images"><span class="sell-upload-icon" aria-hidden="true">＋</span><strong>Choose photos from your device</strong><span>JPG, PNG or WEBP · Up to 5 photos · 4MB each</span><input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        <div class="hint">We remove hidden location data from your photos for your privacy.</div>
        <div class="sell-photo-grid" id="sellPhotoPreview" aria-live="polite"></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block sell-submit">Publish my listing <span aria-hidden="true">→</span></button><p class="sell-submit-note">Your listing goes live as soon as it is published. You can edit it later.</p>
    </form>
   </section><aside class="sell-preview-card" aria-label="Listing preview"><div class="sell-preview-top"><span class="sell-preview-label">LIVE PREVIEW</span><span class="sell-preview-dot"></span></div><div class="sell-preview-image" id="sellPreviewImage"><span>PHOTO PREVIEW</span></div><div class="sell-preview-copy"><span class="sell-preview-category" id="sellPreviewCategory">Your category</span><h3 id="sellPreviewTitle">Your item title</h3><strong id="sellPreviewPrice">GH₵ —</strong><p id="sellPreviewTown">Your location</p><div class="sell-preview-trust"><span>✓</span> Posted by a verified trader</div></div><div class="sell-tip"><strong>Trust tip</strong><p>Show your item in natural light and mention any wear honestly. Buyers appreciate the details.</p></div></aside></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
