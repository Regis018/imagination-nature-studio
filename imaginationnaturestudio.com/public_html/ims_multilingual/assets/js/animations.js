/* =========================================================
   Imagination Nature Studio — Animation Engine
   Runs on PUBLIC pages only. Bails out on admin pages.
   ========================================================= */
(function () {
  'use strict';

  /* ---------- Bail out on admin pages ---------- */
  if (document.body && document.body.classList.contains('admin-body')) {
    document.body.style.opacity = '1';
    document.body.classList.add('is-ready');
    return;
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else fn();
  }

  ready(function () {

    /* ---------- Reveal body after preloader ---------- */
    document.body.classList.add('is-ready');

    /* ---------- Preloader ---------- */
    var pre = document.querySelector('.preloader');
    if (pre) {
      setTimeout(function () {
        pre.classList.add('is-done');
        setTimeout(function () {
          if (pre.parentNode) pre.parentNode.removeChild(pre);
        }, 900);
      }, 1400);
    }

    /* ---------- Scroll progress bar ---------- */
    var bar = document.createElement('div');
    bar.className = 'scroll-progress';
    document.body.appendChild(bar);

    var updateProgress = function () {
      var h = document.documentElement;
      var scrolled = h.scrollTop / (h.scrollHeight - h.clientHeight || 1);
      bar.style.width = (scrolled * 100) + '%';
    };
    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();

    /* ---------- Reveal on scroll ---------- */
    var selectors = [
      '.reveal', '.reveal-fade', '.reveal-left', '.reveal-right',
      '.reveal-scale', '.reveal-blur', '.stagger', '.section-head'
    ];
    var els = document.querySelectorAll(selectors.join(','));

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
      els.forEach(function (el) { io.observe(el); });
    } else {
      els.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* ---------- Hero particles ---------- */
    var hero = document.querySelector('.hero');
    if (hero) {
      var layer = document.createElement('div');
      layer.className = 'hero-particles';
      for (var i = 0; i < 18; i++) {
        var p = document.createElement('span');
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = (Math.random() * 10) + 's';
        p.style.animationDuration = (8 + Math.random() * 8) + 's';
        p.style.opacity = (0.15 + Math.random() * 0.35);
        layer.appendChild(p);
      }
      hero.appendChild(layer);
    }

    /* ---------- Cursor spotlight (desktop only) ---------- */
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      var glow = document.createElement('div');
      glow.className = 'cursor-glow';
      document.body.appendChild(glow);

      var gx = 0, gy = 0, cx = 0, cy = 0;
      document.addEventListener('mousemove', function (e) {
        gx = e.clientX;
        gy = e.clientY;
      });
      (function tick() {
        cx += (gx - cx) * 0.12;
        cy += (gy - cy) * 0.12;
        glow.style.transform = 'translate(' + cx + 'px,' + cy + 'px) translate(-50%,-50%)';
        requestAnimationFrame(tick);
      })();
    }

    /* ---------- Card cursor tracking ---------- */
    var cards = document.querySelectorAll('.card');
    cards.forEach(function (card) {
      card.addEventListener('mousemove', function (e) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
        card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
      });
    });

    /* ---------- Magnetic buttons ---------- */
    var magnets = document.querySelectorAll('.btn-primary, .btn-light');
    magnets.forEach(function (btn) {
      btn.addEventListener('mousemove', function (e) {
        var r = btn.getBoundingClientRect();
        var x = e.clientX - r.left - r.width / 2;
        var y = e.clientY - r.top - r.height / 2;
        btn.style.transform = 'translate(' + (x * 0.12) + 'px,' + (y * 0.24 - 2) + 'px)';
      });
      btn.addEventListener('mouseleave', function () {
        btn.style.transform = '';
      });
    });

    /* ---------- Hero parallax ---------- */
    if (hero && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      var ticking = false;
      window.addEventListener('scroll', function () {
        if (ticking) return;
        requestAnimationFrame(function () {
          var y = window.scrollY;
          if (y < window.innerHeight * 1.2) {
            hero.style.backgroundPosition = 'center calc(50% + ' + (y * 0.4) + 'px)';
          }
          ticking = false;
        });
        ticking = true;
      }, { passive: true });
    }

    /* ---------- Header hide/show on scroll ---------- */
    var header = document.getElementById('siteHeader');
    if (header) {
      var lastY = 0;
      window.addEventListener('scroll', function () {
        var y = window.scrollY;
        if (y > 200 && y > lastY) header.classList.add('hidden');
        else header.classList.remove('hidden');
        header.classList.toggle('scrolled', y > 20);
        lastY = y;
      }, { passive: true });
    }

    /* ---------- Number count-up ---------- */
    var counters = document.querySelectorAll('[data-count]');
    if (counters.length && 'IntersectionObserver' in window) {
      var cio = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          var target = parseInt(el.dataset.count, 10) || 0;
          var duration = parseInt(el.dataset.countDuration || 1600, 10);
          var start = performance.now();
          function step(now) {
            var p = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (p < 1) requestAnimationFrame(step);
          }
          requestAnimationFrame(step);
          cio.unobserve(el);
        });
      }, { threshold: 0.4 });
      counters.forEach(function (c) { cio.observe(c); });
    }

  });
})();