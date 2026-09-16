<?php
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
$sellUrl = $user ? APP_URL . '/add-listing.php' : APP_URL . '/register.php';
$accountUrl = $user ? APP_URL . '/dashboard.php' : APP_URL . '/login.php';

$categories = db()->query('SELECT c.id, c.name, c.slug, COUNT(l.id) AS listing_count
                           FROM categories c
                           LEFT JOIN listings l ON l.category_id = c.id AND l.status = "active"
                           GROUP BY c.id, c.name, c.slug
                           ORDER BY c.id')->fetchAll();
$sellerStmt = db()->query('SELECT u.id, u.full_name, u.business_name, u.town, u.id_verified, u.deals_completed,
                                  COALESCE(AVG(r.rating), 0) AS rating, COUNT(r.id) AS review_count
                           FROM users u
                           LEFT JOIN reviews r ON r.reviewee_id = u.id
                           WHERE u.status = "active" AND u.role = "trader" AND u.phone_verified = 1
                           GROUP BY u.id
                           ORDER BY u.id_verified DESC, u.deals_completed DESC, rating DESC
                           LIMIT 3');
$sellers = $sellerStmt->fetchAll();
$latestListingsStmt = db()->query('SELECT l.id, l.title, l.price, l.town, l.created_at, c.name AS category_name,
                u.full_name, u.phone_verified, u.id_verified,
                (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS thumb
              FROM listings l
              JOIN users u ON u.id = l.user_id
              JOIN categories c ON c.id = l.category_id
              WHERE l.status = "active" AND u.status = "active"
              ORDER BY l.created_at DESC
              LIMIT 6');
$latestListings = $latestListingsStmt->fetchAll();

$tileClasses = ['landing-tint-navy', 'landing-tint-gold', 'landing-tint-palm'];
$tileIcons = ['&#128722;', '&#128187;', '&#128087;', '&#129521;', '&#127807;', '&#128663;', '&#128736;', '&#10024;'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="SureSell is Ghana's trusted local marketplace for verified traders and buyers.">
  <title>SureSell — Trade with people you can trust</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="landing-page">
<header class="landing-header">
  <div class="wrap landing-header-row">
    <a href="#top" class="landing-logo" aria-label="SureSell home">
      <img class="landing-brand-logo" src="<?= APP_URL ?>/assets/img/nokware-logo.svg" alt="SureSell">
    </a>
    <nav class="landing-nav" aria-label="Main navigation">
      <a href="#how">How it works</a>
      <a href="#categories">Categories</a>
      <a href="#trust">Trust &amp; safety</a>
      <a href="#sellers">Sellers</a>
    </nav>
    <div class="landing-header-actions">
      <a href="<?= e($sellUrl) ?>" class="landing-btn landing-btn-gold"><?= $user ? 'Sell an item' : 'Start trading' ?></a>
      <a href="<?= e($accountUrl) ?>" class="landing-login-link"><?= $user ? 'My account' : 'Log in' ?></a>
      <button class="landing-menu-toggle" id="landingMenuToggle" type="button" aria-controls="landingMobilePanel" aria-expanded="false" aria-label="Open navigation menu"><span></span></button>
    </div>
  </div>
  <nav class="landing-mobile-panel" id="landingMobilePanel" aria-label="Mobile navigation">
    <a href="#how">How it works</a>
    <a href="#categories">Categories</a>
    <a href="#trust">Trust &amp; safety</a>
    <a href="#sellers">Sellers</a>
    <a href="<?= e($sellUrl) ?>"><?= $user ? 'Sell an item' : 'Start trading' ?></a>
    <a href="<?= e($accountUrl) ?>"><?= $user ? 'My account' : 'Log in' ?></a>
  </nav>
</header>

<main>
  <section class="landing-hero" id="top">
    <div class="wrap landing-hero-grid">
      <div class="landing-hero-copy">
        <h1>Trade with people you can actually trust.</h1>
        <p class="landing-lede">SureSell helps you find local goods and services from verified traders, with real profiles and deal history built for Ghana's communities.</p>
        <form class="landing-search-panel" action="<?= APP_URL ?>/browse.php" method="get">
          <label class="sr-only" for="landing-search">Search listings</label>
          <div class="landing-search-row">
            <select class="landing-select-field" name="region" aria-label="Region">
              <option value="">All 16 regions</option>
              <?php foreach (ghana_regions() as $region): ?>
                <option value="<?= e($region) ?>"><?= e($region) ?> Region</option>
              <?php endforeach; ?>
            </select>
            <input class="landing-search-field" id="landing-search" name="q" type="search" placeholder="Search fabric, phones, furniture…">
            <button class="landing-search-btn" type="submit" aria-label="Search listings">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="m16.2 16.2 4.8 4.8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
        </form>
        <ul class="landing-trust-strip" aria-label="SureSell trust features">
          <li><span aria-hidden="true">✓</span>Verified sellers</li>
          <li><span aria-hidden="true">⌁</span>Real trade history</li>
          <li><span aria-hidden="true">★</span>Buyer reviews</li>
        </ul>
      </div>
      <div class="landing-hero-visual" aria-hidden="true">
        <svg viewBox="0 0 280 280" fill="none"><circle cx="140" cy="140" r="108" stroke="#4A5A80" stroke-width="1.5" stroke-dasharray="2 8"/><circle cx="140" cy="140" r="92" fill="#E7A227"/><path d="m112 140 20 20 40-44" stroke="#131F35" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/><path d="m108 224 16-24h32l16 24-32 12-32-12Z" fill="#B87E14"/><circle cx="196" cy="200" r="46" fill="#FEFDFB" stroke="#E4E0D3" stroke-width="1.5"/><path d="M182 194h28l-3 20a3 3 0 0 1-3 2.5h-16a3 3 0 0 1-3-2.5l-3-20Z" stroke="#131F35" stroke-width="2"/><path d="M187 194v-4a9 9 0 0 1 18 0v4" stroke="#131F35" stroke-width="2"/></svg>
      </div>
    </div>
  </section>

  <section class="landing-section" id="categories">
    <div class="wrap">
      <div class="landing-section-head"><h2>What people trade on SureSell</h2><p>Explore locally listed goods and services from people in your community.</p></div>
      <div class="landing-category-grid">
        <?php foreach ($categories as $index => $category): ?>
          <a href="<?= APP_URL ?>/browse.php?category=<?= (int) $category['id'] ?>" class="landing-category-tile <?= $tileClasses[$index % count($tileClasses)] ?>">
            <span class="landing-category-icon-badge" aria-hidden="true"><?= $tileIcons[$index] ?? '&#128722;' ?></span>
            <span class="landing-category-copy">
              <span class="landing-category-name"><?= e($category['name']) ?></span>
              <span class="landing-category-count"><?= number_format((int) $category['listing_count']) ?>+ listings</span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="landing-section market-picks" aria-label="Featured market picks">
    <div class="wrap">
      <div class="market-picks-head">
        <div class="landing-section-head">
          <span class="eyebrow">Market picks</span>
          <h2>Fresh finds for every kind of shopper</h2>
        </div>
        <a class="text-link" href="<?= APP_URL ?>/browse.php">Browse all listings <span aria-hidden="true">→</span></a>
      </div>
      <div class="market-picks-grid">
        <a class="market-pick market-pick-cart" href="<?= APP_URL ?>/browse.php?q=shopping+cart" style="background-image: linear-gradient(180deg, rgba(19,31,53,0.18), rgba(19,31,53,0.72)), url('https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=80');">
          <span class="market-pick-kicker">Smart finds</span>
          <strong>Shopping carts</strong>
          <span>Stylish everyday essentials for your home and routine.</span>
        </a>
        <a class="market-pick market-pick-wear" href="<?= APP_URL ?>/browse.php?category=3" style="background-image: linear-gradient(180deg, rgba(168,78,63,0.14), rgba(168,78,63,0.78)), url('https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=900&q=80');">
          <span class="market-pick-kicker">Fashion</span>
          <strong>Designer wear</strong>
          <span>Polished, confident looks from trusted local sellers.</span>
        </a>
        <a class="market-pick market-pick-watch" href="<?= APP_URL ?>/browse.php?category=2" style="background-image: linear-gradient(180deg, rgba(61,70,80,0.12), rgba(61,70,80,0.76)), url('https://images.unsplash.com/photo-1523170335258-f5ed11844a49?auto=format&fit=crop&w=900&q=80');">
          <span class="market-pick-kicker">Luxury</span>
          <strong>Watches</strong>
          <span>Classic timepieces, modern essentials, and standout pieces.</span>
        </a>
        <a class="market-pick market-pick-scent" href="<?= APP_URL ?>/browse.php?q=perfume" style="background-image: linear-gradient(180deg, rgba(183,119,50,0.12), rgba(183,119,50,0.78)), url('https://images.unsplash.com/photo-1528740561666-dc2479d461a6?auto=format&fit=crop&w=900&q=80');">
          <span class="market-pick-kicker">Beauty</span>
          <strong>Perfumes</strong>
          <span>Fresh scents and signature aromas that feel personal.</span>
        </a>
      </div>
    </div>
  </section>

  <section class="landing-section landing-how-section" id="how">
    <div class="wrap">
      <div class="landing-how-head">
        <div class="landing-section-head"><h2>Three steps to a safer trade</h2><p>Simple signals help you make a more informed choice before you buy or sell.</p></div>
        <button class="landing-listings-trigger" id="latestListingsTrigger" type="button" aria-haspopup="dialog" aria-controls="latestListingsDialog">View latest listings <span aria-hidden="true">↗</span></button>
      </div>
      <div class="landing-steps">
        <article class="landing-step"><span>1</span><div><h3>Browse &amp; verify</h3><p>See a trader's verification status and public history before you make contact.</p></div></article>
        <article class="landing-step"><span>2</span><div><h3>Connect safely</h3><p>Use the listing details to start a conversation and ask the questions that matter.</p></div></article>
        <article class="landing-step"><span>3</span><div><h3>Trade with confidence</h3><p>Meet responsibly, inspect items carefully, and confirm completed deals on SureSell.</p></div></article>
      </div>
      <?php if ($latestListings): ?>
        <div class="landing-latest-head"><h3>Just listed</h3><span>Fresh from verified community activity</span></div>
        <div class="landing-latest-grid">
          <?php foreach (array_slice($latestListings, 0, 3) as $listing): ?>
            <a class="landing-latest-card" href="<?= APP_URL ?>/listing.php?id=<?= (int) $listing['id'] ?>">
              <div class="landing-latest-thumb">
                <?php if (!empty($listing['thumb'])): ?><img src="<?= APP_URL ?>/assets/uploads/<?= e($listing['thumb']) ?>" alt="<?= e($listing['title']) ?>" loading="lazy"><?php else: ?><span>No photo</span><?php endif; ?>
              </div>
              <div class="landing-latest-copy"><strong><?= e($listing['title']) ?></strong><b>GH₵ <?= number_format((float) $listing['price'], 2) ?></b><span><?= e($listing['town']) ?> · <?= e($listing['category_name']) ?></span></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="landing-latest-empty">New listings will appear here as soon as verified traders start posting.</div>
      <?php endif; ?>
      <a class="landing-text-link" href="<?= APP_URL ?>/how-it-works.php">Learn how verification works <span aria-hidden="true">→</span></a>
    </div>
  </section>

  <dialog class="latest-listings-dialog" id="latestListingsDialog" aria-labelledby="latestListingsTitle">
    <div class="latest-listings-dialog-head"><div><span class="eyebrow">Fresh on SureSell</span><h2 id="latestListingsTitle">Latest listings</h2></div><button class="dialog-close" id="latestListingsClose" type="button" aria-label="Close latest listings">&times;</button></div>
    <?php if ($latestListings): ?>
      <div class="landing-dialog-listings">
        <?php foreach ($latestListings as $listing): ?>
          <a class="landing-latest-card" href="<?= APP_URL ?>/listing.php?id=<?= (int) $listing['id'] ?>">
            <div class="landing-latest-thumb">
              <?php if (!empty($listing['thumb'])): ?><img src="<?= APP_URL ?>/assets/uploads/<?= e($listing['thumb']) ?>" alt="<?= e($listing['title']) ?>" loading="lazy"><?php else: ?><span>No photo</span><?php endif; ?>
            </div>
            <div class="landing-latest-copy"><strong><?= e($listing['title']) ?></strong><b>GH₵ <?= number_format((float) $listing['price'], 2) ?></b><span><?= e($listing['town']) ?> · <?= e($listing['category_name']) ?></span></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="landing-latest-empty">No active listings yet.</div><?php endif; ?>
    <a class="landing-btn landing-btn-ink" href="<?= APP_URL ?>/browse.php">Browse every listing</a>
  </dialog>

  <section class="landing-section" id="sellers">
    <div class="wrap">
      <div class="landing-section-head"><h2>Trusted sellers, chosen by their community</h2><p>Profiles build credibility through verified details and confirmed trade activity.</p></div>
      <?php if ($sellers): ?>
        <div class="landing-seller-grid">
          <?php foreach ($sellers as $index => $seller): ?>
            <?php $initials = strtoupper(mb_substr($seller['full_name'], 0, 1) . mb_substr(strstr($seller['full_name'], ' ') ?: '', 1, 1)); ?>
            <a class="landing-seller-card" href="<?= APP_URL ?>/profile.php?id=<?= (int) $seller['id'] ?>">
              <div class="landing-seller-top"><span class="landing-avatar landing-avatar-<?= $index % 3 ?>"><?= e($initials ?: 'N') ?></span><div><h3><?= e($seller['business_name'] ?: $seller['full_name']) ?></h3><p><?= e($seller['town']) ?></p></div></div>
              <div class="landing-seller-meta"><span>★ <?= $seller['review_count'] ? number_format((float) $seller['rating'], 1) : 'New' ?> · <?= (int) $seller['deals_completed'] ?> trades</span><span class="landing-verified">✓ <?= $seller['id_verified'] ? 'Verified' : 'Phone verified' ?></span></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="landing-empty"><p>Our verified trader community is growing.</p><a href="<?= e($sellUrl) ?>" class="landing-btn landing-btn-ink">Be among the first to list</a></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="landing-cta-band" id="trust">
    <div class="wrap landing-cta-inner"><div><h2>Selling something? List it free.</h2><p>Create a profile, verify your phone, and start building your trade history.</p></div><a href="<?= e($sellUrl) ?>" class="landing-btn landing-btn-ink">Create a seller profile</a></div>
  </section>
</main>

<footer class="landing-footer">
  <div class="wrap"><div class="landing-footer-top"><a href="#top" class="landing-logo"><img class="landing-brand-logo" src="<?= APP_URL ?>/assets/img/nokware-logo.svg" alt="SureSell"></a><nav aria-label="Footer navigation"><a href="<?= APP_URL ?>/browse.php">Browse</a><a href="<?= APP_URL ?>/how-it-works.php">How it works</a><a href="<?= APP_URL ?>/report.php">Trust &amp; safety</a><a href="<?= APP_URL ?>/register.php">Join SureSell</a></nav></div><p>SureSell is built for Ghana's small traders and artisans — because everyone deserves a fair trade.</p><div class="landing-footer-bottom">&copy; <?= date('Y') ?> SureSell. Built in Ghana.</div></div>
</footer>
<script src="<?= APP_URL ?>/assets/js/script.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/script.js') ?>"></script>
</body>
</html>
