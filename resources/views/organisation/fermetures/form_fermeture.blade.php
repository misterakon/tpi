<div class="modal fade panel-modal" id="modal-fermeture" tabindex="-1" role="dialog" aria-labelledby="titre-modal-fermeture" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:640px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-fermeture">Période</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.fermetures.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c4">
              <label class="form-label" for="f_annee_judiciaire_id"><b>Année judiciaire</b></label>
              <select class="form-select" id="f_annee_judiciaire_id" name="annee_judiciaire_id" required data-sans-recherche>
                @foreach ($annees as $a)
                  <option value="{{ $a->id }}">{{ $a->libelle }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c8">
              <label class="form-label" for="f_type"><b>Type</b></label>
              <select class="form-select" id="f_type" name="type" required data-sans-recherche>
                @foreach (\App\Models\Fermeture::TYPES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_libelle"><b>Libellé</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="160" placeholder="Ex. : Fête de l'Indépendance, Vacances de fin d'année" required>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_debut"><b>Du</b></label>
              <input class="form-control" id="f_debut" name="debut" type="date" required>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_fin"><b>Au</b> <span class="text-muted">(vide : une seule journée)</span></label>
              <input class="form-control" id="f_fin" name="fin" type="date">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_remarque"><b>Remarque</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_remarque" name="remarque" type="text" maxlength="255">
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
