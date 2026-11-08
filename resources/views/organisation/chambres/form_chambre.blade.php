<div class="modal fade panel-modal" id="modal-chambre" tabindex="-1" role="dialog" aria-labelledby="titre-modal-chambre" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:70%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-chambre">Chambre</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.chambres.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c4">
              <label class="form-label" for="f_annee_judiciaire_id"><b>Année judiciaire</b></label>
              <select class="form-select" id="f_annee_judiciaire_id" name="annee_judiciaire_id" required>
                <option value="">Choisir…</option>
                @foreach ($annees as $a)
                  <option value="{{ $a->id }}">{{ $a->libelle }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c8">
              <label class="form-label" for="f_section_id"><b>Section</b></label>
              <select class="form-select" id="f_section_id" name="section_id" required>
                <option value="">Choisir…</option>
                @foreach ($sections as $s)
                  <option value="{{ $s->id }}">{{ $s->libelle }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c5">
              <label class="form-label" for="f_libelle"><b>Chambre</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="120" placeholder="Ex. : 2e chambre civile" required>
            </div>
            <div class="el-c7">
              <label class="form-label" for="f_intitule"><b>Intitulé</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_intitule" name="intitule" type="text" maxlength="160" placeholder="Ex. : Immobilière et des paiements">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_groupe"><b>Groupe</b> <span class="text-muted">(facultatif : ex. Les juridictions correctionnelles)</span></label>
              <input class="form-control" id="f_groupe" name="groupe" type="text" maxlength="120">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_attributions"><b>Attributions</b> <span class="text-muted">(communes à toute la chambre)</span></label>
              <textarea class="form-control" id="f_attributions" name="attributions" data-riche data-hauteur="130" placeholder="Les matières dont la chambre connaît. Si chaque formation a ses propres attributions, laissez vide et renseignez-les dans les formations."></textarea>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_remarque"><b>Remarque</b> <span class="text-muted">(facultatif : ex. juges chargés de la mise en état)</span></label>
              <textarea class="form-control" id="f_remarque" name="remarque" data-riche data-hauteur="60"></textarea>
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_ordre"><b>Ordre</b></label>
              <input class="form-control" id="f_ordre" name="ordre" type="number" min="0" max="9999" step="1" value="0">
            </div>
            <div class="el-c5">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Chambre active</span>
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
