{{-- Modification d'une pièce : titre, type, date (l'adresse d'envoi est posée par le script selon la pièce ouverte) --}}
<div class="modal fade panel-modal" id="modal-piece" tabindex="-1" role="dialog" aria-labelledby="titre-modal-piece" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-piece">Modifier la pièce</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-piece" action="" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_piece_titre"><b>Titre</b></label>
              <input class="form-control" id="f_piece_titre" name="titre" type="text" maxlength="200" required>
            </div>
            <div class="el-c7">
              <label class="form-label" for="f_piece_type"><b>Type de pièce</b></label>
              <select class="form-select" id="f_piece_type" name="type" required>
                @foreach (\App\Models\PieceDossier::TYPES as $code => [$libelle])
                  <option value="{{ $code }}">{{ $libelle }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c5">
              <label class="form-label" for="f_piece_date"><b>Date de la pièce</b> <span class="el-muted">(facultatif)</span></label>
              <input class="form-control" id="f_piece_date" name="date_piece" type="date" max="{{ date('Y-m-d') }}">
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
