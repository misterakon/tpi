@extends('layout.master')
@section('title', 'Registres du tribunal')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
  <style>
    .rg-famille { margin: 22px 0 10px; font-size: 15px; font-weight: 800; color: #123; display: flex; align-items: center; gap: 10px; }
    .rg-famille::after { content: ''; flex: 1; height: 2px; background: linear-gradient(90deg, #f08a24, transparent); border-radius: 2px; }
    .rg-grille { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 14px; }
    .rg-carte { background: #fff; border: 1px solid #e3ebe6; border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 6px; box-shadow: 0 4px 14px rgba(12, 60, 40, .05); transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden; }
    .rg-carte:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(12, 60, 40, .12); }
    .rg-carte::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--rg, #00794c); }
    .rg-carte--penal { --rg: #f08a24; } .rg-carte--tutelle { --rg: #2f6fd0; } .rg-carte--me { --rg: #8e44ad; }
    .rg-titre { font-weight: 800; font-size: 15.5px; color: #123; padding-left: 6px; }
    .rg-desc { color: #2f3b52; font-size: 12.5px; padding-left: 6px; min-height: 32px; }
    .rg-pied { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 6px; padding-left: 6px; }
    .rg-nb { font-size: 26px; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--rg, #00794c); line-height: 1; }
    .rg-nb small { display: block; font-size: 11.5px; font-weight: 600; color: #2f3b52; margin-top: 2px; }
    .rg-btn { background: linear-gradient(135deg, #00794c, #19a06b); color: #fff !important; border: 0; border-radius: 10px; padding: 9px 14px; font-weight: 700; text-decoration: none; box-shadow: 0 6px 14px rgba(0, 121, 76, .3); white-space: nowrap; }
    .rg-btn:hover { filter: brightness(1.08); }
    .rg-btn[aria-disabled="true"] { background: #cfd8d2; box-shadow: none; pointer-events: none; }
  </style>
@endsection

@section('main-content')
  @php
    $groupes = ['Civil' => ['civil', 'civile_diverse'], 'Tutelle et hérédité' => ['tutelle'], 'Mise en état' => ['mise_en_etat'], 'Pénal' => ['penal'], 'Instruction' => ['instruction'], 'Services annexes' => ['annexe'], 'Personnel' => ['personnel']];
  @endphp
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Statistiques" title="Registres du tribunal" subtitle="Les classeurs Excel que le greffe tient à la main, remplis avec les affaires de l'application, au modèle exact du tribunal : mêmes colonnes, mêmes listes, mêmes couleurs. Les récapitulatifs du classeur se calculent tout seuls à l'ouverture dans Excel." icon="fa-solid fa-book-open">
        <x-slot:extra><span><i class="fa-solid fa-file-excel"></i> {{ count($registres) }} registres au modèle du tribunal</span></x-slot:extra>
      </x-hero>

      <div class="el-card">
        <form class="st-filtres" onsubmit="return false" style="grid-template-columns: minmax(0, 340px)">
          <div class="st-champ"><label for="r-chambre">Chambre</label>
            <select class="form-select" id="r-chambre"><option value="">Toutes les chambres</option>@foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select>
          </div>
        </form>
      </div>

      @foreach ($groupes as $titre => $cles)
        <div class="rg-famille"><i class="fa-solid {{ $titre === 'Civil' ? 'fa-scale-balanced' : ($titre === 'Pénal' ? 'fa-handcuffs' : ($titre === 'Mise en état' ? 'fa-hourglass-half' : ($titre === 'Services annexes' ? 'fa-hand-holding-heart' : ($titre === 'Instruction' ? 'fa-magnifying-glass' : ($titre === 'Personnel' ? 'fa-user-tie' : 'fa-people-roof'))))) }}" style="color:#00794c"></i> {{ $titre }}</div>
        <div class="rg-grille">
          @foreach ($registres as $code => [$libelle, $famille, $desc])
            @continue (! in_array($famille, $cles, true))
            <div class="rg-carte {{ $famille === 'penal' || $famille === 'instruction' ? 'rg-carte--penal' : ($famille === 'tutelle' ? 'rg-carte--tutelle' : ($famille === 'mise_en_etat' ? 'rg-carte--me' : '')) }}">
              <div class="rg-titre">{{ $libelle }}</div>
              <div class="rg-desc">{{ $desc }}</div>
              <div class="rg-pied">
                <div class="rg-nb"><span data-nb="{{ $code }}">…</span><small>{{ $famille === 'penal' ? 'décision(s)' : ($famille === 'personnel' ? 'magistrat(s)' : ($famille === 'instruction' ? 'dossier(s)' : ($famille === 'annexe' ? 'ligne(s)' : 'affaire(s)'))) }}</small></div>
                @if ($peutExporter)
                  <a class="rg-btn" data-export="{{ $code }}" href="{{ route('statistiques.registres.export', $code) }}"><i class="fa-solid fa-file-excel"></i> Télécharger</a>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endforeach
      <p class="st-note" style="margin-top:16px">Chaque affaire figure dans un seul registre, selon sa chambre, sa nature et son objet. Ouvrez le fichier dans Excel et activez les calculs : les onglets « RECAP » (stocks, nouvelles, vidées, état civil, matrimonial) se remplissent seuls.</p>
    </div>
  </div>
@endsection

@section('script')
<script>
(function () {
	const U = { effectifs: "{{ route('statistiques.registres.effectifs') }}", base: "{{ url('statistiques/registres') }}" };
	function charger() {
		const c = document.getElementById('r-chambre').value, q = c ? '?chambre=' + encodeURIComponent(c) : '';
		document.querySelectorAll('[data-export]').forEach(function (a) { a.href = U.base + '/' + a.dataset.export + '/export' + q; });
		fetch(U.effectifs + q, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (n) {
			document.querySelectorAll('[data-nb]').forEach(function (e) { const v = n[e.dataset.nb] || 0; e.textContent = v.toLocaleString('fr-FR'); const a = document.querySelector('[data-export="' + e.dataset.nb + '"]'); if (a) { a.setAttribute('aria-disabled', v === 0 ? 'true' : 'false'); } });
		});
	}
	document.addEventListener('DOMContentLoaded', function () { $('#r-chambre').on('change', charger); charger(); });
})();
</script>
@endsection
