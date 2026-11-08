<script type="text/javascript">

	/* Jours et heures d'audience d'une formation (page « Composition »). APP_NOM est défini par le script des affectations, inclus avant celui-ci. */

	/* Audience supplémentaire : une seule date, le jour s'en déduit. */
	function adapter_creneau() {
		const unique = $('#c_frequence').val() === 'ponctuelle';
		$('#bloc-jour, #bloc-fin').toggle(!unique);
		$('#lib-debut').text(unique ? 'Date' : 'En vigueur du');
		$('#fac-debut').toggle(!unique);
		$('#c_debut').prop('required', unique);
	}
	/* jQuery est chargé en bas de page, après ce script : l'écouteur s'installe une fois la page prête. */
	document.addEventListener('DOMContentLoaded', function () {
		$(document).on('change', '#c_frequence', adapter_creneau);
	});

	function add_creneau(formation_id) {
		const $f = $('#form-creneau');
		$f[0].reset();
		$f.find('[name="id_"]').val('');
		$f.find('[name="formation_id"]').val(formation_id);
		$f.find('[name="frequence"]').val('hebdomadaire').trigger('change.select2');
		$f.find('[name="jour"], [name="salle_id"], [name="ordonnance_id"]').val('').trigger('change.select2');
		adapter_creneau();
		$('#titre-modal-creneau').text('Ajouter une audience');
		$('#BtnCreneau').html('ENREGISTRER');
		$('#BtnCreneau').attr('disabled', false);
		$('#modal-creneau').modal('show');
	}

	function edit_creneau(id) {
		const $f = $('#form-creneau');
		$f[0].reset();
		$('#titre-modal-creneau').text("Modifier l'audience");
		$('#BtnCreneau').html('MODIFIER');

		$.ajax({
			url: "{{ route('organisation.creneaux.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$f.find('[name="id_"]').val(data.id);
				$f.find('[name="formation_id"]').val(data.formation_id);
				$f.find('[name="type"]').val(data.type);
				$f.find('[name="frequence"]').val(data.frequence).trigger('change.select2');
				$f.find('[name="jour"]').val(data.jour || '').trigger('change.select2');
				$f.find('[name="heure"]').val(data.heure || '');
				$f.find('[name="salle_id"]').val(data.salle_id || '').trigger('change.select2');
				$f.find('[name="precision"]').val(data.precision || '');
				$f.find('[name="debut"]').val(data.debut || '');
				$f.find('[name="fin"]').val(data.fin || '');
				$f.find('[name="ordonnance_id"]').val(data.ordonnance_id || '').trigger('change.select2');
				adapter_creneau();
				$('#BtnCreneau').removeClass('disabled').attr('disabled', false);
				$('#modal-creneau').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	function delete_creneau(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cette audience ? Pour un changement d'horaire, préférez renseigner sa date de fin : l'historique est conservé.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('organisation.creneaux.delete', ':id') }}".replace(':id', id),
				type: "DELETE",
				success: function (response) {
					if (response.status) {
						Swal.fire({ icon: 'success', title: APP_NOM, text: response.message });
						$("#creneau-" + id).fadeOut(500, function () { $(this).remove(); });
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
