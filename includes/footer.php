</main>
<footer class="site-footer">
  <div class="wrap footer-row">
    <div class="footer-brand">
      <span class="brand-word">Sure<span class="brand-word-light">Sell</span></span>
      <p>SureSell makes it easier to discover trusted local listings, compare options confidently, and trade with peace of mind.</p>
    </div>
    <div class="footer-links">
      <div>
        <h4>Marketplace</h4>
        <a href="<?= APP_URL ?>/browse.php">Browse listings</a>
        <a href="<?= APP_URL ?>/register.php">Become a trader</a>
      </div>
      <div>
        <h4>Trust &amp; safety</h4>
        <a href="<?= APP_URL ?>/how-it-works.php">How verification works</a>
        <a href="<?= APP_URL ?>/report.php">Report a problem</a>
      </div>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <span>&copy; <?= date('Y') ?> SureSell · Built in Ghana</span>
  </div>
</footer>
<script src="<?= APP_URL ?>/assets/js/script.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/script.js') ?>"></script>
</body>
</html>
