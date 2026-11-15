/* =========================================================
   Imagination Nature Studio — main.js
   Handles: public burger menu, admin burger menu,
            portfolio filter, lightbox, reveal animations
   ========================================================= */
(function () {
  'use strict';

  /* ---------- Run when DOM is ready ---------- */
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  /* =========================================================
     1. PUBLIC BURGER MENU
     ========================================================= */
  function setupPublicMenu() {
    var button = document.getElementById('navToggle');
    var menu   = document.getElementById('mainNav');

    if (!button || !menu) {
      return;
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      var isOpen = menu.classList.contains('is-open');

      if (isOpen) {
        menu.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('nav-open');
      } else {
        menu.classList.add('is-open');
        button.classList.add('is-active');
        document.body.classList.add('nav-open');
      }
    });

    /* Close menu when a link inside it is clicked */
    var menuLinks = menu.getElementsByTagName('a');
    for (var i = 0; i < menuLinks.length; i++) {
      menuLinks[i].addEventListener('click', function () {
        menu.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('nav-open');
      });
    }

    /* Close menu when user clicks outside */
    document.addEventListener('click', function (event) {
      if (!menu.classList.contains('is-open')) return;
      if (menu.contains(event.target)) return;
      if (button.contains(event.target)) return;

      menu.classList.remove('is-open');
      button.classList.remove('is-active');
      document.body.classList.remove('nav-open');
    });

    /* Close menu on Escape key */
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && menu.classList.contains('is-open')) {
        menu.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('nav-open');
      }
    });

    /* Close menu when resizing back to desktop */
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900 && menu.classList.contains('is-open')) {
        menu.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('nav-open');
      }
    });
  }

  /* =========================================================
     2. ADMIN BURGER MENU
     ========================================================= */
  function setupAdminMenu() {
    var button   = document.getElementById('adminNavToggle');
    var sidebar  = document.getElementById('adminSidebar');
    var backdrop = document.getElementById('adminBackdrop');

    if (!button || !sidebar) {
      return;
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      var isOpen = sidebar.classList.contains('is-open');

      if (isOpen) {
        sidebar.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('admin-nav-open');
        if (backdrop) backdrop.classList.remove('is-open');
      } else {
        sidebar.classList.add('is-open');
        button.classList.add('is-active');
        document.body.classList.add('admin-nav-open');
        if (backdrop) backdrop.classList.add('is-open');
      }
    });

    /* Close sidebar when backdrop is clicked */
    if (backdrop) {
      backdrop.addEventListener('click', function () {
        sidebar.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('admin-nav-open');
        backdrop.classList.remove('is-open');
      });
    }

    /* Close sidebar when a menu link is clicked (mobile only) */
    var sidebarLinks = sidebar.getElementsByTagName('a');
    for (var i = 0; i < sidebarLinks.length; i++) {
      sidebarLinks[i].addEventListener('click', function () {
        if (window.innerWidth <= 800) {
          sidebar.classList.remove('is-open');
          button.classList.remove('is-active');
          document.body.classList.remove('admin-nav-open');
          if (backdrop) backdrop.classList.remove('is-open');
        }
      });
    }

    /* Close sidebar on Escape key */
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        sidebar.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('admin-nav-open');
        if (backdrop) backdrop.classList.remove('is-open');
      }
    });

    /* Close sidebar when resizing to desktop */
    window.addEventListener('resize', function () {
      if (window.innerWidth > 800 && sidebar.classList.contains('is-open')) {
        sidebar.classList.remove('is-open');
        button.classList.remove('is-active');
        document.body.classList.remove('admin-nav-open');
        if (backdrop) backdrop.classList.remove('is-open');
      }
    });
  }

  /* =========================================================
     3. PORTFOLIO FILTER
     ========================================================= */
  function setupPortfolioFilter() {
    var bar = document.querySelector('.filter-bar');
    if (!bar) return;

    var buttons = bar.getElementsByTagName('button');
    var tiles   = document.querySelectorAll('.masonry .tile');

    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function () {
        for (var k = 0; k < buttons.length; k++) {
          buttons[k].classList.remove('active');
        }
        this.classList.add('active');

        var category = this.getAttribute('data-filter') || 'all';

        for (var t = 0; t < tiles.length; t++) {
          var tileCategory = tiles[t].getAttribute('data-category');
          if (category === 'all' || tileCategory === category) {
            tiles[t].style.display = '';
          } else {
            tiles[t].style.display = 'none';
          }
        }
      });
    }
  }

  /* =========================================================
     4. LIGHTBOX
     ========================================================= */
  function setupLightbox() {
    var box = document.getElementById('lightbox');
    if (!box) return;

    var image    = box.querySelector('img');
    var closeBtn = box.querySelector('.lightbox-close');
    var tiles    = document.querySelectorAll('.masonry .tile');

    function open(src) {
      if (image && src) image.src = src;
      box.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function close() {
      box.classList.remove('open');
      document.body.style.overflow = '';
    }

    for (var i = 0; i < tiles.length; i++) {
      tiles[i].addEventListener('click', function () {
        var imgEl = this.querySelector('img');
        var src = this.getAttribute('data-full') || (imgEl ? imgEl.src : '');
        if (src) open(src);
      });
    }

    if (closeBtn) closeBtn.addEventListener('click', close);
    box.addEventListener('click', function (event) {
      if (event.target === box) close();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') close();
    });
  }

  /* =========================================================
     5. REVEAL ON SCROLL
     ========================================================= */
  function setupReveal() {
    var items = document.querySelectorAll('.reveal');
    if (!items.length) return;

    if (!('IntersectionObserver' in window)) {
      for (var i = 0; i < items.length; i++) items[i].classList.add('is-visible');
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    for (var j = 0; j < items.length; j++) observer.observe(items[j]);
  }

  /* =========================================================
     6. HEADER SCROLL SHADOW
     ========================================================= */
  function setupHeaderScroll() {
    var header = document.getElementById('siteHeader');
    if (!header) return;

    function update() {
      if (window.scrollY > 20) header.classList.add('scrolled');
      else header.classList.remove('scrolled');
    }
    update();
    window.addEventListener('scroll', update, { passive: true });
  }

  /* =========================================================
     BOOT
     ========================================================= */
  onReady(function () {
    setupPublicMenu();
    setupAdminMenu();
    setupPortfolioFilter();
    setupLightbox();
    setupReveal();
    setupHeaderScroll();
  });

})();