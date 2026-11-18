@extends('layout.master')
@section('title', 'Chambres')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
  <style>
    /* Formation d'une chambre : un clic l'ouvre pour la modifier (grisée et barrée si elle est inactive) */
    /* « .el-tbl » : le thème des tableaux (.dataTables_wrapper button) impose sinon un texte blanc à tous les boutons */
    .el-tbl .formation-pastille { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; font: 600 12.5px/1 var(--police-app); background: #fff; color: #0B5A3A; border: 1.5px solid #00794C; white-space: nowrap; }
    .el-tbl button.formation-pastille { cursor: pointer; transition: background .15s, color .15s; }
    .el-tbl button.formation-pastille i { font-size: 10px; opacity: .55; }
    .el-tbl button.formation-pastille:hover { background: #00794C; color: #fff; }
    .el-tbl button.formation-pastille:hover i { opacity: 1; }
    .el-tbl .formation-pastille.is-inactive { background: #F1F3F6; color: #6B7280; border: 1.5px dashed #B6BDC8; text-decoration: line-through; }
  </style>
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Chambres" subtitle="La structure du tribunal : dans chaque section, les chambres (ou juridictions, cabinets d'instruction), leurs attributions et leurs formations. Les personnes qui y siègent et leurs jours d'audience se règlent dans « Composition des chambres »." icon="fa-solid fa-scale-balanced">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-days"></i> {{ $annee?->libelle ?? 'Aucune année' }}</span>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} chambre(s)</span>
          <x-ordonnance-en-vigueur :annee="$annee?->id" />
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
            <colgroup><col style="width:15%"><col><col style="width:26%"><col style="width:100px"><col style="width:150px"></colgroup>
            <thead>
              <tr><th>Section</th><th>Chambre et attributions</th><th>Formations</th><th>Statut</th><th>Actions</th></tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
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
                  <td data-order="{{ $l->formations->count() }}">
                    {{-- Un clic sur une formation l'ouvre pour la modifier ; le « + » en ajoute une --}}
                    <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center">
                      @foreach ($l->formations as $f)
                        @if ($f->nom)
                          @can('formations.modifier')
                            <button type="button" class="formation-pastille {{ $f->actif ? '' : 'is-inactive' }}" title="Modifier : {{ $f->libelle() }}{{ $f->actif ? '' : ' (inactive)' }}" onclick="edit_formation({{ $f->id }})">{{ $f->nom }}<i class="fa-solid fa-pen"></i></button>
                          @else
                            <span class="formation-pastille {{ $f->actif ? '' : 'is-inactive' }}" title="{{ $f->libelle() }}{{ $f->actif ? '' : ' (inactive)' }}">{{ $f->nom }}</span>
                          @endcan
                        @endif
                      @endforeach
                      @if ($l->formations->every(fn ($f) => ! $f->nom))
                        <span class="el-muted">Formation unique</span>
                      @endif
                      @can('formations.creer')
                        <button type="button" class="el-iconbtn el-iconbtn--teal" style="width:28px; height:28px" title="Ajouter une formation" aria-label="Ajouter une formation à {{ $l->libelleComplet() }}" onclick="add_formation({{ $l->id }})"><i class="fa-solid fa-plus"></i></button>
                      @endcan
                    </div>
                  </td>
                  <td><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Active' : 'Inactive'" /></td>
                  <td>
                    <div style="display:flex; gap:8px">
                      @can('affectations.voir')
                        <a class="el-iconbtn el-iconbtn--teal" href="{{ route('organisation.composition', ['annee' => $l->annee_judiciaire_id, 'ouvrir' => $l->id]) }}" title="Voir la composition" aria-label="Composition de {{ $l->libelleComplet() }}"><i class="fa-solid fa-users"></i></a>
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
  @include('organisation.formations.form_formation')
  @include('organisation.formations.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-chambres', { order: [[0, 'asc'], [1, 'asc']], noSort: [4], search: '#el-search' });
  </script>
@endsection
