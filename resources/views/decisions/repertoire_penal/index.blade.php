@extends('layout.master')
@section('title', 'Répertoire pénal')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
@endsection

@section('main-content')
  @php $tons = ['condamne' => 'danger', 'relaxe' => 'success', 'acquitte' => 'success', 'dispense_de_peine' => 'warning']; @endphp
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Décisions" title="Répertoire pénal" subtitle="La décision rendue pour chaque prévenu : issue, peine, amende, puis transmission de la minute. Un prévenu relaxé ou acquitté qui était détenu est remis en liberté dans le suivi des détenus." icon="fa-solid fa-book-open">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $compte['total'] }} décision(s)</span>
          <span><i class="fa-solid fa-gavel"></i> {{ $compte['condamnes'] }} condamnation(s)</span>
          <span><i class="fa-solid fa-file-export"></i> {{ $compte['minutes'] }} minute(s) à transmettre</span>
          <span><i class="fa-solid fa-triangle-exclamation"></i> {{ $compte['retard'] }} de plus de {{ $vigilance }} jours</span>
        </x-slot:extra>
        @can('repertoire_penal.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_decision(null)"><i class="fa-solid fa-plus"></i> Enregistrer une décision</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="5" data-value="">Toutes</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="minute_a_transmettre">Minute à transmettre</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="minute_transmise">Minute transmise</button>
          </div>
          <label class="el-search" style="max-width:320px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="el-search" placeholder="Affaire, prévenu, peine…" autocomplete="off" aria-label="Rechercher"></label>
        </div>
        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-repertoire" data-serveur="{{ route('decisions.repertoire_penal.donnees') }}">
            <colgroup><col style="width:110px"><col style="width:130px"><col><col style="width:150px"><col style="width:250px"><col style="width:170px"><col style="width:130px"></colgroup>
            <thead><tr><th>Date</th><th>Affaire</th><th>Prévenu</th><th>Issue</th><th>Peine</th><th>Minute</th><th>Actions</th></tr></thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  @include('decisions.repertoire_penal.formulaires')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>ElTable.init('#tbl-repertoire', { order: [[0, 'desc']], noSort: [2, 3, 4, 6], search: '#el-search' });</script>
@endsection
