<script type="text/javascript">

	/* ============================================================
	   DOSSIER NUMÉRIQUE : sommaire, visionneuse, ajout par glisser-déposer
	   ============================================================ */
	const DN_PIECES = @js($dossier['pieces']);
	const DN_TAILLE_MAX = {{ \App\Models\PieceDossier::tailleMaxKo() * 1024 }};
	const DN_TYPES = @js(collect(\App\Models\PieceDossier::TYPES)->map(fn ($t) => $t[0])->all());
	const DN_MOTS_TYPES = { assignation: 'assignation', requete: 'assignation', signification: 'signification', procuration: 'procuration', mandat: 'mandat', conclusion: 'conclusions', requisitoire: 'requisitoire', expertise: 'expertise', jugement: 'jugement', decision: 'jugement', ordonnance: 'ordonnance', audience: 'pv_audience', pv: 'pv_audience', piece: 'pieces_versees', bordereau: 'pieces_versees', lettre: 'correspondance', courrier: 'correspondance' };
	const DN_PEUT_VOIR_CONSULTATIONS = {{ \Illuminate\Support\Facades\Gate::allows('dossiers.modifier') ? 'true' : 'false' }};
	const DN_ZOOMS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 3, 4];

	let dnCourante = null;      /* pièce ouverte dans la visionneuse */
	let dnZoom = 'ajuste';      /* « ajuste » : l'image tient dans la fenêtre ; sinon un nombre (1 = taille réelle) */
	let dnRotation = 0;

	function dn_piece(id) { return DN_PIECES.filter(function (p) { return p.id === id; })[0] || null; }

	/** Les pièces visibles (celles que le filtre du sommaire laisse), dans l'ordre d'affichage. */
	function dn_visibles() { return $('#dn-liste .dn-piece:not([hidden])').map(function () { return parseInt($(this).attr('data-id'), 10); }).get(); }

	function dn_selectionner(id) {
		const p = dn_piece(id);
		if (!p) { return; }
		dnCourante = p; dnZoom = 'ajuste'; dnRotation = 0;
		$('#dn-liste .dn-piece').removeClass('is-actif').filter('[data-id="' + id + '"]').addClass('is-actif')[0].scrollIntoView({ block: 'nearest' });

		$('#dn-barre').prop('hidden', false);
		$('#dn-titre').text(p.titre);
		$('#dn-type').text(p.type_libelle + (p.date ? ' · ' + p.date : ''));
		$('#dn-telecharger').attr('href', p.url_telecharger);
		$('#dn-nouvel-onglet').attr('href', p.url);
		$('.dn-outils-image').prop('hidden', p.pdf);

		const $scene = $('#dn-scene').empty();
		if (p.pdf) {
			/* Le lecteur PDF du navigateur : défilement, recherche, impression */
			$scene.append($('<iframe class="dn-pdf" title="Pièce PDF">').attr('src', p.url + '#toolbar=1&navpanes=0&view=FitH'));
		} else {
			/* cadre qui défile > boîte à la taille de l'image (tournée ou non) > image centrée dans la boîte */
			const $cadre = $('<div class="dn-image-cadre" id="dn-image-cadre">');
			const $boite = $('<div class="dn-image-boite" id="dn-image-boite">');
			const $img = $('<img class="dn-image" id="dn-image" alt="">').attr('alt', p.titre).attr('src', p.url);
			$img.on('load', dn_appliquer_zoom);
			$scene.append($cadre.append($boite.append($img)));
		}
		dn_remplir_info(p);
		history.replaceState(null, '', '#dossier-' + id);
	}

	function dn_remplir_info(p) {
		$('#dn-i-titre').text(p.titre); $('#dn-i-type').text(p.type_libelle); $('#dn-i-date').text(p.date || 'Non indiquée');
		$('#dn-i-fichier').text(p.nom); $('#dn-i-taille').text(p.taille);
		$('#dn-i-depot').text((p.depose_le || '') + (p.depose_par ? ' par ' + p.depose_par : '')); $('#dn-i-empreinte').text(p.empreinte + '…');
		if (!DN_PEUT_VOIR_CONSULTATIONS) { return; }
		const $l = $('#dn-consultations').html('<li class="el-muted">…</li>');
		$.getJSON(p.url_consultations, function (r) {
			$l.empty();
			if (!r.consultations || !r.consultations.length) { $l.append($('<li class="el-muted">').text('Pas encore consultée.')); return; }
			r.consultations.forEach(function (c) { $l.append($('<li>').append($('<b>').text(c.qui)).append(document.createTextNode(' ' + c.action + ' la pièce le ' + c.quand))); });
		});
	}

	function dn_voisine(pas) {
		const ids = dn_visibles();
		if (!ids.length) { return; }
		const i = dnCourante ? ids.indexOf(dnCourante.id) : -1;
		dn_selectionner(ids[(i + pas + ids.length) % ids.length]);
	}

	/* ---------- image : zoom et rotation ---------- */
	function dn_appliquer_zoom() {
		const $img = $('#dn-image'), $cadre = $('#dn-image-cadre'), $boite = $('#dn-image-boite');
		if (!$img.length || !$img[0].naturalWidth) { return; }
		const tourne = dnRotation % 180 !== 0;
		const nw = $img[0].naturalWidth, nh = $img[0].naturalHeight;
		let echelle = dnZoom;
		if (dnZoom === 'ajuste') {
			echelle = Math.min(($cadre.innerWidth() - 32) / (tourne ? nh : nw), ($cadre.innerHeight() - 32) / (tourne ? nw : nh), 1);
		}
		$boite.css({ width: (tourne ? nh : nw) * echelle + 'px', height: (tourne ? nw : nh) * echelle + 'px' });
		$img.css({ width: nw * echelle + 'px', height: nh * echelle + 'px', transform: 'translate(-50%, -50%) rotate(' + dnRotation + 'deg)' });
		$('#dn-zoom').text(Math.round(echelle * 100) + ' %');
		dnEchelle = echelle;
	}
	let dnEchelle = 1;

	function dn_zoom(pas) {
		if (pas === 0) { dnZoom = 'ajuste'; }
		else {
			const actuel = dnEchelle;
			const suivant = pas > 0 ? DN_ZOOMS.filter(function (z) { return z > actuel + 0.01; })[0] : DN_ZOOMS.slice().reverse().filter(function (z) { return z < actuel - 0.01; })[0];
			dnZoom = suivant || actuel;
		}
		dn_appliquer_zoom();
	}

	function dn_pivoter() { dnRotation = (dnRotation + 90) % 360; dn_appliquer_zoom(); }

	function dn_plein_ecran() {
		const el = document.getElementById('dn-vue');
		if (document.fullscreenElement) { document.exitFullscreen(); } else if (el.requestFullscreen) { el.requestFullscreen(); }
	}

	function dn_info() { $('#dn-info').prop('hidden', function (i, v) { return !v; }); }

	/* ---------- recherche dans le sommaire ---------- */
	function dn_filtrer() {
		const mots = ($('#dn-recherche').val() || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').split(/\s+/).filter(Boolean);
		let trouvees = 0;
		$('#dn-liste .dn-piece').each(function () {
			const t = ($(this).attr('data-texte') || '').normalize('NFD').replace(/[̀-ͯ]/g, '');
			const ok = mots.every(function (m) { return t.indexOf(m) !== -1; });
			$(this).prop('hidden', !ok);
			if (ok) { trouvees++; }
		});
		$('#dn-liste .dn-groupe').each(function () { $(this).prop('hidden', $(this).find('.dn-piece:not([hidden])').length === 0); });
		$('#dn-aucun').prop('hidden', trouvees > 0 || DN_PIECES.length === 0);
	}

	/* ---------- ajout : choix des fichiers, un type, un titre et une date par fichier ---------- */
	function dn_type_probable(nom) {
		const t = nom.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
		for (const mot in DN_MOTS_TYPES) { if (t.indexOf(mot) !== -1) { return DN_MOTS_TYPES[mot]; } }
		return 'autre';
	}

	function dn_taille(octets) { return octets >= 1048576 ? (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.round(octets / 1024)) + ' Ko'; }

	function dn_ouvrir_ajout() {
		$('#dn-fichiers').val(''); $('#dn-lignes').empty(); $('#dn-envoyer').prop('disabled', true); dn_apercu(null);
		$('#modal-pieces').modal('show');
	}

	/* aperçu en grand du fichier choisi, avant l'envoi (le fichier reste sur l'ordinateur : simple lecture locale) */
	let dnUrlApercu = null;
	function dn_apercu(f) {
		const $a = $('#dn-apercu');
		$a.children('iframe, img').remove();
		if (dnUrlApercu) { URL.revokeObjectURL(dnUrlApercu); dnUrlApercu = null; }
		if (!f) { $a.prop('hidden', true); return; }
		dnUrlApercu = URL.createObjectURL(f);
		const pdf = /pdf$/i.test(f.name) || f.type === 'application/pdf';
		$a.prop('hidden', false).prepend(pdf ? $('<iframe title="Aperçu">').attr('src', dnUrlApercu) : $('<img alt="Aperçu">').attr('src', dnUrlApercu));
		$('#dn-apercu-nom').text(f.name);
	}

	function dn_lister_fichiers() {
		const fichiers = $('#dn-fichiers')[0].files;
		const $l = $('#dn-lignes').empty();
		let valides = 0;
		Array.prototype.forEach.call(fichiers, function (f, i) {
			const trop = f.size > DN_TAILLE_MAX;
			const $ligne = $('<div class="dn-ligne">').toggleClass('dn-ligne--erreur', trop);
			$ligne.append($('<div class="dn-ligne__nom">').append($('<i class="fa-solid ' + (/pdf$/i.test(f.name) ? 'fa-file-pdf' : 'fa-file-image') + '">')).append($('<span>').append($('<b>').text(f.name)).append($('<small>').text(trop ? 'Trop volumineux (' + dn_taille(f.size) + ')' : dn_taille(f.size)))));
			const $type = $('<select class="form-select" data-sans-recherche>').attr('name', 'meta[' + i + '][type]');
			const probable = dn_type_probable(f.name);
			for (const code in DN_TYPES) { $type.append($('<option>').val(code).text(DN_TYPES[code]).prop('selected', code === probable)); }
			$ligne.append($('<div class="dn-ligne__champ">').append($type));
			$ligne.append($('<div class="dn-ligne__champ">').append($('<input class="form-control" type="text" maxlength="200" placeholder="Titre">').attr('name', 'meta[' + i + '][titre]').val(f.name.replace(/\.[^.]+$/, ''))));
			$ligne.append($('<div class="dn-ligne__champ dn-ligne__champ--date">').append($('<input class="form-control" type="date">').attr('name', 'meta[' + i + '][date]').attr('max', new Date().toISOString().slice(0, 10))));
			$ligne.on('click', function (e) { if ($(e.target).is('input, select, textarea, option') || $(e.target).closest('.select2').length) { return; } $l.find('.dn-ligne').removeClass('is-vue'); $ligne.addClass('is-vue'); dn_apercu(f); });
			$l.append($ligne);
			if (!trop) { valides++; }
		});
		if (fichiers.length) { $l.find('.dn-ligne').first().addClass('is-vue'); dn_apercu(fichiers[0]); } else { dn_apercu(null); }
		/* un seul fichier trop gros bloque l'envoi : on le retire de la sélection plutôt que de le laisser échouer côté serveur */
		$('#dn-envoyer').prop('disabled', valides === 0 || valides !== fichiers.length);
		if (valides !== fichiers.length) { $l.prepend($('<div class="aff-erreur">').text('Retirez les fichiers trop volumineux (' + dn_taille(DN_TAILLE_MAX) + ' au plus) puis choisissez à nouveau.')); }
	}

	/* ---------- modification, retrait, localisation ---------- */
	function dn_ouvrir_modification() {
		if (!dnCourante) { return; }
		$('#form-piece').attr('action', dnCourante.url_modifier);
		$('#f_piece_titre').val(dnCourante.titre); $('#f_piece_type').val(dnCourante.type).trigger('change'); $('#f_piece_date').val(dnCourante.date_iso || '');
		$('#modal-piece').modal('show');
	}

	function dn_ouvrir_retrait() {
		if (!dnCourante) { return; }
		$('#form-retrait').attr('action', dnCourante.url_retirer); $('#dn-retrait-titre').text(dnCourante.titre); $('#f_retrait_motif').val('');
		$('#modal-retrait').modal('show');
	}

	function dn_ouvrir_localisation() { $('#modal-localisation').modal('show'); }

	document.addEventListener('DOMContentLoaded', function () {
		$('#dn-recherche').on('input', dn_filtrer);
		$('#dn-fichiers').on('change', dn_lister_fichiers);

		/* glisser-déposer : les fichiers lâchés sur le dossier partent dans la fenêtre d'ajout */
		const $dn = $('#dn');
		if ($('#dn-depot').length) {
			let compteur = 0;
			$dn.on('dragenter', function (e) { e.preventDefault(); compteur++; $dn.addClass('dn--depot'); });
			$dn.on('dragleave', function () { compteur = Math.max(0, compteur - 1); if (!compteur) { $dn.removeClass('dn--depot'); } });
			$dn.on('dragover', function (e) { e.preventDefault(); });
			$dn.on('drop', function (e) {
				e.preventDefault(); compteur = 0; $dn.removeClass('dn--depot');
				const fichiers = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
				if (!fichiers || !fichiers.length) { return; }
				dn_ouvrir_ajout();
				$('#dn-fichiers')[0].files = fichiers;
				dn_lister_fichiers();
			});
		}

		/* flèches du clavier : pièce précédente / suivante (hors saisie de texte) */
		$(document).on('keydown', function (e) {
			if ($('[data-onglet-contenu="dossier"]').prop('hidden') || $(e.target).is('input, textarea, select') || $('.modal.show').length) { return; }
			if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); dn_voisine(-1); }
			if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); dn_voisine(1); }
		});
		$(document).on('wheel', '#dn-image-cadre', function (e) { if (e.ctrlKey) { e.preventDefault(); dn_zoom(e.originalEvent.deltaY < 0 ? 1 : -1); } });
		$(window).on('resize', function () { if (dnZoom === 'ajuste') { dn_appliquer_zoom(); } });

		/* ouverture directe : #dossier ouvre l'onglet, #dossier-12 ouvre aussi la pièce 12 */
		const m = /^#dossier(?:-(\d+))?$/.exec(window.location.hash);
		if (m) {
			afficher_onglet('dossier');
			if (m[1] && dn_piece(parseInt(m[1], 10))) { dn_selectionner(parseInt(m[1], 10)); }
		}
	});

</script>
