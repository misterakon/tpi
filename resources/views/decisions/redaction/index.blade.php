@extends('layout.master')
@section('title', 'Rédaction des décisions')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page tp-anime">

      <x-hero tag="Décisions" title="Rédaction" subtitle="Choisissez un modèle, remplissez les champs qu'il demande, contrôlez la décision sur une page A4, puis validez-la : le texte est alors figé. Le formulaire se compose tout seul d'après le modèle." icon="fa-solid fa-pen-nib">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $total }} décision(s)</span>
          <span><i class="fa-regular fa-pen-to-square"></i> {{ $brouillons }} brouillon(s)</span>
        </x-slot:extra>
        @can('redaction.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Rédiger une décision</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Décisions, de la plus récemment modifiée à la plus ancienne</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-decisions" data-serveur="{{ route('decisions.redaction.donnees') }}">
            <colgroup><col><col style="width:130px"><col style="width:150px"><col style="width:170px"><col style="width:170px"></colgroup>
            <thead>
              <tr><th>Décision</th><th>Version</th><th>État</th><th>Modifiée le</th><th>Actions</th></tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @can('redaction.creer')
    @include('decisions.redaction.form_nouvelle')
  @endcan
  @include('decisions.redaction.js_script_liste')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-decisions', { order: [[3, 'desc']], noSort: [1, 4], search: '#el-search' });
  </script>
@endsection
