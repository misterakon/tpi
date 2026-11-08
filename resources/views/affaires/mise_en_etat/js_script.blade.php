<script type="text/javascript">
	const JUGES = @js($juges);   // chambre => magistrats

	/* La liste des juges chargés dépend de la chambre de l'affaire choisie. */
	function remplir_juges(chambre, choisi) {
		const $s = $('#f_mee_juge').empty().append('<option value="">À désigner</option>');
		(JUGES[chambre] || []).forEach(function (j) { $s.append($('<option>').val(j.id).text(j.nom)); });
		$s.val(choisi ? String(choisi) : '').trigger('change.select2');
	}
	document.addEventListener('DOMContentLoaded', function () {
		$('#f_mee_affaire').on('change', function () { remplir_juges($(this).find(':selected').data('chambre'), null); });
	});

	function ouvrir_mee(id) {
		const f = $('#form-mee')[0]; f.reset();
		$('[name="id_"]', f).val('');
		const afficher = function (m) {
			$('#titre-modal-mee').text(m ? 'Corriger la mise en état' : 'Confier une affaire à la mise en état');
			$('#bloc-mee-affaire').prop('hidden', !!m); $('#bloc-mee-numero').prop('hidden', !m);
			$('#f_mee_affaire').prop('required', !m);
			if (m) {
				$('[name="id_"]', f).val(m.id); $('#f_mee_numero').val(m.numero);
				$('#f_mee_reception').val(m.date_reception); $('#f_mee_audience').val(m.date_premiere_audience || ''); $('#f_mee_obs').val(m.observations || '');
				remplir_juges(m.chambre_id, m.juge_id);
			} else {
				$('#f_mee_affaire').val('').trigger('change.select2'); $('#f_mee_reception').val(@js(date('Y-m-d'))); remplir_juges(null, null);
			}
			$('#modal-mee').modal('show');
		};
		if (id === null) { afficher(null); return; }
		$.getJSON("{{ route('affaires.mise_en_etat.get', ':id') }}".replace(':id', id), afficher).fail(function () { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur lors du chargement des données.' }); });
	}

	function ouvrir_cloture_mee(id, numero) {
		$('#form-mee-cloture').attr('action', "{{ route('affaires.mise_en_etat.cloturer', ':id') }}".replace(':id', id))[0].reset();
		$('#titre-modal-mee-cloture').text('Clôturer la mise en état : ' + numero);
		$('#modal-mee-cloture').modal('show');
	}

	function ouvrir_retrait_mee(id, numero) {
		$('#form-mee-retrait').attr('action', "{{ route('affaires.mise_en_etat.retirer', ':id') }}".replace(':id', id))[0].reset();
		$('#titre-modal-mee-retrait').text('Retirer de la mise en état : ' + numero);
		$('#modal-mee-retrait').modal('show');
	}

	@if ($errors->any() && old('date_reception'))
		document.addEventListener('DOMContentLoaded', function () { $('#modal-mee').modal('show'); });
	@endif
</script>
