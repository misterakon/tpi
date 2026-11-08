{{-- Saisie d'une affaire : penal. Variables : $manuel_penal, $vocab, $modes_penal, $natures_penal, $matieres, $chambres --}}
@php
  $def = \App\Support\RegistreAffaire::depuis('penal');
  $actif = old('registre') === 'penal';
  $anciennes = $actif ? old('parties') : null;
  $parties = is_array($anciennes) ? array_values($anciennes) : [['role' => $def['principal'], 'nature' => 'homme']];
@endphp
<div class="modal fade panel-modal" id="modal-saisie-penal" tabindex="-1" role="dialog" aria-labelledby="titre-modal-saisie-penal" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal-large">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-saisie-penal">Saisie d'une affaire pénale</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form id="form-saisie-penal" class="aff-form-poursuite" data-registre="penal" method="post" action="{{ route('affaires.enrolement.store') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="registre" value="penal">
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>{{ $manuel_penal ? "Reportez ici les informations de l'affaire telles qu'elles figurent au greffe : le numéro est celui du greffe, saisi à la main (une alerte signale les doublons ; un même numéro n'est admis que pour une jonction)." : "Le numéro est attribué par l'application à l'enregistrement." }}</span></div>
          @include('affaires.enrolement.formulaire_poursuite', ['def' => $def, 'pre' => 'p', 'defauts' => [], 'parties' => $parties, 'modification' => false, 'manuel' => $manuel_penal, 'modes' => $modes_penal, 'natures' => $natures_penal, 'nationalites' => config('nationalites')])
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit"><i class="fa-solid fa-file-circle-check"></i> Enregistrer l'affaire</button>
        </div>
      </form>
    </div>
  </div>
</div>
