{{-- Suivi du cycle d'une décision : la frise (rédaction, rédigée, validée, signée, numérotée, transmise au greffe), le numéro, et l'historique des remises faites avant la suppression de l'étape de délivrance. Variables : $decision, $delivrances, $momentNumero --}}
@php
  $etapes = $decision->etapes(); $courante = collect($etapes)->where('fait', true)->count();
  // La durée de chaque étape : le temps écoulé depuis l'étape faite juste avant.
  $brut = ['brouillon' => $decision->created_at, 'redigee' => $decision->redigee_le, 'numerotee' => $decision->numero_le, 'validee' => $decision->validee_le, 'signee' => $decision->date_signature, 'transmise' => $decision->transmise_le, 'delivree' => $decision->delivree_le];
  $precedent = null; $durees = [];
  foreach ($etapes as $e) { $d = $e['fait'] ? ($brut[$e['code']] ?? null) : null; if ($d) { $durees[$e['code']] = $precedent ? \App\Support\Durees::entre($precedent, $d) : null; $precedent = $d; } }
@endphp
<div class="el-card">
  <div class="el-card__head"><div><h2 class="el-card__title">Suivi de la décision</h2><div class="el-card__sub">
    @if ($decision->numeroAffichage())
      Numéro <b>{{ $decision->numeroAffichage() }}</b>@if ($decision->date_decision), décision du {{ $decision->date_decision->format('d/m/Y') }}@endif
    @else
      {{ match ($momentNumero) { 'redaction' => 'Le greffier de la chambre donne le numéro quand le juge a terminé sa rédaction.', 'validation' => 'Le numéro (par exemple 12/26) est généré automatiquement quand le juge valide la décision.', default => 'Le numéro (D-N/année) est attribué automatiquement à la signature de la décision.' } }}
    @endif
  </div></div></div>
  <div class="el-card__body">
    <ol class="red-frise">
      @foreach ($etapes as $i => $e)
        <li class="{{ $e['fait'] ? 'is-fait' : '' }} {{ ! $e['fait'] && $i === $courante ? 'is-suivant' : '' }}">
          <span class="red-frise__pt">@if ($e['fait'])<i class="fa-solid fa-check"></i>@else{{ $i + 1 }}@endif</span>
          <span class="red-frise__lib">{{ $e['libelle'] }}</span>
          <small>{{ $e['fait'] ? ($e['date'] ?: '') : '' }}</small>
          @if (isset($durees[$e['code']]) && $durees[$e['code']] !== null)<small class="red-frise__duree" title="Depuis l'étape précédente" style="color:#00794C; font-weight:600">{{ \App\Support\Durees::court($durees[$e['code']]) }}</small>@endif
        </li>
      @endforeach
    </ol>

    @if ($delivrances->isNotEmpty())
      <div class="red-remises">
        <div class="red-remises__titre"><i class="fa-solid fa-paper-plane"></i> Remises ({{ $delivrances->count() }})</div>
        @foreach ($delivrances as $d)
          <div class="red-remise">
            <b>{{ $d->libelleType() }}</b> à {{ $d->delivre_a }}@if ($d->qualite) ({{ $d->qualite }})@endif
            <small>le {{ $d->delivre_le->format('d/m/Y') }}@if ($d->auteur) · par {{ $d->auteur->name }}@endif @if ($d->observation) · {{ $d->observation }}@endif</small>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
