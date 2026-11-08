<script type="text/javascript">
	const PEUT = @js($peutModifier), CSRF = "{{ csrf_token() }}";
	const U = {
		show: "{{ route('instruction.show', ':id') }}", dossier: "{{ route('instruction.dossier', ':id') }}", inculpe: "{{ route('instruction.inculpe', ':id') }}", victime: "{{ route('instruction.victime', ':id') }}",
		cr: "{{ route('instruction.commissions') }}", crStore: "{{ route('instruction.commissions.store') }}"
	};
	const STATUTS = @js($statuts), NATURES = @js($natures);
	const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	let courant = null;

	function champ(nom, label, valeur, type, extra) {
		extra = extra || '';
		const dis = PEUT ? '' : ' disabled';
		if (type === 'select') { return '<div class="el-c4"><label class="form-label"><b>' + label + '</b></label><select class="form-select" name="' + nom + '" data-sans-recherche' + dis + '><option value="">—</option>' + Object.keys(extra).map(function (k) { return '<option value="' + k + '"' + (valeur === k ? ' selected' : '') + '>' + esc(extra[k]) + '</option>'; }).join('') + '</select></div>'; }
		return '<div class="el-c4"><label class="form-label"><b>' + label + '</b></label><input class="form-control" type="' + (type || 'text') + '" name="' + nom + '" value="' + esc(valeur) + '"' + dis + (type === 'date' ? ' max="' + new Date().toISOString().slice(0, 10) + '"' : '') + (type === 'number' ? ' min="1"' : '') + '></div>';
	}
	function bouton(cible) { return PEUT ? '<div class="el-c12" style="text-align:right"><button type="button" class="el-btn el-btn--accent" data-enr="' + cible + '"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button></div>' : ''; }

	function ouvrir_dossier(id) {
		$('#e-dossier').prop('hidden', true);
		$.getJSON(U.show.replace(':id', id), function (d) {
			courant = d; const f = d.dossier || {};
			$('#t-dossier').text('Dossier ' + d.numero);
			$('#d-fiche').html('<form class="el-formgrid" data-cible="dossier" data-id="' + d.id + '"><div class="el-c12"><div class="aff-info"><i class="fa-solid fa-circle-info"></i><span><b>' + esc(d.objet) + '</b>' + (d.articles ? ' · ' + esc(d.articles) : '') + ' · saisi le ' + esc(d.date_ri) + '</span></div></div>' +
				champ('date_requisitoire', 'Date du réquisitoire introductif', f.date_requisitoire, 'date') + champ('textes_vises', 'Textes visés', f.textes_vises, 'text') + champ('date_faits', 'Date des faits', f.date_faits, 'date') +
				champ('lieu_faits', 'Lieu des faits', f.lieu_faits, 'text') + champ('nature_infraction', "Nature de l'infraction", f.nature_infraction, 'select', NATURES) + champ('infraction_specifique', 'Infraction spécifique', f.infraction_specifique, 'text') +
				champ('autre_specificite', 'Autre spécificité', f.autre_specificite, 'text') + champ('infraction_retenue', "Infraction retenue au terme de l'instruction", f.infraction_retenue, 'text') + champ('autres_ordonnances', 'Autres ordonnances', f.autres_ordonnances, 'text') +
				champ('date_cloture', "Date de l'ordonnance de clôture", f.date_cloture, 'date') + champ('nature_cloture', "Nature de l'ordonnance de clôture", f.nature_cloture, 'text') + champ('date_transmission_parquet', 'Transmission du dossier au parquet', f.date_transmission_parquet, 'date') +
				'<div class="el-c12"><label class="form-label"><b>Observations</b></label><input class="form-control" type="text" name="observations" maxlength="500" value="' + esc(f.observations) + '"' + (PEUT ? '' : ' disabled') + '></div>' + bouton('fiche') + '</form>');
			$('#n-inc').text(d.inculpes.length); $('#n-vic').text(d.victimes.length);
			$('#d-inculpes').html(d.inculpes.length ? d.inculpes.map(function (p) {
				const s = p.suivi || {};
				return '<form class="el-formgrid st-qualifier" style="display:block;background:#fff;border-color:#e3ebe6" data-cible="inculpe" data-id="' + p.id + '"><div class="el-person__name" style="margin-bottom:6px"><b>' + esc(p.nom) + '</b> <span class="el-muted">' + (p.sexe === 'F' ? 'féminin' : 'masculin') + (p.age != null ? ', ' + p.age + ' ans' : '') + (p.mandat ? ' · mandat de dépôt du ' + esc(p.mandat) : '') + '</span></div><div class="el-formgrid">' +
					champ('statut', "Statut de l'inculpé", s.statut, 'select', STATUTS) + champ('precisions_statut', 'Précisions sur le statut', s.precisions_statut, 'text') + champ('date_ogp', 'Date de l\'OGP (mineur)', s.date_ogp, 'date') +
					champ('date_titre_detention', 'Date du titre de détention', s.date_titre_detention, 'date') + champ('duree_detention_mois', '1re durée de détention (mois)', s.duree_detention_mois, 'number') + champ('date_renouvellement', 'Date de renouvellement', s.date_renouvellement, 'date') +
					champ('duree_renouvellement_mois', 'Durée du renouvellement (mois)', s.duree_renouvellement_mois, 'number') + champ('date_liberte_provisoire', 'Ordonnance de liberté provisoire', s.date_liberte_provisoire, 'date') + champ('date_mainlevee_cj', 'Mainlevée du contrôle judiciaire', s.date_mainlevee_cj, 'date') +
					champ('date_cloture', "Date de l'ordonnance de clôture", s.date_cloture, 'date') + champ('nature_cloture', 'Nature de l\'ordonnance de clôture', s.nature_cloture, 'text') + champ('observations', 'Observations', s.observations, 'text') +
					'<div class="el-c12"><label class="aff-interrupteur"><input type="checkbox" name="traite" value="1"' + (s.traite ? ' checked' : '') + (PEUT ? '' : ' disabled') + '><span class="aff-interrupteur__piste" aria-hidden="true"></span><span class="aff-interrupteur__texte"><b>Inculpé pour traite des personnes</b></span></label></div>' + bouton('inculpe') + '</div></form>';
			}).join('') : '<div class="st-vide">Aucun inculpé enregistré : ajoutez-les dans la fiche de l\'affaire.</div>');
			$('#d-victimes').html(d.victimes.length ? d.victimes.map(function (p) {
				const s = p.suivi || {};
				return '<form class="el-formgrid st-qualifier" style="display:block;background:#fff;border-color:#e3ebe6" data-cible="victime" data-id="' + p.id + '"><div class="el-person__name" style="margin-bottom:6px"><b>' + esc(p.nom) + '</b></div><div class="el-formgrid">' +
					champ('infraction_subie', 'Infraction subie', s.infraction_subie, 'text') + champ('audition_1', 'Date de la 1re audition', s.audition_1, 'date') + champ('audition_2', 'Date de la 2e audition', s.audition_2, 'date') + champ('audition_3', 'Date de la 3e audition', s.audition_3, 'date') +
					champ('date_cloture', "Date de l'ordonnance de clôture", s.date_cloture, 'date') + champ('date_notification_cloture', 'Notification de la clôture', s.date_notification_cloture, 'date') + champ('observations', 'Observations', s.observations, 'text') +
					'<div class="el-c12"><label class="aff-interrupteur"><input type="checkbox" name="traite" value="1"' + (s.traite ? ' checked' : '') + (PEUT ? '' : ' disabled') + '><span class="aff-interrupteur__piste" aria-hidden="true"></span><span class="aff-interrupteur__texte"><b>Victime de traite des personnes</b></span></label></div>' + bouton('victime') + '</div></form>';
			}).join('') : '<div class="st-vide">Aucune victime enregistrée.</div>');
			$('#onglets-dossier .st-onglet').removeClass('is-actif').first().addClass('is-actif'); $('.d-pan').prop('hidden', true); $('#d-fiche').prop('hidden', false);
			$('#modal-dossier').modal('show');
		}).fail(function () { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Dossier introuvable ou hors de votre périmètre.' }); });
	}

	$(document).on('click', '#onglets-dossier .st-onglet', function () {
		$('#onglets-dossier .st-onglet').removeClass('is-actif'); $(this).addClass('is-actif'); $('.d-pan').prop('hidden', true); $('#' + $(this).data('pan')).prop('hidden', false);
	});
	$(document).on('click', '[data-enr]', function () {
		const $f = $(this).closest('form'), cible = $f.data('cible'), id = $f.data('id');
		const donnees = $f.serializeArray(); donnees.push({ name: '_token', value: CSRF });
		$('#e-dossier').prop('hidden', true);
		$.ajax({ url: U[cible].replace(':id', id), type: 'POST', data: donnees, dataType: 'json' }).done(function (r) {
			Swal.fire({ icon: 'success', title: r.message, timer: 1400, showConfirmButton: false });
		}).fail(function (x) {
			const j = x.responseJSON || {}; const m = j.errors ? Object.values(j.errors)[0][0] : (j.message || 'Enregistrement impossible.');
			$('#e-dossier').prop('hidden', false).text(m);
		});
	});

	/* ---------- Commissions rogatoires ---------- */
	const MOIS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
	let crDonnees = null;
	function ouvrir_cr() { $('#modal-cr').modal('show'); charger_cr(); }
	function charger_cr() {
		$.getJSON(U.cr, { chambre_id: $('#cr_chambre').val(), annee: $('#cr_annee').val() }, function (d) { crDonnees = d; afficher_cr(); }).fail(function () { $('#tbl-cr tbody').html('<tr><td colspan="5">Aucun cabinet accessible.</td></tr>'); });
	}
	function afficher_cr() {
		const l = crDonnees.lignes[$('#cr_sens').val() + '|' + $('#cr_portee').val()];
		$('#tbl-cr tbody').html(l.map(function (m) {
			const i = function (n, v) { return '<td><input class="form-control" type="number" min="0" max="9999" data-m="' + m.mois + '" data-c="' + n + '" value="' + v + '"' + (PEUT ? '' : ' disabled') + '></td>'; };
			return '<tr><td><b>' + MOIS[m.mois - 1] + '</b></td>' + i('stock_initial', m.stock_initial) + i('nouvelles', m.nouvelles) + i('executees', m.executees) + '<td data-reste="' + m.mois + '">' + Math.max(0, m.stock_initial + m.nouvelles - m.executees) + '</td></tr>';
		}).join(''));
	}
	$(document).on('change', '#cr_chambre, #cr_annee', charger_cr);
	$(document).on('change', '#cr_sens, #cr_portee', afficher_cr);
	$(document).on('input', '#tbl-cr input', function () {
		const m = $(this).data('m'), v = function (c) { return +($('#tbl-cr input[data-m="' + m + '"][data-c="' + c + '"]').val() || 0); };
		$('[data-reste="' + m + '"]').text(Math.max(0, v('stock_initial') + v('nouvelles') - v('executees')));
	});
	function enregistrer_cr() {
		const mois = {};
		$('#tbl-cr input').each(function () { const m = $(this).data('m'); (mois[m] = mois[m] || {})[$(this).data('c')] = $(this).val() || 0; });
		$('#e-cr').prop('hidden', true);
		$.ajax({ url: U.crStore, type: 'POST', dataType: 'json', data: { _token: CSRF, chambre_id: $('#cr_chambre').val(), annee: $('#cr_annee').val(), sens: $('#cr_sens').val(), portee: $('#cr_portee').val(), mois: mois } })
			.done(function (r) { Swal.fire({ icon: 'success', title: r.message, timer: 1400, showConfirmButton: false }); charger_cr(); })
			.fail(function (x) { const j = x.responseJSON || {}; $('#e-cr').prop('hidden', false).text(j.errors ? Object.values(j.errors)[0][0] : (j.message || 'Enregistrement impossible.')); });
	}
</script>
