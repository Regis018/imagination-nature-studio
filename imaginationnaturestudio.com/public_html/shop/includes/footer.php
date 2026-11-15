</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <div class="footer-brand"><?= e(setting('store_name', 'Imagination Nature Studio') ?? '') ?></div>
      <p class="muted">Premium football &amp; celebrity wall art in Rwanda.</p>
    </div>
    <div>
      <h4>Shop</h4>
      <ul>
        <li><a href="<?= e(url('football-clubs.php')) ?>">Football Clubs</a></li>
        <li><a href="<?= e(url('football-legends.php')) ?>">Football Legends</a></li>
        <li><a href="<?= e(url('celebrities.php')) ?>">Celebrities</a></li>
        <li><a href="<?= e(url('custom-frames.php')) ?>">Custom Frames</a></li>
      </ul>
    </div>
    <div>
      <h4>Company</h4>
      <ul>
        <li><a href="<?= e(url('about.php')) ?>">About</a></li>
        <li><a href="<?= e(url('contact.php')) ?>">Contact</a></li>
        <li><a href="<?= e(url('privacy.php')) ?>">Privacy Policy</a></li>
      </ul>
    </div>
    <div>
      <h4>Order on WhatsApp</h4>
      <p><a class="btn btn-gold" href="<?= e(whatsapp_url('Hello! I would like to order frames.')) ?>" target="_blank" rel="noopener">Chat: <?= e(setting('whatsapp_display', '+250 788 577 033') ?? '') ?></a></p>
    </div>
  </div>
  <div class="container footer-bottom">
    <small>© <?= date('Y') ?> <?= e(setting('store_name', '') ?? '') ?>. All rights reserved.</small>
    <small><a href="<?= e(url('admin/login.php')) ?>">Admin</a></small>
  </div>
</footer>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>