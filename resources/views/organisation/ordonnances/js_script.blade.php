<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : dépôt / modification des informations
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('#f_annee_judiciaire_id').val('').trigger('change.select2');
		$('#f_type').val('annuelle').trigger('change.select2');
		$('#bloc-fichier').show();
		$('#f_fichier').prop('required', true);
		$('#bloc-fichier-info').hide();
		$('#titre-modal-ordonnance').text('Archiver une ordonnance');
		$('#BtnEnregistrer').html('ARCHIVER');
		$('.submit').attr('disabled', false);
		$('#modal-ordonnance').modal('show');
	}

	function edit_ordonnance(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-ordonnance').text("Modifier les informations de l'ordonnance");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.ordonnances.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('#f_annee_judiciaire_id').val(data.annee_judiciaire_id).trigger('change.select2');
				$('#f_type').val(data.type).trigger('change.select2');
				$('[name="numero"]').val(data.numero);
				$('[name="date_signature"]').val(data.date_signature);
				$('[name="date_effet"]').val(data.date_effet);
				$('[name="objet"]').val(data.objet);
				$('[name="observations"]').val(data.observations);
				EditeurRiche.charger();
				$('#bloc-fichier').hide();
				$('#f_fichier').prop('required', false).val('');
				$('#info-fichier').text(data.nom_original + ' (' + data.taille + ')');
				$('#bloc-fichier-info').show();
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-ordonnance').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_ordonnance(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette ordonnance de l'archive ? Le fichier PDF sera effacé définitivement.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.ordonnances.delete', ':id') }}".replace(':id', id),
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
