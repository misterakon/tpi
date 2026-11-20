{{-- Écran d'avancement du chargement : superposé à la page, rempli par public/assets/js/import-ordonnance.js. Hors de #io-racine : il survit aux changements de page. --}}
<div class="io-chargeur" id="io-chargeur" hidden role="dialog" aria-modal="true" aria-labelledby="io-ch-titre" aria-live="polite">
  <div class="io-chargeur__boite">
    <div class="io-chargeur__tete">
      <span class="io-spinner" aria-hidden="true"></span>
      <div>
        <h3 id="io-ch-titre">Chargement</h3>
        <p id="io-ch-sous"></p>
      </div>
    </div>
    <div class="io-barre" id="io-ch-barre-zone">
      <div class="io-barre__piste"><div class="io-barre__rempli" id="io-ch-barre" style="width:0%"></div></div>
      <span class="io-barre__pct" id="io-ch-pct">0 %</span>
    </div>
    <ol class="io-ch-etapes" id="io-ch-etapes"></ol>
    <p class="io-chargeur__note"><i class="fa-solid fa-shield-halved"></i> Rien n'est modifié dans l'organisation à cette étape.</p>
  </div>
</div>
