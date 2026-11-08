{{-- Saisie d'une affaire (fenêtre de la liste). Variables : $vocab, $manuel, $modes, $natures, $matieres, $chambres --}}
@php
  $anciennes = old('registre', 'civil') === 'civil' ? old('parties') : null;
  $parties = is_array($anciennes) ? array_values($anciennes) : [['role' => 'demandeur', 'nature' => 'homme'], ['role' => 'defendeur', 'nature' => 'homme']];
@endphp
<div class="modal fade panel-modal" id="modal-saisie" tabindex="-1" role="dialog" aria-labelledby="titre-modal-saisie" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal-large">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-saisie">{{ $vocab['titre'] }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form id="form-enrolement" method="post" action="{{ route('affaires.enrolement.store') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="registre" value="civil">
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>{{ $vocab['sous_titre'] }}</span></div>
          @include('affaires.enrolement.doublons', ['doublons' => session('doublons', [])])
          @include('affaires.enrolement.formulaire', ['defauts' => [], 'parties' => $parties, 'modification' => false, 'manuel' => $manuel, 'nationalites' => config('nationalites')])
        </div>
        <div class="modal-footer">
          @if (config('app.debug'))
            {{-- Outil d'essai (visible seulement en mode débogage) : remplit le formulaire avec un exemple fictif pour voir la chambre proposée --}}
            <button class="btn btn-outline-secondary aff-test" type="button" onclick="remplir_exemple_civil()" title="Remplit le formulaire avec un exemple fictif (outil d'essai)"><i class="fa-solid fa-wand-magic-sparkles"></i> Remplir un exemple</button>
          @endif
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit"><i class="fa-solid fa-file-circle-check"></i> {{ $vocab['bouton'] }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
