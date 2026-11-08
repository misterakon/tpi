{{-- Panneau d'aide « ? » (tiroir à droite), disponible sur toutes les pages. Ouvre l'aide de la page courante ; F1 l'ouvre, Échap le ferme. --}}
<div class="aide-fond" id="aide-fond"></div>
<aside class="aide-tiroir aide" id="aide-tiroir" aria-hidden="true" aria-label="Aide"
  data-page="{{ request()->route()?->getName() }}" data-contexte="{{ route('aide.contexte') }}"
  data-recherche="{{ route('aide.recherche') }}">
  <div class="aide-tiroir__tete"><i class="fa-solid fa-life-ring" style="font-size:24px"></i>
    <h3 id="aide-tiroir-titre">Aide<small>Aide de la page en cours</small></h3><button type="button"
      class="aide-tiroir__x" id="aide-fermer" aria-label="Fermer l'aide"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="aide-tiroir__recherche"><i class="fa-solid fa-magnifying-glass"></i><input type="search"
      id="aide-tiroir-q" placeholder="Chercher dans l'aide…" autocomplete="off" aria-label="Chercher dans l'aide"></div>
  <div class="aide-tiroir__corps" id="aide-tiroir-corps">
    <div class="aide-vide"><i class="fa-solid fa-spinner fa-spin"></i></div>
  </div>
  <div class="aide-tiroir__pied"><a class="aide-btn aide-btn--plein" id="aide-tiroir-lien"
      href="{{ route('aide.index') }}"><i class="fa-solid fa-book-open"></i> Ouvrir le chapitre</a><a class="aide-btn"
      href="{{ route('aide.index') }}"><i class="fa-solid fa-list"></i> Sommaire</a></div>
</aside>
<script>
(function () {
  'use strict';
  const t = document.getElementById('aide-tiroir'), fond = document.getElementById('aide-fond'), corps = document.getElementById('aide-tiroir-corps'), q = document.getElementById('aide-tiroir-q');
  if (!t) { return; }
  let charge = false, accueil = '', minuteur = null, n = 0;
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function ouvrir() {
    t.classList.add('is-ouvert'); fond.classList.add('is-ouvert'); t.setAttribute('aria-hidden', 'false');
    if (!charge) {
      fetch(t.dataset.contexte + '?page=' + encodeURIComponent(t.dataset.page || ''), { credentials: 'same-origin' }).then(r => r.text()).then(h => {
        corps.innerHTML = h; accueil = h; charge = true;
        const c = corps.firstElementChild;
        if (c && c.dataset.titre) { document.getElementById('aide-tiroir-titre').innerHTML = esc(c.dataset.titre) + '<small>Aide de la page en cours</small>'; document.getElementById('aide-tiroir-lien').href = c.dataset.lien; }
      }).catch(() => { corps.innerHTML = '<div class="aide-vide">Impossible de charger l\'aide. Réessayez.</div>'; });
    }
    setTimeout(() => q.focus(), 350);
  }
  function fermer() { t.classList.remove('is-ouvert'); fond.classList.remove('is-ouvert'); t.setAttribute('aria-hidden', 'true'); }
  window.TPIAide = { ouvrir: ouvrir, fermer: fermer };
  // Captures annotées : les épingles apparaissent une à une quand la capture entre à l'écran ; survoler la légende fait sauter l'épingle.
  (function () {
    const figs = document.querySelectorAll('.aide-capture');
    if (!figs.length) { return; }
    if ('IntersectionObserver' in window) {
      const o = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('est-visible'); o.unobserve(e.target); } }), { threshold: .35 });
      figs.forEach(f => o.observe(f));
    } else { figs.forEach(f => f.classList.add('est-visible')); }
    figs.forEach(f => f.querySelectorAll('.aide-legende li').forEach(li => {
      const m = () => f.querySelector('.aide-marque[data-n="' + li.dataset.n + '"]');
      li.addEventListener('mouseenter', () => { const x = m(); if (x) { x.classList.add('is-eclair'); } });
      li.addEventListener('mouseleave', () => { const x = m(); if (x) { x.classList.remove('is-eclair'); } });
    }));
  })();
  // Capture d'écran d'un chapitre : un clic l'agrandit, un autre la ferme.
  document.addEventListener('click', e => {
    const img = e.target.closest('.aide-capture img');
    if (img) { const z = document.createElement('div'); z.className = 'aide-zoom'; z.innerHTML = '<img alt="">'; z.firstChild.src = img.src; z.addEventListener('click', () => z.remove()); document.body.appendChild(z); return; }
  });
  document.addEventListener('click', e => { if (e.target.closest('#aide-ouvrir, [data-aide-ouvrir]')) { e.preventDefault(); ouvrir(); } });
  document.getElementById('aide-fermer').addEventListener('click', fermer);
  fond.addEventListener('click', fermer);
  document.addEventListener('keydown', e => {
    if (e.key === 'F1') { e.preventDefault(); t.classList.contains('is-ouvert') ? fermer() : ouvrir(); }
    if (e.key === 'Escape' && t.classList.contains('is-ouvert')) { fermer(); }
  });
  q.addEventListener('input', () => {
    clearTimeout(minuteur);
    minuteur = setTimeout(() => {
      const v = q.value.trim();
      if (v.length < 2) { corps.innerHTML = accueil; return; }
      const k = ++n;
      fetch(t.dataset.recherche + '?q=' + encodeURIComponent(v), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(j => {
        if (k !== n) { return; }
        corps.innerHTML = j.resultats.length ? '<div class="aide-liste-chap">' + j.resultats.map(r => '<a class="aide-c-' + esc(r.couleur) + '" href="' + esc(r.url) + '"><span class="aide-resultat__ico"><i class="' + esc(r.icone) + '"></i></span><div><em style="font-style:normal;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--c)">' + esc(r.chapitre) + '</em><b style="display:block">' + esc(r.section) + '</b><small style="display:block;font-size:12.5px;opacity:.85">' + r.extrait + '</small></div></a>').join('') + '</div>'
          : '<div class="aide-vide"><i class="fa-regular fa-face-frown" style="font-size:26px"></i><p>Aucun résultat pour « ' + esc(v) + ' ».</p></div>';
      });
    }, 220);
  });
})();
</script>
