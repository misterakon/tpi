@extends('layout.master')
@section('title', 'Infractions')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Référentiels" title="Infractions" subtitle="Les infractions les plus fréquentes avec leurs articles du code pénal. À l'enrôlement d'une affaire pénale, le greffier choisit l'infraction : la nature de la prévention et les articles se remplissent tout seuls." icon="fa-solid fa-handcuffs">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $total }} élément(s)</span>
          <span><i class="fa-solid fa-triangle-exclamation"></i> {{ $sansArticles }} sans articles renseignés</span>
        </x-slot:extra>
        @can('infractions.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Infractions</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-infractions" data-serveur="{{ route('referentiels.infractions.donnees') }}">
            <colgroup><col style="width:34%"><col><col style="width:96px"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Infraction</th><th>Articles visés</th><th>État</th>@canany(['infractions.modifier', 'infractions.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @include('referentiels.infractions.form_infractions')
  @include('referentiels.infractions.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-infractions', { order: [[0, 'asc']], noSort: [3], search: '#el-search' });
  </script>
@endsection
