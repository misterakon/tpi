<script type="text/javascript">
(function () {
	const U = { donnees: "{{ route('statistiques.civil.donnees') }}", detail: "{{ route('statistiques.civil.detail') }}", qualifier: "{{ route('statistiques.civil.qualifier') }}", export: "{{ route('statistiques.civil.export') }}" };
	const PEUT_DETAIL = @js($peutDetail), PEUT_QUALIFIER = @js($peutQualifier), CSRF = "{{ csrf_token() }}";
	const MOIS = ['Janv.', 'Févr.', 'Mars', 'Avril', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
	const VERT = '#00794c', ORANGE = '#f08a24';
	let D = null, graphiques = {}, minuteur = null;

	const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const nb = (n) => (n == null ? '–' : Number(n).toLocaleString('fr-FR'));
	const $id = (i) => document.getElementById(i);
	const annee = new Date().getFullYear();

	function remplirAnnees() {
		const opt = (a, sel) => '<option value="' + a + '"' + (a === sel ? ' selected' : '') + '>' + a + '</option>';
		let h1 = '', h2 = '', h3 = '';
		for (let a = annee + 1; a >= annee - 14; a--) { h1 += opt(a, annee - 4); h2 += opt(a, annee); h3 += opt(a, annee); }
		$id('f-depuis').innerHTML = h1; $id('f-jusqua').innerHTML = h2; $id('f-mois').innerHTML = h3; ['f-depuis', 'f-jusqua', 'f-mois'].forEach(function (i) { $('#' + i).trigger('change.select2'); });
	}
	function params() {
		const p = new URLSearchParams({ depuis: $id('f-depuis').value, jusqua: $id('f-jusqua').value, annee_mois: $id('f-mois').value });
		if ($id('f-chambre').value) { p.set('chambre', $id('f-chambre').value); }
		return p;
	}
	function majExports() {
		const p = params();
		['xlsx', 'pdf'].forEach(function (f) { const a = $id('st-' + f); if (a) { p.set('format', f); a.href = U.export + '?' + p.toString(); } });
	}

	function charger() {
		clearTimeout(minuteur);
		minuteur = setTimeout(function () {
			majExports();
			$id('st-tuiles').innerHTML = '<div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div>';
			fetch(U.donnees + '?' + params().toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
				.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
				.then(function (d) { D = d; afficher(); })
				.catch(function () { $id('st-tuiles').innerHTML = '<div class="st-vide">Impossible de calculer les statistiques. Actualisez la page.</div>'; });
		}, 150);
	}

	function afficher() {
		const k = D.kpis;
		$id('st-resume').innerHTML = '<i class="fa-solid fa-scale-balanced"></i> ' + nb(D.total_affaires) + ' affaire(s) civile(s) analysée(s)' + (D.sans_audience ? ' · <i class="fa-solid fa-triangle-exclamation"></i> ' + D.sans_audience + ' sans date de première audience (comptées à la date d\'enrôlement)' : '');
		const evo = (k.evolution != null && k.evolution > 0) ? Math.round((k.nouvelles - k.evolution) / k.evolution * 100) : null;
		const tendance = evo == null ? '' : '<span class="st-tendance ' + (evo >= 0 ? 'st-tendance--haut' : 'st-tendance--bas') + '">' + (evo >= 0 ? '▲ +' : '▼ ') + evo + ' %</span>';
		$id('st-tuiles').innerHTML =
			tuile('Stock en début ' + k.annee, k.stock_debut, 'affaires en cours au 1er janvier', 'gris', 'fa-box-archive') +
			tuile('Affaires nouvelles', k.nouvelles, 'en ' + k.annee + ' ' + tendance, 'orange', 'fa-folder-plus') +
			tuile('Affaires vidées', k.videes, k.taux == null ? 'aucune affaire nouvelle' : k.taux + ' % des affaires nouvelles', 'vert', 'fa-circle-check') +
			tuile('Stock en fin ' + k.annee, k.stock_fin, 'début + nouvelles − vidées', 'bleu', 'fa-hourglass-half');
		graphes(); onglets();
		const nq = D.a_qualifier.total, p = $id('st-nb-qualifier');
		p.hidden = nq === 0; p.textContent = nq;
		panneaux();
	}
	function tuile(titre, valeur, sous, couleur, icone) {
		return '<div class="st-tuile st-tuile--' + couleur + '"><span class="st-tuile__icone"><i class="fa-solid ' + icone + '"></i></span><div class="st-tuile__etiquette">' + esc(titre) + '</div><div class="st-tuile__valeur">' + (typeof valeur === 'number' ? nb(valeur) : esc(valeur == null ? '–' : valeur)) + '</div><div class="st-tuile__sous">' + sous + '</div></div>';
	}

	function graphes() {
		const r = D.recap, T = r.total, A = r.annees;
		Object.values(graphiques).forEach(function (g) { try { g.destroy(); } catch (e) {} });
		graphiques = {};
		const base = { chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { speed: 500 } }, grid: { borderColor: '#eef2ef' }, dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left' } };
		graphiques.a = new ApexCharts($id('g-annees'), Object.assign({}, base, {
			chart: Object.assign({}, base.chart, { type: 'line', height: 300 }), stroke: { width: [0, 0, 3], curve: 'smooth' }, colors: [ORANGE, VERT, '#2f6fd0'],
			series: [{ name: 'Nouvelles', type: 'column', data: A.map(function (a) { return T.nouvelles[a]; }) }, { name: 'Vidées', type: 'column', data: A.map(function (a) { return T.videes[a]; }) }, { name: 'Stock en fin d\'année', type: 'line', data: A.map(function (a) { return T.stock_fin[a]; }) }],
			xaxis: { categories: A.map(String) }, plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
		})); graphiques.a.render();
		const cats = Object.keys(r.categories).filter(function (c) { return r.par_categorie[c].stock_fin[D.kpis.annee] > 0; });
		$id('g-stock-annee').textContent = 'fin ' + D.kpis.annee;
		if (cats.length) {
			graphiques.c = new ApexCharts($id('g-categories'), Object.assign({}, base, {
				chart: Object.assign({}, base.chart, { type: 'donut', height: 300 }), labels: cats.map(function (c) { return r.categories[c]; }), series: cats.map(function (c) { return r.par_categorie[c].stock_fin[D.kpis.annee]; }),
				colors: [VERT, ORANGE, '#2f6fd0', '#8e44ad', '#19a06b', '#e0a800', '#c0392b', '#16a2b8', '#6c757d', '#a0522d'], legend: { position: 'bottom', fontSize: '11px' }, plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Stock', formatter: function (w) { return nb(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)); } } } } } },
			})); graphiques.c.render();
		} else { $id('g-categories').innerHTML = '<div class="st-vide">Aucune affaire en stock cette année.</div>'; }
		const M = r.mois.total; $id('g-mois-annee').textContent = r.annee_mois;
		graphiques.m = new ApexCharts($id('g-mois'), Object.assign({}, base, {
			chart: Object.assign({}, base.chart, { type: 'line', height: 260 }), stroke: { width: [0, 0, 3], curve: 'smooth' }, colors: [ORANGE, VERT, '#2f6fd0'],
			series: [{ name: 'Nouvelles', type: 'column', data: Object.values(M.nouvelles) }, { name: 'Vidées', type: 'column', data: Object.values(M.videes) }, { name: 'Stock en fin de mois', type: 'line', data: Object.values(M.stock_fin) }],
			xaxis: { categories: MOIS }, plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
		})); graphiques.m.render();
	}

	/* ---------- Tableaux ---------- */
	function tableau(titre, colonnes, lignes, opt) {
		opt = opt || {};
		let h = '<div class="st-bloc"><div class="st-bloc__titre"><span>' + esc(titre) + '</span></div><div class="st-table-wrap"><table class="st-table"><thead><tr><th></th>';
		colonnes.forEach(function (c) { h += '<th class="' + (c.courante ? 'st-annee-courante' : '') + '">' + esc(c.titre) + '</th>'; });
		h += '</tr></thead><tbody>';
		lignes.forEach(function (l, i) {
			const total = i === lignes.length - 1 && opt.total !== false;
			h += '<tr' + (total ? ' class="st-total"' : '') + '><td>' + esc(l.libelle) + '</td>';
			l.valeurs.forEach(function (v, j) {
				const col = colonnes[j], clic = PEUT_DETAIL && v > 0 && col.detail && !total ? ' data-d="' + esc(JSON.stringify(Object.assign({ indicateur: opt.indicateur, categorie: l.cle, sous: l.sous }, col.detail))) + '"' : '';
				h += '<td class="' + (v === 0 ? 'st-zero ' : '') + (clic ? 'st-cliquable ' : '') + (col.courante ? 'st-annee-courante' : '') + '"' + clic + '>' + nb(v) + '</td>';
			});
			h += '</tr>';
		});
		return h + '</tbody></table></div></div>';
	}
	const colsAnnees = function (stockDebut) {
		const A = D.recap.annees, c = [];
		if (stockDebut) { c.push({ titre: 'Stock début ' + A[0] }); }
		A.forEach(function (a) { c.push({ titre: String(a), courante: a === D.kpis.annee, detail: { annee: a } }); });
		return c;
	};
	function lignesCat(cle, avecDebut) {
		const r = D.recap, A = r.annees, out = [];
		Object.keys(r.categories).forEach(function (c) { const b = r.par_categorie[c]; out.push({ cle: c, libelle: r.categories[c], valeurs: (avecDebut ? [b.stock_debut] : []).concat(A.map(function (a) { return b[cle][a]; })) }); });
		const t = r.total; out.push({ cle: 'total', libelle: 'Total', valeurs: (avecDebut ? [t.stock_debut] : []).concat(A.map(function (a) { return t[cle][a]; })) });
		return out;
	}
	function lignesSous(etat, cle) {
		const A = etat.annees, out = [], tot = A.map(function () { return 0; });
		Object.keys(etat.lignes).forEach(function (s) { const l = etat.lignes[s]; const v = A.map(function (a) { return l[cle][a]; }); v.forEach(function (n, i) { tot[i] += n; }); out.push({ cle: etat === D.etat_civil ? 'etat_civil' : 'matrimoniale', sous: s, libelle: l.libelle, valeurs: v }); });
		out.push({ cle: 'total', libelle: 'Total', valeurs: tot });
		return out;
	}

	function panneaux() {
		const r = D.recap, A = r.annees;
		$id('p-annuel').innerHTML =
			tableau("État des stocks en fin d'année", colsAnnees(true), lignesCat('stock_fin', true), { indicateur: 'stock_fin' }) +
			tableau("Affaires à juger selon l'année", colsAnnees(false), lignesCat('a_juger', false), { indicateur: 'nouvelles' }) +
			tableau("Affaires nouvelles selon l'année", colsAnnees(false), lignesCat('nouvelles', false), { indicateur: 'nouvelles' }) +
			tableau('Affaires vidées par an', colsAnnees(false), lignesCat('videes', false), { indicateur: 'videes' }) +
			'<p class="st-note">Une affaire est « nouvelle » l\'année de sa première audience et « vidée » l\'année de sa décision (décision au fond, radiation, PV de conciliation totale, désistement). Stock fin = stock début + nouvelles − vidées.' + (PEUT_DETAIL ? ' Cliquez sur un chiffre pour voir les affaires.' : '') + '</p>';

		const mois = r.mois, moisCol = MOIS.map(function (m, i) { return { titre: m, detail: { annee: r.annee_mois, mois: i + 1 } }; });
		const ordre = function (cle) { const l = []; Object.keys(r.categories).forEach(function (c) { l.push({ cle: c, libelle: r.categories[c], valeurs: Object.values(mois[c][cle]) }); }); l.push({ cle: 'total', libelle: 'Total', valeurs: Object.values(mois.total[cle]) }); return l; };
		$id('p-mensuel').innerHTML =
			'<p class="st-note" style="margin:0 0 10px">Année <b>' + r.annee_mois + '</b> (modifiable dans « Année du détail mensuel »). Stock au 1er janvier : <b>' + nb(mois.total.stock_debut) + '</b>.</p>' +
			tableau('Stock en fin de mois', moisCol, ordre('stock_fin'), { indicateur: 'stock_fin' }) + tableau('Affaires nouvelles par mois', moisCol, ordre('nouvelles'), { indicateur: 'nouvelles' }) + tableau('Affaires vidées par mois', moisCol, ordre('videes'), { indicateur: 'videes' });

		const sousPanneau = function (etat, nom) {
			const cols = colsAnnees(false);
			let h = tableau('Stock en fin d\'année : ' + nom, [{ titre: 'Stock début ' + A[0] }].concat(cols), (function () { const L = Object.keys(etat.lignes).map(function (s) { const l = etat.lignes[s]; return { cle: nom === 'état civil' ? 'etat_civil' : 'matrimoniale', sous: s, libelle: l.libelle, valeurs: [l.stock_debut].concat(A.map(function (a) { return l.stock_fin[a]; })) }; }); const tot = L[0].valeurs.map(function (_, i) { return L.reduce(function (s, l) { return s + l.valeurs[i]; }, 0); }); L.push({ cle: 'total', libelle: 'Total', valeurs: tot }); return L; })(), { indicateur: 'stock_fin' });
			h += tableau('Affaires reçues par an', cols, lignesSous(etat, 'nouvelles'), { indicateur: 'nouvelles' }) + tableau('Affaires vidées par an', cols, lignesSous(etat, 'videes'), { indicateur: 'videes' });
			h += tableau('Demandes accordées (année de la décision)', cols, lignesSous(etat, 'accordees'), { indicateur: 'accordees' }) + tableau('Demandes rejetées (année de la décision)', cols, lignesSous(etat, 'rejetees'), { indicateur: 'rejetees' });
			return h;
		};
		$id('p-etat_civil').innerHTML = sousPanneau(D.etat_civil, 'état civil') + '<p class="st-note">La sous-catégorie est déduite de l\'objet du litige. L\'onglet « À compléter » permet de la corriger et de préciser accordée ou rejetée.</p>';
		const adoption = D.matrimonial;
		$id('p-matrimonial').innerHTML = sousPanneau(adoption, 'matrimonial') +
			tableau('Adoption : filles adoptées (demandes accordées)', colsAnnees(false), lignesSous(adoption, 'filles'), { indicateur: 'accordees' }) + tableau('Adoption : garçons adoptés (demandes accordées)', colsAnnees(false), lignesSous(adoption, 'garcons'), { indicateur: 'accordees' });

		const d = D.durees;
		$id('p-durees').innerHTML = '<div class="st-tuiles">' +
			tuile('Durée moyenne', d.moyenne == null ? null : d.moyenne + ' j', 'de la 1re audience à la décision', 'orange', 'fa-clock') +
			tuile('Durée médiane', d.mediane == null ? null : d.mediane + ' j', 'la moitié des affaires est jugée plus vite', 'vert', 'fa-chart-simple') +
			tuile('Jugées dans le délai', d.dans_le_delai == null ? null : d.dans_le_delai + ' %', 'délai de l\'ordonnance : ' + d.delai_jours + ' jours (6 mois)', 'bleu', 'fa-gauge-high') +
			tuile('Dates manquantes', d.manquantes, 'sur ' + d.jugees + ' jugée(s) en ' + D.kpis.annee + ' : non mesurées', 'gris', 'fa-circle-question') + '</div>' +
			(d.dans_le_delai == null ? '' : '<div class="st-carte"><h3>Part des affaires jugées dans le délai</h3><div class="st-jauge"><i style="width:' + d.dans_le_delai + '%"></i></div></div>') +
			'<p class="st-note">Durées calculées sur les affaires jugées au fond en ' + D.kpis.annee + ' dont la date de première audience est connue.</p>';

		const q = D.a_qualifier;
		$id('p-qualifier').innerHTML = q.total === 0 ? '<div class="st-vide"><i class="fa-solid fa-circle-check" style="font-size:34px;color:#00794c"></i><p>Rien à compléter : toutes les affaires d\'état civil et matrimoniales jugées sont précisées.</p></div>' :
			'<p class="st-note" style="margin:0 0 10px"><b>' + q.total + '</b> affaire(s) jugée(s) au fond en état civil ou matrimonial sans « accordée ou rejetée ». Elles ne figurent pas encore dans les tableaux des demandes accordées et rejetées.</p>' +
			(PEUT_QUALIFIER ? q.liste.map(function (x) { return '<div class="st-qualifier"><div class="st-qualifier__texte"><b>' + esc(x.numero) + ' · ' + esc(x.categorie) + '</b><span>' + esc(x.objet) + ' · jugée le ' + esc(x.date) + '</span></div><button type="button" class="el-btn el-btn--accent" data-q="' + x.id + '" data-n="' + esc(x.numero) + '">Préciser</button></div>'; }).join('') + (q.total > q.liste.length ? '<p class="st-note">… et ' + (q.total - q.liste.length) + ' autre(s).</p>' : '') : '<p class="st-note">Vous n\'avez pas le droit de préciser les affaires.</p>');
	}

	/* ---------- Onglets, détail, qualification ---------- */
	function onglets() { /* rien à recalculer : les onglets ne dépendent que du DOM */ }
	document.addEventListener('click', function (e) {
		const o = e.target.closest('.st-onglet');
		if (o) {
			document.querySelectorAll('.st-onglet').forEach(function (b) { b.classList.toggle('is-actif', b === o); });
			document.querySelectorAll('.st-panneau').forEach(function (p) { p.hidden = p.id !== 'p-' + o.dataset.panneau; });
			if (o.dataset.panneau === 'vue') { Object.values(graphiques).forEach(function (g) { try { g.windowResizeHandler(); } catch (x) {} }); }
			return;
		}
		const c = e.target.closest('td[data-d]');
		if (c) { ouvrirDetail(JSON.parse(c.dataset.d)); return; }
		const q = e.target.closest('button[data-q]');
		if (q) { ouvrirQualifier(q.dataset.q, q.dataset.n); }
	});

	function ouvrirDetail(d) {
		const p = new URLSearchParams(d); if ($id('f-chambre').value) { p.set('chambre', $id('f-chambre').value); }
		Object.keys(d).forEach(function (k) { if (d[k] == null || d[k] === '') { p.delete(k); } });
		$id('t-detail').textContent = 'Affaires concernées'; $id('c-detail').innerHTML = '<div class="st-squelette"></div>';
		$('#modal-detail').modal('show');
		fetch(U.detail + '?' + p.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (x) {
			$id('t-detail').textContent = x.total + ' affaire(s)';
			$id('c-detail').innerHTML = x.total === 0 ? '<div class="st-vide">Aucune affaire.</div>' :
				'<table class="el-tbl"><thead><tr><th>Affaire</th><th>Objet</th><th>Chambre</th><th>1re audience</th><th>Sortie</th></tr></thead><tbody>' + x.lignes.map(function (l) {
					return '<tr><td><b>' + esc(l.numero) + '</b></td><td>' + esc(l.objet) + '</td><td>' + esc(l.chambre) + '</td><td>' + esc(l.entree) + '</td><td>' + (l.sortie ? esc(l.sortie) + '<div class="el-person__sub">' + esc(l.issue || '') + (l.resultat ? ' · ' + esc(l.resultat) : '') + '</div>' : '—') + '</td></tr>';
				}).join('') + '</tbody></table>' + (x.total > x.lignes.length ? '<p class="st-note">Les ' + x.lignes.length + ' premières affaires sur ' + x.total + '.</p>' : '');
		}).catch(function () { $id('c-detail').innerHTML = '<div class="st-vide">Détail indisponible.</div>'; });
	}

	function ouvrirQualifier(id, numero) {
		const f = $id('f-qualifier'); f.reset(); $id('q_affaire').value = id; $id('q_erreur').hidden = true;
		$id('t-qualifier').textContent = 'Préciser l\'affaire ' + numero;
		['q_resultat', 'q_sous', 'q_type', 'q_caractere', 'q_aiv', 'q_aet'].forEach(function (i) { $('#' + i).val('').trigger('change.select2'); });
		$('#modal-qualifier').modal('show');
	}
	const fq = $id('f-qualifier');
	if (fq) {
		fq.addEventListener('submit', function (e) {
			e.preventDefault();
			const donnees = new FormData(fq); donnees.append('_token', CSRF);
			fetch(U.qualifier, { method: 'POST', body: donnees, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (x) {
				if (x.ok) { $('#modal-qualifier').modal('hide'); charger(); return; }
				const e1 = $id('q_erreur'); e1.hidden = false; e1.textContent = x.j.errors ? Object.values(x.j.errors)[0][0] : (x.j.message || 'Enregistrement impossible.');
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		remplirAnnees();
		['f-depuis', 'f-jusqua', 'f-mois', 'f-chambre'].forEach(function (i) {
			$('#' + i).on('change', function () {
				if (i === 'f-jusqua' && +$id('f-depuis').value > +this.value) { $id('f-depuis').value = this.value; $('#f-depuis').trigger('change.select2'); }
				if (i === 'f-depuis' && +$id('f-jusqua').value < +this.value) { $id('f-jusqua').value = this.value; $('#f-jusqua').trigger('change.select2'); }
				charger();
			});
		});
		charger();
	});
})();
</script>
