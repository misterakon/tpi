<script type="text/javascript">
(function () {
	const U = { donnees: "{{ route('statistiques.penal.donnees') }}", export: "{{ route('statistiques.penal.export') }}" };
	const MOIS = ['Janv.', 'Févr.', 'Mars', 'Avril', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
	const VERT = '#00794c', ORANGE = '#f08a24', BLEU = '#2f6fd0';
	let D = null, G = {}, minuteur = null;
	const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const nb = (n) => (n == null ? '–' : Number(n).toLocaleString('fr-FR'));
	const $id = (i) => document.getElementById(i);
	const annee = new Date().getFullYear();

	function remplirAnnees() {
		const opt = (a, sel) => '<option value="' + a + '"' + (a === sel ? ' selected' : '') + '>' + a + '</option>';
		let h1 = '', h2 = '';
		for (let a = annee + 1; a >= annee - 14; a--) { h1 += opt(a, annee - 2); h2 += opt(a, annee); }
		$id('p-depuis').innerHTML = h1; $id('p-jusqua').innerHTML = h2; ['p-depuis', 'p-jusqua'].forEach(function (i) { $('#' + i).trigger('change.select2'); });
	}
	function params() {
		const p = new URLSearchParams({ depuis: $id('p-depuis').value, jusqua: $id('p-jusqua').value });
		if ($id('p-chambre').value) { p.set('chambre', $id('p-chambre').value); }
		return p;
	}
	function majExports() { const p = params(); ['xlsx', 'pdf'].forEach(function (f) { const a = $id('sp-' + f); if (a) { p.set('format', f); a.href = U.export + '?' + p.toString(); } }); }

	function charger() {
		clearTimeout(minuteur);
		minuteur = setTimeout(function () {
			majExports();
			$id('sp-tuiles').innerHTML = '<div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div>';
			fetch(U.donnees + '?' + params().toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
				.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
				.then(function (d) { D = d; afficher(); })
				.catch(function () { $id('sp-tuiles').innerHTML = '<div class="st-vide">Impossible de calculer les statistiques. Actualisez la page.</div>'; });
		}, 150);
	}
	function tuile(titre, valeur, sous, couleur, icone) {
		return '<div class="st-tuile st-tuile--' + couleur + '"><span class="st-tuile__icone"><i class="fa-solid ' + icone + '"></i></span><div class="st-tuile__etiquette">' + esc(titre) + '</div><div class="st-tuile__valeur">' + valeur + '</div><div class="st-tuile__sous">' + sous + '</div></div>';
	}
	function graphique(id, options) {
		if (G[id]) { try { G[id].destroy(); } catch (e) {} }
		const el = $id(id);
		if (options.vide) { el.innerHTML = '<div class="st-vide">Aucune donnée sur la période.</div>'; return; }
		el.innerHTML = '';
		G[id] = new ApexCharts(el, Object.assign({ chart: { fontFamily: 'inherit', toolbar: { show: false } }, grid: { borderColor: '#eef2ef' }, dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left' } }, options.cfg));
		G[id].render();
	}
	function barres(id, items, couleur) {
		graphique(id, { vide: items.length === 0, cfg: {
			chart: { type: 'bar', height: Math.max(220, items.length * 34 + 40), fontFamily: 'inherit', toolbar: { show: false } }, series: [{ name: 'Nombre', data: items.map(function (x) { return x.n; }) }],
			xaxis: { categories: items.map(function (x) { return x.libelle.length > 34 ? x.libelle.slice(0, 33) + '…' : x.libelle; }) }, colors: [couleur], plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '70%' } }, dataLabels: { enabled: true },
		} });
	}

	function afficher() {
		const A = D.annees, an = A[A.length - 1], P = D.par_an[an] || {}, tot = A.reduce(function (s, a) { return s + D.par_an[a].decisions; }, 0);
		const cond = A.reduce(function (s, a) { return s + D.par_an[a].condamnes; }, 0), h = A.reduce(function (s, a) { return s + D.par_an[a].hommes; }, 0), f = A.reduce(function (s, a) { return s + D.par_an[a].femmes; }, 0);
		$id('sp-resume').innerHTML = '<i class="fa-solid fa-book-open"></i> ' + nb(D.total) + ' décision(s) au répertoire sur la période';
		$id('sp-tuiles').innerHTML =
			tuile('Décisions rendues', nb(tot), 'sur ' + A.length + ' année(s)', 'orange', 'fa-gavel') +
			tuile('Condamnés', nb(cond), nb(h) + ' homme(s) · ' + nb(f) + ' femme(s)', 'vert', 'fa-user-lock') +
			tuile('Peine moyenne', D.peines.moyenne_mois == null ? '–' : D.peines.moyenne_mois + ' mois', nb(D.peines.avec_emprisonnement) + ' condamnation(s) à de l\'emprisonnement', 'bleu', 'fa-hourglass-half') +
			tuile('Minutes à transmettre', nb(D.duree_minute.non_transmises), nb(D.duree_minute.en_retard) + ' de plus de ' + D.duree_minute.vigilance + ' jours', 'gris', 'fa-file-export');

		graphique('h-annees', { vide: tot === 0, cfg: { chart: { type: 'bar', height: 300, fontFamily: 'inherit', toolbar: { show: false } }, colors: [ORANGE, VERT], plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
			series: [{ name: 'Décisions', data: A.map(function (a) { return D.par_an[a].decisions; }) }, { name: 'Condamnés', data: A.map(function (a) { return D.par_an[a].condamnes; }) }], xaxis: { categories: A.map(String) } } });
		graphique('h-issues', { vide: D.issues.length === 0, cfg: { chart: { type: 'donut', height: 300, fontFamily: 'inherit' }, labels: D.issues.map(function (x) { return x.libelle; }), series: D.issues.map(function (x) { return x.n; }),
			colors: [VERT, ORANGE, BLEU, '#8e44ad', '#c0392b', '#6c757d', '#16a2b8'], legend: { position: 'bottom' }, plotOptions: { pie: { donut: { size: '60%' } } } } });
		$id('h-mois-annee').textContent = D.annee_mois;
		graphique('h-mois', { vide: tot === 0, cfg: { chart: { type: 'area', height: 250, fontFamily: 'inherit', toolbar: { show: false } }, colors: [ORANGE, VERT], stroke: { width: 3, curve: 'smooth' }, fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
			series: [{ name: 'Décisions', data: Object.values(D.mois).map(function (m) { return m.decisions; }) }, { name: 'Condamnés', data: Object.values(D.mois).map(function (m) { return m.condamnes; }) }], xaxis: { categories: MOIS } } });

		barres('h-infractions', D.infractions, ORANGE); barres('h-nationalites', D.nationalites, VERT); barres('h-professions', D.professions, BLEU);
		graphique('h-natures', { vide: D.natures.every(function (x) { return x.n === 0; }), cfg: { chart: { type: 'donut', height: 240, fontFamily: 'inherit' }, labels: D.natures.map(function (x) { return x.libelle; }), series: D.natures.map(function (x) { return x.n; }), colors: [BLEU, ORANGE, VERT], legend: { position: 'bottom' } } });
		const p = D.peines;
		$id('h-peines').innerHTML = '<div class="st-note"><b>' + nb(p.avec_emprisonnement) + '</b> peine(s) d\'emprisonnement (moyenne ' + (p.moyenne_mois == null ? '–' : p.moyenne_mois + ' mois') + ') · <b>' + nb(p.avec_sursis) + '</b> avec sursis · <b>' + nb(p.amendes) + '</b> amende(s) pour <b>' + nb(p.total_amendes) + ' F CFA</b>.</div>';

		const v = D.victimes, totV = v.hommes + v.femmes + v.garcons + v.filles;
		$id('q-victimes').innerHTML = '<div class="st-tuiles">' + tuile('Victimes', nb(totV), 'toutes décisions de la période', 'orange', 'fa-people-group') + tuile('Hommes', nb(v.hommes), '', 'bleu', 'fa-person') + tuile('Femmes', nb(v.femmes), '', 'vert', 'fa-person-dress') +
			tuile('Mineurs', nb(v.garcons + v.filles), nb(v.garcons) + ' garçon(s) · ' + nb(v.filles) + ' fille(s)', 'gris', 'fa-child') + '</div>' +
			'<div class="st-carte"><h3>Traite des personnes</h3><div class="st-tuile__valeur">' + nb(A.reduce(function (s, a) { return s + D.par_an[a].traite; }, 0)) + '</div><div class="st-note">décision(s) pour traite des personnes, par année : ' + A.map(function (a) { return a + ' : ' + D.par_an[a].traite; }).join(' · ') + '</div></div>';

		const da = D.duree_audience, dm = D.duree_minute;
		$id('q-durees').innerHTML = '<div class="st-tuiles">' +
			tuile('1re audience → décision', da.moyenne == null ? '–' : da.moyenne + ' j', 'médiane : ' + (da.mediane == null ? '–' : da.mediane + ' j'), 'orange', 'fa-clock') +
			tuile('Jugées dans le délai', da.dans_le_delai == null ? '–' : da.dans_le_delai + ' %', 'délai de l\'ordonnance : ' + da.delai_jours + ' jours (3 mois)', 'vert', 'fa-gauge-high') +
			tuile('Décision → minute', dm.moyenne == null ? '–' : dm.moyenne + ' j', 'médiane : ' + (dm.mediane == null ? '–' : dm.mediane + ' j'), 'bleu', 'fa-file-export') +
			tuile('Dates manquantes', nb(da.manquantes) + ' · ' + nb(dm.manquantes), 'audience · minute : non mesurées', 'gris', 'fa-circle-question') + '</div>' +
			(da.dans_le_delai == null ? '' : '<div class="st-carte"><h3>Part des décisions rendues dans le délai</h3><div class="st-jauge"><i style="width:' + da.dans_le_delai + '%"></i></div></div>') +
			'<p class="st-note">Une durée n\'est calculée que si les deux dates sont connues. Les minutes non transmises sont celles dont la date de transmission n\'est pas encore saisie dans le répertoire pénal.</p>';
	}

	document.addEventListener('click', function (e) {
		const o = e.target.closest('#sp-onglets .st-onglet');
		if (!o) { return; }
		document.querySelectorAll('#sp-onglets .st-onglet').forEach(function (b) { b.classList.toggle('is-actif', b === o); });
		document.querySelectorAll('.st-panneau').forEach(function (p) { p.hidden = p.id !== 'q-' + o.dataset.panneau; });
		window.dispatchEvent(new Event('resize'));
	});
	document.addEventListener('DOMContentLoaded', function () {
		remplirAnnees();
		['p-depuis', 'p-jusqua', 'p-chambre'].forEach(function (i) {
			$('#' + i).on('change', function () {
				if (i === 'p-jusqua' && +$id('p-depuis').value > +this.value) { $id('p-depuis').value = this.value; $('#p-depuis').trigger('change.select2'); }
				if (i === 'p-depuis' && +$id('p-jusqua').value < +this.value) { $id('p-jusqua').value = this.value; $('#p-jusqua').trigger('change.select2'); }
				charger();
			});
		});
		charger();
	});
})();
</script>
