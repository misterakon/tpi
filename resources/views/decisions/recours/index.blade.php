@extends('layout.master')
@section('title', 'Recours et certificats')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
@endsection

@section('main-content')
  @php
    $tons = ['a_signifier' => 'warning', 'en_cours' => 'info', 'expire' => 'success', 'recours' => 'danger', 'certifie' => 'success', 'sans_delai' => 'neutral'];
  @endphp
  <div class="page-body">
    <div class="el-page tp-anime">

      <x-hero tag="Décisions" title="Recours et certificats" subtitle="Pour chaque décision signée : la signification qui fait courir le délai de recours, les recours formés et leur transmission à la Cour d'appel, puis le certificat de non-appel ou de non-opposition à l'expiration du délai." icon="fa-solid fa-scale-balanced">
        <x-slot:extra>
          <span><i class="fa-solid fa-file-circle-question"></i> <b data-compte="a_signifier">…</b> à signifier</span>
          <span><i class="fa-solid fa-hourglass-half"></i> <b data-compte="en_cours">…</b> délais en cours</span>
          <span><i class="fa-solid fa-stamp"></i> <b data-compte="a_certifier">…</b> certificats à délivrer</span>
          <span><i class="fa-solid fa-gavel"></i> <b data-compte="recours">…</b> avec recours</span>
        </x-slot:extra>
        @if ($peutDelais)
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" data-bs-toggle="modal" data-bs-target="#modal-delais"><i class="fa-solid fa-sliders"></i> Délais de recours</button>
          </x-slot:actions>
        @endif
      </x-hero>

      @if ($delais->contains(fn ($x) => ! $x->valide && $x->actif))
        <div class="aff-info" style="margin-bottom:12px"><i class="fa-solid fa-triangle-exclamation"></i><span>Certains délais de recours ne sont pas encore confirmés : tant qu'ils ne le sont pas, aucun certificat ne peut être délivré.</span></div>
      @endif

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="4" data-value="">Toutes</button>
            @foreach (\App\Support\Recours\DelaisRecours::ETATS as $code => $lib)
              <button type="button" class="el-pill" data-el-filter="4" data-value="{{ $code }}">{{ $lib }}</button>
            @endforeach
          </div>
          <label class="el-search" style="max-width:320px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Décision, affaire, parties…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-recours" data-serveur="{{ route('decisions.recours.donnees') }}">
            <colgroup><col style="width:120px"><col style="width:130px"><col><col style="width:120px"><col style="width:190px"><col style="width:130px"><col style="width:170px"></colgroup>
            <thead><tr><th>Décision</th><th>Affaire</th><th>Parties et objet</th><th>Signification</th><th>État</th><th>Échéance</th><th>Actions</th></tr></thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @include('decisions.recours.formulaires')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-recours', { order: [[0, 'desc']], noSort: [2, 3, 5, 6], search: '#el-search' });
    $.getJSON(@json(route('decisions.recours.compteurs')), function (c) { $.each(c, function (k, v) { $('[data-compte="' + k + '"]').text(v); }); });
  </script>
  @if (session('certificat'))
    <script>window.open(@js(route('decisions.recours.imprimer', session('certificat'))), '_blank');</script>
  @endif
@endsection
