  </main><!-- /.admin-main -->
</div><!-- /.admin-shell -->

<script src="<?= e(SITE_URL) ?>/assets/js/main.js?v=<?= @filemtime(SITE_ROOT . '/assets/js/main.js') ?: time() ?>" defer></script>

<script>
/* =========================================================
   Admin top bar — user dropdown
   ========================================================= */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    var wrap = document.getElementById('adminUser');
    var btn  = document.getElementById('adminUserBtn');

    if (!wrap || !btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      var open = wrap.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!wrap.classList.contains('open')) return;
      if (wrap.contains(e.target)) return;
      wrap.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        wrap.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  });
})();
</script>

</body>
</html>