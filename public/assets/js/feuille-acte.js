/*
 * Aperçu d'un acte de décision sur des pages A4, comme dans le document Word du tribunal :
 *   - le timbre (cour d'appel, tribunal, numéros, parties) dans la colonne de gauche de la première page ;
 *   - la qualité, en retrait à droite du timbre, puis le factum sur toute la largeur ;
 *   - police, tailles, marges et retrait pris dans les réglages « Impression des actes ».
 * Le texte est découpé en pages par le navigateur lui-même, ligne par ligne (colonnes CSS), comme le fait Word.
 *
 *   FeuilleActe.afficher(conteneur, { entete: '<p>…</p>', qualite: '<p>…</p>', factum: '<p>…</p>' }, miseEnPage, { couleurs: false })
 */
(function (window, document) {
	'use strict';

	var MM = 'mm';
	var HAUTEUR_NATURELLE = 1.14;

	function el(classe, html) {
		var d = document.createElement('div');
		d.className = classe;
		if (html !== undefined) { d.innerHTML = html; }
		return d;
	}

	function police(nom) {
		return "'" + String(nom).replace(/[^\p{L}0-9 .\-]/gu, '') + "', Garamond, 'EB Garamond', Georgia, serif";
	}

	/** Un flux de texte (qualité puis factum) réparti en colonnes de la largeur du corps de page. */
	function creerFlux(parties, c, colonnes) {
		var flux = el('acte-flux');
		var largeurCorps = 210 - c.margeGauche - c.margeDroite;
		var hauteurCorps = 297 - c.margeHaut - c.margeBas;
		flux.style.width = (colonnes * largeurCorps) + MM;
		flux.style.height = hauteurCorps + MM;
		flux.style.columnWidth = largeurCorps + MM;
		flux.style.columnCount = colonnes;
		flux.style.fontFamily = police(c.police);
		flux.style.fontSize = c.taille + 'pt';

		// L'interligne de Word est un pourcentage de la hauteur naturelle de la police (environ 1,14 fois sa taille).
		var q = el('acte-q', parties.qualite || '');
		q.style.marginLeft = c.retrait + MM;
		q.style.lineHeight = (c.interligneQualite / 100) * HAUTEUR_NATURELLE;
		var f = el('acte-f', parties.factum || '');
		f.style.lineHeight = (c.interligneFactum / 100) * HAUTEUR_NATURELLE;
		flux.appendChild(q);
		flux.appendChild(f);
		return flux;
	}

	function afficher(conteneur, parties, c, options) {
		options = options || {};
		conteneur.innerHTML = '';

		var vide = !(parties.entete || parties.qualite || parties.factum);
		if (vide) { conteneur.appendChild(el('acte-vide', 'Rien à afficher : le modèle est encore vide.')); return; }

		var largeurCorps = 210 - c.margeGauche - c.margeDroite;
		var hauteurCorps = 297 - c.margeHaut - c.margeBas;

		var zone = el('acte-zone' + (options.couleurs ? ' acte-couleurs' : ''));
		var cadre = el('acte-cadre');
		var pages = el('acte-pages');
		cadre.appendChild(pages);
		zone.appendChild(cadre);
		conteneur.appendChild(zone);

		// 1. Mesure : on laisse le navigateur répartir le texte sur autant de colonnes que nécessaire (au plus 60 pages).
		var mesure = el('acte-pages');
		mesure.style.cssText = 'position:absolute; left:-99999px; top:0; visibility:hidden';
		var fluxMesure = creerFlux(parties, c, 60);
		mesure.appendChild(fluxMesure);
		document.body.appendChild(mesure);
		var plage = document.createRange();
		plage.selectNodeContents(fluxMesure);
		var droite = -Infinity, gauche = fluxMesure.getBoundingClientRect().left;
		Array.prototype.forEach.call(plage.getClientRects(), function (r) { droite = Math.max(droite, r.right); });
		var pxParMm = fluxMesure.getBoundingClientRect().width / (60 * largeurCorps);
		var nb = Math.max(1, Math.min(60, Math.ceil(((droite - gauche) / pxParMm) / largeurCorps - 0.001)));
		document.body.removeChild(mesure);

		// 2. Les pages : chacune montre une fenêtre sur le flux, décalée d'une colonne.
		for (var i = 0; i < nb; i++) {
			var page = el('acte-page');
			page.style.fontFamily = police(c.police);

			var corps = el('acte-corps');
			corps.style.left = c.margeGauche + MM;
			corps.style.top = c.margeHaut + MM;
			corps.style.width = largeurCorps + MM;
			corps.style.height = hauteurCorps + MM;
			var flux = creerFlux(parties, c, nb);
			flux.style.transform = 'translateX(-' + (i * largeurCorps) + MM + ')';
			corps.appendChild(flux);
			page.appendChild(corps);

			if (i === 0 && parties.entete) {
				var timbre = el('acte-timbre', parties.entete);
				timbre.style.left = c.timbreGauche + MM;
				timbre.style.top = c.timbreHaut + MM;
				timbre.style.width = c.timbreLargeur + MM;
				timbre.style.height = c.timbreHauteur + MM;
				timbre.style.fontFamily = police(c.policeTimbre);
				timbre.style.fontSize = c.tailleTimbre + 'pt';
				page.appendChild(timbre);
			}

			page.appendChild(el('acte-pied', String(i + 1)));
			pages.appendChild(page);
		}

		// 3. Zoom : « ajuste » remplit la largeur disponible (sans agrandir au-delà de la taille réelle), sinon un nombre (1 = taille réelle).
		var largeurPage = 210 * pxParMm, hauteurPages = pages.offsetHeight;
		var echelle = typeof options.zoom === 'number' ? options.zoom : Math.min(1, Math.max(0.2, (zone.clientWidth - 24) / largeurPage));
		pages.style.width = largeurPage + 'px';
		pages.style.transform = 'scale(' + echelle + ')';
		cadre.style.width = (largeurPage * echelle) + 'px';
		cadre.style.height = (hauteurPages * echelle) + 'px';
		return { pages: nb, echelle: echelle };
	}

	window.FeuilleActe = { afficher: afficher };
})(window, document);
