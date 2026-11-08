{{-- Correction d'une affaire (fenêtre de la fiche). Variables : $affaire, $correction (listes, def, manuel, defauts, parties) --}}
@php
  $def = $correction['def'];
  $civil = $def['cle'] === 'civil';
  $parties = $correction['parties'];
  // Une correction refusée se rouvre avec ce qui avait été tapé (le formulaire lit les anciennes valeurs de lui-même).
  $apresErreur = $errors->any() && old('registre') === $def['cle'] && is_array(old('parties'));
  if ($apresErreur) { $parties = array_values(old('parties')); }
@endphp
<div class="modal fade panel-modal" id="modal-correction" tabindex="-1" role="dialog" aria-labelledby="titre-modal-correction" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal-large">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-correction">Correction : {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form id="form-correction" @class(['aff-form-poursuite' => ! $civil]) @if (! $civil) data-registre="{{ $def['cle'] }}" @endif method="post" action="{{ route('affaires.liste.modifier.enregistrer', $affaire->id) }}" autocomplete="off">
        @csrf
        <input type="hidden" name="registre" value="{{ $def['cle'] }}">
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>Le numéro et la date du RG ne changent pas. Chaque correction est enregistrée au journal avec l'ancienne et la nouvelle valeur.</span></div>
          @include('affaires.enrolement.doublons', ['doublons' => session('doublons', [])])
          @if ($civil)
            @include('affaires.enrolement.formulaire', [
                'defauts' => $correction['defauts'], 'parties' => $parties, 'modification' => true, 'manuel' => $correction['manuel'], 'nationalites' => config('nationalites'),
                'modes' => $correction['modes'], 'natures' => $correction['natures'], 'matieres' => $correction['matieres'], 'chambres' => $correction['chambres'],
            ])
          @else
            @include('affaires.enrolement.formulaire_poursuite', [
                'pre' => 'm', 'defauts' => $correction['defauts'], 'parties' => $parties, 'modification' => true, 'manuel' => $correction['manuel'], 'nationalites' => config('nationalites'),
                'modes' => $def['cle'] === 'penal' ? $correction['modes_penal'] : $correction['modes_instruction'],
                'natures' => $def['cle'] === 'penal' ? $correction['natures_penal'] : $correction['natures_instruction'],
                'matieres' => $correction['matieres'], 'chambres' => $correction['chambres'],
            ])
          @endif
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les corrections</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script type="text/javascript">

	let correctionModifiee = false;

	/** Ouvre la fenêtre de correction. Si on l'avait fermée avec des changements non enregistrés, on recharge la fiche : valeurs d'origine. */
	function ouvrir_correction() {
		if (correctionModifiee) {
			window.location.hash = 'corriger';
			window.location.reload();
			return;
		}
		$('#modal-correction').modal('show');
	}

	document.addEventListener('DOMContentLoaded', function () {
		$('#form-correction').on('input change', function () { correctionModifiee = true; });
		if (window.location.hash === '#corriger' @if ($apresErreur) || true @endif) {
			history.replaceState(null, '', window.location.pathname + window.location.search);
			correctionModifiee = {{ $apresErreur ? 'true' : 'false' }};
			$('#modal-correction').modal('show');
		}
	});

</script>
