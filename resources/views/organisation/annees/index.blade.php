@extends('layout.master')
@section('title', 'Année judiciaire')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Année judiciaire" subtitle="L'organisation du tribunal est fixée par une ordonnance, qui change chaque année. Créez ici chaque année et indiquez celle qui est en cours." icon="fa-solid fa-calendar-days">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} année(s)</span>
        </x-slot:extra>
        @can('annees.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Années judiciaires enregistrées</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-annees">
            <colgroup><col><col style="width:15%"><col style="width:15%"><col style="width:16%"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Année</th><th>Du</th><th>Au</th><th>État</th>@canany(['annees.modifier', 'annees.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td data-order="{{ $l->debut->timestamp }}">
                    <div class="el-person__name">{{ $l->libelle }}</div>
                    <div class="el-person__sub">{{ $l->ordonnance ?: 'Ordonnance non renseignée' }}</div>
                  </td>
                  <td class="el-num" data-order="{{ $l->debut->timestamp }}">{{ $l->debut->format('d/m/Y') }}</td>
                  <td class="el-num" data-order="{{ $l->fin->timestamp }}">{{ $l->fin->format('d/m/Y') }}</td>
                  <td><x-chip :tone="$l->courante ? 'success' : 'neutral'" :label="$l->courante ? 'En cours' : 'Autre année'" /></td>
                  @canany(['annees.modifier', 'annees.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('annees.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_annee({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('annees.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_annee({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                        @endcan
                      </div>
                    </td>
                  @endcanany
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @include('organisation.annees.form_annee')
  @include('organisation.annees.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-annees', { order: [[0, 'asc']], noSort: [4], search: '#el-search' });
  </script>
@endsection
