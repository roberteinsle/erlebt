// Navigation, Scroll-Einblendungen und Tagline. Ohne JavaScript bleibt alles sichtbar.
(() => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Mobiles Menü
  const toggle = document.querySelector('.nav-toggle');
  const menu = document.getElementById('mobile-menu');
  if (toggle && menu) {
    const links = [...menu.querySelectorAll('a')];
    const step = Math.min(60, links.length > 1 ? 400 / (links.length - 1) : 0);
    links.forEach((link, i) => { link.style.transitionDelay = `${Math.round(i * step)}ms`; });
    const setOpen = (open) => {
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
      menu.classList.toggle('open', open);
      menu.inert = !open;
      document.body.style.overflow = open ? 'hidden' : '';
    };
    menu.inert = true;
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    links.forEach((link) => link.addEventListener('click', (e) => {
      const hash = link.getAttribute('href');
      setOpen(false);
      if (!hash.startsWith('#')) return;
      // Erst schließen, dann scrollen: solange das Menü offen ist, ist die Seite gesperrt.
      const target = document.querySelector(hash);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth' });
      history.pushState(null, '', hash);
    }));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
  }

  // Ankerziele vorab einblenden, sonst zielt der Browser auf die noch versetzte Position.
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href^="#"]');
    const target = link && document.querySelector(link.getAttribute('href'));
    const section = target && target.closest('.reveal:not(.visible)');
    if (!section) return;
    section.style.transition = 'none';
    section.classList.add('visible');
    section.getBoundingClientRect();
    section.style.transition = '';
  }, true);

  if (!('IntersectionObserver' in window)) {
    document.querySelectorAll('.reveal').forEach((el) => el.classList.add('visible'));
    document.querySelectorAll('.word').forEach((el) => el.classList.add('on'));
    return;
  }

  // Abschnitte einmalig einblenden
  const revealer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) { entry.target.classList.add('visible'); revealer.unobserve(entry.target); }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll('.reveal').forEach((el) => revealer.observe(el));

  // Tagline: Wörter einzeln einfärben, sobald sie die Auslöselinie passieren
  const tagline = document.querySelector('[data-words]');
  if (tagline) {
    const words = tagline.textContent.trim().split(/\s+/);
    tagline.setAttribute('aria-label', words.join(' '));
    tagline.innerHTML = words.map((w) => `<span class="word" aria-hidden="true">${w}</span>`).join(' ');
    const spans = tagline.querySelectorAll('.word');
    if (reduced) {
      spans.forEach((s) => s.classList.add('on'));
    } else {
      const lighter = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) { entry.target.classList.add('on'); lighter.unobserve(entry.target); }
        });
      }, { rootMargin: '0px 0px -40% 0px' });
      spans.forEach((s) => lighter.observe(s));
    }
  }

  // Aktuellen Abschnitt in der Navigation markieren
  const navLinks = [...document.querySelectorAll('.nav-links a[href^="#"]')];
  const sections = navLinks.map((a) => document.querySelector(a.getAttribute('href'))).filter(Boolean);
  const marker = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      navLinks.forEach((a) => a.toggleAttribute('aria-current', a.getAttribute('href') === `#${entry.target.id}`));
      navLinks.forEach((a) => { if (a.hasAttribute('aria-current')) a.setAttribute('aria-current', 'true'); });
    });
  }, { rootMargin: '-45% 0px -50% 0px' });
  sections.forEach((s) => marker.observe(s));
})();
