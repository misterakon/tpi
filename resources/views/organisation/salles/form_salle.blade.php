<div class="modal fade panel-modal" id="modal-salle" tabindex="-1" role="dialog" aria-labelledby="titre-modal-salle" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:660px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-salle">salle</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.salles.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c7">
              <label class="form-label" for="f_nom"><b>Nom</b></label>
              <input class="form-control" id="f_nom" name="nom" type="text" maxlength="100" placeholder="Ex. : Salle n°4" required>
            </div>
            <div class="el-c5">
              <label class="form-label" for="f_type"><b>Lieu</b></label>
              <select class="form-select" id="f_type" name="type" required>
                <option value="">Choisir…</option>
                @foreach (\App\Models\Salle::TYPES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c7">
              <label class="form-label" for="f_emplacement"><b>Emplacement</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_emplacement" name="emplacement" type="text" maxlength="150" placeholder="Ex. : 1er étage">
            </div>
            <div class="el-c5">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Utilisable</span>
              </label>
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
