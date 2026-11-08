@extends('layout.master')
@section('title', $o->libelle())

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Archive des ordonnances" :title="$o->libelle()" :subtitle="$o->objet" icon="fa-solid fa-file-signature">
        <x-slot:extra>
          <span><i class="fa-solid fa-tag"></i> {{ \App\Models\Ordonnance::TYPES[$o->type] ?? $o->type }}</span>
          <span><i class="fa-regular fa-calendar"></i> Année {{ $o->annee?->libelle }}</span>
          <span><i class="fa-solid fa-signature"></i> Signée le {{ $o->date_signature->format('d/m/Y') }}</span>
          @if ($o->date_effet)<span><i class="fa-regular fa-calendar-check"></i> Effet le {{ $o->date_effet->format('d/m/Y') }}</span>@endif
          <span><i class="fa-regular fa-file-pdf"></i> {{ $o->tailleLisible() }}</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('organisation.ordonnances') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
          <button class="el-btn el-btn--ghost" type="button" onclick="imprimer_ordonnance()"><i class="fa-solid fa-print"></i> Imprimer</button>
          <a class="el-btn el-btn--accent" href="{{ route('organisation.ordonnances.telecharger', $o->id) }}"><i class="fa-solid fa-download"></i> Télécharger</a>
        </x-slot:actions>
      </x-hero>

      @if ($o->observations)
        <div class="el-card">
          <div class="el-card__body" style="padding-top:18px">
            <b>Observations</b>
            <div class="el-riche" style="margin-top:6px">{!! \App\Support\TexteRiche::html($o->observations) !!}</div>
          </div>
        </div>
      @endif

      <div class="el-card" style="overflow:hidden">
        <iframe id="lecteur-pdf" title="{{ $o->libelle() }}" src="{{ route('organisation.ordonnances.lire', $o->id) }}#view=FitH" style="display:block; width:100%; height:max(640px, calc(100vh - 230px)); border:0; background:#F4F6F9"></iframe>
      </div>

      <p class="el-muted" style="font-size:13px">Si le document ne s'affiche pas dans votre navigateur, utilisez « Télécharger ». Archivé le {{ $o->created_at->format('d/m/Y') }}@if ($o->deposePar) par {{ $o->deposePar->name }}@endif.</p>

    </div>
  </div>

  @include('organisation.ordonnances.js_script_lecture')
@endsection
