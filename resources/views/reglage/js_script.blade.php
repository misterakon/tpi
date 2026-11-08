<script type="text/javascript">

	/* Ce script est inclus AVANT le chargement de jQuery (qui est en bas de page) :
	   il n'utilise donc que du JavaScript natif au chargement ; jQuery et SweetAlert ne servent
	   que lors d'un clic, quand ils sont déjà chargés. */

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   RUBRIQUES : un seul panneau visible à la fois
	   ============================================================ */
	function afficher_rubrique(cle) {
		document.querySelectorAll('.el-rub').forEach(function (b) {
			const actif = b.dataset.rub === cle;
			b.classList.toggle('is-active', actif);
			b.setAttribute('aria-selected', actif ? 'true' : 'false');
		});
		document.querySelectorAll('.el-panneau').forEach(function (p) { p.hidden = (p.id !== 'rub-' + cle); });

		const url = new URL(window.location.href);
		url.searchParams.set('groupe', cle);
		window.history.replaceState(null, '', url.toString());
	}

	/* ============================================================
	   VALEURS MODIFIÉES : la ligne change de couleur tant qu'on n'a pas enregistré
	   ============================================================ */
	function marquer_modifie(e) {
		const champ = e.target;
		if (!champ.matches || !champ.matches('.el-reglage__ctrl [data-initial]')) { return; }
		champ.closest('.el-reglage').classList.toggle('is-modifie', String(champ.value) !== String(champ.dataset.initial));
	}
	document.addEventListener('input', marquer_modifie);
	document.addEventListener('change', marquer_modifie);

	/* ============================================================
	   ACTION : envoyer un courriel de test (configuration déjà enregistrée)
	   ============================================================ */
	function tester_courriel() {
		const bouton = document.getElementById('test_bouton');
		const zone = document.getElementById('test_resultat');
		const jeton = document.querySelector('meta[name="csrf-token"]');
		bouton.disabled = true;
		zone.hidden = false;
		zone.className = 'el-test__resultat';
		zone.textContent = 'Envoi en cours…';

		fetch(@js(route('reglages.tester')), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': jeton ? jeton.content : @js(csrf_token())
			},
			body: JSON.stringify({ destinataire: document.getElementById('test_destinataire').value })
		}).then(function (r) {
			return r.json().then(function (d) { return { ok: r.ok, d: d }; });
		}).then(function (res) {
			const d = res.d;
			if (res.ok && d.status) {
				zone.className = 'el-test__resultat is-ok';
				zone.textContent = d.message;
			} else {
				zone.className = 'el-test__resultat is-ko';
				const premiere = d.errors ? Object.values(d.errors)[0][0] : null;
				zone.textContent = premiere || d.message || "L'envoi a échoué.";
			}
		}).catch(function () {
			zone.className = 'el-test__resultat is-ko';
			zone.textContent = "Impossible de joindre l'application. Réessayez.";
		}).finally(function () { bouton.disabled = false; });
	}

	/* ============================================================
	   ACTION : rétablir les valeurs par défaut d'une rubrique
	   ============================================================ */
	function retablir_defauts(cle, libelle) {
		Swal.fire({
			title: APP_NOM,
			text: "Rétablir les valeurs par défaut de la rubrique « " + libelle + " » ? Les valeurs que vous aviez choisies seront perdues.",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, rétablir'
		}).then((result) => {
			if (result.isConfirmed) { document.getElementById('defaut-' + cle).submit(); }
		});
	}

</script>
