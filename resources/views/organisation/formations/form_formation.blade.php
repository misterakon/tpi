{{-- Fenêtre de saisie d'une formation ; incluse dans la page « Composition » d'une chambre (variable : $chambre). --}}
<div class="modal fade panel-modal" id="modal-formation" tabindex="-1" role="dialog" aria-labelledby="titre-modal-formation" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-formation">Formation</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form_formation" id="form-formation" action="{{ route('organisation.formations.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">
        <input type="hidden" name="chambre_id" value="{{ $chambre->id }}">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c5">
              <label class="form-label" for="f_nom"><b>Formation</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_nom" name="nom" type="text" maxlength="120" placeholder="Ex. : Formation A, 1re formation">
            </div>
            <div class="el-c7">
              <label class="form-label" for="f_intitule"><b>Intitulé</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_intitule" name="intitule" type="text" maxlength="160" placeholder="Ex. : Tribunal de simple police">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_attributions"><b>Attributions de la formation</b> <span class="text-muted">(facultatif : si elles diffèrent de celles de la chambre)</span></label>
              <textarea class="form-control" id="f_attributions" name="attributions" data-riche data-hauteur="110"></textarea>
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_ordre"><b>Ordre</b></label>
              <input class="form-control" id="f_ordre" name="ordre" type="number" min="0" max="9999" step="1" value="0">
            </div>
            <div class="el-c5">
              <label class="el-switch el-switch--lg" for="f_actif_formation">
                <input type="checkbox" id="f_actif_formation" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Formation active</span>
              </label>
            </div>
          </div>
          <small class="text-muted" style="display:block; margin-top:10px">Sans nom de formation, la composition est celle de la chambre elle-même (une seule fois par chambre).</small>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white submit" type="submit">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
