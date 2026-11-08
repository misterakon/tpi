{{-- Choix du registre avant la saisie : civil, pénal ou instruction --}}
<div class="modal fade panel-modal" id="modal-choix" tabindex="-1" role="dialog" aria-labelledby="titre-modal-choix" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-choix">Quelle affaire saisir ?</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body dark-modal">
        <div class="aff-choix-liste">
          <button type="button" onclick="choisir_registre('civil')"><i class="fa-solid fa-scale-balanced"></i><span><b>Affaire civile</b><small>Série RG : demandeurs et défendeurs</small></span></button>
          <button type="button" onclick="choisir_registre('penal')"><i class="fa-solid fa-handcuffs"></i><span><b>Affaire pénale</b><small>Série RP : prévenus</small></span></button>
          <button type="button" onclick="choisir_registre('instruction')"><i class="fa-solid fa-magnifying-glass"></i><span><b>Instruction</b><small>Séries RI (adultes) et RE (enfants) : inculpés</small></span></button>
        </div>
      </div>
    </div>
  </div>
</div>
