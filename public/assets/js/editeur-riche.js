/*
 * Éditeur de texte riche (gras, italique, listes…) : transforme chaque <textarea data-riche> en éditeur Quill.
 * Le textarea reste le champ envoyé au serveur (son contenu est du HTML, nettoyé côté serveur par TexteRiche).
 *
 *   <textarea data-riche data-hauteur="140" name="attributions"></textarea>
 *
 * Après avoir rempli un textarea par script ($(...).val(...)), appeler EditeurRiche.charger() pour rafraîchir l'éditeur.
 * La réinitialisation d'un formulaire (reset) vide les éditeurs automatiquement.
 */
(function (window, document) {
	'use strict';

	var BARRE =
		'<span class="ql-formats">' +
			'<button type="button" class="ql-bold" title="Gras"></button>' +
			'<button type="button" class="ql-italic" title="Italique"></button>' +
			'<button type="button" class="ql-underline" title="Souligné"></button>' +
			'<button type="button" class="ql-strike" title="Barré"></button>' +
		'</span>' +
		'<span class="ql-formats">' +
			'<button type="button" class="ql-list" value="bullet" title="Liste à puces"></button>' +
			'<button type="button" class="ql-list" value="ordered" title="Liste numérotée"></button>' +
			'<button type="button" class="ql-blockquote" title="Citation"></button>' +
		'</span>' +
		'<span class="ql-formats">' +
			'<button type="button" class="ql-link" title="Lien"></button>' +
			'<button type="button" class="ql-clean" title="Effacer la mise en forme"></button>' +
		'</span>';

	var ALIGNEMENT =
		'<span class="ql-formats">' +
			'<button type="button" class="ql-align" value="" title="Aligner à gauche"></button>' +
			'<button type="button" class="ql-align" value="center" title="Centrer"></button>' +
			'<button type="button" class="ql-align" value="right" title="Aligner à droite"></button>' +
			'<button type="button" class="ql-align" value="justify" title="Justifier"></button>' +
		'</span>';

	var instances = [];

	function echapper(texte) {
		return String(texte).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	/** Un ancien texte brut devient des paragraphes ; un HTML est gardé tel quel. */
	function versHtml(valeur) {
		valeur = (valeur || '').trim();
		if (valeur === '') { return ''; }
		if (/<[a-z!\/]/i.test(valeur)) { return valeur; }
		return valeur.split(/\r?\n/).map(function (ligne) { return '<p>' + (ligne === '' ? '<br>' : echapper(ligne)) + '</p>'; }).join('');
	}

	function vide(quill) {
		return quill.getText().trim() === '' && !quill.root.querySelector('ul,ol,blockquote');
	}

	function remplir(inst) {
		inst.enCours = true;
		var html = versHtml(inst.champ.value);
		if (typeof inst.quill.clipboard.dangerouslyPasteHTML === 'function') {
			inst.quill.clipboard.dangerouslyPasteHTML(html);
		} else {
			inst.quill.pasteHTML(html);
		}
		inst.quill.history.clear();
		inst.enCours = false;
	}

	function creer(champ) {
		if (champ.getAttribute('data-riche-ok')) { return; }
		champ.setAttribute('data-riche-ok', '1');

		var cadre = document.createElement('div');
		cadre.className = 'el-editeur';
		var barre = document.createElement('div');
		barre.className = 'el-editeur__barre';
		barre.innerHTML = BARRE + (champ.hasAttribute('data-alignement') ? ALIGNEMENT : '');
		var zone = document.createElement('div');
		zone.className = 'el-editeur__zone';
		zone.style.height = (parseInt(champ.getAttribute('data-hauteur'), 10) || 120) + 30 + 'px';   // hauteur fixe : au-delà, le texte défile
		cadre.appendChild(barre);
		cadre.appendChild(zone);
		champ.parentNode.insertBefore(cadre, champ.nextSibling);
		champ.style.display = 'none';
		champ.removeAttribute('maxlength');

		var quill = new window.Quill(zone, {
			theme: 'snow',
			modules: { toolbar: barre },
			placeholder: champ.getAttribute('placeholder') || ''
		});

		var inst = { champ: champ, quill: quill, enCours: false };
		quill.on('text-change', function () {
			if (inst.enCours) { return; }
			champ.value = vide(quill) ? '' : quill.root.innerHTML;
			champ.dispatchEvent(new Event('input', { bubbles: true }));   // pour savoir qu'on a écrit dans l'éditeur
		});
		instances.push(inst);
		remplir(inst);
	}

	var EditeurRiche = {
		init: function (racine) {
			if (!window.Quill) { return; }
			(racine || document).querySelectorAll('textarea[data-riche]').forEach(creer);
		},
		/** Insère du texte à l'endroit du curseur dans l'éditeur d'un textarea (sélecteur ou élément). */
		inserer: function (champ, texte) {
			var el = typeof champ === 'string' ? document.querySelector(champ) : champ;
			var inst = instances.filter(function (i) { return i.champ === el; })[0];
			if (!inst) { return; }
			var sel = inst.quill.getSelection(true);
			var index = sel ? sel.index : inst.quill.getLength() - 1;
			inst.quill.insertText(index, texte, 'user');
			inst.quill.setSelection(index + texte.length, 0);
		},
		/** Lecture seule : les éditeurs ne se modifient plus. */
		desactiver: function () {
			instances.forEach(function (i) { i.quill.enable(false); });
		},
		/** Recharge chaque éditeur depuis la valeur actuelle de son textarea. */
		charger: function () {
			instances.forEach(remplir);
		}
	};

	document.addEventListener('reset', function () {
		setTimeout(EditeurRiche.charger, 0);
	}, true);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { EditeurRiche.init(); });
	} else {
		EditeurRiche.init();
	}

	window.EditeurRiche = EditeurRiche;
})(window, document);
