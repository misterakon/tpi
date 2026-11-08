<div class="modal fade panel-modal" id="modal-modes_saisine" tabindex="-1" role="dialog" aria-labelledby="titre-modal-modes_saisine" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:640px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-modes_saisine">Mode de saisine</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('referentiels.modes_saisine.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c8">
              <label class="form-label" for="f_libelle"><b>Mode de saisine</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="160" placeholder="Ex. : Assignation" required>
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
              <label class="el-switch el-switch--lg" for="f_a_valider">
                <input type="checkbox" id="f_a_valider" name="a_valider" value="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">À valider par la Présidente</span>
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
