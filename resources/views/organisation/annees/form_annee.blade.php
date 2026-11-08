<div class="modal fade panel-modal" id="modal-annee" tabindex="-1" role="dialog" aria-labelledby="titre-modal-annee" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:560px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-annee">année judiciaire</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.annees.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c4">
              <label class="form-label" for="f_libelle"><b>Année judiciaire</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="20" placeholder="2026-2027" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_debut"><b>Début</b></label>
              <input class="form-control" id="f_debut" name="debut" type="date" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_fin"><b>Fin</b></label>
              <input class="form-control" id="f_fin" name="fin" type="date" required>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_ordonnance"><b>Ordonnance de référence</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_ordonnance" name="ordonnance" type="text" maxlength="120" placeholder="Ex. : Ordonnance n°600/2026">
            </div>
            <div class="el-c12">
              <label class="el-switch el-switch--lg" for="f_courante">
                <input type="checkbox" id="f_courante" name="courante" value="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Année en cours</span>
              </label>
              <small class="text-muted">Une seule année peut être « en cours » : l'ancienne est décochée.</small>
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
