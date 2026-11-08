{{-- Analyse et pilotage : tout se met à jour sans recharger la page. Variables : $iaDisponible, $quota, $peutIA, $historique --}}
<script type="text/javascript">
(function () {
	'use strict';

	const URL_DONNEES = @js(route('audiences.pilotage.donnees'));
	const URL_PDF = @js(route('audiences.pilotage.pdf'));
	const URL_ANALYSER = @js(route('audiences.pilotage.analyser'));
	const URL_SYNTHESE = @js(route('audiences.pilotage.synthese', 0));
	const CSRF = @js(csrf_token());
	const IA_DISPO = @js($iaDisponible), PEUT_IA = @js($peutIA);
	let quota = @js($quota), historique = @js($historique);
	const LIEN_SUIVI = @js(auth()->user()->can('alertes.voir') ? route('alertes.suivi') : null);
	const LIENS = @js([
		'renvois' => auth()->user()->can('renvois.voir') ? route('audiences.renvois') : null,
		'redaction' => auth()->user()->can('redaction.voir') ? route('decisions.redaction') : null,
		'feuille' => auth()->user()->can('feuille.voir') ? route('audiences.feuille') : null,
		'suites' => auth()->user()->can('suites.voir') ? route('audiences.suites') : null,
	]);
	const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const COULEURS_SUITES = { renvoi: '#F59A3B', delibere: '#6B7BE8', juge: '#00A36C', radie: '#94A3B8', joint: '#2AA7B2', desiste: '#A66BC9' };

	let historiqueOuvert = false, periode = '30j', D = null, A = null, IA = null, onglet = 'flux', ongletDonut = 'suites', ongletAnalyse = IA_DISPO ? 'ia' : 'auto', graphe = null, donut = null, jauge = null, tri = { cle: 'stock', sens: -1 }, minuteur = null, enCours = 0;

	const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	const fmt = function (n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)).replace(/[  ]/g, ' '); };
	const pct = function (n) { return n == null ? '—' : n + ' %'; };

	/* ---------------------------------------------------------------- indice de santé */
	function sante() {
		const s = A.sante, couleurs = { bon: '#00794C', vigilance: '#E8710A', alerte: '#B42318' };
		const facteurs = s.facteurs.length ? s.facteurs.map(function (f) { return '<div class="pi-facteur"><span>' + esc(f.libelle) + '</span><b>' + f.points + '</b></div>'; }).join('') : '<div class="pi-facteur pi-facteur--ok"><span><i class="fa-solid fa-circle-check"></i>Rien ne fait baisser l\'indice.</span></div>';
		$('#sante').attr('class', 'pi-sante pi-sante--' + s.niveau).html('<div class="pi-sante__titre">Indice de santé du Siège</div><div id="jauge"></div><div class="pi-sante__verdict">' + esc(s.libelle) + '</div>'
			+ '<div class="pi-sante__facteurs">' + facteurs + '</div>'
			+ '<div class="pi-sante__sous"><i class="fa-solid fa-circle-question"></i>Comment est calculé cet indice ?</div>'
			+ '<div class="pi-explication" id="explication"><p>L\'indice résume la santé du tribunal en <b>une note sur 100</b>. On part de 100 et on retire des points pour chaque difficulté : stock qui grossit, affaires en retard, décisions non rédigées, feuilles d\'audience non saisies, alertes graves, détenus à échéance proche.</p><p class="pi-explication__fin"><b>80 et plus</b> : maîtrisée · <b>60 à 79</b> : vigilance · <b>moins de 60</b> : préoccupante</p><button type="button" class="pi-plus" id="btn-plus"><i class="fa-solid fa-circle-plus"></i>Voir plus</button></div>');
		if (jauge) { jauge.destroy(); }
		jauge = new ApexCharts(document.querySelector('#jauge'), {
			chart: { type: 'radialBar', height: 170, sparkline: { enabled: true }, animations: { enabled: !reduit } }, series: [s.score], colors: [couleurs[s.niveau]],
			plotOptions: { radialBar: { startAngle: -125, endAngle: 125, hollow: { size: '62%' }, track: { background: '#E5EDE8', strokeWidth: '100%' }, dataLabels: { name: { show: true, offsetY: 20, fontSize: '12px', color: '#2F3A52', fontFamily: 'Inter' }, value: { offsetY: -12, fontSize: '38px', fontWeight: 800, fontFamily: 'Inter', color: couleurs[s.niveau], formatter: function (v) { return Math.round(v); } } } } },
			labels: ['sur 100'], stroke: { lineCap: 'round' },
		}); jauge.render();
	}

	/* La fenêtre « Voir plus » : chaque règle expliquée, avec ce qu'elle retire. */
	function expliquerIndice() {
		const s = A.sante;
		$('#corps-indice').html('<p class="pi-ex__intro">On part de <b>100</b>. Pour chaque difficulté ci-dessous, on retire des points, jusqu\'à un maximum. Plus la note est basse, plus la situation demande une action.</p>'
			+ '<h5 class="pi-ex__titre">Ce qui le fait baisser aujourd\'hui</h5>' + (s.facteurs.length ? '<ul class="pi-ex__liste">' + s.facteurs.map(function (f) { return '<li><span>' + esc(f.libelle) + '</span><b>' + f.points + '</b></li>'; }).join('') + '</ul>' : '<p>Rien ne fait baisser l\'indice : il est à 100.</p>')
			+ '<h5 class="pi-ex__titre">Les règles de calcul</h5><table class="pi-ex__table"><tbody>' + (s.regles || []).map(function (r) { return '<tr><td><b>' + esc(r.critere) + '</b><small>' + esc(r.explication) + '</small></td><td>' + esc(r.retrait) + '</td></tr>'; }).join('') + '</tbody></table>'
			+ '<p class="pi-ex__intro" style="margin-top:12px"><b>80 et plus</b> : situation maîtrisée · <b>60 à 79</b> : points de vigilance · <b>moins de 60</b> : situation préoccupante.</p>');
		$('#modal-indice').modal('show');
	}

	/* ---------------------------------------------------------------- indicateurs clés */
	function kpis() {
		const k = D.kpis;
		const kpiPct = function (p) { if (p == null || isNaN(p)) { return ''; } const v = Math.round(Math.max(0, Math.min(100, p))); return ' data-pct="' + v + '" style="--pct:' + v + '"'; };
		const cartes = [
			{ c: 'vert', i: 'fa-folder-open', l: 'Affaires en cours', v: k.stock, chip: ['fa-hourglass-half', 'stock du tribunal'] },
			{ c: 'sarcelle', i: 'fa-file-circle-plus', l: 'Affaires enrôlées', v: k.entrees, chip: ['fa-calendar-plus', 'sur la période'] },
			{ c: 'ardoise', i: 'fa-flag-checkered', l: 'Affaires terminées', v: k.sorties, chip: ['fa-gavel', 'jugées, radiées, jointes…'], href: LIENS.suites },
			{ c: k.taux_sortie === null ? 'sarcelle' : (k.taux_sortie < 50 ? 'rouge' : (k.taux_sortie < 80 ? 'orange' : 'vert')), i: 'fa-gauge-high', l: 'Taux de traitement', v: k.taux_sortie, suf: ' %', chip: ['fa-scale-balanced', 'terminées pour 100 enrôlées'], pct: k.taux_sortie },
			{ c: k.delai_moyen !== null && k.delai_moyen > 183 ? 'orange' : 'sarcelle', i: 'fa-stopwatch', l: 'Délai moyen', v: k.delai_moyen, suf: ' j', chip: ['fa-calendar-day', 'de l\'enrôlement à la fin'] },
			{ c: k.retards <= 0 ? 'vert' : (k.stock > 0 && k.retards / k.stock >= 0.3 ? 'rouge' : 'orange'), i: 'fa-clock', l: 'Affaires en retard', v: k.retards, chip: ['fa-triangle-exclamation', 'échéance dépassée'], href: LIENS.renvois, pct: k.stock > 0 ? k.retards / k.stock * 100 : null },
			{ c: k.a_rediger > 0 ? 'orange' : 'vert', i: 'fa-pen-nib', l: 'Décisions à rédiger', v: k.a_rediger, chip: ['fa-gavel', k.a_rediger ? 'la plus ancienne : ' + k.plus_ancienne_decision + ' j' : 'rien en attente'], href: LIENS.redaction },
			{ c: k.alertes_critiques > 0 ? 'rouge' : 'vert', i: 'fa-bell', l: 'Alertes critiques', v: k.alertes_critiques, chip: ['fa-list-check', k.alertes_ouvertes + ' alerte' + (k.alertes_ouvertes > 1 ? 's' : '') + ' ouverte' + (k.alertes_ouvertes > 1 ? 's' : '')], href: LIEN_SUIVI, pct: k.alertes_ouvertes > 0 ? k.alertes_critiques / k.alertes_ouvertes * 100 : null },
		];
		$('#kpis').html(cartes.map(function (c) {
			const corps = '<div class="au-kpi__icone"><i class="fa-solid ' + c.i + '"></i></div><span class="au-kpi__label">' + esc(c.l) + '</span><div class="pi-kpi__bas"><div class="au-kpi__valeur"><span>' + (c.v == null ? '—' : fmt(c.v) + (c.suf || '')) + '</span></div><div class="au-chip"><i class="fa-solid ' + c.chip[0] + '"></i><span>' + esc(c.chip[1]) + '</span></div></div>';
			const pc = kpiPct(c.pct);
			return c.href ? '<a class="au-kpi au-kpi--' + c.c + '"' + pc + ' href="' + esc(c.href) + '">' + corps + '</a>' : '<div class="au-kpi au-kpi--' + c.c + '"' + pc + '>' + corps + '</div>';
		}).join(''));
	}

	/* ---------------------------------------------------------------- graphiques */
	/* Hauteur utile d'un graphique : la carte de l'indice fixe la hauteur de la ligne, le graphique la remplit (pas de vide dessous). */
	function hauteurDispo(id) {
		const $s = $('#sante'), $c = $('#' + id).closest('.pi-carte');
		if (!$s.length || !$c.length || $(window).width() < 1200) { return 290; }
		const libre = $s.outerHeight() - $('#kpis').outerHeight() - 22 - ($c.find('.pi-carte__tete').outerHeight() + 18 + 14 + 12);
		return Math.max(260, Math.floor(libre));
	}
	const base = { fontFamily: 'Inter, sans-serif', toolbar: { show: false }, animations: { enabled: !reduit } };

	function dessinerGraphe() {
		if (graphe) { graphe.destroy(); graphe = null; } $('#graphe').empty();
		let options;
		if (onglet === 'flux') {
			$('#titre-graphe').text('Affaires enrôlées et terminées'); $('#sous-graphe').text('Affaires enregistrées au tribunal et affaires jugées, radiées, jointes ou désistées, mois par mois');
			options = { chart: Object.assign({ type: 'bar', height: hauteurDispo('graphe') }, base), series: [{ name: 'Enrôlées', data: D.mensuel.entrees }, { name: 'Terminées', data: D.mensuel.sorties }], colors: ['#00A36C', '#F59A3B'],
				plotOptions: { bar: { borderRadius: 5, columnWidth: '62%' } }, dataLabels: { enabled: false }, xaxis: { categories: D.mensuel.mois, labels: { style: { colors: '#2F3A52', fontSize: '11.5px' } } },
				yaxis: { labels: { style: { colors: '#2F3A52' }, formatter: function (v) { return Math.round(v); } } }, grid: { borderColor: '#E3E9F0', strokeDashArray: 4 }, legend: { position: 'top', horizontalAlign: 'right', fontSize: '13px' }, tooltip: { shared: true, intersect: false } };
		} else {
			const lignes = D.par_chambre.filter(function (c) { return c.stock > 0 || c.retards > 0; }).sort(function (a, b) { return b.stock - a.stock; }).slice(0, 10);
			$('#titre-graphe').text('Affaires en cours par chambre'); $('#sous-graphe').text('Dont celles qui ont une échéance dépassée (en rouge) — les dix chambres les plus chargées');
			options = { chart: Object.assign({ type: 'bar', height: Math.max(hauteurDispo('graphe'), lignes.length * 36 + 60), stacked: true }, base), series: [{ name: 'Dans les délais', data: lignes.map(function (c) { return Math.max(0, c.stock - Math.min(c.stock, c.retards)); }) }, { name: 'En retard', data: lignes.map(function (c) { return Math.min(c.stock, c.retards); }) }],
				colors: ['#2E9E6B', '#D64545'], plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } }, dataLabels: { enabled: true, style: { fontSize: '11.5px' }, formatter: function (v) { return v > 0 ? v : ''; } },
				xaxis: { categories: lignes.map(function (c) { return c.libelle; }), labels: { style: { colors: '#2F3A52' } } }, yaxis: { labels: { style: { colors: '#1F2A44', fontSize: '12px', fontWeight: 600 }, maxWidth: 190 } }, grid: { borderColor: '#E3E9F0', strokeDashArray: 4 }, legend: { position: 'top', horizontalAlign: 'right', fontSize: '13px' } };
		}
		graphe = new ApexCharts(document.querySelector('#graphe'), options); graphe.render();
	}

	function dessinerDonut() {
		if (donut) { donut.destroy(); donut = null; }
		$('#donut').empty();
		const suites = ongletDonut === 'suites';
		const donnees = suites ? D.suites.filter(function (s) { return s.n > 0; }) : D.alertes_par_type.filter(function (s) { return s.n > 0; });
		$('#titre-donut').text(suites ? 'Suites notées' : 'Alertes ouvertes par type'); $('#sous-donut').text(suites ? 'Ce qui s\'est passé aux audiences de la période' : 'Ce qui demande une action aujourd\'hui');
		if (!donnees.length) { $('#donut').html('<div class="al-vide"><i class="fa-solid fa-circle-check"></i>' + (suites ? 'Aucune suite notée sur la période.' : 'Aucune alerte ouverte.') + '</div>'); return; }
		const total = donnees.reduce(function (a, s) { return a + s.n; }, 0);
		donut = new ApexCharts(document.querySelector('#donut'), {
			chart: Object.assign({ type: 'donut', height: hauteurDispo('donut') }, base), series: donnees.map(function (s) { return s.n; }), labels: donnees.map(function (s) { return s.libelle; }),
			colors: suites ? donnees.map(function (s) { return COULEURS_SUITES[s.cle]; }) : ['#E8710A', '#B42318', '#3B76B8', '#00A36C', '#6B7BE8', '#2AA7B2', '#A66BC9', '#94A3B8', '#F59A3B', '#0A7C86'],
			plotOptions: { pie: { donut: { size: '66%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '13px', color: '#2F3A52', formatter: function () { return total; } }, value: { fontSize: '30px', fontWeight: 800, color: '#0F1B33' } } } } },
			dataLabels: { enabled: false }, legend: { position: 'bottom', fontSize: '12.5px', labels: { colors: '#1F2A44' } }, stroke: { width: 2, colors: ['#fff'] },
		}); donut.render();
	}

	/* ---------------------------------------------------------------- analyse (automatique ou IA) */
	function note(n, source) {
		const forts = n.points_forts || [], attention = n.points_attention || [], reco = n.recommandations || [];
		return '<div class="pi-note"><div class="pi-note__situation"><i class="fa-solid ' + (source === 'ia' ? 'fa-wand-magic-sparkles' : 'fa-chart-line') + '"></i><div>' + esc(n.situation) + '</div></div>'
			+ '<div class="pi-note__grille">'
			+ '<section class="pi-bloc pi-bloc--forts"><h4><i class="fa-solid fa-circle-check"></i>Points forts</h4><ul>' + forts.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul></section>'
			+ '<section class="pi-bloc pi-bloc--attention"><h4><i class="fa-solid fa-triangle-exclamation"></i>Points d\'attention</h4><ul>' + (attention.length ? attention.map(function (a) { const t = typeof a === 'string' ? { texte: a } : a; return '<li>' + (t.gravite ? '<span class="pi-grav pi-grav--' + esc(t.gravite) + '">' + (t.gravite === 'critique' ? 'Critique' : 'Attention') + '</span>' : '') + esc(t.texte) + '</li>'; }).join('') : '<li>Aucun point d\'attention sur la période.</li>') + '</ul></section>'
			+ '<section class="pi-bloc pi-bloc--reco"><h4><i class="fa-solid fa-route"></i>Recommandations</h4><ol>' + reco.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ol></section></div></div>';
	}

	/* Le texte rédigé par l'IA (titres « ## » et puces « - ») devient la même note que l'analyse automatique. */
	function lireTexte(texte) {
		const parts = { situation: '', points_forts: [], points_attention: [], recommandations: [] }; let cle = null;
		String(texte).split('\n').forEach(function (l) {
			const t = l.trim(); if (!t) { return; }
			if (/^##\s*Situation/i.test(t)) { cle = 'situation'; } else if (/^##\s*Points forts/i.test(t)) { cle = 'points_forts'; } else if (/^##\s*Points d/i.test(t)) { cle = 'points_attention'; } else if (/^##\s*Recommandations/i.test(t)) { cle = 'recommandations'; }
			else if (cle === 'situation') { parts.situation += (parts.situation ? ' ' : '') + t; } else if (cle) { parts[cle].push(t.replace(/^-\s*/, '')); }
		});
		return parts;
	}

	function analyseAuto() {
		$('#analyse-auto').html(note(A, 'auto'));
		$('#sous-analyse').text(ongletAnalyse === 'auto' ? 'Règles et modèles de phrases : toujours disponibles, sans appel externe, mêmes chiffres = même analyse.' : 'Rédigée par l\'IA à partir des totaux ci-dessus (aucun nom, aucun numéro d\'affaire).');
	}

	function analyseIA() {
		const $z = $('#analyse-ia');
		if (!IA_DISPO) {
			$z.html('<div class="pi-ia-vide"><i class="fa-solid fa-wand-magic-sparkles"></i><div><b>L\'analyse par l\'IA n\'est pas activée.</b><br>Elle est facultative : l\'analyse automatique ci-contre reste complète. Pour l\'activer, un administrateur choisit « Oui » dans <b>Paramètres &gt; Réglages &gt; Pilotage et IA</b> et place la clé d\'API dans le fichier <code>.env</code> du serveur (<code>PILOTAGE_IA_CLE</code>). Seuls des totaux sont transmis, jamais un nom ni un numéro d\'affaire.</div></div>');
			return;
		}
		let html = '';
		if (PEUT_IA) {
			html += '<div class="pi-ia-meta pi-noprint" style="margin-bottom:12px"><button type="button" class="au-btn au-btn--vert" id="btn-ia"' + (quota <= 0 ? ' disabled' : '') + '><i class="fa-solid fa-wand-magic-sparkles"></i>' + (IA ? 'Relancer l\'analyse par l\'IA' : 'Analyser avec l\'IA') + '</button><span><i class="fa-solid fa-ticket"></i>' + quota + ' analyse' + (quota > 1 ? 's' : '') + ' restante' + (quota > 1 ? 's' : '') + ' aujourd\'hui</span></div>';
		} else {
			html += '<div class="pi-ia-meta" style="margin-bottom:12px"><span><i class="fa-solid fa-lock"></i>Vous pouvez lire les analyses de l\'IA ; seuls les profils autorisés peuvent en demander une.</span></div>';
		}
		if (IA) {
			html += '<div class="pi-ia-meta" style="margin-bottom:12px"><span><i class="fa-solid fa-circle-info"></i>Note rédigée le ' + esc(IA.cree_le) + (IA.auteur ? ' à la demande de ' + esc(IA.auteur) : '') + ' · ' + esc(IA.periode) + ' (du ' + esc(IA.du) + ' au ' + esc(IA.au) + ')' + (IA.modele ? ' · ' + esc(IA.modele) : '') + '</span></div>' + note(lireTexte(IA.contenu), 'ia');
		} else {
			html += '<div class="pi-ia-vide"><i class="fa-solid fa-wand-magic-sparkles"></i><div><b>Aucune note de l\'IA pour cette période.</b><br>' + (PEUT_IA ? 'Cliquez sur « Analyser avec l\'IA » : la note est rédigée en quelques secondes à partir des mêmes chiffres.' : 'Demandez à un profil autorisé de lancer l\'analyse.') + '</div></div>';
		}
		if (historique.length) {
			// Les notes précédentes sont repliées : un bouton les affiche ou les cache.
			html += '<div class="pi-historique" data-no-print><button type="button" class="pi-historique__bascule" id="btn-historique" aria-expanded="' + (historiqueOuvert ? 'true' : 'false') + '"><i class="fa-solid fa-clock-rotate-left"></i>Notes précédentes (' + historique.length + ')<i class="fa-solid fa-chevron-' + (historiqueOuvert ? 'up' : 'down') + ' pi-historique__fleche"></i></button>'
				+ '<div class="pi-historique__liste"' + (historiqueOuvert ? '' : ' hidden') + '>' + historique.map(function (h) { return '<button type="button" data-synthese="' + h.id + '" title="' + esc(h.perimetre || '') + '">' + esc(h.cree_le.split(' à ')[0]) + ' · ' + esc(h.periode) + (h.source === 'ia' ? ' · IA' : '') + '</button>'; }).join('') + '</div></div>';
		}
		$z.html(html);
	}

	function ecranAnalyse() {
		$('#btn-pdf').attr('href', URL_PDF + '?periode=' + periode + '&source=' + (ongletAnalyse === 'ia' && IA ? 'ia&synthese=' + IA.id : 'auto'));
		$('#onglets-analyse button').removeClass('is-actif').filter('[data-a="' + ongletAnalyse + '"]').addClass('is-actif');
		$('#analyse-auto').prop('hidden', ongletAnalyse !== 'auto'); $('#analyse-ia').prop('hidden', ongletAnalyse !== 'ia');
		$('#sous-analyse').text(ongletAnalyse === 'auto' ? 'Règles et modèles de phrases : toujours disponibles, sans appel externe, mêmes chiffres = même analyse.' : 'Rédigée par l\'IA à partir des totaux ci-dessus (aucun nom, aucun numéro d\'affaire).');
	}

	/* Génération de la note par l'IA : panneau de progression (étapes qui s'allument), puis la note apparaît bloc par bloc, sans recharger la page. */
	let minuteurEtapes = null;
	function etapesIA() {
		const liste = ['Collecte des indicateurs', 'Analyse des écarts et des retards', 'Rédaction de la note par l\'IA', 'Mise en forme de la note'];
		const $ol = $('#pi-etapes').empty();
		liste.forEach(function (t, i) { $ol.append('<li class="' + (i === 0 ? 'pi-etape--active' : '') + '">' + esc(t) + '</li>'); });
		let courant = 0; clearInterval(minuteurEtapes);
		minuteurEtapes = setInterval(function () {
			const $l = $ol.children(); if (courant >= $l.length - 2) { return; }   // l'étape de rédaction dure jusqu'à la réponse
			$l.eq(courant).attr('class', 'pi-etape--faite'); courant++; $l.eq(courant).attr('class', 'pi-etape--active');
		}, 3200);
	}
	function finEtapes() { clearInterval(minuteurEtapes); $('#pi-etapes li').attr('class', 'pi-etape--faite'); }

	function demanderIA() {
		const $b = $('#btn-ia'); $b.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>Génération…');
		$('#analyse-ia .pi-erreur-ia').remove();
		$('#analyse-ia').children().not('.pi-ia-meta:first').not('.pi-historique').hide();
		$('#analyse-ia').append('<div class="pi-chargement" id="pi-chargement" aria-live="polite"><div class="pi-chargement__tete"><span class="pi-spinner"></span><div><strong>L\'IA analyse les chiffres…</strong><small>Comptez entre 10 et 40 secondes. Seuls des totaux sont transmis : aucun nom, aucun numéro d\'affaire.</small></div></div><ol class="pi-etapes" id="pi-etapes"></ol><div class="pi-squelette"><i style="width:94%"></i><i style="width:88%"></i><i style="width:91%"></i><i style="width:62%"></i></div></div>');
		etapesIA();
		const ctrl = new AbortController(), delai = setTimeout(function () { ctrl.abort(); }, 150000);
		fetch(URL_ANALYSER, { method: 'POST', credentials: 'same-origin', signal: ctrl.signal, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ periode: periode }) })
			.then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { throw new Error((j.errors ? Object.values(j.errors)[0][0] : j.message) || 'Erreur'); } return j; }); })
			.then(function (j) {
				clearTimeout(delai); finEtapes();
				setTimeout(function () {
					IA = j.ia; quota = j.quota; historique.unshift(j.ia); historique = historique.slice(0, 8);
					analyseIA(); ecranAnalyse();
					$('#analyse-ia .pi-note > *, #analyse-ia .pi-note__grille > *').each(function (i) { $(this).addClass('pi-apparait').css('animation-delay', (i * 140) + 'ms'); });
					toast('success', 'Analyse de l\'IA rédigée.');
				}, 450);
			})
			.catch(function (e) {
				clearTimeout(delai); finEtapes(); $('#pi-chargement').remove();
				analyseIA(); ecranAnalyse();
				$('#analyse-ia').prepend('<div class="au-erreur pi-erreur-ia" style="margin-bottom:12px"><i class="fa-solid fa-circle-exclamation"></i> ' + esc(e.name === 'AbortError' ? 'La génération prend trop de temps : réessayez dans un instant.' : e.message) + '</div>');
			});
	}

	function toast(icone, titre) { if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: icone, title: titre, showConfirmButton: false, timer: 3200, timerProgressBar: true }); } }

	/* ---------------------------------------------------------------- situation par chambre : la liste coupée en deux blocs */
	function tableau() {
		const lignes = D.par_chambre.slice().sort(function (a, b) {
			const x = a[tri.cle], y = b[tri.cle];
			if (tri.cle === 'libelle') { return String(x).localeCompare(String(y), 'fr', { numeric: true }) * tri.sens; }
			return ((x == null ? -1 : x) - (y == null ? -1 : y)) * tri.sens;
		});
		const maxStock = Math.max(1, Math.max.apply(null, D.par_chambre.map(function (c) { return c.stock; })));
		const pill = function (v, mauvais, moyen, suf) { return '<span class="pi-pill pi-pill--' + (v == null ? 'neutre' : (mauvais(v) ? 'mauvais' : (moyen(v) ? 'moyen' : 'bon'))) + '">' + (v == null ? '—' : v + (suf || '')) + '</span>'; };
		const ligne = function (c) {
			return '<tr><td class="au-tbl__num">' + esc(c.libelle) + '<span class="pi-barre"><i style="width:' + Math.round(c.stock / maxStock * 100) + '%"></i></span></td>'
				+ '<td><b>' + c.stock + '</b></td><td>' + c.entrees + '</td><td>' + c.sorties + '</td>'
				+ '<td>' + pill(c.taux_sortie, function (v) { return v < 50; }, function (v) { return v < 80; }, ' %') + '</td>'
				+ '<td>' + pill(c.delai_moyen, function (v) { return v > 183; }, function (v) { return v > 120; }, ' j') + '</td>'
				+ '<td>' + pill(c.retards, function (v) { return v >= 3; }, function (v) { return v >= 1; }) + '</td>'
				+ '<td>' + pill(c.a_rediger, function (v) { return v >= 3; }, function (v) { return v >= 1; }) + '</td>'
				+ '<td>' + pill(c.renvois_pct, function (v) { return v > 60; }, function (v) { return v > 40; }, ' %') + '</td>'
				+ '<td>' + pill(c.alertes, function () { return c.critiques > 0; }, function (v) { return v >= 1; }) + '</td></tr>';
		};
		const moitie = Math.ceil(lignes.length / 2);
		const vide = '<tr><td colspan="10"><div class="au-vide"><i class="fa-solid fa-landmark"></i>Aucune chambre dans votre périmètre.</div></td></tr>';
		$('#corps-a').html(lignes.slice(0, moitie).map(ligne).join('') || vide);
		$('#corps-b').html(lignes.slice(moitie).map(ligne).join('') || (lignes.length ? '' : vide));
		$('.pi-tbl th i').remove(); $('.pi-tbl th[data-tri="' + tri.cle + '"]').append('<i class="fa-solid fa-arrow-' + (tri.sens < 0 ? 'down' : 'up') + '"></i>');
	}

	/* ---------------------------------------------------------------- alertes */
	function alertes() {
		const liste = D.alertes_recentes;
		$('#alertes').html(liste.length ? liste.map(function (a) {
			const lien = a.url || LIEN_SUIVI || '#';
			return '<a class="pi-alerte pi-alerte--' + esc(a.niveau) + '" href="' + esc(lien) + '"><i class="pi-alerte__bande"></i><div><b>' + esc(a.titre) + '</b><small>' + esc(a.type_libelle) + (a.chambre ? ' · ' + esc(a.chambre) : '') + ' · À faire : ' + esc(a.action) + '</small></div><span class="au-badge au-badge--' + (a.retard > 0 ? 'rouge' : 'gris') + '">' + (a.retard > 0 ? 'En retard de ' + a.retard + ' j' : 'Échéance ' + esc(a.echeance)) + '</span></a>';
		}).join('') : '<div class="al-vide"><i class="fa-solid fa-circle-check"></i>Aucune alerte ouverte : toutes les échéances du circuit sont respectées.</div>');
	}

	function bandeau(heure) {
		$('#bandeau').html('<span><i class="fa-solid fa-landmark"></i>' + esc(D.perimetre) + '</span><span><i class="fa-regular fa-calendar"></i>Du ' + esc(D.periode.debut.split('-').reverse().join('/')) + ' au ' + esc(D.periode.fin.split('-').reverse().join('/')) + ' (' + D.periode.jours + ' jours)</span>'
			+ '<span class="pi-bandeau__fin"><i class="fa-solid fa-rotate"></i>Actualisé à ' + esc(heure) + ' · <button type="button" id="btn-actualiser" data-no-print>Actualiser</button></span>');
	}

	/* ---------------------------------------------------------------- chargement */
	function afficher(heure) { bandeau(heure); sante(); kpis(); dessinerGraphe(); dessinerDonut(); analyseAuto(); analyseIA(); ecranAnalyse(); tableau(); alertes(); }

	function charger(frais) {
		const n = ++enCours; $('#pi-loader').prop('hidden', false);
		fetch(URL_DONNEES + '?periode=' + encodeURIComponent(periode) + (frais ? '&frais=1' : ''), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { if (!r.ok) { throw new Error('Erreur'); } return r.json(); })
			.then(function (j) { if (n !== enCours) { return; } D = j.donnees; A = j.analyse; IA = j.ia; afficher(j.genere_le); })
			.catch(function () { $('#bandeau').html('<span><i class="fa-solid fa-triangle-exclamation" style="color:#B42318"></i>Impossible de charger les chiffres. <button type="button" id="btn-actualiser">Réessayer</button></span>'); })
			.then(function () { if (n === enCours) { $('#pi-loader').prop('hidden', true); } });
	}

	document.addEventListener('DOMContentLoaded', function () {
		charger(false);
		$('#periodes').on('click', 'button', function () { periode = this.getAttribute('data-periode'); $('#periodes button').removeClass('is-actif'); $(this).addClass('is-actif'); charger(false); });
		$(document).on('click', '#btn-actualiser', function () { charger(true); });
		$(document).on('click', '#btn-plus', expliquerIndice);
		$(document).on('click', '#btn-historique', function () { historiqueOuvert = !historiqueOuvert; $(this).attr('aria-expanded', historiqueOuvert ? 'true' : 'false').find('.pi-historique__fleche').attr('class', 'fa-solid fa-chevron-' + (historiqueOuvert ? 'up' : 'down') + ' pi-historique__fleche'); $(this).next('.pi-historique__liste').prop('hidden', !historiqueOuvert); });
		$(document).on('click', '#btn-explique', function () { const $z = $('#explication'), ouvrir = $z.prop('hidden'); $z.prop('hidden', !ouvrir); $(this).attr('aria-expanded', ouvrir ? 'true' : 'false'); });
		$('#onglets-graphe').on('click', 'button', function () { onglet = this.getAttribute('data-g'); $('#onglets-graphe button').removeClass('is-actif'); $(this).addClass('is-actif'); dessinerGraphe(); });
		$('#onglets-donut').on('click', 'button', function () { ongletDonut = this.getAttribute('data-d'); $('#onglets-donut button').removeClass('is-actif'); $(this).addClass('is-actif'); dessinerDonut(); });
		$('#onglets-analyse').on('click', 'button', function () { ongletAnalyse = this.getAttribute('data-a'); ecranAnalyse(); });
		$('.pi-tbl').on('click', 'th[data-tri]', function () { const c = this.getAttribute('data-tri'); tri = { cle: c, sens: tri.cle === c ? -tri.sens : (c === 'libelle' ? 1 : -1) }; tableau(); });
		$('#analyse-ia').on('click', '#btn-ia', demanderIA).on('click', '[data-synthese]', function () {
			const $b = $(this); $b.addClass('is-actif');
			fetch(URL_SYNTHESE.replace(/\/0$/, '/' + this.getAttribute('data-synthese')), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) { if (!r.ok) { throw new Error('Erreur'); } return r.json(); }).then(function (j) { IA = j.ia; analyseIA(); }).catch(function () { toast('error', 'Impossible de lire cette note.'); });
		});
		minuteur = setInterval(function () { if (!document.hidden) { charger(false); } }, 120000);
	});
})();
</script>
