@extends('layout.master')
@section('title', 'Liste des affaires')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/dossier-numerique.css') }}?v={{ filemtime(public_path('assets/css/dossier-numerique.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page tp-anime">

      <x-hero tag="Affaires" title="Liste des affaires" subtitle="Toutes les affaires enregistrées, de la plus récente à la plus ancienne. Cliquez sur un numéro pour ouvrir la fiche." icon="fa-solid fa-folder-open">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ number_format($total, 0, ',', ' ') }} affaire(s){{ array_filter($filtres) ? ' (filtre actif)' : '' }}</span>
        </x-slot:extra>
        @can('enrolement.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_choix()"><i class="fa-solid fa-plus"></i> {{ $vocab['ajout'] }}</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <form class="aff-filtres" method="get" action="{{ route('affaires.liste') }}" id="form-filtres" autocomplete="off">
          <div class="aff-filtres__champ aff-filtres__champ--large">
            <label class="form-label" for="flt_chambre"><b>Chambre</b></label>
            <select class="form-select" id="flt_chambre" name="chambre">
              <option value="">Toutes les chambres</option>
              @foreach ($chambres as $c)
                <option value="{{ $c->id }}" @selected($filtres['chambre'] === $c->id)>{{ $c->libelle }}</option>
              @endforeach
            </select>
          </div>
          <div class="aff-filtres__champ">
            <label class="form-label" for="flt_du"><b>Du</b></label>
            <input class="form-control" type="date" id="flt_du" name="du" value="{{ $filtres['du'] }}">
          </div>
          <div class="aff-filtres__champ">
            <label class="form-label" for="flt_au"><b>Au</b></label>
            <input class="form-control" type="date" id="flt_au" name="au" value="{{ $filtres['au'] }}">
          </div>
          <div class="aff-filtres__champ">
            <label class="form-label" for="flt_statut"><b>Statut</b></label>
            <select class="form-select" id="flt_statut" name="statut" data-sans-recherche>
              <option value="">Tous</option>
              @foreach (\App\Models\Affaire::STATUTS as $code => $libelle)
                <option value="{{ $code }}" @selected($filtres['statut'] === $code)>{{ $libelle }}</option>
              @endforeach
            </select>
          </div>
          <div class="aff-filtres__actions">
            <button class="el-btn el-btn--accent" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button>
            @if (array_filter($filtres))
              <a class="el-btn el-btn--ghost" href="{{ route('affaires.liste') }}"><i class="fa-solid fa-xmark"></i> Réinitialiser</a>
            @endif
            @can('affaires.exporter')
              <a class="el-btn el-btn--ghost" href="{{ route('affaires.liste.export', array_filter($filtres) + ['format' => 'xlsx']) }}" data-export title="Exporter la liste filtrée au format Excel"><i class="fa-solid fa-file-excel"></i> Excel</a>
              <a class="el-btn el-btn--ghost" href="{{ route('affaires.liste.export', array_filter($filtres) + ['format' => 'pdf']) }}" data-export title="Exporter la liste filtrée au format PDF"><i class="fa-solid fa-file-pdf"></i> PDF</a>
            @endcan
          </div>
        </form>

        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="1" data-value="">Toutes</button>
            <button type="button" class="el-pill" data-el-filter="1" data-value="RG">Civil (RG)</button>
            <button type="button" class="el-pill" data-el-filter="1" data-value="RP">Pénal (RP)</button>
            <button type="button" class="el-pill" data-el-filter="1" data-value="RI">Instruction (RI)</button>
            <button type="button" class="el-pill" data-el-filter="1" data-value="RE">Enfants (RE)</button>
          </div>
          <label class="el-search" style="max-width:320px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Numéro, partie, objet…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-affaires" data-serveur="{{ route('affaires.liste.donnees', array_filter($filtres)) }}">
            <colgroup><col style="width:132px"><col style="width:96px"><col style="width:122px"><col><col style="width:190px"><col style="width:180px"><col style="width:150px"></colgroup>
            <thead>
              <tr><th>Numéro</th><th>Registre</th><th>Date</th><th>Parties et objet</th><th>Nature</th><th>Chambre</th><th>Statut</th></tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @can('enrolement.creer')
    @include('affaires.liste.form_choix')
    @include('affaires.liste.form_saisie')
    @include('affaires.liste.form_saisie_penal')
    @include('affaires.liste.form_saisie_instruction')
    @include('affaires.enrolement.js_script')
    @include('affaires.enrolement.js_script_poursuite')
  @endcan
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-affaires', { order: [[2, 'desc']], noSort: [4, 5], search: '#el-search' });

    /* l'export tient compte aussi du registre choisi dans les pastilles (Civil, Pénal…) */
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('[data-export]').forEach(function (lien) {
        const base = lien.getAttribute('href');
        lien.addEventListener('click', function () {
          const actif = document.querySelector('.el-pill.is-active[data-el-filter="1"]');
          const serie = actif ? actif.getAttribute('data-value') : '';
          lien.setAttribute('href', base + (serie ? '&serie=' + encodeURIComponent(serie) : ''));
          setTimeout(function () { lien.setAttribute('href', base); }, 300);
        });
      });
    });
  </script>
@endsection
