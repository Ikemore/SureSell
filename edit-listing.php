<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); die('Listing not found.'); }

$stmt = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$listing = $stmt->fetch();

// Ownership check — never trust a listing ID belongs to the current user without verifying
if (!$listing || (int)$listing['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    die('You do not have permission to edit this listing.');
}

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['delete'])) {
        db()->prepare('UPDATE listings SET status = "removed" WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
        flash_set('success', 'Listing removed.');
        redirect('dashboard.php');
    }

    if (isset($_POST['mark_sold'])) {
        db()->prepare('UPDATE listings SET status = "sold" WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
        flash_set('success', 'Marked as sold.');
        redirect('dashboard.php');
    }

    $title       = clean_str($_POST['title'] ?? '');
    $description = clean_str($_POST['description'] ?? '');
    $price       = clean_str($_POST['price'] ?? '');
    $town        = clean_str($_POST['town'] ?? '');
    $categoryId  = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $negotiable  = !empty($_POST['negotiable']) ? 1 : 0;

    if (mb_strlen($title) < 5 || mb_strlen($title) > 140) $errors[] = 'Title should be 5–140 characters.';
    if (mb_strlen($description) < 20 || mb_strlen($description) > 1500) $errors[] = 'Description should be 20–1500 characters.';
    if (!is_numeric($price) || (float)$price <= 0) $errors[] = 'Please enter a valid price.';
    if (mb_strlen($town) < 2) $errors[] = 'Please enter a town/location.';
    if (!$categoryId) $errors[] = 'Please choose a category.';

    if (!$errors) {
        db()->prepare('UPDATE listings SET title=?, description=?, price=?, town=?, category_id=?, negotiable=? WHERE id=? AND user_id=?')
            ->execute([$title, $description, $price, $town, $categoryId, $negotiable, $id, $user['id']]);
        flash_set('success', 'Listing updated.');
        redirect('listing.php?id=' . $id);
    }
    $listing = array_merge($listing, compact('title','description','price','town') + ['category_id'=>$categoryId,'negotiable'=>$negotiable]);
}

$pageTitle = 'Edit Listing';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card wide">
    <h1>Edit listing</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
      <div class="field"><label>Title</label>
        <input type="text" name="title" value="<?= e($listing['title']) ?>" maxlength="140" required></div>
      <div class="field"><label>Category</label>
        <select name="category_id" required>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $listing['category_id']==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label>Description</label>
        <textarea name="description" maxlength="1500" required><?= e($listing['description']) ?></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="field"><label>Price (GH₵)</label><input type="number" step="0.01" name="price" value="<?= e($listing['price']) ?>" required></div>
        <div class="field"><label>Town</label><input type="text" name="town" value="<?= e($listing['town']) ?>" required></div>
      </div>
      <div class="field"><label style="display:flex;align-items:center;gap:8px;font-weight:500;">
        <input type="checkbox" name="negotiable" style="width:auto;" <?= $listing['negotiable']?'checked':'' ?>> Price is negotiable</label></div>
      <button type="submit" class="btn btn-primary btn-block">Save changes</button>
    </form>

    <div style="display:flex;gap:12px;margin-top:20px;">
      <form method="post" style="flex:1;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
        <button type="submit" name="mark_sold" value="1" class="btn btn-outline btn-block">Mark as sold</button></form>
      <form method="post" style="flex:1;" onsubmit="return confirm('Remove this listing permanently from public view?');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
        <button type="submit" name="delete" value="1" class="btn btn-danger btn-block">Remove listing</button></form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
