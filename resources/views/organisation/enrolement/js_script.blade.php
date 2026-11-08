<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('#f_jour_audience, #f_jour_limite').val('1').trigger('change.select2');
		$('#f_heure').val('16:00');
		$('#titre-modal-enrolement').text("Nouvelle règle d'enrôlement");
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-enrolement').modal('show');
	}

	function edit_enrolement(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-enrolement').text("Modifier la règle d'enrôlement");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.enrolement.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('#f_jour_audience').val(data.jour_audience).trigger('change.select2');
				$('#f_jour_limite').val(data.jour_limite).trigger('change.select2');
				$('#f_heure').val(data.heure);
				$('[name="remarque"]').val(data.remarque || '');
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-enrolement').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTIONS : supprimer, essayer une date
	   ============================================================ */
	function delete_enrolement(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette règle d'enrôlement ?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.enrolement.delete', ':id') }}".replace(':id', id),
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

	function calculer_enrolement() {
		const date = $('#essai-date').val();
		if (!date) { $('#essai-resultat').text("Indiquez la date de l'audience."); return; }
		$.getJSON("{{ route('organisation.enrolement.calcul') }}", { date: date })
			.done(function (r) { $('#essai-resultat').text(r.message); })
			.fail(function () { $('#essai-resultat').text('Date invalide.'); });
	}

</script>
