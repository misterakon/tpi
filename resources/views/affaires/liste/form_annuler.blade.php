{{-- Annulation d'une affaire : le motif est obligatoire, le numéro reste attribué (variable : $affaire) --}}
<div class="modal fade panel-modal" id="modal-annuler" tabindex="-1" role="dialog" aria-labelledby="titre-modal-annuler" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-annuler">Annuler l'affaire {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form action="{{ route('affaires.liste.annuler', $affaire->id) }}" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="red-bandeau">Une affaire ne se supprime pas : elle est marquée « annulée ». Son numéro reste attribué et ne sera jamais réutilisé.</div>
          <div class="el-formgrid" style="margin-top:14px">
            <div class="el-c12">
              <label class="form-label" for="f_motif_annulation"><b>Motif de l'annulation</b></label>
              <textarea class="form-control" id="f_motif_annulation" name="motif" rows="3" maxlength="300" required placeholder="Ex. : enrôlement en double de l'affaire RG-0012/26"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-danger text-white" type="submit">Annuler l'affaire</button>
        </div>
      </form>
    </div>
  </div>
</div>
