@extends('layout.master')
@section('title', 'Sections')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Sections" subtitle="Le siège du tribunal est divisé en sections (civile, pénale, instruction, sociale…) par l'ordonnance. Chaque section regroupe des chambres ; chaque chambre a ses attributions et, au besoin, plusieurs formations." icon="fa-solid fa-sitemap">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} section(s)</span>
        </x-slot:extra>
        @can('sections.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Sections du siège</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-sections">
            <colgroup><col><col style="width:12%"><col style="width:20%"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Section</th><th>Ordre</th><th>Chambres</th>@canany(['sections.modifier', 'sections.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td><div class="el-person__name">{{ $l->libelle }}</div></td>
                  <td class="el-num" data-order="{{ $l->ordre }}">{{ $l->ordre }}</td>
                  <td data-order="{{ $l->chambres_count }}"><x-chip :tone="$l->chambres_count ? 'info' : 'neutral'" :label="$l->chambres_count.' chambre(s)'" /></td>
                  @canany(['sections.modifier', 'sections.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('sections.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_section({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('sections.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_section({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('organisation.sections.form_section')
  @include('organisation.sections.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-sections', { order: [[1, 'asc']], noSort: [3], search: '#el-search' });
  </script>
@endsection
