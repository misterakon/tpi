@extends('layout.master')
@section('title', "Suites d'audience")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page">

      <x-hero tag="Audiences" title="Suites d'audience" subtitle="Tout ce qui a été noté aux audiences : jugées, radiées, jointes, désistements, renvois et délibérés. Filtrez par suite, chambre ou période, puis exportez la liste en Excel ou en PDF." icon="fa-solid fa-scale-balanced">
        <x-slot:actions>
          @if ($peutExporter)
            <a class="el-btn el-btn--ghost" id="export-xlsx" href="#" title="Un seul classeur : toutes les suites, une feuille chacune, plus les affaires en retard"><i class="fa-solid fa-file-excel"></i> Excel</a>
            <a class="el-btn el-btn--accent" id="export-pdf" href="#" title="Le PDF de la suite affichée (la pastille sélectionnée)"><i class="fa-solid fa-file-pdf"></i> PDF</a>
          @endif
        </x-slot:actions>
      </x-hero>

      <div class="au-kpis au-kpis--compact" id="kpis-suites" style="grid-template-columns: repeat(4, minmax(0, 1fr))"></div>

      <div class="au-cadre">
        <div class="au-compte-filtres" id="filtres-suite"></div>

        <div class="au-filtres-ligne">
          <div class="au-champ">
            <label for="f-chambre">Chambre</label>
            <select class="form-select" id="f-chambre" data-sans-recherche>
              <option value="">Toutes les chambres</option>
              @foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach
            </select>
          </div>
          <div class="au-champ au-champ--date"><label for="f-du">Du</label><input type="date" id="f-du" class="form-control"></div>
          <div class="au-champ au-champ--date"><label for="f-au">Au</label><input type="date" id="f-au" class="form-control"></div>
          <div class="au-recherche"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="f-q" placeholder="Numéro, partie, objet…" autocomplete="off" maxlength="80"></div>
          <button type="button" class="au-lien" id="f-reinit" hidden><i class="fa-solid fa-rotate-left"></i> Réinitialiser</button>
        </div>

        <div class="au-fond">
          <div class="au-tbl-carte">
            <table class="au-tbl">
              <colgroup><col style="width:136px"><col style="width:14%"><col><col style="width:204px"><col style="width:166px"><col style="width:18%"><col style="width:58px"></colgroup>
              <thead><tr><th>Audience du</th><th>Affaire</th><th>Parties et objet</th><th>Dates de l'affaire</th><th>Suite</th><th>Détail</th><th></th></tr></thead>
              <tbody id="suites-corps"></tbody>
            </table>
          </div>
        </div>
        <div class="au-pagination" id="pagination-suites"></div>
      </div>

    </div>
  </div>
@endsection

@section('script')
  @include('audiences.suites.js_script')
@endsection
