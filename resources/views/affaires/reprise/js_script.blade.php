<script type="text/javascript">
/* Reprise de l'existant : modèle, envoi, analyse, contrôle puis importation. JavaScript natif, sans dépendance à jQuery. */
(function () {
	'use strict';

	const APP_NOM = @js(config('app.name'));
	const PLACE = 'XXXXXXXXXXXXXXXXXXXXXXXXXX';
	const URLS = {
		modele: @js(route('affaires.reprise.modele', 'RG')),
		televerser: @js(route('affaires.reprise.televerser')),
		analyser: @js(route('affaires.reprise.analyser', 'XXXXXXXXXXXXXXXXXXXXXXXXXX')),
		controler: @js(route('affaires.reprise.controler', 'XXXXXXXXXXXXXXXXXXXXXXXXXX')),
		importer: @js(route('affaires.reprise.importer', 'XXXXXXXXXXXXXXXXXXXXXXXXXX')),
		etat: @js(route('affaires.reprise.etat', 'XXXXXXXXXXXXXXXXXXXXXXXXXX')),
		rapport: @js(route('affaires.reprise.rapport', 'XXXXXXXXXXXXXXXXXXXXXXXXXX')),
		annuler: @js(route('affaires.reprise.annuler', 'XXXXXXXXXXXXXXXXXXXXXXXXXX')),
		liste: @js(route('affaires.liste')),
	};
	const ETATS = { erreur: ['Erreur', 'imp-pill--erreur'], nouvelle: ['Nouvelle', 'imp-pill--nouvelle'], doublon: ['Doublon', 'imp-pill--doublon'] };
	const ETAPES_ANALYSE = [['envoi', 'Envoi du fichier', 10], ['verif', 'Vérification du classeur et de ses titres', 10], ['lecture', "Lecture des lignes de l'onglet « Registre »", 20], ['controle', 'Contrôle de chaque ligne', 55], ['resume', 'Préparation du résultat', 5]];
	const ETAPES_IMPORT = [['import', 'Création des affaires et de leurs parties', 90], ['fin', 'Enregistrement et nettoyage', 10]];

	const etat = { ref: null, serie: 'RG', lignes: [], filtre: 'toutes', importable: false, total: 0, valides: 0 };
	const suivi = { etapes: [], courante: 0, fait: 0, total: 0, octets: false, pourcent: 0, debut: 0, chrono: null };
	const el = function (id) { return document.getElementById(id); };
	const nb = function (n) { return Number(n || 0).toLocaleString('fr-FR'); };
	const url = function (cle, ref) { return URLS[cle].replace(PLACE, ref || etat.ref); };
	const pause = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
	function esc(t) { return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
	function octets(n) { return n >= 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.round(n / 1024)) + ' Ko'; }
	function alerte(o) { return window.Swal ? window.Swal.fire(o) : Promise.resolve({ isConfirmed: window.confirm(String(o.title || '') + '\n' + String(o.text || o.html || '').replace(/<[^>]+>/g, '')) }); }
	function jeton() { return document.querySelector('meta[name="csrf-token"]').content; }
	function etape(n) { const s = el('imp_stepper'); if (s) { s.setAttribute('data-etape', n); } }

	/* ---------- appels au serveur ---------- */
	async function appel(cle, methode, ref) {
		let r;
		try { r = await fetch(url(cle, ref), { method: methode || 'POST', headers: { 'X-CSRF-TOKEN': jeton(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }); }
		catch (e) { return { ok: false, statut: 0, json: { message: 'Le serveur ne répond pas. Vérifiez la connexion puis reprenez.' } }; }
		let json = {};
		try { json = await r.json(); } catch (e) { json = { message: r.status === 419 ? 'Votre session a expiré : rechargez la page.' : r.status === 403 ? "Vous n'avez pas le droit d'effectuer cette étape." : 'Réponse inattendue du serveur (' + r.status + ').' }; }
		if (r.status === 422 && json.errors && !json.message) { json.message = Object.values(json.errors)[0][0]; }
		return { ok: r.ok && json.status !== false, statut: r.status, json: json };
	}

	function envoyerFichier(fichier, serie) {
		return new Promise(function (resolve, reject) {
			const fd = new FormData(); fd.append('fichier', fichier); fd.append('serie', serie); fd.append('_token', jeton());
			const xhr = new XMLHttpRequest();
			xhr.open('POST', URLS.televerser); xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			xhr.upload.onprogress = function (e) { if (e.lengthComputable) { appliquer('envoi', e.loaded, e.total, true); } };
			xhr.timeout = 300000;
			xhr.onerror = function () { reject(new Error('Le fichier n\'a pas pu être envoyé : vérifiez la connexion.')); };
			xhr.ontimeout = function () { reject(new Error("Le serveur met trop de temps à répondre. Découpez le fichier en plusieurs imports.")); };
			xhr.onload = function () {
				let r = {}; try { r = JSON.parse(xhr.responseText); } catch (e) { r = { message: xhr.status === 419 ? 'Votre session a expiré : rechargez la page.' : xhr.status === 413 ? 'Le fichier dépasse la taille permise par le serveur.' : 'Réponse inattendue du serveur (' + xhr.status + ').' }; }
				if (xhr.status === 422 && r.errors && !r.message) { r.message = Object.values(r.errors)[0][0]; }
				if (xhr.status !== 200 || r.status === false) { reject(new Error(r.message || 'Le fichier a été refusé.')); } else { resolve(r); }
			};
			xhr.send(fd);
		});
	}

	/* ---------- barre de progression pondérée, comme un assistant d'installation ---------- */
	function global() {
		const total = suivi.etapes.reduce(function (s, e) { return s + e[2]; }, 0); let acquis = 0;
		for (let i = 0; i < suivi.courante; i++) { acquis += suivi.etapes[i][2]; }
		const c = suivi.etapes[suivi.courante];
		if (c && suivi.total > 0) { acquis += c[2] * Math.min(1, suivi.fait / suivi.total); }
		return Math.min(99, Math.round(acquis / total * 100));
	}

	function dessiner(fin) {
		const pct = fin === 'fini' ? 100 : Math.max(suivi.pourcent, global()); suivi.pourcent = pct;
		const b = el('imp-prog-barre'); b.style.width = pct + '%'; b.classList.toggle('imp-fini', fin === 'fini'); b.classList.toggle('imp-erreur', fin === 'erreur');
		el('imp-prog-pct').textContent = pct + ' %'; el('imp-prog-aria').setAttribute('aria-valuenow', pct);
		const c = suivi.etapes[suivi.courante];
		el('imp-prog-libelle').textContent = fin === 'fini' ? 'Terminé' : fin === 'erreur' ? 'Opération interrompue' : (c ? c[1] : '');
		let compteur = '';
		if (!fin && suivi.total > 0) { compteur = suivi.octets ? ' - ' + octets(suivi.fait) + ' / ' + octets(suivi.total) : ' - ' + nb(suivi.fait) + ' / ' + nb(suivi.total); }
		if (!fin && suivi.debut) { compteur += (compteur ? ' · ' : ' - ') + Math.max(0, Math.round((Date.now() - suivi.debut) / 1000)) + ' s'; }
		el('imp-prog-compteur').textContent = compteur;
		el('imp-prog-etapes').innerHTML = suivi.etapes.map(function (e, i) {
			const fait = fin === 'fini' || i < suivi.courante, cours = !fin && i === suivi.courante;
			const icone = fait ? 'fa-solid fa-check' : cours ? 'fa-solid fa-spinner' : 'fa-regular fa-circle';
			return '<li class="imp-etape ' + (fait ? 'imp-etape--fait' : cours ? 'imp-etape--cours' : '') + '"><span class="imp-etape__icone imp-centre"><i class="' + icone + '"></i></span><span>' + esc(e[1]) + '</span>'
				+ (cours && suivi.total > 0 && !suivi.octets ? '<span class="imp-compteur">' + nb(suivi.fait) + ' / ' + nb(suivi.total) + '</span>' : '') + '</li>';
		}).join('');
	}

	function ouvrirProgression(titre, etapes) {
		suivi.etapes = etapes; suivi.courante = 0; suivi.fait = 0; suivi.total = 0; suivi.octets = false; suivi.pourcent = 0; suivi.debut = Date.now();
		el('imp-prog-titre').textContent = titre; el('imp-resultat').hidden = true; el('imp-progression').hidden = false;
		dessiner(); el('imp-progression').scrollIntoView({ behavior: 'smooth', block: 'center' });
		if (suivi.chrono) { clearInterval(suivi.chrono); } suivi.chrono = setInterval(function () { dessiner(); }, 1000);
	}

	function appliquer(cle, fait, total, enOctets) {
		const i = suivi.etapes.findIndex(function (e) { return e[0] === cle; });
		if (i < 0 || i < suivi.courante) { return; }
		suivi.courante = i; suivi.fait = fait || 0; suivi.total = total || 0; suivi.octets = !!enOctets; dessiner();
	}

	async function clore(fin) {
		if (suivi.chrono) { clearInterval(suivi.chrono); suivi.chrono = null; }
		dessiner(fin); await pause(fin === 'fini' ? 600 : 900); el('imp-progression').hidden = true;
	}

	function protegerDepart(actif) { window.onbeforeunload = actif ? function (e) { e.preventDefault(); e.returnValue = ''; return ''; } : null; }

	/* ---------- étape 1 et 2 : modèle et fichier ---------- */
	function majSerie() {
		etat.serie = el('imp-serie').value;
		el('imp-modele').setAttribute('href', URLS.modele.replace(/RG$/, etat.serie)); el('imp-serie-rappel').textContent = etat.serie;
		document.querySelectorAll('[data-colonnes]').forEach(function (b) { b.hidden = b.dataset.colonnes !== etat.serie; });
	}

	function majDepot() {
		const f = el('imp-fichier').files[0], valide = !f || /\.xlsx$/i.test(f.name), d = el('imp-depot');
		d.classList.toggle('imp-depot--rempli', !!f && valide); d.classList.toggle('imp-depot--erreur', !!f && !valide);
		el('imp-depot-icone').className = !f ? 'fa-solid fa-cloud-arrow-up' : valide ? 'fa-solid fa-file-circle-check' : 'fa-solid fa-file-circle-xmark';
		if (!f) { el('imp-depot-titre').innerHTML = 'Glissez votre fichier ici ou <u>parcourez</u>'; el('imp-depot-sous').textContent = 'Format .xlsx · onglet « Registre » du modèle'; return; }
		el('imp-depot-titre').textContent = f.name; el('imp-depot-sous').textContent = valide ? octets(f.size) + ' · prêt à être analysé' : 'Format non pris en charge : un fichier .xlsx est attendu';
	}

	function invalider() { el('imp-resultat').hidden = true; etat.importable = false; etape(el('imp-fichier').files.length ? 2 : 1); }

	/* ---------- analyse : envoi, vérification, lecture, contrôle, résultat ---------- */
	async function analyser() {
		const fichier = el('imp-fichier').files[0];
		if (!fichier) { alerte({ icon: 'warning', title: 'Fichier manquant', text: 'Choisissez le classeur Excel (.xlsx) rempli à partir du modèle.' }); return; }
		if (!/\.xlsx$/i.test(fichier.name)) { alerte({ icon: 'error', title: 'Format refusé', text: 'Seuls les classeurs Excel .xlsx sont acceptés.' }); return; }
		const bouton = el('imp-analyser'); bouton.disabled = true; bouton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analyse en cours…';
		protegerDepart(true);
		try {
			ouvrirProgression('Analyse du fichier', ETAPES_ANALYSE);
			const r = await envoyerFichier(fichier, etat.serie);
			etat.ref = r.reference;
			appliquer('verif', 0, 0); dessiner();
			if (r.deja_importe) { await alerte({ icon: 'info', title: APP_NOM, text: 'Ce fichier exact a déjà été importé. Les numéros déjà enregistrés seront ignorés.' }); }
			appliquer('lecture', 0, 0);
			const a = await appel('analyser');
			if (!a.ok) { throw new Error(a.json.message || 'Le fichier n\'a pas pu être lu.'); }
			etat.total = a.json.total;
			await controle('controle');
			appliquer('resume', 0, 0);
			await afficherResultat();
			await clore('fini');
			el('imp-resultat').hidden = false; etape(3); el('imp-resultat').scrollIntoView({ behavior: 'smooth', block: 'start' });
		} catch (e) {
			try { await clore('erreur'); } catch (x) { /* écran déjà refermé */ }
			invalider();
			alerte({ icon: 'error', title: 'Fichier refusé', html: esc(e.message || 'Analyse impossible.') });
		} finally {
			protegerDepart(false); bouton.disabled = false; bouton.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Analyser le fichier';
		}
	}

	/** Contrôle paquet par paquet, avec le pourcentage réel renvoyé par le serveur. */
	async function controle(cleEtape) {
		let essais = 0;
		for (;;) {
			const r = await appel('controler');
			if (!r.ok) {
				if (r.statut === 409 && essais++ < 6) { await pause(1500); continue; }
				throw new Error((r.json.message || 'Le contrôle a été interrompu.') + ' Vous pourrez le reprendre depuis l\'historique.');
			}
			essais = 0; appliquer(cleEtape, r.json.traitees, r.json.total);
			if (r.json.termine) { return r.json; }
		}
	}

	/* ---------- résultat ---------- */
	function tuile(libelle, valeur, ton, icone) {
		return '<div class="imp-kpi imp-kpi--' + ton + '"><span class="imp-kpi__icone imp-centre"><i class="fa-solid ' + icone + '"></i></span><span class="imp-kpi__valeur">' + nb(valeur) + '</span><span class="imp-kpi__libelle">' + esc(libelle) + '</span></div>';
	}

	function verdict(ton, icone, titre, texte) {
		return '<div class="imp-verdict imp-verdict--' + ton + '"><span class="imp-verdict__icone imp-centre"><i class="fa-solid ' + icone + '"></i></span><div><b>' + titre + '</b><br><span>' + texte + '</span></div></div>';
	}

	function dessinerLignes() {
		const nbs = { toutes: etat.lignes.length, erreur: 0, nouvelle: 0, doublon: 0 };
		etat.lignes.forEach(function (l) { if (nbs[l.etat] !== undefined) { nbs[l.etat]++; } });
		document.querySelectorAll('[data-nb]').forEach(function (n) { n.textContent = nbs[n.getAttribute('data-nb')] || 0; });
		document.querySelectorAll('.imp-seg__btn').forEach(function (b) { b.classList.toggle('imp-actif', b.getAttribute('data-filtre') === etat.filtre); });
		const visibles = etat.lignes.filter(function (l) { return etat.filtre === 'toutes' || l.etat === etat.filtre; });
		if (!visibles.length) { el('imp-lignes').innerHTML = '<tr><td colspan="8" class="imp-vide"><i class="fa-regular fa-folder-open"></i>Aucune ligne dans cette catégorie.</td></tr>'; return; }
		el('imp-lignes').innerHTML = visibles.map(function (l) {
			const e = ETATS[l.etat] || ['', ''];
			const messages = l.messages.map(function (m) { const cls = m.niveau === 'erreur' ? 'erreur' : m.niveau === 'doublon' ? 'doublon' : 'avert'; return '<div class="imp-msg imp-msg--' + cls + '"><i class="fa-solid ' + (cls === 'erreur' ? 'fa-circle-exclamation' : 'fa-circle-info') + '"></i><span>' + esc(m.texte) + '</span></div>'; }).join('');
			return '<tr class="imp-ligne--' + esc(l.etat) + '"><td class="imp-num-ligne">' + l.ligne + '</td><td><span class="imp-pill ' + e[1] + '">' + e[0] + '</span></td><td style="white-space:nowrap">' + esc(l.numero) + '</td><td style="white-space:nowrap">' + esc(l.date) + '</td><td>' + esc(l.objet) + '</td><td>' + esc(l.parties) + '</td><td>' + esc(l.chambre) + '</td><td>' + (messages || '—') + '</td></tr>';
		}).join('');
	}

	async function afficherResultat() {
		const r = await appel('etat', 'GET');
		if (!r.ok) { throw new Error(r.json.message || 'Le résumé est indisponible.'); }
		const i = r.json.import; etat.lignes = r.json.lignes; etat.importable = r.json.importable; etat.valides = i.valides;
		el('imp-resume').innerHTML = tuile('Lignes lues', i.total, 'neutre', 'fa-table-list') + tuile('Lignes en erreur', i.erreurs, i.erreurs ? 'rouge' : 'neutre', 'fa-circle-xmark') + tuile('Affaires nouvelles', i.valides, 'vert', 'fa-circle-plus') + tuile('Doublons ignorés', i.doublons, 'orange', 'fa-clone');
		el('imp-verdict').innerHTML = i.erreurs > 0
			? verdict('erreur', 'fa-circle-xmark', nb(i.erreurs) + ' ligne(s) en erreur', "Corrigez le fichier (le rapport Excel liste tous les problèmes) puis relancez l'analyse : rien ne sera importé tant qu'il reste une erreur.")
			: i.valides === 0 ? verdict('info', 'fa-circle-info', 'Aucune nouvelle affaire', 'Tous les numéros existent déjà dans l\'application : il n\'y a rien à importer.')
			: verdict('ok', 'fa-circle-check', 'Le fichier est valide', '<b>' + nb(i.valides) + ' affaire(s)</b> du registre ' + esc(i.serie) + ' seront créées' + (i.doublons ? ' (' + nb(i.doublons) + ' doublon(s) ignoré(s))' : '') + '. Vérifiez le détail ci-dessous puis cliquez sur « Importer ».');
		etat.filtre = i.erreurs > 0 ? 'erreur' : 'toutes'; dessinerLignes();
		el('imp-troncature').hidden = !r.json.tronque;
		const rap = el('imp-rapport'); rap.href = url('rapport'); rap.hidden = (i.erreurs + i.doublons) === 0;
		const bi = el('imp-importer'); if (bi) { bi.disabled = !etat.importable; }
	}

	/* ---------- importation ---------- */
	async function importer() {
		if (!etat.importable) { return; }
		const choix = await alerte({ title: 'Importer dans le registre ' + etat.serie + ' ?', html: 'Les <b>' + nb(etat.valides) + '</b> affaires valides vont être créées. Cette opération est « tout ou rien » et peut être annulée tant que ces affaires n\'ont pas été travaillées.', icon: 'question', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, importer' });
		if (!choix.isConfirmed) { return; }
		const bouton = el('imp-importer'); bouton.disabled = true; bouton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Import en cours…';
		protegerDepart(true);
		try {
			ouvrirProgression('Import dans le registre ' + etat.serie, ETAPES_IMPORT);
			let essais = 0, derniere = null;
			for (;;) {
				const r = await appel('importer');
				if (!r.ok) { if (r.statut === 409 && essais++ < 6) { await pause(1500); continue; } throw new Error((r.json.message || "L'importation a été interrompue.") + ' Reprenez-la depuis l\'historique : les affaires déjà créées sont conservées.'); }
				essais = 0; derniere = r.json; appliquer('import', r.json.traitees, r.json.total);
				if (r.json.termine) { break; }
			}
			appliquer('fin', 0, 0); await clore('fini'); invalider(); el('imp-fichier').value = ''; majDepot(); etape(4);
			const suite = await alerte({ icon: 'success', title: 'Import terminé', html: '<b>' + nb(derniere.importees) + ' affaire(s)</b> créées dans le registre ' + esc(etat.serie) + '.' + (derniere.doublons ? '<br>' + nb(derniere.doublons) + ' doublon(s) ignoré(s).' : '') + '<br><small>Vérifiez la nature des parties sur chaque fiche : elle est devinée d\'après le nom.</small>', showCancelButton: true, confirmButtonText: 'Voir la liste des affaires', cancelButtonText: 'Fermer' });
			if (suite.isConfirmed) { window.location.href = URLS.liste; } else { window.location.reload(); }
		} catch (e) {
			try { await clore('erreur'); } catch (x) { /* écran déjà refermé */ }
			el('imp-resultat').hidden = false;
			alerte({ icon: 'error', title: 'Import interrompu', html: esc(e.message || "L'importation a échoué.") });
		} finally { protegerDepart(false); bouton.innerHTML = '<i class="fa-solid fa-file-import"></i> Importer'; bouton.disabled = !etat.importable; }
	}

	/* ---------- historique ---------- */
	window.imp_reprendre = async function (ref) {
		etat.ref = ref; const r = await appel('etat', 'GET');
		if (!r.ok) { alerte({ icon: 'error', title: 'Erreur', text: r.json.message || "L'import est introuvable." }); return; }
		const i = r.json.import; etat.serie = i.serie; el('imp-serie').value = i.serie; majSerie();
		try {
			ouvrirProgression('Reprise du traitement', ETAPES_ANALYSE); suivi.courante = i.statut === 'televerse' ? 2 : 3; suivi.fait = 0; suivi.total = 0;
			if (i.statut === 'televerse') { const a = await appel('analyser'); if (!a.ok) { throw new Error(a.json.message || 'Le fichier n\'a pas pu être lu.'); } }
			if (i.statut !== 'pret') { await controle('controle'); }
			appliquer('resume', 0, 0); await afficherResultat(); await clore('fini'); el('imp-resultat').hidden = false; etape(3); el('imp-resultat').scrollIntoView({ behavior: 'smooth' });
		} catch (e) { try { await clore('erreur'); } catch (x) { /* déjà refermé */ } alerte({ icon: 'error', title: 'Reprise impossible', html: esc(e.message) }); }
	};

	window.imp_annuler = async function (ref, termine) {
		const conf = await alerte({ title: APP_NOM, text: termine ? "Annuler cet import ? Les affaires reprises seront retirées du registre (possible seulement si personne n'a travaillé dessus)." : 'Abandonner cet import ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Retour', confirmButtonText: termine ? "Oui, annuler l'import" : 'Oui, abandonner' });
		if (!conf.isConfirmed) { return; }
		const r = await appel('annuler', 'POST', ref);
		if (!r.ok) { alerte({ icon: 'error', title: 'Impossible', text: r.json.message || "L'annulation a échoué." }); return; }
		await alerte({ icon: 'success', title: APP_NOM, text: r.json.message }); location.reload();
	};

	document.addEventListener('DOMContentLoaded', function () {
		if (!el('imp-analyser')) { return; }
		el('imp-serie').addEventListener('change', function () { majSerie(); invalider(); });
		majSerie();
		el('imp-analyser').addEventListener('click', analyser);
		const imp = el('imp-importer'); if (imp) { imp.addEventListener('click', importer); }
		el('imp-fichier').addEventListener('change', function () { majDepot(); invalider(); });
		const depot = el('imp-depot');
		['dragenter', 'dragover'].forEach(function (n) { depot.addEventListener(n, function (e) { e.preventDefault(); depot.classList.add('imp-depot--survol'); }); });
		['dragleave', 'drop'].forEach(function (n) { depot.addEventListener(n, function (e) { e.preventDefault(); depot.classList.remove('imp-depot--survol'); }); });
		depot.addEventListener('drop', function (e) { const f = e.dataTransfer && e.dataTransfer.files; if (f && f.length) { el('imp-fichier').files = f; el('imp-fichier').dispatchEvent(new Event('change', { bubbles: true })); } });
		document.querySelectorAll('.imp-seg__btn').forEach(function (b) { b.addEventListener('click', function () { etat.filtre = b.getAttribute('data-filtre'); dessinerLignes(); }); });
		majDepot(); etape(1);
	});
})();
</script>
