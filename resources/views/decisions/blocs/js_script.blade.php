<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('#f_categorie').val('formule').trigger('change.select2');
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		$('#titre-modal-bloc').text('Nouveau bloc de texte');
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-bloc').modal('show');
	}

	/** Les modèles qui insèrent ce bloc (corriger le bloc change leurs prochaines versions). */
	function voir_modeles(bouton) {
		const modeles = JSON.parse($(bouton).attr('data-modeles') || '[]');
		$('#titre-modal-modeles').text($(bouton).attr('data-bloc'));
		$('#modeles-intro').text(modeles.length + (modeles.length > 1 ? ' modèles utilisent' : ' modèle utilise') + " ce bloc : le corriger se répercute dans leurs nouvelles versions.");
		const $l = $('#modeles-liste').empty();
		modeles.forEach(function (m) {
			const $li = $('<li>');
			$li.append(m.url ? $('<a>').attr('href', m.url).text(m.nom) : $('<span>').text(m.nom));
			$l.append($li);
		});
		$('#modal-modeles').modal('show');
	}

	function edit_bloc(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-bloc').text('Modifier le bloc de texte');
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('decisions.blocs.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="nom"]').val(data.nom);
				$('#f_categorie').val(data.categorie).trigger('change.select2');
				$('[name="code"]').val(data.code);
				$('[name="actif"]').prop('checked', !!data.actif);
				$('[name="contenu"]').val(data.contenu || '');
				EditeurRiche.charger();
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-bloc').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_bloc(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer ce bloc ? Il ne doit être utilisé par aucun modèle.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('decisions.blocs.delete', ':id') }}".replace(':id', id),
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
