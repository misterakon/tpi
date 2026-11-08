{{-- Clôture d'une requête : traitée (décision rendue) ou classée sans suite (motif) --}}
<div class="modal fade panel-modal" id="modal-cloture" tabindex="-1" role="dialog" aria-labelledby="titre-modal-cloture" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-cloture">Clôturer la requête</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-cloture" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="issue" value="traitee">
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c8">
              <label class="form-label" for="f_clo_reference"><b id="lib-reference">Décision rendue</b></label>
              <input class="form-control" id="f_clo_reference" name="reference" type="text" maxlength="120" required placeholder="Ex. : Ordonnance n° 12/26">
              @error('reference') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_clo_date"><b>Date</b></label>
              <input class="form-control" id="f_clo_date" name="date" type="date" max="{{ date('Y-m-d') }}" required value="{{ date('Y-m-d') }}">
              @error('date') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-primary text-white" type="submit" id="btn-cloture">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
