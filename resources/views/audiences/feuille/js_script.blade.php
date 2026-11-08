{{-- Feuille d'audience : saisie des suites sans rechargement. Variables : $role, $lignes, $composition, $resume, $peutModifier --}}
<script type="text/javascript">
(function () {
	'use strict';

	let LIGNES = @js($lignes);
	let COMPOSITION = @js($composition);
	let RESUME = @js($resume);
	let ETAT = @js($role->etatAudience());
	const PEUT = @js($peutModifier);
	const JOUR_ROLE = @js($role->date_audience->toDateString());
	const CSRF = @js(csrf_token());
	const URL_SUITE = @js(route('audiences.feuille.suite', [$role->id, 0]));
	const URL_DATES = @js(route('audiences.feuille.dates', $role->id));
	const URL_COMPOSITION = @js(route('audiences.feuille.composition', $role->id));
	const URL_OUVRIR = @js(route('audiences.feuille.ouvrir', $role->id));
	const URL_TERMINER = @js(route('audiences.feuille.terminer', $role->id));
	const URL_REDIGER = @js(auth()->user()->can('redaction.creer') ? route('decisions.redaction') : null);
	const URL_ROUVRIR = @js(route('audiences.feuille.rouvrir', $role->id));
	const PRESENCES = @js(\App\Models\RoleLigne::PRESENCES);
	const RUBRIQUES = @js(\App\Models\RoleLigne::RUBRIQUES);
	const ISSUES = {
		renvoi: { lib: 'Renvoyer', fait: 'Renvoyée', ico: 'fa-arrows-rotate' }, delibere: { lib: 'Mettre en délibéré', fait: 'En délibéré', ico: 'fa-hourglass-half' },
		juge: { lib: 'Jugée', fait: 'Jugée', ico: 'fa-gavel' }, radie: { lib: 'Radiée', fait: 'Radiée', ico: 'fa-ban' }, joint: { lib: 'Jointe', fait: 'Jointe à une autre', ico: 'fa-link' }, desiste: { lib: 'Désistement', fait: 'Désistement', ico: 'fa-handshake' },
	};
	const ETATS = { a_tenir: 'Audience à tenir', en_cours: 'Audience en cours', terminee: 'Audience terminée' };
	let PROCHAINES = [], ouvert = null, brouillon = {}, minuteurComp = null, repliees = {};

	const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	const editable = function () { return PEUT && ETAT !== 'terminee'; };
	const iso = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
	const libelleJour = function (isoDate) { const d = new Date(isoDate + 'T00:00:00'); const t = d.toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }); return t.charAt(0).toUpperCase() + t.slice(1); };
	const apres = function (jours, mois) { const d = new Date(JOUR_ROLE + 'T00:00:00'); if (mois) { d.setMonth(d.getMonth() + mois); } else { d.setDate(d.getDate() + jours); } return iso(d); };
	const toast = function (icone, titre) { if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: icone, title: titre, showConfirmButton: false, timer: 2400, timerProgressBar: true }); } };

	function appel(url, donnees) {
		return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(donnees || {}) })
			.then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { const e = new Error((j.errors ? Object.values(j.errors)[0][0] : j.message) || 'Erreur'); e.statut = r.status; throw e; } return j; }); });
	}

	/* ---------------------------------------------------------------- suivi */
	function suivi() {
		const r = RESUME, pct = r.total ? Math.round(r.traitees / r.total * 100) : 0;
		$('#suivi-n').text(r.traitees + ' / ' + r.total); $('#suivi-barre').css('width', pct + '%');
		$('#compteurs').html(Object.keys(ISSUES).map(function (k) { const n = r.parSuite[k] || 0; return '<span class="au-cpt au-cpt--' + k + (n ? '' : ' is-zero') + '"><i class="fa-solid ' + ISSUES[k].ico + '"></i>' + esc(ISSUES[k].fait) + ' <b>' + n + '</b></span>'; }).join(''));
		$('#point-etat').attr('class', 'au-point-etat is-' + ETAT); $('#texte-etat').text(ETATS[ETAT]);
		$('#btn-terminer').prop('hidden', !(PEUT && ETAT !== 'terminee')); $('#btn-rouvrir').prop('hidden', !(PEUT && ETAT === 'terminee'));
	}

	/* ---------------------------------------------------------------- composition */
	function composition() {
		$('#composition').html(COMPOSITION.length ? COMPOSITION.map(function (m, i) {
			return '<div class="au-membre ' + (m.present ? '' : 'is-absent') + '" data-i="' + i + '"><div class="au-membre__ligne"><div class="au-membre__nom">' + esc(m.nom) + '<small>' + esc(m.role) + '</small></div>'
				+ '<button type="button" class="au-interrupteur ' + (m.present ? 'is-actif' : '') + '" data-act="present" ' + (editable() ? '' : 'disabled') + ' role="switch" aria-checked="' + (m.present ? 'true' : 'false') + '" aria-label="Présent : ' + esc(m.nom) + '"></button></div>'
				+ (m.present ? '' : '<input type="text" maxlength="120" data-act="remplacant" value="' + esc(m.remplacant || '') + '" placeholder="Remplacé par (nom)…" ' + (editable() ? '' : 'disabled') + '>') + '</div>';
		}).join('') : '<div class="au-vide" style="padding:12px"><i class="fa-solid fa-users"></i>Aucune composition enregistrée pour cette chambre.</div>');
	}
	function enregistrerComposition() {
		clearTimeout(minuteurComp);
		minuteurComp = setTimeout(function () {
			appel(URL_COMPOSITION, { membres: COMPOSITION }).then(function (j) { if (j.etat) { ETAT = j.etat; suivi(); } toast('success', j.message); }).catch(function (e) { toast('error', e.message); });
		}, 700);
	}

	/* ---------------------------------------------------------------- lignes */
	function numeroHtml(l) { return l.numero.map(function (n, i) { return i === 0 ? '<div class="au-lg__numero">' + (l.fiche ? '<a class="au-lg__fiche" href="' + esc(l.fiche) + '" target="_blank" rel="noopener" title="Ouvrir la fiche de l\'affaire">' + esc(n) + '<i class="fa-solid fa-arrow-up-right-from-square"></i></a>' : esc(n)) + '</div>' : '<div class="au-lg__numero"><small>' + esc(n) + '</small></div>'; }).join(''); }

	function resumeSuite(l) {
		if (!l.issue) { return ''; }
		let t = '<b>' + esc(ISSUES[l.issue].fait) + '</b>';
		if (l.issue === 'renvoi') { t += ' au ' + esc(libelleJour(l.renvoi_au)); }
		if (l.issue === 'delibere') { t += l.date_delibere ? ' jusqu\'au ' + esc(libelleJour(l.date_delibere)) : ' (date à fixer : retour au prochain rôle)'; }
		if (l.motif) { t += ' · ' + esc(l.motif); }
		const rediger = l.issue === 'juge' && URL_REDIGER && l.affaire_id ? '<a class="au-resume-suite__lien" href="' + esc(URL_REDIGER + '?affaire=' + l.affaire_id) + '" title="Ouvrir la rédaction de la décision de cette affaire"><i class="fa-solid fa-pen-nib"></i>Rédiger la décision</a>' : '';
		return '<div class="au-resume-suite" style="--c:var(--au-vert)"><i class="fa-solid ' + ISSUES[l.issue].ico + '"></i><span>' + t + '</span>' + rediger + (editable() ? '<button type="button" data-act="modifier">Modifier</button>' : '') + '</div>';
	}

	function detail(l) {
		const b = brouillon[l.id]; if (!b) { return ''; }
		const meta = ISSUES[b.issue];
		let champs = '';
		if (b.issue === 'renvoi') {
			champs = '<div><label for="r-' + l.id + '">Renvoyée au</label><input type="date" id="r-' + l.id + '" data-champ="renvoi_au" min="' + apres(1) + '" value="' + esc(b.renvoi_au || '') + '"></div>'
				+ '<div><label for="m-' + l.id + '">Motif du renvoi</label><input type="text" id="m-' + l.id + '" data-champ="motif" list="dl-motifs" maxlength="200" value="' + esc(b.motif || '') + '" placeholder="Choisir ou écrire…"></div>'
				+ '<div class="au-plein"><div class="au-propositions"><span>Prochaines audiences :</span>' + PROCHAINES.map(function (p) { return '<button type="button" class="au-prop" data-prop="' + esc(p.date) + '">' + esc(p.libelle) + '</button>'; }).join('')
				+ '<button type="button" class="au-prop" data-prop="' + apres(15) + '">+ 15 jours</button><button type="button" class="au-prop" data-prop="' + apres(0, 1) + '">+ 1 mois</button></div><div data-zone="analyse"></div></div>';
		} else if (b.issue === 'delibere') {
			champs = '<div><label for="d-' + l.id + '">Délibéré jusqu\'au (facultatif)</label><input type="date" id="d-' + l.id + '" data-champ="date_delibere" min="' + apres(1) + '" value="' + esc(b.date_delibere || '') + '"></div>'
				+ '<div><label for="m-' + l.id + '">Remarque</label><input type="text" id="m-' + l.id + '" data-champ="motif" maxlength="200" value="' + esc(b.motif || '') + '" placeholder="Ex. : note en délibéré attendue"></div>'
				+ '<div class="au-plein"><div class="au-propositions"><span>Prononcer :</span><button type="button" class="au-prop" data-prop="' + apres(15) + '">Dans 15 jours</button><button type="button" class="au-prop" data-prop="' + apres(0, 1) + '">Dans 1 mois</button><button type="button" class="au-prop" data-prop="' + apres(0, 2) + '">Dans 2 mois</button>'
				+ PROCHAINES.map(function (p) { return '<button type="button" class="au-prop" data-prop="' + esc(p.date) + '">' + esc(p.libelle) + '</button>'; }).join('') + '</div><div data-zone="analyse"></div>'
				+ '<div style="margin-top:8px;font:500 12.5px Inter,sans-serif;color:#5B6B86">Sans date, l\'affaire revient au prochain rôle de la chambre ; avec une date, elle ne revient qu\'à partir de ce jour.</div></div>';
		} else {
			champs = '<div class="au-plein"><label for="m-' + l.id + '">Précision (facultatif)</label><input type="text" id="m-' + l.id + '" data-champ="motif" maxlength="200" value="' + esc(b.motif || '') + '" placeholder="' + (b.issue === 'juge' ? 'Ex. : jugement du jour, décision à rédiger' : b.issue === 'joint' ? 'Ex. : jointe à 1402/26' : 'Précision éventuelle') + '"></div>';
		}
		return '<div class="au-detail" style="--c:var(--au-vert)"><div class="au-detail__titre"><i class="fa-solid ' + meta.ico + '"></i>' + esc(meta.fait) + ' : ' + esc(l.numero[0]) + '</div><div class="au-champs">' + champs
			+ '<div class="au-plein"><label for="n-' + l.id + '">Remarque d\'audience (facultatif)</label><input type="text" id="n-' + l.id + '" data-champ="note_audience" maxlength="500" value="' + esc(b.note_audience || '') + '"></div></div>'
			+ '<div data-zone="erreur"></div><div class="au-actions">' + (l.issue ? '<button type="button" class="au-btn au-btn--gris" data-act="effacer" style="margin-right:auto"><i class="fa-solid fa-eraser"></i>Effacer la suite</button>' : '')
			+ '<button type="button" class="au-btn au-btn--clair" data-act="annuler">Annuler</button><button type="button" class="au-btn au-btn--vert" data-act="enregistrer"><i class="fa-solid fa-check"></i>Enregistrer</button></div></div>';
	}

	function histo(l) {
		if (!l.historique.length) { return ''; }
		return '<div style="margin-top:10px"><button type="button" class="au-lien" data-act="histo">Historique (' + l.historique.length + ' passage' + (l.historique.length > 1 ? 's' : '') + ' précédent' + (l.historique.length > 1 ? 's' : '') + ')</button><div class="au-histo" hidden>'
			+ l.historique.map(function (h) { return '<div class="au-histo__ligne"><b>' + esc(h.date) + '</b> · ' + esc(h.libelle) + (h.detail ? ' ' + esc(h.detail) : '') + (h.motif ? ' · ' + esc(h.motif) : '') + '</div>'; }).join('') + '</div></div>';
	}

	function ligne(l) {
		const ed = editable();
		const etiq = (l.renvois_avant > 0 ? '<span class="au-badge au-badge--orange"><i class="fa-solid fa-arrows-rotate"></i>' + (l.renvois_avant === 1 ? 'Déjà renvoyée 1 fois' : 'Déjà renvoyée ' + l.renvois_avant + ' fois') + '</span>' : '')
			+ (l.rubrique === 'deliberes' ? '<span class="au-badge au-badge--sarcelle"><i class="fa-solid fa-hourglass-half"></i>Délibéré à rendre</span>' : '');
		const pres = '<select class="au-presence" data-act="presence" ' + (ed ? '' : 'disabled') + ' aria-label="Présence"><option value="">Présence : non notée</option>' + Object.keys(PRESENCES).map(function (k) { return '<option value="' + k + '"' + (l.presence === k ? ' selected' : '') + '>' + esc(PRESENCES[k]) + '</option>'; }).join('') + '</select>';
		const pilules = Object.keys(ISSUES).map(function (k) { return '<button type="button" class="au-pilule au-pilule--' + k + ((brouillon[l.id] ? brouillon[l.id].issue : l.issue) === k ? ' is-actif' : '') + '" data-issue="' + k + '"' + (ed ? '' : ' disabled') + '><i class="fa-solid ' + ISSUES[k].ico + '"></i>' + esc(ISSUES[k].lib) + '</button>'; }).join('');
		return '<article class="au-lg ' + (ouvert === l.id ? 'is-ouvert' : '') + '" id="lg-' + l.id + '" data-id="' + l.id + '" data-issue="' + esc(l.issue || '') + '">'
			+ '<div class="au-lg__tete"><div class="au-num">' + l.n + '</div><div class="au-lg__corps">' + numeroHtml(l) + '<div class="au-lg__parties">' + esc(l.parties || '—') + '</div>' + (l.objet ? '<div class="au-lg__objet">' + esc(l.objet.split('\n')[0]) + '</div>' : '') + (etiq ? '<div class="au-lg__etiquettes">' + etiq + '</div>' : '') + '</div><div class="au-lg__droite">' + pres + '</div></div>'
			+ (ouvert === l.id ? '' : resumeSuite(l)) + (ed || l.issue ? '<div class="au-choix" ' + (ouvert === l.id || !ed ? (ed ? '' : 'hidden') : '') + '>' + pilules + '</div>' : '') + detail(l) + histo(l) + '</article>';
	}

	function lignes() {
		let html = '';
		Object.keys(RUBRIQUES).forEach(function (r) {
			const ls = LIGNES.filter(function (l) { return l.rubrique === r; }); if (!ls.length) { return; }
			const ferme = !!repliees[r];
			html += '<section class="au-groupe au-groupe--' + r + (ferme ? ' is-ferme' : '') + '" data-rub="' + r + '"><button type="button" class="au-rubrique" data-act="plier" aria-expanded="' + (ferme ? 'false' : 'true') + '"><span class="au-rubrique__ico"><i class="fa-solid ' + { deliberes: 'fa-hourglass-half', anciennes: 'fa-clock-rotate-left', nouvelles: 'fa-folder-plus' }[r] + '"></i></span><span class="au-rubrique__titre">' + esc(RUBRIQUES[r]) + '</span><em>' + ls.length + ' affaire' + (ls.length > 1 ? 's' : '') + '</em><span class="au-rubrique__fleche"><i class="fa-solid fa-chevron-down"></i></span></button>'
				+ '<div class="au-groupe__corps"' + (ferme ? ' style="display:none"' : '') + '>' + ls.map(ligne).join('') + '</div></section>';
		});
		$('#lignes').html(html || '<div class="au-panel au-vide"><i class="fa-solid fa-clipboard-list"></i>Ce rôle ne contient aucune affaire.</div>');
	}

	function remplacer(l) { const i = LIGNES.findIndex(function (x) { return x.id === l.id; }); if (i >= 0) { LIGNES[i] = l; } }
	function recharger(id) { const l = LIGNES.find(function (x) { return x.id === id; }); const $a = $('#lg-' + id); if (!l || !$a.length) { lignes(); return; } $a.replaceWith(ligne(l)); }

	/* ---------------------------------------------------------------- saisie d'une suite */
	function analyserDate(id) {
		const b = brouillon[id], d = b && (b.issue === 'renvoi' ? b.renvoi_au : b.date_delibere); const zone = $('#lg-' + id + ' [data-zone="analyse"]'); zone.empty();
		if (!d) { return; }
		fetch(URL_DATES + '?date=' + encodeURIComponent(d), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
			const a = j && j.analyse; if (!a || (!a.ferme && a.siege)) { return; }
			const msg = a.ferme ? 'Le ' + a.jour + ' est un jour fermé (' + a.ferme + ').' : 'La ' + a.chambre + ' ne siège pas le ' + a.jour + ' (jours habituels : ' + a.habituels.join(', ') + ').';
			zone.html('<div class="au-alerte"><i class="fa-solid fa-triangle-exclamation"></i><span>' + esc(msg) + ' Vous pouvez quand même enregistrer cette date.</span></div>');
		}).catch(function () {});
	}

	function ouvrirSuite(id, issue) {
		const l = LIGNES.find(function (x) { return x.id === id; }); if (!l) { return; }
		const ancien = ouvert; ouvert = id;
		brouillon[id] = { issue: issue, renvoi_au: l.issue === issue ? l.renvoi_au : '', date_delibere: l.issue === issue ? l.date_delibere : '', motif: l.issue === issue ? (l.motif || '') : '', note_audience: l.note || '' };
		if (ancien && ancien !== id) { recharger(ancien); }
		recharger(id);
		const $d = $('#lg-' + id + ' .au-detail'); const $premier = $d.find('input').first(); if ($premier.length) { $premier.trigger('focus'); }
		analyserDate(id);
	}

	function fermer(id) { ouvert = null; delete brouillon[id]; recharger(id); }

	function enregistrer(id, effacer, silencieux) {
		const l = LIGNES.find(function (x) { return x.id === id; }); const b = brouillon[id];
		const donnees = effacer ? { issue: null, presence: l.presence, note_audience: l.note } : Object.assign({}, b, { presence: l.presence });
		if (!effacer && b.issue !== 'renvoi') { delete donnees.renvoi_au; } if (!effacer && b.issue !== 'delibere') { delete donnees.date_delibere; }
		if (donnees.renvoi_au === '') { delete donnees.renvoi_au; } if (donnees.date_delibere === '') { delete donnees.date_delibere; }
		$('#lg-' + id + ' [data-zone="erreur"]').empty();
		return appel(URL_SUITE.replace(/\/0$/, '/' + id), donnees).then(function (j) {
			remplacer(j.ligne); RESUME = j.resume; ETAT = j.etat; ouvert = null; delete brouillon[id]; suivi();
			recharger(id); $('#lg-' + id).addClass('is-flash'); if (!silencieux) { toast('success', j.message); }
		}).catch(function (e) { $('#lg-' + id + ' [data-zone="erreur"]').html('<div class="au-erreur"><i class="fa-solid fa-circle-exclamation"></i> ' + esc(e.message) + '</div>'); });
	}

	function enregistrerPresence(id, valeur) {
		const l = LIGNES.find(function (x) { return x.id === id; }); if (!l) { return; }
		appel(URL_SUITE.replace(/\/0$/, '/' + id), { issue: l.issue, renvoi_au: l.renvoi_au || undefined, date_delibere: l.date_delibere || undefined, motif: l.motif, presence: valeur || null, note_audience: l.note }).then(function (j) {
			remplacer(j.ligne); RESUME = j.resume; ETAT = j.etat; suivi(); toast('success', 'Présence enregistrée.');
		}).catch(function (e) { toast('error', e.message); recharger(id); });
	}

	/* ---------------------------------------------------------------- audience : ouvrir, terminer, rouvrir */
	function terminer() {
		const sans = RESUME.total - RESUME.traitees;
		Swal.fire({ icon: sans ? 'warning' : 'question', title: "Terminer l'audience ?", text: sans ? sans + ' affaire' + (sans > 1 ? 's n\'ont' : ' n\'a') + ' pas de suite notée : ' + (sans > 1 ? 'elles reviendront' : 'elle reviendra') + ' parmi les anciennes du prochain rôle.' : 'Toutes les affaires ont une suite. Vous pourrez rouvrir la feuille pour corriger.',
			showCancelButton: true, confirmButtonText: 'Terminer', cancelButtonText: 'Pas encore', confirmButtonColor: '#00794C' }).then(function (r) {
			if (!r.isConfirmed) { return; }
			appel(URL_TERMINER, { note_audience: $('#note-audience').val() }).then(function (j) { ETAT = j.etat; ouvert = null; brouillon = {}; suivi(); composition(); lignes(); toast('success', j.message); }).catch(function (e) { toast('error', e.message); });
		});
	}
	function rouvrir() {
		appel(URL_ROUVRIR, {}).then(function (j) { ETAT = j.etat; suivi(); composition(); lignes(); toast('success', j.message); }).catch(function (e) { toast('error', e.message); });
	}

	/* La barre d'avancement et le panneau de composition se collent sous le menu fixe, jamais dessus. */
	function sousLeMenu() {
		const bas = function (s) { const e = document.querySelector(s); return e ? Math.max(0, Math.round(e.getBoundingClientRect().bottom)) : 0; };
		const haut = Math.max(bas('.sidebar-wrapper'), bas('.page-header')) + 12 + 'px';
		document.querySelectorAll('.au-suivi, .au-aside').forEach(function (el) { el.style.top = haut; });
	}
	let attente = false;
	function sousLeMenuDoux() { if (attente) { return; } attente = true; window.requestAnimationFrame(function () { attente = false; sousLeMenu(); }); }

	document.addEventListener('DOMContentLoaded', function () {
		sousLeMenu(); window.addEventListener('resize', sousLeMenuDoux); window.addEventListener('load', sousLeMenu); window.addEventListener('scroll', sousLeMenuDoux, { passive: true });
		suivi(); composition(); lignes();
		fetch(URL_DATES, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) { if (j) { PROCHAINES = j.prochaines || []; } }).catch(function () {});

		$('#btn-terminer').on('click', terminer); $('#btn-rouvrir').on('click', rouvrir);

		$('#composition').on('click', '[data-act="present"]', function () {
			const i = +$(this).closest('.au-membre').attr('data-i'); COMPOSITION[i].present = !COMPOSITION[i].present; if (COMPOSITION[i].present) { COMPOSITION[i].remplacant = null; }
			composition(); enregistrerComposition();
			if (!COMPOSITION[i].present) { $('#composition .au-membre[data-i="' + i + '"] input').trigger('focus'); }
		}).on('input', '[data-act="remplacant"]', function () { COMPOSITION[+$(this).closest('.au-membre').attr('data-i')].remplacant = this.value; enregistrerComposition(); });

		$('#lignes').on('click', '[data-act="plier"]', function () {
			const $g = $(this).closest('.au-groupe'), ferme = !$g.hasClass('is-ferme');
			repliees[$g.attr('data-rub')] = ferme; $g.toggleClass('is-ferme', ferme); $(this).attr('aria-expanded', ferme ? 'false' : 'true');
			$g.children('.au-groupe__corps').stop(true, true)[ferme ? 'slideUp' : 'slideDown'](180);
		});
		$('#lignes').on('click', '.au-pilule', function () {
			const id = +$(this).closest('.au-lg').attr('data-id'), issue = this.getAttribute('data-issue'); if (ouvert === id && brouillon[id] && brouillon[id].issue === issue) { fermer(id); return; } ouvrirSuite(id, issue);
		}).on('click', '[data-act="modifier"]', function () {
			const id = +$(this).closest('.au-lg').attr('data-id'), l = LIGNES.find(function (x) { return x.id === id; }); ouvrirSuite(id, l.issue);
		}).on('click', '[data-act="annuler"]', function () { fermer(+$(this).closest('.au-lg').attr('data-id')); })
		.on('click', '[data-act="enregistrer"]', function () { enregistrer(+$(this).closest('.au-lg').attr('data-id'), false); })
		.on('click', '[data-act="effacer"]', function () { enregistrer(+$(this).closest('.au-lg').attr('data-id'), true); })
		.on('click', '[data-act="histo"]', function () { $(this).siblings('.au-histo').prop('hidden', function (i, v) { return !v; }); })
		.on('change', '[data-act="presence"]', function () { enregistrerPresence(+$(this).closest('.au-lg').attr('data-id'), this.value); })
		.on('input change', '[data-champ]', function () {
			const id = +$(this).closest('.au-lg').attr('data-id'); if (!brouillon[id]) { return; } brouillon[id][this.getAttribute('data-champ')] = this.value;
			if (this.type === 'date') { analyserDate(id); }
		}).on('click', '[data-prop]', function () {
			const id = +$(this).closest('.au-lg').attr('data-id'), b = brouillon[id]; if (!b) { return; }
			const champ = b.issue === 'renvoi' ? 'renvoi_au' : 'date_delibere'; b[champ] = this.getAttribute('data-prop'); $('#lg-' + id + ' [data-champ="' + champ + '"]').val(b[champ]); analyserDate(id);
		}).on('keydown', '.au-detail input', function (e) { if (e.key === 'Enter') { e.preventDefault(); enregistrer(+$(this).closest('.au-lg').attr('data-id'), false); } if (e.key === 'Escape') { fermer(+$(this).closest('.au-lg').attr('data-id')); } });
	});
})();
</script>
