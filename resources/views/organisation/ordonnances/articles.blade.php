@extends('layout.master')
@section('title', "Articles de l'ordonnance")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/import-ordonnance.css') }}?v={{ filemtime(public_path('assets/css/import-ordonnance.css')) }}">
@endsection

@section('main-content')
  <div class="page-body io-page">
    <div class="el-page tp-anime">

      <x-hero tag="Organisation" title="Articles de l'ordonnance" subtitle="Le texte des articles de l'ordonnance en vigueur, tel qu'il est enregistré. Il se met à jour quand on charge un nouveau fichier d'ordonnance." icon="fa-solid fa-scroll">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-days"></i> Année {{ $annee?->libelle ?? '—' }}</span>
          <span><i class="fa-solid fa-list-ol"></i> {{ $articles->count() }} article(s)</span>
        </x-slot:extra>
      </x-hero>

      @include('organisation.ordonnances._onglets', ['actif' => 'articles'])

      @if ($articles->isEmpty())
        <div class="io-vide">Aucun article n'est enregistré pour cette année. Ils arrivent avec le chargement d'un fichier d'ordonnance (feuille « Articles »).</div>
      @else
        @foreach ($articles as $a)
          <div class="el-card" style="margin-bottom:14px">
            <div class="el-card__head">
              <div>
                <h2 class="el-card__title">{{ $a->numero }}@if ($a->intitule) <span class="el-muted" style="font-weight:600">· {{ $a->intitule }}</span>@endif</h2>
                @if ($a->rattache_a)<div class="el-card__sub"><i class="fa-solid fa-link"></i> Pilote : {{ \App\Models\ArticleOrdonnance::RATTACHEMENTS[$a->rattache_a] ?? $a->rattache_a }}</div>@endif
              </div>
            </div>
            <div class="el-card__body" style="padding-top:4px">
              @foreach (preg_split('/\R+/u', (string) $a->texte) as $ligne)
                <p style="margin:0 0 8px; line-height:1.6">{{ $ligne }}</p>
              @endforeach
            </div>
          </div>
        @endforeach
      @endif

    </div>
  </div>
@endsection
