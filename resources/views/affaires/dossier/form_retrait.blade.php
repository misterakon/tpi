{{-- Retrait d'une pièce du dossier : motif obligatoire, le fichier et son historique de consultation sont conservés --}}
<div class="modal fade panel-modal" id="modal-retrait" tabindex="-1" role="dialog" aria-labelledby="titre-modal-retrait" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-retrait">Retirer la pièce du dossier</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-retrait" action="" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="red-bandeau">« <b id="dn-retrait-titre"></b> » n'apparaîtra plus dans le dossier. Le fichier est conservé, avec l'historique de ses consultations, et le retrait est inscrit au journal.</div>
          <div class="el-formgrid" style="margin-top:14px">
            <div class="el-c12">
              <label class="form-label" for="f_retrait_motif"><b>Motif du retrait</b></label>
              <textarea class="form-control" id="f_retrait_motif" name="motif" rows="3" maxlength="300" required placeholder="Ex. : pièce déposée par erreur dans ce dossier"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-danger text-white" type="submit">Retirer la pièce</button>
        </div>
      </form>
    </div>
  </div>
</div>
