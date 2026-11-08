<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		$('#titre-modal-infractions').text("Nouvelle : infraction");
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-infractions').modal('show');
	}

	function edit_infraction(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-infractions').text("Modifier : infraction");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('referentiels.infractions.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="libelle"]').val(data.libelle === null ? '' : data.libelle);
				$('[name="articles"]').val(data.articles === null ? '' : data.articles);
				$('[name="actif"]').prop('checked', !!data.actif);
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-infractions').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_infraction(id) {
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
				url: "{{ route('referentiels.infractions.delete', ':id') }}".replace(':id', id),
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
