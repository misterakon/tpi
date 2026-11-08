{{-- Enregistrement ou correction d'une requête (dont la requête d'hérédité) --}}
<div class="modal fade panel-modal" id="modal-requete" tabindex="-1" role="dialog" aria-labelledby="titre-modal-requete" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:60%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-requete">Requête</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-requete" action="{{ route('affaires.requetes.store') }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">
        <div class="modal-body dark-modal">
          <div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>Une requête n'a pas de défendeur : on enregistre ceux qui la présentent (les requérants). Elle reçoit un numéro RQ, puis le secrétariat de la Présidente la répartit entre les formations.</span></div>
          <div class="el-formgrid" style="margin-top:14px">
            <div class="el-c8">
              <label class="form-label" for="f_req_type"><b>Type de requête</b></label>
              <select class="form-select" id="f_req_type" name="type" required>
                @foreach (\App\Models\Requete::TYPES as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach
              </select>
              @error('type') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_req_date"><b>Déposée le</b></label>
              <input class="form-control" id="f_req_date" name="date_depot" type="date" max="{{ date('Y-m-d') }}" required value="{{ date('Y-m-d') }}">
              @error('date_depot') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_req_requerants"><b>Requérants</b> <span class="el-muted">(un par ligne)</span></label>
              <textarea class="form-control" id="f_req_requerants" name="requerants" rows="3" maxlength="1000" required placeholder="Ex. : KOUASSI Yao Marc, fils du défunt&#10;KOUASSI Adjoua, épouse du défunt"></textarea>
              @error('requerants') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_req_objet"><b>Objet</b></label>
              <textarea class="form-control" id="f_req_objet" name="objet" rows="2" maxlength="1000" required placeholder="Ex. : Établissement de la qualité d'héritiers de feu KOUASSI Jean"></textarea>
              @error('objet') <div class="aff-erreur">{{ $message }}</div> @enderror
            </div>
            <div class="el-c12" id="bloc-defunt">
              <div class="aff-info"><i class="fa-solid fa-feather"></i><span>Hérédité : le défunt dont on établit la succession.</span></div>
              <div class="el-formgrid" style="margin-top:10px">
                <div class="el-c6">
                  <label class="form-label" for="f_req_defunt"><b>Nom et prénoms du défunt</b></label>
                  <input class="form-control" id="f_req_defunt" name="defunt_nom" type="text" maxlength="200" placeholder="Ex. : KOUASSI Jean Baptiste">
                  @error('defunt_nom') <div class="aff-erreur">{{ $message }}</div> @enderror
                </div>
                <div class="el-c3">
                  <label class="form-label" for="f_req_deces"><b>Décédé le</b></label>
                  <input class="form-control" id="f_req_deces" name="defunt_date_deces" type="date" max="{{ date('Y-m-d') }}">
                </div>
                <div class="el-c3">
                  <label class="form-label" for="f_req_lieu"><b>Lieu du décès</b></label>
                  <input class="form-control" id="f_req_lieu" name="defunt_lieu_deces" type="text" maxlength="160">
                </div>
              </div>
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
