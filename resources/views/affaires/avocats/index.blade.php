@extends('layout.master')
@section('title', 'Avocats')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Affaires" title="Avocats et cabinets" subtitle="La liste des avocats proposée à l'enrôlement. Un avocat ajouté à la volée par un greffier apparaît « À compléter » : complétez sa fiche (barreau, téléphone, e-mail) quand vous avez le temps." icon="fa-solid fa-user-tie">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $total }} fiche(s)</span>
          @if ($acompleter)<span><i class="fa-solid fa-pen-ruler"></i> {{ $acompleter }} à compléter</span>@endif
        </x-slot:extra>
        @can('avocats.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="4" data-value="">Tous</button>
            <button type="button" class="el-pill" data-el-filter="4" data-value="completer">À compléter</button>
            <button type="button" class="el-pill" data-el-filter="4" data-value="inactif">Inactifs</button>
          </div>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Nom, cabinet, barreau…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-avocats" data-serveur="{{ route('affaires.avocats.donnees') }}">
            <colgroup><col><col style="width:200px"><col style="width:170px"><col style="width:100px"><col style="width:130px">@canany(['avocats.modifier', 'avocats.supprimer'])<col style="width:112px">@endcanany</colgroup>
            <thead>
              <tr><th>Avocat ou cabinet</th><th>Barreau et cabinet</th><th>Contact</th><th>Affaires</th><th>État</th>@canany(['avocats.modifier', 'avocats.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @include('affaires.avocats.form_avocat')
  @include('affaires.avocats.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-avocats', { order: [[0, 'asc']], noSort: [1, 2, 5], search: '#el-search' });
  </script>
@endsection
