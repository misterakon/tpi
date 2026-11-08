{{-- Fenêtres du répertoire pénal : décision (création et correction), transmission de la minute. Variables : $affaires, $prevenus --}}
<div class="modal fade panel-modal" id="modal-decision" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal"><div class="modal-content">
    <div class="modal-header"><h4 class="modal-title" id="t-decision">Enregistrer une décision</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
    <form method="post" action="{{ route('decisions.repertoire_penal.store') }}" id="f-decision" autocomplete="off">
      @csrf <input type="hidden" name="id_" id="d_id">
      <div class="modal-body dark-modal"><div class="el-formgrid">
        <div class="el-c8"><label class="form-label"><b>Affaire pénale</b></label>
          <select class="form-select" name="affaire_id" id="d_affaire" required><option value="">Choisir…</option>
            @foreach ($affaires as $a)<option value="{{ $a->id }}">{{ $a->numeroAffichage() }} : {{ \Illuminate\Support\Str::limit(\App\Support\Audiences\SuiviAffaires::parties($a), 50) }}</option>@endforeach
          </select></div>
        <div class="el-c4"><label class="form-label"><b>Date de la décision</b></label><input class="form-control" type="date" name="date_decision" id="d_date" max="{{ date('Y-m-d') }}" required></div>
        <div class="el-c8"><label class="form-label"><b>Prévenu concerné</b></label><select class="form-select" name="partie_id" id="d_partie"><option value="">Toute l'affaire</option></select></div>
        <div class="el-c4"><label class="form-label"><b>N° au répertoire</b></label><input class="form-control" type="text" name="numero_decision" id="d_numero" maxlength="40"></div>
        <div class="el-c6"><label class="form-label"><b>Issue</b></label><select class="form-select" name="issue" id="d_issue" data-sans-recherche>@foreach (\App\Models\DecisionPenale::ISSUES as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
        <div class="el-c6"><label class="form-label"><b>Décision</b></label><select class="form-select" name="contradiction" id="d_contradiction" data-sans-recherche>@foreach (\App\Models\DecisionPenale::CONTRADICTIONS as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
        <div class="el-c4"><label class="form-label"><b>Emprisonnement</b> <span class="el-muted">(mois)</span></label><input class="form-control" type="number" min="1" max="1200" name="emprisonnement_mois" id="d_empr"></div>
        <div class="el-c4"><label class="form-label"><b>dont sursis</b> <span class="el-muted">(mois)</span></label><input class="form-control" type="number" min="1" max="1200" name="sursis_mois" id="d_sursis"></div>
        <div class="el-c4"><label class="form-label"><b>Amende</b> <span class="el-muted">(F CFA)</span></label><input class="form-control" type="number" min="1" name="amende" id="d_amende"></div>
        <div class="el-c12"><label class="form-label"><b>Autres peines</b></label><input class="form-control" type="text" name="autres_peines" id="d_autres" maxlength="300" placeholder="Ex. : suspension du permis de conduire, confiscation"></div>
        <div class="el-c8"><label class="form-label"><b>Infraction retenue par le tribunal</b></label><input class="form-control" type="text" name="infraction_retenue" id="d_infraction" maxlength="200" list="liste-infractions" placeholder="Choisir ou saisir"><datalist id="liste-infractions">@foreach ($infractions as $i)<option value="{{ $i }}">@endforeach</datalist></div>
        <div class="el-c4"><label class="form-label"><b>Nature</b></label><select class="form-select" name="nature_infraction" id="d_nature" data-sans-recherche><option value="">Déduite de l'affaire</option><option value="crime">Crime</option><option value="delit">Délit</option><option value="contravention">Contravention</option></select></div>
        <div class="el-c12"><label class="aff-interrupteur" for="d_traite"><input type="checkbox" id="d_traite" name="traite" value="1"><span class="aff-interrupteur__piste" aria-hidden="true"></span><span class="aff-interrupteur__texte"><b>Traite des personnes</b></span></label></div>
        <div class="el-c3"><label class="form-label"><b>Victimes hommes</b></label><input class="form-control" type="number" min="0" max="500" name="victimes_hommes" id="d_vh"></div>
        <div class="el-c3"><label class="form-label"><b>Victimes femmes</b></label><input class="form-control" type="number" min="0" max="500" name="victimes_femmes" id="d_vf"></div>
        <div class="el-c3"><label class="form-label"><b>Garçons mineurs</b></label><input class="form-control" type="number" min="0" max="500" name="victimes_garcons" id="d_vg"></div>
        <div class="el-c3"><label class="form-label"><b>Filles mineures</b></label><input class="form-control" type="number" min="0" max="500" name="victimes_filles" id="d_vm"></div>
        <div class="el-c12"><label class="form-label"><b>Dispositif</b> <span class="el-muted">(facultatif)</span></label><textarea class="form-control" name="dispositif" id="d_dispositif" rows="3" maxlength="4000"></textarea></div>
      </div></div>
      <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">Enregistrer</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade panel-modal" id="modal-minute" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal"><div class="modal-content">
    <div class="modal-header"><h4 class="modal-title">Transmission de la minute</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
    <form method="post" id="f-minute" autocomplete="off">
      @csrf
      <div class="modal-body dark-modal"><div class="el-formgrid">
        <div class="el-c5"><label class="form-label"><b>Date de transmission</b></label><input class="form-control" type="date" name="date_transmission_minute" max="{{ date('Y-m-d') }}" required value="{{ date('Y-m-d') }}"></div>
        <div class="el-c7"><label class="form-label"><b>Transmise à</b></label><input class="form-control" type="text" name="minute_transmise_a" maxlength="120" placeholder="Ex. : Parquet, greffe central"></div>
      </div></div>
      <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">Enregistrer</button></div>
    </form>
  </div></div>
</div>

<script type="text/javascript">
	const PREVENUS = @js($prevenus);
	function remplir_prevenus(affaire, choisi) {
		const $s = $('#d_partie').empty().append('<option value="">Toute l\'affaire</option>');
		(PREVENUS[affaire] || []).forEach(function (p) { $s.append($('<option>').val(p.id).text(p.nom)); });
		$s.val(choisi ? String(choisi) : '').trigger('change.select2');
	}
	document.addEventListener('DOMContentLoaded', function () { $('#d_affaire').on('change', function () { remplir_prevenus($(this).val(), null); }); });

	function ouvrir_decision(id) {
		$('#f-decision')[0].reset(); $('#d_id').val('');
		const montrer = function (d) {
			$('#t-decision').text(d ? 'Corriger la décision' : 'Enregistrer une décision');
			$('#d_affaire').prop('disabled', !!d);
			if (d) {
				$('#d_id').val(d.id); $('#d_affaire').val(String(d.affaire_id)).trigger('change.select2'); remplir_prevenus(d.affaire_id, d.partie_id);
				$('#d_date').val(d.date_decision); $('#d_numero').val(d.numero_decision || ''); $('#d_issue').val(d.issue).trigger('change.select2'); $('#d_contradiction').val(d.contradiction).trigger('change.select2');
				$('#d_empr').val(d.emprisonnement_mois || ''); $('#d_sursis').val(d.sursis_mois || ''); $('#d_amende').val(d.amende || ''); $('#d_autres').val(d.autres_peines || ''); $('#d_infraction').val(d.infraction_retenue || ''); $('#d_nature').val(d.nature_infraction || '').trigger('change.select2'); $('#d_traite').prop('checked', !!d.traite); $('#d_vh').val(d.victimes_hommes == null ? '' : d.victimes_hommes); $('#d_vf').val(d.victimes_femmes == null ? '' : d.victimes_femmes); $('#d_vg').val(d.victimes_garcons == null ? '' : d.victimes_garcons); $('#d_vm').val(d.victimes_filles == null ? '' : d.victimes_filles); $('#d_dispositif').val(d.dispositif || '');
			} else { $('#d_affaire').val('').trigger('change.select2'); remplir_prevenus(null, null); $('#d_issue').val('condamne').trigger('change.select2'); }
			$('#modal-decision').modal('show');
		};
		if (id === null) { montrer(null); return; }
		$.getJSON("{{ route('decisions.repertoire_penal.get', ':id') }}".replace(':id', id), montrer).fail(function () { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur lors du chargement des données.' }); });
	}
	/* Le champ « affaire » est désactivé en correction : on le réactive à l'envoi pour qu'il parte avec le formulaire. */
	$('#f-decision').on('submit', function () { $('#d_affaire').prop('disabled', false); });

	function ouvrir_minute(id) { $('#f-minute').attr('action', "{{ route('decisions.repertoire_penal.minute', ':id') }}".replace(':id', id)); $('#modal-minute').modal('show'); }
	function supprimer_decision(id) {
		Swal.fire({ icon: 'warning', title: 'Supprimer cette décision du répertoire ?', showCancelButton: true, confirmButtonText: 'Oui, supprimer', cancelButtonText: 'Retour' }).then(function (r) {
			if (!r.isConfirmed) { return; }
			$.ajax({ url: "{{ route('decisions.repertoire_penal.delete', ':id') }}".replace(':id', id), type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, dataType: 'JSON',
				success: function (d) { Swal.fire({ icon: d.status ? 'success' : 'error', title: d.message }).then(function () { location.reload(); }); },
				error: function () { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Suppression impossible.' }); } });
		});
	}
</script>
