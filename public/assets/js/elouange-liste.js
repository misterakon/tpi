/* eLouange : listes dynamiques (filtres, recherche, pages) sans recharger la page.
 *
 * Le serveur reste maître : il renvoie des morceaux de page déjà prêts (JSON : une entrée par zone).
 * L'adresse du navigateur est mise à jour à chaque changement : le bouton « précédent », le rechargement
 * et le partage d'un lien gardent exactement les mêmes filtres et la même page.
 *
 *   ElListe.init({ form: '#form-filtres', zones: { haut: '#zone-haut', tableau: '#zone-tableau' }, defiler: 'tableau' });
 *
 * Dans le formulaire :
 *   [data-set='{"du":"2026-10-01","au":""}']  bouton qui fixe des champs (l'état actif suit les valeurs)
 *   [data-effacer]                            bouton qui vide tous les filtres (caché quand il n'y a aucun filtre)
 *   formulaire avec data-ajax                 pour que le chargeur global ne s'en mêle pas
 */
(function () {
  'use strict';

  function init(options) {
    var form = document.querySelector(options.form);
    if (!form) { return; }

    var zones = options.zones || {};
    var base = options.url || form.getAttribute('action');
    var delai = options.delai || 350;
    var minuteur = null;
    var controleur = null;
    var compteur = 0;

    function zone(cle) { return document.querySelector(zones[cle]); }
    function cles() { return Object.keys(zones); }
    function champs() { return Array.prototype.filter.call(form.elements, function (el) { return el.name && el.type !== 'button' && el.type !== 'submit'; }); }

    /* ---------- Formulaire <-> adresse ---------- */

    function parametres() {
      var p = new URLSearchParams();
      new FormData(form).forEach(function (valeur, nom) {
        valeur = String(valeur).trim();
        if (valeur !== '') { p.set(nom, valeur); }
      });
      return p;
    }

    function remplir(params) {
      champs().forEach(function (el) { el.value = params.get(el.name) || ''; });
    }

    function majEtat() {
      document.querySelectorAll('[data-set]').forEach(function (bouton) {
        var fixe = {};
        try { fixe = JSON.parse(bouton.getAttribute('data-set')); } catch (e) { return; }
        var actif = Object.keys(fixe).every(function (nom) {
          var champ = form.elements[nom];
          return champ && String(champ.value) === String(fixe[nom]);
        });
        bouton.classList.toggle('is-active', actif);
      });

      var effacer = form.querySelector('[data-effacer]');
      if (effacer) { effacer.hidden = parametres().toString() === ''; }
    }

    /* ---------- Chargement ---------- */

    function enChargement(oui) {
      cles().forEach(function (cle) {
        var z = zone(cle);
        if (z) { z.classList.toggle('is-loading', oui); z.setAttribute('aria-busy', oui ? 'true' : 'false'); }
      });
    }

    function erreur(message) {
      if (window.Swal) { Swal.fire({ icon: 'error', title: 'Erreur', text: message || 'Impossible de charger la liste. Réessayez.' }); }
      else { window.alert(message || 'Impossible de charger la liste.'); }
    }

    function charger(params, reglages) {
      reglages = reglages || {};
      var adresse = base + (params.toString() ? '?' + params.toString() : '');

      if (controleur) { controleur.abort(); }
      controleur = new AbortController();
      var moi = ++compteur;

      enChargement(true);
      if (window.EA && EA.progress) { EA.progress.start(); }

      fetch(adresse, { signal: controleur.signal, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) {
          if (r.ok) { return r.json(); }
          return r.json().catch(function () { return {}; }).then(function (e) {
            var detail = e && e.errors ? Object.keys(e.errors).map(function (k) { return e.errors[k][0]; }).join(' ') : (e && e.message);
            throw new Error(detail || ('Erreur ' + r.status));
          });
        })
        .then(function (donnees) {
          if (moi !== compteur) { return; }
          cles().forEach(function (cle) { var z = zone(cle); if (z && donnees[cle] !== undefined) { z.innerHTML = donnees[cle]; } });
          if (reglages.pousser !== false) { window.history.pushState({ liste: true }, '', adresse); }
          majEtat();
          if (reglages.defiler && options.defiler) {
            var cible = zone(options.defiler);
            if (cible && cible.getBoundingClientRect().top < 0) { cible.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
          }
        })
        .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (moi === compteur) { erreur(e && e.message); } })
        .then(function () {
          if (moi !== compteur) { return; }
          enChargement(false);
          if (window.EA && EA.progress) { EA.progress.done(); }
        });
    }

    /* ---------- Événements ---------- */

    form.addEventListener('submit', function (e) { e.preventDefault(); window.clearTimeout(minuteur); charger(parametres()); });

    // Recherche : on attend la fin de la frappe, avec au moins 2 lettres (ou le champ vide pour tout revoir)
    form.addEventListener('input', function (e) {
      if (!e.target.matches('input[type="search"], input[type="text"]')) { return; }
      window.clearTimeout(minuteur);
      if (e.target.value.trim().length === 1) { return; }
      minuteur = window.setTimeout(function () { charger(parametres()); }, delai);
    });

    // Listes déroulantes et dates : tout de suite
    form.addEventListener('change', function (e) {
      if (e.target.matches('input[type="search"], input[type="text"]')) { return; }
      charger(parametres());
    });

    // Capture : on passe avant le chargeur global des liens, qui verrait sinon une navigation qui n'arrive jamais
    document.addEventListener('click', function (e) {
      var fixe = e.target.closest('[data-set]');
      if (fixe) {
        var valeurs = {};
        try { valeurs = JSON.parse(fixe.getAttribute('data-set')); } catch (err) { return; }
        e.preventDefault(); e.stopPropagation();
        Object.keys(valeurs).forEach(function (nom) { if (form.elements[nom]) { form.elements[nom].value = valeurs[nom]; } });
        charger(parametres());
        return;
      }

      var effacer = e.target.closest('[data-effacer]');
      if (effacer) {
        e.preventDefault(); e.stopPropagation();
        champs().forEach(function (el) { el.value = ''; });
        charger(parametres());
        return;
      }

      var lien = e.target.closest('.el-pagination__pages a');
      if (lien && cles().some(function (cle) { var z = zone(cle); return z && z.contains(lien); })) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) { return; }   // ouvrir dans un nouvel onglet reste possible
        e.preventDefault(); e.stopPropagation();
        var params = new URL(lien.href, window.location.origin).searchParams;
        remplir(params);
        charger(params, { defiler: true });
      }
    }, true);

    // Bouton « précédent » / « suivant » du navigateur
    window.addEventListener('popstate', function () {
      var params = new URLSearchParams(window.location.search);
      remplir(params);
      charger(params, { pousser: false });
    });

    majEtat();
  }

  window.ElListe = { init: init };
})();
