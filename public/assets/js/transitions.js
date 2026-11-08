/* Chiffres des tuiles qui « comptent » jusqu'à leur valeur (tableau de bord). Aucun fondu à la sortie : la page change aussitôt, sans vide.
   Désactivé pour qui a demandé « moins d'animations ». */
(function () {
  'use strict';
  const calme = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (calme) { return; }

  // Compteurs : « 1 234 », « 82 % », « 12 j »… le nombre part de 0 et monte en 0,9 s.
  function compter(el) {
    if (el.dataset.tpCompte) { return; }
    el.dataset.tpCompte = '1';
    const m = el.textContent.match(/^(\D*?)(\d[\d\s  ]*)(.*)$/);
    if (!m) { return; }
    const cible = parseInt(m[2].replace(/[\s  ]/g, ''), 10);
    if (!isFinite(cible) || cible <= 0 || cible > 1e9) { return; }
    const debut = performance.now(), duree = 900;
    const fmt = (n) => n.toLocaleString('fr-FR');
    const pas = (t) => {
      const p = Math.min(1, (t - debut) / duree), e = 1 - Math.pow(1 - p, 3);
      el.textContent = m[1] + fmt(Math.round(cible * e)) + m[3];
      if (p < 1) { requestAnimationFrame(pas); } else { el.textContent = m[1] + m[2] + m[3]; }
    };
    el.textContent = m[1] + '0' + m[3];
    requestAnimationFrame(pas);
  }
  function balayer(noeud) {
    if (!noeud.querySelectorAll) { return; }
    noeud.querySelectorAll('.tp-anime .au-kpi__valeur > span').forEach(compter);
  }
  new MutationObserver((ms) => ms.forEach((m) => m.addedNodes.forEach(balayer))).observe(document.body, { childList: true, subtree: true });
  balayer(document);
})();
