{{-- Suites d'audience : filtres instantanés, pagination et export sans quitter la page. Variables : $suites, $urlFeuille, $urlFiche --}}
<script type="text/javascript">
(function () {
	'use strict';

	const URL_DONNEES = @js(route('audiences.suites.donnees'));
	const URL_EXPORT = @js(route('audiences.suites.export'));
	const URL_FEUILLE = @js($urlFeuille);
	const URL_FICHE = @js($urlFiche);
	const SUITES = @js($suites);
	const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const COULEURS = { retard: 'rouge', renvoi: 'orange', delibere: 'indigo', juge: 'vert', radie: 'gris', joint: 'sarcelle', desiste: 'violet' };
	const ICONES = { retard: 'fa-triangle-exclamation', renvoi: 'fa-arrows-rotate', delibere: 'fa-hourglass-half', juge: 'fa-gavel', radie: 'fa-ban', joint: 'fa-link', desiste: 'fa-handshake' };
	const f = { suite: '', chambre: '', du: '', au: '', q: '', page: 1 };
	let compteurs = {}, requete = 0, minuteur = null;

	const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

	function parametres(avecPage) {
		const p = new URLSearchParams();
		['suite', 'chambre', 'du', 'au', 'q'].forEach(function (k) { if (f[k]) { p.set(k, f[k]); } });
		if (avecPage) { p.set('page', f.page); }
		return p.toString();
	}

	function kpis(animer) {
		const kpiPct = function (p) { if (p == null || isNaN(p)) { return ''; } const v = Math.round(Math.max(0, Math.min(100, p))); return ' data-pct="' + v + '" style="--pct:' + v + '"'; };
		const total = Object.keys(SUITES).reduce(function (a, k) { return a + (compteurs[k] || 0); }, 0);
		const finales = (compteurs.juge || 0) + (compteurs.radie || 0) + (compteurs.joint || 0) + (compteurs.desiste || 0);
		const cartes = [
			['ardoise', 'fa-list-check', 'Suites notées', total, 'fa-filter', 'avec les filtres en cours'],
			['vert', 'fa-gavel', 'Jugées', compteurs.juge || 0, 'fa-flag-checkered', finales + ' affaire' + (finales > 1 ? 's' : '') + ' sorties des rôles', (total > 0 ? (compteurs.juge || 0) / total * 100 : null)],
			['orange', 'fa-arrows-rotate', 'Renvoyées', compteurs.renvoi || 0, 'fa-calendar-day', 'reviennent à leur date', (total > 0 ? (compteurs.renvoi || 0) / total * 100 : null)],
			['sarcelle', 'fa-hourglass-half', 'En délibéré', compteurs.delibere || 0, 'fa-scale-balanced', 'décisions à rendre', (total > 0 ? (compteurs.delibere || 0) / total * 100 : null)],
		];
		$('#kpis-suites').html(cartes.map(function (k) {
			return '<div class="au-kpi au-kpi--' + k[0] + '"' + kpiPct(k[6]) + '><div class="au-kpi__icone"><i class="fa-solid ' + k[1] + '"></i></div><span class="au-kpi__label">' + esc(k[2]) + '</span><div><div class="au-kpi__valeur"><span class="au-compteur" data-valeur="' + k[3] + '">' + k[3] + '</span></div><div class="au-chip"><i class="fa-solid ' + k[4] + '"></i><span>' + esc(k[5]) + '</span></div></div></div>';
		}).join(''));
		if (reduit || !animer) { return; }
		document.querySelectorAll('#kpis-suites .au-compteur').forEach(function (el) {
			const cible = parseInt(el.getAttribute('data-valeur'), 10) || 0; if (cible <= 0) { return; }
			let debut = null; el.textContent = '0'; setTimeout(function () { el.textContent = cible; }, 1200);
			window.requestAnimationFrame(function pas(t) { if (debut === null) { debut = t; } const p = Math.min(1, (t - debut) / 600); el.textContent = Math.round(cible * (1 - Math.pow(1 - p, 3))); if (p < 1) { window.requestAnimationFrame(pas); } });
		});
	}

	function filtresSuite() {
		const total = Object.keys(SUITES).reduce(function (a, k) { return a + (compteurs[k] || 0); }, 0);
		let html = '<button type="button" class="au-filtre-etat ' + (f.suite === '' ? 'is-actif' : '') + '" data-suite="">Toutes<b>' + total + '</b></button>';
		Object.keys(SUITES).forEach(function (k) { html += '<button type="button" class="au-filtre-etat ' + (f.suite === k ? 'is-actif' : '') + '" data-suite="' + k + '"><i class="fa-solid ' + ICONES[k] + '"></i>' + esc(SUITES[k]) + '<b>' + (compteurs[k] || 0) + '</b></button>'; });
		html += '<button type="button" class="au-filtre-etat au-filtre-etat--alerte ' + (f.suite === 'retard' ? 'is-actif' : '') + '" data-suite="retard"><i class="fa-solid fa-triangle-exclamation"></i>En retard<b>' + (compteurs.retard || 0) + '</b></button>';
		$('#filtres-suite').html(html);
	}

	/* Les dates qui situent l'affaire : son enrôlement et, quand elle est connue, sa première audience. */
	function datesAffaire(l) {
		const une = function (libelle, v) { return v ? '<div><span>' + libelle + '</span><b>' + esc(v) + '</b></div>' : ''; };
		return une('Enrôlée le', l.enrolee_le) + une('1re audience', l.premiere_le) || '<span class="au-tbl__vide">—</span>';
	}

	/* Pendant le chargement, des lignes grisées qui pulsent montrent que la liste arrive. */
	function squelette() {
		let h = '';
		for (let i = 0; i < 6; i++) {
			h += '<tr class="au-sq"><td><i style="width:70px"></i></td><td><i style="width:60px"></i><i style="width:90px"></i></td><td><i style="width:85%"></i><i style="width:55%"></i></td><td><i style="width:120px"></i><i style="width:130px"></i></td><td><i style="width:84px;height:22px;border-radius:99px"></i></td><td><i style="width:75%"></i></td><td></td></tr>';
		}
		return h;
	}

	function ligne(l) {
		const num = URL_FICHE && l.affaire_id ? '<a class="au-lg__fiche" href="' + esc(URL_FICHE.replace(/\/0$/, '/' + l.affaire_id)) + '" target="_blank" rel="noopener" title="Ouvrir la fiche de l\'affaire">' + esc(l.numero || '—') + '<i class="fa-solid fa-arrow-up-right-from-square"></i></a>' : esc(l.numero || '—');
		const voir = URL_FEUILLE && l.role_id ? '<a class="au-iconbtn" href="' + esc(URL_FEUILLE.replace(/\/0$/, '/' + l.role_id)) + '" title="Voir la feuille de cette audience" aria-label="Voir la feuille de cette audience"><i class="fa-solid fa-clipboard-list"></i></a>' : '';
		return '<tr><td class="au-tbl__date">' + (l.date_libelle ? '<span class="au-badge au-badge--date"><i class="fa-regular fa-calendar"></i>' + esc(l.date_libelle) + '</span>' : '<span class="au-tbl__vide">—</span>') + '</td>'
			+ '<td><div class="au-tbl__num">' + num + '</div><small>' + esc(l.chambre) + '</small></td>'
			+ '<td><div class="au-tbl__parties">' + esc(l.parties || '—') + '</div>' + (l.objet ? '<small>' + esc(l.objet) + '</small>' : '') + '</td>'
			+ '<td class="au-tbl__dates">' + datesAffaire(l) + '</td>'
			+ '<td><span class="au-badge au-badge--' + COULEURS[l.suite] + '"><i class="fa-solid ' + ICONES[l.suite] + '"></i>' + esc(l.suite_libelle) + '</span></td>'
			+ '<td class="au-tbl__detail">' + (l.detail ? esc(l.detail) : '<span class="au-tbl__vide">—</span>') + '</td><td class="au-tbl__act">' + voir + '</td></tr>';
	}

	function pagination(j) {
		if (!j.total) { $('#pagination-suites').empty(); return; }
		const debut = (j.page - 1) * j.par_page + 1, fin = Math.min(j.total, j.page * j.par_page);
		const pages = []; for (let i = 1; i <= j.pages; i++) { if (i === 1 || i === j.pages || Math.abs(i - j.page) <= 2) { pages.push(i); } }
		let nav = '<button type="button" data-p="' + (j.page - 1) + '"' + (j.page <= 1 ? ' disabled' : '') + ' aria-label="Page précédente"><i class="fa-solid fa-angle-left"></i></button>', prec = 0;
		pages.forEach(function (i) { if (i - prec > 1) { nav += '<span class="au-pagination__points">…</span>'; } nav += '<button type="button" data-p="' + i + '"' + (i === j.page ? ' class="is-actif" aria-current="page"' : '') + '>' + i + '</button>'; prec = i; });
		nav += '<button type="button" data-p="' + (j.page + 1) + '"' + (j.page >= j.pages ? ' disabled' : '') + ' aria-label="Page suivante"><i class="fa-solid fa-angle-right"></i></button>';
		$('#pagination-suites').html('<span>' + debut + ' à ' + fin + ' sur ' + j.total + ' suite' + (j.total > 1 ? 's' : '') + '</span><nav>' + nav + '</nav>');
	}

	function liens() {
		const q = parametres(false);
		$('#export-xlsx').attr('href', URL_EXPORT + '?format=xlsx' + (q ? '&' + q : ''));
		$('#export-pdf').attr('href', URL_EXPORT + '?format=pdf' + (q ? '&' + q : ''));
		$('#f-reinit').prop('hidden', !(f.suite || f.chambre || f.du || f.au || f.q));
	}

	function charger() {
		const numero = ++requete; liens();
		$('.au-tbl-carte').addClass('is-charge');
		if (!$('#suites-corps tr').length) { $('#suites-corps').html(squelette()); }
		fetch(URL_DONNEES + '?' + parametres(true), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { if (!r.ok) { throw new Error('Erreur'); } return r.json(); })
			.then(function (j) {
				if (numero !== requete) { return; }
				const premiere = $.isEmptyObject(compteurs);
				compteurs = j.compteurs; kpis(premiere); filtresSuite();
				$('#suites-corps').html(j.lignes.length ? j.lignes.map(ligne).join('') : '<tr><td colspan="7"><div class="au-vide"><i class="fa-solid fa-scale-balanced"></i>Aucune suite ne correspond à ces filtres.</div></td></tr>');
				pagination(j);
			})
			.catch(function () { $('#suites-corps').html('<tr><td colspan="7"><div class="au-vide"><i class="fa-solid fa-triangle-exclamation"></i>Impossible de charger la liste. Réessayez.</div></td></tr>'); })
			.then(function () { if (numero === requete) { $('.au-tbl-carte').removeClass('is-charge'); } });
	}

	document.addEventListener('DOMContentLoaded', function () {
		charger();
		$('#filtres-suite').on('click', '.au-filtre-etat', function () { f.suite = this.getAttribute('data-suite') || ''; f.page = 1; charger(); });
		$('#f-chambre').on('change', function () { f.chambre = this.value; f.page = 1; charger(); });
		$('#f-du, #f-au').on('change', function () { f.du = $('#f-du').val(); f.au = $('#f-au').val(); f.page = 1; charger(); });
		$('#f-q').on('input', function () { const v = this.value; clearTimeout(minuteur); minuteur = setTimeout(function () { f.q = v.trim(); f.page = 1; charger(); }, 300); });
		$('#pagination-suites').on('click', 'button:not(:disabled)', function () { f.page = parseInt(this.getAttribute('data-p'), 10) || 1; charger(); window.scrollTo({ top: $('.au-cadre').offset().top - 150, behavior: reduit ? 'auto' : 'smooth' }); });
		$('#f-reinit').on('click', function () { f.suite = f.chambre = f.du = f.au = f.q = ''; f.page = 1; $('#f-chambre').val('').trigger('change.select2'); $('#f-du, #f-au, #f-q').val(''); charger(); });
	});
})();
</script>
