<script type="text/javascript">

	const APP_NOM = @js(config('app.name'));

	/* ============================================================
	   FORMULAIRE : création / modification
	   ============================================================ */
	function add_form() {
		$('.modal_form')[0].reset();
		restaurer_natures();
		$('[name="id_"]').val('');
		$('[name="etape"], [name="acteur"], [name="registre"], [name="nature_affaire_id"], [name="unite"], [name="point_depart"]').val('').trigger('change.select2');
		$('.modal_form input[type="checkbox"][data-defaut="1"]').prop('checked', true);
		nomAuto = ''; touche = {};
		maj_delai(true);
		$('#titre-modal-delais_procedure').text("Nouveau : délai d'une étape");
		$('#BtnEnregistrer').html('ENREGISTRER');
		$('.submit').attr('disabled', false);
		$('#modal-delais_procedure').modal('show');
	}

	function edit_delai_procedure(id) {
		$('.modal_form')[0].reset();
		$('#titre-modal-delais_procedure').text("Modifier : délai d'une étape");
		$('#BtnEnregistrer').html('MODIFIER');

		$.ajax({
			url: "{{ route('referentiels.delais_procedure.get', ':id') }}".replace(':id', id),
			type: "GET",
			dataType: "JSON",
			success: function (data) {
				restaurer_natures();
				$('[name="id_"]').val(data.id);
				$('[name="libelle"]').val(data.libelle === null ? '' : data.libelle);
				$('[name="etape"]').val(data.etape === null ? '' : data.etape).trigger('change.select2');
				$('[name="acteur"]').val(data.acteur === null ? '' : data.acteur).trigger('change.select2');
				$('[name="registre"]').val(data.registre === null ? '' : data.registre).trigger('change.select2');
				$('[name="nature_affaire_id"]').val(data.nature_affaire_id === null ? '' : data.nature_affaire_id).trigger('change.select2');
				$('[name="valeur"]').val(data.valeur === null ? '' : data.valeur);
				$('[name="unite"]').val(data.unite === null ? '' : data.unite).trigger('change.select2');
				$('[name="renvois_max"]').val(data.renvois_max === null ? '' : data.renvois_max);
				$('[name="point_depart"]').val(data.point_depart === null ? '' : data.point_depart).trigger('change.select2');
				$('[name="actif"]').prop('checked', !!data.actif);
				$('[name="a_valider"]').prop('checked', !!data.a_valider);
				nomAuto = null; touche = { unite: true, point_depart: true };
				maj_delai(false);
				$('.submit').removeClass('disabled').attr('disabled', false);
				$('#modal-delais_procedure').modal('show');
			},
			error: function () {
				Swal.fire({ icon: 'error', title: APP_NOM, text: 'Erreur lors du chargement des données.' });
			}
		});
	}


	/* ============================================================
	   FORMULAIRE DYNAMIQUE : suggestions, champs qui s'adaptent, résumé en direct
	   ============================================================ */
	const ETAPES = @js(\App\Models\DelaiProcedure::ETAPES);
	const ACTEURS = @js(\App\Models\DelaiProcedure::ACTEURS);
	const UNITES = @js(\App\Models\DelaiProcedure::UNITES);
	const POINTS = @js(\App\Models\DelaiProcedure::POINTS_DEPART);
	const REGISTRES = @js(\App\Models\NatureAffaire::REGISTRES);
	const REGISTRE_DE_NATURE = @js($registresNature);

	/* Suggestions selon l'étape (modifiables) : point de départ, unité, et si le champ « renvois » a un sens. */
	const PAR_ETAPE = {
		procedure_complete: { depart: 'ouverture_audience', unite: 'mois', renvois: true, acteur: false },
		enrolement: { depart: 'reception', unite: 'jours_ouvrables' },
		programmation: { depart: 'enrolement', unite: 'jours_ouvrables' },
		role: { depart: 'etape_precedente', unite: 'jours_ouvrables' },
		audience: { depart: 'ouverture_audience', unite: 'jours_ouvrables', renvois: true },
		renvoi_delibere: { depart: 'ouverture_audience', unite: 'jours', renvois: true },
		saisie_decision: { depart: 'decision_rendue', unite: 'jours_ouvrables' },
		redaction: { depart: 'mise_en_delibere', unite: 'jours_ouvrables' },
		cloture: { depart: 'decision_rendue', unite: 'jours_ouvrables' },
		remise: { depart: 'decision_rendue', unite: 'jours_ouvrables' }
	};

	let nomAuto = '';   /* dernier nom proposé ; null = nom saisi à la main */
	let touche = {};    /* champs que l'utilisateur a déjà choisis lui-même */

	function sansParentheses(t) { return String(t || '').replace(/\s*\([^)]*\)\s*$/, ''); }
	function minuscule(t) { t = String(t || ''); return t.charAt(0).toLowerCase() + t.slice(1); }

	function texte_select(nom) {
		const v = $('[name="' + nom + '"]').val();
		return v ? $('[name="' + nom + '"] option:selected').text() : '';
	}

	/* Natures d'affaire : on ne propose que celles du registre choisi (le registre est écrit entre parenthèses dans le libellé). */
	let toutesNatures = null;
	function memoriser_natures() {
		if (toutesNatures) { return; }
		toutesNatures = $('#f_nature_affaire_id option').map(function () { return { v: this.value, t: $(this).text() }; }).get();
	}
	function restaurer_natures() {
		memoriser_natures();
		const $s = $('#f_nature_affaire_id'), garde = $s.val();
		$s.empty();
		toutesNatures.forEach(function (o) { $s.append($('<option>').val(o.v).text(o.t)); });
		$s.val(garde).trigger('change.select2');
	}
	function filtrer_natures() {
		memoriser_natures();
		const reg = $('#f_registre').val(), $s = $('#f_nature_affaire_id'), garde = $s.val();
		$s.empty();
		toutesNatures.forEach(function (o) {
			const m = /\((RG|RP|RI|RE)\)\s*$/.exec(o.t);
			if (o.v === '' || !reg || (m && m[1] === reg)) { $s.append($('<option>').val(o.v).text(o.t)); }
		});
		$s.val($s.find('option[value="' + garde + '"]').length ? garde : '').trigger('change.select2');
	}

	function maj_delai(proposer) {
		const etape = $('#f_etape').val();
		const regle = PAR_ETAPE[etape] || {};

		/* Une procédure complète n'a pas de responsable unique ; les renvois ne comptent que pour certaines étapes. */
		const acteurUtile = etape !== 'procedure_complete';
		$('#bloc-acteur').toggle(acteurUtile);
		if (!acteurUtile) { $('#f_acteur').val('').trigger('change.select2'); }
		$('#bloc-renvois').toggle(!!regle.renvois);
		if (!regle.renvois) { $('#f_renvois_max').val(''); }

		/* Suggestions pour une nouvelle règle : on ne touche pas à ce que l'utilisateur a déjà choisi. */
		if (proposer && etape) {
			if (!touche.point_depart && regle.depart) { $('#f_point_depart').val(regle.depart).trigger('change.select2'); }
			if (!touche.unite && regle.unite) { $('#f_unite').val(regle.unite).trigger('change.select2'); }
		}

		/* Le registre se déduit de la nature d'affaire. */
		const nature = $('#f_nature_affaire_id').val();
		if (nature) {
			$('#f_registre').val(REGISTRE_DE_NATURE[nature] || '').prop('disabled', true).trigger('change.select2');
			$('#aide-registre').text('(déduit de la nature d\'affaire)');
		} else {
			$('#f_registre').prop('disabled', false).trigger('change.select2');
			$('#aide-registre').text('(facultatif)');
		}

		/* Nom proposé tant que l'utilisateur n'a pas écrit le sien. */
		const nomActuel = $('#f_libelle').val();
		if (nomAuto !== null && (nomActuel === '' || nomActuel === nomAuto)) {
			const cible = nature ? sansParentheses(texte_select('nature_affaire_id')) : sansParentheses(texte_select('registre'));
			const base = sansParentheses(texte_select('etape')) || '';
			nomAuto = base ? base + (cible ? ' – ' + cible : '') : '';
			$('#f_libelle').val(nomAuto);
		}

		filtrer_natures();
		resume_delai();
	}

	function resume_delai() {
		const etape = ETAPES[$('#f_etape').val()];
		const valeur = $('#f_valeur').val(), unite = UNITES[$('#f_unite').val()], depart = POINTS[$('#f_point_depart').val()];
		const acteur = ACTEURS[$('#f_acteur').val()], renvois = $('#f_renvois_max').val();
		const nature = $('#f_nature_affaire_id').val(), registre = $('#f_registre').val();
		const lignes = [];

		if (!etape) {
			$('#apercu-texte').text("Choisissez l'étape : le reste se propose tout seul.");
			return;
		}
		lignes.push('Étape : ' + sansParentheses(etape) + (acteur ? ' — à faire par : ' + acteur : ''));
		lignes.push('Délai : ' + (valeur ? valeur + ' ' + (unite || '') + (depart ? ' à partir de ' + minuscule(depart) : '') : 'à saisir' + (depart ? ', à compter de ' + minuscule(depart) : '')));
		if (renvois !== '' && renvois !== null && $('#bloc-renvois').is(':visible')) { lignes.push('Renvois : ' + renvois + ' au plus'); }
		lignes.push('S\'applique à : ' + (nature ? sansParentheses(texte_select('nature_affaire_id')) : (registre ? REGISTRES[registre] : 'toutes les affaires')));

		const $t = $('#apercu-texte').empty().removeClass('el-muted');
		lignes.forEach(function (l) { $t.append($('<div>').text(l)); });
	}

	/* jQuery est chargé en bas de page, après ce script : les écouteurs s'installent une fois la page prête. */
	document.addEventListener('DOMContentLoaded', function () {
		$(document).on('change', '#f_etape', function () { maj_delai(true); });
		$(document).on('change', '#f_nature_affaire_id, #f_registre', function () { maj_delai(false); });
		$(document).on('change', '#f_unite', function () { touche.unite = true; resume_delai(); });
		$(document).on('change', '#f_point_depart', function () { touche.point_depart = true; resume_delai(); });
		$(document).on('change', '#f_acteur', resume_delai);
		$(document).on('input', '#f_valeur, #f_renvois_max', resume_delai);
		$(document).on('input', '#f_libelle', function () { if ($(this).val() !== nomAuto) { nomAuto = null; } });
		$(document).on('submit', '.modal_form', function () { $('#f_registre').prop('disabled', false); });
	});

	/* ============================================================
	   ACTION : supprimer
	   ============================================================ */
	function delete_delai_procedure(id) {
		Swal.fire({
			title: APP_NOM,
			text: "Supprimer cet élément ?",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#00794C',
			cancelButtonColor: '#d33',
			cancelButtonText: 'Annuler',
			confirmButtonText: 'Oui, supprimer'
		}).then((result) => {
			if (!result.isConfirmed) { return; }
			$.ajax({
				url: "{{ route('referentiels.delais_procedure.delete', ':id') }}".replace(':id', id),
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

</script>
