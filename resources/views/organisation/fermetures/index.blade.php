@extends('layout.master')
@section('title', 'Jours fériés et vacances')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Jours fériés et vacances" subtitle="Les jours où le tribunal ne tient pas d'audience : jours fériés, vacances judiciaires et fermetures exceptionnelles. Une journée seule a la même date de début et de fin." icon="fa-solid fa-umbrella-beach">
        <x-slot:extra>
          <span><i class="fa-regular fa-calendar"></i> {{ $annee ? 'Année '.$annee->libelle : 'Aucune année judiciaire' }}</span>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} période(s)</span>
        </x-slot:extra>
        @can('fermetures.creer')
          @if ($annee)
            <x-slot:actions>
              <form method="post" action="{{ route('organisation.fermetures.feries') }}" style="display:inline">
                @csrf
                <input type="hidden" name="annee_judiciaire_id" value="{{ $annee->id }}">
                <button class="el-btn el-btn--ghost" type="submit" title="Jour de l'An, 1er mai, 7 et 15 août, Toussaint, 15 novembre, Noël"><i class="fa-solid fa-wand-magic-sparkles"></i> Ajouter les fêtes fixes</button>
              </form>
              <button class="el-btn el-btn--accent" type="button" onclick="add_form({{ $annee->id }})"><i class="fa-solid fa-plus"></i> Ajouter</button>
            </x-slot:actions>
          @endif
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <label style="display:flex; align-items:center; gap:10px; margin:0">
            <span class="el-muted">Année judiciaire</span>
            <select class="form-select" id="choix-annee" style="min-width:170px" data-sans-recherche>
              @foreach ($annees as $a)
                <option value="{{ $a->id }}" @selected($annee && $a->id === $annee->id)>{{ $a->libelle }}</option>
              @endforeach
            </select>
          </label>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-fermetures">
            <colgroup><col><col style="width:200px"><col style="width:112px"><col style="width:112px"><col style="width:80px"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Libellé</th><th>Type</th><th>Du</th><th>Au</th><th>Jours</th>@canany(['fermetures.modifier', 'fermetures.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div>
                    @if ($l->remarque)<div class="el-person__sub" title="{{ $l->remarque }}">{{ $l->remarque }}</div>@endif
                  </td>
                  <td><x-chip :tone="['ferie' => 'success', 'vacances' => 'info', 'fermeture' => 'alert'][$l->type] ?? 'neutral'" :label="\App\Models\Fermeture::TYPES[$l->type] ?? $l->type" class="el-chip--libre" /></td>
                  <td data-order="{{ $l->debut->format('Y-m-d') }}">{{ $l->debut->format('d/m/Y') }}</td>
                  <td data-order="{{ $l->fin->format('Y-m-d') }}">{{ $l->fin->format('d/m/Y') }}</td>
                  <td class="el-num" data-order="{{ $l->jours() }}">{{ $l->jours() }}</td>
                  @canany(['fermetures.modifier', 'fermetures.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('fermetures.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_fermeture({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('fermetures.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_fermeture({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('organisation.fermetures.form_fermeture')
  @include('organisation.fermetures.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-fermetures', { order: [[2, 'asc']], noSort: [5], search: '#el-search' });
    $('#choix-annee').on('change', function () {
      window.location.href = @js(route('organisation.fermetures')) + '?annee=' + encodeURIComponent(this.value);
    });
  </script>
@endsection
