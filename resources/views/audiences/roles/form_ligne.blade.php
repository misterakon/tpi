{{-- Modification d'une ligne du rôle (droit « roles.modifier »). Rôle publié : seule la suite donnée à l'audience se note. Variable : $role --}}
<div class="modal fade panel-modal" id="modal-ligne" tabindex="-1" role="dialog" aria-labelledby="titre-modal-ligne" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal aff-modal--large">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-ligne">Modifier la ligne</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-ligne" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            @unless ($role->estPublie())
              <div class="el-c4">
                <label class="form-label" for="f_lig_rubrique"><b>Rubrique</b></label>
                <select class="form-select" id="f_lig_rubrique" name="rubrique" data-sans-recherche>
                  @foreach (\App\Models\RoleLigne::RUBRIQUES as $code => $lib)<option value="{{ $code }}">{{ $lib }}</option>@endforeach
                </select>
              </div>
              <div class="el-c8">
                <label class="form-label" for="f_lig_numero"><b>Numéro</b> <span class="el-muted">(un par ligne : « 690/26 » puis « Et 848/26 »)</span></label>
                <textarea class="form-control" id="f_lig_numero" name="numero" rows="2" maxlength="120"></textarea>
              </div>
              <div class="el-c6">
                <label class="form-label" for="f_lig_dem"><b>{{ $role->estPenal() ? 'Identité des prévenus' : 'Demandeurs' }}</b> <span class="el-muted">({{ $role->estPenal() ? 'nom, puis âge, nationalité, profession' : 'un par ligne' }})</span></label>
                <textarea class="form-control" id="f_lig_dem" name="demandeurs" rows="4" maxlength="2000"></textarea>
              </div>
              <div class="el-c6">
                <label class="form-label" for="f_lig_def"><b>{{ $role->estPenal() ? 'Date du mandat de dépôt' : 'Défendeurs' }}</b> <span class="el-muted">({{ $role->estPenal() ? 'flagrants délits : 23-09-26' : 'un par ligne' }})</span></label>
                <textarea class="form-control" id="f_lig_def" name="defendeurs" rows="4" maxlength="2000"></textarea>
              </div>
              <div class="el-c6">
                <label class="form-label" for="f_lig_avdem"><b>Avocats des demandeurs</b></label>
                <textarea class="form-control" id="f_lig_avdem" name="avocats_demandeurs" rows="2" maxlength="1000"></textarea>
              </div>
              <div class="el-c6">
                <label class="form-label" for="f_lig_avdef"><b>Avocats des défendeurs</b></label>
                <textarea class="form-control" id="f_lig_avdef" name="avocats_defendeurs" rows="2" maxlength="1000"></textarea>
              </div>
              <div class="el-c8">
                <label class="form-label" for="f_lig_objet"><b>{{ $role->estPenal() ? 'Nature de la prévention et articles' : 'Objet' }}</b></label>
                <textarea class="form-control" id="f_lig_objet" name="objet" rows="3" maxlength="1500"></textarea>
              </div>
              <div class="el-c4">
                <label class="form-label" for="f_lig_obs"><b>{{ $role->estPenal() ? 'Observation ou décision' : 'Observations' }}</b></label>
                <textarea class="form-control" id="f_lig_obs" name="observations" rows="3" maxlength="1000"></textarea>
              </div>
            @endunless
            <div class="el-c12"><div class="rl-separateur"><i class="fa-solid fa-flag-checkered"></i> Suite donnée à l'audience <small>(alimente le rôle suivant, n'est pas imprimée)</small></div></div>
            <div class="el-c6">
              <label class="form-label" for="f_lig_issue"><b>Issue</b></label>
              <select class="form-select" id="f_lig_issue" name="issue" data-sans-recherche>
                <option value="">Pas encore renseignée</option>
                @foreach (\App\Models\RoleLigne::ISSUES as $code => $lib)<option value="{{ $code }}">{{ $lib }}</option>@endforeach
              </select>
            </div>
            <div class="el-c6" id="bloc-renvoi">
              <label class="form-label" for="f_lig_renvoi"><b>Renvoyée au</b></label>
              <input class="form-control" id="f_lig_renvoi" name="renvoi_au" type="date" min="{{ $role->date_audience->copy()->addDay()->format('Y-m-d') }}">
              @error('renvoi_au') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12"><div class="rl-renvoi-aide" id="aide-renvoi" hidden></div></div>
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
