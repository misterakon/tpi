<script type="text/javascript">

	/* ---------- onglets de la fiche (informations, dossier numérique, scellés) ---------- */
	function afficher_onglet(nom) {
		$('[data-onglet-bouton]').removeClass('is-actif').attr('aria-selected', 'false').filter('[data-onglet-bouton="' + nom + '"]').addClass('is-actif').attr('aria-selected', 'true');
		$('[data-onglet-contenu]').prop('hidden', true).filter('[data-onglet-contenu="' + nom + '"]').prop('hidden', false);
	}
	document.addEventListener('DOMContentLoaded', function () {
		if (location.hash === '#scelles' && $('[data-onglet-contenu="scelles"]').length) { afficher_onglet('scelles'); }
	});

	function corriger_numero() {
		$('#f_nouveau_numero').val(''); $('#f_motif_numero').val('');
		$('#modal-numero').modal('show');
	}

	function annuler_affaire() {
		$('#f_motif_annulation').val('');
		$('#modal-annuler').modal('show');
	}

	function joindre_affaire() {
		$('#f_affaire_a_joindre').val('').trigger('change');
		$('#modal-joindre').modal('show');
	}

	function rediger_decision() {
		$('#f_modele_decision').val('').trigger('change');
		$('#modal-rediger').modal('show');
	}

	function reattribuer_affaire() {
		$('#f_chambre_reattribution').val('').trigger('change');
		$('#f_motif_reattribution').val('');
		$('#modal-reattribuer').modal('show');
	}

</script>
