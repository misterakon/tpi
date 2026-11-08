{{-- Délivrer la décision : expédition, grosse ou copie, à qui et quand. Variable : $decision --}}
<div class="modal fade panel-modal" id="modal-delivrance" tabindex="-1" role="dialog" aria-labelledby="titre-modal-delivrance" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:600px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-delivrance">Délivrer la décision</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form method="post" action="{{ route('decisions.redaction.delivrer', $decision->id) }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c6">
              <label class="form-label" for="f_deliv_type"><b>Type de remise</b></label>
              <select class="form-select" id="f_deliv_type" name="type" data-sans-recherche required>
                @foreach (\App\Models\Decision::TYPES_DELIVRANCE as $cle => $libelle)<option value="{{ $cle }}">{{ $libelle }}</option>@endforeach
              </select>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_deliv_le"><b>Date de remise</b></label>
              <input class="form-control" id="f_deliv_le" name="delivre_le" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required>
            </div>
            <div class="el-c8">
              <label class="form-label" for="f_deliv_a"><b>Remise à</b></label>
              <input class="form-control" id="f_deliv_a" name="delivre_a" type="text" maxlength="160" placeholder="Nom de la personne ou de l'avocat" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_deliv_qualite"><b>Qualité</b></label>
              <input class="form-control" id="f_deliv_qualite" name="qualite" type="text" maxlength="60" placeholder="Partie, avocat…">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_deliv_obs"><b>Observation</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_deliv_obs" name="observation" type="text" maxlength="300">
              <small class="text-muted">Une grosse (copie exécutoire) ne se délivre qu'une fois à la même personne ; une expédition ou une copie peut l'être plusieurs fois.</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Enregistrer la remise</button>
        </div>
      </form>
    </div>
  </div>
</div>
