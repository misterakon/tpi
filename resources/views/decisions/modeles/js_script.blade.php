<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));
	const FILTRE_CONSEILLE = @js(\App\Support\Redaction\VariablesDecision::FILTRE_CONSEILLE);

	/* Les accolades sont écrites en deux morceaux pour que Blade ne les prenne pas pour une variable de page. */
	const OUVRE = '{' + '{', FERME = '}' + '}';

	const MISE_EN_PAGE = @js($miseEnPage);

	let ongletActif = 'entete';
	let dernierApercu = null;
	let compteurVariables = {{ count(old('variables', $propres)) + 100 }};

	/* ============================================================
	   ONGLETS ET INSERTION
	   ============================================================ */
	function afficher_onglet(nom) {
		ongletActif = nom;
		['entete', 'qualite', 'factum'].forEach(function (n) {
			document.getElementById('panneau-' + n).style.display = n === nom ? '' : 'none';
			document.getElementById('onglet-' + n).classList.toggle('actif', n === nom);
		});
	}

	function inserer_variable(bouton) {
		const code = bouton.getAttribute('data-code'), type = bouton.getAttribute('data-type');
		let filtre = document.getElementById('f_filtre').value;
		const argument = document.getElementById('f_filtre_arg').value.trim();
		if (filtre === '') { filtre = FILTRE_CONSEILLE[type] || ''; }
		if (filtre === '-') { filtre = ''; }
		const texte = OUVRE + ' ' + code + (filtre ? '|' + filtre + (argument ? ':' + argument : '') : '') + ' ' + FERME;
		EditeurRiche.inserer('#f_' + ongletActif, texte);
	}

	function inserer_bloc(bouton) {
		EditeurRiche.inserer('#f_' + ongletActif, OUVRE + '> ' + bouton.getAttribute('data-code') + ' ' + FERME);
	}

	function chercher_element(valeur) {
		const t = valeur.trim().toLowerCase();
		document.querySelectorAll('.mdl-chip').forEach(function (c) {
			c.style.display = !t || (c.getAttribute('data-texte') || '').indexOf(t) !== -1 ? '' : 'none';
		});
	}

	/* ============================================================
	   VARIABLES PROPRES AU MODÈLE
	   ============================================================ */
	function ajouter_variable() {
		const modele = document.getElementById('modele-variable').innerHTML.replace(/__I__/g, compteurVariables++);
		$('#lignes-variables').append(modele);
	}

	function retirer_variable(bouton) {
		$(bouton).closest('.mdl-ligne').remove();
	}

	/* ============================================================
	   APERÇU, VERSIONS
	   ============================================================ */
	function apercu_modele() {
		$.ajax({
			url: "{{ route('decisions.modeles.apercu') }}",
			type: 'POST',
			data: $('#form-modele').serialize(),
			dataType: 'JSON',
			success: function (r) {
				dernierApercu = r;
				zoomApercu = 'ajuste';
				const $a = $('#apercu-alertes').empty();
				if (r.manquantes && r.manquantes.length) {
					$a.append($('<div class="mdl-partie">').text('Données sans valeur dans l\'aperçu'));
					r.manquantes.forEach(function (m) { $a.append($('<span class="mdl-flag">').text(m)); });
				}
				if (r.inconnues && r.inconnues.length) {
					$a.append($('<div class="mdl-partie" style="margin-top:8px">').text('Variables absentes du catalogue (ajoutez-les dans « Variables propres »)'));
					r.inconnues.forEach(function (m) { $a.append($('<span class="mdl-flag">').text(m)); });
				}
				$('#modal-apercu').modal('show');
				dessiner_apercu();   // après l'ouverture : la largeur disponible est alors connue
			},
			error: function (xhr) {
				const j = xhr.responseJSON || {};
				const m = j.errors ? Object.values(j.errors).map(function (x) { return x[0]; }).join(' ') : (j.message || 'Aperçu impossible.');
				Swal.fire({ icon: 'error', title: APP_NOM, text: m });
			}
		});
	}

	/** Dessine la décision sur des pages A4, comme dans le document Word. */
	const PALIERS_ZOOM = [0.3, 0.4, 0.5, 0.6, 0.75, 0.9, 1, 1.25, 1.5, 1.75, 2, 2.5, 3];
	let zoomApercu = 'ajuste';   /* « ajuste » : la feuille remplit la largeur ; sinon un nombre (1 = taille réelle) */
	let echelleApercu = 1;

	function dessiner_apercu() {
		if (!dernierApercu) { return; }
		const r = FeuilleActe.afficher(document.getElementById('apercu-feuilles'), dernierApercu, MISE_EN_PAGE, { couleurs: document.getElementById('apercu-couleurs').checked, zoom: zoomApercu });
		if (r) {
			echelleApercu = r.echelle;
			document.getElementById('apercu-zoom').textContent = Math.round(r.echelle * 100) + ' %';
		}
	}

	/** +1 / -1 : palier suivant ou précédent ; 0 : ajuster à la largeur ; un nombre supérieur à 5 : ce pourcentage (100 = taille réelle). */
	function zoom_apercu(pas) {
		if (pas === 0) {
			zoomApercu = 'ajuste';
		} else if (pas > 1) {
			zoomApercu = pas / 100;
		} else {
			const actuel = echelleApercu;
			const suivant = pas > 0 ? PALIERS_ZOOM.find(function (p) { return p > actuel + 0.01; }) : PALIERS_ZOOM.slice().reverse().find(function (p) { return p < actuel - 0.01; });
			zoomApercu = suivant || (pas > 0 ? PALIERS_ZOOM[PALIERS_ZOOM.length - 1] : PALIERS_ZOOM[0]);
		}
		dessiner_apercu();
	}

	/* Ctrl + molette sur l'aperçu : zoom (jQuery est chargé en bas de page, l'écouteur s'installe une fois la page prête). */
	document.addEventListener('DOMContentLoaded', function () {
		document.getElementById('apercu-feuilles').addEventListener('wheel', function (e) {
			if (!e.ctrlKey) { return; }
			e.preventDefault();
			zoom_apercu(e.deltaY < 0 ? 1 : -1);
		}, { passive: false });
	});

	function dupliquer_ce_modele() {
		Swal.fire({
			title: APP_NOM, text: "Créer une copie de ce modèle, telle qu'il est enregistré ? Les changements non enregistrés ne sont pas repris.", icon: 'question',
			showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, dupliquer'
		}).then(function (r) { if (r.isConfirmed) { document.getElementById('form-duplication').submit(); } });
	}

	function restaurer_version(numero) {
		Swal.fire({
			title: APP_NOM, text: 'Remettre la version ' + numero + ' en service ? Une nouvelle version sera créée à partir d\'elle.', icon: 'question',
			showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, restaurer'
		}).then(function (r) {
			if (!r.isConfirmed) { return; }
			const f = document.getElementById('form-restauration');
			f.action = "{{ route('decisions.modeles.restaurer', [$modele->id ?? 0, ':n']) }}".replace(':n', numero);
			f.submit();
		});
	}

	/* Lecture seule : les éditeurs se désactivent une fois la page entièrement chargée. */
	@if ($lecture)
	window.addEventListener('load', function () { EditeurRiche.desactiver(); });
	@endif

</script>
