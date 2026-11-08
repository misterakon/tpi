@extends('layout.master')
@section('title', 'Dossier numérique')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/dossier-numerique.css') }}?v={{ filemtime(public_path('assets/css/dossier-numerique.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Affaires" title="Dossier numérique" subtitle="Les pièces de chaque affaire, consultables directement dans l'application : assignation, conclusions, procès-verbaux, jugement… Ouvrez un dossier pour le parcourir pièce par pièce." icon="fa-solid fa-folder-open">
        <x-slot:extra>
          <span><i class="fa-solid fa-folder-tree"></i> {{ $total }} dossier(s)</span>
          <span><i class="fa-solid fa-paperclip"></i> {{ $pieces }} pièce(s)</span>
        </x-slot:extra>
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="3" data-value="">Tous</button>
            <button type="button" class="el-pill" data-el-filter="3" data-value="avec">Avec pièces</button>
            <button type="button" class="el-pill" data-el-filter="3" data-value="vide">Dossiers vides</button>
          </div>
          <label class="el-search" style="max-width:320px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Numéro, partie, emplacement…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-dossiers" data-serveur="{{ route('affaires.dossiers.donnees') }}">
            <colgroup><col style="width:132px"><col><col style="width:170px"><col style="width:120px"><col style="width:190px"><col style="width:230px"><col style="width:110px"></colgroup>
            <thead>
              <tr><th>Numéro</th><th>Parties et objet</th><th>Chambre</th><th>Pièces</th><th>Dernière pièce</th><th>Dossier papier</th><th>Ouvrir</th></tr>
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
    ElTable.init('#tbl-dossiers', { order: [[0, 'desc']], noSort: [2, 3, 4, 5, 6], search: '#el-search' });
  </script>
@endsection
