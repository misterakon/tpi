@extends('layout.master')
@section('title', 'Modes de saisine')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Référentiels" title="Modes de saisine" subtitle="La manière dont la juridiction est saisie : assignation, requête, citation directe, flagrant délit… Les termes marqués « À valider » viennent du droit courant et non des documents du tribunal." icon="fa-solid fa-file-import">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} élément(s)</span>
        </x-slot:extra>
        @can('modes_saisine.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Modes de saisine</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-modes_saisine">
            <colgroup><col><col style="width:150px"><col style="width:140px"><col style="width:96px"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Mode de saisine</th><th>Registre</th><th>Validation</th><th>État</th>@canany(['modes_saisine.modifier', 'modes_saisine.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div>
                  </td>
                  <td>
                    <x-chip tone="info" :label="\App\Models\ModeSaisine::REGISTRES[$l->registre] ?? $l->registre" class="el-chip--libre" />
                  </td>
                  <td>
                    @if ($l->a_valider)<x-chip tone="alert" label="À valider" class="el-chip--libre" />@endif
                  </td>
                  <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
                  @canany(['modes_saisine.modifier', 'modes_saisine.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('modes_saisine.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_mode_saisine({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('modes_saisine.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_mode_saisine({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('referentiels.modes_saisine.form_modes_saisine')
  @include('referentiels.modes_saisine.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-modes_saisine', { order: [[0, 'asc']], noSort: [4], search: '#el-search' });
  </script>
@endsection
