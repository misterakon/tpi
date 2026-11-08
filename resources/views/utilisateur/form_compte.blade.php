<div class="modal fade panel-modal" id="tooltipmodal" tabindex="-1" role="dialog" aria-labelledby="tooltipmodal" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="mySmallModalLabel">Utilisateur</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('utilisateur.compte.store') }}" method="post">
          @csrf
          <input type="hidden" name="id_" value="">

          <div class="modal-body dark-modal">
            <div class="col-span-12">
              <label class="form-label" for="name"><b>Nom</b></label>
              <input class="form-control" id="name" name="name" type="text" maxlength="255" required>
            </div>
            <div class="col-span-12 mt-2">
              <label class="form-label" for="email"><b>E-mail</b></label>
              <input class="form-control" id="email" name="email" type="email" maxlength="255" required>
              <small class="text-muted">C'est à cette adresse que le code de connexion est envoyé.</small>
            </div>
            <div class="col-span-12 mt-2">
              <label class="form-label" for="password"><b>Mot de passe</b> <span class="text-muted" id="password_hint"></span></label>
              <div class="input-group flex !flex-nowrap">
                <input class="form-control rounded-r-none" id="password" name="password" type="password" minlength="{{ config('securite.mot_de_passe_min') }}" autocomplete="new-password">
                <button class="btn btn-primary text-white rounded-l-none" id="btn_voir_mdp" type="button" onclick="mdp_basculer()" title="Afficher / masquer le mot de passe"><i class="fa-regular fa-eye"></i></button>
              </div>

              <div class="card-wrapper custom-border rounded-3 checkbox-checked mt-2">
                <div class="form-check-size rtl-input">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input me-2" id="mdp_saisir" type="radio" name="mdp_mode" value="saisir" checked onchange="mdp_changer_mode('saisir')">
                    <label class="form-check-label" for="mdp_saisir">Saisir</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input me-2" id="mdp_generer" type="radio" name="mdp_mode" value="generer" onchange="mdp_changer_mode('generer')">
                    <label class="form-check-label" for="mdp_generer" title="Générer automatiquement">Générer</label>
                  </div>
                </div>
              </div>

              <div class="mt-2 hidden" id="mdp_actions">
                <button class="btn btn-primary text-white btn-sm" type="button" onclick="mdp_regenerer()"><i class="fa-solid fa-rotate"></i> Régénérer</button>
                <button class="btn btn-primary text-white btn-sm" id="btn_copier_mdp" type="button" onclick="mdp_copier()"><i class="fa-regular fa-copy"></i> Copier</button>
              </div>
            </div>

            <div class="col-span-12 mt-2">
              <label class="form-label" for="profil_id"><b>Profil</b></label>
              <select class="form-select form-select-sm" id="profil_id" name="profil_id" required>
                <option value="" selected disabled>Sélectionnez un profil</option>
                @foreach ($profils as $profil)
                  <option value="{{ $profil->id }}">{{ $profil->nom }}@if ($profil->description) — {{ \Illuminate\Support\Str::limit($profil->description, 60) }}@endif</option>
                @endforeach
              </select>
            </div>
            <div class="col-span-12 mt-2">
              <label class="form-label" for="actif"><b>Statut</b></label>
              <select class="form-select form-select-sm" id="actif" name="actif" required>
                <option value="1" selected>Actif</option>
                <option value="0">Inactif</option>
              </select>
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
