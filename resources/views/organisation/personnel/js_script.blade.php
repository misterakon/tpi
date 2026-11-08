<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		$('#titre-modal-agent').text('Nouveau : agent');
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-agent').modal('show');
	}

	function edit_agent(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-agent').text('Modifier : agent');
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.personnel.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				Object.keys(data).forEach(function (nom) {
					if (nom === 'id') { return; }
					const $c = $('.modal_form [name="' + nom + '"]');
					if ($c.is(':checkbox')) { $c.prop('checked', !!data[nom]); }
					else { $c.val(data[nom] === null ? '' : String(data[nom]).substring(0, $c.is('[type="date"]') ? 10 : 1000)).trigger('change.select2'); }
				});
				if (window.RechercheListes) { RechercheListes.rafraichir(); }
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-agent').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_agent(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cet agent ? Il sera masqué, mais son historique d'affectations sera conservé.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.personnel.delete', ':id') }}".replace(':id', id),
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
