{{-- Planning des salles : changement de semaine sans recharger la page (la zone seule est remplacée, l'adresse et l'historique suivent). --}}
<script type="text/javascript">

	(function () {
		const zone = document.getElementById('planning-zone');
		if (!zone) { return; }
		const base = zone.dataset.url;

		function charger(du, historique) {
			const url = base + '?du=' + encodeURIComponent(du);
			zone.classList.add('is-chargement');
			fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
				.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
				.then(function (html) {
					zone.innerHTML = html;
					if (historique !== false) { history.pushState({ du: du }, '', url); }
				})
				.catch(function () { Swal.fire({ icon: 'error', title: 'Planning', text: 'La semaine n\'a pas pu être chargée. Réessayez.' }); })
				.finally(function () { zone.classList.remove('is-chargement'); });
		}

		zone.addEventListener('click', function (e) {
			const a = e.target.closest('a[data-semaine]');
			if (!a) { return; }
			e.preventDefault();
			charger(a.dataset.semaine);
		});
		zone.addEventListener('change', function (e) {
			if (e.target.id === 'planning-date' && e.target.value) { charger(e.target.value); }
		});
		window.addEventListener('popstate', function () {
			const du = new URLSearchParams(location.search).get('du') || '';
			charger(du, false);
		});
	})();
</script>
