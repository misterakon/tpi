<div class="modal fade panel-modal" id="modal-objets_litige" tabindex="-1" role="dialog" aria-labelledby="titre-modal-objets_litige" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:56%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-objets_litige">Objet du litige</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('referentiels.objets_litige.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_libelle"><b>Objet du litige</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="160" placeholder="Ex. : Divorce et séparation de corps" required>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_matiere_id"><b>Matière</b></label>
              <select class="form-select" id="f_matiere_id" name="matiere_id" required data-sans-recherche>
                <option value="">Choisir…</option>
                @foreach ($opt_matiere_id as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_nature_affaire_id"><b>Nature d'affaire</b> <span class="text-muted">(Facultatif)</span></label>
              <select class="form-select" id="f_nature_affaire_id" name="nature_affaire_id">
                <option value="">—</option>
                @foreach ($opt_nature_affaire_id as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_chambre_libelle"><b>Chambre</b> <span class="text-muted">(La chambre qui connaît de cet objet (d'après l'ordonnance))</span></label>
              <select class="form-select" id="f_chambre_libelle" name="chambre_libelle">
                <option value="">—</option>
                @foreach ($opt_chambre_libelle as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c5">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Actif</span>
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
