@extends('layout.master')
@section('title', 'Journal de connexion')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Paramètres" title="Journal de connexion" subtitle="Qui s'est connecté, quand et d'où : connexions, échecs, codes de double authentification et déconnexions. Ce journal ne peut être ni modifié ni effacé." icon="fa-solid fa-right-to-bracket">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ number_format($total, 0, ',', ' ') }} ligne(s)</span>
          <span><i class="fa-solid fa-triangle-exclamation"></i> {{ $echecs24h }} échec(s) sur 24 h</span>
        </x-slot:extra>
        @can('journaux.connexion.exporter')
          <x-slot:actions>
            <a class="el-btn el-btn--accent" href="{{ route('journal.connexions.export', $filtres) }}"><i class="fa-solid fa-file-csv"></i> Exporter (CSV)</a>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        @include('journal._filtres', ['route' => 'journal.connexions', 'types' => $evenements, 'libelleType' => 'Événement', 'avecResultat' => true, 'placeholder' => 'Nom, e-mail ou adresse IP'])

        <div class="el-tblwrap">
          <table class="el-tbl" style="min-width:860px" id="tbl-journal" data-serveur="{{ route('journal.connexions.donnees', $filtres) }}">
            <thead>
              <tr><th>Date</th><th>Événement</th><th>Utilisateur</th><th>Adresse IP</th><th>Navigateur</th></tr>
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
