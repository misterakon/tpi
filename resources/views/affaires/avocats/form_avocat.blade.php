<div class="modal fade panel-modal" id="modal-avocats" tabindex="-1" role="dialog" aria-labelledby="titre-modal-avocats" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:700px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-avocats">Avocat</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('affaires.avocats.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c4">
              <label class="form-label" for="f_type"><b>Type</b></label>
              <select class="form-select" id="f_type" name="type" required data-sans-recherche>
                @foreach (\App\Models\Avocat::TYPES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c8" id="bloc-nom">
              <label class="form-label" for="f_nom"><b id="lib-nom">Nom</b></label>
              <input class="form-control" id="f_nom" name="nom" type="text" maxlength="160" placeholder="Ex. : YAO" required>
            </div>
            <div class="el-c12" id="bloc-prenoms">
              <label class="form-label" for="f_prenoms"><b>Prénoms</b></label>
              <input class="form-control" id="f_prenoms" name="prenoms" type="text" maxlength="160" placeholder="Ex. : Aminata">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_barreau"><b>N° d'inscription au Barreau</b> <span class="el-muted">(facultatif)</span></label>
              <input class="form-control" id="f_barreau" name="barreau_numero" type="text" maxlength="60">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_cabinet"><b>Cabinet ou société</b> <span class="el-muted">(facultatif)</span></label>
              <input class="form-control" id="f_cabinet" name="cabinet" type="text" maxlength="200">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_telephone"><b>Téléphone</b></label>
              <input class="form-control" id="f_telephone" name="telephone" type="text" maxlength="40" placeholder="Ex. : 07 00 00 00 00">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_email"><b>E-mail</b></label>
              <input class="form-control" id="f_email" name="email" type="email" maxlength="160">
            </div>
            <div class="el-c12">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Actif (proposé à l'enrôlement)</span>
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
