<script type="text/javascript">

	/* Formations d'une chambre (page « Composition »). APP_NOM est défini par le script des affectations, inclus avant celui-ci. */

	function add_formation() {
		$('#form-formation')[0].reset();
		$('#form-formation [name="id_"]').val('');
		$('#form-formation input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		$('#titre-modal-formation').text('Nouvelle formation');
		$('#modal-formation').modal('show');
	}

	function edit_formation(id) {
		$('#form-formation')[0].reset();
		$('#titre-modal-formation').text('Modifier la formation');

		$.ajax({
			url: "{{ route('organisation.formations.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				const $f = $('#form-formation');
				$f.find('[name="id_"]').val(data.id);
				$f.find('[name="nom"]').val(data.nom || '');
				$f.find('[name="intitule"]').val(data.intitule || '');
				$f.find('[name="attributions"]').val(data.attributions || '');
				EditeurRiche.charger();
				$f.find('[name="ordre"]').val(data.ordre);
				$f.find('[name="actif"]').prop('checked', !!data.actif);
				$('#modal-formation').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	function delete_formation(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette formation ? Elle ne doit avoir ni composition ni calendrier ; sinon, désactivez-la.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.formations.delete', ':id') }}".replace(':id', id),
				type: "DELETE",
				success: function (response) {
					if (response.status) {
						Swal.fire({ icon: 'success', title: APP_NOM, text: response.message }).then(function () { window.location.reload(); });
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
