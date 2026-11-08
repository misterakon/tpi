@extends('layout.master')
@section('title', 'Renvois et délibérés')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page">

      <x-hero tag="Audiences" title="Renvois et délibérés" subtitle="Toutes les affaires renvoyées avec leurs dates, leur motif et leur nombre de renvois ; tous les délibérés avec leur date de prononcé. Une affaire revient d'elle-même sur le rôle à sa date." icon="fa-solid fa-arrows-rotate">
        <x-slot:actions>
          @can('feuille.voir')<a class="el-btn el-btn--ghost" href="{{ route('audiences.feuille') }}"><i class="fa-solid fa-clipboard-list"></i> Feuille d'audience</a>@endcan
        </x-slot:actions>
      </x-hero>

      <div class="au-kpis au-kpis--compact" id="kpis-renvois" style="grid-template-columns: repeat(4, minmax(0, 1fr))"></div>

      <div class="au-cadre">
        <div class="au-barre-onglets">
          <div class="au-onglets" role="tablist" id="onglets-type">
            <button type="button" class="is-actif" data-type="renvoyee" role="tab">Renvois <b id="n-renvoyee"></b></button>
            <button type="button" data-type="deliberee" role="tab">Délibérés <b id="n-deliberee"></b></button>
          </div>
          <div style="min-width:220px;max-width:300px;flex:1 1 220px">
            <select class="form-select" id="f-chambre" data-sans-recherche>
              <option value="">Toutes les chambres</option>
              @foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach
            </select>
          </div>
          <div class="au-recherche"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="recherche" placeholder="Numéro, partie, objet…" autocomplete="off"></div>
        </div>
        <div class="au-compte-filtres" id="filtres-etat"></div>
        <div class="au-fond"><div class="au-cartes-renvois" id="liste"></div></div>
        <div class="au-pagination" id="pagination-renvois"></div>
      </div>

    </div>
  </div>
  @if ($peutModifier)
    @include('audiences.renvois.form_date')
  @endif
@endsection

@section('script')
  @include('audiences.renvois.js_script', ['lignes' => $lignes, 'peutModifier' => $peutModifier])
@endsection
