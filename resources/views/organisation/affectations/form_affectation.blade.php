<div class="modal fade panel-modal" id="modal-affectation" tabindex="-1" role="dialog" aria-labelledby="titre-modal-affectation" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-affectation">Affectation</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('organisation.affectations.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">
        <input type="hidden" name="formation_id" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c7">
              <label class="form-label" for="f_agent_id"><b>Personne</b></label>
              <select class="form-select" id="f_agent_id" name="agent_id" required>
                <option value="">Choisir…</option>
                @foreach ($agents as $agent)
                  <option value="{{ $agent->id }}">{{ $agent->nomComplet() }} — {{ $agent->fonctionLibelle() }}@unless ($agent->actif) (hors poste)@endunless</option>
                @endforeach
              </select>
            </div>
            <div class="el-c5">
              <label class="form-label" for="f_role"><b>Rôle</b></label>
              <select class="form-select" id="f_role" name="role" required>
                <option value="">Choisir…</option>
                @foreach (\App\Models\Affectation::ROLES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c6">
              <label class="el-switch el-switch--lg" for="f_suppleant">
                <input type="checkbox" id="f_suppleant" name="suppleant" value="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Suppléant</span>
              </label>
            </div>
            <div class="el-c6">
              <label class="el-switch el-switch--lg" for="f_chef_section">
                <input type="checkbox" id="f_chef_section" name="chef_section" value="1">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Chef de section</span>
              </label>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_debut"><b>Du</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_debut" name="debut" type="date">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_fin"><b>Au</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_fin" name="fin" type="date">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_remarque"><b>Remarque</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="f_remarque" name="remarque" type="text" maxlength="200" placeholder="Ex. : en remplacement de…">
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
