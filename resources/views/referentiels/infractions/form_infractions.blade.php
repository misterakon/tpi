<div class="modal fade panel-modal" id="modal-infractions" tabindex="-1" role="dialog" aria-labelledby="titre-modal-infractions" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:560px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-infractions">Infraction</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('referentiels.infractions.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_libelle"><b>Infraction (nature de la prévention)</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="200" placeholder="Ex. : Abus de confiance" required>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_articles"><b>Articles visés</b> <span class="el-muted">(code pénal)</span></label>
              <input class="form-control" id="f_articles" name="articles" type="text" maxlength="300" placeholder="Ex. : Art. 421 du CP">
              <small class="text-muted">Proposés avec l'infraction à l'enrôlement d'une affaire pénale.</small>
            </div>
            <div class="el-c5">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Actif</span>
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
