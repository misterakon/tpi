{{-- Réattribution à une autre chambre (la Présidente) : motif obligatoire, trace conservée (variables : $affaire, $chambres) --}}
<div class="modal fade panel-modal" id="modal-reattribuer" tabindex="-1" role="dialog" aria-labelledby="titre-modal-reattribuer" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-reattribuer">Réattribuer {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form action="{{ route('affaires.liste.reattribuer', $affaire->id) }}" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_chambre_reattribution"><b>Nouvelle chambre</b></label>
              <select class="form-select" id="f_chambre_reattribution" name="chambre_id" required>
                <option value="">Choisir…</option>
                @foreach ($chambres as $c)
                  <option value="{{ $c->id }}" @disabled($c->id === $affaire->chambre_id)>{{ $c->libelle }}{{ $c->intitule ? ' – '.$c->intitule : '' }}{{ $c->id === $affaire->chambre_id ? ' (chambre actuelle)' : '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_motif_reattribution"><b>Motif</b></label>
              <textarea class="form-control" id="f_motif_reattribution" name="motif" rows="3" maxlength="300" required placeholder="Ex. : affaire commerciale, de la compétence de la chambre commerciale"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-primary text-white" type="submit">Réattribuer</button>
        </div>
      </form>
    </div>
  </div>
</div>
