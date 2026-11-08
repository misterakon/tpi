@extends('layout.master')
@section('title', 'Types d\'audience')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Référentiels" title="Types d'audience" subtitle="Les natures d'audience tenues par les chambres : audience publique, conciliation, référé, session… Elles remplacent la saisie libre dans le calendrier des audiences." icon="fa-solid fa-gavel">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} élément(s)</span>
        </x-slot:extra>
        @can('types_audience.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Types d'audience</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-types_audience">
            <colgroup><col><col style="width:96px"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Type d'audience</th><th>État</th>@canany(['types_audience.modifier', 'types_audience.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div>
                  </td>
                  <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
                  @canany(['types_audience.modifier', 'types_audience.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('types_audience.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_type_audience({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('types_audience.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_type_audience({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('referentiels.types_audience.form_types_audience')
  @include('referentiels.types_audience.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-types_audience', { order: [[0, 'asc']], noSort: [2], search: '#el-search' });
  </script>
@endsection
