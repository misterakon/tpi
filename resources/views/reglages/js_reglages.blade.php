<script type="text/javascript">

	/* Reglages (JS natif : jQuery n'est pas encore charge quand ce script s'execute) */

	// Bouton "Retablir la valeur par defaut"
	function reglage_retablir(cle, libelle) {
		Swal.fire({
			title: @js(config('app.name')),
			text: 'Rétablir « ' + libelle + ' » à sa valeur par défaut ?',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, rétablir'
		}).then(function (result) {
			if (!result.isConfirmed) { return; }
			const form = document.getElementById('form_retablir');
			form.querySelector('[name="cle"]').value = cle;
			form.submit();
		});
	}

	// Recherche : filtre les reglages, deplie les modules qui contiennent un resultat, masque les autres
	window.addEventListener('load', function () {
		const champ = document.getElementById('rg-recherche');
		if (!champ) { return; }

		const aucun = document.getElementById('rg-aucun');
		const modules = document.querySelectorAll('.rg-module');
		const ouvertsAvant = new Map();
		modules.forEach(function (m) { ouvertsAvant.set(m, m.open); });

		champ.addEventListener('input', function () {
			const q = champ.value.trim().toLowerCase();
			let total = 0;

			modules.forEach(function (module) {
				let visibles = 0;
				module.querySelectorAll('.rg-ligne').forEach(function (ligne) {
					const ok = q === '' || (ligne.dataset.recherche || '').indexOf(q) !== -1;
					ligne.style.display = ok ? '' : 'none';
					if (ok) { visibles++; }
				});
				module.style.display = visibles === 0 ? 'none' : '';
				if (q !== '' && visibles > 0) { module.open = true; }
				if (q === '') { module.open = ouvertsAvant.get(module); }
				total += visibles;
			});

			aucun.style.display = (q !== '' && total === 0) ? '' : 'none';
		});
	});

</script>
