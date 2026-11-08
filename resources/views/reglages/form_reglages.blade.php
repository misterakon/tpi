{{-- Formulaire des réglages d'UN module. Variables : $module, $m. Le JS est dans js_reglages. --}}
@php $retour = old('module') === $module; /* formulaire réaffiché après une erreur : on reprend la saisie */ @endphp

<form method="post" action="{{ route('reglages.store') }}">
  @csrf
  <input type="hidden" name="module" value="{{ $module }}">

  @foreach ($m['reglages'] as $reglage => $d)
    @php
      $cle = "{$module}.{$reglage}";
      $champ = "valeurs.{$reglage}";
      $id = "rg_{$module}_{$reglage}";
      $valeur = $retour
          ? ($d['type'] === 'bool' ? (bool) old($champ) : old($champ, $valeurs[$cle]))
          : $valeurs[$cle];
    @endphp

    <div class="rg-ligne {{ $modifies[$cle] ? 'rg-ligne--modifie' : '' }}" data-recherche="{{ mb_strtolower($d['libelle'].' '.($d['aide'] ?? '').' '.$m['libelle']) }}">
      <div class="rg-gauche">
        <p class="rg-libelle">{{ $d['libelle'] }}@if ($modifies[$cle]) <span class="rg-tag">Modifié</span>@endif</p>
        @if (! empty($d['aide'])) <p class="rg-aide">{{ $d['aide'] }}</p> @endif
        <p class="rg-defaut"><i class="fa-solid fa-circle-info"></i> Par défaut : {{ \App\Support\Reglages::afficher($d, $d['defaut']) }}</p>
        @error($champ) <p class="rg-erreur">{{ $message }}</p> @enderror
      </div>

      <div class="rg-droite">
        @switch($d['type'])
          @case('bool')
            <label class="rg-switch" for="{{ $id }}" title="{{ $d['libelle'] }}">
              <input type="checkbox" id="{{ $id }}" name="valeurs[{{ $reglage }}]" value="1" role="switch" aria-label="{{ $d['libelle'] }}" @checked($valeur)>
              <span class="rg-slider"></span>
              <span class="rg-etat"></span>
            </label>
            @break

          @case('int')
            <div class="rg-nombre">
              <input class="form-control form-control-sm" type="number" id="{{ $id }}" name="valeurs[{{ $reglage }}]" value="{{ $valeur }}" min="{{ $d['min'] }}" max="{{ $d['max'] }}" required>
              @isset($d['unite']) <span>{{ $d['unite'] }}</span> @endisset
            </div>
            <small class="rg-defaut">Entre {{ number_format($d['min'], 0, ',', ' ') }} et {{ number_format($d['max'], 0, ',', ' ') }}</small>
            @break

          @case('float')
            <div class="rg-nombre">
              <input class="form-control form-control-sm" type="number" id="{{ $id }}" name="valeurs[{{ $reglage }}]" value="{{ $valeur }}" min="{{ $d['min'] }}" max="{{ $d['max'] }}" step="{{ $d['step'] ?? '0.1' }}" required>
              @isset($d['unite']) <span>{{ $d['unite'] }}</span> @endisset
            </div>
            <small class="rg-defaut">Entre {{ $d['min'] }} et {{ $d['max'] }}</small>
            @break

          @case('texte')
            <input class="form-control form-control-sm" type="text" id="{{ $id }}" name="valeurs[{{ $reglage }}]" value="{{ $valeur }}" maxlength="{{ $d['max'] ?? 255 }}" required>
            @break

          @case('choix')
            <select class="form-select form-select-sm" id="{{ $id }}" name="valeurs[{{ $reglage }}]">
              @foreach ($d['options'] as $option => $libelle) <option value="{{ $option }}" @selected((string) $valeur === (string) $option)>{{ $libelle }}</option> @endforeach
            </select>
            @break
        @endswitch

        @if ($modifies[$cle])
          <button class="rg-retablir" type="button" onclick="reglage_retablir('{{ $cle }}', @js($d['libelle']))"><i class="fa-solid fa-rotate-left"></i> Rétablir la valeur par défaut</button>
        @endif
      </div>
    </div>
  @endforeach

  <div class="rg-pied">
    <button class="btn btn-primary text-white" type="submit"><i class="fa-solid fa-floppy-disk"></i> Enregistrer « {{ $m['libelle'] }} »</button>
  </div>
</form>
