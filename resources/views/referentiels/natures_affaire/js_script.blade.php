<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('[name="registre"]').val('').trigger('change.select2');
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		$('#titre-modal-natures_affaire').text("Nouvelle : nature d'affaire");
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-natures_affaire').modal('show');
	}

	function edit_nature_affaire(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-natures_affaire').text("Modifier : nature d'affaire");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('referentiels.natures_affaire.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="libelle"]').val(data.libelle === null ? '' : data.libelle);
				$('[name="registre"]').val(data.registre === null ? '' : data.registre).trigger('change.select2');
				$('[name="actif"]').prop('checked', !!data.actif);
				$('[name="exclue_enrolement"]').prop('checked', !!data.exclue_enrolement);
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-natures_affaire').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_nature_affaire(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cet élément ?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('referentiels.natures_affaire.delete', ':id') }}".replace(':id', id),
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
