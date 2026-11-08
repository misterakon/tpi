{{-- Répartition d'une requête vers une formation (droit « requetes.repartir »). Variable : $chambres --}}
<div class="modal fade panel-modal" id="modal-repartition" tabindex="-1" role="dialog" aria-labelledby="titre-modal-repartition" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-repartition">Répartir la requête</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-repartition" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_rep_chambre"><b>Formation (chambre) compétente</b></label>
              <select class="form-select" id="f_rep_chambre" name="chambre_id" required>
                <option value="">Choisir…</option>
                @foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}{{ $c->intitule ? ' – '.$c->intitule : '' }}</option>@endforeach
              </select>
              @error('chambre_id') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_rep_note"><b>Note</b> <span class="el-muted">(facultatif)</span></label>
              <input class="form-control" id="f_rep_note" name="note" type="text" maxlength="300" placeholder="Ex. : urgence, saisie conservatoire à autoriser">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Répartir</button>
        </div>
      </form>
    </div>
  </div>
</div>
