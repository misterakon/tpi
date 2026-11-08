@extends('layout.master')
@section('title', 'Matières')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Référentiels" title="Matières" subtitle="Les grands domaines du droit jugés par le tribunal : civile, commerciale, administrative et fiscale, sociale, correctionnelle, criminelle… Chaque objet du litige se rattache à une matière." icon="fa-solid fa-book">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} élément(s)</span>
        </x-slot:extra>
        @can('matieres.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Matières</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-matieres">
            <colgroup><col><col style="width:100px"><col style="width:96px"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Matière</th><th>Objets</th><th>État</th>@canany(['matieres.modifier', 'matieres.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div>
                  </td>
                  <td>
                    <x-chip :tone="$l->objets_count ? 'info' : 'neutral'" :label="$l->objets_count.''" />
                  </td>
                  <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
                  @canany(['matieres.modifier', 'matieres.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('matieres.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_matiere({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('matieres.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_matiere({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('referentiels.matieres.form_matieres')
  @include('referentiels.matieres.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-matieres', { order: [[0, 'asc']], noSort: [3], search: '#el-search' });
  </script>
@endsection
