{{-- Suivi du cycle d'une décision : la frise (rédaction, rédigée, validée, signée, numérotée, délivrée), le numéro, et les remises. Variables : $decision, $delivrances, $momentNumero --}}
@php $etapes = $decision->etapes(); $courante = collect($etapes)->where('fait', true)->count(); @endphp
<div class="el-card">
  <div class="el-card__head"><div><h2 class="el-card__title">Suivi de la décision</h2><div class="el-card__sub">
    @if ($decision->numeroAffichage())
      Numéro <b>{{ $decision->numeroAffichage() }}</b>@if ($decision->date_decision), décision du {{ $decision->date_decision->format('d/m/Y') }}@endif
    @else
      {{ match ($momentNumero) { 'redaction' => 'Le greffier de la chambre donne le numéro quand le juge a terminé sa rédaction.', 'validation' => 'Le numéro (D-N/année) est généré automatiquement quand le juge valide la décision.', default => 'Le numéro (D-N/année) est attribué automatiquement à la signature de la décision.' } }}
    @endif
  </div></div></div>
  <div class="el-card__body">
    <ol class="red-frise">
      @foreach ($etapes as $i => $e)
        <li class="{{ $e['fait'] ? 'is-fait' : '' }} {{ ! $e['fait'] && $i === $courante ? 'is-suivant' : '' }}">
          <span class="red-frise__pt">@if ($e['fait'])<i class="fa-solid fa-check"></i>@else{{ $i + 1 }}@endif</span>
          <span class="red-frise__lib">{{ $e['libelle'] }}</span>
          <small>{{ $e['fait'] ? ($e['date'] ?: '') : '' }}</small>
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
