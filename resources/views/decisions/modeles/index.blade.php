@extends('layout.master')
@section('title', 'Modèles de décision')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Décisions" title="Modèles de décision" subtitle="Un modèle par type de décision (hérédité, divorce, référé…), écrit en deux parties : la qualité, reprise des données de l'affaire, et le factum, rédigé par le juge. Chaque modification crée une nouvelle version ; les décisions déjà rédigées gardent celle qu'elles ont utilisée." icon="fa-solid fa-file-signature">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} modèle(s)</span>
        </x-slot:extra>
        @can('modeles.creer')
          <x-slot:actions>
            <a class="el-btn el-btn--accent" href="{{ route('decisions.modeles.nouveau') }}"><i class="fa-solid fa-plus"></i> Nouveau modèle</a>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Modèles de décision</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-modeles">
            <colgroup><col><col style="width:160px"><col style="width:160px"><col style="width:120px"><col style="width:140px"><col style="width:156px"></colgroup>
            <thead>
              <tr>
                <th>Modèle</th>
                <th>Matière</th>
                <th>Objets liés</th>
                <th>Version</th>
                <th>État</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                @php $v = $l->versionCourante(); @endphp
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name" title="{{ $l->nom }}">{{ $l->nom }}</div>
                    @if ($l->description)<div class="el-person__sub" title="{{ $l->description }}">{{ $l->description }}</div>@endif
                  </td>
                  <td>{{ $l->matiere?->libelle }}</td>
                  <td class="el-num" data-order="{{ $l->objets_count }}"><x-chip :tone="$l->objets_count ? 'info' : 'neutral'" :label="$l->objets_count.''" /></td>
                  <td data-order="{{ $v?->numero ?? 0 }}">@if ($v)<span title="{{ $v->created_at?->format('d/m/Y') }}">v{{ $v->numero }}</span>@else<span class="el-muted">—</span>@endif</td>
                  <td><x-chip :tone="['actif' => 'success', 'brouillon' => 'alert', 'archive' => 'neutral'][$l->etat] ?? 'neutral'" :label="\App\Models\ModeleDecision::ETATS[$l->etat] ?? $l->etat" /></td>
                  <td>
                    <div style="display:flex; gap:8px">
                      @can('modeles.modifier')
                        <a class="el-iconbtn el-iconbtn--blue" href="{{ route('decisions.modeles.edition', $l->id) }}" title="Modifier" aria-label="Modifier"><i class="fa-regular fa-pen-to-square"></i></a>
                      @else
                        <a class="el-iconbtn el-iconbtn--blue" href="{{ route('decisions.modeles.edition', $l->id) }}" title="Consulter" aria-label="Consulter"><i class="fa-regular fa-eye"></i></a>
                      @endcan
                      @can('modeles.creer')
                        <button class="el-iconbtn el-iconbtn--teal" type="button" title="Dupliquer" aria-label="Dupliquer" onclick="dupliquer_modele({{ $l->id }})"><i class="fa-regular fa-copy"></i></button>
                      @endcan
                      @can('modeles.supprimer')
                        <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_modele({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  <form id="form-duplication" method="post" action="" style="display:none">@csrf</form>
  @include('decisions.modeles.js_script_liste')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-modeles', { order: [[0, 'asc']], noSort: [5], search: '#el-search' });
  </script>
@endsection
