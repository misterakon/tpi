{{-- Ordonnance de clôture : numéro, date, puis versement à la chambre de jugement. Variables : $chambres (vide sans le droit de réattribuer) --}}
<div class="modal fade panel-modal" id="modal-mee-cloture" tabindex="-1" role="dialog" aria-labelledby="titre-modal-mee-cloture" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-mee-cloture">Clôturer la mise en état</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-mee-cloture" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c7">
              <label class="form-label" for="f_cl_numero"><b>N° de l'ordonnance de clôture</b></label>
              <input class="form-control" id="f_cl_numero" name="numero_ordonnance_cloture" type="text" maxlength="60" required placeholder="Ex. : 12/26">
              @error('numero_ordonnance_cloture') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c5">
              <label class="form-label" for="f_cl_date"><b>Date de la clôture</b></label>
              <input class="form-control" id="f_cl_date" name="date_cloture" type="date" max="{{ date('Y-m-d') }}" required value="{{ date('Y-m-d') }}">
              @error('date_cloture') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12"><div class="aff-info"><i class="fa-solid fa-arrow-right-arrow-left"></i><span>L'affaire est versée à la chambre de jugement : elle y apparaît « à mettre au rôle » pour l'audience indiquée ci-dessous.</span></div></div>
            <div class="el-c5">
              <label class="form-label" for="f_cl_jugement"><b>Audience de jugement</b></label>
              <input class="form-control" id="f_cl_jugement" name="date_audience_jugement" type="date" required>
              @error('date_audience_jugement') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            @if ($chambres->isNotEmpty())
              <div class="el-c7">
                <label class="form-label" for="f_cl_chambre"><b>Chambre de jugement</b></label>
                <select class="form-select" id="f_cl_chambre" name="chambre_jugement_id" data-sans-recherche>
                  @foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach
                </select>
                <small class="text-muted">Par défaut, la chambre de l'affaire.</small>
              </div>
            @endif
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-primary text-white" type="submit">Clôturer et verser au rôle</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Retirer de la mise en état (erreur de saisie, ou affaire reprise par la formation) --}}
<div class="modal fade panel-modal" id="modal-mee-retrait" tabindex="-1" role="dialog" aria-labelledby="titre-modal-mee-retrait" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-mee-retrait">Retirer de la mise en état</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-mee-retrait" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <label class="form-label" for="f_ret_motif"><b>Motif</b></label>
          <input class="form-control" id="f_ret_motif" name="motif" type="text" maxlength="300" required placeholder="Ex. : saisie par erreur, affaire reprise par la formation">
          <small class="text-muted">L'affaire redevient une affaire ordinaire de sa chambre.</small>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-primary text-white" type="submit">Retirer</button>
        </div>
      </form>
    </div>
  </div>
</div>
