<div class="modal fade panel-modal" id="modal-ordonnance" tabindex="-1" role="dialog" aria-labelledby="titre-modal-ordonnance" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-ordonnance">Ordonnance</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.ordonnances.store') }}" method="post" enctype="multipart/form-data" autocomplete="off">
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
            <div class="el-c4">
              <label class="form-label" for="f_type"><b>Type</b></label>
              <select class="form-select" id="f_type" name="type" required data-sans-recherche>
                @foreach (\App\Models\Ordonnance::TYPES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_numero"><b>Numéro</b></label>
              <input class="form-control" id="f_numero" name="numero" type="text" maxlength="60" placeholder="Ex. : 600/2026" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_date_signature"><b>Date de signature</b></label>
              <input class="form-control" id="f_date_signature" name="date_signature" type="date" max="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_date_effet"><b>Date d'effet</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_date_effet" name="date_effet" type="date">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_objet"><b>Objet</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_objet" name="objet" type="text" maxlength="255" placeholder="Ex. : Répartition des magistrats et des audiences, année 2026-2027">
            </div>
            <div class="el-c12" id="bloc-fichier">
              <label class="form-label" for="f_fichier"><b>Ordonnance signée</b> <span class="text-muted">(PDF, 30 Mo au maximum)</span></label>
              <input class="form-control" id="f_fichier" name="fichier" type="file" accept="application/pdf,.pdf">
            </div>
            <div class="el-c12" id="bloc-fichier-info" style="display:none">
              <label class="form-label"><b>Fichier archivé</b></label>
              <div class="el-person__sub" id="info-fichier" style="font-size:14px"></div>
              <small class="text-muted">Le PDF archivé ne peut pas être remplacé ; seules les informations ci-dessus se modifient.</small>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_observations"><b>Observations</b> <span class="text-muted">(facultatif)</span></label>
              <textarea class="form-control" id="f_observations" name="observations" data-riche data-hauteur="110" placeholder="Précisions utiles : ce que l'ordonnance modifie, personnes concernées…"></textarea>
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
