@extends('layout.master')
@section('title', "Journal d'audit")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Paramètres" title="Journal d'audit" subtitle="Qui a fait quoi, sur quel élément, avec les valeurs avant et après. Les mots de passe n'y figurent jamais. Ce journal ne peut être ni modifié ni effacé." icon="fa-solid fa-clipboard-list">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ number_format($total, 0, ',', ' ') }} ligne(s)</span>
        </x-slot:extra>
        @can('journaux.audit.exporter')
          <x-slot:actions>
            <a class="el-btn el-btn--accent" href="{{ route('journal.audit.export', $filtres) }}"><i class="fa-solid fa-file-csv"></i> Exporter (CSV)</a>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        @include('journal._filtres', ['route' => 'journal.index', 'types' => $actions, 'libelleType' => 'Action', 'avecResultat' => false, 'placeholder' => 'Élément, auteur ou adresse IP'])

        <div class="el-tblwrap">
          <table class="el-tbl" style="min-width:860px" id="tbl-journal" data-serveur="{{ route('journal.audit.donnees', $filtres) }}">
            <thead>
              <tr><th>Date</th><th>Action</th><th>Auteur</th><th>Élément</th><th>Détail</th></tr>
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
  <script>ElTable.init('#tbl-journal', { order: [[0, 'desc']], noSort: [1, 2, 3, 4] });</script>
@endsection
