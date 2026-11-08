{{-- Création d'un rôle d'audience (droit « roles.creer »). Variables : $chambres, $salles --}}
<div class="modal fade panel-modal" id="modal-role" tabindex="-1" role="dialog" aria-labelledby="titre-modal-role" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal" style="width:50%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-role">Nouveau rôle d'audience</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-role" method="post" action="{{ route('audiences.roles.store') }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_role_chambre"><b>Chambre</b></label>
              <select class="form-select" id="f_role_chambre" name="chambre_id" required>
                <option value="">Choisir…</option>
                @foreach ($chambres as $c)<option value="{{ $c->id }}" data-type="{{ $c->section?->code === 'penale' ? 'penal' : 'civil' }}" @selected((int) old('chambre_id') === $c->id)>{{ $c->libelle }}{{ $c->intitule ? ' – '.$c->intitule : '' }}</option>@endforeach
              </select>
              @error('chambre_id') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_role_type"><b>Type de rôle</b></label>
              <select class="form-select" id="f_role_type" name="type" data-sans-recherche>
                @foreach (\App\Models\RoleAudience::TYPES as $code => $libelle)
                  @if ($code !== 'penal' || ($peutPenal ?? false))<option value="{{ $code }}" @selected(old('type', 'civil') === $code)>{{ $libelle }}</option>@endif
                @endforeach
              </select>
            </div>
            <div class="el-c6" id="bloc-role-additif" style="display:none">
              <label class="rl-interrupteur" for="f_role_additif">
                <input type="checkbox" id="f_role_additif" name="additif" value="1" @checked(old('additif'))>
                <span class="rl-interrupteur__piste"><span class="rl-interrupteur__bouton"></span></span>
                <span><b>Rôle additif</b><small>Mention « (Rôle additif) ».</small></span>
              </label>
            </div>
            <div class="el-c12" id="bloc-role-intitule" style="display:none">
              <label class="form-label" for="f_role_intitule"><b>Intitulé de l'audience</b></label>
              <input class="form-control" id="f_role_intitule" name="intitule" type="text" maxlength="160" list="liste-intitules" value="{{ old('intitule') }}" placeholder="Choisissez ou saisissez l'intitulé">
              <datalist id="liste-intitules"></datalist>
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_role_date"><b>Date de l'audience</b></label>
              <input class="form-control" id="f_role_date" name="date_audience" type="date" value="{{ old('date_audience') }}" required>
              @error('date_audience') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_role_heure"><b>Heure</b> <span class="el-muted">(du calendrier)</span></label>
              <input class="form-control" id="f_role_heure" name="heure" type="time" value="{{ old('heure') }}">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_role_salle"><b>Salle</b> <span class="el-muted">(du calendrier)</span></label>
              <select class="form-select" id="f_role_salle" name="salle_id">
                <option value="">Salle à fixer</option>
                @foreach ($salles as $s)<option value="{{ $s->id }}" @selected((int) old('salle_id') === $s->id)>{{ $s->nom }}</option>@endforeach
              </select>
              @error('salle_id') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12"><div class="rl-astuce"><i class="fa-solid fa-wand-magic-sparkles"></i><span>Le rôle est composé aussitôt : délibérés, affaires anciennes et affaires nouvelles de la chambre sont reprises sans ressaisie.</span></div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Créer et composer le rôle</button>
        </div>
      </form>
    </div>
  </div>
</div>
