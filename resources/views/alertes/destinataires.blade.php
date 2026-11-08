@extends('layout.master')
@section('title', 'Destinataires des alertes')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  @php
      $sansCompte = collect($controle['chambres'])->filter(fn ($c) => collect($c['greffiers'])->where('compte', true)->isEmpty() || collect($c['presidents'])->where('compte', true)->isEmpty())->count();
  @endphp
  <div class="page-body">
    <div class="el-page au-page al-page">

      <x-hero tag="Alertes" title="Destinataires des alertes" subtitle="Qui recevrait les alertes aujourd'hui, chambre par chambre, d'après l'ordonnance. Une personne sans compte ne reçoit rien : reliez son compte à sa fiche d'agent dans Utilisateurs. En attendant, ses alertes sont transmises aux administrateurs." icon="fa-solid fa-users-viewfinder">
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('alertes.regles') }}"><i class="fa-solid fa-sliders"></i> Règles d'alerte</a>
        </x-slot:actions>
      </x-hero>
      {{-- Qui recevrait quoi : une personne sans compte ne reçoit rien. --}}
      <div class="au-cadre">
        <div class="al-controle__tete">
          <div><b>Contrôle des destinataires</b><small>Qui recevrait les alertes aujourd'hui, d'après l'ordonnance. Une personne <b>sans compte</b> ne reçoit rien : reliez son compte à sa fiche d'agent (Utilisateurs).</small></div>
          @if ($sansCompte > 0)<span class="au-badge au-badge--orange"><i class="fa-solid fa-triangle-exclamation"></i>{{ $sansCompte }} chambre{{ $sansCompte > 1 ? 's' : '' }} à compléter</span>@else<span class="au-badge au-badge--vert"><i class="fa-solid fa-circle-check"></i>Tout est relié</span>@endif
        </div>
        <div class="au-fond">
          <div class="au-tbl-carte">
            <table class="au-tbl al-tbl">
              <colgroup><col style="width:26%"><col><col></colgroup>
              <thead><tr><th>Chambre / fonction</th><th>Greffier(s)</th><th>Président de la formation</th></tr></thead>
              <tbody>
                @foreach ($controle['chambres'] as $c)
                  <tr>
                    <td class="au-tbl__num">{{ $c['chambre'] }}</td>
                    @foreach (['greffiers', 'presidents'] as $role)
                      <td>
                        @forelse ($c[$role] as $a)<span class="al-agent {{ $a['compte'] ? 'is-ok' : 'is-ko' }}"><i class="fa-solid {{ $a['compte'] ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>{{ $a['nom'] }}<em>{{ $a['compte'] ? 'avec compte' : 'sans compte' }}</em></span>@empty<span class="al-agent is-ko"><i class="fa-solid fa-circle-xmark"></i>Personne d'affecté</span>@endforelse
                      </td>
                    @endforeach
                  </tr>
                @endforeach
                @foreach ($controle['fonctions'] as $f)
                  <tr>
                    <td class="au-tbl__num">{{ $f['fonction'] }}</td>
                    <td colspan="2">
                      @forelse ($f['agents'] as $a)<span class="al-agent {{ $a['compte'] ? 'is-ok' : 'is-ko' }}"><i class="fa-solid {{ $a['compte'] ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>{{ $a['nom'] }}<em>{{ $a['compte'] ? 'avec compte' : 'sans compte' }}</em></span>@empty<span class="al-agent is-ko"><i class="fa-solid fa-circle-xmark"></i>Aucun agent avec cette fonction</span>@endforelse
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
@endsection
