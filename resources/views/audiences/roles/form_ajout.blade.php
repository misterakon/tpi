{{-- Ajout d'une affaire au rôle par son numéro, ou d'une ligne vide à remplir (droit « roles.modifier »). Variable : $role --}}
<div class="modal fade panel-modal" id="modal-ajout" tabindex="-1" role="dialog" aria-labelledby="titre-modal-ajout" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-ajout">Ajouter une affaire</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-ajout" method="post" action="{{ route('audiences.roles.ligne.ajouter', $role->id) }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c12">
              <label class="form-label" for="f_aj_rubrique"><b>Rubrique</b></label>
              <select class="form-select" id="f_aj_rubrique" name="rubrique" data-sans-recherche>
                @foreach (\App\Models\RoleLigne::RUBRIQUES as $code => $lib)<option value="{{ $code }}">{{ $lib }}</option>@endforeach
              </select>
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_aj_numero"><b>Numéro de l'affaire</b></label>
              <input class="form-control" id="f_aj_numero" name="numero" type="text" maxlength="40" placeholder="Ex. : 1391/25" value="{{ old('numero') }}">
              @error('numero') <div class="aff-erreur">{{ $message }}</div> @enderror
              <small class="el-muted">Les parties, leurs avocats et l'objet sont repris de l'affaire. Laissez vide pour ajouter une ligne à remplir à la main.</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Ajouter</button>
        </div>
      </form>
    </div>
  </div>
</div>
