{{-- Liste des rôles : ouverture de la fenêtre de création, heure et salle d'après le calendrier de la chambre. Variable : $creneaux (chambre => jour => [heure, salle]) --}}
<script type="text/javascript">

	const CRENEAUX_ROLES = @js($creneaux);

	/* Pré-remplit l'heure et la salle quand la chambre et la date correspondent à un jour d'audience habituel. */
	function role_creneau() {
		const chambre = $('#f_role_chambre').val(), date = $('#f_role_date').val();
		if (!chambre || !date || !CRENEAUX_ROLES[chambre]) { return; }
		const jour = new Date(date + 'T12:00:00').getDay() || 7;
		const k = CRENEAUX_ROLES[chambre][jour];
		if (!k) { return; }
		if (k.heure) { $('#f_role_heure').val(k.heure); }
		if (k.salle) { $('#f_role_salle').val(String(k.salle)).trigger('change.select2'); }
	}

	const INTITULES_ROLES = @js(\App\Models\RoleAudience::INTITULES);

	/* Rôle de référé ou pénal : intitulé de l'audience (liste proposée) et mention « additif ». */
	function role_type() {
		const t = $('#f_role_type').val(), propositions = INTITULES_ROLES[t] || [];
		$('#bloc-role-intitule, #bloc-role-additif').toggle(propositions.length > 0);
		$('#liste-intitules').html(propositions.map(function (i) { return $('<option>').attr('value', i)[0].outerHTML; }).join(''));
	}

	/* Une chambre pénale propose d'emblée le rôle pénal (si l'utilisateur a le droit) ; sinon le rôle civil. Le choix reste modifiable. */
	function role_type_defaut() {
		const voulu = $('#f_role_chambre option:selected').data('type') === 'penal' ? 'penal' : 'civil';
		const possible = $('#f_role_type option[value="' + voulu + '"]').length > 0;
		$('#f_role_type').val(possible ? voulu : 'civil').trigger('change.select2'); role_type();
	}

	function ouvrir_role(chambre, date) {
		$('#form-role')[0].reset(); setTimeout(role_type, 0);
		$('#f_role_chambre').val(chambre ? String(chambre) : '').trigger('change.select2');
		role_type_defaut();
		$('#f_role_salle').val('').trigger('change.select2');
		$('#f_role_date').val(date || '');
		if (chambre && date) { role_creneau(); }
		$('#modal-role').modal('show');
	}

	document.addEventListener('DOMContentLoaded', function () {
		$('#f_role_chambre').on('change', function () { role_type_defaut(); role_creneau(); });
		$('#f_role_type').on('change', role_type); role_type();
		$('#f_role_date').on('change', role_creneau);
	});

	@if ($errors->any() && old('chambre_id'))
		document.addEventListener('DOMContentLoaded', function () { $('#modal-role').modal('show'); });
	@endif
</script>
