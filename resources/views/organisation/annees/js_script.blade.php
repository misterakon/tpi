<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');

		$('#titre-modal-annee').text('Nouveau : année judiciaire');
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-annee').modal('show');
	}

	function edit_annee(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-annee').text('Modifier : année judiciaire');
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.annees.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				Object.keys(data).forEach(function (nom) {
					if (nom === 'id') { return; }
					const $c = $('.modal_form [name="' + nom + '"]');
					if ($c.is(':checkbox')) { $c.prop('checked', !!data[nom]); }
					else { $c.val(data[nom] === null ? '' : String(data[nom]).substring(0, $c.is('[type="date"]') ? 10 : 1000)); }
				});
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-annee').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_annee(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette année judiciaire ? L'année en cours ne peut pas être supprimée.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.annees.delete', ':id') }}".replace(':id', id),
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
