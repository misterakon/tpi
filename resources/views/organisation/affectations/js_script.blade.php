<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : ajout / modification d'une affectation
	   ============================================================ */
	function add_form(formation_id) {
		const $f = $('#modal-affectation .modal_form');
		$f[0].reset();
		$f.find('[name="id_"]').val('');
		$f.find('[name="formation_id"]').val(formation_id);
		$f.find('[name="agent_id"], [name="role"]').val('').trigger('change.select2');
		$('#titre-modal-affectation').text('Ajouter une personne à la formation');
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-affectation').modal('show');
	}

	function edit_affectation(id) {
		const $f = $('#modal-affectation .modal_form');
		$f[0].reset();
		$('#titre-modal-affectation').text("Modifier l'affectation");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.affectations.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$f.find('[name="id_"]').val(data.id);
				$f.find('[name="formation_id"]').val(data.formation_id);
				$f.find('[name="agent_id"]').val(data.agent_id).trigger('change.select2');
				$f.find('[name="role"]').val(data.role).trigger('change.select2');
				$f.find('[name="suppleant"]').prop('checked', !!data.suppleant);
				$f.find('[name="chef_section"]').prop('checked', !!data.chef_section);
				$f.find('[name="debut"]').val(data.debut || '');
				$f.find('[name="fin"]').val(data.fin || '');
				$f.find('[name="remarque"]').val(data.remarque || '');
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-affectation').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : remplacer une personne à une date donnée
	   ============================================================ */
	function remplacer_affectation(id, nom) {
		$('#form-remplacement')[0].reset();
		$('#form-remplacement').attr('action', "{{ route('organisation.affectations.remplacer', ':id') }}".replace(':id', id));
		$('#remplacement-ancien').text(nom);
		$('#f_nouvel_agent_id').val('').trigger('change.select2');
		$('#modal-remplacement').modal('show');
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_affectation(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette affectation ? Pour un départ ou un remplacement, préférez « Remplacer » ou une date de fin : l'historique est alors conservé.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.affectations.delete', ':id') }}".replace(':id', id),
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
