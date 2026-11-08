<div class="modal fade panel-modal" id="modal-enrolement" tabindex="-1" role="dialog" aria-labelledby="titre-modal-enrolement" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:50%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-enrolement">Règle d'enrôlement</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.enrolement.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c4">
              <label class="form-label" for="f_jour_audience"><b>Audience le</b></label>
              <select class="form-select" id="f_jour_audience" name="jour_audience" required data-sans-recherche>
                @foreach (\App\Models\Creneau::JOURS as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c5">
              <label class="form-label" for="f_jour_limite"><b>Enrôlement clos le</b></label>
              <select class="form-select" id="f_jour_limite" name="jour_limite" required data-sans-recherche>
                @foreach (\App\Models\Creneau::JOURS as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }} précédent</option>
                @endforeach
              </select>
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_heure"><b>À</b></label>
              <input class="form-control" id="f_heure" name="heure" type="time" value="16:00" required>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_remarque"><b>Remarque</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_remarque" name="remarque" type="text" maxlength="255">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white submit" id="BtnEnregistrer" type="submit">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
