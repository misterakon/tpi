@extends('layout.master')
@section('title', 'Statistiques pénales')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Statistiques" title="Statistiques pénales" subtitle="Décisions rendues, condamnés par sexe, nationalité et profession, infractions, peines, victimes, traite des personnes et durées, d'après le répertoire pénal. Aucune ressaisie." icon="fa-solid fa-handcuffs">
        <x-slot:extra><span id="sp-resume"><i class="fa-solid fa-spinner fa-spin"></i> Calcul en cours…</span></x-slot:extra>
        @if ($peutExporter)
          <x-slot:actions>
            @can('stats_registres.voir')<a class="el-btn" href="{{ route('statistiques.registres') }}"><i class="fa-solid fa-book-open"></i> Registres du tribunal</a>@endcan
            <a class="el-btn el-btn--accent" id="sp-xlsx" href="#"><i class="fa-solid fa-file-excel"></i> Excel</a>
            <a class="el-btn st-btn-pdf" id="sp-pdf" href="#"><i class="fa-solid fa-file-pdf"></i> PDF</a>
          </x-slot:actions>
        @endif
      </x-hero>

      <div class="el-card">
        <form class="st-filtres" onsubmit="return false">
          <div class="st-champ"><label for="p-depuis">De l'année</label><select class="form-select" id="p-depuis"></select></div>
          <div class="st-champ"><label for="p-jusqua">À l'année</label><select class="form-select" id="p-jusqua"></select></div>
          <div class="st-champ"><label for="p-chambre">Chambre</label>
            <select class="form-select" id="p-chambre"><option value="">Toutes les chambres</option>@foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select>
          </div>
        </form>
      </div>

      <div class="st-tuiles" id="sp-tuiles"><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div></div>

      <div class="st-onglets" id="sp-onglets">
        <button type="button" class="st-onglet is-actif" data-panneau="vue">Vue d'ensemble</button>
        <button type="button" class="st-onglet" data-panneau="infractions">Infractions et peines</button>
        <button type="button" class="st-onglet" data-panneau="condamnes">Condamnés</button>
        <button type="button" class="st-onglet" data-panneau="victimes">Victimes et traite</button>
        <button type="button" class="st-onglet" data-panneau="durees">Durées et minutes</button>
      </div>

      <section class="st-panneau" id="q-vue">
        <div class="st-grille">
          <div class="st-carte"><h3>Décisions et condamnés par année</h3><div id="h-annees"></div></div>
          <div class="st-carte"><h3>Issue des décisions</h3><div id="h-issues"></div></div>
        </div>
        <div class="st-carte" style="margin-top:14px"><h3>Mois de <span id="h-mois-annee"></span> <small>décisions et condamnés</small></h3><div id="h-mois"></div></div>
      </section>
      <section class="st-panneau" id="q-infractions" hidden>
        <div class="st-grille">
          <div class="st-carte"><h3>Infractions retenues <small>les plus fréquentes</small></h3><div id="h-infractions"></div></div>
          <div class="st-carte"><h3>Nature des infractions</h3><div id="h-natures"></div><div id="h-peines" style="margin-top:12px"></div></div>
        </div>
      </section>
      <section class="st-panneau" id="q-condamnes" hidden>
        <div class="st-grille">
          <div class="st-carte"><h3>Nationalité des condamnés</h3><div id="h-nationalites"></div></div>
          <div class="st-carte"><h3>Profession ou emploi des condamnés</h3><div id="h-professions"></div></div>
        </div>
      </section>
      <section class="st-panneau" id="q-victimes" hidden></section>
      <section class="st-panneau" id="q-durees" hidden></section>

    </div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
  @include('statistiques.penal.js_script')
@endsection
