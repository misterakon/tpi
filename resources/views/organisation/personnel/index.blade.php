@extends('layout.master')
@section('title', 'Personnel du tribunal')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <style>
    .el-kpi--filtre { cursor: pointer; text-align: left; font: inherit; color: inherit; border: 1px solid var(--el-line); }
    .el-kpi--filtre.is-active { border-color: #F77F00; box-shadow: 0 0 0 3px rgba(247, 127, 0, .18); }
    .el-kpi--filtre .el-kpi__label { line-height: 1.25; }
  </style>
@endsection

@section('main-content')
  @php
    $categories = \App\Models\Agent::CATEGORIES;
    $parCategorie = $lignes->groupBy('categorie');
    $horsPoste = $lignes->where('actif', false)->count();
  @endphp

  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Personnel du tribunal" subtitle="Toutes les personnes qui travaillent avec le tribunal : magistrats, greffiers, assesseurs, personnel administratif et technique (agents inter-ministériels) et membres extérieurs. Un agent n'a pas besoin d'avoir un compte pour être enregistré." icon="fa-solid fa-user-tie">
        <x-slot:extra>
          <span><i class="fa-solid fa-users"></i> {{ $lignes->count() }} personne(s)</span>
          @if ($horsPoste)<span><i class="fa-solid fa-user-clock"></i> {{ $horsPoste }} hors poste</span>@endif
        </x-slot:extra>
        @can('personnel.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      {{-- Une carte par catégorie : un clic filtre la liste --}}
      <div class="el-kpis">
        @foreach ($categories as $cle => $categorie)
          @php $groupe = $parCategorie->get($cle, collect()); @endphp
          <button type="button" class="el-kpi el-kpi--filtre" data-categorie="{{ $cle }}" aria-label="Afficher : {{ $categorie['libelle'] }}">
            <span class="el-kpi__icon el-tone-{{ $categorie['ton'] }}"><i class="{{ $categorie['icone'] }}"></i></span>
            <span>
              <div class="el-kpi__value">{{ $groupe->count() }}</div>
              <div class="el-kpi__label">{{ $categorie['libelle'] }}</div>
              @if ($groupe->where('actif', false)->count())<div class="el-kpi__note">{{ $groupe->where('actif', false)->count() }} hors poste</div>@endif
            </span>
          </button>
        @endforeach
      </div>

      <div class="el-note" style="margin:0">
        <span class="el-note__icon"><i class="fa-solid fa-circle-info"></i></span>
        <div>
          <div class="el-note__title">Qui est affecté aux chambres ?</div>
          <div class="el-note__text">Seuls les magistrats, greffiers et assesseurs figurent dans l'ordonnance et peuvent être affectés aux chambres. Le personnel administratif et technique et les membres extérieurs sont enregistrés ici, sans affectation de chambre.</div>
        </div>
      </div>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="1" data-value="">Tous</button>
            @foreach ($categories as $cle => $categorie)
              <button type="button" class="el-pill" data-el-filter="1" data-value="{{ $cle }}">{{ $categorie['libelle'] }}</button>
            @endforeach
          </div>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Nom, matricule, fonction…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-personnel">
            <colgroup><col><col style="width:200px"><col style="width:22%"><col style="width:17%"><col style="width:110px">@canany(['personnel.modifier', 'personnel.supprimer'])<col style="width:112px">@endcanany</colgroup>
            <thead>
              <tr><th>Personne</th><th>Catégorie</th><th>Fonction</th><th>Compte</th><th>Statut</th>@canany(['personnel.modifier', 'personnel.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person">
                      <x-avatar :name="$l->nom.' '.$l->prenoms" :size="38" />
                      <div>
                        <div class="el-person__name">{{ $l->nomComplet() }}</div>
                        @if ($l->matricule)<div class="el-person__sub">Matricule {{ $l->matricule }}</div>@endif
                      </div>
                    </div>
                  </td>
                  <td data-search="{{ $l->categorie }}"><x-chip :tone="$categories[$l->categorie]['chip'] ?? 'neutral'" :label="$categories[$l->categorie]['singulier'] ?? 'Autre'" /></td>
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
    ElTable.init('#tbl-personnel', { order: [[0, 'asc']], noSort: [5], search: '#el-search', pageLength: 15 });

    // Arrivée depuis la fiche d'une personne (« Modifier cette personne ») : son formulaire s'ouvre directement.
    @can('personnel.modifier')
      const A_MODIFIER = @js((int) request()->query('modifier'));
      if (A_MODIFIER) { edit_agent(A_MODIFIER); }
    @endcan

    // Une carte de catégorie = le filtre correspondant ; cliquer à nouveau la désactive.
    $(document).on('click', '.el-kpi--filtre', function () {
      const cle = $(this).data('categorie');
      const dejaActive = $(this).hasClass('is-active');
      $('.el-pill[data-value="' + (dejaActive ? '' : cle) + '"]').trigger('click');
    });
    // Les pastilles et les cartes restent synchronisées.
    $(document).on('click', '.el-pill[data-el-filter]', function () {
      const valeur = $(this).attr('data-value') || '';
      $('.el-kpi--filtre').removeClass('is-active').filter('[data-categorie="' + valeur + '"]').addClass('is-active');
    });
  </script>
@endsection
