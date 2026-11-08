<script type="text/javascript">

	/* Formulaires pénal et d'instruction : chaque formulaire (.aff-form-poursuite) travaille avec ses propres éléments. */
	const PROCHAIN_INDICE = {};
	let minuterieNumero = null;

	function formulaire_de(element) { return $(element).closest('.aff-form-poursuite'); }

	function ouvrir_saisie_penal(apresErreur) {
		if (apresErreur !== true) { reinitialiser_saisie($('#modal-saisie-penal')); }
		$('#modal-saisie-penal').modal('show');
	}
	function ouvrir_saisie_instruction(apresErreur) {
		if (apresErreur !== true) { reinitialiser_saisie($('#modal-saisie-instruction')); }
		$('#modal-saisie-instruction').modal('show');
	}

	function ajouter_partie_poursuite(bouton, role) {
		const $f = formulaire_de(bouton);
		const cle = $f.attr('id');
		if (PROCHAIN_INDICE[cle] === undefined) { PROCHAIN_INDICE[cle] = $f.find('.aff-partie').length + 100; }
		const modele = $f.find('template[data-modele-partie="' + role + '"]')[0];
		const $bloc = $(modele.innerHTML.replace(/__I__/g, PROCHAIN_INDICE[cle]++));
		$f.find('[data-liste="' + role + '"]').append($bloc);
		$bloc.find('input[type="text"]').first().trigger('focus');
	}

	function retirer_partie_poursuite(bouton) {
		const $p = $(bouton).closest('.aff-partie');
		const role = $p.find('input[name$="[role]"]').val();
		const principal = ['prevenu', 'inculpe'].indexOf(role) !== -1;
		if (principal && $p.closest('[data-liste]').find('.aff-partie').length <= 1) {
			Swal.fire({ title: "{{ config('app.name') }}", icon: 'info', text: 'Une affaire a au moins une personne poursuivie.', confirmButtonColor: '#00794C' });
			return;
		}
		$p.remove();
	}

	function changer_nature_poursuite(select) {
		const physique = ['homme', 'femme'].indexOf($(select).val()) !== -1;
		$(select).closest('.aff-partie').attr('data-type', physique ? 'physique' : 'autre');
	}

	/** Le numéro saisi existe-t-il déjà ? Doublon, ou jonction si l'interrupteur est activé. */
	function controler_numero_poursuite($f) {
		const numero = $f.find('[data-numero]').val();
		const $zone = $f.find('[data-alerte-numero]').attr('class', 'aff-alerte-numero').empty();
		if (!numero || !/^\d{1,6}\/\d{2}$/.test(numero.trim())) { return; }
		const serie = $f.attr('data-registre') === 'penal' ? 'RP' : ($f.find('[data-nature] option:selected').attr('data-serie') || 'RI');

		$.ajax({
			url: "{{ route('affaires.enrolement.numero') }}", type: 'POST', dataType: 'JSON',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), numero_rg: numero.trim(), serie: serie },
			success: function (r) {
				const jonction = $f.find('[data-jonction]').is(':checked');
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

	/** Propositions de nature, matière et chambre d'après la nature de la prévention saisie (mêmes mots-clés que le civil). */
	function proposer_poursuite($f) {
		const objet = ($f.find('textarea[name="objet"]').val() || '').trim();
		const $zone = $f.find('[data-suggestions]').empty();
		if (objet.length < 4) { return; }

		$.ajax({
			url: "{{ route('affaires.enrolement.suggestions') }}", type: 'POST', dataType: 'JSON',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), objet: objet, registre: $f.attr('data-registre') },
			success: function (r) {
				$zone.empty();
				if (!r.propositions || !r.propositions.length) { return; }
				$zone.append($('<div class="aff-suggestions__titre">').text("Propositions de chambre d'après les mots saisis (cliquez pour les appliquer) :"));
				r.propositions.forEach(function (p) {
					const $b = $('<button type="button" class="aff-suggestion">');
					$b.append($('<b>').text(p.libelle));
					$b.append($('<span>').text([p.nature, p.matiere, p.chambre].filter(Boolean).join(' · ')));
					$b.on('click', function () {
						[['[data-nature]', p.nature_id], ['select[name="matiere_id"]', p.matiere_id], ['select[name="chambre_id"]', p.chambre_id]].forEach(function (c) {
							if (c[1]) { $f.find(c[0]).val(String(c[1])).trigger('change'); }
						});
					});
					$zone.append($b);
				});
			}
		});
	}

	/** Audiences de la chambre choisie (pénal) : un bouton par jour et heure, qui remplit la date et l'heure. */
	function charger_dates_poursuite($f) {
		const $zone = $f.find('[data-dates-audience]').empty();
		const chambre = $f.find('select[name="chambre_id"]').val();
		if (!$zone.length || !chambre) { return; }

		$.ajax({
			url: "{{ route('affaires.enrolement.dates') }}", type: 'POST', dataType: 'JSON',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), chambre_id: chambre, registre: 'penal' },
			success: function (r) {
				$zone.empty();
				if (!r.dates || !r.dates.length) {
					$zone.append($('<div class="aff-dates__note">').text("Aucun jour d'audience n'est défini pour cette chambre dans le calendrier : saisissez la date à la main."));
					return;
				}
				$zone.append($('<div class="aff-dates__note">').text("Audiences : " + r.jours + ". Cliquez sur une date pour la choisir ; la date reste facultative."));
				const $liste = $('<div class="aff-dates__liste">');
				r.dates.forEach(function (d) {
					const $b = $('<button type="button" class="aff-date">').append($('<b>').text(d.libelle));
					if (d.precision && d.heure) { $b.append($('<span>').text(d.precision)); }
					$b.on('click', function () {
						$f.find('input[name="date_premiere_audience"]').val(d.date);
						$f.find('input[name="heure_premiere_audience"]').val(d.heure || '');
					});
					$liste.append($b);
				});
				$zone.append($liste);
			}
		});
	}

	let minuteriePropositions = null;

	document.addEventListener('DOMContentLoaded', function () {
		$('.aff-form-poursuite').each(function () {
			const $f = $(this);
			$f.find('textarea[name="objet"]').on('input', function () { clearTimeout(minuteriePropositions); minuteriePropositions = setTimeout(function () { proposer_poursuite($f); }, 450); });
			$f.find('[data-numero]').on('input change', function () { clearTimeout(minuterieNumero); minuterieNumero = setTimeout(function () { controler_numero_poursuite($f); }, 350); });
			$f.find('[data-jonction], [data-nature]').on('change', function () { controler_numero_poursuite($f); });
			$f.find('select[name="chambre_id"]').on('change', function () { charger_dates_poursuite($f); });
			charger_dates_poursuite($f);
			controler_numero_poursuite($f);
		});
		/* Une saisie refusée rouvre la bonne fenêtre, avec ce qui avait été tapé et les messages. */
		@if (($ouvrirAuto ?? true) && $errors->any() && old('parties') !== null && old('registre') === 'penal')
			ouvrir_saisie_penal(true);
		@elseif (($ouvrirAuto ?? true) && $errors->any() && old('parties') !== null && old('registre') === 'instruction')
			ouvrir_saisie_instruction(true);
		@endif
	});

</script>
