/* === GRAND STRAND DIGITAL — SHARED JS === */

// --- Light / dark theme toggle ---
// The <head> snippet has already set data-theme before first paint; this
// adds the button, keeps aria-label in sync, and remembers the choice.
(function () {
  'use strict';
  var root = document.documentElement;
  var KEY = 'gsd-theme';
  var nav = document.getElementById('nav');
  if (!nav) return;
  var chosen = false; // explicit click this page view, kept even if storage fails

  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'theme-btn';
  btn.innerHTML =
    '<svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>' +
    '<svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
  var mobileToggle = nav.querySelector('.mobile-toggle');
  nav.insertBefore(btn, mobileToggle || null);

  function current() { return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }
  function sync() {
    var next = current() === 'dark' ? 'light' : 'dark';
    btn.setAttribute('aria-label', 'Switch to ' + next + ' theme');
    btn.setAttribute('title', 'Switch to ' + next + ' theme');
  }
  function setTheme(t, persist) {
    if (persist) chosen = true;
    root.classList.add('theme-anim');
    root.setAttribute('data-theme', t);
    sync();
    if (persist) { try { localStorage.setItem(KEY, t); } catch (e) {} }
    setTimeout(function () { root.classList.remove('theme-anim'); }, 400);
  }
  btn.addEventListener('click', function () { setTheme(current() === 'dark' ? 'light' : 'dark', true); });

  // Follow OS changes live, but only until the visitor picks a theme themselves
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function (e) {
      if (chosen) return;
      var saved = null; try { saved = localStorage.getItem(KEY); } catch (err) {}
      if (saved !== 'dark' && saved !== 'light') setTheme(e.matches ? 'dark' : 'light', false);
    };
    if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
  }
  sync();
})();

(function () {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // --- Scroll-driven effects (nav shadow + hero parallax), batched per frame ---
  const nav = document.getElementById('nav');
  const pageHero = reduceMotion ? null : document.querySelector('.page-hero');
  let ticking = false;
  function onScrollFrame() {
    const y = window.scrollY;
    if (nav) nav.classList.toggle('scrolled', y > 50);
    if (pageHero && y < 600) pageHero.style.backgroundPositionY = (y * 0.3) + 'px';
    ticking = false;
  }
  window.addEventListener('scroll', () => {
    if (!ticking) { ticking = true; requestAnimationFrame(onScrollFrame); }
  }, { passive: true });
  onScrollFrame();

  // --- Mobile menu ---
  const mobileToggle = document.querySelector('.mobile-toggle');
  const navLinks = document.querySelector('.nav-links');
  if (mobileToggle && navLinks) {
    if (!navLinks.id) navLinks.id = 'primary-nav';
    mobileToggle.setAttribute('aria-controls', navLinks.id);
    mobileToggle.setAttribute('aria-expanded', 'false');
    const setMenu = (open) => {
      navLinks.classList.toggle('show', open);
      mobileToggle.setAttribute('aria-expanded', String(open));
    };
    mobileToggle.addEventListener('click', () => setMenu(!navLinks.classList.contains('show')));
    navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenu(false)));
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navLinks.classList.contains('show')) {
        setMenu(false);
        mobileToggle.focus();
      }
    });
  }

  // --- Scroll reveal (.reveal, .reveal-left, .reveal-right, .reveal-scale) ---
  const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');
  const showAll = () => revealEls.forEach(el => el.classList.add('visible'));
  if (reduceMotion || !('IntersectionObserver' in window)) {
    showAll();
  } else {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(el => observer.observe(el));
    // Fallback for fast scrollers / slow loads
    setTimeout(showAll, 2000);
  }

  // --- Smooth scroll for same-page anchor links ---
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const href = a.getAttribute('href');
      if (href === '#') return;
      const target = document.querySelector(href);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    });
  });

  // --- Tabs (WAI-ARIA tabs pattern, arrow-key navigation) ---
  const tabBtns = Array.from(document.querySelectorAll('.tab-btn'));
  if (tabBtns.length) {
    const list = tabBtns[0].parentElement;
    list.setAttribute('role', 'tablist');
    const activate = (btn, focus) => {
      tabBtns.forEach(b => {
        const on = b === btn;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', String(on));
        b.tabIndex = on ? 0 : -1;
        const panel = document.getElementById(b.dataset.tab);
        if (panel) panel.classList.toggle('active', on);
      });
      if (focus) btn.focus();
    };
    tabBtns.forEach((btn, i) => {
      const panel = document.getElementById(btn.dataset.tab);
      btn.id = btn.id || 'tab-btn-' + i;
      btn.setAttribute('role', 'tab');
      if (panel) {
        btn.setAttribute('aria-controls', panel.id);
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', btn.id);
      }
      btn.addEventListener('click', () => activate(btn, false));
      btn.addEventListener('keydown', (e) => {
        let next = null;
        if (e.key === 'ArrowRight') next = tabBtns[(i + 1) % tabBtns.length];
        else if (e.key === 'ArrowLeft') next = tabBtns[(i - 1 + tabBtns.length) % tabBtns.length];
        else if (e.key === 'Home') next = tabBtns[0];
        else if (e.key === 'End') next = tabBtns[tabBtns.length - 1];
        if (next) { e.preventDefault(); activate(next, true); }
      });
    });
    activate(tabBtns.find(b => b.classList.contains('active')) || tabBtns[0], false);
  }

  // --- Accordions (keyboard operable, announces expanded state) ---
  document.querySelectorAll('.accordion-header').forEach((header, i) => {
    const item = header.parentElement;
    const body = item.querySelector('.accordion-body, .accordion-content');
    header.setAttribute('role', 'button');
    header.tabIndex = 0;
    header.setAttribute('aria-expanded', String(item.classList.contains('open')));
    if (body) {
      body.id = body.id || 'accordion-panel-' + i;
      header.setAttribute('aria-controls', body.id);
    }
    const toggle = () => {
      const wasOpen = item.classList.contains('open');
      item.parentElement.querySelectorAll('.accordion-item').forEach(other => {
        other.classList.remove('open');
        const h = other.querySelector('.accordion-header');
        if (h) h.setAttribute('aria-expanded', 'false');
      });
      if (!wasOpen) {
        item.classList.add('open');
        header.setAttribute('aria-expanded', 'true');
      }
    };
    header.addEventListener('click', toggle);
    header.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
  });

  // --- Counter animation (skipped for reduced motion) ---
  const counters = document.querySelectorAll('[data-count]');
  const finalText = (el) => (el.dataset.prefix || '') + parseInt(el.dataset.count, 10) + (el.dataset.suffix || '');
  function animateCounter(el) {
    if (reduceMotion) { el.textContent = finalText(el); return; }
    const target = parseInt(el.dataset.count, 10);
    const start = performance.now();
    const duration = 1500;
    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      el.textContent = (el.dataset.prefix || '') + Math.round(target * t) + (el.dataset.suffix || '');
      if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }
  // Observe each counter on its own so counters work anywhere on the page,
  // including inside tab panels that only become visible when selected.
  if (counters.length) {
    if ('IntersectionObserver' in window) {
      const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            animateCounter(e.target);
            counterObserver.unobserve(e.target);
          }
        });
      }, { threshold: 0.3 });
      counters.forEach(el => counterObserver.observe(el));
    } else {
      counters.forEach(el => { el.textContent = finalText(el); });
    }
  }
})();
