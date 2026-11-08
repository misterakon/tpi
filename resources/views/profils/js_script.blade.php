<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		$('#titre-modal-profil').text('Nouveau profil');
		$('#BtnEnregistrer').html('ENREGISTRER').val('Enregistrer');
		$('.submit').attr('disabled', false);
		synchroniser_droits();
		$('#modal-profil').modal('show');
	}

	function edit_profil(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-profil').text('Modifier le profil');
		$('#BtnEnregistrer').html('MODIFIER').val('Modifier');

		$.ajax({
			url: "{{ route('profils.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="nom"]').val(data.nom);
				$('[name="description"]').val(data.description || '');
				appliquer_choix(data.niveaux || {}, data.speciales || []);
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-profil').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function deletes_profil(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Êtes-vous sûr de vouloir supprimer ce profil ? Un profil attribué à des comptes ne peut pas être supprimé.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('profils.delete', ':id') }}".replace(':id', id),
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

	/* ============================================================
	   DROITS : un niveau par sous-menu (Aucun, Lecture, Saisie, Gestion)
	   ============================================================ */
	const ORDRE = ['aucun', 'lecture', 'saisie', 'gestion'];

	function lignes() { return $('.el-droits__ligne'); }
	function niveau_de($ligne) { return $ligne.find('input[type="radio"]:checked').val() || 'aucun'; }
	function niveau_max($ligne) { return $ligne.find('input[type="radio"]').last().val(); }

	// Met à jour compteur, interrupteurs (coché / partiel / décoché) et droits spéciaux (grisés si « Aucun »)
	function synchroniser_droits() {
		let accessibles = 0, complets = 0;

		lignes().each(function () {
			const $l = $(this), n = niveau_de($l);
			if (n !== 'aucun') { accessibles++; }
			const speciales = $l.find('input[name="speciales[]"]');
			speciales.prop('disabled', n === 'aucun');
			if (n === 'aucun') { speciales.prop('checked', false); }
			$l.toggleClass('is-aucun', n === 'aucun');
			if (n === niveau_max($l) && speciales.length === speciales.filter(':checked').length) { complets++; }
		});

		$('#compteur-droits').text(accessibles + ' / ' + lignes().length + ' sous-menus');
		etat_interrupteur($('#droits-tout'), complets, lignes().length, accessibles);

		$('.droits-menu').each(function () {
			const $lm = $('[data-menu="' + $(this).data('menu') + '"] .el-droits__ligne');
			let c = 0, a = 0;
			$lm.each(function () {
				const $l = $(this), n = niveau_de($l), sp = $l.find('input[name="speciales[]"]');
				if (n !== 'aucun') { a++; }
				if (n === niveau_max($l) && sp.length === sp.filter(':checked').length) { c++; }
			});
			etat_interrupteur($(this), c, $lm.length, a);
		});
	}

	// coché = tout au maximum ; partiel = au moins un accès ; décoché = aucun accès
	function etat_interrupteur($interrupteur, complets, total, accessibles) {
		$interrupteur.prop('checked', total > 0 && complets === total);
		$interrupteur.closest('.el-switch').toggleClass('is-partiel', complets < total && accessibles > 0);
	}

	function regler($ligne, etat) {
		const cible = etat ? niveau_max($ligne) : 'aucun';
		$ligne.find('input[type="radio"][value="' + cible + '"]').prop('checked', true);
		$ligne.find('input[name="speciales[]"]').prop('checked', etat);
	}

	function basculer_tout(etat) {
		lignes().each(function () { regler($(this), etat); });
		synchroniser_droits();
	}

	function basculer_menu(menu, etat) {
		$('[data-menu="' + menu + '"] .el-droits__ligne').each(function () { regler($(this), etat); });
		synchroniser_droits();
	}

	// Préremplit la fenêtre avec les choix d'un profil existant
	function appliquer_choix(niveaux, speciales) {
		lignes().each(function () {
			const $l = $(this), n = niveaux[$l.data('page')] || 'aucun';
			$l.find('input[type="radio"][value="' + n + '"]').prop('checked', true);
			$l.find('input[name="speciales[]"]').each(function () { $(this).prop('checked', speciales.indexOf(this.value) !== -1); });
		});
		synchroniser_droits();
	}

</script>
