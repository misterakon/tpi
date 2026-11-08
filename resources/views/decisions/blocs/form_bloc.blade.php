<div class="modal fade panel-modal" id="modal-bloc" tabindex="-1" role="dialog" aria-labelledby="titre-modal-bloc" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-bloc">Bloc de texte</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('decisions.blocs.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c8">
              <label class="form-label" for="f_nom"><b>Nom du bloc</b></label>
              <input class="form-control" id="f_nom" name="nom" type="text" maxlength="160" placeholder="Ex. : Formule de clôture" required>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_categorie"><b>Catégorie</b></label>
              <select class="form-select" id="f_categorie" name="categorie" required data-sans-recherche>
                @foreach (\App\Models\BlocTexte::CATEGORIES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_code"><b>Code</b> <span class="text-muted">(minuscules, chiffres, tiret bas)</span></label>
              <input class="form-control" id="f_code" name="code" type="text" maxlength="60" placeholder="formule_cloture" required>
            </div>
            <div class="el-c6" style="align-self:end">
              <label class="el-switch el-switch--lg" for="f_actif">
                <input type="checkbox" id="f_actif" name="actif" value="1" data-defaut="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Bloc actif</span>
              </label>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_contenu"><b>Texte du bloc</b> <span class="text-muted">(il peut contenir des variables : &#123;&#123; defunt.nom &#125;&#125;)</span></label>
              <textarea class="form-control" id="f_contenu" name="contenu" data-riche data-alignement data-hauteur="150"></textarea>
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
