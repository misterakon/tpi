{{-- Affichage d'une alerte (page « Mes notifications » et suivi des alertes) et justification d'un retard. Expose window.AL. Variable : $peutJustifier --}}
<script type="text/javascript">
window.AL = (function () {
	'use strict';

	const URL_JUSTIFIER = @js(route('alertes.justifier', 0));
	const CSRF = @js(csrf_token());
	const PEUT = @js($peutJustifier);
	const NIVEAUX = { info: 'Information', attention: 'Attention', critique: 'Critique' };
	const ICONES = { securite: 'fa-shield-halved', mise_au_role: 'fa-folder-plus', publication_role: 'fa-bullhorn', feuille_a_saisir: 'fa-clipboard-list', renvoi: 'fa-arrows-rotate', delibere: 'fa-hourglass-half', redaction: 'fa-pen-nib', jamais_portee: 'fa-calendar-xmark', delai_procedure: 'fa-stopwatch', detention: 'fa-lock', limite_renvois: 'fa-ban', repartition_requete: 'fa-share-nodes', validation_decision: 'fa-circle-check', signature_decision: 'fa-signature', minute_a_transmettre: 'fa-file-export', certificat_recours: 'fa-stamp' };
	let courante = null, apres = null;

	const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	const toast = function (icone, titre) { if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: icone, title: titre, showConfirmButton: false, timer: 3200, timerProgressBar: true }); } };

	/* Une alerte (a) : niveau, titre, action à mener, échéance, retard, état. options : { lue, nouvelle, ouvrir, lire, motifVisible } */
	function carte(a, o) {
		o = o || {};
		const resolue = a.statut === 'resolue', justifiee = a.statut === 'justifiee';
		const meta = [];
		if (a.echeance) { meta.push('<span><i class="fa-regular fa-calendar"></i>Échéance ' + esc(a.echeance) + '</span>'); }
		if (a.chambre) { meta.push('<span><i class="fa-solid fa-landmark"></i>' + esc(a.chambre) + '</span>'); }
		if (resolue) { meta.push('<span class="al-ok"><i class="fa-solid fa-check"></i>Refermée' + (a.resolue_le ? ' le ' + esc(a.resolue_le) : '') + ' : action faite</span>'); }
		else if (justifiee) { meta.push('<span class="al-just"><i class="fa-solid fa-user-clock"></i>Retard justifié jusqu\'au ' + esc(a.justifiee_jusqu) + '</span>'); }
		else if (a.retard > 0) { meta.push('<span class="al-retard"><i class="fa-solid fa-triangle-exclamation"></i>En retard de ' + a.retard + ' jour' + (a.retard > 1 ? 's' : '') + '</span>'); }
		if (o.orphelin) { meta.push('<span class="al-orphelin">Aucun destinataire désigné : à compléter dans les comptes</span>'); }
		const motif = a.justification ? '<div class="al-motif"><b>Justification' + (a.justifiee_par ? ' de ' + esc(a.justifiee_par) : '') + ' :</b> ' + esc(a.justification) + '</div>' : '';
		const actions = [];
		if (a.url || o.ouvrir) { actions.push('<a class="au-btn au-btn--vert" href="' + esc(o.ouvrir || a.url) + '"><i class="fa-solid fa-arrow-right"></i>Traiter</a>'); }
		if (PEUT && !resolue && a.id) { actions.push('<button type="button" class="au-btn au-btn--clair" data-act="justifier" data-alerte="' + a.id + '"><i class="fa-solid fa-user-clock"></i>' + (justifiee ? 'Modifier la justification' : 'Justifier le retard') + '</button>'); }
		if (o.lire) { actions.push('<button type="button" class="au-btn au-btn--gris" data-act="lire" data-notif="' + esc(o.lire) + '"><i class="fa-solid fa-check"></i>Marquer comme lue</button>'); }
		return '<article class="al-carte al-carte--' + esc(a.niveau) + (resolue ? ' is-resolue' : '') + (o.lue ? ' is-lue' : '') + '" data-alerte="' + (a.id || '') + '">'
			+ '<div class="al-ico"><i class="fa-solid ' + (ICONES[a.type] || 'fa-bell') + '"></i></div>'
			+ '<div class="al-corps"><div class="al-ligne1">' + (o.nouvelle ? '<span class="al-nouvelle" title="Non lue"></span>' : '') + '<span class="al-niveau"><i class="fa-solid fa-bell"></i>' + esc(NIVEAUX[a.niveau] || a.niveau) + (a.regle ? ' · ' + esc(a.regle) : '') + '</span>' + (a.type_libelle ? '<span class="al-type">' + esc(a.type_libelle) + '</span>' : '') + '</div>'
			+ '<div class="al-titre">' + esc(a.titre) + '</div><div class="al-action"><i class="fa-solid fa-circle-arrow-right"></i><span>À faire : ' + esc(a.action) + '</span></div>'
			+ '<div class="al-meta">' + meta.join('') + '</div>' + motif + '</div>'
			+ '<div class="al-actions">' + actions.join('') + '</div></article>';
	}

	function pagination(sel, total, page, parPage, noms) {
		const pages = Math.ceil(total / parPage);
		if (!total) { $(sel).empty(); return; }
		const debut = (page - 1) * parPage + 1, fin = Math.min(total, page * parPage), liste = [];
		for (let i = 1; i <= pages; i++) { if (i === 1 || i === pages || Math.abs(i - page) <= 2) { liste.push(i); } }
		let nav = '<button type="button" data-p="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="Page précédente"><i class="fa-solid fa-angle-left"></i></button>', prec = 0;
		liste.forEach(function (i) { if (i - prec > 1) { nav += '<span class="au-pagination__points">…</span>'; } nav += '<button type="button" data-p="' + i + '"' + (i === page ? ' class="is-actif" aria-current="page"' : '') + '>' + i + '</button>'; prec = i; });
		nav += '<button type="button" data-p="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + ' aria-label="Page suivante"><i class="fa-solid fa-angle-right"></i></button>';
		$(sel).html('<span>' + debut + ' à ' + fin + ' sur ' + total + ' ' + noms[total > 1 ? 1 : 0] + '</span><nav>' + nav + '</nav>');
	}

	/* ------------------------------------------------------------ justifier un retard */
	function ouvrirJustification(alerte, titre, motifActuel, rappel) {
		courante = alerte; $('#justif-rappel').text(rappel || titre); $('#f_justif_motif').val(motifActuel || ''); $('#f_justif_jours').val('7').trigger('change.select2'); $('#erreur-justification').empty();
		$('#modal-justification').modal('show');
	}

	function envoyer() {
		if (!courante) { return; }
		$('#erreur-justification').empty(); $('#valider-justification').prop('disabled', true);
		fetch(URL_JUSTIFIER.replace(/\/0\/justifier$/, '/' + courante + '/justifier'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ motif: $('#f_justif_motif').val(), jours: $('#f_justif_jours').val() }) })
			.then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { throw new Error((j.errors ? Object.values(j.errors)[0][0] : j.message) || 'Erreur'); } return j; }); })
			.then(function (j) { $('#modal-justification').modal('hide'); toast('success', j.message); if (apres) { apres(j.alerte); } })
			.catch(function (e) { $('#erreur-justification').html('<div class="au-erreur"><i class="fa-solid fa-circle-exclamation"></i> ' + esc(e.message) + '</div>'); })
			.then(function () { $('#valider-justification').prop('disabled', false); });
	}

	document.addEventListener('DOMContentLoaded', function () { $('#form-justification').on('submit', function (e) { e.preventDefault(); envoyer(); }); });

	return { carte: carte, pagination: pagination, esc: esc, toast: toast, justifier: ouvrirJustification, apresJustification: function (f) { apres = f; } };
})();
</script>
