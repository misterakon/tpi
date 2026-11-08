/*
 * Page "Liste des tâches"
 *  - Onglet Diligence : blocs dépliables  ([data-diligence-toggle])
 *  - Pagination des cartes                ([data-pagination], 9 par page par défaut)
 *
 * Pour changer le nombre par page sur une grille : data-pagination="12"
 */
(function () {
  'use strict';

  var PAR_PAGE_DEFAUT = 9;

  /* ---------- Blocs dépliables ---------- */
  function initToggles() {
    document.querySelectorAll('[data-diligence-toggle]').forEach(function (lien) {
      lien.addEventListener('click', function (event) {
        event.preventDefault();
        var cible = document.getElementById(lien.dataset.diligenceToggle);
        if (!cible) return;
        var ouvert = lien.getAttribute('aria-expanded') !== 'false';
        lien.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
        cible.style.display = ouvert ? 'none' : '';
      });
    });
  }

  /* ---------- Pagination ---------- */
  function pagesAffichees(page, total) {
    if (total <= 7) {
      return Array.from({ length: total }, function (_, i) { return i + 1; });
    }
    var nums = Array.from(new Set([1, total, page - 1, page, page + 1]))
      .filter(function (n) { return n >= 1 && n <= total; })
      .sort(function (a, b) { return a - b; });
    var sortie = [];
    nums.forEach(function (n, i) {
      if (i > 0 && n - nums[i - 1] > 1) sortie.push('…');
      sortie.push(n);
    });
    return sortie;
  }

  function initPagination() {
    document.querySelectorAll('[data-pagination]').forEach(function (conteneur) {
      var parPage = parseInt(conteneur.dataset.pagination, 10) || PAR_PAGE_DEFAUT;
      var page = 1;
      var nav = document.createElement('div');
      nav.className = 'pg-nav';
      conteneur.insertAdjacentElement('afterend', nav);

      function bouton(texte, cible, actif, desactive) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm ' + (actif ? 'btn-primary text-white' : 'btn-light');
        b.textContent = texte;
        if (desactive) b.disabled = true;
        else b.addEventListener('click', function () { page = cible; rendre(); });
        return b;
      }

      function rendre() {
        var items = Array.from(conteneur.querySelectorAll(':scope > .pg-item'));
        var total = Math.max(1, Math.ceil(items.length / parPage));
        if (page > total) page = total;
        var debut = (page - 1) * parPage;
        var fin = Math.min(debut + parPage, items.length);
        items.forEach(function (el, i) { el.style.display = (i >= debut && i < fin) ? '' : 'none'; });

        nav.innerHTML = '';
        if (total <= 1) return; // une seule page : pas de pagination

        var info = document.createElement('span');
        info.className = 'pg-info';
        info.textContent = (debut + 1) + '–' + fin + ' sur ' + items.length;
        nav.appendChild(info);

        var boutons = document.createElement('div');
        boutons.className = 'pg-boutons';
        boutons.appendChild(bouton('‹', page - 1, false, page === 1));
        pagesAffichees(page, total).forEach(function (n) {
          if (n === '…') {
            var points = document.createElement('span');
            points.className = 'pg-points';
            points.textContent = '…';
            boutons.appendChild(points);
          } else {
            boutons.appendChild(bouton(String(n), n, n === page, false));
          }
        });
        boutons.appendChild(bouton('›', page + 1, false, page === total));
        nav.appendChild(boutons);
      }

      rendre();
      // Carte retirée (ex. bouton Supprimer) : les pages se recalculent
      new MutationObserver(rendre).observe(conteneur, { childList: true });
    });
  }

  function init() {
    initToggles();
    initPagination();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
