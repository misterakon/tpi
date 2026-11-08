{{-- Numéro de la décision : jamais saisi, attribué par l'application ; affiché grisé, en lecture seule. Variables : $decision, $momentNumero --}}
@php
  $numero = $decision->numeroAffichage();
  $attente = match ($momentNumero) {
    'redaction' => 'Donné par le greffier de la chambre',
    'validation' => 'Généré à la validation',
    default => 'Attribué à la signature',
  };
@endphp
<label class="form-label" for="f_numero_decision"><b>Numéro de la décision</b></label>
<input class="form-control red-numero" id="f_numero_decision" type="text" value="{{ $numero ?: $attente }}" disabled aria-readonly="true">
<small class="text-muted">{{ $numero ? 'Numéro définitif : il suit la décision.' : 'Le numéro (D-N/année) est attribué automatiquement ; il ne se saisit pas.' }}</small>
