@extends('layout.master')
@section('title', 'Chambres')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Chambres" subtitle="Dans chaque section, les chambres (ou juridictions, cabinets d'instruction) de l'année judiciaire, avec leurs attributions. Une chambre peut avoir plusieurs formations ; sa composition et ses audiences se règlent depuis la colonne « Composition »." icon="fa-solid fa-scale-balanced">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-days"></i> {{ $annee?->libelle ?? 'Aucune année' }}</span>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} chambre(s)</span>
        </x-slot:extra>
        @can('chambres.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <form method="GET" action="{{ route('organisation.chambres') }}" style="display:flex; align-items:center; gap:10px; min-width:260px">
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
          <table class="el-tbl el-tbl--fixe" id="tbl-chambres">
            <colgroup><col style="width:13%"><col><col style="width:9%"><col style="width:15%"><col style="width:120px"><col style="width:120px"><col style="width:150px"></colgroup>
            <thead>
              <tr><th>Section</th><th>Chambre</th><th>Formations</th><th>Président</th><th>Personnes</th><th>Statut</th><th>Actions</th></tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                @php
                  $affectations = $l->formations->flatMap->affectations;
                  $presidents = $affectations->where('role', 'president')->where('suppleant', false)->map(fn ($a) => $a->agent?->nomComplet())->unique()->filter();
                  $nommees = $l->formations->filter(fn ($f) => $f->nom);
                @endphp
                <tr id="row-{{ $l->id }}">
                  <td data-order="{{ $l->section->ordre }}" title="{{ $l->section->libelle }}">
                    <div class="el-person__name">{{ $l->section->libelle }}</div>
                    @if ($l->groupe)<div class="el-person__sub">{{ $l->groupe }}</div>@endif
                  </td>
                  <td data-order="{{ $l->ordre }}">
                    <div class="el-person__name">{{ $l->libelle }}</div>
                    @if ($l->intitule)<div class="el-person__name" style="font-weight:500">{{ $l->intitule }}</div>@endif
                    @if ($l->attributions)<div class="el-person__sub" title="{{ \App\Support\TexteRiche::texte($l->attributions) }}">{{ \App\Support\TexteRiche::texte($l->attributions) }}</div>@endif
                  </td>
                  <td data-order="{{ $nommees->count() }}" title="{{ $nommees->map(fn ($f) => $f->libelle())->implode(' ; ') }}">
                    @if ($nommees->isEmpty())
                      <span class="el-muted">Formation unique</span>
                    @else
                      {{ $nommees->count() }} formation(s)
                    @endif
                  </td>
                  <td title="{{ $presidents->implode(' ; ') }}">
                    @if ($presidents->isEmpty())
                      <span class="el-muted">—</span>
                    @else
                      {{ $presidents->first() }}@if ($presidents->count() > 1) <span class="el-muted">+{{ $presidents->count() - 1 }}</span>@endif
                    @endif
                  </td>
                  <td data-order="{{ $affectations->count() }}" title="{{ $affectations->count() }} personne(s), {{ $l->formations->sum(fn ($f) => $f->creneaux->count()) }} audience(s)"><x-chip :tone="$affectations->count() ? 'info' : 'neutral'" :label="$affectations->count().' pers.'" /></td>
                  <td><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Active' : 'Inactive'" /></td>
                  <td>
                    <div style="display:flex; gap:8px">
                      @can('affectations.voir')
                        <a class="el-iconbtn el-iconbtn--teal" href="{{ route('organisation.affectations', $l->id) }}" title="Composition et formations" aria-label="Composition de {{ $l->libelleComplet() }}"><i class="fa-solid fa-users"></i></a>
                      @endcan
                      @can('chambres.modifier')
                        <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_chambre({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                      @endcan
                      @can('chambres.supprimer')
                        <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_chambre({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                      @endcan
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @include('organisation.chambres.form_chambre')
  @include('organisation.chambres.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-chambres', { order: [[0, 'asc'], [1, 'asc']], noSort: [6], search: '#el-search' });
  </script>
@endsection
