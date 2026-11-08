{{-- Ajout ou modification d'un scellé. Variable : $affaire --}}
<div class="modal fade panel-modal" id="modal-scelle" tabindex="-1" role="dialog" aria-labelledby="titre-modal-scelle" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog" style="width:50%">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-scelle">Scellé</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <form id="form-scelle" action="{{ route('affaires.scelles.store', $affaire->id) }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="">
        <div class="modal-body dark-modal">
          <div class="el-formgrid">
            <div class="el-c4">
              <label class="form-label" for="f_scelle_numero"><b>Numéro du scellé</b></label>
              <input class="form-control" id="f_scelle_numero" name="numero" type="text" maxlength="40" required placeholder="Ex. : S1" value="{{ old('numero') }}">
              @if ($errors->scelle->has('numero')) <div class="aff-erreur">{{ $errors->scelle->first('numero') }}</div> @endif
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_scelle_nature"><b>Nature</b></label>
              <select class="form-select" id="f_scelle_nature" name="nature" required data-sans-recherche>
                @foreach (\App\Models\Scelle::NATURES as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach
              </select>
            </div>
            <div class="el-c4">
              <label class="form-label" for="f_scelle_saisie"><b>Saisi le</b></label>
              <input class="form-control" id="f_scelle_saisie" name="date_saisie" type="date" max="{{ date('Y-m-d') }}">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_scelle_designation"><b>Désignation</b></label>
              <input class="form-control" id="f_scelle_designation" name="designation" type="text" maxlength="250" required placeholder="Ex. : Un pistolet automatique et deux chargeurs">
            </div>
            <div class="el-c6">
              <label class="form-label" for="f_scelle_lieu"><b>Lieu de conservation</b></label>
              <input class="form-control" id="f_scelle_lieu" name="lieu_conservation" type="text" maxlength="160" placeholder="Ex. : Greffe, armoire 2">
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_scelle_etat"><b>État</b></label>
              <select class="form-select" id="f_scelle_etat" name="etat" required data-sans-recherche>
                @foreach (\App\Models\Scelle::ETATS as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach
              </select>
            </div>
            <div class="el-c3">
              <label class="form-label" for="f_scelle_date_etat"><b>Date de l'état</b></label>
              <input class="form-control" id="f_scelle_date_etat" name="date_etat" type="date" max="{{ date('Y-m-d') }}">
            </div>
            <div class="el-c12">
              <label class="form-label" for="f_scelle_obs"><b>Observations</b> <span class="el-muted">(facultatif)</span></label>
              <input class="form-control" id="f_scelle_obs" name="observations" type="text" maxlength="500" placeholder="Ex. : restitué au propriétaire sur ordonnance du juge d'instruction">
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
<script type="text/javascript">
	function ouvrir_scelle(s) {
		const f = $('#form-scelle')[0]; f.reset();
		$('[name="id_"]', f).val(s ? s.id : '');
		$('#titre-modal-scelle').text(s ? 'Modifier : scellé ' + s.numero : 'Nouveau scellé');
		const valeurs = s || { nature: 'autre', etat: 'conserve' };
		['numero', 'designation', 'lieu_conservation', 'observations', 'date_saisie', 'date_etat'].forEach(function (c) { $('[name="' + c + '"]', f).val(valeurs[c] || ''); });
		$('#f_scelle_nature').val(valeurs.nature).trigger('change.select2'); $('#f_scelle_etat').val(valeurs.etat).trigger('change.select2');
		$('#modal-scelle').modal('show');
	}

	function supprimer_scelle(id) {
		Swal.fire({ title: @js(config('app.name')), text: "Supprimer ce scellé ? Préférez changer son état (restitué, détruit…) pour garder la trace.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, supprimer' }).then(function (r) {
			if (!r.isConfirmed) { return; }
			$.ajax({ url: "{{ route('affaires.scelles.delete', ':id') }}".replace(':id', id), type: 'DELETE', success: function (res) {
				if (res.status) { $('#scelle-' + id).fadeOut(400, function () { $(this).remove(); }); } else { Swal.fire({ icon: 'error', title: 'Erreur', text: res.message }); }
			}, error: function () { Swal.fire({ icon: 'error', title: 'Erreur', text: "Une erreur s'est produite lors de la suppression." }); } });
		});
	}

	@if ($errors->scelle->any())
		document.addEventListener('DOMContentLoaded', function () { afficher_onglet('scelles'); $('#modal-scelle').modal('show'); });
	@endif
</script>
