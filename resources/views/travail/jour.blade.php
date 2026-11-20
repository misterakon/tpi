@extends('layout.master')
@section('title', 'Mon travail du jour')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/travail-du-jour.css') }}?v={{ filemtime(public_path('assets/css/travail-du-jour.css')) }}">
@endsection

@section('main-content')
  @php
    $moi = auth()->user();
    $prenom = trim(explode(' ', (string) $moi->name)[0] ?? '');
    $heure = (int) now()->format('G');
    $salut = $heure < 12 ? 'Bonjour' : ($heure < 18 ? 'Bon après-midi' : 'Bonsoir');
    $parGroupe = collect($t['taches'])->groupBy('groupe');
    $sous = $t['total'] > 0
        ? "Voici ce qui vous attend aujourd'hui, de la plus urgente à la moins urgente. Chaque carte mène à l'écran où agir."
        : 'Rien ne vous attend pour le moment. Bonne journée !';
  @endphp
  <div class="page-body tj-page">
    <div class="el-page">

      <x-hero :tag="ucfirst($t['jour'])" :title="$salut.($prenom ? ', '.$prenom : '')" :subtitle="$sous" icon="fa-solid fa-list-check">
        <x-slot:extra>
          <span><i class="fa-solid fa-list-check"></i> <b>{{ $t['total'] }}</b> rubrique{{ $t['total'] > 1 ? 's' : '' }} à traiter</span>
          <span><i class="fa-solid fa-layer-group"></i> <b>{{ $t['elements'] }}</b> élément{{ $t['elements'] > 1 ? 's' : '' }} en tout</span>
          @if ($t['audiences'])<span><i class="fa-solid fa-gavel"></i> <b>{{ count($t['audiences']) }}</b> audience(s) aujourd'hui</span>@endif
          <span><i class="fa-solid fa-user"></i> {{ $moi->profil?->nom }}</span>
        </x-slot:extra>
      </x-hero>

      {{-- Aujourd'hui : les audiences du jour --}}
      @if ($t['audiences'])
        <section class="tj-section">
          <h2 class="tj-titre"><span class="tj-titre__ic"><i class="fa-solid fa-gavel"></i></span> Aujourd'hui à l'audience</h2>
          <div class="tj-audiences">
            @foreach ($t['audiences'] as $a)
              <div class="tj-audience">
                <div class="tj-audience__heure">{{ $a['heure'] ?? '—' }}</div>
                <div class="tj-audience__corps">
                  <b>{{ $a['chambre'] }}</b>
                  <span>{{ $a['type'] }} · {{ $a['affaires'] }} affaire{{ $a['affaires'] > 1 ? 's' : '' }}</span>
                </div>
                <div class="tj-audience__actions">
                  <a class="el-btn el-btn--ghost" href="{{ $a['url'] }}"><i class="fa-solid fa-table-list"></i> Le rôle</a>
                  @if ($a['feuille'])<a class="el-btn el-btn--accent" href="{{ $a['feuille'] }}"><i class="fa-solid fa-gavel"></i> Feuille d'audience</a>@endif
                </div>
              </div>
            @endforeach
          </div>
        </section>
      @endif

      {{-- Les tâches, par thème : les panneaux se rangent côte à côte --}}
      <div class="tj-groupes">
      @forelse ($groupes as $cle => [$libelle, $icone])
        @continue(! $parGroupe->has($cle))
        <section class="tj-section">
          <h2 class="tj-titre"><span class="tj-titre__ic"><i class="fa-solid {{ $icone }}"></i></span> {{ $libelle }}</h2>
          <div class="tj-cartes">
            @foreach ($parGroupe[$cle] as $x)
              <a class="tj-carte tj-carte--{{ $x['ton'] }}" href="{{ $x['url'] }}" title="{{ $x['verbe'] }} : {{ $x['titre'] }}">
                <span class="tj-carte__ic"><i class="fa-solid {{ $x['icone'] }}"></i></span>
                <span class="tj-carte__n">{{ $x['n'] }}</span>
                <span class="tj-carte__corps">
                  <b>{{ $x['titre'] }}</b>
                  <small>{{ $x['aide'] }}</small>
                </span>
                <i class="fa-solid fa-chevron-right tj-carte__fl" aria-hidden="true"></i>
              </a>
            @endforeach
          </div>
        </section>
      @empty
      @endforelse
      </div>

      @if ($t['total'] === 0)
        <div class="tj-vide"><i class="fa-solid fa-circle-check"></i><div><b>Tout est à jour.</b><span>Aucune tâche en attente dans votre périmètre.</span></div></div>
      @endif

      @if ($t['a_jour'])
        <div class="tj-ajour">
          <span class="tj-ajour__t"><i class="fa-solid fa-circle-check"></i> À jour</span>
          @foreach ($t['a_jour'] as $lib)<span class="tj-puce">{{ $lib }}</span>@endforeach
        </div>
      @endif

      {{-- Dernières alertes --}}
      @if ($t['alertes'])
        <section class="tj-section">
          <h2 class="tj-titre"><span class="tj-titre__ic"><i class="fa-solid fa-bell"></i></span> Dernières alertes non lues <a class="tj-lien" href="{{ route('notifications.index') }}">Tout voir <i class="fa-solid fa-arrow-right"></i></a></h2>
          <div class="el-card">
            @foreach ($t['alertes'] as $al)
              <a class="tj-alerte tj-alerte--{{ $al['niveau'] }}" href="{{ $al['ouvrir'] }}">
                <span class="tj-alerte__pt"></span>
                <span class="tj-alerte__titre">{{ $al['titre'] }}</span>
                <span class="tj-alerte__quand">{{ $al['quand'] }}</span>
              </a>
            @endforeach
          </div>
        </section>
      @endif

    </div>
  </div>
@endsection
