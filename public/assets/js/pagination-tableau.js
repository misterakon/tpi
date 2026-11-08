/* Pagination simple d'un tableau ou d'une grille, côté navigateur.
   Usage : <tbody data-pagine="10"> ou <div class="grille" data-pagine="12"> : seuls les éléments de la page courante sont affichés,
   une barre « Précédent · 1 2 3 · Suivant » apparaît sous le tableau quand il y a plusieurs pages. */
(function () {
	if (window.PaginationTableau) { return; }

	function barre(cible) {
		var nav = document.createElement('div'); nav.className = 'pg';
		var hote = cible.closest('.overflow-x-auto') || (cible.tagName === 'TBODY' ? cible.closest('table') : cible);
		hote.insertAdjacentElement('afterend', nav);
		return nav;
	}

	function init(cible) {
		var taille = parseInt(cible.getAttribute('data-pagine'), 10) || 10;
		var elements = Array.prototype.filter.call(cible.children, function (e) { return e.nodeType === 1; });
		if (elements.length <= taille) { return; }
		var nb = Math.ceil(elements.length / taille); var page = 1; var nav = barre(cible);

		function bouton(texte, p, inactif, courant) {
			var b = document.createElement('button'); b.type = 'button'; b.innerHTML = texte; b.disabled = !!inactif;
			if (courant) { b.className = 'pg-actif'; b.setAttribute('aria-current', 'page'); }
			b.addEventListener('click', function () { page = p; afficher(); });
			return b;
		}
		function afficher() {
			var debut = (page - 1) * taille;
			elements.forEach(function (e, i) { e.hidden = i < debut || i >= debut + taille; });
			nav.innerHTML = '';
			var info = document.createElement('span'); info.className = 'pg-info';
			info.textContent = (debut + 1) + ' à ' + Math.min(debut + taille, elements.length) + ' sur ' + elements.length;
			nav.appendChild(info);
			var liste = document.createElement('span'); liste.className = 'pg-liste';
			liste.appendChild(bouton('‹', page - 1, page === 1));
			var vus = [];
			for (var i = 1; i <= nb; i++) {
				if (i === 1 || i === nb || Math.abs(i - page) <= 1) { vus.push(i); } else if (vus[vus.length - 1] !== '…') { vus.push('…'); }
			}
			vus.forEach(function (v) {
				if (v === '…') { var s = document.createElement('span'); s.className = 'pg-points'; s.textContent = '…'; liste.appendChild(s); }
				else { liste.appendChild(bouton(String(v), v, false, v === page)); }
			});
			liste.appendChild(bouton('›', page + 1, page === nb));
			nav.appendChild(liste);
		}
		afficher();
	}

	function style() {
		var s = document.createElement('style');
		s.textContent = '.pg{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 4px 4px}'
			+ '.pg-info{font-size:13px;color:#52637C}.pg-liste{display:inline-flex;gap:4px}'
			+ '.pg-liste button{min-width:34px;height:34px;padding:0 8px;border:1px solid #B9CCE3;border-radius:9px;background:#F4F8FD;font-size:13px;font-weight:700;color:#1F4E79;cursor:pointer}'
			+ '.pg-liste button:hover:not(:disabled){border-color:#2B6CB0;background:#E1ECF9}.pg-liste button:disabled{opacity:.4;cursor:default}'
			+ '.pg-liste .pg-actif{background:#1F4E79;border-color:#1F4E79;color:#fff}.pg-points{display:inline-flex;align-items:center;padding:0 4px;color:#52637C}'
			+ '[hidden]{display:none!important}';
		document.head.appendChild(s);
	}

	function demarrer() { style(); Array.prototype.forEach.call(document.querySelectorAll('[data-pagine]'), init); }
	window.PaginationTableau = { init: init };
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', demarrer); } else { demarrer(); }
})();
