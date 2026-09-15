<?php
require_once __DIR__ . '/functions.php';
$__user = current_user();
$__page = $__page ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' · ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Karla:wght@400;500;600;700&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="<?= APP_URL ?>/index.php">
      <img class="brand-logo" src="<?= APP_URL ?>/assets/img/nokware-logo.svg" alt="SureSell">
    </a>

    <button class="nav-toggle" id="navToggle" type="button" aria-label="Open menu" aria-controls="siteNav" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <nav class="site-nav" id="siteNav">
      <a href="<?= APP_URL ?>/browse.php" class="<?= $__page==='browse'?'active':'' ?>">Browse</a>
      <?php if ($__user): ?>
        <a href="<?= APP_URL ?>/add-listing.php" class="<?= $__page==='add-listing'?'active':'' ?>">Sell an item</a>
        <a href="<?= APP_URL ?>/dashboard.php" class="<?= $__page==='dashboard'?'active':'' ?>">My dashboard</a>
        <a href="<?= APP_URL ?>/profile.php?id=<?= (int)$__user['id'] ?>" class="nav-user">
          <?php if (!empty($__user['avatar_path'])): ?>
            <img class="nav-avatar" src="<?= APP_URL ?>/assets/uploads/<?= e($__user['avatar_path']) ?>" alt="">
          <?php else: ?>
            <span class="nav-avatar nav-avatar-placeholder"><?= e(mb_substr($__user['full_name'],0,1)) ?></span>
          <?php endif; ?>
          <span class="nav-user-name">
            <strong><?= e($__user['full_name']) ?></strong>
            <small><?= is_platform_admin($__user) ? 'Admin' : 'Trader account' ?></small>
          </span>
        </a>
        <?php if (is_platform_admin($__user)): ?><a href="<?= APP_URL ?>/admin/index.php" class="nav-admin-link">Admin</a><?php endif; ?>
        <a href="<?= APP_URL ?>/logout.php" class="btn-ghost nav-logout">Log out</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/login.php" class="<?= $__page==='login'?'active':'' ?>">Log in</a>
        <a href="<?= APP_URL ?>/register.php" class="btn-solid">Join as a trader</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($msg = flash_get('success')): ?>
  <div class="wrap"><div class="alert alert-success"><?= e($msg) ?></div></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="wrap"><div class="alert alert-error"><?= e($msg) ?></div></div>
<?php endif; ?>
<main>
