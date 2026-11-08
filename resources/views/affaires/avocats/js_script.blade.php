<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* Un cabinet n'a pas de prénoms : le champ disparaît. */
	function avocat_type() {
		const cabinet = $('#f_type').val() === 'cabinet';
		$('#bloc-prenoms').toggle(!cabinet);
		$('#lib-nom').text(cabinet ? 'Nom du cabinet' : 'Nom');
	}
	document.addEventListener('DOMContentLoaded', function () { $('#f_type').on('change', avocat_type); });

	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('#f_type').val('avocat').trigger('change.select2'); avocat_type();
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		$('#titre-modal-avocats').text("Nouvel avocat");
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-avocats').modal('show');
	}

	function edit_avocat(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-avocats').text("Modifier : avocat");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('affaires.avocats.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('#f_type').val(data.type).trigger('change.select2'); avocat_type();
				['nom', 'prenoms', 'barreau_numero', 'cabinet', 'telephone', 'email'].forEach(function (c) { $('[name="' + c + '"]').val(data[c] === null ? '' : data[c]); });
				$('[name="actif"]').prop('checked', !!data.actif);
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-avocats').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	function delete_avocat(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cet avocat ?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('affaires.avocats.delete', ':id') }}".replace(':id', id),
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
