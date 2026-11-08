{{-- Justifier un retard : le motif est conservé et tracé, les relances de l'alerte sont suspendues pour la durée choisie. Variable : $pauses --}}
<div class="modal fade panel-modal" id="modal-justification" tabindex="-1" role="dialog" aria-labelledby="titre-modal-justification" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:560px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-justification">Justifier le retard</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form id="form-justification" autocomplete="off">
        <div class="modal-body dark-modal">
          <div class="al-motif" style="margin-bottom:14px" id="justif-rappel"></div>
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_justif_motif"><b>Motif du retard</b></label>
              <textarea class="form-control" id="f_justif_motif" name="motif" rows="3" maxlength="500" placeholder="Ex. : en attente de la pièce demandée à l'avocat, parties convoquées à nouveau…" required></textarea>
              <small class="text-muted">Au moins 10 caractères. Le motif est visible de la hiérarchie et conservé dans le journal d'audit.</small>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_justif_jours"><b>Suspendre les relances pendant</b></label>
              <select class="form-select" id="f_justif_jours" name="jours" data-sans-recherche>
                @foreach ($pauses as $j)<option value="{{ $j }}" @selected($j === 7)>{{ $j }} jours</option>@endforeach
              </select>
              <small class="text-muted">Passé ce délai, si l'action n'est toujours pas faite, les relances reprennent.</small>
            </div>
            <div class="el-c12" id="erreur-justification"></div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" id="valider-justification" type="submit">Justifier le retard</button>
        </div>
      </form>
    </div>
  </div>
</div>
