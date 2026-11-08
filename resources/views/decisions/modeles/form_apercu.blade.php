{{-- Aperçu : le modèle rempli avec des données fictives, sur des pages A4 comme dans le document Word (réglages « Impression des actes ») --}}
<div class="modal fade panel-modal" id="modal-apercu" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:70%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">Aperçu avec des données fictives</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body dark-modal" style="max-height:80vh; overflow:auto">
        <div id="apercu-alertes"></div>
        <div class="acte-barre">
          <label style="margin:0"><input type="checkbox" id="apercu-couleurs" onchange="dessiner_apercu()"> Couleurs de travail (qualité en rouge, factum en bleu, comme dans les documents Word)</label>
          <span class="acte-zoom" title="Zoom (Ctrl + molette)">
            <button type="button" onclick="zoom_apercu(-1)" aria-label="Réduire">&minus;</button>
            <output id="apercu-zoom">100 %</output>
            <button type="button" onclick="zoom_apercu(1)" aria-label="Agrandir">+</button>
            <button type="button" class="large" onclick="zoom_apercu(0)">Largeur</button>
            <button type="button" class="large" onclick="zoom_apercu(100)">100 %</button>
          </span>
          <span class="el-muted">Feuille A4 ; police, marges et retrait réglés dans Paramètres, Réglages, « Impression des actes ».</span>
        </div>
        <div id="apercu-feuilles"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>
