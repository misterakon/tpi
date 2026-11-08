<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));
	const ANNEE_AFFICHEE = @js($annee?->id);
	var compteur_lignes = 0;

	/* ============================================================
	   LIGNES « PERSONNE + FONCTION »
	   ============================================================ */
	function ajouter_ligne(agent_id, fonction) {
		const html = document.getElementById('modele-ligne-agent').innerHTML.replace(/__I__/g, compteur_lignes++);
		const $ligne = $(html);
		$('#lignes-agents').append($ligne);
		if (agent_id) { $ligne.find('select').val(String(agent_id)); }
		if (fonction) { $ligne.find('input').val(fonction); }
		if (window.RechercheListes) { RechercheListes.appliquer($ligne); RechercheListes.rafraichir($ligne); }
	}

	function retirer_ligne(bouton) { $(bouton).closest('.el-ligne').remove(); }

	function vider_lignes() { $('#lignes-agents').empty(); compteur_lignes = 0; }

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		vider_lignes();
		ajouter_ligne();
		$('#f_annee_judiciaire_id').val(ANNEE_AFFICHEE).trigger('change.select2');
		$('#f_categorie').val('').trigger('change.select2');
		$('#titre-modal-service').text('Nouveau service ou fonction');
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-service').modal('show');
	}

	function edit_service(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-service').text('Modifier le service ou la fonction');
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.services.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="annee_judiciaire_id"]').val(data.annee_judiciaire_id).trigger('change.select2');
				$('[name="categorie"]').val(data.categorie).trigger('change.select2');
				$('[name="libelle"]').val(data.libelle);
				$('[name="ordre"]').val(data.ordre);
				$('[name="remarque"]').val(data.remarque || '');
				EditeurRiche.charger();
				vider_lignes();
				(data.agents || []).forEach(function (a) { ajouter_ligne(a.agent_id, a.fonction); });
				if (!data.agents || !data.agents.length) { ajouter_ligne(); }
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-service').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_service(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer ce service ou cette fonction ?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.services.delete', ':id') }}".replace(':id', id),
				type: "DELETE",
				success: function (response) {
					if (response.status) {
						Swal.fire({ icon: 'success', title: APP_NOM, text: response.message });
						$("#row-" + id).fadeOut(500, function () { $(this).remove(); });
					} else {
						Swal.fire({ icon: 'error', title: 'Erreur', text: response.message });
					}
				},
				error: function () {
					Swal.fire({ icon: 'error', title: 'Erreur', text: "Une erreur s'est produite lors de la suppression." });
				}
			});
		});
	}

</script>
