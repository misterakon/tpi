{{-- Confier une affaire civile à la mise en état, ou corriger une mise en état ouverte. Variables : $eligibles, $juges --}}
<div class="modal fade panel-modal" id="modal-mee" tabindex="-1" role="dialog" aria-labelledby="titre-modal-mee" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-mee">Mise en état</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-mee" action="{{ route('affaires.mise_en_etat.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>Seules les affaires civiles (registre RG) passent en mise en état. Tant qu'elle est en mise en état, l'affaire ne figure sur aucun rôle de jugement : elle y est versée à la clôture, avec la date de l'audience de jugement.</span></div>
          <div class="el-formgrid" style="margin-top:14px">
            <div class="el-c12" id="bloc-mee-affaire">
              <label class="form-label" for="f_mee_affaire"><b>Affaire</b></label>
              <select class="form-select" id="f_mee_affaire" name="affaire_id" required>
                <option value="">Choisissez l'affaire…</option>
                @foreach ($eligibles as $a)
                  <option value="{{ $a->id }}" data-chambre="{{ $a->chambre_id }}">{{ $a->numeroAffichage() }} · {{ $a->chambre?->libelle }} · {{ \Illuminate\Support\Str::limit(\App\Support\Audiences\SuiviAffaires::parties($a), 60) }}</option>
                @endforeach
              </select>
              @error('affaire_id') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12" id="bloc-mee-numero" hidden>
              <label class="form-label"><b>Affaire</b></label>
              <input class="form-control" id="f_mee_numero" type="text" disabled>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_mee_reception"><b>Dossier reçu le</b></label>
              <input class="form-control" id="f_mee_reception" name="date_reception" type="date" max="{{ date('Y-m-d') }}" required value="{{ date('Y-m-d') }}">
              @error('date_reception') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_mee_audience"><b>Première audience</b> <span class="el-muted">(si fixée)</span></label>
              <input class="form-control" id="f_mee_audience" name="date_premiere_audience" type="date">
              @error('date_premiere_audience') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_mee_juge"><b>Juge chargé</b></label>
              <select class="form-select" id="f_mee_juge" name="juge_id" data-sans-recherche>
                <option value="">À désigner</option>
              </select>
              @error('juge_id') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_mee_obs"><b>Observations</b> <span class="el-muted">(facultatif)</span></label>
              <textarea class="form-control" id="f_mee_obs" name="observations" rows="2" maxlength="1000"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white" type="submit">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
