{{-- Retouche à la main du texte généré : gras, italique, lignes, alignement… (variables : $decision) --}}
<div class="modal fade panel-modal" id="modal-retouche" tabindex="-1" role="dialog" aria-labelledby="titre-modal-retouche" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog modal-retouche">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-retouche">Retoucher le texte de la décision</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <div class="modal-body dark-modal">
        <div class="red-bandeau">
          Modifiez seulement ce qui doit l'être : gras, italique, lignes vides, alignement, mots. Une partie retouchée <b>ne suit plus le formulaire</b> :
          si vous changez ensuite un nom ou une date, corrigez-le aussi ici, ou cliquez sur « Rétablir le texte généré ».
        </div>

        @foreach (\App\Support\Redaction\RenduDecision::PARTIES as $code => $titre)
          <div class="red-retouche">
            <div class="red-retouche__tete">
              <label class="form-label" for="r_{{ $code }}"><b>{{ $titre }}</b> <span class="red-retouche__etat" id="r_{{ $code }}_etat"></span></label>
              <button type="button" class="el-btn el-btn--ghost red-retouche__retablir" onclick="retablir_partie('{{ $code }}')"><i class="fa-solid fa-rotate-left"></i> Rétablir le texte généré</button>
            </div>
            <textarea class="form-control" id="r_{{ $code }}" data-partie="{{ $code }}" data-riche data-alignement data-hauteur="{{ $code === 'entete' ? 200 : 300 }}"></textarea>
          </div>
        @endforeach
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
        <button class="btn btn-primary text-white" type="button" onclick="enregistrer_retouche()">Enregistrer la retouche</button>
      </div>
    </div>
  </div>
</div>
