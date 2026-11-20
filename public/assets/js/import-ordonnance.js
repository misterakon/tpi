/* Chargement de l'ordonnance par fichier Excel : on reste sur la même page.
   - le fichier part avec une barre d'envoi, puis le serveur le lit et le contrôle pendant que l'écran suit son avancement ;
   - les pages de l'assistant (contrôle, comparaison, bilan) remplacent le contenu de #io-racine sans recharger toute la page ;
   - sans JavaScript, tout fonctionne comme avant (formulaires et liens classiques).
   Attributs : form[data-io-depot] (envoi du fichier), form[data-io-analyse] (enregistre puis relance le contrôle),
   form[data-io-action] (action immédiate ; data-io-confirm = texte de confirmation), a[data-io-nav] (page suivante). */
(function () {
	'use strict';

	var RACINE = 'io-racine';
	var DUREE_MIN = 900;   // l'écran d'avancement reste visible au moins ce temps (ms), pour qu'on puisse le lire
	var ETAPES = [
		{ cle: 'envoi', lib: 'Envoi du fichier', ic: 'fa-cloud-arrow-up' },
		{ cle: 'verification', lib: 'Vérification du fichier', ic: 'fa-shield-halved' },
		{ cle: 'lecture', lib: 'Lecture des feuilles', ic: 'fa-table' },
		{ cle: 'controle', lib: 'Contrôle des données', ic: 'fa-magnifying-glass-chart' },
		{ cle: 'comparaison', lib: "Comparaison avec l'organisation", ic: 'fa-code-compare' }
	];

	var surveillance = null, ouvertA = 0, avecEtapes = false;

	function el(id) { return document.getElementById(id); }
	function jeton() {
		var champ = document.querySelector('input[name="_token"]'), meta = document.querySelector('meta[name="csrf-token"]');
		return champ ? champ.value : (meta ? meta.getAttribute('content') : '');
	}
	function entetes(extra) {
		var h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': jeton() };
		for (var k in (extra || {})) { h[k] = extra[k]; }
		return h;
	}
	function attendre(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
	function echapper(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }

	/* ---------- messages ---------- */
	function toast(type, message) {
		if (!message) { return; }
		if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'), title: message, showConfirmButton: false, timer: 4200, timerProgressBar: true }); }
	}
	function alerter(message) {
		if (window.Swal) { Swal.fire({ icon: 'warning', title: document.title.split('|')[0].trim() || 'Information', text: message }); } else { window.alert(message); }
	}
	function messageErreur(json, defaut) {
		if (json && json.errors) { var k = Object.keys(json.errors)[0]; if (k) { return json.errors[k][0]; } }
		return (json && json.message) || defaut || 'Une erreur est survenue.';
	}

	/* ---------- écran d'avancement ---------- */
	function ouvrir(titre, sous, etapes, avecEnvoi) {
		var ch = el('io-chargeur');
		if (!ch) { return; }
		avecEtapes = !!etapes;
		el('io-ch-titre').textContent = titre;
		el('io-ch-sous').textContent = sous || '';
		el('io-ch-etapes').innerHTML = etapes ? ETAPES.filter(function (e) { return avecEnvoi || e.cle !== 'envoi'; }).map(function (e) {
			return '<li data-etape="' + e.cle + '"><span class="io-ch-puce"><i class="fa-solid ' + e.ic + '"></i></span><span class="io-ch-lib">' + echapper(e.lib) + '</span><span class="io-ch-detail"></span></li>';
		}).join('') : '';
		el('io-ch-barre-zone').hidden = !etapes;
		ch.classList.toggle('io-chargeur--simple', !etapes);
		pct(0);
		ch.hidden = false;
		document.body.classList.add('io-bloque');
		ouvertA = Date.now();
	}
	function pct(p) {
		p = Math.max(0, Math.min(100, Math.round(p)));
		el('io-ch-barre').style.width = p + '%';
		el('io-ch-pct').textContent = p + ' %';
	}
	function etape(cle, detail) {
		var liste = [].slice.call(document.querySelectorAll('#io-ch-etapes li')), trouve = false;
		liste.forEach(function (li) {
			if (li.dataset.etape === cle) { li.className = 'is-actif'; trouve = true; if (detail) { li.querySelector('.io-ch-detail').textContent = detail; } }
			else { li.className = trouve ? '' : 'is-fait'; }
		});
	}
	function toutFait() {
		[].slice.call(document.querySelectorAll('#io-ch-etapes li')).forEach(function (li) { li.className = 'is-fait'; });
		pct(100);
	}
	function sous(t) { el('io-ch-sous').textContent = t; }
	function fermer() {
		clearInterval(surveillance); surveillance = null;
		var ch = el('io-chargeur');
		if (ch) { ch.hidden = true; }
		document.body.classList.remove('io-bloque');
	}
	function fermerApresDuree() {
		var reste = DUREE_MIN - (Date.now() - ouvertA);
		return reste > 0 ? attendre(reste).then(fermer) : (fermer(), Promise.resolve());
	}

	/* ---------- analyse du fichier, avec suivi ---------- */
	function analyser(urls, debutPct) {
		debutPct = debutPct || 0;
		var occupe = false;
		etape('verification');
		surveillance = setInterval(function () {
			if (occupe) { return; }
			occupe = true;
			fetch(urls.progression, { headers: entetes(), credentials: 'same-origin', cache: 'no-store' })
				.then(function (r) { return r.json(); })
				.then(function (p) {
					if (!p || !surveillance) { return; }
					pct(debutPct + (100 - debutPct) * (p.pct / 100));
					etape(p.etape, p.message);
					sous(p.message);
				})
				.catch(function () { /* un battement manqué : sans importance */ })
				.then(function () { occupe = false; });
		}, 280);

		return fetch(urls.analyser, { method: 'POST', headers: entetes(), credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok !== false, json: j }; }); })
			.then(function (res) {
				clearInterval(surveillance); surveillance = null;
				if (!res.ok) { throw new Error(messageErreur(res.json, 'Le fichier n\'a pas pu être lu.')); }
				toutFait();
				sous('Contrôle terminé');

				return res.json;
			});
	}

	/* ---------- navigation sans rechargement ---------- */
	function executerMessagesDeSession(doc) {
		[].slice.call(doc.querySelectorAll('script')).forEach(function (s) {
			var t = s.textContent || '';
			if (t.indexOf('Swal.fire') !== -1 && t.length < 3000 && t.indexOf('const ') === -1) { try { (new Function(t))(); } catch (e) { /* message perdu : sans gravité */ } }
		});
	}
	function afficher(url, empiler) {
		return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
			.then(function (r) { return r.text().then(function (html) { return { r: r, html: html }; }); })
			.then(function (x) {
				var doc = new DOMParser().parseFromString(x.html, 'text/html'), nouveau = doc.getElementById(RACINE), cible = el(RACINE);
				if (!nouveau || !cible) { window.location.href = url; return; }
				cible.innerHTML = nouveau.innerHTML;
				document.title = doc.title || document.title;
				if (empiler) { history.pushState({ io: 1 }, '', x.r.url || url); }
				window.scrollTo(0, 0);
				init();
				executerMessagesDeSession(doc);
			});
	}
	function chargerPage(url, message) {
		var minuteur = setTimeout(function () { ouvrir(message || 'Chargement…', '', false); }, 220);
		return afficher(url, true).catch(function () { window.location.href = url; }).then(function () { clearTimeout(minuteur); fermer(); });
	}

	/* ---------- envoi du fichier ---------- */
	function envoyer(form) {
		var champ = form.querySelector('input[type=file]');
		if (!champ || !champ.files.length) { alerter('Choisissez d\'abord le fichier Excel (.xlsx) à charger.'); return; }
		ouvrir('Chargement du fichier', champ.files[0].name, true, true);
		etape('envoi');
		var xhr = new XMLHttpRequest();
		xhr.open('POST', form.action);
		var h = entetes();
		for (var k in h) { xhr.setRequestHeader(k, h[k]); }
		xhr.upload.onprogress = function (e) {
			if (e.lengthComputable) { pct(e.loaded / e.total * 20); sous('Envoi du fichier… ' + Math.round(e.loaded / e.total * 100) + ' %'); }
		};
		xhr.onerror = function () { fermer(); alerter('L\'envoi du fichier a échoué : vérifiez votre connexion.'); };
		xhr.onload = function () {
			var j = {};
			try { j = JSON.parse(xhr.responseText); } catch (e) { /* réponse non JSON */ }
			if (xhr.status < 200 || xhr.status >= 300 || !j.ok) { fermer(); alerter(messageErreur(j, 'Le fichier a été refusé.')); return; }
			pct(20); sous('Fichier reçu');
			analyser(j, 20)
				.then(function () { return fermerApresDuree(); })
				.then(function () { return afficher(j.voir, true); })
				.catch(function (e) { fermer(); alerter(e.message); afficher(j.voir, true).catch(function () { window.location.href = j.voir; }); });
		};
		xhr.send(new FormData(form));
	}

	/* ---------- formulaires de l'assistant ---------- */
	function soumettreAnalyse(form) {
		ouvrir('Recontrôle du fichier', 'Enregistrement de vos choix…', true);
		etape('verification');
		var message = '';
		fetch(form.action, { method: 'POST', headers: entetes(), body: new FormData(form), credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok !== false, json: j }; }); })
			.then(function (res) {
				if (!res.ok) { throw new Error(messageErreur(res.json)); }
				message = res.json.message;

				return analyser(res.json, 0);
			})
			.then(function (j) { return fermerApresDuree().then(function () { return afficher(j.voir, false); }); })
			.then(function () { toast('success', message); })
			.catch(function (e) { fermer(); alerter(e.message); });
	}
	function soumettreAction(form) {
		var texte = form.getAttribute('data-io-confirm');
		if (texte) {
			var n = el('n-sel');
			if (n) { texte = texte.replace('{n}', n.textContent); }
			if (!window.confirm(texte)) { return; }
		}
		ouvrir(form.getAttribute('data-io-titre') || 'Traitement en cours', form.getAttribute('data-io-sous') || 'Merci de patienter, ne fermez pas cette page.', false);
		fetch(form.action, { method: 'POST', headers: entetes(), body: new FormData(form), credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok && j.ok !== false, json: j }; }); })
			.then(function (res) {
				if (!res.ok) {
					fermer();
					alerter(messageErreur(res.json));
					if (res.json.url) { return afficher(res.json.url, true); }
					return;
				}

				return afficher(res.json.url, true).then(function () {
					fermer();
					toast('success', res.json.message);
					if (res.json.avertissement) { alerter(res.json.avertissement); }
				});
			})
			.catch(function () { fermer(); alerter('Le serveur n\'a pas répondu : rien n\'a été modifié. Rechargez la page pour vérifier l\'état.'); });
	}

	/* ---------- comparaison : cases à cocher ---------- */
	function majSelection() {
		var form = el('f-appliquer');
		if (!form) { return; }
		var lignes = [].slice.call(form.querySelectorAll('.io-sel')), tout = el('tout'), n = el('n-sel'), btn = el('b-appliquer');
		var c = lignes.filter(function (l) { return l.checked; }).length;
		n.textContent = c; btn.disabled = c === 0;
		tout.checked = c === lignes.length; tout.indeterminate = c > 0 && c < lignes.length;
		[].slice.call(form.querySelectorAll('.io-bloc-case')).forEach(function (b) {
			var d = lignes.filter(function (l) { return l.dataset.bloc === b.dataset.bloc; }), k = d.filter(function (l) { return l.checked; }).length;
			b.checked = k === d.length; b.indeterminate = k > 0 && k < d.length;
		});
	}

	/* ---------- dépôt du fichier : glisser-déposer ---------- */
	function initDepot() {
		var zone = el('zone-depot'), champ = el('fichier'), nom = el('nom-fichier');
		if (!zone || !champ || zone.dataset.pret) { return; }
		zone.dataset.pret = '1';
		champ.addEventListener('change', function () { nom.textContent = champ.files.length ? champ.files[0].name : ''; });
		['dragenter', 'dragover'].forEach(function (e) { zone.addEventListener(e, function (ev) { ev.preventDefault(); zone.classList.add('is-survol'); }); });
		['dragleave', 'drop'].forEach(function (e) { zone.addEventListener(e, function () { zone.classList.remove('is-survol'); }); });
		zone.addEventListener('drop', function (ev) { ev.preventDefault(); if (ev.dataTransfer.files.length) { champ.files = ev.dataTransfer.files; champ.dispatchEvent(new Event('change')); } });
	}

	function init() { initDepot(); majSelection(); }

	/* ---------- écouteurs (délégués : ils survivent au remplacement du contenu) ---------- */
	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a[data-io-nav]') : null;
		if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) { return; }
		e.preventDefault();
		chargerPage(a.href, a.getAttribute('data-io-titre'));
	});
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (e.defaultPrevented || !f.matches) { return; }
		if (f.matches('form[data-io-depot]')) { e.preventDefault(); envoyer(f); }
		else if (f.matches('form[data-io-analyse]')) { e.preventDefault(); soumettreAnalyse(f); }
		else if (f.matches('form[data-io-action]')) { e.preventDefault(); soumettreAction(f); }
	});
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (!t.matches) { return; }
		if (t.id === 'tout') { [].slice.call(document.querySelectorAll('#f-appliquer .io-sel')).forEach(function (l) { l.checked = t.checked; }); majSelection(); }
		else if (t.matches('.io-bloc-case')) { [].slice.call(document.querySelectorAll('#f-appliquer .io-sel')).forEach(function (l) { if (l.dataset.bloc === t.dataset.bloc) { l.checked = t.checked; } }); majSelection(); }
		else if (t.matches('.io-sel')) { majSelection(); }
	});
	window.addEventListener('popstate', function () { if (el(RACINE)) { afficher(window.location.href, false).catch(function () { window.location.reload(); }); } });

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
