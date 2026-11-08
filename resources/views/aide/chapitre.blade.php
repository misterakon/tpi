@extends('layout.master')
@section('title', $chapitre['titre'].' · Aide')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  @php $faq = $chapitre['type'] === 'faq'; @endphp
  <div class="aide-progres" id="aide-progres"></div>
  <div class="page-body">
    <div class="el-page aide aide-c-{{ $chapitre['couleur'] }}">

      <x-hero tag="Manuel d'utilisation" :title="$chapitre['titre']" :subtitle="$chapitre['resume']" :icon="$chapitre['icone']">
        <x-slot:extra>
          <span><i class="fa-regular fa-clock"></i> {{ $chapitre['minutes'] }} min de lecture</span>
          <span><i class="fa-solid fa-list-ol"></i> {{ count($chapitre['sections']) }} section{{ count($chapitre['sections']) > 1 ? 's' : '' }}</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn" href="{{ route('aide.index') }}"><i class="fa-solid fa-arrow-left"></i> Sommaire</a>
          <button class="el-btn el-btn--accent" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
        </x-slot:actions>
      </x-hero>

      <div class="aide-page">
        <nav class="aide-toc" aria-label="Dans ce chapitre">
          <div class="aide-toc__tete"><span class="aide-carte__ico"><i class="{{ $chapitre['icone'] }}"></i></span><div><b>{{ $chapitre['titre'] }}</b><small>Dans ce chapitre</small></div></div>
          @foreach ($chapitre['sections'] as $s)
            <a class="lien" href="#{{ $s['ancre'] }}" data-vers="{{ $s['ancre'] }}"><span>{{ $loop->iteration }}</span>{{ $s['titre'] }}</a>
          @endforeach
          <div class="aide-toc__outils">
            <a class="aide-btn aide-btn--plein" href="{{ route('aide.index') }}"><i class="fa-solid fa-book-open"></i> Tous les chapitres</a>
            <button type="button" class="aide-btn aide-btn--orange" data-aide-ouvrir><i class="fa-solid fa-magnifying-glass"></i> Chercher (F1)</button>
          </div>
        </nav>

        <div class="aide-corps">
          @if ($chapitre['intro_html'])<div class="aide-intro aide-texte">{!! $chapitre['intro_html'] !!}</div>@endif
          @foreach ($chapitre['sections'] as $s)
            @include('aide._section', ['s' => $s, 'i' => $loop->index, 'faq' => $faq, 'c' => $chapitre, 'ouvrirPremiere' => true])
          @endforeach

          <div class="aide-nav">
            @if ($precedent)<a href="{{ route('aide.chapitre', $precedent['slug']) }}"><small><i class="fa-solid fa-arrow-left"></i> Chapitre précédent</small><b>{{ $precedent['titre'] }}</b></a>@else<span></span>@endif
            @if ($suivant)<a class="suiv" href="{{ route('aide.chapitre', $suivant['slug']) }}"><small>Chapitre suivant <i class="fa-solid fa-arrow-right"></i></small><b>{{ $suivant['titre'] }}</b></a>@endif
          </div>
        </div>
      </div>

    </div>
  </div>
@endsection

@section('script')
  <script>
  (function () {
    'use strict';
    // Barre de lecture
    const barre = document.getElementById('aide-progres');
    function lecture() { const h = document.documentElement; const max = h.scrollHeight - h.clientHeight; barre.style.width = (max > 0 ? Math.min(100, h.scrollTop / max * 100) : 0) + '%'; }
    window.addEventListener('scroll', lecture, { passive: true }); lecture();
    // Sommaire : la section lue est surlignée
    const liens = [...document.querySelectorAll('.aide-toc a.lien')];
    const cibles = liens.map(a => document.getElementById(a.dataset.vers)).filter(Boolean);
    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver(es => { es.forEach(e => { if (e.isIntersecting) { liens.forEach(l => l.classList.toggle('is-actif', l.dataset.vers === e.target.id)); } }); }, { rootMargin: '-90px 0px -65% 0px' });
      cibles.forEach(c => obs.observe(c));
    }
    liens.forEach(a => a.addEventListener('click', e => {
      e.preventDefault();
      const c = document.getElementById(a.dataset.vers);
      if (c) { c.scrollIntoView({ behavior: 'smooth', block: 'start' }); history.replaceState(null, '', '#' + a.dataset.vers); if (c.tagName === 'DETAILS') { c.open = true; } }
    }));
    // Lien direct vers une question dépliable
    if (location.hash) { const c = document.getElementById(location.hash.slice(1)); if (c && c.tagName === 'DETAILS') { c.open = true; } }
    // Copier le lien d'une section
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-copier]');
      if (!b) { return; }
      const url = location.origin + location.pathname + '#' + b.dataset.copier;
      (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(() => { b.innerHTML = '<i class="fa-solid fa-check" style="color:#059669"></i>'; setTimeout(() => { b.innerHTML = '<i class="fa-solid fa-link"></i>'; }, 1400); }).catch(() => {});
    });
  })();
  </script>
@endsection
