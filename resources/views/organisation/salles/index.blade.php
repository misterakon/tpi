@extends('layout.master')
@section('title', 'Salles et lieux')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Salles" subtitle="Les salles où se tiennent les audiences. Ils seront proposés dans le calendrier des audiences de chaque formation." icon="fa-solid fa-door-open">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} salle(s)</span>
        </x-slot:extra>
        @can('salles.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Salles enregistrés</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-salles">
            <colgroup><col><col style="width:24%"><col style="width:18%"><col style="width:112px"></colgroup>
            <thead>
              <tr>
                <th>Nom</th>
                <th>Lieu</th>
                <th>Statut</th>
                @canany(['salles.modifier', 'salles.supprimer'])
                <th>Actions</th>
                @endcanany
              </tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name">{{ $l->nom }}</div>
                    @if ($l->emplacement)<div class="el-person__sub">{{ $l->emplacement }}</div>@endif
                  </td>
                  <td>{{ $l->typeLibelle() }}</td>
                  <td><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Utilisable' : 'Hors service'" /></td>
                  @canany(['salles.modifier', 'salles.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('salles.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_salle({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('salles.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_salle({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('organisation.salles.form_salle')
  @include('organisation.salles.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-salles', { order: [[0, 'asc']], noSort: [3], search: '#el-search' });
  </script>
@endsection
