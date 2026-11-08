<script type="text/javascript">

	let prochainIndice = {{ is_array(old('parties')) && old('parties') ? max(array_keys(old('parties'))) + 1 : 2 }};
	let minuterie = null;
	let minuterie2 = null;

	/** Demande d'abord le registre (civil, pénal, instruction), puis ouvre la fenêtre de saisie qui correspond. */
	function ouvrir_choix() {
		$('#modal-choix').modal('show');
	}

	function choisir_registre(registre) {
		$('#modal-choix').modal('hide');
		setTimeout(function () {
			if (registre === 'penal') { ouvrir_saisie_penal(); }
			else if (registre === 'instruction') { ouvrir_saisie_instruction(); }
			else { ouvrir_saisie(); }
		}, 250);
	}

	/**
	 * Remet une fenêtre de saisie à zéro à chaque ouverture : champs vides, une seule personne par rôle (celles ajoutées sont retirées),
	 * messages et propositions effacés. Une saisie refusée n'est pas remise à zéro : elle se rouvre avec ce qui avait été tapé.
	 */
	function reinitialiser_saisie($modal) {
		$modal.find('.aff-erreur, [data-alerte-numero], #alerte-numero, #suggestions, #dates-audience, .aff-doublons').empty();
		$modal.find('.aff-doublons, .aff-derogation').remove();
		$modal.find('.is-invalid').removeClass('is-invalid');
		$modal.find('input, textarea').not('[type="hidden"], [type="checkbox"], [type="radio"]').val('');
		$modal.find('input[type="checkbox"]').prop('checked', false);
		$modal.find('select').each(function () { $(this).val(this.options.length && this.name.indexOf('[nature]') !== -1 ? this.options[0].value : '').trigger('change'); });
		$modal.find('input[name="date_rg"]').val(new Date().toISOString().slice(0, 10));

		/* Personnes : on garde le nombre prévu par défaut dans chaque liste, les autres disparaissent */
		$modal.find('[data-defaut]').each(function () {
			const garder = parseInt($(this).attr('data-defaut'), 10) || 0;
			$(this).children('.aff-partie').slice(garder).remove();
		});
		$modal.find('.aff-partie').attr('data-type', 'physique');
		$modal.find('.aff-autres').attr('data-ferme', '1');
		$modal.find('.modal-body').scrollTop(0);
	}

	/** Ouvre la fenêtre de saisie d'une affaire civile (à zéro, sauf si elle se rouvre après une erreur). */
	function ouvrir_saisie(apresErreur) {
		if (apresErreur !== true) { reinitialiser_saisie($('#modal-saisie')); }
		$('#modal-saisie').modal('show');
	}

	/** Ajoute une partie (demandeur ou défendeur) à partir du modèle caché. */
	function ajouter_partie(role) {
		const html = document.getElementById('modele-partie').innerHTML.replace(/__I__/g, prochainIndice++).replace(/__ROLE__/g, role).replace(/__TITRE__/g, role === 'demandeur' ? 'Demandeur' : 'Défendeur');
		const $bloc = $(html);
		$('#parties-' + role).append($bloc);
		$bloc.find('input[type="text"]').first().trigger('focus');
	}

	function retirer_partie(bouton) {
		const $p = $(bouton).closest('.aff-partie');
		const role = $p.find('input[name$="[role]"]').val();
		if (role === 'demandeur' && $('#parties-demandeur .aff-partie').length <= 1) {
			Swal.fire({ title: "{{ config('app.name') }}", icon: 'info', text: 'Une affaire a au moins un demandeur.', confirmButtonColor: '#00794C' });
			return;
		}
		$p.remove();
	}

	/** « et 19 autres » : affiche le champ du nombre d'autres personnes. */
	function afficher_autres(lien) {
		const $b = $(lien).closest('.aff-autres').removeAttr('data-ferme');
		$b.find('input').trigger('focus');
	}

	/** Personne physique : nom, prénoms, naissance… ; couple, personne morale, État : une dénomination. */
	function changer_nature(choix) {
		const nature = $(choix).val();
		const physique = ['homme', 'femme'].indexOf(nature) !== -1;
		if (nature === 'mp') {
			const $d = $(choix).closest('.aff-partie').find('input[name$="[denomination]"]');
			if (!$d.val()) { $d.val('MP'); }
		}
		$(choix).closest('.aff-partie').attr('data-type', physique ? 'physique' : 'autre');
	}

	/* ============================================================
	   OBJET DU LITIGE : propositions par mots-clés
	   ============================================================ */
	function proposer(appliquerLaPremiere) {
		const objet = $('#f_objet').val().trim();
		const $zone = $('#suggestions').empty();
		if (objet.length < 4) { return; }

		$.ajax({
			url: "{{ route('affaires.enrolement.suggestions') }}", type: 'POST', dataType: 'JSON',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), objet: objet, registre: 'civil' },
			success: function (r) {
				$zone.empty();
				if (!r.propositions || !r.propositions.length) { return; }
				$zone.append($('<div class="aff-suggestions__titre">').text("Propositions de chambre d'après l'objet (cliquez pour les appliquer) :"));
				if (appliquerLaPremiere === true) { appliquer_proposition(r.propositions[0]); }
				r.propositions.forEach(function (p) {
					const $b = $('<button type="button" class="aff-suggestion">');
					$b.append($('<b>').text(p.libelle));
					$b.append($('<span>').text([p.nature, p.matiere, p.chambre].filter(Boolean).join(' · ')));
					$b.on('click', function () { appliquer_proposition(p); });
					$zone.append($b);
				});
			}
		});
	}

	function appliquer_proposition(p) {
		[['#f_nature', p.nature_id], ['#f_matiere', p.matiere_id], ['#f_chambre', p.chambre_id]].forEach(function (c) {
			if (c[1]) { $(c[0]).val(String(c[1])).trigger('change'); }
		});
	}

	/* ============================================================
	   DATE DE PREMIÈRE AUDIENCE : prochaines audiences de la chambre et date limite d'enrôlement
	   ============================================================ */
	function charger_dates() {
		const chambre = $('#f_chambre').val();
		const $zone = $('#dates-audience').empty();
		const manuel = String($zone.data('manuel')) === '1';   // numéro saisi à la main : les dates sont une aide, la date reste facultative
		if (!chambre) { return; }

		$.ajax({
			url: "{{ route('affaires.enrolement.dates') }}", type: 'POST', dataType: 'JSON',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), chambre_id: chambre, nature_affaire_id: $('#f_nature').val() },
			success: function (r) {
				$zone.empty();
				if (!r.dates || !r.dates.length) {
					$zone.append($('<div class="aff-dates__note">').text("Aucun jour d'audience n'est défini pour cette chambre dans le calendrier."));
					return;
				}
				$zone.append($('<div class="aff-dates__note">').text(
					(r.exemptee ? "Cette nature d'affaire n'est pas soumise à la date limite d'enrôlement. " : "Audiences : " + r.jours + ". Cliquez sur une date pour la choisir ; ") +
					(r.exemptee ? '' : manuel ? "la date reste facultative." : "la plus proche encore ouverte est proposée.")
				));
				const $liste = $('<div class="aff-dates__liste">');
				r.dates.forEach(function (d) {
					const $b = $('<button type="button" class="aff-date">').toggleClass('aff-date--fermee', !d.ouverte && !r.exemptee);
					$b.append($('<b>').text(d.libelle));
					if (d.precision && d.heure) { $b.append($('<span>').text(d.precision)); }
					if (d.limite) { $b.append($('<span>').text((d.ouverte || r.exemptee ? "Enrôlement jusqu'au " : 'Date limite dépassée : ') + d.limite)); }
					$b.on('click', function () { $('#f_date_audience').val(d.date); $('#f_heure_audience').val(d.heure || ''); });
					$liste.append($b);
				});
				$zone.append($liste);
				const ouverte = r.dates.filter(function (d) { return d.ouverte; })[0];
				if (!manuel && !$('#f_date_audience').val() && ouverte && !r.exemptee) { $('#f_date_audience').val(ouverte.date); if (!$('#f_heure_audience').val()) { $('#f_heure_audience').val(ouverte.heure || ''); } }
			}
		});
	}

	/* ============================================================
	   OUTIL D'ESSAI : remplit le formulaire civil avec un exemple fictif (chaque clic prend l'exemple suivant)
	   ============================================================ */
	const EXEMPLES_CIVIL = [
		{ objet: 'Demande en paiement de loyers impayés et en expulsion', demandeur: ['KOUASSI', 'Yao Marc', 'ivoirienne'], defendeur: 'Société EXEMPLE IMMOBILIER SARL' },
		{ objet: 'Déguerpissement et démolition de construction', demandeur: ['DIABY', 'Karamoko', 'malienne'], defendeur: 'Société BATIMAT SARL' },
		{ objet: "Contestation d'un acte sans mot-clé connu", demandeur: ['BAMBA', 'Fatoumata', 'ivoirienne'], defendeur: 'Société TEST SA' }
	];
	let prochainExemple = 0;

	function remplir_exemple_civil() {
		const ex = EXEMPLES_CIVIL[prochainExemple++ % EXEMPLES_CIVIL.length];
		const $m = $('#modal-saisie');
		/* La nature, la matière et la chambre sont vidées : c'est l'objet qui les propose. */
		['#f_nature', '#f_matiere', '#f_chambre', '#f_mode'].forEach(function (s) { $m.find(s).val('').trigger('change'); });
		const mode = $m.find('#f_mode option').filter(function () { return /assignation/i.test($(this).text()); }).val();
		if (mode) { $m.find('#f_mode').val(mode).trigger('change'); }
		$m.find('#f_numero_rg').val((1500 + Math.floor(Math.random() * 400)) + '/' + String(new Date().getFullYear()).slice(2)).trigger('change');
		$m.find('#f_objet').val(ex.objet);

		const $demandeur = $m.find('#parties-demandeur .aff-partie').first();
		$demandeur.find('select[name$="[nature]"]').val('homme').trigger('change');
		$demandeur.find('input[name$="[nom]"]').val(ex.demandeur[0]);
		$demandeur.find('input[name$="[prenoms]"]').val(ex.demandeur[1]);
		$demandeur.find('select[name$="[nationalite]"]').val(ex.demandeur[2]).trigger('change');
		const $defendeur = $m.find('#parties-defendeur .aff-partie').first();
		$defendeur.find('select[name$="[nature]"]').val('morale').trigger('change');
		$defendeur.find('input[name$="[denomination]"]').val(ex.defendeur);

		proposer(true);   /* les propositions s'affichent sous l'objet et la première est appliquée */
	}

	/* ============================================================
	   NUMÉRO RG SAISI À LA MAIN : doublon ou jonction
	   ============================================================ */
	function controler_numero() {
		const numero = $('#f_numero_rg').val().trim();
		const $zone = $('#alerte-numero').attr('class', 'aff-alerte-numero').empty();
		if (!/^\d{1,6}\/\d{2}$/.test(numero)) { return; }

		$.ajax({
			url: "{{ route('affaires.enrolement.numero') }}", type: 'POST', dataType: 'JSON',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), numero_rg: numero },
			success: function (r) {
				const jonction = $('#f_jonction').is(':checked');
				if (!r.existe) {
					if (jonction) { $zone.addClass('aff-alerte-numero--erreur').text("Aucune affaire n'a ce numéro : ce n'est pas une jonction. Désactivez l'interrupteur « Jonction »."); }
					return;
				}
				const liste = r.affaires.map(function (a) { return a.numero + ' — ' + a.intitule + (a.annulee ? ' (annulée)' : ''); }).join(' ; ');
				if (jonction) {
					$zone.addClass('aff-alerte-numero--info').text('Jonction avec : ' + liste + '. Les deux affaires porteront le même numéro (même chambre exigée).');
				} else {
					$zone.addClass('aff-alerte-numero--erreur').text('Ce numéro est déjà enregistré : ' + liste + ". S'il s'agit d'une jonction, activez l'interrupteur « Jonction » ; sinon cette affaire est un doublon et ne pourra pas être enregistrée.");
				}
			}
		});
	}

	/* jQuery est chargé en bas de page, après ce script : les écouteurs s'installent une fois la page prête. */
	document.addEventListener('DOMContentLoaded', function () {
		$('#f_objet').on('input', function () { clearTimeout(minuterie); minuterie = setTimeout(proposer, 450); });
		proposer();
		if ($('#dates-audience').length) {
			$('#f_chambre, #f_nature').on('change', charger_dates);
			charger_dates();
		}
		$('#f_numero_rg').on('input change', function () { clearTimeout(minuterie2); minuterie2 = setTimeout(controler_numero, 350); });
		$('#f_jonction').on('change', controler_numero);
		controler_numero();
		/* Une saisie refusée rouvre la fenêtre avec ce qui avait été tapé et les messages d'erreur. */
		@if (($ouvrirAuto ?? true) && $errors->any() && old('parties') !== null && old('registre', 'civil') === 'civil')
			ouvrir_saisie(true);
		@endif
	});

</script>
