<div class="modal fade panel-modal" id="modal-agent" tabindex="-1" role="dialog" aria-labelledby="titre-modal-agent" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-agent">agent</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.personnel.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c5">
              <label class="form-label" for="f_nom"><b>Nom</b></label>
              <input class="form-control" id="f_nom" name="nom" type="text" maxlength="80" required>
            </div>
            <div class="el-c7">
              <label class="form-label" for="f_prenoms"><b>Prénoms</b></label>
              <input class="form-control" id="f_prenoms" name="prenoms" type="text" maxlength="120" required>
            </div>
            <div class="el-c5">
              <label class="form-label" for="f_fonction"><b>Fonction</b></label>
              <select class="form-select" id="f_fonction" name="fonction" required>
                <option value="">Choisir…</option>
                @foreach (\App\Models\Agent::FONCTIONS as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_sexe"><b>Sexe</b> <span class="text-muted">(facultatif)</span></label>
              <select class="form-select" id="f_sexe" name="sexe">
                <option value="">—</option>
                @foreach (\App\Models\Agent::SEXES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_matricule"><b>Matricule</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_matricule" name="matricule" type="text" maxlength="40">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_telephone"><b>Téléphone</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_telephone" name="telephone" type="text" maxlength="30">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_email"><b>E-mail</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_email" name="email" type="email" maxlength="255">
            </div>
            <div class="el-c8">
              <label class="form-label" for="f_user_id"><b>Compte de l'application</b> <span class="text-muted">(facultatif)</span></label>
              <select class="form-select" id="f_user_id" name="user_id">
                <option value="">Aucun compte</option>
                @foreach ($comptes as $id => $nom)
                  <option value="{{ $id }}">{{ $nom }}</option>
                @endforeach
              </select>
              <small class="text-muted">Facultatif : relie la personne à son compte.</small>
            </div>
            <div class="el-c4">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">En poste</span>
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
