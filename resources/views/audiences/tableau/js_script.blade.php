{{-- Tableau de bord des audiences : affichage et actualisation sans rechargement. Variable : $donnees --}}
<script type="text/javascript">
(function () {
	'use strict';

	let D = @js($donnees);
	const URL_DONNEES = @js(route('audiences.tableau.donnees'));
	const LIENS = @js([
		'feuille' => auth()->user()->can('feuille.voir') ? route('audiences.feuille.show', 0) : null,
		'feuilles' => auth()->user()->can('feuille.voir') ? route('audiences.feuille') : null,
		'renvois' => auth()->user()->can('renvois.voir') ? route('audiences.renvois') : null,
		'redaction' => auth()->user()->can('redaction.voir') ? route('decisions.redaction') : null,
	]);
	const COULEURS = { a_venir: '#43C08E', renvoyee: '#F59A3B', deliberee: '#8EA0FF', sans_suite: '#F2705F', non_portee: '#FDE68A', jugee: '#7DD3C0', terminee: '#B8C4D4' };
	const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	let onglet = 'chambres', graphique = null, donut = null, minuteur = null;

	const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	const fmt = function (n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)).replace(/[  ]/g, ' '); };
	const pluriel = function (n, mot) { return n + ' ' + mot + (n > 1 ? 's' : ''); };
	const initiales = function (nom) { const m = String(nom).split(/\s+/).filter(Boolean); return ((m[0] || '?').charAt(0) + (m[1] || '').charAt(0)).toUpperCase(); };

	/* ---------------------------------------------------------------- indicateurs */
	function kpis() {
		const k = D.kpis;
		const cartes = [
			{ cls: 'vert', ico: 'fa-folder-open', lib: 'Affaires en cours', val: k.en_cours, chip: ['fa-arrows-rotate', k.renvoyees + ' renvoyée' + (k.renvoyees > 1 ? 's' : '')] },
			{ cls: 'sarcelle', ico: 'fa-calendar-check', lib: 'Audiences à venir', val: k.audiences, chip: ['fa-sun', k.aujourdhui + " aujourd'hui"], href: LIENS.feuilles },
			{ cls: 'ardoise', ico: 'fa-hourglass-half', lib: 'Délibérés en attente', val: k.deliberes, chip: ['fa-bell', k.deliberes_echus + ' échu' + (k.deliberes_echus > 1 ? 's' : '')], href: LIENS.renvois },
			{ cls: 'orange', ico: 'fa-file-pen', lib: 'Décisions à rédiger', val: k.a_rediger, chip: ['fa-gavel', 'jugées sans décision validée'], href: LIENS.redaction },
			{ cls: k.retard > 0 ? 'rouge' : 'vert', ico: k.retard > 0 ? 'fa-triangle-exclamation' : 'fa-thumbs-up', lib: 'Affaires en retard', val: k.retard, chip: [k.retard > 0 ? 'fa-arrow-right' : 'fa-check', k.retard > 0 ? k.retard_renvois + ' renvoi(s) · ' + k.retard_deliberes + ' délibéré(s)' : 'Rien en retard'], href: LIENS.renvois },
		];
		$('#kpis').html(cartes.map(function (c) {
			const dedans = '<div class="au-kpi__icone"><i class="fa-solid ' + c.ico + '"></i></div><span class="au-kpi__label">' + esc(c.lib) + '</span><div><div class="au-kpi__valeur"><span class="au-compteur" data-valeur="' + c.val + '">' + fmt(c.val) + '</span></div><div class="au-chip"><i class="fa-solid ' + c.chip[0] + '"></i><span>' + esc(c.chip[1]) + '</span></div></div>';
			return c.href ? '<a class="au-kpi au-kpi--' + c.cls + '" href="' + esc(c.href) + '">' + dedans + '</a>' : '<div class="au-kpi au-kpi--' + c.cls + '">' + dedans + '</div>';
		}).join(''));
		if (reduit) { return; }
		document.querySelectorAll('.au-compteur').forEach(function (el) {
			const cible = parseInt(el.getAttribute('data-valeur'), 10) || 0; if (cible <= 0) { return; }
			let debut = null; el.textContent = '0'; setTimeout(function () { el.textContent = fmt(cible); }, 1200);
			window.requestAnimationFrame(function pas(t) { if (debut === null) { debut = t; } const p = Math.min(1, (t - debut) / 800); el.textContent = fmt(cible * (1 - Math.pow(1 - p, 3))); if (p < 1) { window.requestAnimationFrame(pas); } });
		});
	}

	/* ---------------------------------------------------------------- graphiques */
	const base = { fontFamily: 'Inter, sans-serif', toolbar: { show: false }, animations: { speed: 500 } };
	const grille = { borderColor: 'rgba(120,130,150,.16)', strokeDashArray: 4 };
	const axeEntier = { min: 0, forceNiceScale: true, decimalsInFloat: 0, labels: { formatter: function (v) { return Math.round(v); } } };

	function vues() {
		return {
			chambres: {
				titre: "Affaires par chambre et par situation",
				resume: [[D.kpis.en_cours, 'en cours'], [D.kpis.renvoyees, 'renvoyées'], [D.kpis.deliberes, 'en délibéré'], [D.kpis.retard, 'en retard']],
				options: function () {
					const c = D.par_chambre;
					return { chart: Object.assign({ type: 'bar', height: Math.max(300, c.length * 46 + 80), stacked: true }, base),
						series: [{ name: 'À venir', data: c.map(function (x) { return x.a_venir; }) }, { name: 'Renvoyées', data: c.map(function (x) { return x.renvoyee; }) }, { name: 'En délibéré', data: c.map(function (x) { return x.deliberee; }) }, { name: 'À rédiger', data: c.map(function (x) { return x.a_rediger; }) }],
						colors: ['#00A36C', '#F59A3B', '#4B5BD6', '#8E4DB5'], plotOptions: { bar: { horizontal: true, barHeight: '58%', borderRadius: 5 } },
						xaxis: { categories: c.map(function (x) { return x.libelle; }), labels: { formatter: function (v) { return Math.round(v); } } }, yaxis: { labels: { maxWidth: 210, style: { fontSize: '12.5px', fontWeight: 500 } } },
						grid: grille, dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left', markers: { radius: 12 } }, noData: { text: 'Aucune affaire en cours' } };
				}
			},
			jours: {
				resume: [[D.kpis.audiences, 'audiences prévues'], [D.kpis.aujourdhui, "aujourd'hui"]],
				options: function () {
					const j = D.par_jour;
					return { chart: Object.assign({ type: 'bar', height: 320, stacked: true }, base),
						series: [{ name: 'Rôle publié', data: j.map(function (x) { return x.publie; }) }, { name: 'Rôle en préparation', data: j.map(function (x) { return x.brouillon; }) }],
						colors: ['#00794C', '#F59A3B'], plotOptions: { bar: { columnWidth: '50%', borderRadius: 6 } }, xaxis: { categories: j.map(function (x) { return x.libelle; }), labels: { rotate: -35, style: { fontSize: '11.5px' } } },
						yaxis: axeEntier, grid: grille, dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left', markers: { radius: 12 } }, noData: { text: 'Aucune audience' } };
				}
			},
			echeances: {
				resume: [[D.echeances.echus, 'échus'], [D.echeances.semaine, 'cette semaine'], [D.echeances.mois, 'sous 30 jours'], [D.echeances.sans_date, 'sans date']],
				options: function () {
					const e = D.echeances;
					return { chart: Object.assign({ type: 'bar', height: 320 }, base), series: [{ name: 'Délibérés', data: [e.echus, e.semaine, e.mois, e.plus_tard, e.sans_date] }],
						colors: ['#4B5BD6'], plotOptions: { bar: { columnWidth: '46%', borderRadius: 8, distributed: true } },
						xaxis: { categories: ['Échus', 'Cette semaine', 'Sous 30 jours', 'Plus tard', 'Sans date'] }, yaxis: axeEntier, grid: grille, dataLabels: { enabled: true }, legend: { show: false }, tooltip: { y: { formatter: function (v) { return pluriel(v, 'délibéré'); } } }, noData: { text: 'Aucun délibéré' }, fill: { opacity: 1 } };
				}
			}
		};
	}
	const COULEURS_ECH = ['#B42318', '#E8710A', '#4B5BD6', '#00794C', '#94A3B8'];

	function afficherGraphique() {
		const v = vues()[onglet];
		$('#sous-titre-graphique').text({ chambres: 'Chaque barre regroupe les affaires de la chambre selon leur situation', jours: 'Rôles prévus jour par jour, publiés ou encore en préparation', echeances: 'Délibérés classés par date de prononcé' }[onglet]);
		$('#resume').html(v.resume.map(function (r) { return '<div><b>' + fmt(r[0]) + '</b><span>' + esc(r[1]) + '</span></div>'; }).join(''));
		if (graphique) { graphique.destroy(); }
		const opts = v.options();
		if (onglet === 'echeances') { opts.colors = COULEURS_ECH; }
		graphique = new ApexCharts(document.querySelector('#graphique'), opts); graphique.render();
	}

	function afficherDonut() {
		const r = D.repartition.filter(function (x) { return x.n > 0; });
		if (donut) { donut.destroy(); donut = null; }
		const total = r.reduce(function (a, x) { return a + x.n; }, 0);
		$('#legende').html(r.length ? r.map(function (x) { return '<div><i class="au-point" style="background:' + COULEURS[x.cle] + '"></i><span>' + esc(x.libelle) + '</span><b>' + fmt(x.n) + '</b><em>' + Math.round(x.n / Math.max(1, total) * 100) + ' %</em></div>'; }).join('') : '<div style="opacity:.85">Aucune affaire pour le moment.</div>');
		if (!r.length) { $('#donut').empty(); return; }
		donut = new ApexCharts(document.querySelector('#donut'), { chart: Object.assign({ type: 'donut', height: 210 }, base), series: r.map(function (x) { return x.n; }), labels: r.map(function (x) { return x.libelle; }), colors: r.map(function (x) { return COULEURS[x.cle]; }),
			stroke: { width: 0 }, dataLabels: { enabled: false }, legend: { show: false }, tooltip: { theme: 'light', fillSeriesColor: false },
			plotOptions: { pie: { donut: { size: '72%', labels: { show: true, name: { show: false }, value: { show: true, color: '#fff', fontSize: '24px', fontWeight: 700 }, total: { show: true, label: 'affaires', color: '#fff', formatter: function (w) { return fmt(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)); } } } } } } });
		donut.render();
	}

	/* ---------------------------------------------------------------- listes */
	const vide = function (icone, texte) { return '<div class="au-vide"><i class="fa-solid ' + icone + '"></i>' + esc(texte) + '</div>'; };
	function dateBloc(texte, cls) { const p = String(texte).split(' '); return '<div class="au-date ' + (cls || '') + '"><b>' + esc(p[0] || '') + '</b>' + esc(p.slice(1).join(' ')) + '</div>'; }

	function listeAudiences() {
		$('#liste-audiences').html(D.audiences.length ? D.audiences.map(function (a) {
			const jour = String(a.date).split(' ')[1] || '';
			const etat = a.etat === 'terminee' ? '<span class="au-badge au-badge--vert"><i class="fa-solid fa-check"></i>Terminée</span>' : (a.etat === 'en_cours' ? '<span class="au-badge au-badge--orange"><i class="fa-solid fa-bolt"></i>En cours</span>' : (a.statut === 'publie' ? '<span class="au-badge au-badge--sarcelle"><i class="fa-solid fa-circle-check"></i>Publié</span>' : '<span class="au-badge au-badge--gris"><i class="fa-solid fa-pen-ruler"></i>En préparation</span>'));
			const cible = (a.statut === 'publie' && LIENS.feuille) ? LIENS.feuille.replace(/\/0$/, '/' + a.id) : null;
			const interieur = '<div class="au-date ' + (a.aujourdhui ? 'au-date--orange' : '') + '"><b>' + esc(jour.split('/')[0]) + '</b>' + esc(a.date.split(' ')[0].slice(0, 3)) + '</div><div class="au-item__texte"><b>' + esc(a.chambre) + '</b><small>' + esc(a.date) + (a.heure ? ' · ' + esc(a.heure) : '') + (a.salle ? ' · ' + esc(a.salle) : '') + ' · ' + pluriel(a.affaires, 'affaire') + '</small></div>' + etat;
			return cible ? '<a class="au-item" href="' + esc(cible) + '">' + interieur + '</a>' : '<div class="au-item">' + interieur + '</div>';
		}).join('') : vide('fa-calendar', 'Aucune audience dans les 14 prochains jours.'));
	}

	function aTraiter() {
		const k = D.kpis, t = [];
		if (k.deliberes_echus > 0) { t.push(['fa-hourglass-end', 'rouge', k.deliberes_echus + ' délibéré' + (k.deliberes_echus > 1 ? 's' : '') + ' à rendre', 'La date de prononcé est atteinte', LIENS.renvois]); }
		if (k.retard_renvois > 0) { t.push(['fa-calendar-xmark', 'rouge', k.retard_renvois + ' renvoi' + (k.retard_renvois > 1 ? 's' : '') + ' dépassé' + (k.retard_renvois > 1 ? 's' : ''), 'La date de renvoi est passée sans nouveau passage', LIENS.renvois]); }
		if (k.a_rediger > 0) { t.push(['fa-file-pen', 'orange', k.a_rediger + ' décision' + (k.a_rediger > 1 ? 's' : '') + ' à rédiger', 'Affaires jugées sans décision validée', LIENS.redaction]); }
		if (k.aujourdhui > 0) { t.push(['fa-sun', 'orange', k.aujourdhui + " audience" + (k.aujourdhui > 1 ? 's' : '') + " aujourd'hui", 'Ouvrez la feuille et notez les suites', LIENS.feuilles]); }
		$('#a-traiter').html(t.length ? t.map(function (x) {
			const ic = '<div class="au-date au-date--' + (x[1] === 'rouge' ? 'rouge' : 'orange') + '" style="height:48px;width:48px"><i class="fa-solid ' + x[0] + '" style="font-size:18px"></i></div><div class="au-item__texte"><b>' + esc(x[2]) + '</b><small>' + esc(x[3]) + '</small></div>';
			return x[4] ? '<a class="au-item" href="' + esc(x[4]) + '">' + ic + '<i class="fa-solid fa-chevron-right" style="color:#94A3B8"></i></a>' : '<div class="au-item">' + ic + '</div>';
		}).join('') : '<div class="au-vide"><i class="fa-regular fa-circle-check"></i>Rien à traiter pour le moment.</div>');
	}

	function listeAffaires(cible, lignes, icone, messageVide, detail) {
		$(cible).html(lignes.length ? lignes.map(function (a) {
			return '<div class="au-item"><div class="au-item__texte"><b>' + esc(a.numero) + ' <span style="font-weight:500;color:#5B6B86">· ' + esc(a.chambre) + '</span></b><small>' + esc(a.parties) + '</small><small>' + detail(a) + '</small></div></div>';
		}).join('') : vide(icone, messageVide));
	}

	function magistrats() {
		const actif = String($('#f-agent').val() || '');
		$('#magistrats').html(D.magistrats.length ? D.magistrats.map(function (m, i) {
			return '<button type="button" class="au-mag ' + (String(m.id) === actif ? 'is-actif' : '') + '" data-agent="' + m.id + '"><div class="au-mag__tete"><div class="au-av au-av-' + (i % 6) + '">' + esc(initiales(m.nom)) + '</div><div><b class="au-mag__nom">' + esc(m.nom) + '</b><span class="au-mag__sous">' + esc(m.fonction) + ' · ' + esc(m.chambres_libelles.join(', ')) + '</span></div></div>'
				+ '<div class="au-mag__stats"><div><b>' + m.en_cours + '</b><small>en cours</small></div><div><b>' + m.deliberee + '</b><small>délibérés</small></div><div><b>' + m.a_rediger + '</b><small>à rédiger</small></div><div class="' + (m.retard ? 'is-alerte' : '') + '"><b>' + m.retard + '</b><small>en retard</small></div></div>'
				+ '<div class="au-mag__pied"><i class="fa-solid fa-calendar-day"></i>' + (m.prochaine ? 'Prochaine audience : ' + esc(m.prochaine) : 'Aucune audience programmée') + '</div></button>';
		}).join('') : vide('fa-user-tie', 'Aucun magistrat affecté dans ce périmètre.'));
	}

	function tout() {
		kpis(); afficherGraphique(); afficherDonut(); listeAudiences(); aTraiter(); magistrats();
		listeAffaires('#liste-deliberes', D.deliberes, 'fa-hourglass-half', 'Aucun délibéré en attente.', function (a) { return a.echeance ? (a.jours > 0 ? '<span style="color:#B42318;font-weight:700">Échu depuis ' + pluriel(a.jours, 'jour') + '</span> · ' : '') + 'prononcé le ' + esc(a.echeance) + (a.proroge ? ' · prorogé ' + a.proroge + ' fois' : '') : 'Date de prononcé non fixée'; });
		listeAffaires('#liste-retards', D.retards, 'fa-circle-check', 'Aucune affaire en retard.', function (a) { return a.etat === 'renvoyee' ? 'Renvoi du ' + esc(a.echeance) + ' dépassé de ' + pluriel(a.jours, 'jour') : (a.etat === 'deliberee' ? 'Délibéré échu depuis ' + pluriel(a.jours, 'jour') : (a.etat === 'sans_suite' ? 'Suite non notée' : 'Jamais portée au rôle')); });
		listeAffaires('#liste-redaction', D.a_rediger, 'fa-file-circle-check', 'Aucune décision à rédiger.', function () { return 'Jugée : décision à rédiger et à valider'; });
		$('#maj').text('Actualisé à ' + D.genere_le);
	}

	/* ---------------------------------------------------------------- actualisation */
	function actualiser(silencieux) {
		const bouton = $('#btn-maj'); bouton.addClass('is-tourne');
		const q = new URLSearchParams(); if ($('#f-chambre').val()) { q.set('chambre', $('#f-chambre').val()); } if ($('#f-agent').val()) { q.set('agent', $('#f-agent').val()); }
		fetch(URL_DONNEES + '?' + q.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { if (!r.ok) { throw new Error('http'); } return r.json(); })
			.then(function (d) { D = d; tout(); })
			.catch(function () { if (!silencieux && window.Swal) { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible d\'actualiser le tableau de bord. Réessayez.' }); } })
			.then(function () { setTimeout(function () { bouton.removeClass('is-tourne'); }, 400); });
	}

	document.addEventListener('DOMContentLoaded', function () {
		tout();
		$('#f-chambre, #f-agent').on('change', function () { actualiser(false); });
		$('#btn-maj').on('click', function () { actualiser(false); });
		$('.au-onglets button').on('click', function () { onglet = this.getAttribute('data-onglet'); $('.au-onglets button').removeClass('is-actif'); $(this).addClass('is-actif'); afficherGraphique(); });
		$('#magistrats').on('click', '.au-mag', function () {
			const id = String(this.getAttribute('data-agent')), deja = String($('#f-agent').val() || '') === id;
			$('#f-agent').val(deja ? '' : id).trigger('change');
		});
		/* Les chiffres se rafraîchissent d'eux-mêmes toutes les 90 secondes tant que la page est visible. */
		minuteur = setInterval(function () { if (!document.hidden) { actualiser(true); } }, 90000);
	});
})();
</script>
