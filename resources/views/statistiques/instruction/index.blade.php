@extends('layout.master')
@section('title', 'Statistiques de l\'instruction')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">
      <x-hero tag="Statistiques" title="Statistiques de l'instruction" subtitle="Dossiers ouverts et clôturés, stock, durée de l'instruction, inculpés (sexe, mineurs, détenus), victimes et commissions rogatoires, comme dans les classeurs des cabinets d'instruction et du juge des enfants." icon="fa-solid fa-magnifying-glass">
        <x-slot:extra><span id="si-resume"><i class="fa-solid fa-spinner fa-spin"></i> Calcul en cours…</span></x-slot:extra>
        <x-slot:actions>
          @can('instruction.voir')<a class="el-btn el-btn--accent" href="{{ route('instruction') }}"><i class="fa-solid fa-folder-open"></i> Dossiers</a>@endcan
          @can('stats_registres.voir')<a class="el-btn st-btn-pdf" href="{{ route('statistiques.registres') }}"><i class="fa-solid fa-file-excel"></i> Registres</a>@endcan
        </x-slot:actions>
      </x-hero>
      <div class="el-card">
        <form class="st-filtres" onsubmit="return false">
          <div class="st-champ"><label for="i-depuis">De l'année</label><select class="form-select" id="i-depuis"></select></div>
          <div class="st-champ"><label for="i-jusqua">À l'année</label><select class="form-select" id="i-jusqua"></select></div>
          <div class="st-champ"><label for="i-chambre">Cabinet</label><select class="form-select" id="i-chambre"><option value="">Tous les cabinets</option>@foreach ($cabinets as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select></div>
        </form>
      </div>
      <div class="st-tuiles" id="si-tuiles"><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div></div>
      <div class="st-grille">
        <div class="st-carte"><h3>Dossiers ouverts et clôturés <small>le stock suit la ligne</small></h3><div id="gi-annees"></div></div>
        <div class="st-carte"><h3>Statut des inculpés</h3><div id="gi-statuts"></div></div>
      </div>
      <div class="st-grille" style="margin-top:14px">
        <div class="st-carte"><h3>Commissions rogatoires par année</h3><div id="gi-cr"></div></div>
        <div class="st-carte"><h3>Victimes</h3><div id="gi-victimes"></div></div>
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
<script>
(function () {
	const URL_D = "{{ route('statistiques.instruction.donnees') }}", VERT = '#00794c', ORANGE = '#f08a24', BLEU = '#2f6fd0';
	let D = null, G = {}, t = null;
	const esc = (x) => String(x == null ? '' : x).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const nb = (n) => (n == null ? '–' : Number(n).toLocaleString('fr-FR'));
	const $id = (i) => document.getElementById(i), annee = new Date().getFullYear();
	function params() { const p = new URLSearchParams({ depuis: $id('i-depuis').value, jusqua: $id('i-jusqua').value }); if ($id('i-chambre').value) { p.set('chambre', $id('i-chambre').value); } return p; }
	function tuile(titre, valeur, sous, couleur, icone) { return '<div class="st-tuile st-tuile--' + couleur + '"><span class="st-tuile__icone"><i class="fa-solid ' + icone + '"></i></span><div class="st-tuile__etiquette">' + esc(titre) + '</div><div class="st-tuile__valeur">' + valeur + '</div><div class="st-tuile__sous">' + sous + '</div></div>'; }
	function graphe(id, vide, cfg) { if (G[id]) { try { G[id].destroy(); } catch (e) {} } const el = $id(id); if (vide) { el.innerHTML = '<div class="st-vide">Aucune donnée sur la période.</div>'; return; } el.innerHTML = ''; G[id] = new ApexCharts(el, Object.assign({ chart: { fontFamily: 'inherit', toolbar: { show: false } }, grid: { borderColor: '#eef2ef' }, dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left' } }, cfg)); G[id].render(); }
	function charger() { clearTimeout(t); t = setTimeout(function () { fetch(URL_D + '?' + params(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { if (!r.ok) { throw 0; } return r.json(); }).then(function (d) { D = d; afficher(); }).catch(function () { $id('si-tuiles').innerHTML = '<div class="st-vide">Impossible de calculer. Actualisez la page.</div>'; }); }, 150); }
	function afficher() {
		const A = D.annees, an = A[A.length - 1], P = D.par_an[an], i = D.inculpes, v = D.victimes, d = D.durees;
		$id('si-resume').innerHTML = '<i class="fa-solid fa-folder-open"></i> ' + nb(D.total_dossiers) + ' dossier(s) d\'instruction';
		$id('si-tuiles').innerHTML = tuile('Dossiers ouverts en ' + an, nb(P.ouverts), nb(P.clos) + ' clôturé(s) la même année', 'orange', 'fa-folder-plus') + tuile('Stock fin ' + an, nb(P.stock_fin), 'dossiers en cours d\'instruction', 'bleu', 'fa-hourglass-half') +
			tuile('Inculpés', nb(i.total), nb(i.hommes) + ' homme(s) · ' + nb(i.femmes) + ' femme(s) · ' + nb(i.mineurs) + ' mineur(s)', 'vert', 'fa-user-lock') + tuile('Détenus', nb(i.detenus), 'durée moyenne de l\'instruction : ' + (d.moyenne == null ? '–' : d.moyenne + ' j') + ' (médiane ' + (d.mediane == null ? '–' : d.mediane + ' j') + ')', 'gris', 'fa-lock');
		graphe('gi-annees', D.total_dossiers === 0, { chart: { type: 'line', height: 300, fontFamily: 'inherit', toolbar: { show: false } }, stroke: { width: [0, 0, 3], curve: 'smooth' }, colors: [ORANGE, VERT, BLEU], plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
			series: [{ name: 'Ouverts', type: 'column', data: A.map(function (a) { return D.par_an[a].ouverts; }) }, { name: 'Clôturés', type: 'column', data: A.map(function (a) { return D.par_an[a].clos; }) }, { name: 'Stock en fin d\'année', type: 'line', data: A.map(function (a) { return D.par_an[a].stock_fin; }) }], xaxis: { categories: A.map(String) } });
		graphe('gi-statuts', i.statuts.length === 0, { chart: { type: 'donut', height: 300, fontFamily: 'inherit' }, labels: i.statuts.map(function (x) { return x.libelle; }), series: i.statuts.map(function (x) { return x.n; }), colors: [VERT, ORANGE, BLEU, '#c0392b', '#6c757d'], legend: { position: 'bottom' } });
		const tot = A.reduce(function (s, a) { return s + D.commissions[a].recues + D.commissions[a].emises; }, 0);
		graphe('gi-cr', tot === 0, { chart: { type: 'bar', height: 260, fontFamily: 'inherit', toolbar: { show: false } }, colors: [ORANGE, BLEU, VERT], plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
			series: [{ name: 'Reçues', data: A.map(function (a) { return D.commissions[a].recues; }) }, { name: 'Émises', data: A.map(function (a) { return D.commissions[a].emises; }) }, { name: 'Exécutées', data: A.map(function (a) { return D.commissions[a].executees; }) }], xaxis: { categories: A.map(String) } });
		graphe('gi-victimes', v.total === 0, { chart: { type: 'donut', height: 260, fontFamily: 'inherit' }, labels: ['Hommes', 'Femmes', 'Mineurs (dont déjà comptés)'], series: [v.hommes, v.femmes, v.mineurs], colors: [BLEU, VERT, ORANGE], legend: { position: 'bottom' } });
	}
	document.addEventListener('DOMContentLoaded', function () {
		let h1 = '', h2 = ''; for (let a = annee + 1; a >= annee - 14; a--) { h1 += '<option value="' + a + '"' + (a === annee - 2 ? ' selected' : '') + '>' + a + '</option>'; h2 += '<option value="' + a + '"' + (a === annee ? ' selected' : '') + '>' + a + '</option>'; }
		$id('i-depuis').innerHTML = h1; $id('i-jusqua').innerHTML = h2; $('#i-depuis, #i-jusqua').trigger('change.select2');
		['i-depuis', 'i-jusqua', 'i-chambre'].forEach(function (i) { $('#' + i).on('change', function () {
			if (i === 'i-jusqua' && +$id('i-depuis').value > +this.value) { $id('i-depuis').value = this.value; $('#i-depuis').trigger('change.select2'); }
			if (i === 'i-depuis' && +$id('i-jusqua').value < +this.value) { $id('i-jusqua').value = this.value; $('#i-jusqua').trigger('change.select2'); }
			charger(); }); });
		charger();
	});
})();
</script>
@endsection
