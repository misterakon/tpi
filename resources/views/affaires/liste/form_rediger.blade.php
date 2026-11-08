{{-- Rédiger une décision à partir de l'affaire : le modèle ; le reste est repris de l'affaire (variables : $affaire, $modeles) --}}
<div class="modal fade panel-modal" id="modal-rediger" tabindex="-1" role="dialog" aria-labelledby="titre-modal-rediger" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-rediger">Rédiger une décision — {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form action="{{ route('affaires.liste.rediger', $affaire->id) }}" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="red-bandeau">Le numéro RG, la date du RG, la chambre (et sa composition), la date d'audience et le demandeur sont repris de l'affaire : il ne reste qu'à compléter le reste.</div>
          <div class="el-formgrid" style="margin-top:14px">
            <div class="el-c12">
              <label class="form-label" for="f_modele_decision"><b>Modèle de décision</b></label>
              <select class="form-select" id="f_modele_decision" name="modele_decision_id" required>
                <option value="">Choisir…</option>
                @foreach ($modeles as $m)
                  <option value="{{ $m->id }}">{{ $m->nom }}{{ $m->etat === 'brouillon' ? ' (modèle en brouillon)' : '' }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-primary text-white" type="submit">Ouvrir la décision</button>
        </div>
      </form>
    </div>
  </div>
</div>
