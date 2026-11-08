/*
 * Page "Liste des tâches"
 *  - Onglet Diligence : blocs dépliables  ([data-diligence-toggle])
 *  - Pagination des cartes                ([data-pagination], 9 par page par défaut)
 *  - Recherche d'une tâche                (#tl-recherche) : filtre les cartes de TOUS les onglets,
 *                                          met à jour les compteurs et la pagination
 *
 * Pour changer le nombre par page sur une grille : data-pagination="12"
 */
(function () {
  'use strict';

  var PAR_PAGE_DEFAUT = 9;
  var termeAffiche = ''; // texte saisi dans la recherche ('' = pas de recherche)

  /* ---------- Outils ---------- */
  // Minuscules, sans accents, espaces simplifiés : "Koné" et "kone" se valent
  function normaliser(texte) {
    return String(texte || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase().replace(/\s+/g, ' ').trim();
  }

  // Texte interrogeable d'une carte : tout son texte + les noms des responsables (infobulles des avatars)
  function texteCarte(carte) {
    if (carte._texte === undefined) {
      var noms = Array.prototype.map.call(carte.querySelectorAll('.project-bottom [data-tooltip]'), function (n) {
        return n.getAttribute('data-tooltip');
      }).join(' ');
      carte._texte = normaliser(carte.textContent + ' ' + noms);
    }
    return carte._texte;
  }

  function cartesVisibles(zone) {
    return zone.querySelectorAll('.pg-item:not([data-masque])').length;
  }

  function cartesTotal(zone) {
    return zone.querySelectorAll('.pg-item').length;
  }

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

      // Message affiché quand la recherche ne trouve rien dans cette grille
      var aucun = document.createElement('div');
      aucun.className = 'tl-vide pg-aucun';
      aucun.style.display = 'none';
      conteneur.insertAdjacentElement('afterend', aucun);

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
        var tous = Array.from(conteneur.querySelectorAll(':scope > .pg-item'));
        // Seules les cartes qui correspondent à la recherche comptent pour la pagination
        var items = tous.filter(function (el) { return !el.hasAttribute('data-masque'); });
        tous.forEach(function (el) { if (el.hasAttribute('data-masque')) el.style.display = 'none'; });

        var total = Math.max(1, Math.ceil(items.length / parPage));
        if (page > total) page = total;
        var debut = (page - 1) * parPage;
        var fin = Math.min(debut + parPage, items.length);
        items.forEach(function (el, i) { el.style.display = (i >= debut && i < fin) ? '' : 'none'; });

        // "Aucun résultat" : seulement si une recherche est active et que cette grille avait des cartes
        if (termeAffiche && tous.length && !items.length) {
          var terme = termeAffiche.length > 40 ? termeAffiche.slice(0, 40) + '…' : termeAffiche;
          aucun.textContent = 'Aucune tâche ne correspond à « ' + terme + ' » dans cette catégorie.';
          aucun.style.display = '';
        } else {
          aucun.style.display = 'none';
        }

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
      // Carte retirée (ex. bouton Supprimer) : les pages et les compteurs se recalculent
      new MutationObserver(function () { rendre(); majCompteurs(); majInfo(); }).observe(conteneur, { childList: true });
      // La recherche a changé : retour à la première page
      conteneur.addEventListener('pg:rafraichir', function () { page = 1; rendre(); });
    });
  }

  /* ---------- Compteurs (onglets et blocs Diligence) ---------- */
  function badgesCompteurs() {
    var liste = [];
    document.querySelectorAll('[data-tabfilter]').forEach(function (lien) {
      var badge = lien.querySelector('.tl-count');
      var zone = document.querySelector('.tab-pan[data-tabcontent="' + lien.dataset.tabfilter + '"]');
      if (badge && zone) liste.push([badge, zone]);
    });
    document.querySelectorAll('.diligence-bloc').forEach(function (bloc) {
      var badge = bloc.querySelector('.diligence-toggle .tl-count');
      if (badge) liste.push([badge, bloc]);
    });
    return liste;
  }

  // Sans recherche : nombre de cartes de la zone ; avec recherche : nombre de cartes qui correspondent
  function majCompteurs() {
    badgesCompteurs().forEach(function (paire) {
      paire[0].textContent = termeAffiche ? cartesVisibles(paire[1]) : cartesTotal(paire[1]);
    });
  }

  function majInfo() {
    var info = document.getElementById('tl-recherche-info');
    if (!info) return;
    var zoneTout = document.querySelector('.tab-pan[data-tabcontent="1"]');
    if (!termeAffiche || !zoneTout) { info.textContent = ''; return; }
    var trouvees = cartesVisibles(zoneTout);
    info.textContent = trouvees + (trouvees > 1 ? ' tâches trouvées' : ' tâche trouvée') + ' sur ' + cartesTotal(zoneTout);
  }

  /* ---------- Recherche ---------- */
  function appliquerRecherche(brut) {
    termeAffiche = String(brut || '').trim();
    var mots = normaliser(termeAffiche).split(' ').filter(Boolean);

    document.querySelectorAll('[data-pagination]').forEach(function (conteneur) {
      conteneur.querySelectorAll(':scope > .pg-item').forEach(function (carte) {
        // Tous les mots saisis doivent se retrouver sur la carte (dans n'importe quel ordre)
        var correspond = mots.every(function (mot) { return texteCarte(carte).indexOf(mot) !== -1; });
        if (correspond) carte.removeAttribute('data-masque');
        else carte.setAttribute('data-masque', '');
      });
      conteneur.dispatchEvent(new Event('pg:rafraichir'));
    });

    majCompteurs();
    majInfo();
  }

  function initRecherche() {
    var champ = document.getElementById('tl-recherche');
    if (!champ) return;
    var effacer = document.getElementById('tl-recherche-effacer');
    var minuteur = null;

    function lancer(valeur) {
      appliquerRecherche(valeur);
      if (effacer) effacer.hidden = !valeur;
    }
    function vider() {
      champ.value = '';
      lancer('');
    }

    champ.addEventListener('input', function () {
      clearTimeout(minuteur);
      minuteur = setTimeout(function () { lancer(champ.value); }, 150); // évite de recalculer à chaque frappe
    });
    champ.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && champ.value) { event.preventDefault(); vider(); }
    });
    if (effacer) effacer.addEventListener('click', function () { vider(); champ.focus(); });

    // Lien direct : ...?q=TA26-001 pré-remplit la recherche
    var q = new URLSearchParams(window.location.search).get('q');
    if (q) { champ.value = q; lancer(q); }
  }

  function init() {
    initToggles();
    initPagination();
    initRecherche();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
