@extends('layout.master')
@section('title', 'Recherche')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
  <style>
    .rch-barre { display: flex; gap: 10px; }
    .rch-barre input { flex: 1; height: 52px; font-size: 18px; padding: 0 18px; border-radius: 14px; border: 2px solid #cfe3d8; }
    .rch-barre input:focus { border-color: #00794c; outline: 0; box-shadow: 0 0 0 4px rgba(0, 121, 76, .12); }
    .rch-item { display: block; padding: 11px 14px; border-top: 1px solid #eef2ef; color: inherit; text-decoration: none; }
    .rch-item:hover { background: #f4faf6; } .rch-item b { display: block; } .rch-item span { color: #2f3b52; font-size: 13px; }
    .rch-item mark, .rch-item b mark { background: #ffe3bf; color: inherit; padding: 0 2px; border-radius: 3px; }
    .rch-tete { display: flex; align-items: center; gap: 10px; padding: 12px 16px; font-weight: 800; background: linear-gradient(90deg, #00794c, #19a06b); color: #fff; border-radius: 14px 14px 0 0; }
    .rch-tete small { margin-left: auto; background: rgba(255, 255, 255, .2); border-radius: 99px; padding: 2px 11px; font-weight: 700; }
    .rch-carte { background: #fff; border: 1px solid #e3ebe6; border-radius: 14px; margin-bottom: 16px; box-shadow: 0 4px 14px rgba(12, 60, 40, .05); overflow: hidden; }
  </style>
@endsection

@section('main-content')
  @php
    $surligner = function (?string $texte) use ($termes) {
        $sortie = e((string) $texte);
        foreach ($termes as $t) { $sortie = preg_replace('/('.preg_quote(e($t), '/').')/iu', '<mark>$1</mark>', $sortie) ?? $sortie; }
        return new \Illuminate\Support\HtmlString($sortie);
    };
  @endphp
  <div class="page-body">
    <div class="el-page">
      <x-hero tag="Recherche" title="Recherche dans les dossiers" subtitle="Un seul champ : numéro d'affaire ou du parquet, nom d'une partie, objet, article, numéro de décision, titre d'une pièce. Tous les mots saisis doivent se retrouver. Vous ne trouvez que ce que vos droits vous permettent de voir." icon="fa-solid fa-magnifying-glass" />

      <div class="el-card" style="padding:16px">
        <form method="get" action="{{ route('recherche') }}" class="rch-barre" role="search">
          <input type="search" name="q" value="{{ $q }}" placeholder="Ex. : KONE loyers, 1334/26, divorce, vol aggravé…" autocomplete="off" autofocus maxlength="120" aria-label="Rechercher">
          <button class="el-btn el-btn--accent" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
        </form>
      </div>

      @if ($trop_court)
        <div class="st-carte st-vide" style="margin-top:14px">Saisissez au moins un mot de deux lettres.</div>
      @elseif ($q !== '' && count($rubriques) === 0)
        <div class="st-carte st-vide" style="margin-top:14px"><i class="fa-solid fa-face-meh" style="font-size:34px;color:#f08a24"></i><p>Aucun résultat pour « {{ $q }} ».<br>Essayez moins de mots, ou une partie du nom.</p></div>
      @elseif ($q === '')
        <div class="st-carte st-vide" style="margin-top:14px"><i class="fa-solid fa-lightbulb" style="font-size:30px;color:#f08a24"></i><p>Astuce : « KONE loyers » trouve les affaires où figurent à la fois « KONE » et « loyers », même dans deux champs différents.</p></div>
      @else
        <p class="st-note" style="margin:12px 2px">{{ collect($rubriques)->sum('total') }} résultat(s) pour « {{ $q }} »</p>
        @foreach ($rubriques as $r)
          <div class="rch-carte">
            <div class="rch-tete"><i class="fa-solid {{ $r['icone'] }}"></i> {{ $r['titre'] }}<small>{{ $r['total'] }}</small></div>
            @foreach ($r['items'] as $i)
              <a class="rch-item" href="{{ $i['url'] }}"><b>{{ $surligner($i['titre']) }}</b><span>{{ $surligner($i['sous']) }}</span></a>
            @endforeach
            @if ($r['total'] > count($r['items']))<div class="rch-item"><span>… et {{ $r['total'] - count($r['items']) }} autre(s) : précisez la recherche.</span></div>@endif
          </div>
        @endforeach
      @endif
    </div>
  </div>
@endsection
