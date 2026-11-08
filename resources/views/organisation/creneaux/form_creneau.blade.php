{{-- Fenêtre de saisie d'un jour et d'une heure d'audience ; incluse dans la page « Composition » d'une chambre (variables : $salles, $ordonnances). --}}
<div class="modal fade panel-modal" id="modal-creneau" tabindex="-1" role="dialog" aria-labelledby="titre-modal-creneau" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:50%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-creneau">Audience</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form_creneau" id="form-creneau" action="{{ route('organisation.creneaux.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">
        <input type="hidden" name="formation_id" value="">

        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="c_type"><b>Nature de l'audience</b></label>
              <input class="form-control" id="c_type" name="type" type="text" maxlength="160" list="types-audience" placeholder="Ex. : Audience publique" required>
              <datalist id="types-audience">
                <option value="Audience publique">
                <option value="Conciliation">
                <option value="Réception des requêtes">
                <option value="Audience de référé">
                <option value="Audience de mise en état">
                <option value="Session">
              </datalist>
            </div>
            <div class="el-c4">
              <label class="form-label" for="c_frequence"><b>Fréquence</b></label>
              <select class="form-select" id="c_frequence" name="frequence" required data-sans-recherche>
                @foreach (\App\Models\Creneau::FREQUENCES as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c4" id="bloc-jour">
              <label class="form-label" for="c_jour"><b>Jour</b></label>
              <select class="form-select" id="c_jour" name="jour" data-sans-recherche>
                <option value="">—</option>
                @foreach (\App\Models\Creneau::JOURS as $valeur => $texte)
                  <option value="{{ $valeur }}">{{ $texte }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c4">
              <label class="form-label" for="c_heure"><b>Heure</b></label>
              <input class="form-control" id="c_heure" name="heure" type="time">
            </div>
            <div class="el-c12">
              <label class="form-label" for="c_salle_id"><b>Salle</b> <span class="text-muted">(facultatif)</span></label>
              <select class="form-select" id="c_salle_id" name="salle_id">
                <option value="">Aucune</option>
                @foreach ($salles as $salle)
                  <option value="{{ $salle->id }}">{{ $salle->nom }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c12">
              <label class="form-label" for="c_precision"><b>Précision</b> <span class="text-muted">(facultatif : ex. après l'audience de la formation A)</span></label>
              <input class="form-control" id="c_precision" name="precision" type="text" maxlength="200">
            </div>
            <div class="el-c4">
              <label class="form-label" for="c_debut"><b id="lib-debut">En vigueur du</b> <span class="text-muted" id="fac-debut">(facultatif)</span></label>
              <input class="form-control" id="c_debut" name="debut" type="date">
            </div>
            <div class="el-c4" id="bloc-fin">
              <label class="form-label" for="c_fin"><b>Jusqu'au</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="c_fin" name="fin" type="date">
            </div>
            <div class="el-c4">
              <label class="form-label" for="c_ordonnance_id"><b>Ordonnance</b> <span class="text-muted">(facultatif)</span></label>
              <select class="form-select" id="c_ordonnance_id" name="ordonnance_id">
                <option value="">Aucune</option>
                @foreach ($ordonnances as $o)
                  <option value="{{ $o->id }}">{{ $o->libelle() }}</option>
                @endforeach
              </select>
            </div>
            <div class="el-c12">
              <small class="text-muted">En cas de changement d'horaire en cours d'année : renseignez la date « Jusqu'au » de l'ancienne audience, puis ajoutez la nouvelle avec sa date « En vigueur du ». L'historique est conservé.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white submit" id="BtnCreneau" type="submit">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
