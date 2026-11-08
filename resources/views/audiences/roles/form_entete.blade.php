{{-- En-tête du rôle : date, heure, salle, avocats, signature (droit « roles.modifier », rôle en préparation). Variables : $role, $salles --}}
<div class="modal fade panel-modal" id="modal-entete" tabindex="-1" role="dialog" aria-labelledby="titre-modal-entete" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-entete">En-tête du rôle</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-entete" method="post" action="{{ route('audiences.roles.entete', $role->id) }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            @if ($role->type !== 'civil')
              <div class="el-c12">
                <label class="form-label" for="f_ent_intitule"><b>Intitulé de l'audience</b></label>
                <input class="form-control" id="f_ent_intitule" name="intitule" type="text" maxlength="160" list="liste-intitules-ent" value="{{ old('intitule', $role->intitule) }}">
                <datalist id="liste-intitules-ent">@foreach (\App\Models\RoleAudience::INTITULES[$role->type] ?? [] as $i)<option value="{{ $i }}">@endforeach</datalist>
              </div>
              <div class="el-c12">
                <label class="rl-interrupteur" for="f_ent_additif">
                  <input type="checkbox" id="f_ent_additif" name="additif" value="1" @checked(old('additif', $role->additif))>
                  <span class="rl-interrupteur__piste"><span class="rl-interrupteur__bouton"></span></span>
                  <span><b>Rôle additif</b><small>Ajoute la mention « (Rôle additif) » sous la date.</small></span>
                </label>
              </div>
            @endif
            <div class="el-c6">
              <label class="form-label" for="f_ent_date"><b>Date de l'audience</b></label>
              <input class="form-control" id="f_ent_date" name="date_audience" type="date" value="{{ old('date_audience', $role->date_audience->format('Y-m-d')) }}" required>
              @error('date_audience') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_ent_heure"><b>Heure</b></label>
              <input class="form-control" id="f_ent_heure" name="heure" type="time" value="{{ old('heure', $role->heure ? substr((string) $role->heure, 0, 5) : '') }}">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_ent_salle"><b>Salle</b></label>
              <select class="form-select" id="f_ent_salle" name="salle_id">
                <option value="">Salle à fixer</option>
                @foreach ($salles as $s)<option value="{{ $s->id }}" @selected((int) old('salle_id', $role->salle_id) === $s->id)>{{ $s->nom }}</option>@endforeach
              </select>
              @error('salle_id') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_ent_fait"><b>Fait à</b></label>
              <input class="form-control" id="f_ent_fait" name="fait_a" type="text" maxlength="80" value="{{ old('fait_a', $role->fait_a) }}" required>
              @error('fait_a') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_ent_signe"><b>Date de signature</b></label>
              <input class="form-control" id="f_ent_signe" name="date_signature" type="date" value="{{ old('date_signature', $role->date_signature?->format('Y-m-d')) }}">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_ent_signataire"><b>Signataire</b></label>
              <input class="form-control" id="f_ent_signataire" name="signataire" type="text" maxlength="80" value="{{ old('signataire', $role->signataire) }}" required placeholder="Le Président">
              @error('signataire') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            @if ($role->estPenal())
              <div class="el-c12"><div class="rl-separateur"><i class="fa-solid fa-people-group"></i> Équipe d'audience <small>(laissée vide, elle est remplie à la main sur le rôle imprimé)</small></div></div>
              <div class="el-c6"><label class="form-label" for="f_ent_pres"><b>Président</b></label><input class="form-control" id="f_ent_pres" name="president" type="text" maxlength="200" value="{{ old('president', $role->president) }}"></div>
              <div class="el-c6"><label class="form-label" for="f_ent_proc"><b>Procureur</b></label><input class="form-control" id="f_ent_proc" name="procureur" type="text" maxlength="200" value="{{ old('procureur', $role->procureur) }}"></div>
              <div class="el-c6"><label class="form-label" for="f_ent_ass"><b>Assesseurs</b></label><input class="form-control" id="f_ent_ass" name="assesseurs" type="text" maxlength="300" value="{{ old('assesseurs', $role->assesseurs) }}"></div>
              <div class="el-c6"><label class="form-label" for="f_ent_gref"><b>Greffier</b></label><input class="form-control" id="f_ent_gref" name="greffier" type="text" maxlength="200" value="{{ old('greffier', $role->greffier) }}"></div>
            @endif
            <div class="el-c12">
              <label class="rl-interrupteur" for="f_ent_avocats">
                <input type="checkbox" id="f_ent_avocats" name="avec_avocats" value="1" @checked(old('avec_avocats', $role->avec_avocats))>
                <span class="rl-interrupteur__piste"><span class="rl-interrupteur__bouton"></span></span>
                <span><b>Afficher les avocats</b><small>« (Me X) » sous chaque partie, comme sur le rôle de janvier.</small></span>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
