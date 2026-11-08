/*
 * Tableau de bord : animation des compteurs (.ed-compteur).
 * Le script du thème (counter-custom.js) n'anime pas correctement les petites valeurs (< 50 environ) :
 * ces compteurs gardent donc leur propre classe. La valeur réelle est déjà écrite dans la page ;
 * ce script ne fait que la faire monter de 0 à data-valeur.
 */
(function () {
  'use strict';

  function animer(el) {
    var cible = parseInt(el.getAttribute('data-valeur'), 10);
    if (isNaN(cible) || cible <= 0) { return; }

    var duree = 900;
    var debut = null;
    el.textContent = '0';

    function pas(t) {
      if (debut === null) { debut = t; }
      var p = Math.min((t - debut) / duree, 1);
      el.textContent = String(Math.round(cible * (1 - Math.pow(1 - p, 3)))); // ralentit vers la fin
      if (p < 1) { requestAnimationFrame(pas); } else { el.textContent = String(cible); }
    }
    requestAnimationFrame(pas);
  }

  function init() {
    // Respecte le réglage "réduire les animations" : on laisse la valeur finale telle quelle
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    document.querySelectorAll('.ed-compteur').forEach(animer);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
