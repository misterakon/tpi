<div class="modal fade panel-modal" id="modal-delais_procedure" tabindex="-1" role="dialog" aria-labelledby="titre-modal-delais_procedure" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-delais_procedure">Délai d'une étape</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('referentiels.delais_procedure.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_libelle"><b>Règle</b> <span class="text-muted">(proposée automatiquement, modifiable)</span></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="160" placeholder="Ex. : Rédaction du jugement civil" required>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_etape"><b>Étape du circuit</b></label>
              <select class="form-select" id="f_etape" name="etape" required data-sans-recherche>
                <option value="">Choisir…</option>
                @foreach ($opt_etape as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c6" id="bloc-acteur">
              <label class="form-label" for="f_acteur"><b>Qui doit agir</b> <span class="text-muted">(facultatif)</span></label>
              <select class="form-select" id="f_acteur" name="acteur" data-sans-recherche>
                <option value="">—</option>
                @foreach ($opt_acteur as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_registre"><b>Registre</b> <span class="text-muted" id="aide-registre">(facultatif)</span></label>
              <select class="form-select" id="f_registre" name="registre" data-sans-recherche>
                <option value="">—</option>
                @foreach ($opt_registre as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c8">
              <label class="form-label" for="f_nature_affaire_id"><b>Nature d'affaire</b> <span class="text-muted">(facultatif : plus précis que le registre)</span></label>
              <select class="form-select" id="f_nature_affaire_id" name="nature_affaire_id" data-sans-recherche>
                <option value="">—</option>
                @foreach ($opt_nature_affaire_id as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_valeur"><b>Délai</b></label>
              <input class="form-control" id="f_valeur" name="valeur" type="number" min="0" max="999" step="1" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_unite"><b>Unité</b></label>
              <select class="form-select" id="f_unite" name="unite" required data-sans-recherche>
                <option value="">Choisir…</option>
                @foreach ($opt_unite as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c5" id="bloc-renvois">
              <label class="form-label" for="f_renvois_max"><b>Renvois au plus</b> <span class="text-muted">(vide : sans limite)</span></label>
              <input class="form-control" id="f_renvois_max" name="renvois_max" type="number" min="0" max="99" step="1">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_point_depart"><b>Le délai court à partir de</b></label>
              <select class="form-select" id="f_point_depart" name="point_depart" required data-sans-recherche>
                <option value="">Choisir…</option>
                @foreach ($opt_point_depart as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c12">
              <div id="apercu-delai" style="border-left:4px solid #00794C; background:#F5FAF7; border-radius:0 10px 10px 0; padding:10px 14px; font-size:13.5px; line-height:1.55">
                <b>En résumé</b>
                <div id="apercu-texte" class="el-muted">Choisissez l'étape et le délai.</div>
              </div>
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
                <input type="checkbox" id="f_a_valider" name="a_valider" value="1" data-defaut="1">
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
