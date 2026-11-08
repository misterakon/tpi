<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	var mdp_aide_base = '';   // texte d'aide du mode "saisir" (differe entre creation et modification)

	function add_form() {
		$('.modal_form')[0].reset();
		$('[name="id_"]').val('');
		mdp_reinitialiser();
		$('#password').prop('required', false);
		$('#profil_id').val('').trigger('change');
		$('[name="actif"]').val('1').trigger('change');
		mdp_definir_aide('(facultatif : laissez vide pour envoyer un lien par e-mail)');
		$('#BtnEnregistrer').html('ENREGISTRER').val('Enregistrer');
		$('.submit').attr('disabled', false);
		$('#tooltipmodal').modal('show');
	}

	function edit_compte(id) {
		$('.modal_form')[0].reset();
		$('#BtnEnregistrer').html('MODIFIER').val('Modifier');

		// En edition, le mot de passe n'est jamais precharge (il n'est meme
		// pas renvoye par le serveur) : laisser vide = ne pas le changer.
		mdp_reinitialiser();
		$('#password').prop('required', false);
		mdp_definir_aide('(laisser vide pour ne pas modifier le mot de passe actuel)');

		$.ajax({
			url: "{{ route('utilisateur.compte.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				$('[name="id_"]').val(data.id);
				$('[name="name"]').val(data.name);
				$('[name="email"]').val(data.email);
				$('[name="profil_id"]').val(data.profil_id).trigger('change');   // « change » : la liste déroulante avec recherche (Select2) affiche alors le profil
				$('[name="actif"]').val(data.actif ? "1" : "0").trigger('change');

				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#tooltipmodal').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}

	/* ============================================================
	   ACTIONS : supprimer, envoyer un lien de mot de passe
	   ============================================================ */
	function deletes_compte(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Êtes-vous sûr de vouloir supprimer ce compte ? Il sera désactivé et masqué ; son historique reste dans le journal.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('utilisateur.compte.delete', ':id') }}".replace(':id', id),
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

	function revoquer_codes(id, nom) {
		Swal.fire({
			title: APP_NOM, icon: 'warning', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, annuler les codes',
			text: "Annuler tous les codes de secours de " + nom + " ? Les codes déjà imprimés ou notés ne marcheront plus ; la personne pourra en générer de nouveaux dans « Mon compte »."
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('utilisateur.compte.codes_secours.revoquer', ':id') }}".replace(':id', id), type: "POST",
				success: function (response) { Swal.fire({ icon: response.status ? 'success' : 'warning', title: APP_NOM, text: response.message }); },
				error: function () { Swal.fire({ icon: 'error', title: 'Erreur', text: "Une erreur s'est produite." }); }
			});
		});
	}

	function lien_mot_de_passe(id, email) {
		Swal.fire({
			title: APP_NOM,
			text: "Envoyer à " + email + " un lien pour choisir son mot de passe ?",
			icon: 'question',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, envoyer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('utilisateur.compte.lien', ':id') }}".replace(':id', id),
				type: "POST",
				success: function (response) {
					Swal.fire({ icon: response.status ? 'success' : 'warning', title: APP_NOM, text: response.message });
				},
				error: function () {
					Swal.fire({ icon: 'error', title: 'Erreur', text: "Une erreur s'est produite pendant l'envoi." });
				}
			});
		});
	}


	/* ============================================================
	   MOT DE PASSE : afficher / masquer, saisir ou generer automatiquement
	   ============================================================ */
	function mdp_definir_aide(texte) {
		mdp_aide_base = texte;
		$('#password_hint').text(texte);
	}

	// Remet le champ dans son etat initial : mode "saisir", mot de passe masque, boutons caches
	function mdp_reinitialiser() {
		$('#mdp_saisir').prop('checked', true);
		$('#password').attr('type', 'password');
		$('#btn_voir_mdp i').attr('class', 'fa-regular fa-eye');
		$('#mdp_actions').addClass('hidden');
	}

	function mdp_basculer() {
		const visible = $('#password').attr('type') === 'text';
		$('#password').attr('type', visible ? 'password' : 'text');
		$('#btn_voir_mdp i').attr('class', visible ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash');
	}

	// Entier aleatoire 0..max-1, sans biais (generateur cryptographique du navigateur)
	function mdp_entier_aleatoire(max) {
		const tirage = new Uint32Array(1);
		const limite = Math.floor(0x100000000 / max) * max;
		do { window.crypto.getRandomValues(tirage); } while (tirage[0] >= limite);
		return tirage[0] % max;
	}

	// 12 caracteres : au moins une minuscule, une majuscule, un chiffre et un symbole ;
	// sans caracteres ambigus (0 O 1 l I) pour pouvoir le dicter ou le recopier sans erreur.
	function mdp_generer(longueur) {
		longueur = longueur || 12;
		const minuscules = 'abcdefghijkmnpqrstuvwxyz';
		const majuscules = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
		const chiffres = '23456789';
		const symboles = '!@#$%*?-_+=';
		const tous = minuscules + majuscules + chiffres + symboles;
		const un = (jeu) => jeu.charAt(mdp_entier_aleatoire(jeu.length));

		const caracteres = [un(minuscules), un(majuscules), un(chiffres), un(symboles)];
		while (caracteres.length < longueur) { caracteres.push(un(tous)); }

		for (let i = caracteres.length - 1; i > 0; i--) {   // melange (Fisher-Yates)
			const j = mdp_entier_aleatoire(i + 1);
			[caracteres[i], caracteres[j]] = [caracteres[j], caracteres[i]];
		}
		return caracteres.join('');
	}

	function mdp_changer_mode(mode) {
		if (mode === 'generer') {
			mdp_regenerer();
			$('#mdp_actions').removeClass('hidden');
			$('#password_hint').text('(généré automatiquement : notez-le ou copiez-le avant d\'enregistrer)');
		} else {
			$('#password').val('').attr('type', 'password');
			$('#btn_voir_mdp i').attr('class', 'fa-regular fa-eye');
			$('#mdp_actions').addClass('hidden');
			$('#password_hint').text(mdp_aide_base);
		}
	}

	function mdp_regenerer() {
		// Le mot de passe genere est affiche en clair, pour pouvoir le communiquer a l'utilisateur
		$('#password').val(mdp_generer(12)).attr('type', 'text');
		$('#btn_voir_mdp i').attr('class', 'fa-regular fa-eye-slash');
	}

	function mdp_copier() {
		const champ = document.getElementById('password');
		if (!champ.value) { return; }

		const confirme = function () {
			$('#btn_copier_mdp i').attr('class', 'fa-solid fa-check');
			setTimeout(function () { $('#btn_copier_mdp i').attr('class', 'fa-regular fa-copy'); }, 1500);
		};

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(champ.value).then(confirme);
		} else {
			// Repli (page en http sur un reseau interne) : copie via la selection du champ
			const typeInitial = champ.type;
			champ.type = 'text';
			champ.select();
			try { if (document.execCommand('copy')) { confirme(); } } catch (e) { /* copie impossible */ }
			champ.type = typeInitial;
			champ.blur();
		}
	}

</script>
