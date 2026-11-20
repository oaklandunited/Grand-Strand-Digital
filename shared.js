/* === GRAND STRAND DIGITAL — SHARED JS === */
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
  function animateCounters() {
    counters.forEach(el => {
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
    });
  }
  const counterSection = document.querySelector('.counters-row');
  if (counterSection && counters.length) {
    if ('IntersectionObserver' in window) {
      const counterObserver = new IntersectionObserver((entries) => {
        if (entries.some(e => e.isIntersecting)) {
          animateCounters();
          counterObserver.disconnect();
        }
      }, { threshold: 0.3 });
      counterObserver.observe(counterSection);
    } else {
      counters.forEach(el => { el.textContent = finalText(el); });
    }
  }
})();
