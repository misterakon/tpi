@extends('layout.master')
@section('title', 'Magistrats et greffiers')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Magistrats et greffiers" subtitle="Les personnes du tribunal qui pourront être affectées aux chambres et formations. Un agent n'a pas besoin d'avoir un compte pour être enregistré." icon="fa-solid fa-user-tie">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} agent(s)</span>
        </x-slot:extra>
        @can('personnel.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Agents enregistrés</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-personnel">
            <colgroup><col><col style="width:24%"><col style="width:20%"><col style="width:12%"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Agent</th><th>Fonction</th><th>Compte</th><th>Statut</th>@canany(['personnel.modifier', 'personnel.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name">{{ $l->nomComplet() }}</div>
                    @if ($l->matricule)<div class="el-person__sub">Matricule {{ $l->matricule }}</div>@endif
                  </td>
                  <td>{{ $l->fonctionLibelle() }}</td>
                  <td><x-chip :tone="$l->user ? 'info' : 'neutral'" :label="$l->user?->name ?? 'Aucun compte'" /></td>
                  <td><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'En poste' : 'Hors poste'" /></td>
                  @canany(['personnel.modifier', 'personnel.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('personnel.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_agent({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('personnel.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_agent({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('organisation.personnel.form_agent')
  @include('organisation.personnel.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-personnel', { order: [[0, 'asc']], noSort: [4], search: '#el-search' });
  </script>
@endsection
