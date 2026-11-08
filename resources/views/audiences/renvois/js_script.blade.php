{{-- Renvois et délibérés : filtres instantanés, changement de date sans rechargement. Variables : $lignes, $peutModifier --}}
<script type="text/javascript">
(function () {
	'use strict';

	let LIGNES = @js($lignes);
	const PEUT = @js($peutModifier);
	const CSRF = @js(csrf_token());
	const URL_DATE = @js(route('audiences.renvois.date', 0));
	const URL_PROROGER = @js(route('audiences.renvois.proroger', 0));
	const URL_DATES = @js(route('audiences.renvois.dates', 0));
	const URL_ROLE = @js(auth()->user()->can('roles.voir') ? route('audiences.roles.show', 0) : null);
	const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const PAR_PAGE = 5;
	let type = 'renvoyee', etat = 'tous', ouverte = null, courante = null, page = 1;

	const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	const pluriel = function (n, mot) { return n + ' ' + mot + (n > 1 ? 's' : ''); };
	const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
	const toast = function (icone, titre) { if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: icone, title: titre, showConfirmButton: false, timer: 2600, timerProgressBar: true }); } };
	const libelleJour = function (isoDate) { const t = new Date(isoDate + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }); return t.charAt(0).toUpperCase() + t.slice(1); };

	function appel(url, donnees) {
		return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(donnees) })
			.then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { throw new Error((j.errors ? Object.values(j.errors)[0][0] : j.message) || 'Erreur'); } return j; }); });
	}

	/* proche = dans les 7 jours */
	const categorie = function (l) { if (l.retard) { return 'retard'; } if (l.jours === null) { return 'sans_date'; } return l.jours <= 7 ? 'semaine' : 'plus_tard'; };
	const ETIQUETTES = { tous: 'Tous', retard: 'En retard', semaine: 'Cette semaine', plus_tard: 'Plus tard', sans_date: 'Sans date' };

	function kpis() {
		const kpiPct = function (p) { if (p == null || isNaN(p)) { return ''; } const v = Math.round(Math.max(0, Math.min(100, p))); return ' data-pct="' + v + '" style="--pct:' + v + '"'; };
		const r = LIGNES.filter(function (l) { return l.etat === 'renvoyee'; }), d = LIGNES.filter(function (l) { return l.etat === 'deliberee'; });
		const cartes = [
			['vert', 'fa-arrows-rotate', 'Affaires renvoyées', r.length, 'fa-calendar-day', r.filter(function (l) { return categorie(l) === 'semaine'; }).length + ' à revenir cette semaine', ((r.length + d.length) > 0 ? r.length / (r.length + d.length) * 100 : null)],
			[r.some(function (l) { return l.retard; }) ? 'rouge' : 'vert', 'fa-calendar-xmark', 'Renvois dépassés', r.filter(function (l) { return l.retard; }).length, 'fa-triangle-exclamation', 'date passée sans nouveau passage', (r.length > 0 ? r.filter(function (l) { return l.retard; }).length / r.length * 100 : null)],
			['ardoise', 'fa-hourglass-half', 'Délibérés en attente', d.length, 'fa-calendar-check', d.filter(function (l) { return l.echeance; }).length + ' avec une date de prononcé', ((r.length + d.length) > 0 ? d.length / (r.length + d.length) * 100 : null)],
			[d.some(function (l) { return l.retard; }) ? 'orange' : 'sarcelle', 'fa-bell', 'Délibérés échus', d.filter(function (l) { return l.retard; }).length, 'fa-gavel', 'à rendre ou à proroger', (d.length > 0 ? d.filter(function (l) { return l.retard; }).length / d.length * 100 : null)],
		];
		$('#kpis-renvois').html(cartes.map(function (k) { return '<div class="au-kpi au-kpi--' + k[0] + '"' + kpiPct(k[6]) + '><div class="au-kpi__icone"><i class="fa-solid ' + k[1] + '"></i></div><span class="au-kpi__label">' + esc(k[2]) + '</span><div><div class="au-kpi__valeur"><span class="au-compteur" data-valeur="' + k[3] + '">' + k[3] + '</span></div><div class="au-chip"><i class="fa-solid ' + k[4] + '"></i><span>' + esc(k[5]) + '</span></div></div></div>'; }).join(''));
		$('#n-renvoyee').text(r.length); $('#n-deliberee').text(d.length);
		if (reduit) { return; }
		document.querySelectorAll('.au-compteur').forEach(function (el) { const cible = parseInt(el.getAttribute('data-valeur'), 10) || 0; if (cible <= 0) { return; } let debut = null; el.textContent = '0'; setTimeout(function () { el.textContent = cible; }, 1200); window.requestAnimationFrame(function pas(t) { if (debut === null) { debut = t; } const p = Math.min(1, (t - debut) / 700); el.textContent = Math.round(cible * (1 - Math.pow(1 - p, 3))); if (p < 1) { window.requestAnimationFrame(pas); } }); });
	}

	function filtres(base) {
		const n = function (c) { return base.filter(function (l) { return c === 'tous' || categorie(l) === c; }).length; };
		const dispo = ['tous', 'retard', 'semaine', 'plus_tard', 'sans_date'].filter(function (c) { return c === 'tous' || n(c) > 0; });
		if (dispo.indexOf(etat) < 0) { etat = 'tous'; }
		$('#filtres-etat').html(dispo.map(function (c) { return '<button type="button" class="au-filtre-etat ' + (etat === c ? 'is-actif' : '') + '" data-etat="' + c + '">' + ETIQUETTES[c] + '<b>' + n(c) + '</b></button>'; }).join(''));
	}

	function carte(l) {
		const cat = categorie(l), d = l.echeance ? new Date(l.echeance + 'T00:00:00') : null;
		const bloc = d ? '<div class="au-date ' + (cat === 'retard' ? 'au-date--rouge' : (cat === 'semaine' ? 'au-date--orange' : '')) + '"><b>' + String(d.getDate()).padStart(2, '0') + '</b>' + MOIS[d.getMonth()] + '</div>' : '<div class="au-date au-date--gris"><i class="fa-solid fa-question" style="font-size:22px"></i></div>';
		const etiq = [];
		if (l.etat === 'renvoyee') { etiq.push('<span class="au-badge au-badge--orange"><i class="fa-solid fa-arrows-rotate"></i>' + (l.nb_renvois === 1 ? '1 renvoi' : l.nb_renvois + ' renvois') + '</span>'); }
		else { etiq.push('<span class="au-badge au-badge--sombre"><i class="fa-solid fa-hourglass-half"></i>En délibéré</span>'); if (l.proroge) { etiq.push('<span class="au-badge au-badge--orange"><i class="fa-solid fa-clock-rotate-left"></i>Prorogé ' + l.proroge + (l.proroge > 1 ? ' fois' : ' fois') + '</span>'); } if (l.nb_renvois) { etiq.push('<span class="au-badge au-badge--gris">' + (l.nb_renvois === 1 ? '1 renvoi avant' : l.nb_renvois + ' renvois avant') + '</span>'); } }
		if (l.jours !== null) { etiq.push(l.retard ? '<span class="au-badge au-badge--rouge"><i class="fa-solid fa-triangle-exclamation"></i>' + (l.jours === 0 ? 'À rendre aujourd\'hui' : 'En retard de ' + pluriel(Math.abs(l.jours), 'jour')) + '</span>' : '<span class="au-badge au-badge--vert"><i class="fa-regular fa-clock"></i>' + (l.jours === 0 ? 'Aujourd\'hui' : (l.jours === 1 ? 'Demain' : 'Dans ' + pluriel(l.jours, 'jour'))) + '</span>'); }
		else { etiq.push('<span class="au-badge au-badge--gris"><i class="fa-regular fa-calendar"></i>Date non fixée : retour au prochain rôle</span>'); }
		if (l.motif) { etiq.push('<span class="au-badge au-badge--gris"><i class="fa-regular fa-comment"></i>' + esc(l.motif) + '</span>'); }
		const histo = l.historique.length ? '<div class="au-rv__histo"><div class="au-histo">' + l.historique.map(function (h) { return '<div class="au-histo__ligne"><b>' + esc(h.date) + '</b> · ' + esc(h.libelle) + (h.detail ? ' ' + esc(h.detail) : '') + (h.motif ? ' · ' + esc(h.motif) : '') + '</div>'; }).join('') + '</div></div>' : '';
		const actions = (PEUT ? '<button type="button" class="au-btn au-btn--vert" data-act="' + (l.etat === 'renvoyee' ? 'changer' : 'proroger') + '"><i class="fa-solid ' + (l.etat === 'renvoyee' ? 'fa-calendar-day' : 'fa-clock-rotate-left') + '"></i>' + (l.etat === 'renvoyee' ? 'Changer la date' : (l.echeance ? 'Proroger' : 'Fixer la date')) + '</button>' : '')
			+ '<button type="button" class="au-btn au-btn--clair" data-act="histo"><i class="fa-solid fa-timeline"></i>Historique</button>'
			+ (URL_ROLE && l.role_id ? '<a class="au-btn au-btn--clair" href="' + esc(URL_ROLE.replace(/\/0$/, '/' + l.role_id)) + '"><i class="fa-solid fa-list-ol"></i>Dernier rôle</a>' : '');
		return '<article class="au-rv ' + (l.etat === 'deliberee' ? 'is-delibere ' : '') + (cat === 'retard' ? 'is-retard ' : (cat === 'semaine' ? 'is-proche ' : '')) + (ouverte === l.ligne ? 'is-ouvert' : '') + '" data-ligne="' + l.ligne + '">' + bloc
			+ '<div class="au-rv__corps"><b>' + esc(l.numero) + ' <span style="font-weight:600;color:#2F3A52">·' + esc(l.chambre) + '</span></b><div>' + esc(l.parties || '—') + '</div>' + (l.objet ? '<small>' + esc(l.objet.split('\n')[0]) + '</small>' : '') + '<small>1re audience : ' + esc(l.premiere || '—') + ' · dernier passage : ' + esc(l.derniere || '—') + (l.echeance_libelle ? ' · ' + (l.etat === 'renvoyee' ? 'renvoyée au ' : 'prononcé le ') + esc(l.echeance_libelle) : '') + '</small><div class="au-rv__etiquettes">' + etiq.join('') + '</div></div>'
			+ '<div class="au-rv__actions">' + actions + '</div>' + histo + '</article>';
	}

	function pagination(total, debut, fin) {
		const pages = Math.ceil(total / PAR_PAGE);
		if (!total) { $('#pagination-renvois').empty(); return; }
		let nav = '<button type="button" data-p="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="Page précédente"><i class="fa-solid fa-angle-left"></i></button>';
		for (let i = 1; i <= pages; i++) { nav += '<button type="button" data-p="' + i + '"' + (i === page ? ' class="is-actif" aria-current="page"' : '') + '>' + i + '</button>'; }
		nav += '<button type="button" data-p="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + ' aria-label="Page suivante"><i class="fa-solid fa-angle-right"></i></button>';
		$('#pagination-renvois').html('<span>' + debut + ' à ' + fin + ' sur ' + total + ' affaire' + (total > 1 ? 's' : '') + '</span><nav>' + nav + '</nav>');
	}

	function afficher() {
		const q = ($('#recherche').val() || '').toLowerCase().trim(), ch = String($('#f-chambre').val() || '');
		const base = LIGNES.filter(function (l) { return l.etat === type && (!ch || String(l.chambre_id) === ch) && (!q || (l.numero + ' ' + l.parties + ' ' + l.objet + ' ' + l.chambre + ' ' + (l.motif || '')).toLowerCase().indexOf(q) >= 0); });
		filtres(base);
		const liste = base.filter(function (l) { return etat === 'tous' || categorie(l) === etat; });
		page = Math.min(Math.max(1, page), Math.max(1, Math.ceil(liste.length / PAR_PAGE)));
		const debut = (page - 1) * PAR_PAGE;
		pagination(liste.length, debut + 1, Math.min(liste.length, debut + PAR_PAGE));
		$('#liste').html(liste.length ? liste.slice(debut, debut + PAR_PAGE).map(carte).join('') :'<div class="au-vide"><i class="fa-solid ' + (type === 'renvoyee' ? 'fa-arrows-rotate' : 'fa-hourglass-half') + '"></i>' + (type === 'renvoyee' ? 'Aucune affaire renvoyée dans cette vue.' : 'Aucun délibéré dans cette vue.') + '</div>');
	}

	/* ---------------------------------------------------------------- changement de date */
	function ouvrirDate(l) {
		courante = l; const renvoi = l.etat === 'renvoyee';
		$('#titre-modal-date').text((renvoi ? 'Changer la date de renvoi' : (l.echeance ? 'Proroger le délibéré' : 'Fixer la date du délibéré')) + ' · ' + l.numero);
		$('#lib-date b').text(renvoi ? 'Nouvelle date de renvoi' : 'Délibéré jusqu\'au');
		$('#aide-date').html('<i class="fa-solid fa-circle-info"></i><span>' + (renvoi ? 'L\'affaire reviendra parmi les anciennes du rôle de cette date.' : 'L\'affaire reviendra parmi les délibérés du rôle de cette date ou du premier rôle qui suit.') + '</span>');
		$('#f_date_valeur').val(l.echeance || '').attr('min', new Date(Date.now() + 86400000).toISOString().slice(0, 10)); $('#f_date_motif').val(''); $('#erreur-date, #analyse-date, #propositions-date').empty();
		$('#modal-date').modal('show');
		fetch(URL_DATES.replace(/\/0\/dates$/, '/' + l.ligne + '/dates'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
			if (!j || !courante || courante.ligne !== l.ligne) { return; }
			$('#propositions-date').html(j.prochaines.length ? '<span>Prochaines audiences :</span>' + j.prochaines.map(function (p) { return '<button type="button" class="au-prop" data-prop="' + esc(p.date) + '">' + esc(p.libelle) + '</button>'; }).join('') : '');
		}).catch(function () {});
	}
	function analyser() {
		const d = $('#f_date_valeur').val(); $('#analyse-date').empty(); if (!d || !courante) { return; }
		fetch(URL_DATES.replace(/\/0\/dates$/, '/' + courante.ligne + '/dates') + '?date=' + encodeURIComponent(d), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
			const a = j && j.analyse; if (!a || (!a.ferme && a.siege)) { return; }
			$('#analyse-date').html('<div class="au-alerte"><i class="fa-solid fa-triangle-exclamation"></i><span>' + esc(a.ferme ? 'Le ' + a.jour + ' est un jour fermé (' + a.ferme + ').' : 'La ' + a.chambre + ' ne siège pas le ' + a.jour + ' (jours habituels : ' + a.habituels.join(', ') + ').') + ' Vous pouvez quand même enregistrer cette date.</span></div>');
		}).catch(function () {});
	}
	function valider() {
		if (!courante) { return; }
		const renvoi = courante.etat === 'renvoyee', url = (renvoi ? URL_DATE : URL_PROROGER).replace(/\/0\/(date|proroger)$/, '/' + courante.ligne + '/' + (renvoi ? 'date' : 'proroger'));
		const donnees = renvoi ? { renvoi_au: $('#f_date_valeur').val(), motif: $('#f_date_motif').val() } : { date_delibere: $('#f_date_valeur').val(), motif: $('#f_date_motif').val() };
		$('#erreur-date').empty(); $('#valider-date').prop('disabled', true);
		appel(url, donnees).then(function (j) {
			const l = LIGNES.find(function (x) { return x.ligne === courante.ligne; }); const nouvelle = $('#f_date_valeur').val();
			const d = new Date(nouvelle + 'T00:00:00'), auj = new Date(); auj.setHours(0, 0, 0, 0); const jours = Math.round((d - auj) / 86400000);
			if (l) { l.echeance = nouvelle; l.echeance_libelle = d.toLocaleDateString('fr-FR'); l.jours = jours; l.retard = renvoi ? jours < 0 : jours <= 0; if (!renvoi) { l.proroge = l.proroge + (donnees.date_delibere && courante.echeance ? 1 : 0); } if (donnees.motif) { l.motif = donnees.motif; } }
			$('#modal-date').modal('hide'); kpis(); afficher(); toast('success', j.message);
		}).catch(function (e) { $('#erreur-date').html('<div class="au-erreur"><i class="fa-solid fa-circle-exclamation"></i> ' + esc(e.message) + '</div>'); }).then(function () { $('#valider-date').prop('disabled', false); });
	}

	document.addEventListener('DOMContentLoaded', function () {
		kpis(); afficher();
		$('#onglets-type').on('click', 'button', function () { type = this.getAttribute('data-type'); etat = 'tous'; page = 1; $('#onglets-type button').removeClass('is-actif'); $(this).addClass('is-actif'); afficher(); });
		$('#filtres-etat').on('click', '.au-filtre-etat', function () { etat = this.getAttribute('data-etat'); page = 1; afficher(); });
		$('#f-chambre').on('change', function () { page = 1; afficher(); }); $('#recherche').on('input', function () { page = 1; afficher(); });
		$('#pagination-renvois').on('click', 'button:not(:disabled)', function () { page = parseInt(this.getAttribute('data-p'), 10) || 1; afficher(); });
		$('#liste').on('click', '[data-act="histo"]', function () { const $a = $(this).closest('.au-rv'); $a.toggleClass('is-ouvert'); ouverte = $a.hasClass('is-ouvert') ? +$a.attr('data-ligne') : null; })
			.on('click', '[data-act="changer"], [data-act="proroger"]', function () { const id = +$(this).closest('.au-rv').attr('data-ligne'); ouvrirDate(LIGNES.find(function (x) { return x.ligne === id; })); });
		$('#form-date').on('submit', function (e) { e.preventDefault(); valider(); });
		$('#f_date_valeur').on('change', analyser);
		$('#modal-date').on('click', '[data-prop]', function () { $('#f_date_valeur').val(this.getAttribute('data-prop')); analyser(); });
	});
})();
</script>
