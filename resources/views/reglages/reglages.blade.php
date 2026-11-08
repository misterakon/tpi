@extends('layout.master')
@section('title', 'Réglages')

@section('css')
  {{-- Copiez public/assets/css/reglages.css depuis e-Tache --}}
  <link rel="stylesheet" href="{{ asset('assets/css/reglages.css') }}">
@endsection

@section('main-content')
  @php
    $icones = ['appareils' => 'fa-mobile-screen', 'acces' => 'fa-lock', 'securite' => 'fa-shield-halved'];
    $teintes = ['appareils' => '#2E7D5B', 'acces' => '#C77D0A', 'securite' => '#7B6BE0'];
    $totalModifies = collect($modifies)->filter()->count();
  @endphp

  <div class="page-body">
    <div class="page-title">
      <div class="grid grid-cols-12 mx-2 items-center">
        <div class="col-span-6 sm:col-span-12"><h3>Réglages</h3></div>
        <div class="col-span-6 sm:col-span-12">
          <ol class="breadcrumb flex">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><svg class="stroke-icon"><use href="{{ asset('assets/svg/icon-sprite.svg#stroke-home') }}"></use></svg></a></li>
            <li class="breadcrumb-item">Paramètres</li>
            <li class="breadcrumb-item active">Réglages</li>
          </ol>
        </div>
      </div>
    </div>

    <div class="container">

      {{-- Bandeau : explication, recherche, compteur --}}
      <div class="card rg-entete">
        <div class="rg-entete__texte">
          <p class="rg-entete__titre"><i class="fa-solid fa-sliders"></i> Le fonctionnement de l'application se règle ici</p>
          <p class="rg-entete__aide">Chaque réglage affiche sa valeur par défaut. Toute modification est inscrite dans le journal d'audit. Le montant de l'application et la répartition entre parties prenantes ont leur propre page (versionnée), qui arrive au lot suivant.</p>
        </div>
        <div class="rg-entete__outils">
          <div class="rg-recherche">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="rg-recherche" placeholder="Rechercher un réglage…" autocomplete="off">
          </div>
          <span class="rg-compteur {{ $totalModifies ? 'rg-compteur--actif' : '' }}">
            {{ $totalModifies ? $totalModifies.' réglage(s) modifié(s)' : 'Valeurs par défaut' }}
          </span>
        </div>
      </div>

      <p id="rg-aucun" class="rg-aucun" style="display:none;">Aucun réglage ne correspond à votre recherche.</p>

      @foreach ($catalogue as $module => $m)
        @php
          $nbModifies = collect($m['reglages'])->keys()->filter(fn ($k) => $modifies["{$module}.{$k}"])->count();
          $couleur = $teintes[$module] ?? '#2B6CB0';
        @endphp
        <details class="card rg-module" style="--rg-c: {{ $couleur }}" @if (request('module') === $module || old('module') === $module) open @endif>
          <summary>
            <span class="rg-icone"><i class="fa-solid {{ $icones[$module] ?? 'fa-gear' }}"></i></span>
            <span class="rg-resume">
              <span class="rg-titre">{{ $m['libelle'] }}</span>
              <span class="rg-desc">{{ $m['description'] }}</span>
            </span>
            <span class="rg-meta">
              <span class="rg-nb"><b>{{ count($m['reglages']) }} réglage(s)</b></span>
              @if ($nbModifies) <span class="rg-badge-modif">{{ $nbModifies }} modifié(s)</span> @endif
            </span>
            <i class="fa-solid fa-chevron-down rg-chevron"></i>
          </summary>
          <div class="rg-corps">
            @include('reglages.form_reglages', ['module' => $module, 'm' => $m])
          </div>
        </details>
      @endforeach

      {{-- Historique des modifications --}}
      <div class="card rg-historique">
        <div class="rg-historique__titre"><i class="fa-solid fa-clock-rotate-left"></i> Dernières modifications de réglages</div>
        <div class="overflow-x-auto">
          <table class="table table-striped" style="width:100%">
            <thead><tr><th>Date</th><th>Acteur</th><th>Modification</th></tr></thead>
            <tbody>
              @forelse ($historique as $e)
                <tr>
                  <td style="white-space:nowrap;">{{ $e->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $e->user?->name ?? '—' }}</td>
                  <td>{{ $e->description }}</td>
                </tr>
              @empty
                <tr><td colspan="3" style="text-align:center;padding:20px;">Aucune modification : tous les réglages ont leur valeur par défaut.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Formulaire caché utilisé par les boutons « Rétablir » --}}
    <form id="form_retablir" method="post" action="{{ route('reglages.retablir') }}" style="display:none;">
      @csrf
      <input type="hidden" name="cle" value="">
    </form>

    @include('reglages.js_reglages')

  </div>
@endsection
