@extends('layout.master')
@section('title', 'Détenus')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Affaires" title="Détenus prioritaires" subtitle="Les prévenus et inculpés sous mandat de dépôt, classés par échéance : les plus pressants en premier. L'échéance saisie est reprise telle quelle ; à défaut, elle est calculée d'après la durée réglée dans Réglages > Affaires." icon="fa-solid fa-lock">
        <x-slot:extra>
          <span><i class="fa-solid fa-users"></i> {{ $compte['total'] }} détenu(s)</span>
          <span><i class="fa-solid fa-triangle-exclamation"></i> {{ $compte['depassees'] }} échéance(s) dépassée(s)</span>
          <span><i class="fa-solid fa-hourglass-half"></i> {{ $compte['proches'] }} dans les {{ config('tpi.penal.alerte_jours') }} jours</span>
        </x-slot:extra>
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="5" data-value="">Tous</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="depassee">Échéance dépassée</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="proche">Échéance proche</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="normal">Autres</button>
          </div>
          <label class="el-search" style="max-width:320px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Nom, numéro, lieu…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-detenus" data-serveur="{{ route('affaires.detenus.donnees') }}">
            <colgroup><col style="width:130px"><col><col style="width:170px"><col style="width:120px"><col style="width:200px"><col style="width:190px"><col style="width:190px"></colgroup>
            <thead>
              <tr><th>Affaire</th><th>Détenu</th><th>Chambre ou cabinet</th><th>Mandat</th><th>Lieu de détention</th><th>Échéance</th><th>Première audience</th></tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-detenus', { order: [[5, 'asc']], noSort: [1, 2, 4, 6], search: '#el-search' });
  </script>
@endsection
