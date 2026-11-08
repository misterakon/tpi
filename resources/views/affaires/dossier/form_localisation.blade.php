{{-- Où se trouve le dossier papier. Variable : $affaire --}}
<div class="modal fade panel-modal" id="modal-localisation" tabindex="-1" role="dialog" aria-labelledby="titre-modal-localisation" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-localisation">Dossier papier de {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form action="{{ route('affaires.dossier.localisation', $affaire->id) }}" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_localisation"><b>Emplacement</b></label>
              <input class="form-control" id="f_localisation" name="localisation" type="text" maxlength="200" value="{{ $affaire->localisation_dossier }}" placeholder="Ex. : Greffe, armoire B, rayon 3, carton 12">
              <small class="text-muted">Laissez vide si le dossier papier n'existe pas ou n'est plus suivi.</small>
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
