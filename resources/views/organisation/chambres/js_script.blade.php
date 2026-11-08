<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));
	const ANNEE_AFFICHEE = @js($annee?->id);

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		// La nouvelle chambre est proposée dans l'année affichée.
		$('#f_annee_judiciaire_id').val(ANNEE_AFFICHEE).trigger('change.select2');
		$('#f_section_id').val('').trigger('change.select2');
		$('#titre-modal-chambre').text('Nouvelle chambre');
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-chambre').modal('show');
	}

	function edit_chambre(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-chambre').text('Modifier la chambre');
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.chambres.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="annee_judiciaire_id"]').val(data.annee_judiciaire_id).trigger('change.select2');
				$('[name="section_id"]').val(data.section_id).trigger('change.select2');
				$('[name="libelle"]').val(data.libelle);
				$('[name="intitule"]').val(data.intitule || '');
				$('[name="groupe"]').val(data.groupe || '');
				$('[name="attributions"]').val(data.attributions || '');
				$('[name="remarque"]').val(data.remarque || '');
				EditeurRiche.charger();
				$('[name="ordre"]').val(data.ordre);
				$('[name="actif"]').prop('checked', !!data.actif);
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-chambre').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_chambre(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette chambre ? Elle ne doit avoir ni composition ni calendrier ; sinon, désactivez-la.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.chambres.delete', ':id') }}".replace(':id', id),
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
