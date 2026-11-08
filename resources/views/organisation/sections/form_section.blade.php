<div class="modal fade panel-modal" id="modal-section" tabindex="-1" role="dialog" aria-labelledby="titre-modal-section" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:520px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-section">Section</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.sections.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c9">
              <label class="form-label" for="f_libelle"><b>Nom de la section</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="120" placeholder="Ex. : Section pénale" required>
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_ordre"><b>Ordre</b></label>
              <input class="form-control" id="f_ordre" name="ordre" type="number" min="0" max="999" step="1" value="0">
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
