{{-- Ajout de pièces au dossier : un type, un titre et une date par fichier. Variables : $affaire, $dossier --}}
<div class="modal fade panel-modal" id="modal-pieces" tabindex="-1" role="dialog" aria-labelledby="titre-modal-pieces" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog dn-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-pieces">Ajouter des pièces au dossier {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-pieces" action="{{ route('affaires.dossier.ajouter', $affaire->id) }}" method="post" enctype="multipart/form-data" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>PDF, JPG, PNG ou WebP, {{ $dossier['taille_max'] }} au plus par fichier, vingt fichiers à la fois. Le contenu est vérifié : un fichier renommé n'est pas accepté. Un fichier déjà présent dans le dossier est ignoré.</span></div>

          <label class="dn-choix" for="dn-fichiers">
            <i class="fa-solid fa-file-circle-plus"></i><span><b>Choisir des fichiers</b> sur cet ordinateur</span>
          </label>
          <input type="file" id="dn-fichiers" name="fichiers[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" class="dn-fichiers-input" required>
          @error('fichiers') <div class="aff-erreur">{{ $message }}</div> @enderror
          @error('fichiers.*') <div class="aff-erreur">{{ $message }}</div> @enderror

          <div class="dn-apercu" id="dn-apercu" hidden><span class="dn-apercu__nom" id="dn-apercu-nom"></span></div>
          <div class="dn-lignes" id="dn-lignes"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit" id="dn-envoyer" disabled><i class="fa-solid fa-cloud-arrow-up"></i> Ajouter au dossier</button>
        </div>
      </form>
    </div>
  </div>
</div>
