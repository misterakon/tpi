<div class="modal fade panel-modal" id="modal-natures_affaire" tabindex="-1" role="dialog" aria-labelledby="titre-modal-natures_affaire" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:640px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-natures_affaire">Nature d'affaire</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('referentiels.natures_affaire.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c8">
              <label class="form-label" for="f_libelle"><b>Nature d'affaire</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="160" placeholder="Ex. : Matrimoniale" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_registre"><b>Registre</b></label>
              <select class="form-select" id="f_registre" name="registre" required data-sans-recherche>
                <option value="">Choisir…</option>
                @foreach ($opt_registre as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c5">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Actif</span>
              </label>
            </div>
            <div class="el-c12">
              <label class="el-switch el-switch--lg" for="f_exclue_enrolement">
                <input type="checkbox" id="f_exclue_enrolement" name="exclue_enrolement" value="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Exclue des dates limites d'enrôlement (référés, voies d'exécution)</span>
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
