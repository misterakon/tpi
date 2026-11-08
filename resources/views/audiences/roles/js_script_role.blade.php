{{-- Éditeur du rôle : fenêtres, confirmation des actions sensibles. Variables : $role, $publie --}}
<script type="text/javascript">

	/* Les lignes du rôle, pour remplir la fenêtre de modification sans aller-retour au serveur. */
	const LIGNES_ROLE = @js($role->lignes->mapWithKeys(fn ($l) => [$l->id => $l->only(['rubrique', 'numero', 'demandeurs', 'defendeurs', 'avocats_demandeurs', 'avocats_defendeurs', 'objet', 'observations', 'issue']) + ['renvoi_au' => $l->renvoi_au?->format('Y-m-d')]]));
	const URL_LIGNE = @js(route('audiences.roles.ligne.modifier', [$role->id, 0]));

	function role_renvoi() { $('#bloc-renvoi').toggle($('#f_lig_issue').val() === 'renvoi'); }

	/* Date de renvoi : avertit (sans bloquer) si le jour est fermé ou si la chambre n'y siège pas, et propose la prochaine audience habituelle. */
	const URL_RENVOI = @js(route('audiences.roles.renvoi', $role->id));
	function role_verifier_renvoi() {
		const aide = $('#aide-renvoi'), d = $('#f_lig_renvoi').val();
		aide.prop('hidden', true).empty();
		if (!d || $('#f_lig_issue').val() !== 'renvoi') { return; }
		fetch(URL_RENVOI + '?date=' + encodeURIComponent(d), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (j) {
				if (!j || $('#f_lig_renvoi').val() !== d || (!j.ferme && j.siege)) { return; }
				const msg = j.ferme ? 'Le ' + j.jour + ' est un jour fermé (' + j.ferme + ').' : 'La ' + j.chambre + ' ne siège pas le ' + j.jour + ' (jours habituels : ' + j.habituels.join(', ') + ').';
				aide.append($('<i class="fa-solid fa-triangle-exclamation"></i>')).append($('<span></span>').text(' ' + msg + ' Vous pouvez quand même enregistrer cette date.'));
				if (j.suggestions && j.suggestions.length) {
					const choix = $('<div class="rl-renvoi-aide__choix-liste"></div>').append($('<span></span>').text('Prochaines audiences :'));
					j.suggestions.forEach(function (s) {
						choix.append($('<button type="button" class="rl-renvoi-aide__choix"></button>').text(s.libelle).on('click', function () {
							$('#f_lig_renvoi').val(s.date); role_verifier_renvoi();
						}));
					});
					aide.append(choix);
				}
				aide.prop('hidden', false);
			}).catch(function () {});
	}

	function modifier_ligne(id) {
		const l = LIGNES_ROLE[id]; if (!l) { return; }
		const f = $('#form-ligne')[0]; f.reset();
		$(f).attr('action', URL_LIGNE.replace(/\/0$/, '/' + id));
		['numero', 'demandeurs', 'defendeurs', 'avocats_demandeurs', 'avocats_defendeurs', 'objet', 'observations'].forEach(function (c) { $('[name="' + c + '"]', f).val(l[c] || ''); });
		$('#f_lig_rubrique').val(l.rubrique).trigger('change.select2');
		$('#f_lig_issue').val(l.issue || '').trigger('change.select2');
		$('#f_lig_renvoi').val(l.renvoi_au || '');
		$('#titre-modal-ligne').text(@js($publie ? 'Suite donnée à l\'audience' : 'Modifier la ligne') + (l.numero ? ' · ' + l.numero.split('\n')[0] : ''));
		role_renvoi();
		role_verifier_renvoi();
		$('#modal-ligne').modal('show');
	}

	function ajouter_ligne(rubrique) {
		$('#f_aj_rubrique').val(rubrique).trigger('change.select2'); $('#f_aj_numero').val('');
		$('#modal-ajout').modal('show');
		setTimeout(function () { $('#f_aj_numero').trigger('focus'); }, 350);
	}

	function retirer_ligne(id, numero) {
		Swal.fire({ icon: 'warning', title: 'Retirer du rôle ?', text: numero + ' ne figurera plus sur ce rôle.', showCancelButton: true, confirmButtonText: 'Retirer', cancelButtonText: 'Annuler', confirmButtonColor: '#B42318' }).then(function (r) {
			if (!r.isConfirmed) { return; }
			const f = $('<form method="post"></form>').attr('action', URL_LIGNE.replace(/\/0$/, '/' + id + '/retirer')).append('<input type="hidden" name="_token" value="{{ csrf_token() }}">');
			$('body').append(f); f.trigger('submit');
		});
	}

	function publier_role() {
		Swal.fire({ icon: 'question', title: 'Publier le rôle ?', text: 'Sa version {{ $role->version }} sera figée. Vous pourrez le rouvrir pour une correction.', showCancelButton: true, confirmButtonText: 'Publier', cancelButtonText: 'Pas encore', confirmButtonColor: '#00794C' })
			.then(function (r) { if (r.isConfirmed) { $('#form-publier').trigger('submit'); } });
	}

	function supprimer_role() {
		Swal.fire({ icon: 'warning', title: 'Supprimer ce rôle ?', text: 'Il n\'a jamais été publié : il sera effacé.', showCancelButton: true, confirmButtonText: 'Supprimer', cancelButtonText: 'Annuler', confirmButtonColor: '#B42318' })
			.then(function (r) { if (r.isConfirmed) { $('#form-supprimer').trigger('submit'); } });
	}

	document.addEventListener('DOMContentLoaded', function () {
		$('#f_lig_issue').on('change', function () { role_renvoi(); role_verifier_renvoi(); });
		$('#f_lig_renvoi').on('change', role_verifier_renvoi);
	});

	@if ($errors->has('date_audience') || $errors->has('salle_id') || $errors->has('fait_a') || $errors->has('signataire'))
		document.addEventListener('DOMContentLoaded', function () { $('#modal-entete').modal('show'); });
	@elseif ($errors->has('numero'))
		document.addEventListener('DOMContentLoaded', function () { $('#modal-ajout').modal('show'); });
	@elseif ($errors->has('motif'))
		document.addEventListener('DOMContentLoaded', function () { $('#modal-rouvrir').modal('show'); });
	@endif
</script>
