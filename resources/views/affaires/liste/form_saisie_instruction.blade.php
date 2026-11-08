{{-- Saisie d'une affaire : instruction. Variables : $manuel_instruction, $vocab, $modes_instruction, $natures_instruction, $matieres, $chambres --}}
@php
  $def = \App\Support\RegistreAffaire::depuis('instruction');
  $actif = old('registre') === 'instruction';
  $anciennes = $actif ? old('parties') : null;
  $parties = is_array($anciennes) ? array_values($anciennes) : [['role' => $def['principal'], 'nature' => 'homme']];
@endphp
<div class="modal fade panel-modal" id="modal-saisie-instruction" tabindex="-1" role="dialog" aria-labelledby="titre-modal-saisie-instruction" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal-large">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-saisie-instruction">Saisie au cabinet d'instruction</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form id="form-saisie-instruction" class="aff-form-poursuite" data-registre="instruction" method="post" action="{{ route('affaires.enrolement.store') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="registre" value="instruction">
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>{{ $manuel_instruction ? "Reportez ici les informations de l'affaire telles qu'elles figurent au greffe : le numéro est celui du greffe, saisi à la main (une alerte signale les doublons ; un même numéro n'est admis que pour une jonction)." : "Le numéro est attribué par l'application à l'enregistrement." }}</span></div>
          @include('affaires.enrolement.formulaire_poursuite', ['def' => $def, 'pre' => 'i', 'defauts' => [], 'parties' => $parties, 'modification' => false, 'manuel' => $manuel_instruction, 'modes' => $modes_instruction, 'natures' => $natures_instruction, 'nationalites' => config('nationalites')])
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit"><i class="fa-solid fa-file-circle-check"></i> Enregistrer l'affaire</button>
        </div>
      </form>
    </div>
  </div>
</div>
