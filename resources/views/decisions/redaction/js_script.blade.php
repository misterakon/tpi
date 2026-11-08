<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));
	const MISE_EN_PAGE = @js($miseEnPage);
	const EXEMPLES = @js(\App\Support\Redaction\ChampsDecision::exemplesPour($champs));
	const FIGEE = @js($decision->estValidee());
	const PALIERS_ZOOM = [0.3, 0.4, 0.5, 0.6, 0.75, 0.9, 1, 1.25, 1.5, 1.75, 2, 2.5, 3];

	let apercu = null;           /* dernière réponse du serveur */
	const ZOOM_DEFAUT = 1.25;    /* zoom à l'ouverture : 1 = 100 %, 1.25 = 125 % (le bouton « Largeur » ajuste à la largeur) */
	let zoom = ZOOM_DEFAUT;      /* « ajuste » : la feuille remplit la largeur ; sinon un nombre (1 = taille réelle) */
	let echelle = 1;
	let minuterie = null;

	/* ============================================================
	   APERÇU : la décision remplie avec ce qui est saisi
	   ============================================================ */
	function actualiser_apercu() {
		$.ajax({
			url: "{{ route('decisions.redaction.apercu', $decision->id) }}",
			type: 'POST',
			data: $('#form-decision').serialize(),
			dataType: 'JSON',
			success: function (r) { apercu = r; dessiner(); afficher_etat(r); },
			error: function (xhr) {
				const j = xhr.responseJSON || {};
				$('#apercu-etat').empty().append($('<div class="red-bandeau red-bandeau--alerte">').text(j.message || "L'aperçu n'a pas pu être calculé."));
			}
		});
	}

	function afficher_etat(r) {
		const $e = $('#apercu-etat').empty();
		if (r.retouchees && r.retouchees.length) {
			const noms = r.retouchees.map(function (p) { return PARTIES_NOMS[p]; }).join(', ');
			$e.append($('<div class="red-bandeau red-bandeau--retouche">').text('Retouché à la main : ' + noms + '. Cette partie ne suit plus le formulaire ; « Retoucher le texte » permet de la rétablir.'));
		}
		if (r.fige) {
			$e.append($('<div class="red-bandeau">').text('Texte figé à la validation : il ne change plus, même si le modèle est modifié.'));
		} else if (r.a_completer && r.a_completer.length) {
			$e.append($('<div class="red-etat__titre">').text('À compléter (cliquez pour aller au champ) :'));
			r.a_completer.forEach(function (m) {
				$e.append($('<button type="button" class="red-manque">').text(m.libelle).attr('data-champ', m.code));
			});
		} else {
			$e.append($('<div class="red-bandeau">').text('Tous les champs sont renseignés.'));
		}
	}

	/* ============================================================
	   RETOUCHE À LA MAIN du texte généré
	   ============================================================ */
	const PARTIES_NOMS = { entete: 'en-tête', qualite: 'qualité', factum: 'factum' };
	const PARTIES_CODES = ['entete', 'qualite', 'factum'];
	let aRetablir = [];

	function marquer_retouche(partie) {
		const retouchee = apercu && apercu.retouchees && apercu.retouchees.indexOf(partie) !== -1 && aRetablir.indexOf(partie) === -1;
		$('#r_' + partie + '_etat').text(aRetablir.indexOf(partie) !== -1 ? 'sera rétabli' : (retouchee ? 'retouché' : ''));
	}

	/** Ouvre la fenêtre avec le texte tel qu'il est affiché (retouches comprises) ; les champs encore vides apparaissent entre crochets. */
	function ouvrir_retouche() {
		if (!apercu) { return; }
		aRetablir = [];
		PARTIES_CODES.forEach(function (p) {
			$('#r_' + p).val(apercu[p] || '').removeAttr('data-modifie');
			marquer_retouche(p);
		});
		EditeurRiche.charger();
		$('#modal-retouche').modal('show');
	}

	function retablir_partie(partie) {
		$('#r_' + partie).val((apercu.generes && apercu.generes[partie]) || '').removeAttr('data-modifie');
		EditeurRiche.charger();
		if (aRetablir.indexOf(partie) === -1) { aRetablir.push(partie); }
		marquer_retouche(partie);
	}

	function enregistrer_retouche() {
		const donnees = { _token: $('meta[name="csrf-token"]').attr('content'), retablir: aRetablir.slice(), retouches: {} };
		PARTIES_CODES.forEach(function (p) {
			if (aRetablir.indexOf(p) === -1 && $('#r_' + p).attr('data-modifie')) { donnees.retouches[p] = $('#r_' + p).val(); }
		});
		$.ajax({
			url: "{{ route('decisions.redaction.retoucher', $decision->id) }}",
			type: 'POST', data: donnees, dataType: 'JSON',
			success: function () { $('#modal-retouche').modal('hide'); actualiser_apercu(); },
			error: function (xhr) {
				Swal.fire({ title: APP_NOM, icon: 'error', text: (xhr.responseJSON && xhr.responseJSON.message) || "La retouche n'a pas pu être enregistrée.", confirmButtonColor: '#00794C' });
			}
		});
	}

	/** Date de naissance : « date exacte » ou « année seulement » (jour inconnu), selon le champ caché « mode ». */
	function appliquer_naissance($b) {
		const annee = $b.find('input[type="hidden"]').val() === 'annee';
		$b.find('input[type="date"]').prop('hidden', annee);
		$b.find('input[type="number"]').prop('hidden', !annee);
		$b.find('.red-naissance__lien').text(annee ? 'Saisir la date exacte' : "Jour inconnu : saisir l'année seulement");
	}

	function basculer_naissance(lien) {
		const $b = $(lien).closest('.red-naissance');
		const $m = $b.find('input[type="hidden"]');
		$m.val($m.val() === 'annee' ? 'date' : 'annee');
		appliquer_naissance($b);
		$b.closest('form').trigger('change');
	}

	/** Affiche la ligne suivante d'une liste de personnes (assesseurs). */
	function ajouter_personne(bouton) {
		const $lignes = $(bouton).closest('.red-personnes').find('.red-personne[hidden]');
		$lignes.first().removeAttr('hidden').find('input').trigger('focus');
		if ($lignes.length <= 1) { $(bouton).attr('hidden', true); }
	}

	function recomposer_tribunal() {
		Swal.fire({
			title: APP_NOM, icon: 'question', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, reprendre',
			text: "Le président, les assesseurs et le greffier de l'ordonnance (à la date d'audience enregistrée) remplaceront ceux du formulaire. Les autres champs ne changent pas."
		}).then(function (r) { if (r.isConfirmed) { soumettre("{{ route('decisions.redaction.composition', $decision->id) }}"); } });
	}

	/** Efface le président, les assesseurs et le greffier du formulaire (rien n'est enregistré avant « Enregistrer »). */
	function vider_composition() {
		const $r = $('.red-rangee');
		$r.find('input[type="text"]').val('');
		$r.find('select').val('').trigger('change.select2');
		$r.find('.red-personne').each(function (i) { $(this).prop('hidden', i >= 2); });
		$r.find('.red-ajouter').prop('hidden', false);
		actualiser_apercu();
	}

	/** Va au champ du formulaire correspondant à une pastille ou à un « À compléter » : ouvre son bloc et le met en évidence. */
	function aller_au_champ(code) {
		let $c = $('.red-champ[data-code="' + String(code).replace(/"/g, '') + '"]');
		if (!$c.length) {
			/* « demandeur » retombe sur « demandeur.nom » */
			$c = $('.red-champ[data-code="' + String(code).replace(/"/g, '') + '.nom"]');
		}
		if (!$c.length) { return; }
		$c.closest('details').prop('open', true);
		$('.red-champ--cible').removeClass('red-champ--cible');
		$c.addClass('red-champ--cible');
		$c[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		$c.find('input, textarea, select').not(':disabled').first().trigger('focus');
		setTimeout(function () { $c.removeClass('red-champ--cible'); }, 2500);
	}

	function dessiner() {
		if (!apercu) { return; }
		const r = FeuilleActe.afficher(document.getElementById('apercu-feuilles'), apercu, MISE_EN_PAGE, { couleurs: false, zoom: zoom });
		if (r) {
			echelle = r.echelle;
			document.getElementById('apercu-zoom').textContent = Math.round(r.echelle * 100) + ' %';
		}
	}

	/** +1 / -1 : palier suivant ou précédent ; 0 : ajuster à la largeur ; un nombre supérieur à 1 : ce pourcentage (100 = taille réelle). */
	function zoom_apercu(pas) {
		if (pas === 0) {
			zoom = 'ajuste';
		} else if (pas > 1) {
			zoom = pas / 100;
		} else {
			const actuel = echelle;
			const suivant = pas > 0 ? PALIERS_ZOOM.find(function (p) { return p > actuel + 0.01; }) : PALIERS_ZOOM.slice().reverse().find(function (p) { return p < actuel - 0.01; });
			zoom = suivant || (pas > 0 ? PALIERS_ZOOM[PALIERS_ZOOM.length - 1] : PALIERS_ZOOM[0]);
		}
		dessiner();
	}

	/* ============================================================
	   ACTIONS
	   ============================================================ */
	function soumettre(url) {
		const f = document.getElementById('form-action');
		f.action = url;
		f.submit();
	}

	function valider_decision() {
		Swal.fire({
			title: APP_NOM, icon: 'question', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, valider',
			text: "Valider cette décision ? Le texte sera figé : il ne pourra plus être modifié (une correction passera par une nouvelle décision). Les valeurs enregistrées sont utilisées : enregistrez d'abord vos dernières saisies."
		}).then(function (r) { if (r.isConfirmed) { soumettre("{{ route('decisions.redaction.valider', $decision->id) }}"); } });
	}

	function marquer_redigee() {
		Swal.fire({
			title: APP_NOM, icon: 'question', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Pas encore', confirmButtonText: 'Oui, rédigée',
			text: "La rédaction est terminée ? La décision attendra la validation et ne sera plus modifiable que par celui qui valide. Enregistrez d'abord vos dernières saisies."
		}).then(function (r) { if (r.isConfirmed) { soumettre("{{ route('decisions.redaction.redigee', $decision->id) }}"); } });
	}

	function numeroter_decision() {
		Swal.fire({
			title: APP_NOM, icon: 'question', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, attribuer',
			text: "Le prochain numéro de l'année sera attribué à cette décision. Il est définitif : il ne sera ni modifié ni réutilisé."
		}).then(function (r) { if (r.isConfirmed) { soumettre("{{ route('decisions.redaction.numeroter', $decision->id) }}"); } });
	}

	function renvoyer_decision() {
		Swal.fire({
			title: APP_NOM, icon: 'question', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, renvoyer',
			text: "La décision redeviendra un brouillon, modifiable par son rédacteur."
		}).then(function (r) { if (r.isConfirmed) { soumettre("{{ route('decisions.redaction.renvoyer', $decision->id) }}"); } });
	}

	function ouvrir_signature() { $('#modal-signature').modal('show'); }
	function ouvrir_delivrance() { $('#modal-delivrance').modal('show'); }

	function corriger_decision() {
		Swal.fire({
			title: APP_NOM, icon: 'question', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, corriger',
			text: "La décision validée reste telle quelle. Une nouvelle décision en brouillon est créée avec les mêmes valeurs, pour la corriger puis la valider."
		}).then(function (r) { if (r.isConfirmed) { soumettre("{{ route('decisions.redaction.corriger', $decision->id) }}"); } });
	}

	/** Remplit le formulaire avec les exemples fictifs du modèle : pour essayer ou former, jamais pour une vraie décision. */
	let avantExemples = null;

	/** Un seul bouton : remplit avec les exemples, puis (quand c'est fait) remet le formulaire d'avant. */
	function basculer_exemples() { if (avantExemples) { annuler_exemples(); } else { remplir_exemples(); } }
   /* le formulaire tel qu'il était avant les exemples, pour pouvoir le restaurer */

	function remplir_exemples() {
		if (!avantExemples) {
			avantExemples = [];
			$('#form-decision').find('input[name], textarea[name], select[name]').not('[name="_token"]').each(function () {
				avantExemples.push({ el: this, valeur: $(this).val(), cache: $(this).closest('.red-personne').prop('hidden') });
			});
		}
		$('#btn-exemples').html('<i class="fa-solid fa-rotate-left"></i> <span>Annuler les exemples</span>');
		Object.keys(EXEMPLES).forEach(function (code) {
			const v = EXEMPLES[code];
			const $c = $('.red-champ[data-code="' + code.replace(/"/g, '') + '"]');
			if (!$c.length) { return; }
			const type = $c.attr('data-type');
			if (type === 'personne') {
				$c.find('[name$="[nom]"]').val(v.nom || '');
				$c.find('[name$="[genre]"]').val(v.genre || '').trigger('change.select2');
			} else if (type === 'personnes') {
				(v || []).forEach(function (p, i) {
					$c.find('[name$="[' + i + '][nom]"]').val(p.nom || '').closest('.red-personne').removeAttr('hidden');
					$c.find('[name$="[' + i + '][genre]"]').val(p.genre || '').trigger('change.select2');
				});
			} else if (type === 'naissance') {
				$c.find('input[type="hidden"]').val(v.mode || 'date');
				$c.find('input[type="date"]').val(v.date || '');
				$c.find('input[type="number"]').val(v.annee || '');
				appliquer_naissance($c.find('.red-naissance'));
			} else if (type === 'liste') {
				$c.find('textarea').val(Array.isArray(v) ? v.join('\n') : v);
			} else if (type === 'genre') {
				$c.find('select').val(v).trigger('change.select2');
			} else {
				$c.find('input').val(v);
				$c.find('select').val(v).trigger('change.select2');
			}
		});
		actualiser_apercu();
	}

	/** Remet le formulaire comme il était avant « Exemples » (ce qui n'avait pas été enregistré revient aussi). */
	function annuler_exemples() {
		if (!avantExemples) { return; }
		avantExemples.forEach(function (c) {
			$(c.el).val(c.valeur).trigger('change.select2');
			$(c.el).closest('.red-personne').prop('hidden', c.cache);
		});
		$('.red-naissance').each(function () { appliquer_naissance($(this)); });
		avantExemples = null;
		$('#btn-exemples').html('<i class="fa-solid fa-wand-magic-sparkles"></i> <span>Exemples</span>');
		actualiser_apercu();
	}

	/* jQuery est chargé en bas de page, après ce script : les écouteurs s'installent une fois la page prête. */
	document.addEventListener('DOMContentLoaded', function () {
		actualiser_apercu();
		if (!FIGEE) {
			$('#form-decision').on('input change', function () {
				clearTimeout(minuterie);
				minuterie = setTimeout(actualiser_apercu, 700);
			});
		}
		$('#modal-retouche').on('input', 'textarea[data-partie]', function () { $(this).attr('data-modifie', '1'); });
		$('#apercu-etat').on('click', '.red-manque', function () { aller_au_champ($(this).attr('data-champ')); });
		$('#apercu-feuilles').on('click', '.acte-vide', function () { aller_au_champ($(this).attr('data-champ')); });
		document.getElementById('apercu-feuilles').addEventListener('wheel', function (e) {
			if (!e.ctrlKey) { return; }
			e.preventDefault();
			zoom_apercu(e.deltaY < 0 ? 1 : -1);
		}, { passive: false });
		window.addEventListener('resize', function () { if (zoom === 'ajuste') { dessiner(); } });
	});

</script>
