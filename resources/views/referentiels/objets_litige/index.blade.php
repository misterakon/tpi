@extends('layout.master')
@section('title', 'Objets du litige')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Référentiels" title="Objets du litige" subtitle="Le motif précis d'une affaire (divorce, hérédité, expulsion, recouvrement de créance…), rattaché à une matière, à une nature d'affaire et à la chambre qui la juge. La liste de départ reprend les attributions de l'ordonnance ; le tribunal la complète." icon="fa-solid fa-list-check">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $total }} élément(s)</span>
        </x-slot:extra>
        @can('objets_litige.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Objets du litige</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-objets_litige" data-serveur="{{ route('referentiels.objets_litige.donnees') }}">
            <colgroup><col><col style="width:150px"><col style="width:170px"><col style="width:96px"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Objet du litige</th><th>Matière</th><th>Nature</th><th>État</th>@canany(['objets_litige.modifier', 'objets_litige.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @include('referentiels.objets_litige.form_objets_litige')
  @include('referentiels.objets_litige.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-objets_litige', { order: [[0, 'asc']], noSort: [4], search: '#el-search' });
  </script>
@endsection
