@extends('layout.master')
@section('title', "Manuel d'utilisation complet")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page aide">
      <x-hero tag="Manuel d'utilisation" title="Manuel complet" subtitle="Tous vos chapitres sur une seule page. Utilisez « Imprimer » puis « Enregistrer au format PDF » pour garder une copie." icon="fa-solid fa-book">
        <x-slot:actions>
          <a class="el-btn" href="{{ route('aide.index') }}"><i class="fa-solid fa-arrow-left"></i> Sommaire</a>
          <button class="el-btn el-btn--accent" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer / PDF</button>
        </x-slot:actions>
      </x-hero>

      <section class="aide-section aide-c-vert">
        <h2><span class="num"><i class="fa-solid fa-list"></i></span><span>Sommaire</span></h2>
        <div class="aide-texte"><ol>@foreach ($chapitres as $c)<li><p><b>{{ $c['titre'] }}</b> — {{ $c['resume'] }}</p></li>@endforeach</ol></div>
      </section>

      @foreach ($chapitres as $c)
        <div class="aide-manuel-chap aide-c-{{ $c['couleur'] }}" style="display:flex; flex-direction:column; gap:16px">
          <section class="aide-intro">
            <h2 style="margin:0 0 8px !important; display:flex; gap:12px; align-items:center"><span class="aide-carte__ico" style="width:44px;height:44px"><i class="{{ $c['icone'] }}"></i></span>{{ $loop->iteration }}. {{ $c['titre'] }}</h2>
            <div class="aide-texte">{!! $c['intro_html'] ?: e($c['resume']) !!}</div>
          </section>
          @foreach ($c['sections'] as $s)
            @include('aide._section', ['s' => $s, 'i' => $loop->index, 'faq' => $c['type'] === 'faq', 'c' => $c, 'ouvrirPremiere' => false])
          @endforeach
        </div>
      @endforeach
    </div>
  </div>
@endsection

@section('script')
  <script>document.querySelectorAll('details.aide-faq').forEach(function (d) { d.open = true; });</script>
@endsection
