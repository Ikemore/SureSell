<?php
require_once __DIR__ . '/includes/functions.php';

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

// ---- Read + validate filters (never trusted directly into SQL) ----
$q          = clean_str($_GET['q'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: null;
$town       = clean_str($_GET['town'] ?? '');
$region     = clean_str($_GET['region'] ?? '');
$minPrice   = filter_input(INPUT_GET, 'min', FILTER_VALIDATE_FLOAT) ?: null;
$maxPrice   = filter_input(INPUT_GET, 'max', FILTER_VALIDATE_FLOAT) ?: null;
$verifiedOnly = !empty($_GET['verified']);

if (!valid_ghana_region($region)) {
    $region = '';
}

$where = ['l.status = "active"'];
$params = [];

if ($q !== '') {
    $where[] = 'MATCH(l.title, l.description) AGAINST (? IN NATURAL LANGUAGE MODE)';
    $params[] = $q;
}
if ($categoryId) {
    $where[] = 'l.category_id = ?';
    $params[] = $categoryId;
}
if ($town !== '') {
    $where[] = 'l.town LIKE ?';
    $params[] = '%' . $town . '%';
}
if ($region !== '') {
    $where[] = 'l.region = ?';
    $params[] = $region;
}
if ($minPrice !== null) {
    $where[] = 'l.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'l.price <= ?';
    $params[] = $maxPrice;
}
if ($verifiedOnly) {
  $where[] = 'u.email_verified = 1 AND u.phone_verified = 1 AND u.id_verified = 1';
}

$selectedCategory = null;
if ($categoryId) {
    foreach ($categories as $category) {
        if ((int) $category['id'] === $categoryId) {
            $selectedCategory = $category;
            break;
        }
    }
}

$sql = 'SELECT l.id, l.title, l.price, l.town, l.created_at, c.name AS category_name,
               u.full_name, u.avatar_path, u.email_verified, u.phone_verified, u.id_verified,
               (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS thumb
        FROM listings l
        JOIN users u ON u.id = l.user_id
  JOIN categories c ON c.id = l.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY l.created_at DESC
        LIMIT 60';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$pageTitle = 'Browse Listings';
$__page = 'browse';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="section-head" style="margin-top:32px;">
    <h1 style="font-size:2rem;">Browse listings</h1>
    <?php if ($selectedCategory): ?><p>Showing only listings tagged <strong><?= e($selectedCategory['name']) ?></strong>.</p><?php endif; ?>
  </div>

  <section class="browse-category-section" aria-labelledby="browse-categories-heading">
    <h2 id="browse-categories-heading">Choose a category</h2>
    <p>Start with the kind of item or service you want to find.</p>
    <div class="category-row">
      <a class="category-chip <?= !$categoryId ? 'active' : '' ?>" href="browse.php">All categories</a>
      <?php foreach ($categories as $cat): ?>
        <a class="category-chip <?= $categoryId === (int)$cat['id'] ? 'active' : '' ?>" href="browse.php?category=<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <form method="get" class="form-card wide" style="margin:0 0 32px;padding:24px;">
    <div class="browse-filter-fields">
      <div class="field" style="margin:0;">
        <label for="q">Search</label>
        <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="What are you looking for?">
      </div>
      <div class="field" style="margin:0;">
        <label for="category">Category</label>
        <select id="category" name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
        <div class="field" style="margin:0;">
          <label for="town">Town</label>
          <input type="text" id="town" name="town" value="<?= e($town) ?>" placeholder="e.g. Nkawie">
        </div>
        <div class="field" style="margin:0;">
          <label for="region">Region</label>
          <select id="region" name="region">
            <option value="">All 16 regions</option>
            <?php foreach (ghana_regions() as $ghanaRegion): ?>
              <option value="<?= e($ghanaRegion) ?>" <?= $region === $ghanaRegion ? 'selected' : '' ?>><?= e($ghanaRegion) ?> Region</option>
            <?php endforeach; ?>
          </select>
        </div>
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
    <div style="margin-top:14px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
      <label style="font-size:0.88rem;display:flex;align-items:center;gap:6px;">
        <input type="checkbox" name="verified" value="1" <?= $verifiedOnly ? 'checked' : '' ?> style="width:auto;"> Verified traders only
      </label>
      <input type="number" name="min" value="<?= e((string)($minPrice ?? '')) ?>" placeholder="Min GH₵" style="max-width:130px;">
      <input type="number" name="max" value="<?= e((string)($maxPrice ?? '')) ?>" placeholder="Max GH₵" style="max-width:130px;">
    </div>
  </form>

  <div class="compare-rail" id="compareRail" aria-live="polite">
    <div>
      <span class="compare-rail-label">Selected sellers</span>
      <strong id="compareCount">0 selected</strong>
    </div>
    <a id="compareButton" class="compare-rail-btn disabled" href="<?= APP_URL ?>/compare.php" aria-disabled="true">Compare &amp; Contact</a>
  </div>

  <?php if (!$results): ?>
    <div class="empty-state">
      <h3>No listings match your search</h3>
      <p>Try a different keyword, category, or clear your filters.</p>
    </div>
  <?php else: ?>
    <div class="listing-grid" style="margin-bottom:48px;">
      <?php foreach ($results as $item): ?>
        <?php include __DIR__ . '/includes/listing-card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
