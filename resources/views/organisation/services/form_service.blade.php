<div class="modal fade panel-modal" id="modal-service" tabindex="-1" role="dialog" aria-labelledby="titre-modal-service" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-service">Service</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.services.store') }}" method="post" autocomplete="off">
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
            <div class="el-c6">
              <label class="form-label" for="f_categorie"><b>Catégorie</b></label>
              <select class="form-select" id="f_categorie" name="categorie" required>
                <option value="">Choisir…</option>
                @foreach (\App\Models\Service::CATEGORIES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c2">
              <label class="form-label" for="f_ordre"><b>Ordre</b></label>
              <input class="form-control" id="f_ordre" name="ordre" type="number" min="0" max="9999" step="1" value="0">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_libelle"><b>Service ou fonction</b></label>
              <input class="form-control" id="f_libelle" name="libelle" type="text" maxlength="255" placeholder="Ex. : Service des recours civils" required>
            </div>

            <div class="el-c12">
              <label class="form-label"><b>Personnes</b> <span class="text-muted">(chacune avec sa fonction, facultative)</span></label>
              <div class="el-lignes" id="lignes-agents"></div>
              <button type="button" class="el-btn el-btn--ghost el-btn--sm" style="margin-top:10px" onclick="ajouter_ligne()"><i class="fa-solid fa-plus"></i> Ajouter une personne</button>
            </div>

            <div class="el-c12">
              <label class="form-label" for="f_remarque"><b>Remarque</b> <span class="text-muted">(facultatif : personnes extérieures au tribunal, mission…)</span></label>
              <textarea class="form-control" id="f_remarque" name="remarque" data-riche data-hauteur="90"></textarea>
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

{{-- Modèle d'une ligne « personne + fonction » (copié par le script) --}}
<template id="modele-ligne-agent">
  <div class="el-ligne">
    <div>
      <label class="form-label">Personne</label>
      <select class="form-select" name="agents[__I__][agent_id]" required>
        <option value="">Choisir…</option>
        @foreach ($agents as $agent)
          <option value="{{ $agent->id }}">{{ $agent->nomComplet() }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="form-label">Fonction</label>
      <input class="form-control" name="agents[__I__][fonction]" type="text" maxlength="160" placeholder="Ex. : Secrétaire général">
    </div>
    <button class="el-iconbtn el-iconbtn--red" type="button" title="Retirer cette personne" aria-label="Retirer cette personne" onclick="retirer_ligne(this)"><i class="fa-regular fa-trash-can"></i></button>
  </div>
</template>
