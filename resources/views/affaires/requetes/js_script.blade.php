<script type="text/javascript">

	/* Le bloc « défunt » ne sert qu'à l'hérédité. */
	function requete_type() { $('#bloc-defunt').toggle($('#f_req_type').val() === 'heredite'); }
	document.addEventListener('DOMContentLoaded', function () { $('#f_req_type').on('change', requete_type); requete_type(); });

	function ouvrir_requete(id) {
		const f = $('#form-requete')[0]; f.reset();
		$('[name="id_"]', f).val('');
		const ouvrir = function (r) {
			$('#titre-modal-requete').text(r ? 'Corriger la requête' : 'Enregistrer une requête');
			if (r) {
				$('[name="id_"]', f).val(r.id);
				['objet', 'requerants', 'defunt_nom', 'defunt_lieu_deces', 'date_depot', 'defunt_date_deces'].forEach(function (c) { $('[name="' + c + '"]', f).val(r[c] || ''); });
				$('#f_req_type').val(r.type);
			} else { $('#f_req_type').val('heredite'); $('#f_req_date').val(@js(date('Y-m-d'))); }
			$('#f_req_type').trigger('change.select2'); requete_type();
			$('#modal-requete').modal('show');
		};
		if (id === null) { ouvrir(null); return; }
		$.getJSON("{{ route('affaires.requetes.get', ':id') }}".replace(':id', id), ouvrir).fail(function () { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur lors du chargement des données.' }); });
	}

	function ouvrir_repartition(id, numero, chambre) {
		$('#form-repartition').attr('action', "{{ route('affaires.requetes.repartir', ':id') }}".replace(':id', id));
		$('#titre-modal-repartition').text('Répartir la requête ' + numero);
		$('#f_rep_chambre').val(chambre ? String(chambre) : '').trigger('change.select2'); $('#f_rep_note').val('');
		$('#modal-repartition').modal('show');
	}

	function ouvrir_cloture(id, numero, issue) {
		const traitee = issue === 'traitee';
		$('#form-cloture').attr('action', "{{ route('affaires.requetes.cloturer', ':id') }}".replace(':id', id));
		$('#form-cloture [name="issue"]').val(issue);
		$('#titre-modal-cloture').text((traitee ? 'Requête traitée : ' : 'Classer sans suite : ') + numero);
		$('#lib-reference').text(traitee ? 'Décision rendue' : 'Motif du classement');
		$('#f_clo_reference').val('').attr('placeholder', traitee ? 'Ex. : Ordonnance n° 12/26 ou Jugement d\'hérédité n° 8/26' : 'Ex. : requérant désisté, requête sans objet');
		$('#btn-cloture').text(traitee ? 'Marquer comme traitée' : 'Classer sans suite');
		$('#modal-cloture').modal('show');
	}

	@if ($errors->any() && old('type'))
		document.addEventListener('DOMContentLoaded', function () { $('#modal-requete').modal('show'); });
	@endif
</script>
