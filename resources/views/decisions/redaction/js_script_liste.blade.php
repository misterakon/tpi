<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	const URL_AFFAIRES = @js(route('decisions.redaction.affaires'));
	const PRESELECTION = @js($preselection ?? null);
	const FR = { noResults: function () { return 'Aucune affaire trouvée'; }, searching: function () { return 'Recherche en cours…'; }, errorLoading: function () { return 'Les résultats ne peuvent pas être chargés.'; } };

	/* Un résultat : numéro, parties, chambre et dernière audience ; « À rédiger » pour une affaire jugée sans décision validée. */
	function presenter(a) {
		if (!a.id) { return a.text; }
		const $r = $('<div class="red-aff"></div>');
		const $t = $('<b></b>').text(a.numero);
		if (a.a_rediger) { $t.append($('<span class="red-aff__etiquette"></span>').text('À rédiger')); }
		$r.append($t).append($('<div></div>').text(a.intitule || ''));
		const suite = [a.chambre, a.audience ? 'audience du ' + a.audience.split('-').reverse().join('/') : '', a.suite].filter(Boolean).join(' · ');
		if (suite) { $r.append($('<small></small>').text(suite)); }
		return $r;
	}

	function remplir(a) {
		if (a.chambre_id) { $('#f_chambre_id').val(String(a.chambre_id)).trigger('change.select2'); }
		if (a.audience) { $('#f_date_audience').val(a.audience); }
	}

	function choisir(a) {
		$('#f_affaire_id').empty().append(new Option(a.numero + ' — ' + (a.intitule || ''), a.id, true, true)).trigger('change.select2');
		remplir(a);
	}

	function add_form() {
		$('.modal_form')[0].reset();
		$('#f_modele_decision_id').val('').trigger('change.select2');
		$('#f_chambre_id').val('').trigger('change.select2');
		$('#f_affaire_id').empty().trigger('change.select2');
		$('.submit').attr('disabled', false);
		$('#modal-decision').modal('show');
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (!$('#f_affaire_id').length) { return; }
		$('#f_affaire_id').select2({
			width: '100%', language: FR, dropdownParent: $('#modal-decision'), placeholder: 'Rechercher par numéro ou par partie…', allowClear: false, minimumInputLength: 0,
			ajax: { url: URL_AFFAIRES, dataType: 'json', delay: 250, data: function (p) { return { q: p.term || '' }; }, processResults: function (j) { return { results: j.resultats }; } },
			templateResult: presenter, templateSelection: function (a) { return a.id ? a.numero ? a.numero + ' — ' + (a.intitule || '') : a.text : a.text; },
		}).on('select2:select', function (e) { remplir(e.params.data); });
		if (PRESELECTION) { add_form(); choisir(PRESELECTION); }
	});

	function delete_decision(id) {
		Swal.fire({
			title: APP_NOM, text: "Supprimer ce brouillon ? Les valeurs saisies seront perdues.", icon: 'warning', showCancelButton: true,
			confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('decisions.redaction.delete', ':id') }}".replace(':id', id), type: "DELETE",
				success: function (r) {
					if (r.status) { Swal.fire({ icon: 'success', title: APP_NOM, text: r.message }); $("#row-" + id).fadeOut(500, function () { $(this).remove(); }); }
					else { Swal.fire({ icon: 'error', title: 'Erreur', text: r.message }); }
				},
				error: function () { Swal.fire({ icon: 'error', title: 'Erreur', text: "Une erreur s'est produite lors de la suppression." }); }
			});
		});
	}

</script>
