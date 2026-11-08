/* eLouange : chargements (barre de progression des pages de connexion, boutons en cours d'envoi, formulaires).
   Chargé sur toutes les pages, connexion comprise. Aucune dépendance.
   La barre horizontale n'existe QUE sur la connexion : dans le tableau de bord, seul le chargeur par défaut du modèle s'affiche. */
(function () {
  'use strict';

  var EA = window.EA = window.EA || {};

  /* ---------- Couleur d'accent = celle du bouton principal du thème ---------- */
  (function () {
    var b = document.createElement('button');
    b.className = 'btn btn-primary';
    b.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden';
    document.body.appendChild(b);
    var c = window.getComputedStyle(b).backgroundColor;
    b.parentNode.removeChild(b);
    if (c && c !== 'transparent' && c !== 'rgba(0, 0, 0, 0)') {
      document.documentElement.style.setProperty('--ea-c', c);
    }
  })();

  /* ---------- Barre de progression ---------- */
  var barre, interieur, minuteur, valeur = 0;

  function creerBarre() {
    if (barre) { return; }
    barre = document.createElement('div');
    barre.className = 'ea-progress';
    barre.innerHTML = '<div class="ea-progress__bar"></div>';
    document.body.appendChild(barre);
    interieur = barre.firstChild;
  }

  /* La barre est réservée aux pages de connexion (la carte .login-main n'existe que dans leur gabarit). */
  function barreAutorisee() { return !!document.querySelector('.login-main'); }

  EA.progress = {
    start: function () {
      if (!barreAutorisee()) { return; }
      creerBarre();
      clearInterval(minuteur);
      valeur = 8;
      interieur.style.width = valeur + '%';
      barre.classList.add('on');
      minuteur = setInterval(function () {
        valeur += (92 - valeur) * 0.08;
        interieur.style.width = valeur + '%';
      }, 300);
    },
    done: function () {
      if (!barre) { return; }
      clearInterval(minuteur);
      interieur.style.width = '100%';
      setTimeout(function () {
        barre.classList.remove('on');
        setTimeout(function () { interieur.style.width = '0'; }, 300);
      }, 220);
    }
  };

  /* ---------- Bouton en cours d'envoi ---------- */
  EA.setLoading = function (bouton, texte) {
    if (!bouton || bouton.dataset.eaOriginal !== undefined) { return; }
    bouton.dataset.eaOriginal = bouton.innerHTML;
    bouton.style.minWidth = bouton.offsetWidth + 'px';
    bouton.classList.add('ea-loading');
    bouton.setAttribute('aria-busy', 'true');
    bouton.innerHTML = '<span class="ea-spinner" aria-hidden="true"></span>' + (texte || 'Veuillez patienter…');
  };

  EA.clearLoading = function (bouton) {
    if (!bouton || bouton.dataset.eaOriginal === undefined) { return; }
    bouton.innerHTML = bouton.dataset.eaOriginal;
    delete bouton.dataset.eaOriginal;
    bouton.style.minWidth = '';
    bouton.classList.remove('ea-loading');
    bouton.removeAttribute('aria-busy');
  };

  function toutReinitialiser() {
    EA.progress.done();
    document.querySelectorAll('.ea-loading').forEach(EA.clearLoading);
    document.querySelectorAll('form[data-ea-busy]').forEach(function (f) { delete f.dataset.eaBusy; });
  }

  /* ---------- Formulaires classiques (modals, filtres, réglages…) ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-ajax') || form.hasAttribute('data-no-loading')) { return; }
    if (form.target && form.target !== '_self') { return; }

    if (form.dataset.eaBusy) { e.preventDefault(); return; }   // double clic

    setTimeout(function () {
      if (e.defaultPrevented) { return; }                      // un autre script a annulé l'envoi
      form.dataset.eaBusy = '1';
      var bouton = e.submitter || form.querySelector('[type="submit"]');
      EA.setLoading(bouton, bouton && bouton.dataset.btnLoading);
      EA.progress.start();
    }, 0);
  });

  /* ---------- Liens : la barre démarre avant le changement de page ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-swap') || a.hasAttribute('data-no-loading') || a.getAttribute('onclick')) { return; }

    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) { return; }
    if (a.origin !== window.location.origin) { return; }
    if (a.pathname === window.location.pathname && a.search === window.location.search) { return; }

    EA.progress.start();
  });

  /* Retour arrière : la page revient du cache avec ses boutons figés */
  window.addEventListener('pageshow', function (e) { if (e.persisted) { toutReinitialiser(); } });
})();
