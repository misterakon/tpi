/* eLouange : écrans de connexion sans rechargement de page.
   - les formulaires [data-ajax] s'envoient par fetch (réponse JSON du serveur) ;
   - voile de chargement, animation de succès, erreurs sous les champs ;
   - passage d'une étape à l'autre dans la même carte (connexion -> code) ;
   - saisie du code en 6 cases ; compte à rebours du renvoi. */
(function () {
  'use strict';

  var EA = window.EA = window.EA || {};
  var PAGES_CARTE = /\/(login|two-factor|forgot-password|reset-password)(\/|$)/;

  function attendre(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  function carte() { return document.querySelector('.login-main'); }

  /* ---------- Voile de chargement ---------- */
  var voile = {
    minuteur: null,
    element: function () {
      var c = carte();
      if (!c) { return null; }
      var v = c.querySelector('.ea-veil');
      if (!v) {
        v = document.createElement('div');
        v.className = 'ea-veil';
        v.setAttribute('role', 'status');
        v.setAttribute('aria-live', 'polite');
        v.innerHTML = '<div class="ea-spin" aria-hidden="true"><svg viewBox="0 0 100 100">' +
          '<circle class="ea-spin__track" cx="50" cy="50" r="42"/>' +
          '<circle class="ea-spin__arc" cx="50" cy="50" r="42"/>' +
          '<circle class="ea-spin__arc2" cx="50" cy="50" r="30"/></svg><span class="ea-spin__core"></span></div>' +
          '<svg class="ea-check" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="3"/>' +
          '<path fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" d="M14 27l8 8 16-17"/></svg>' +
          '<p class="ea-veil__text"></p>' +
          '<span class="ea-line" aria-hidden="true"></span>';
        c.appendChild(v);
      }
      return v;
    },
    afficher: function (texte, texte2) {
      var v = this.element(); if (!v) { return; }
      var t = v.querySelector('.ea-veil__text');
      clearTimeout(this.minuteur);
      v.classList.remove('ok');
      t.textContent = texte;
      void v.offsetWidth;
      v.classList.add('on');
      if (texte2) { this.minuteur = setTimeout(function () { t.textContent = texte2; }, 2600); }
    },
    succes: function (texte) {
      var v = this.element(); if (!v) { return; }
      clearTimeout(this.minuteur);
      v.querySelector('.ea-veil__text').textContent = texte;
      v.classList.add('on', 'ok');
    },
    masquer: function () {
      var c = carte(), v = c && c.querySelector('.ea-veil');
      clearTimeout(this.minuteur);
      if (v) { v.classList.remove('on', 'ok'); }
    }
  };

  /* ---------- Erreurs et messages ---------- */
  function effacerErreurs(form) {
    form.querySelectorAll('.ea-error').forEach(function (n) { n.remove(); });
    form.querySelectorAll('.is-invalid').forEach(function (n) { n.classList.remove('is-invalid'); });
    form.querySelectorAll('.ea-otp.invalid').forEach(function (n) { n.classList.remove('invalid'); });
    var c = carte(); if (c) { c.querySelectorAll('.ea-notice').forEach(function (n) { n.remove(); }); }
  }

  function erreurChamp(form, nom, message) {
    var champ = form.querySelector('[name="' + nom + '"]');
    var groupe = champ ? (champ.closest('.form-group') || champ.parentNode) : null;
    if (!champ || !groupe) { notice(form, message, 'error'); return; }

    var otp = champ.closest('[data-otp]') || groupe.querySelector('[data-otp]');
    if (otp) { otp.classList.add('invalid'); } else { champ.classList.add('is-invalid'); }

    var span = document.createElement('span');
    span.className = 'ea-error' + (otp ? ' ea-error--center' : '');
    span.textContent = message;
    groupe.appendChild(span);
  }

  function notice(form, message, type) {
    var c = carte(); if (!c) { return; }
    c.querySelectorAll('.ea-notice').forEach(function (n) { n.remove(); });
    var div = document.createElement('div');
    div.className = 'ea-notice' + (type === 'error' ? ' ea-notice--error' : '');
    div.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var icone = document.createElement('i');
    icone.className = type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check';
    var texte = document.createElement('span');
    texte.textContent = message;
    div.appendChild(icone); div.appendChild(texte);
    c.insertBefore(div, c.firstChild);
  }

  function secouer() {
    var c = carte(); if (!c) { return; }
    c.classList.remove('ea-shake'); void c.offsetWidth; c.classList.add('ea-shake');
    setTimeout(function () { c.classList.remove('ea-shake'); }, 450);
  }

  function lecture(form, verrou) {
    form.querySelectorAll('input:not([type="hidden"])').forEach(function (i) { i.readOnly = verrou; });
  }

  /* ---------- Changement d'étape dans la même carte ---------- */
  async function aller(url, empiler) {
    var cible = new URL(url, window.location.href);
    if (cible.origin !== window.location.origin || !PAGES_CARTE.test(cible.pathname)) {
      EA.progress.start();
      window.location.href = cible.href;
      return;
    }
    await echanger(cible.href, empiler !== false);
  }

  async function echanger(url, empiler) {
    var c = carte();
    if (!c) { window.location.href = url; return; }

    EA.progress.start();
    c.classList.add('ea-leave');

    var reponse, html;
    try {
      reponse = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
      html = await reponse.text();
    } catch (e) { window.location.href = url; return; }

    var doc = new DOMParser().parseFromString(html, 'text/html');
    var nouvelle = doc.querySelector('.login-main');
    if (!nouvelle) { window.location.href = reponse.url || url; return; }

    await attendre(180);
    c.innerHTML = nouvelle.innerHTML;
    document.title = doc.title;
    c.classList.remove('ea-leave');
    c.classList.add('ea-enter');
    setTimeout(function () { c.classList.remove('ea-enter'); }, 450);
    if (empiler) { window.history.pushState({ ea: 1 }, '', reponse.url || url); }

    EA.progress.done();
    initialiser(c, true);
  }

  window.addEventListener('popstate', function () {
    if (PAGES_CARTE.test(window.location.pathname) && carte()) { echanger(window.location.href, false); }
    else { window.location.reload(); }
  });

  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-swap]') : null;
    if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    e.preventDefault();
    echanger(a.href, true);
  });

  /* ---------- « Je ne reçois pas le code » : affiche ou masque la saisie d'un code de secours ----------
     Délégation sur le document : la page du code arrive par échange AJAX, ses scripts en ligne ne s'exécutent pas. */
  document.addEventListener('click', function (e) {
    var lien = e.target.closest ? e.target.closest('#lien-secours') : null;
    if (!lien) { return; }
    e.preventDefault();
    var bloc = document.getElementById('bloc-secours');
    if (!bloc) { return; }
    var ouvrir = bloc.hasAttribute('hidden');
    if (ouvrir) { bloc.removeAttribute('hidden'); var champ = document.getElementById('code-secours'); if (champ) { champ.focus(); } } else { bloc.setAttribute('hidden', ''); }
    lien.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
  });

  /* ---------- Envoi d'un formulaire en AJAX ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-ajax')) { return; }
    e.preventDefault();
    envoyer(form);
  });

  async function envoyer(form) {
    if (form.dataset.eaBusy) { return; }
    effacerErreurs(form);

    var codeChamp = form.querySelector('[data-otp] input[name="code"]');
    if (codeChamp && codeChamp.value.length < 6) {
      erreurChamp(form, 'code', 'Saisissez les 6 chiffres du code.');
      secouer();
      return;
    }

    form.dataset.eaBusy = '1';
    var bouton = form.querySelector('[type="submit"]');
    var avecVoile = form.dataset.veil !== 'off';
    var donnees = new FormData(form);       // lu avant de verrouiller les champs
    var navigue = false;
    var attenteRenvoi = null;

    EA.setLoading(bouton, form.dataset.btnLoading);
    EA.progress.start();
    lecture(form, true);
    if (avecVoile) { voile.afficher(form.dataset.loading || 'Chargement…', form.dataset.loading2); }

    try {
      var reponse = await fetch(form.action, {
        method: 'POST', body: donnees, credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      var json = null;
      try { json = await reponse.json(); } catch (_) { /* réponse sans JSON */ }

      if (reponse.ok) {
        if (json && json.redirect) {
          navigue = true;
          if (avecVoile) { voile.succes(form.dataset.success || 'C\u2019est bon'); await attendre(form.dataset.success ? 750 : 300); }
          await aller(json.redirect);
          return;
        }
        voile.masquer();
        if (json && json.message) { notice(form, json.message, 'success'); }
        if (json && json.attente != null) { attenteRenvoi = json.attente; }
        return;
      }

      voile.masquer();

      if (reponse.status === 422 && json && json.errors) {
        var premier = null;
        Object.keys(json.errors).forEach(function (nom) {
          erreurChamp(form, nom, json.errors[nom][0]);
          premier = premier || nom;
        });
        secouer();
        if (form.querySelector('[data-otp]')) { videCode(form); }
        else {
          var champ = form.querySelector('[name="' + premier + '"]');
          if (champ) { if (champ.type === 'password') { champ.value = ''; } champ.focus(); }
        }
      } else if (reponse.status === 419) {
        notice(form, 'Votre session a expiré. La page va se recharger…', 'error');
        setTimeout(function () { window.location.reload(); }, 1600);
      } else {
        notice(form, (json && json.message) || 'Une erreur est survenue. Réessayez dans un instant.', 'error');
        secouer();
      }
    } catch (err) {
      voile.masquer();
      notice(form, 'Connexion impossible. Vérifiez votre réseau puis réessayez.', 'error');
      secouer();
    } finally {
      if (!navigue) {
        EA.progress.done();
        EA.clearLoading(bouton);
        lecture(form, false);
        delete form.dataset.eaBusy;
        if (attenteRenvoi !== null && bouton) { demarrerCompteARebours(bouton, attenteRenvoi); }
      }
    }
  }

  /* ---------- Saisie du code en 6 cases ---------- */
  function videCode(form) {
    var boite = form.querySelector('[data-otp]'); if (!boite) { return; }
    boite.querySelectorAll('input[data-otp-digit]').forEach(function (i) { i.value = ''; i.classList.remove('filled'); });
    boite.querySelector('input[name="code"]').value = '';
    boite.querySelector('input[data-otp-digit]').focus();
  }

  function initialiserCode(racine) {
    racine.querySelectorAll('[data-otp]').forEach(function (boite) {
      var cases = Array.prototype.slice.call(boite.querySelectorAll('input[data-otp-digit]'));
      var cache = boite.querySelector('input[name="code"]');
      var form = boite.closest('form');

      function synchroniser() {
        cache.value = cases.map(function (c) { return c.value; }).join('');
        cases.forEach(function (c) { c.classList.toggle('filled', c.value !== ''); });
      }
      function envoiAuto() {
        if (cache.value.length === cases.length && form.hasAttribute('data-autosubmit')) {
          if (form.requestSubmit) { form.requestSubmit(); }
          else { form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true })); }
        }
      }
      function remplir(depart, chiffres) {
        chiffres.split('').slice(0, cases.length - depart).forEach(function (d, k) { cases[depart + k].value = d; });
        synchroniser();
        cases[Math.min(depart + chiffres.length, cases.length - 1)].focus();
        envoiAuto();
      }

      cases.forEach(function (c, i) {
        c.addEventListener('input', function () {
          var v = c.value.replace(/\D/g, '');
          if (v.length > 1) { remplir(i, v); return; }       // collage, ou remplissage automatique du téléphone
          c.value = v;
          synchroniser();
          if (v && cases[i + 1]) { cases[i + 1].focus(); }
          envoiAuto();
        });
        c.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !c.value && cases[i - 1]) { e.preventDefault(); cases[i - 1].value = ''; cases[i - 1].focus(); synchroniser(); }
          else if (e.key === 'ArrowLeft' && cases[i - 1]) { e.preventDefault(); cases[i - 1].focus(); }
          else if (e.key === 'ArrowRight' && cases[i + 1]) { e.preventDefault(); cases[i + 1].focus(); }
        });
        c.addEventListener('paste', function (e) {
          e.preventDefault();
          var texte = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '');
          if (texte) { remplir(0, texte); }
        });
        c.addEventListener('focus', function () { c.select(); });
      });
    });
  }

  /* ---------- Compte à rebours du renvoi ---------- */
  function demarrerCompteARebours(bouton, secondes) {
    var etiquette = bouton.querySelector('[data-countdown-label]');
    clearInterval(bouton._ea);
    var reste = Math.max(0, parseInt(secondes, 10) || 0);

    if (reste === 0) { bouton.disabled = false; if (etiquette) { etiquette.textContent = ''; } return; }

    bouton.disabled = true;
    if (etiquette) { etiquette.textContent = ' (' + reste + ' s)'; }
    bouton._ea = setInterval(function () {
      reste--;
      var e = bouton.querySelector('[data-countdown-label]');
      if (reste <= 0) { clearInterval(bouton._ea); bouton.disabled = false; if (e) { e.textContent = ''; } }
      else if (e) { e.textContent = ' (' + reste + ' s)'; }
    }, 1000);
  }

  /* ---------- Afficher / masquer le mot de passe (contenu inséré par le script) ---------- */
  function initialiserMotDePasse(racine, insere) {
    if (!insere) { return; }                    // au chargement, le thème s'en occupe déjà
    racine.querySelectorAll('.show-hide span').forEach(function (s) {
      s.addEventListener('click', function () {
        var champ = s.closest('.form-input').querySelector('input');
        champ.type = champ.type === 'password' ? 'text' : 'password';
        s.classList.toggle('show');
      });
    });
  }

  /* ---------- Initialisation (page chargée ou étape insérée) ---------- */
  function initialiser(racine, insere) {
    initialiserCode(racine);
    initialiserMotDePasse(racine, insere);
    racine.querySelectorAll('[data-countdown]').forEach(function (b) { demarrerCompteARebours(b, b.getAttribute('data-attente')); });

    var premier = racine.querySelector('[data-otp-digit]') || racine.querySelector('input[autofocus]') || racine.querySelector('input:not([type="hidden"])');
    if (premier && insere) { premier.focus(); }

    // Les erreurs rendues par le serveur (page rechargée normalement) : même secousse
    if (racine.querySelector('.ea-error') && !insere) { secouer(); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var c = carte();
    if (!c) { return; }
    c.classList.add('ea-enter');
    setTimeout(function () { c.classList.remove('ea-enter'); }, 500);
    initialiser(c, false);
  });
})();
