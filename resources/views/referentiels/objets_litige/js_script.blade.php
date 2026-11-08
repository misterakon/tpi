<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('[name="matiere_id"], [name="nature_affaire_id"], [name="chambre_libelle"]').val('').trigger('change.select2');
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		$('#titre-modal-objets_litige').text("Nouveau : objet du litige");
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-objets_litige').modal('show');
	}

	function edit_objet_litige(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-objets_litige').text("Modifier : objet du litige");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('referentiels.objets_litige.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="libelle"]').val(data.libelle === null ? '' : data.libelle);
				$('[name="matiere_id"]').val(data.matiere_id === null ? '' : data.matiere_id).trigger('change.select2');
				$('[name="nature_affaire_id"]').val(data.nature_affaire_id === null ? '' : data.nature_affaire_id).trigger('change.select2');
				$('[name="chambre_libelle"]').val(data.chambre_libelle === null ? '' : data.chambre_libelle).trigger('change.select2');
				$('[name="actif"]').prop('checked', !!data.actif);
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-objets_litige').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_objet_litige(id) {
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
				url: "{{ route('referentiels.objets_litige.delete', ':id') }}".replace(':id', id),
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
