{{-- Rédiger une décision : l'affaire du registre et le modèle (variables : $modeles, $chambres) --}}
<style>
  .red-aff { line-height: 1.35; } .red-aff b { font-weight: 700; } .red-aff small { display: block; color: #2F3A52; font-size: 12.5px; }
  .red-aff .red-aff__etiquette { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 99px; background: #FFEBD6; color: #8A3A00; font-size: 11.5px; font-weight: 700; }
</style>
<div class="modal fade panel-modal" id="modal-decision" tabindex="-1" role="dialog" aria-labelledby="titre-modal-decision" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:640px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-decision">Rédiger une décision</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('decisions.redaction.creer') }}" method="post" autocomplete="off">
        @csrf

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_modele_decision_id"><b>Modèle de décision</b></label>
              <select class="form-select" id="f_modele_decision_id" name="modele_decision_id" required>
                <option value="">Choisir…</option>
                @foreach ($modeles as $m)
                  <option value="{{ $m->id }}">{{ $m->nom }}{{ $m->etat === 'brouillon' ? ' (modèle en brouillon)' : '' }}</option>
                @endforeach
              </select>
              <small class="text-muted">Les modèles en brouillon sont proposés tant que les modèles définitifs ne sont pas validés.</small>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_affaire_id"><b>Affaire</b></label>
              <select class="form-select" id="f_affaire_id" name="affaire_id" data-sans-recherche required></select>
              <small class="text-muted">Cherchez par numéro ou par nom d'une partie. Sans recherche, la liste montre d'abord les affaires jugées dont la décision reste à rédiger. La chambre et la date d'audience se remplissent toutes seules.</small>
            </div>
            <div class="el-c8">
              <label class="form-label" for="f_chambre_id"><b>Chambre</b></label>
              <select class="form-select" id="f_chambre_id" name="chambre_id" required>
                <option value="">Choisir…</option>
                @foreach ($chambres as $c)
                  <option value="{{ $c->id }}">{{ $c->libelle }}{{ $c->intitule ? ' – '.$c->intitule : '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_date_audience"><b>Date d'audience</b></label>
              <input class="form-control" id="f_date_audience" name="date_audience" type="date">
            </div>
            <div class="el-c12">
              <small class="text-muted">La chambre fournit la composition du tribunal (président, assesseurs, greffier à la date d'audience, d'après l'ordonnance) : elle est remplie d'avance et reste modifiable si la composition change ce jour-là.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white submit" id="BtnEnregistrer" type="submit">Commencer la rédaction</button>
        </div>
      </form>
    </div>
  </div>
</div>
<style>
  .select2-results__option--highlighted .red-aff small, .select2-results__option--highlighted .red-aff div { color: #fff; }
</style>
