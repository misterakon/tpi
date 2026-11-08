@extends('layout.master')
@section('title', 'Mise en état')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page tp-anime">

      <x-hero tag="Affaires" title="Mise en état" subtitle="Les affaires civiles confiées à un juge chargé de la mise en état : date de réception du dossier, première audience, ordonnance de clôture et durée. À la clôture, l'affaire est versée à la chambre de jugement pour l'audience indiquée." icon="fa-solid fa-hourglass-half">
        <x-slot:extra>
          <span><i class="fa-solid fa-hourglass-half"></i> {{ $compte['en_cours'] }} en cours</span>
          <span><i class="fa-solid fa-triangle-exclamation"></i> {{ $compte['longues'] }} de plus de {{ $vigilance }} jours</span>
          <span><i class="fa-solid fa-flag-checkered"></i> {{ $compte['cloturees_30j'] }} clôturées en 30 jours</span>
          <span><i class="fa-solid fa-stopwatch"></i> durée moyenne : {{ $compte['duree_moyenne'] !== null ? $compte['duree_moyenne'].' jours' : '—' }}</span>
        </x-slot:extra>
        @can('mise_en_etat.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_mee(null)"><i class="fa-solid fa-plus"></i> Confier une affaire</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="5" data-value="">Toutes</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="en_cours">En cours</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="cloturee">Clôturées</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="retiree">Retirées</button>
          </div>
          <label class="el-search" style="max-width:320px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Numéro, parties, juge, ordonnance…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-mee" data-serveur="{{ route('affaires.mise_en_etat.donnees') }}">
            <colgroup><col style="width:130px"><col><col style="width:170px"><col style="width:120px"><col style="width:130px"><col style="width:200px"><col style="width:100px"><col style="width:140px"></colgroup>
            <thead>
              <tr><th>Affaire</th><th>Parties et objet</th><th>Juge chargé</th><th>Dossier reçu</th><th>1re audience</th><th>Ordonnance de clôture</th><th>Durée</th><th>Actions</th></tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @can('mise_en_etat.voir')
    @include('affaires.mise_en_etat.form_ouverture')
    @include('affaires.mise_en_etat.form_cloture')
    @include('affaires.mise_en_etat.js_script')
  @endcan
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-mee', { order: [[3, 'desc']], noSort: [1, 2, 5, 6, 7], search: '#el-search' });
  </script>
@endsection
