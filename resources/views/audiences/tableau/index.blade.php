@extends('layout.master')
@section('title', 'Tableau de bord des audiences')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page">

      <x-hero tag="Audiences" title="Tableau de bord des audiences" subtitle="Les affaires en cours, les audiences à venir, les délibérés à rendre, les décisions à rédiger et les retards, par chambre et par magistrat." icon="fa-solid fa-chart-pie">
        <x-slot:actions>
          @can('feuille.voir')<a class="el-btn el-btn--ghost" href="{{ route('audiences.feuille') }}"><i class="fa-solid fa-clipboard-list"></i> Feuille d'audience</a>@endcan
          @can('renvois.voir')<a class="el-btn el-btn--accent" href="{{ route('audiences.renvois') }}"><i class="fa-solid fa-arrows-rotate"></i> Renvois et délibérés</a>@endcan
        </x-slot:actions>
      </x-hero>

      {{-- Filtres : l'affichage se met à jour sans recharger la page --}}
      <div class="au-filtres">
        <div>
          <label for="f-chambre">Chambre</label>
          <select class="form-select" id="f-chambre" data-sans-recherche>
            <option value="">Toutes les chambres</option>
            @foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach
          </select>
        </div>
        <div>
          <label for="f-agent">Magistrat</label>
          <select class="form-select" id="f-agent">
            <option value="">Tous les magistrats</option>
            @foreach ($magistrats as $m)<option value="{{ $m['id'] }}">{{ $m['nom'] }}</option>@endforeach
          </select>
        </div>
        <div class="au-actualise"><span id="maj">Actualisé à {{ $donnees['genere_le'] }}</span><button type="button" id="btn-maj" title="Actualiser" aria-label="Actualiser"><i class="fa-solid fa-rotate"></i></button></div>
      </div>

      <div class="au-kpis" id="kpis"></div>

      <div class="au-ligne12">
        <div class="au-s8">
          <div class="au-panel">
            <div class="au-tete">
              <div><h5>Vue d'ensemble</h5><small id="sous-titre-graphique"></small></div>
              <div class="au-onglets" role="tablist">
                <button type="button" class="is-actif" data-onglet="chambres" role="tab">Par chambre</button>
                <button type="button" data-onglet="jours" role="tab">14 prochains jours</button>
                <button type="button" data-onglet="echeances" role="tab">Délibérés</button>
              </div>
            </div>
            <div class="au-resume" id="resume"></div>
            <div id="graphique"></div>
          </div>
        </div>
        <div class="au-s4">
          <div class="au-sombre">
            <h5>Où en sont les affaires</h5>
            <small>Toutes les affaires enrôlées du périmètre</small>
            <div id="donut" style="margin-top:8px"></div>
            <div class="au-legende" id="legende"></div>
          </div>
        </div>
      </div>

      <div class="au-ligne12">
        <div class="au-s7">
          <div class="au-panel">
            <div class="au-tete"><div><h5>Audiences à venir</h5><small>Les 14 prochains jours, rôles préparés ou publiés</small></div>
              @can('feuille.voir')<a class="au-btn au-btn--clair" href="{{ route('audiences.feuille') }}">Feuilles d'audience</a>@endcan</div>
            <div class="au-liste" id="liste-audiences"></div>
          </div>
        </div>
        <div class="au-s5">
          <div class="au-panel">
            <div class="au-tete"><div><h5>À traiter</h5><small>Ce qui demande une action maintenant</small></div></div>
            <div class="au-liste" id="a-traiter"></div>
          </div>
        </div>
      </div>

      <div class="au-panel">
        <div class="au-tete"><div><h5>Par magistrat</h5><small>Cliquez sur un magistrat pour n'afficher que ses chambres</small></div></div>
        <div class="au-magistrats" id="magistrats"></div>
      </div>

      <div class="au-ligne12">
        <div class="au-s4"><div class="au-panel"><div class="au-tete"><div><h5>Délibérés à rendre</h5><small>Les plus proches ou échus d'abord</small></div></div><div class="au-liste" id="liste-deliberes"></div></div></div>
        <div class="au-s4"><div class="au-panel"><div class="au-tete"><div><h5>Affaires en retard</h5><small>Renvoi dépassé, suite non notée</small></div></div><div class="au-liste" id="liste-retards"></div></div></div>
        <div class="au-s4"><div class="au-panel"><div class="au-tete"><div><h5>Décisions à rédiger</h5><small>Affaires jugées sans décision validée</small></div></div><div class="au-liste" id="liste-redaction"></div></div></div>
      </div>

    </div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
  @include('audiences.tableau.js_script', ['donnees' => $donnees])
@endsection
