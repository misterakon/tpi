{{-- Jonction des causes : joindre une affaire de la même chambre à celle-ci (variables : $affaire, $joignables) --}}
<div class="modal fade panel-modal" id="modal-joindre" tabindex="-1" role="dialog" aria-labelledby="titre-modal-joindre" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-joindre">Joindre une affaire à {{ $affaire->numeroAffichage() }}</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form action="{{ route('affaires.liste.joindre', $affaire->id) }}" method="post" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="red-bandeau">Les deux affaires gardent leur numéro ; elles seront jugées ensemble et figureront sur une même ligne du rôle. Seules les affaires de la même chambre sont proposées.</div>
          <div class="el-formgrid" style="margin-top:14px">
            <div class="el-c12">
              <label class="form-label" for="f_affaire_a_joindre"><b>Affaire à joindre</b></label>
              <select class="form-select" id="f_affaire_a_joindre" name="affaire_id" required>
                <option value="">Choisir…</option>
                @foreach ($joignables as $j)
                  <option value="{{ $j->id }}">{{ $j->numeroAffichage() }} — {{ \Illuminate\Support\Str::limit($j->intitule(), 70) }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button>
          <button class="btn btn-primary text-white" type="submit">Joindre</button>
        </div>
      </form>
    </div>
  </div>
</div>
