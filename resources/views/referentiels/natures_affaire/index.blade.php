@extends('layout.master')
@section('title', 'Natures d\'affaire')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Référentiels" title="Natures d'affaire" subtitle="Les catégories des états statistiques du Ministère (civile ordinaire, état des personnes, matrimoniale, référé…). Les référés et les voies d'exécution sont exclus des dates limites d'enrôlement (art. 7 de l'ordonnance)." icon="fa-solid fa-folder-tree">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} élément(s)</span>
        </x-slot:extra>
        @can('natures_affaire.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Natures d'affaire</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-natures_affaire">
            <colgroup><col><col style="width:150px"><col style="width:130px"><col style="width:96px"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Nature d'affaire</th><th>Registre</th><th>Enrôlement</th><th>État</th>@canany(['natures_affaire.modifier', 'natures_affaire.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div>
                  </td>
                  <td>
                    <x-chip tone="info" :label="\App\Models\NatureAffaire::REGISTRES[$l->registre] ?? $l->registre" class="el-chip--libre" />
                  </td>
                  <td>
                    @if ($l->exclue_enrolement)<x-chip tone="alert" label="Exclue" />@else<span class="el-muted">—</span>@endif
                  </td>
                  <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
                  @canany(['natures_affaire.modifier', 'natures_affaire.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('natures_affaire.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_nature_affaire({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('natures_affaire.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_nature_affaire({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('referentiels.natures_affaire.form_natures_affaire')
  @include('referentiels.natures_affaire.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-natures_affaire', { order: [[0, 'asc']], noSort: [4], search: '#el-search' });
  </script>
@endsection
