@extends('layout.master')
@section('title', 'Blocs de texte')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/blocs-texte.css') }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Décisions" title="Blocs de texte" subtitle="Des passages qui reviennent dans plusieurs décisions : visas, formule de dépens, clôture. On les écrit une fois ici, puis on les insère dans les modèles ; une correction ici se répercute dans les nouvelles versions de chaque modèle qui les utilise." icon="fa-solid fa-cubes">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $total }} bloc(s)</span>
        </x-slot:extra>
        @can('blocs.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Blocs réutilisables</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-blocs" data-serveur="{{ route('decisions.blocs.donnees') }}">
            <colgroup><col><col style="width:190px"><col style="width:120px"><col style="width:140px"><col style="width:96px"><col style="width:112px"></colgroup>
            <thead>
              <tr>
                <th>Bloc</th>
                <th>Code</th>
                <th>Catégorie</th>
                <th>Utilisé dans</th>
                <th>État</th>
                @canany(['blocs.modifier', 'blocs.supprimer'])
                <th>Actions</th>
                @endcanany
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @include('decisions.blocs.form_bloc')
  @include('decisions.blocs.form_modeles')
  @include('decisions.blocs.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-blocs', { order: [[2, 'asc'], [0, 'asc']], noSort: [3, 5], search: '#el-search' });
  </script>
@endsection
