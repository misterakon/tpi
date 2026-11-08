@extends('layout.master')
@section('title', 'Instruction')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
@endsection

@section('main-content')
    <div class="page-body">
    <div class="el-page">

      <x-hero tag="Instruction" title="Dossiers d'instruction" subtitle="Le suivi des dossiers des cabinets d'instruction et du juge des enfants, comme dans les classeurs du tribunal : réquisitoire, faits, inculpés et détention, victimes et auditions, ordonnance de clôture, commissions rogatoires." icon="fa-solid fa-magnifying-glass">
        <x-slot:extra>
          <span><i class="fa-solid fa-folder-open"></i> {{ $total }} dossier(s)</span>
          <span><i class="fa-solid fa-hourglass-half"></i> {{ $total - $clos }} en cours</span>
          <span><i class="fa-solid fa-lock"></i> {{ $detenus }} détenu(s)</span>
        </x-slot:extra>
        <x-slot:actions>
          <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_cr()"><i class="fa-solid fa-scroll"></i> Commissions rogatoires</button>
          @can('stats_registres.voir')<a class="el-btn st-btn-pdf" href="{{ route('statistiques.registres') }}"><i class="fa-solid fa-file-excel"></i> Registres</a>@endcan
        </x-slot:actions>
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="5" data-value="">Tous</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="en_cours">En cours</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="clos">Clôturés</button>
          </div>
          <label class="el-search" style="max-width:320px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="el-search" placeholder="N°, inculpé, infraction…" autocomplete="off" aria-label="Rechercher"></label>
        </div>
        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-instruction" data-serveur="{{ route('instruction.donnees') }}">
            <colgroup><col style="width:120px"><col style="width:170px"><col><col style="width:210px"><col style="width:120px"><col style="width:140px"><col style="width:110px"></colgroup>
            <thead><tr><th>Dossier</th><th>Cabinet</th><th>Inculpés et infraction</th><th>Saisine</th><th>Détenus</th><th>État</th><th>Actions</th></tr></thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- Fenêtre du dossier : dossier, inculpés, victimes --}}
  <div class="modal fade panel-modal" id="modal-dossier" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header"><h4 class="modal-title" id="t-dossier">Dossier</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <div class="modal-body dark-modal">
        <div class="st-onglets" id="onglets-dossier" style="margin-top:0">
          <button type="button" class="st-onglet is-actif" data-pan="d-fiche">Le dossier</button>
          <button type="button" class="st-onglet" data-pan="d-inculpes">Inculpés <span class="st-pastille" id="n-inc">0</span></button>
          <button type="button" class="st-onglet" data-pan="d-victimes">Victimes <span class="st-pastille" id="n-vic">0</span></button>
        </div>
        <div class="aff-erreur" id="e-dossier" hidden></div>
        <section id="d-fiche" class="d-pan"></section>
        <section id="d-inculpes" class="d-pan" hidden></section>
        <section id="d-victimes" class="d-pan" hidden></section>
      </div>
    </div></div>
  </div>

  {{-- Commissions rogatoires --}}
  <div class="modal fade panel-modal" id="modal-cr" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <div class="modal-header"><h4 class="modal-title">Commissions rogatoires</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <div class="modal-body dark-modal">
        <div class="st-filtres" style="grid-template-columns: repeat(4, minmax(0,1fr)); padding:0 0 12px">
          <div class="st-champ"><label>Cabinet</label><select class="form-select" id="cr_chambre">@foreach ($cabinets as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select></div>
          <div class="st-champ"><label>Année</label><select class="form-select" id="cr_annee">@for ($y = now()->year + 1; $y >= now()->year - 6; $y--)<option value="{{ $y }}" @selected($y === now()->year)>{{ $y }}</option>@endfor</select></div>
          <div class="st-champ"><label>Sens</label><select class="form-select" id="cr_sens" data-sans-recherche><option value="recu">Reçues</option><option value="emis">Émises</option></select></div>
          <div class="st-champ"><label>Portée</label><select class="form-select" id="cr_portee" data-sans-recherche><option value="nationale">Nationales</option><option value="internationale">Internationales</option></select></div>
        </div>
        <div class="aff-erreur" id="e-cr" hidden></div>
        <table class="el-tbl" id="tbl-cr"><thead><tr><th>Mois</th><th style="width:130px">Stock initial</th><th style="width:130px">Nouvelles</th><th style="width:130px">Exécutées</th><th style="width:130px">Stock restant</th></tr></thead><tbody></tbody></table>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>@if ($peutModifier)<button class="btn btn-primary text-white" type="button" onclick="enregistrer_cr()">Enregistrer</button>@endif</div>
    </div></div>
  </div>

  @include('instruction.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>ElTable.init('#tbl-instruction', { order: [[0, 'desc']], noSort: [1, 2, 3, 4, 6], search: '#el-search' });</script>
@endsection
