@extends('layout.master')
@section('title', 'Services et responsabilités')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Services et responsabilités" subtitle="La coordination du tribunal, les comités, les délégations, les services annexes, les bureaux locaux et les fonctions particulières (juge taxateur, testaments…) fixés par l'ordonnance." icon="fa-solid fa-building-columns">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-days"></i> {{ $annee?->libelle ?? 'Aucune année' }}</span>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} élément(s)</span>
        </x-slot:extra>
        @can('services.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <form method="GET" action="{{ route('organisation.services') }}" style="display:flex; align-items:center; gap:10px; min-width:260px">
            <label class="el-muted" for="filtre-annee" style="margin:0; white-space:nowrap">Année judiciaire</label>
            <select class="form-select" id="filtre-annee" name="annee" onchange="this.form.submit()" aria-label="Année judiciaire">
              @foreach ($annees as $a)
                <option value="{{ $a->id }}" @selected($annee?->id === $a->id)>{{ $a->libelle }}@if ($a->courante) (en cours)@endif</option>
              @endforeach
            </select>
          </form>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-services">
            <colgroup><col style="width:250px"><col><col style="width:28%"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Catégorie</th><th>Service ou fonction</th><th>Personnes</th>@canany(['services.modifier', 'services.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $i => $l)
                <tr id="row-{{ $l->id }}">
                  <td data-order="{{ $i }}"><x-chip tone="info" class="el-chip--libre" :label="\App\Models\Service::CATEGORIES[$l->categorie] ?? $l->categorie" /></td>
                  <td>
                    <div class="el-person__name">{{ $l->libelle }}</div>
                    @if ($l->remarque)<div class="el-person__sub" title="{{ \App\Support\TexteRiche::texte($l->remarque) }}">{{ \App\Support\TexteRiche::texte($l->remarque) }}</div>@endif
                  </td>
                  <td title="{{ $l->agents->map(fn ($sa) => $sa->agent?->nomComplet().($sa->fonction ? ' ('.$sa->fonction.')' : ''))->implode(' ; ') }}">
                    @if ($l->agents->isEmpty())
                      <span class="el-muted">—</span>
                    @else
                      {{ $l->agents->first()->agent?->nomComplet() }}@if ($l->agents->count() > 1) <span class="el-muted">+{{ $l->agents->count() - 1 }}</span>@endif
                    @endif
                  </td>
                  @canany(['services.modifier', 'services.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('services.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_service({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('services.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_service({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('organisation.services.form_service')
  @include('organisation.services.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-services', { order: [[0, 'asc']], noSort: [3], search: '#el-search' });
  </script>
@endsection
