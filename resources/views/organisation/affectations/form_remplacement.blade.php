<div class="modal fade panel-modal" id="modal-remplacement" tabindex="-1" role="dialog" aria-labelledby="titre-modal-remplacement" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:50%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-remplacement">Remplacer une personne</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form_remplacement" id="form-remplacement" action="" method="post" autocomplete="off">
        @csrf

        <div class="modal-body dark-modal">
          <p class="el-muted" style="margin:0 0 14px">
            <b id="remplacement-ancien"></b> garde son historique : son affectation se termine la veille de la date d'effet, et la personne choisie reprend le même rôle à cette date.
          </p>
          <div class="el-formgrid">
            <div class="el-c8">
              <label class="form-label" for="f_nouvel_agent_id"><b>Remplaçant(e)</b></label>
              <select class="form-select" id="f_nouvel_agent_id" name="nouvel_agent_id" required>
                <option value="">Choisir…</option>
                @foreach ($agents->where('actif', true) as $agent)
                  <option value="{{ $agent->id }}">{{ $agent->nomComplet() }} — {{ $agent->fonctionLibelle() }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_date_effet"><b>Date d'effet</b></label>
              <input class="form-control" id="f_date_effet" name="date_effet" type="date" required>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white submit" type="submit">Remplacer</button>
        </div>
      </form>
    </div>
  </div>
</div>
