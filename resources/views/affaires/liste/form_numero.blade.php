{{-- Correction du numéro reporté du registre du greffe (droit « affaires.corriger_numero »). Variable : $affaire --}}
<div class="modal fade panel-modal" id="modal-numero" tabindex="-1" role="dialog" aria-labelledby="titre-modal-numero" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-numero">Corriger le numéro {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form action="{{ route('affaires.liste.numero', $affaire->id) }}" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>À utiliser seulement pour une faute de frappe dans le numéro reporté du registre. L'ancien numéro, le motif et votre nom sont conservés dans l'historique. Un numéro déjà pris par une autre affaire est refusé.</span></div>
          <div class="el-formgrid" style="margin-top:14px">
            <div class="el-c5">
              <label class="form-label" for="f_nouveau_numero"><b>Bon numéro</b></label>
              <input class="form-control @error('numero_rg') is-invalid @enderror" id="f_nouveau_numero" name="numero_rg" type="text" inputmode="numeric" maxlength="9" required placeholder="Ex. : 1391/25" value="{{ old('numero_rg') }}">
              @error('numero_rg') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c7">
              <label class="form-label"><b>Numéro actuel</b></label>
              <input class="form-control" type="text" value="{{ $affaire->numeroAffichage() }}" disabled>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_motif_numero"><b>Motif de la correction</b></label>
              <textarea class="form-control @error('motif') is-invalid @enderror" id="f_motif_numero" name="motif" rows="2" maxlength="300" required placeholder="Ex. : chiffres inversés à la saisie (1931 au lieu de 1391)">{{ old('motif') }}</textarea>
              @error('motif') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-primary text-white" type="submit">Corriger le numéro</button>
        </div>
      </form>
    </div>
  </div>
</div>
