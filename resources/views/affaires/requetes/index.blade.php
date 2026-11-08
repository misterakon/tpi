@extends('layout.master')
@section('title', 'Requêtes')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page tp-anime">

      <x-hero tag="Affaires" title="Requêtes et hérédité" subtitle="Les requêtes déposées au greffe, dont les requêtes d'hérédité. Le secrétariat de la Présidente les répartit entre les formations ; la formation les traite ou les classe." icon="fa-solid fa-file-pen">
        <x-slot:extra>
          <span><i class="fa-solid fa-inbox"></i> {{ $compte['recues'] }} à répartir</span>
          <span><i class="fa-solid fa-clock"></i> {{ $compte['retard'] }} en retard (plus de {{ config('tpi.requetes.delai_repartition_jours') }} j)</span>
          <span><i class="fa-solid fa-share-nodes"></i> {{ $compte['reparties'] }} réparties, en cours</span>
        </x-slot:extra>
        @can('requetes.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_requete(null)"><i class="fa-solid fa-plus"></i> Enregistrer une requête</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="4" data-value="">Toutes</button>
            <button type="button" class="el-pill" data-el-filter="4" data-value="recue">À répartir</button>
            <button type="button" class="el-pill" data-el-filter="4" data-value="repartie">Réparties</button>
            <button type="button" class="el-pill" data-el-filter="4" data-value="close">Clôturées</button>
          </div>
          <label class="el-search" style="max-width:320px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Numéro, requérant, défunt, objet…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-requetes" data-serveur="{{ route('affaires.requetes.donnees') }}">
            <colgroup><col style="width:110px"><col style="width:190px"><col><col style="width:120px"><col style="width:170px"><col style="width:170px"><col style="width:200px"></colgroup>
            <thead>
              <tr><th>Numéro</th><th>Type</th><th>Requérants et objet</th><th>Déposée le</th><th>Statut</th><th>Formation</th><th>Actions</th></tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @can('requetes.voir')
    @include('affaires.requetes.form_requete')
    @include('affaires.requetes.form_repartition', ['chambres' => $chambres])
    @include('affaires.requetes.form_cloture')
    @include('affaires.requetes.js_script')
  @endcan
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-requetes', { order: [[3, 'desc']], noSort: [1, 2, 5, 6], search: '#el-search' });
  </script>
@endsection
