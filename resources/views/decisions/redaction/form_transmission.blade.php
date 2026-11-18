{{-- Transmettre la décision signée au greffe : la date vaut date de délivrance ; copie Word facultative au greffier en chef. Variables : $decision, $adresseGreffierEnChef --}}
<div class="modal fade panel-modal" id="modal-transmission" tabindex="-1" role="dialog" aria-labelledby="titre-modal-transmission" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-transmission">Transmettre au greffe</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form method="post" action="{{ route('decisions.redaction.transmettre', $decision->id) }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_transm_le"><b>Date de transmission au greffe</b></label>
              <input class="form-control" id="f_transm_le" name="date_transmission" type="date" min="{{ $decision->date_signature?->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required>
              <small class="text-muted">Cette date vaut aussi date de délivrance au greffe.</small>
            </div>
            <div class="el-c12">
              <label class="aff-interrupteur" for="f_transm_copie">
                <input type="checkbox" id="f_transm_copie" name="envoyer_copie" value="1">
                <span class="aff-interrupteur__piste" aria-hidden="true"></span>
                <span class="aff-interrupteur__texte"><b>Envoyer une copie au greffier en chef</b> (Word, par e-mail)</span>
              </label>
              @if (! $adresseGreffierEnChef)
                <div class="aff-info" style="margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i><span>L'adresse e-mail du greffier en chef n'est pas encore réglée (Réglages &gt; Décisions) : la copie ne partirait pas.</span></div>
              @endif
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Transmettre</button>
        </div>
      </form>
    </div>
  </div>
</div>
