{{-- Réouverture d'un rôle publié : le motif est obligatoire, la version suivante est ouverte (droits « roles.modifier » et « roles.publier »). Variable : $role --}}
<div class="modal fade panel-modal" id="modal-rouvrir" tabindex="-1" role="dialog" aria-labelledby="titre-modal-rouvrir" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-rouvrir">Rouvrir le rôle</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-rouvrir" method="post" action="{{ route('audiences.roles.rouvrir', $role->id) }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12"><div class="rl-astuce"><i class="fa-solid fa-circle-info"></i><span>La version {{ $role->version }} reste conservée telle qu'elle a été publiée. Vos corrections formeront la version {{ $role->version + 1 }}.</span></div></div>
            <div class="el-c12">
              <label class="form-label" for="f_rou_motif"><b>Motif de la correction</b></label>
              <input class="form-control" id="f_rou_motif" name="motif" type="text" maxlength="300" required placeholder="Ex. : affaire 1391/25 ajoutée, erreur de numéro">
              @error('motif') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Rouvrir</button>
        </div>
      </form>
    </div>
  </div>
</div>
