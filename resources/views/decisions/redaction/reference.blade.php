{{-- Référence de l'affaire : celle du registre quand la décision y est rattachée (non modifiable), sinon une référence saisie. Variables : $decision, $lecture --}}
<label class="form-label" for="f_reference"><b>Référence de l'affaire</b></label>
<input class="form-control" id="f_reference" name="reference" type="text" maxlength="120" value="{{ old('reference', $decision->reference) }}" required @disabled($lecture) @readonly((bool) $decision->affaire_id)>
@if ($decision->affaire_id)
  <small class="text-muted">
    Rattachée à l'affaire du registre
    @can('affaires.voir')
      · <a href="{{ route('affaires.liste.fiche', $decision->affaire_id) }}" target="_blank" rel="noopener"><b>ouvrir la fiche de l'affaire</b> <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
    @endcan
  </small>
@endif
