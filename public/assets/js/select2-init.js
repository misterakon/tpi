/* Listes déroulantes AVEC RECHERCHE dans la liste (Select2), pour toute l'application.
   Le champ fermé ressemble à une liste normale ; en l'ouvrant, un champ de recherche apparaît en haut de la liste
   (accents et majuscules ignorés). S'applique à toutes les listes .form-select ; exclure une liste : attribut data-sans-recherche.
   Dépend de jQuery et de select2.full.min.js (chargés avant). Les formulaires, valeurs et évènements « change » fonctionnent comme avant. */
(function ($) {
	'use strict';
	if (!$ || !$.fn || !$.fn.select2) { return; }

	var FR = {
		noResults: function () { return 'Aucun résultat'; },
		searching: function () { return 'Recherche en cours…'; },
		loadingMore: function () { return 'Chargement…'; },
		errorLoading: function () { return 'Les résultats ne peuvent pas être chargés.'; },
		inputTooShort: function (a) { return 'Saisissez ' + (a.minimum - a.input.length) + ' caractère(s) de plus'; }
	};

	/* Le champ fermé reprend l'aspect des champs du thème (bordure, arrondi, couleurs) */
	function copierStyleDesChamps() {
		var sonde = document.createElement('input');
		sonde.className = 'form-control';
		sonde.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden';
		document.body.appendChild(sonde);
		var c = window.getComputedStyle(sonde);
		var racine = document.documentElement.style;
		racine.setProperty('--s2-bordure', c.borderTopColor);
		racine.setProperty('--s2-rayon', c.borderTopLeftRadius);
		racine.setProperty('--s2-fond', c.backgroundColor);
		racine.setProperty('--s2-texte', c.color);
		racine.setProperty('--s2-taille', c.fontSize);
		racine.setProperty('--s2-hauteur', c.height);
		document.body.removeChild(sonde);
	}

	function initialiser(racine) {
		$(racine || document).find('select.form-select').each(function () {
			var s = this, $s = $(s);
			if ($s.hasClass('select2-hidden-accessible') || $s.is('[data-sans-recherche]') || $s.closest('.dataTables_length').length) { return; }

			var $modal = $s.closest('.modal');
			var premiere = s.options.length ? s.options[0] : null;
			var options = {
				width: '100%',
				language: FR,
				dropdownParent: $modal.length ? $modal : $(document.body),
				dropdownAutoWidth: false
			};
			if (s.multiple) { options.closeOnSelect = false; }
			else if (premiere && premiere.value === '') { options.placeholder = { id: '', text: premiere.text.trim() }; }
			/* data-libre : on peut aussi taper une valeur absente de la liste (ex. un nouvel avocat) */
			if ($s.is('[data-libre]')) {
				var etiquette = $s.attr('data-libre-texte') || 'Nouveau';
				options.tags = true;
				options.createTag = function (t) { var v = $.trim(t.term); return v === '' ? null : { id: v, text: v + ' (' + etiquette.toLowerCase() + ')' }; };
			}
			if ($s.hasClass('form-select-sm')) { options.containerCssClass = 'select2-sm'; }

			$s.select2(options);

			/* Les écouteurs natifs de la page (addEventListener) doivent aussi être prévenus d'un changement */
			$s.on('select2:select select2:unselect select2:clear', function () { s.dispatchEvent(new Event('change', { bubbles: true })); });

			/* Le champ de recherche reçoit le curseur dès l'ouverture */
			$s.on('select2:open', function () {
				setTimeout(function () { var champ = document.querySelector('.select2-container--open .select2-search__field'); if (champ) { champ.focus(); } }, 0);
			});
		});
	}

	/* form.reset() et affectations par script : l'affichage se met à jour */
	document.addEventListener('reset', function (e) {
		setTimeout(function () { $(e.target).find('select.select2-hidden-accessible').trigger('change.select2'); }, 0);
	}, true);

	window.RechercheListes = {
		appliquer: initialiser,
		rafraichir: function (racine) { $(racine || document).find('select.select2-hidden-accessible').trigger('change.select2'); }
	};

	$(function () {
		copierStyleDesChamps();
		initialiser(document);
		if (window.MutationObserver) {
			var minuteur = null;
			new MutationObserver(function () { clearTimeout(minuteur); minuteur = setTimeout(function () { initialiser(document); }, 60); })
				.observe(document.body, { childList: true, subtree: true });
		}
	});
})(window.jQuery);
