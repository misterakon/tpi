{{-- Règle d'alerte : le moment (décalage en jours), les destinataires, les canaux, les types d'échéance visés. Variables : $destinataires, $canaux, $niveaux, $types --}}
<div class="modal fade panel-modal" id="modal-regle" tabindex="-1" role="dialog" aria-labelledby="titre-modal-regle" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="max-width:1000px;width:94%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-regle">Nouvelle règle d'alerte</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form id="form-regle" autocomplete="off">
        <div class="modal-body dark-modal" style="max-height:68vh;overflow-y:auto">
          <div class="el-formgrid" style="align-items:start">
            <div class="el-c4">
              <label class="form-label" for="f_regle_decalage"><b>Décalage (jours)</b></label>
              <input class="form-control" id="f_regle_decalage" name="decalage_jours" type="number" min="-365" max="365" step="1" required>
              <small class="text-muted">Négatif : avant l'échéance (-3). Positif : après (+7). 0 : le jour même.</small>
            </div>
            <div class="el-c8">
              <label class="form-label" for="f_regle_libelle"><b>Libellé</b></label>
              <input class="form-control" id="f_regle_libelle" name="libelle" type="text" maxlength="80" placeholder="Ex. : Retard persistant" required>
              <small class="text-muted" id="apercu-code">Le code se déduit du décalage (J-3, J0, J+7).</small>
            </div>

            <div class="el-c6">
              <label class="form-label" for="f_regle_niveau"><b>Gravité</b></label>
              <select class="form-select" id="f_regle_niveau" name="niveau" data-sans-recherche>
                @foreach ($niveaux as $cle => $libelle)<option value="{{ $cle }}" @selected($cle === 'attention')>{{ $libelle }}</option>@endforeach
              </select>
            </div>
            <div class="el-c6">
              <label class="form-label"><b>Canaux</b></label>
              <div class="al-cases">
                @foreach ($canaux as $cle => $libelle)
                  <label class="al-case"><input type="checkbox" name="canaux[]" value="{{ $cle }}"><span>{{ $libelle }}</span></label>
                @endforeach
              </div>
            </div>

            <div class="el-c12">
              <label class="form-label"><b>Qui est prévenu</b></label>
              <div class="al-cases al-cases--grille">
                @foreach ($destinataires as $cle => $libelle)
                  <label class="al-case"><input type="checkbox" name="destinataires[]" value="{{ $cle }}"><span>{{ $libelle }}</span></label>
                @endforeach
              </div>
              <small class="text-muted">Plus le retard est grand, plus on monte : greffier et président de la formation d'abord, puis vice-présidents, Présidente et coordonnateur du greffe. Cochez « Administrateurs » pour les alertes graves : ils sont aussi prévenus, quelle que soit la règle, quand le destinataire désigné n'a pas de compte.</small>
            </div>

            <div class="el-c12">
              <label class="form-label"><b>Types d'échéance visés</b></label>
              <div class="al-cases al-cases--grille">
                @foreach ($types as $cle => $libelle)
                  <label class="al-case"><input type="checkbox" name="types[]" value="{{ $cle }}"><span>{{ $libelle }}</span></label>
                @endforeach
              </div>
              <small class="text-muted">Ne cochez rien pour viser tous les types d'échéance.</small>
            </div>

            <div class="el-c12">
              <label class="al-case al-case--interrupteur"><input type="checkbox" name="actif" value="1" id="f_regle_actif" checked><span><b>Règle active</b> : une règle inactive est conservée mais n'envoie plus rien.</span></label>
            </div>
            <div class="el-c12" id="erreur-regle"></div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" id="valider-regle" type="submit">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
