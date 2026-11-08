/* eLouange : pages publiques sans rechargement (paiement, retour de paiement, suppression de compte).
   Aucune dépendance. Les adresses viennent des attributs data-* de la page (aucun script en ligne). */
(function () {
  'use strict';

  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = meta ? meta.getAttribute('content') : '';

  function q(sel, ctx) { return (ctx || document).querySelector(sel); }
  function all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  function appeler(methode, url, data) {
    var options = {
      method: methode,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }
    };
    if (data) { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(data); }
    return fetch(url, options).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, ok: r.ok, json: j }; });
    });
  }
  function post(url, data) { return appeler('POST', url, data || {}); }

  function charger(bouton, actif) {
    if (!bouton) { return; }
    bouton.classList.toggle('is-loading', actif);
    bouton.disabled = actif;
  }

  function message(zone, texte, type) {
    if (!zone) { return; }
    zone.textContent = texte || '';
    zone.hidden = !texte;
    zone.className = 'notice notice--' + (type || 'error');
  }

  function erreurChamp(nom, texte, ctx) {
    var p = q('[data-err="' + nom + '"]', ctx);
    if (p) { p.textContent = texte || ''; }
    var champ = q('[name="' + nom + '"]', ctx);
    if (champ && champ.closest('.input')) { champ.closest('.input').classList.toggle('is-invalid', !!texte); }
  }

  function effacerErreurs(ctx) {
    all('[data-err]', ctx).forEach(function (p) { p.textContent = ''; });
    all('.input.is-invalid', ctx).forEach(function (i) { i.classList.remove('is-invalid'); });
  }

  /* ---------- Formatage des champs ---------- */
  function formaterTelephone(champ) {
    champ.addEventListener('input', function () {
      var d = champ.value.replace(/\D/g, '');
      if (d.indexOf('225') === 0 && d.length > 10) { d = d.slice(3); }
      d = d.slice(0, 10);
      champ.value = d.replace(/(\d{2})(?=\d)/g, '$1 ');
    });
  }
  function formaterCode(champ) {
    champ.addEventListener('input', function () {
      var c = champ.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
      champ.value = c.length > 5 ? c.slice(0, 5) + '-' + c.slice(5) : c;
    });
  }
  function verifierSaisie(form) {
    var tel = form.elements.telephone.value.replace(/\D/g, '');
    var code = form.elements.code.value.replace(/[^A-Za-z0-9]/g, '');
    var ok = true;
    effacerErreurs(form);
    if (tel.length < 8) { erreurChamp('telephone', 'Saisissez votre numéro de téléphone.', form); ok = false; }
    if (code.length !== 10) { erreurChamp('code', 'Le code unique contient 10 caractères.', form); ok = false; }
    return ok;
  }
  function erreursServeur(form, json) {
    var e = json && json.errors ? json.errors : null;
    if (!e) { return false; }
    Object.keys(e).forEach(function (nom) { erreurChamp(nom, e[nom][0], form); });
    return true;
  }
  function montant(n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/[\u202f\u00a0]/g, ' '); }

  /* ---------- Étapes ---------- */
  function etapes(racine) {
    var liste = {};
    all('[data-step]', racine).forEach(function (s) { liste[s.getAttribute('data-step')] = s; });
    var rang = { id: 0, recap: 1, wait: 2, done: 3, fail: 2, info: 0 };

    return function afficher(nom) {
      Object.keys(liste).forEach(function (k) {
        liste[k].hidden = k !== nom;
        if (k === nom) { liste[k].classList.remove('enter'); void liste[k].offsetWidth; liste[k].classList.add('enter'); }
      });
      var idx = rang[nom] === undefined ? 0 : rang[nom];
      all('.stepper li', racine).forEach(function (li, i) {
        li.classList.toggle('is-done', i < idx || nom === 'done');
        li.classList.toggle('is-active', i === idx && nom !== 'done');
      });
      var cible = q('input, button, h2', liste[nom]);
      if (cible) { setTimeout(function () { try { cible.focus({ preventScroll: true }); } catch (e) { /* sans effet */ } }, 60); }
    };
  }

  /* ===================================================================
     Page de paiement
     =================================================================== */
  function pagePaiement(racine) {
    var url = {
      verifier: racine.getAttribute('data-url-verifier'),
      initier: racine.getAttribute('data-url-initier'),
      statut: racine.getAttribute('data-url-statut')
    };
    var afficher = etapes(racine);
    var form = q('[data-form="verifier"]', racine);
    var etat = { billet: null, ref: null, url: null, minuteur: null, fenetre: null, debut: 0 };
    var canal = 'BroadcastChannel' in window ? new BroadcastChannel('elouange-pay') : null;

    formaterTelephone(form.elements.telephone);
    formaterCode(form.elements.code);

    if (racine.getAttribute('data-closed') === '1') {
      q('[data-bind="info-texte"]', racine).textContent = racine.getAttribute('data-closed-message') || 'Le paiement est momentanément indisponible.';
      q('[data-action="info-retour"]', racine).hidden = true;
      afficher('info');
      return;
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var zone = q('[data-notice]', form);
      var bouton = q('button[type="submit"]', form);
      message(zone, '');
      if (!verifierSaisie(form)) { return; }

      charger(bouton, true);
      post(url.verifier, { telephone: form.elements.telephone.value, code: form.elements.code.value, website: form.elements.website.value }).then(function (r) {
        charger(bouton, false);

        if (r.status === 422 && erreursServeur(form, r.json)) { return; }
        if (!r.ok) { message(zone, r.json.message || 'Vérification impossible. Réessayez dans un instant.'); return; }

        var j = r.json;
        if (j.etat === 'a_payer') {
          etat.billet = j.billet;
          q('[data-bind="nom"]', racine).textContent = j.nom;
          q('[data-bind="initiale"]', racine).textContent = (j.nom || '?').charAt(0);
          all('[data-bind="montant"]', racine).forEach(function (n) { n.textContent = montant(j.montant); });
          message(q('[data-notice]', q('[data-step="recap"]', racine)), '');
          afficher('recap');
        } else {
          q('[data-bind="info-texte"]', racine).textContent = j.message || '';
          q('[data-action="info-retour"]', racine).hidden = false;
          afficher('info');
        }
      }).catch(function () {
        charger(bouton, false);
        message(zone, 'Connexion impossible. Vérifiez votre réseau puis réessayez.');
      });
    });

    racine.addEventListener('click', function (e) {
      var cible = e.target.closest('[data-action]');
      if (!cible) { return; }
      var action = cible.getAttribute('data-action');

      if (action === 'payer') { payer(cible); }
      else if (action === 'changer' || action === 'info-retour') { arreter(); etat.billet = null; form.reset(); effacerErreurs(form); afficher('id'); }
      else if (action === 'reessayer') { afficher('recap'); }
      else if (action === 'verifier-maintenant') { interroger(); }
    });

    function payer(bouton) {
      var zone = q('[data-notice]', q('[data-step="recap"]', racine));
      message(zone, '');

      // La fenêtre doit s'ouvrir tout de suite, pendant le clic, sinon le navigateur la bloque.
      var fenetre = window.open('', 'cinetpay', 'popup=yes,width=480,height=780');
      etat.fenetre = fenetre;
      charger(bouton, true);

      post(url.initier, { billet: etat.billet }).then(function (r) {
        charger(bouton, false);

        if (!r.ok) {
          if (fenetre) { fenetre.close(); }
          if (r.json && r.json.recommencer) { arreter(); etat.billet = null; message(q('[data-notice]', form), r.json.message, 'info'); afficher('id'); return; }
          message(zone, (r.json && r.json.message) || 'Le paiement ne peut pas démarrer. Réessayez dans un instant.');
          return;
        }

        etat.ref = r.json.ref;
        etat.url = r.json.url;
        var lien = q('[data-link-payer]', racine);
        lien.setAttribute('href', etat.url);
        q('[data-bind="popup-bloquee"]', racine).hidden = !!fenetre;

        if (fenetre) { try { fenetre.location.href = etat.url; } catch (err) { /* sans effet */ } }

        afficher('wait');
        demarrer();
      }).catch(function () {
        charger(bouton, false);
        if (fenetre) { fenetre.close(); }
        message(zone, 'Connexion impossible. Vérifiez votre réseau puis réessayez.');
      });
    }

    function interroger() {
      if (!etat.ref) { return; }
      appeler('GET', url.statut.replace('__REF__', encodeURIComponent(etat.ref))).then(function (r) {
        var j = r.json || {};
        var texte = q('[data-bind="statut"]', racine);
        if (texte && j.message) { texte.textContent = j.message; }

        if (j.statut === 'acceptee') { arreter(); succes(); }
        else if (j.statut === 'refusee' || j.statut === 'expiree') { arreter(); echec(j.message); }
      }).catch(function () { /* la prochaine interrogation réessaiera */ });
    }

    function demarrer() {
      arreter();
      etat.debut = Date.now();
      etat.minuteur = setInterval(function () {
        if (Date.now() - etat.debut > 40 * 60 * 1000) { arreter(); echec('Le délai est dépassé sans paiement.'); return; }
        interroger();
      }, 3000);
      interroger();
    }
    function arreter() { if (etat.minuteur) { clearInterval(etat.minuteur); etat.minuteur = null; } }

    function succes() {
      if (etat.fenetre && !etat.fenetre.closed) { try { etat.fenetre.close(); } catch (e) { /* sans effet */ } }
      afficher('done');
    }
    function echec(texte) {
      q('[data-bind="echec-texte"]', racine).textContent = texte || 'Le paiement n\u2019a pas abouti.';
      afficher('fail');
    }

    // Reprendre tout de suite quand on revient sur l'onglet, ou quand la fenêtre de paiement signale la fin.
    document.addEventListener('visibilitychange', function () { if (!document.hidden && etat.minuteur) { interroger(); } });
    if (canal) { canal.onmessage = function (e) { if (e.data && e.data.ref === etat.ref && etat.minuteur) { interroger(); } }; }
  }

  /* ===================================================================
     Fenêtre de retour de paiement (ouverte par CinetPay)
     =================================================================== */
  function pageRetour(racine) {
    var ref = racine.getAttribute('data-ref');
    var url = racine.getAttribute('data-url-statut').replace('__REF__', encodeURIComponent(ref));
    var canal = 'BroadcastChannel' in window ? new BroadcastChannel('elouange-pay') : null;
    var afficher = etapes(racine);
    var minuteur = null;
    var essais = 0;

    function interroger() {
      essais++;
      appeler('GET', url).then(function (r) {
        var j = r.json || {};
        if (canal) { canal.postMessage({ ref: ref }); }
        var texte = q('[data-bind="statut"]', racine);
        if (texte && j.message) { texte.textContent = j.message; }

        if (j.statut === 'acceptee') { clearInterval(minuteur); afficher('done'); setTimeout(function () { try { window.close(); } catch (e) { /* sans effet */ } }, 2800); }
        else if (j.statut === 'refusee' || j.statut === 'expiree') { clearInterval(minuteur); afficher('fail'); }
        else if (essais > 40) { clearInterval(minuteur); }
      }).catch(function () { /* on réessaie */ });
    }

    afficher('wait');
    minuteur = setInterval(interroger, 3000);
    interroger();
  }

  /* ===================================================================
     Suppression de compte
     =================================================================== */
  function pageSuppression(racine) {
    var url = { verifier: racine.getAttribute('data-url-verifier'), confirmer: racine.getAttribute('data-url-confirmer') };
    var afficher = etapes(racine);
    var form = q('[data-form="verifier"]', racine);
    var confirmation = q('[data-form="confirmer"]', racine);
    var billet = null;

    formaterTelephone(form.elements.telephone);
    formaterCode(form.elements.code);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var zone = q('[data-notice]', form);
      var bouton = q('button[type="submit"]', form);
      message(zone, '');
      if (!verifierSaisie(form)) { return; }

      charger(bouton, true);
      post(url.verifier, { telephone: form.elements.telephone.value, code: form.elements.code.value, website: form.elements.website.value }).then(function (r) {
        charger(bouton, false);
        if (r.status === 422 && erreursServeur(form, r.json)) { return; }
        if (!r.ok) { message(zone, r.json.message || 'Vérification impossible. Réessayez dans un instant.'); return; }
        billet = r.json.billet;
        q('[data-bind="nom"]', racine).textContent = r.json.nom;
        afficher('recap');
      }).catch(function () { charger(bouton, false); message(zone, 'Connexion impossible. Vérifiez votre réseau puis réessayez.'); });
    });

    confirmation.addEventListener('submit', function (e) {
      e.preventDefault();
      var zone = q('[data-notice]', confirmation);
      var bouton = q('button[type="submit"]', confirmation);
      message(zone, '');
      charger(bouton, true);
      post(url.confirmer, { billet: billet, confirmation: confirmation.elements.confirmation.value }).then(function (r) {
        charger(bouton, false);
        if (!r.ok) {
          if (r.json && r.json.recommencer) { billet = null; afficher('id'); message(q('[data-notice]', form), r.json.message, 'info'); return; }
          message(zone, (r.json && r.json.message) || 'La suppression a échoué. Réessayez dans un instant.');
          return;
        }
        afficher('done');
      }).catch(function () { charger(bouton, false); message(zone, 'Connexion impossible. Vérifiez votre réseau puis réessayez.'); });
    });

    racine.addEventListener('click', function (e) {
      if (e.target.closest('[data-action="changer"]')) { billet = null; form.reset(); afficher('id'); }
    });
  }

  var racine = q('[data-mode]');
  if (!racine) { return; }
  var mode = racine.getAttribute('data-mode');
  if (mode === 'pay') { pagePaiement(racine); }
  else if (mode === 'return') { pageRetour(racine); }
  else if (mode === 'delete') { pageSuppression(racine); }
})();
