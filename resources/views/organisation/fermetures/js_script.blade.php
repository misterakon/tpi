<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form(annee_id) {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('#f_annee_judiciaire_id').val(annee_id).trigger('change.select2');
		$('#f_type').val('ferie').trigger('change.select2');
		$('#titre-modal-fermeture').text('Nouvelle période');
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-fermeture').modal('show');
	}

	function edit_fermeture(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-fermeture').text('Modifier la période');
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.fermetures.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('#f_annee_judiciaire_id').val(data.annee_judiciaire_id).trigger('change.select2');
				$('#f_type').val(data.type).trigger('change.select2');
				$('[name="libelle"]').val(data.libelle);
				$('[name="debut"]').val(data.debut);
				$('[name="fin"]').val(data.fin);
				$('[name="remarque"]').val(data.remarque || '');
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-fermeture').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_fermeture(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette période ?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.fermetures.delete', ':id') }}".replace(':id', id),
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
