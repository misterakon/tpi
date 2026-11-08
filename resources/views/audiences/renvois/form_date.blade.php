{{-- Changer la date d'un renvoi, ou fixer / proroger un délibéré (droit « renvois.modifier »). La même fenêtre sert aux deux. --}}
<div class="modal fade panel-modal" id="modal-date" tabindex="-1" role="dialog" aria-labelledby="titre-modal-date" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content au-modal-dates">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-date">Changer la date</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-date" autocomplete="off" onsubmit="return false">
        <div class="modal-body dark-modal">
          <div class="au-champs" style="grid-template-columns:1fr">
            <div class="au-conseil" id="aide-date" style="margin:0"></div>
            <div>
              <label for="f_date_valeur" id="lib-date"><b>Nouvelle date</b></label>
              <input id="f_date_valeur" name="date" type="date" required>
              <div class="au-propositions" id="propositions-date"></div>
              <div id="analyse-date"></div>
            </div>
            <div>
              <label for="f_date_motif"><b>Motif</b> <small>(facultatif)</small></label>
              <input id="f_date_motif" name="motif" type="text" maxlength="200" list="dl-motifs-rv" placeholder="Ex. : pour conclusions du défendeur">
              <datalist id="dl-motifs-rv">@foreach (\App\Models\RoleLigne::MOTIFS_RENVOI as $m)<option value="{{ $m }}">@endforeach</datalist>
            </div>
            <div id="erreur-date"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit" id="valider-date"><i class="fa-solid fa-check"></i> Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
