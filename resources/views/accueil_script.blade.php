{{-- Tableau de bord : les blocs se remplissent depuis /accueil/donnees, sans recharger la page. --}}
<script type="text/javascript">
(function () {
	'use strict';

	const URL_DONNEES = @js(route('dashboard.donnees'));
	const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const MOIS = ['JANV.', 'FÉVR.', 'MARS', 'AVR.', 'MAI', 'JUIN', 'JUIL.', 'AOÛT', 'SEPT.', 'OCT.', 'NOV.', 'DÉC.'];
	const COULEURS_SUITES = { renvoi: '#F59A3B', delibere: '#6B7BE8', juge: '#00A36C', radie: '#94A3B8', joint: '#2AA7B2', desiste: '#A66BC9' };
	const ICONES_AIDE = { en_cours: 'fa-hourglass-half', audiences: 'fa-calendar-day', retards: 'fa-triangle-exclamation', a_rediger: 'fa-gavel' };
	const base = { fontFamily: 'Inter, sans-serif', toolbar: { show: false }, animations: { enabled: !reduit } };
	let mois = 12, courbe = null, donut = null, barres = null, enCours = 0;

	const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	const fmt = function (n) { return new Intl.NumberFormat('fr-FR').format(n); };
	const pluriel = function (n, mot) { return n + ' ' + mot + (n > 1 ? 's' : ''); };
	const vide = function (icone, texte) { return '<div class="au-vide"><i class="fa-solid ' + icone + '"></i>' + esc(texte) + '</div>'; };
	const blocDate = function (iso, classe) {
		const d = iso ? new Date(iso + 'T00:00:00') : null;
		return d ? '<div class="au-date ' + (classe || '') + '"><b>' + String(d.getDate()).padStart(2, '0') + '</b>' + MOIS[d.getMonth()] + '</div>' : '<div class="au-date au-date--gris"><i class="fa-solid fa-question" style="font-size:22px"></i></div>';
	};
	const detruire = function (g) { if (g) { g.destroy(); } return null; };

	/* ---------------------------------------------------------------- bandeau et tuiles */
	function bandeau(d) {
		const a = d.accueil;
		$('#ac-bandeau').html('<span><i class="fa-solid fa-landmark"></i>' + esc(a.perimetre) + '</span><span><i class="fa-regular fa-calendar"></i>' + esc(a.date) + '</span>'
			+ '<span class="pi-bandeau__fin"><i class="fa-solid fa-rotate"></i>Actualisé à ' + esc(d.genere_le) + ' · <button type="button" id="ac-actualiser">Actualiser</button></span>');
	}

	function kpis(d) {
		if (!d.kpis.length) {
			$('#ac-kpis').attr('class', 'ac-tuiles ac-tuiles--aucune').html('<div class="ac-aucun"><i class="fa-regular fa-compass"></i><div><b>Bienvenue.</b><br>Utilisez le menu pour accéder aux pages de votre profil.</div></div>');
			return;
		}
		$('#ac-kpis').attr('class', 'ac-tuiles').html(d.kpis.map(function (k) {
			const pc = k.pct == null ? '' : ' data-pct="' + k.pct + '" style="--pct:' + Math.max(0, Math.min(100, k.pct)) + '"';
			return '<a class="au-kpi au-kpi--' + esc(k.ton) + '"' + pc + ' href="' + esc(k.lien) + '"><div class="au-kpi__icone"><i class="fa-solid ' + esc(k.icone) + '"></i></div><span class="au-kpi__label">' + esc(k.libelle) + '</span>'
				+ '<div><div class="au-kpi__valeur"><span>' + fmt(k.valeur == null ? 0 : k.valeur) + '</span></div><div class="au-chip"><i class="fa-solid ' + (ICONES_AIDE[k.code] || 'fa-circle-info') + '"></i><span>' + esc(k.aide) + '</span></div></div></a>';
		}).join(''));
	}

	/* ---------------------------------------------------------------- graphiques */
	function dessinerCourbe(d) {
		const $b = $('#ac-bloc-courbe'); courbe = detruire(courbe); $('#ac-courbe').empty();
		if (!d.courbe) { $b.prop('hidden', true); return; }
		$b.prop('hidden', false);
		const c = d.courbe;
		if (!c.entrees.some(function (v) { return v > 0; }) && !c.sorties.some(function (v) { return v > 0; })) { $('#ac-courbe').html(vide('fa-chart-line', 'Aucune activité sur cette période.')); return; }
		courbe = new ApexCharts(document.querySelector('#ac-courbe'), {
			chart: Object.assign({ type: 'area', height: 300, zoom: { enabled: false } }, base),
			series: [{ name: 'Enrôlées', data: c.entrees }, { name: 'Terminées', data: c.sorties }],
			colors: ['#00794C', '#E8710A'], stroke: { curve: 'smooth', width: 3 }, dataLabels: { enabled: false },
			fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02, stops: [0, 95, 100] } }, markers: { size: 0, hover: { size: 5 } },
			xaxis: { categories: c.mois, labels: { style: { colors: '#2F3A52', fontSize: '11px' }, rotate: -45, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false } },
			yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: '#2F3A52' }, formatter: function (v) { return Math.round(v); } } },
			grid: { borderColor: '#E3E9F0', strokeDashArray: 4 }, legend: { position: 'top', horizontalAlign: 'right', fontSize: '12.5px', markers: { radius: 6 } },
			tooltip: { shared: true, intersect: false, y: { formatter: function (v) { return pluriel(v, 'affaire'); } } },
		});
		courbe.render();
	}

	/* Les suites notées : un anneau net, au centre le total, et à côté chaque suite avec sa part. */
	function dessinerSuites(d) {
		const $b = $('#ac-bloc-suites'); donut = detruire(donut); $('#ac-donut').empty(); $('#ac-suites-liste').empty();
		if (d.suites === null) { $b.prop('hidden', true); return; }
		$b.prop('hidden', false);
		if (!d.suites.length) { $('#ac-donut').html(vide('fa-circle-check', 'Aucune suite notée sur la période.')); return; }
		const total = d.suites.reduce(function (a, s) { return a + s.n; }, 0);
		donut = new ApexCharts(document.querySelector('#ac-donut'), {
			chart: Object.assign({ type: 'donut', height: 230 }, base), series: d.suites.map(function (s) { return s.n; }), labels: d.suites.map(function (s) { return s.libelle; }),
			colors: d.suites.map(function (s) { return COULEURS_SUITES[s.cle] || '#94A3B8'; }),
			fill: { type: 'gradient', gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.35, opacityFrom: 1, opacityTo: 0.82, stops: [0, 100] } },
			plotOptions: { pie: { expandOnClick: false, donut: { size: '74%', labels: { show: true, name: { show: true, fontSize: '12px', color: '#2F3A52', offsetY: 18 }, value: { show: true, fontSize: '34px', fontWeight: 800, color: '#0F1B33', offsetY: -14 },
				total: { show: true, showAlways: true, label: 'suites notées', fontSize: '12px', color: '#2F3A52', formatter: function () { return total; } } } } } },
			dataLabels: { enabled: false }, legend: { show: false }, stroke: { width: 4, colors: ['#fff'] }, tooltip: { y: { formatter: function (v) { return pluriel(v, 'suite'); } } },
		});
		donut.render();
		$('#ac-suites-liste').html(d.suites.slice().sort(function (a, b) { return b.n - a.n; }).map(function (s) {
			const pct = Math.round(s.n / total * 100), c = COULEURS_SUITES[s.cle] || '#94A3B8';
			return '<li><span class="ac-suites__ligne"><i style="background:' + c + '"></i><span>' + esc(s.libelle) + '</span><b>' + s.n + '</b><em>' + pct + ' %</em></span><span class="ac-suites__barre"><u style="width:' + pct + '%;background:' + c + '"></u></span></li>';
		}).join(''));
	}

	function dessinerBarres(d) {
		const $b = $('#ac-bloc-chambres'); barres = detruire(barres); $('#ac-barres').empty();
		if (d.chambres === null) { $b.prop('hidden', true); return; }
		$b.prop('hidden', false);
		if (!d.chambres.length) { $('#ac-barres').html(vide('fa-folder-open', 'Aucune affaire en cours dans votre périmètre.')); return; }
		barres = new ApexCharts(document.querySelector('#ac-barres'), {
			chart: Object.assign({ type: 'bar', height: Math.min(330, Math.max(260, d.chambres.length * 48 + 60)), stacked: true }, base),
			series: [{ name: 'Dans les délais', data: d.chambres.map(function (c) { return Math.max(0, c.stock - c.retards); }) }, { name: 'En retard', data: d.chambres.map(function (c) { return c.retards; }) }],
			colors: ['#2E9E6B', '#D64545'], plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
			dataLabels: { enabled: true, style: { fontSize: '11.5px' }, formatter: function (v) { return v > 0 ? v : ''; } },
			xaxis: { categories: d.chambres.map(function (c) { return c.libelle; }), labels: { style: { colors: '#2F3A52' } } },
			yaxis: { labels: { style: { colors: '#1F2A44', fontSize: '11.5px', fontWeight: 600 }, maxWidth: 150 } },
			grid: { borderColor: '#E3E9F0', strokeDashArray: 4 }, legend: { show: false }, tooltip: { shared: true, intersect: false },
		});
		barres.render();
	}

	/* ---------------------------------------------------------------- prochaines audiences : une colonne, défilement */
	function audiences(d) {
		const $b = $('#ac-bloc-audiences');
		if (d.audiences === null) { $b.prop('hidden', true); return; }
		$b.prop('hidden', false); $('#ac-lien-roles').attr('href', d.liens.roles || '#');
		$('#ac-sous-audiences').text(d.audiences.length ? pluriel(d.audiences.length, 'rôle publié') + ' à venir' : 'Les rôles publiés');
		$('#ac-audiences').html(d.audiences.length ? d.audiences.map(function (r) {
			const etiq = ['<span class="au-badge ' + (r.aujourdhui ? 'au-badge--orange' : 'au-badge--vert') + '"><i class="fa-regular fa-clock"></i>' + esc(r.quand) + (r.heure ? ' à ' + esc(r.heure) : '') + '</span>',
				'<span class="au-badge au-badge--gris">' + pluriel(r.affaires, 'affaire') + '</span>'];
			return '<a class="ac-aud ' + (r.aujourdhui ? 'is-aujourdhui' : '') + '" href="' + esc(r.url) + '">' + blocDate(r.date, r.aujourdhui ? 'au-date--orange' : '')
				+ '<span class="ac-aud__texte"><b>' + esc(r.chambre) + '</b><span class="ac-aud__type">' + esc(r.type) + '</span><span class="au-rv__etiquettes">' + etiq.join('') + '</span></span><i class="fa-solid fa-chevron-right ac-aud__fleche"></i></a>';
		}).join('') : vide('fa-calendar-check', 'Aucune audience à venir : aucun rôle publié.'));
	}

	function recentes(d) {
		const $b = $('#ac-bloc-recentes');
		if (d.recentes === null) { $b.prop('hidden', true); return; }
		$b.prop('hidden', false); $('#ac-lien-affaires').attr('href', d.liens.affaires || '#');
		$('#ac-recentes').html(d.recentes.length ? d.recentes.map(function (a) {
			return '<article class="au-rv">' + blocDate(a.date, '')
				+ '<div class="au-rv__corps"><b>' + esc(a.numero) + ' <span style="font-weight:600;color:#2F3A52">· ' + esc(a.chambre) + '</span></b><div>' + esc(a.objet || 'Objet non renseigné') + '</div>'
				+ '<div class="au-rv__etiquettes"><span class="au-badge au-badge--vert"><i class="fa-solid fa-file-circle-plus"></i>Enrôlée le ' + esc(a.date_libelle || '—') + '</span></div></div>'
				+ '<div class="au-rv__actions"><a class="au-btn au-btn--clair" href="' + esc(a.url) + '"><i class="fa-solid fa-folder-open"></i>Ouvrir</a></div></article>';
		}).join('') : vide('fa-folder-open', 'Aucune affaire enrôlée pour le moment.'));
	}

	/* ---------------------------------------------------------------- priorités : petites lignes, la première sort par le haut toutes les quelques secondes */
	let minuteurPas = null, survolDefil = false, enMouvement = false;
	function pas() {
		const $p = $('#ac-retards'), $l = $p.children();
		if (survolDefil || enMouvement || document.hidden || $l.length < 2) { return; }
		enMouvement = true;
		const h = $l.get(1).offsetTop - $l.get(0).offsetTop;   // hauteur d'une ligne, écart compris
		$p.css({ transition: 'transform .55s cubic-bezier(.4, 0, .2, 1)', transform: 'translateY(' + (-h) + 'px)' });
		setTimeout(function () { $p.css({ transition: 'none', transform: 'none' }); $l.first().appendTo($p); enMouvement = false; }, 580);
	}

	function retards(d) {
		const $b = $('#ac-bloc-retards');
		clearInterval(minuteurPas); enMouvement = false;
		if (d.retards === null) { $b.prop('hidden', true); return; }
		$b.prop('hidden', false); $('#ac-lien-alertes').attr('href', d.liens.alertes || '#');
		const liste = d.retards;
		$('#ac-sous-retards').text(liste.length ? pluriel(liste.length, 'priorité') : 'Retards et échéances proches');
		$('#ac-retards').css({ transition: 'none', transform: 'none' });
		if (!liste.length) { $('#ac-defil').removeClass('is-anime'); $('#ac-retards').html(vide('fa-circle-check', 'Aucun retard : tout est à jour.')); return; }
		$('#ac-retards').html(liste.map(function (a) {
			const p = String(a.echeance).split('/'), iso = p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : null;
			const etiq = [a.retard > 0 ? '<span class="au-badge au-badge--rouge"><i class="fa-solid fa-triangle-exclamation"></i>Retard de ' + a.retard + ' j</span>' : '<span class="au-badge au-badge--orange"><i class="fa-regular fa-clock"></i>' + esc(a.echeance) + '</span>', '<span class="au-badge au-badge--gris">' + esc(a.type) + '</span>'];
			const corps = blocDate(iso, a.retard > 0 ? 'au-date--rouge' : 'au-date--orange') + '<span class="ac-aud__texte"><b title="' + esc(a.titre) + '">' + esc(a.titre) + '</b><span class="au-rv__etiquettes">' + etiq.join('') + '</span></span><i class="fa-solid fa-chevron-right ac-aud__fleche"></i>';
			return a.url ? '<a class="ac-aud ac-aud--priorite ' + (a.retard > 0 ? 'is-retard' : '') + '" href="' + esc(a.url) + '">' + corps + '</a>' : '<div class="ac-aud ac-aud--priorite">' + corps + '</div>';
		}).join(''));
		// Plus de lignes que la fenêtre n'en montre : la liste avance d'une ligne à la fois, et s'arrête au survol.
		const anime = liste.length > 3 && !reduit;
		$('#ac-defil').toggleClass('is-anime', anime);
		if (anime) { minuteurPas = setInterval(pas, 3600); }
	}

	function afficher(d) {
		bandeau(d); kpis(d); dessinerCourbe(d); audiences(d); dessinerSuites(d); dessinerBarres(d); retards(d); recentes(d);
		$('#ac-ligne-a').prop('hidden', $('#ac-bloc-courbe').prop('hidden') && $('#ac-bloc-audiences').prop('hidden'));
		$('#ac-ligne-b').prop('hidden', $('#ac-bloc-suites').prop('hidden') && $('#ac-bloc-chambres').prop('hidden') && $('#ac-bloc-retards').prop('hidden'));
		$('#ac-pied').text('Les chiffres se rafraîchissent tout seuls toutes les deux minutes.');
	}

	function charger() {
		const n = ++enCours;
		fetch(URL_DONNEES + '?mois=' + mois, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { if (!r.ok) { throw new Error('Erreur'); } return r.json(); })
			.then(function (d) { if (n === enCours) { afficher(d); } })
			.catch(function () { $('#ac-bandeau').html('<span><i class="fa-solid fa-triangle-exclamation" style="color:#B42318"></i>Impossible de charger vos chiffres pour le moment. <button type="button" id="ac-actualiser">Réessayer</button></span>'); });
	}

	document.addEventListener('DOMContentLoaded', function () {
		charger();
		$('#ac-mois').on('click', 'button', function () { mois = parseInt(this.getAttribute('data-mois'), 10); $('#ac-mois button').removeClass('is-actif'); $(this).addClass('is-actif'); charger(); });
		$(document).on('click', '#ac-actualiser', charger);
		$('#ac-defil').on('mouseenter focusin', function () { survolDefil = true; }).on('mouseleave focusout', function () { survolDefil = false; });
		setInterval(function () { if (!document.hidden) { charger(); } }, 120000);
	});
})();
</script>
