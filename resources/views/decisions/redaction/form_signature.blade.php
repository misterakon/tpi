{{-- Signer la décision : la date de signature ; le numéro est attribué ici ou à la validation selon les réglages. Variables : $decision, $momentNumero --}}
<div class="modal fade panel-modal" id="modal-signature" tabindex="-1" role="dialog" aria-labelledby="titre-modal-signature" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:520px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-signature">Signer la décision</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form method="post" action="{{ route('decisions.redaction.signer', $decision->id) }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_date_signature"><b>Date de signature</b></label>
              <input class="form-control" id="f_date_signature" name="date_signature" type="date" max="{{ today()->toDateString() }}" value="{{ old('date_signature', today()->toDateString()) }}" required>
              <small class="text-muted">Le jour où la décision est réellement signée (pas dans le futur).</small>
            </div>
            <div class="el-c12">
              <small class="text-muted">
                @if ($decision->numero)
                  La décision porte déjà le numéro <b>{{ $decision->numeroAffichage() }}</b>.
                @else
                  Un numéro est attribué automatiquement à la signature, sans trou ni doublon dans l'année (<b>{{ date('Y') }}</b> pour une décision de cette année). Il est inscrit dans le texte figé de la décision. Cette étape ne se défait pas.
                @endif
              </small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Signer la décision</button>
        </div>
      </form>
    </div>
  </div>
</div>
