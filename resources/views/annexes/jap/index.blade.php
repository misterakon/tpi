@extends('layout.master')
@section('title', 'Activités du JAP')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
  <style>
    .jp-table input { width: 100%; min-width: 58px; text-align: center; padding: 4px 4px; border: 1px solid #d9e3dc; border-radius: 6px; }
    .jp-table input:disabled { background: #f6f8f7; }
    .jp-table td.jp-calc { background: #e7f3ec; font-weight: 700; text-align: center; }
    .jp-table th { position: static; font-size: 11.5px; }
    .jp-table td:first-child, .jp-table th:first-child { min-width: 230px; position: static; }
  </style>
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">
      <x-hero tag="Services annexes" title="Activités du juge de l'application des peines" subtitle="Les compteurs mensuels du classeur du tribunal : visites des établissements, puis placement à l'extérieur, semi-liberté, permissions de sortir, libération conditionnelle et reclassement. Le total à traiter et le stock restant se calculent." icon="fa-solid fa-scale-unbalanced">
        <x-slot:extra><span id="jp-resume"><i class="fa-solid fa-calendar"></i> Année en cours</span></x-slot:extra>
        <x-slot:actions>
          @can('stats_registres.voir')<a class="el-btn st-btn-pdf" href="{{ route('statistiques.registres') }}"><i class="fa-solid fa-file-excel"></i> Classeur Excel</a>@endcan
        </x-slot:actions>
      </x-hero>

      <div class="el-card">
        <form class="st-filtres" onsubmit="return false" style="grid-template-columns: minmax(0, 220px)">
          <div class="st-champ"><label for="jp-annee">Année</label><select class="form-select" id="jp-annee">@for ($y = now()->year + 1; $y >= now()->year - 6; $y--)<option value="{{ $y }}" @selected($y === now()->year)>{{ $y }}</option>@endfor</select></div>
        </form>
      </div>

      <div class="st-tuiles" id="jp-tuiles"></div>
      <div class="aff-erreur" id="jp-erreur" hidden style="margin:8px 0"></div>

      <div class="st-bloc">
        <div class="st-bloc__titre"><span>Visites des établissements pénitentiaires</span></div>
        <div class="st-table-wrap"><table class="st-table jp-table" id="jp-visites"></table></div>
      </div>
      @foreach ($rubriques as $code => [$libelle, $champs])
        <div class="st-bloc">
          <div class="st-bloc__titre"><span>{{ $libelle }}</span></div>
          <div class="st-table-wrap"><table class="st-table jp-table" id="jp-{{ $code }}" data-rubrique="{{ $code }}"></table></div>
        </div>
      @endforeach

      @if ($peutModifier)
        <div style="text-align:right; margin:10px 0 24px"><button class="el-btn el-btn--accent" type="button" id="jp-enregistrer"><i class="fa-solid fa-floppy-disk"></i> Enregistrer l'année</button></div>
      @endif
    </div>
  </div>
@endsection

@section('script')
<script>
(function () {
	const PEUT = @js($peutModifier), CSRF = "{{ csrf_token() }}", URL_D = "{{ route('jap.donnees') }}", URL_E = "{{ route('jap.enregistrer') }}";
	const RUBRIQUES = @js($rubriques), VISITES = @js($visites);
	const MOIS = ['Janv.', 'Févr.', 'Mars', 'Avril', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
	const esc = (x) => String(x == null ? '' : x).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	let V = {};

	const val = (cle, m) => +((V[cle] || {})[m] || 0);
	function cellule(cle, m) { return '<td><input type="number" min="0" max="999999" data-cle="' + cle + '" data-m="' + m + '" value="' + val(cle, m) + '"' + (PEUT ? '' : ' disabled') + '></td>'; }
	function entete(titre) { return '<thead><tr><th>' + esc(titre) + '</th>' + MOIS.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '<th>Total</th></tr></thead>'; }

	function dessiner() {
		let h = entete('') + '<tbody>';
		Object.keys(VISITES).forEach(function (k) { h += '<tr><td>' + esc(VISITES[k][0]) + '</td>' + MOIS.map(function (_, i) { return cellule('visites_' + k, i + 1); }).join('') + '<td class="jp-calc" data-total="visites_' + k + '"></td></tr>'; });
		document.getElementById('jp-visites').innerHTML = h + '</tbody>';
		Object.keys(RUBRIQUES).forEach(function (code) {
			const champs = RUBRIQUES[code][1]; let t = entete('') + '<tbody>';
			Object.keys(champs).forEach(function (c) {
				t += '<tr><td>' + esc(champs[c]) + '</td>' + MOIS.map(function (_, i) { return c === 'stock' && i > 0 ? '<td class="jp-calc" data-calc="stock" data-r="' + code + '" data-m="' + (i + 1) + '"></td>' : cellule(code + '_' + c, i + 1); }).join('') + '<td class="jp-calc" data-total="' + code + '_' + c + '"></td></tr>';
				if (c === 'initiatives' || (c === 'recues' && !champs.initiatives)) { t += '<tr class="st-total"><td>Total à traiter</td>' + MOIS.map(function (_, i) { return '<td class="jp-calc" data-calc="total" data-r="' + code + '" data-m="' + (i + 1) + '"></td>'; }).join('') + '<td class="jp-calc"></td></tr>'; }
			});
			t += '<tr class="st-total"><td>Stock restant en fin de mois</td>' + MOIS.map(function (_, i) { return '<td class="jp-calc" data-calc="reste" data-r="' + code + '" data-m="' + (i + 1) + '"></td>'; }).join('') + '<td class="jp-calc"></td></tr>';
			document.getElementById('jp-' + code).innerHTML = t + '</tbody>';
		});
		calculer();
	}
	function lire(cle, m) { const e = document.querySelector('input[data-cle="' + cle + '"][data-m="' + m + '"]'); return e ? +(e.value || 0) : 0; }
	function calculer() {
		Object.keys(RUBRIQUES).forEach(function (code) {
			let reste = 0;
			for (let m = 1; m <= 12; m++) {
				const stock = m === 1 ? lire(code + '_stock', 1) : reste, total = stock + lire(code + '_recues', m) + lire(code + '_initiatives', m);
				reste = Math.max(0, total - lire(code + '_traitees', m));
				const poser = function (type, v) { const td = document.querySelector('[data-calc="' + type + '"][data-r="' + code + '"][data-m="' + m + '"]'); if (td) { td.textContent = v; } };
				poser('stock', stock); poser('total', total); poser('reste', reste);
			}
		});
		document.querySelectorAll('[data-total]').forEach(function (td) {
			const cle = td.dataset.total; if (cle.endsWith('_stock')) { td.textContent = ''; return; }
			let s = 0; for (let m = 1; m <= 12; m++) { s += lire(cle, m); } td.textContent = s;
		});
		const T = (code, c) => { let s = 0; for (let m = 1; m <= 12; m++) { s += lire(code + '_' + c, m); } return s; };
		const tot = (c) => Object.keys(RUBRIQUES).reduce(function (s, k) { return s + T(k, c); }, 0);
		const visites = (() => { let s = 0; for (let m = 1; m <= 12; m++) { s += lire('visites_effectuees', m); } return s; })();
		document.getElementById('jp-tuiles').innerHTML =
			t('Visites effectuées', visites, 'établissements pénitentiaires', 'orange', 'fa-building-shield') + t('Demandes reçues', tot('recues'), 'toutes rubriques', 'bleu', 'fa-inbox') +
			t('Demandes traitées', tot('traitees'), tot('accord') + ' accord(s) · ' + tot('rejet') + ' rejet(s)', 'vert', 'fa-circle-check') + t('Initiatives du JAP', tot('initiatives'), 'libération conditionnelle et reclassement', 'gris', 'fa-lightbulb');
	}
	function t(titre, v, sous, c, i) { return '<div class="st-tuile st-tuile--' + c + '"><span class="st-tuile__icone"><i class="fa-solid ' + i + '"></i></span><div class="st-tuile__etiquette">' + esc(titre) + '</div><div class="st-tuile__valeur">' + Number(v).toLocaleString('fr-FR') + '</div><div class="st-tuile__sous">' + esc(sous) + '</div></div>'; }

	function charger() {
		const a = document.getElementById('jp-annee').value;
		document.getElementById('jp-resume').innerHTML = '<i class="fa-solid fa-calendar"></i> Année ' + a;
		fetch(URL_D + '?annee=' + a, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { V = d.valeurs || {}; dessiner(); });
	}
	document.addEventListener('input', function (e) { if (e.target.matches('.jp-table input')) { calculer(); } });
	document.addEventListener('DOMContentLoaded', function () {
		$('#jp-annee').on('change', charger); charger();
		const b = document.getElementById('jp-enregistrer');
		if (b) {
			b.addEventListener('click', function () {
				const valeurs = {}; document.querySelectorAll('.jp-table input').forEach(function (i) { (valeurs[i.dataset.cle] = valeurs[i.dataset.cle] || {})[i.dataset.m] = i.value || 0; });
				const e = document.getElementById('jp-erreur'); e.hidden = true;
				$.ajax({ url: URL_E, type: 'POST', dataType: 'json', data: { _token: CSRF, annee: document.getElementById('jp-annee').value, valeurs: valeurs } })
					.done(function (r) { Swal.fire({ icon: 'success', title: r.message, timer: 1500, showConfirmButton: false }); })
					.fail(function (x) { const j = x.responseJSON || {}; e.hidden = false; e.textContent = j.errors ? Object.values(j.errors)[0][0] : (j.message || 'Enregistrement impossible.'); window.scrollTo({ top: 0, behavior: 'smooth' }); });
			});
		}
	});
})();
</script>
@endsection
