@extends('layout.master')
@section('title', 'Rôle du jour')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
  <style>
    .rj-role { background: #fff; border: 1px solid #e3ebe6; border-radius: 18px; box-shadow: 0 6px 20px rgba(12, 60, 40, .07); margin-bottom: 16px; overflow: hidden; }
    .rj-tete { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; padding: 14px 18px; background: linear-gradient(90deg, #00794c, #19a06b); color: #fff; }
    .rj-heure { font-size: 30px; font-weight: 800; line-height: 1; background: rgba(255, 255, 255, .18); border-radius: 12px; padding: 8px 14px; }
    .rj-titre { flex: 1; min-width: 220px; } .rj-titre b { font-size: 17px; display: block; } .rj-titre span { opacity: .9; font-size: 13px; }
    .rj-etat { padding: 5px 14px; border-radius: 99px; font-weight: 800; font-size: 12.5px; background: #fff; color: #00794c; text-transform: uppercase; letter-spacing: .04em; }
    .rj-etat--en_cours { background: #f08a24; color: #fff; animation: rj-pulse 1.6s infinite; } .rj-etat--terminee { background: #e7f3ec; color: #1f2933; }
    @keyframes rj-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(240, 138, 36, .6); } 50% { box-shadow: 0 0 0 8px rgba(240, 138, 36, 0); } }
    .rj-compo { padding: 8px 18px; font-size: 13px; color: #1f2933; border-bottom: 1px solid #e3e9e5; display: flex; flex-wrap: wrap; gap: 4px 22px; }
    .rj-progres { padding: 8px 18px 0; } .rj-rub { padding: 4px 18px 10px; }
    .rj-rub h4 { font-size: 12.5px; letter-spacing: .06em; text-transform: uppercase; color: #f08a24; margin: 12px 0 4px; font-weight: 800; }
    .rj-ligne { display: grid; grid-template-columns: 42px 120px 1fr 190px; gap: 10px; align-items: center; padding: 8px 4px; border-top: 1px solid #f0f3f1; }
    .rj-ligne:nth-child(even) { background: #fbfdfc; } .rj-ligne.is-fait { opacity: .78; }
    .rj-ordre { font-weight: 800; color: #0b5d3b; text-align: center; } .rj-num { font-weight: 800; }
    .rj-parties b { display: block; } .rj-parties span { color: #2b3440; font-size: 13px; }
    .rj-issue { text-align: right; } .rj-issue i { font-style: normal; display: inline-block; padding: 3px 11px; border-radius: 99px; font-size: 12px; font-weight: 800; background: #e7f3ec; color: #0b5d3b; }
    .rj-issue i.rj-renvoi { background: #fff1e3; color: #b8620b; } .rj-issue i.rj-attente { background: #e9edf0; color: #1f2933; }
    .rj-tete { cursor: pointer; user-select: none; }
    .rj-mini { display: inline-block; vertical-align: middle; width: 90px; height: 7px; margin-left: 10px; border-radius: 99px; background: #fff; overflow: hidden; }
    .rj-mini i { display: block; height: 100%; border-radius: 99px; background: #fc6b03; }
    .rj-chevron { font-size: 18px; transition: transform .25s; } .rj-role.is-ferme .rj-chevron { transform: rotate(-90deg); } .rj-role.is-ferme .rj-corps { display: none; }
    .rj-role.is-ferme .rj-tete { border-radius: 18px; }
    .rj-bandeau { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 4px 0 14px; }
    .rj-pastille { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 12px; border: 1px solid #d5e0d9; background: #fff; color: #1f2933; font-weight: 700; font-size: 13.5px; cursor: pointer; }
    .rj-pastille:hover { border-color: #00794c; background: #f4faf6; } .rj-pastille i.pt { width: 10px; height: 10px; border-radius: 50%; background: #5b6577; }
    .rj-pastille i.pt.en_cours { background: #f08a24; box-shadow: 0 0 0 4px rgba(240, 138, 36, .25); } .rj-pastille i.pt.a_tenir { background: #00794c; } .rj-pastille small { font-weight: 600; color: #2b3440; }
    .rj-outils { margin-left: auto; display: flex; gap: 6px; }
    .rj-nav { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .rj-nav .jour { font-weight: 800; font-size: 17px; text-transform: capitalize; min-width: 210px; text-align: center; }
    body.rj-plein .page-header, body.rj-plein .sidebar-wrapper, body.rj-plein footer, body.rj-plein .st-filtres-masque { display: none !important; }
    body.rj-plein .page-body-wrapper { margin: 0 !important; padding: 0 !important; } body.rj-plein .page-wrapper .page-body-wrapper .page-body { margin: 0 !important; padding: 14px !important; }
    body.rj-plein .rj-ligne { font-size: 20px; padding: 12px 4px; } body.rj-plein .rj-heure { font-size: 40px; }
    @media (max-width: 720px) { .rj-ligne { grid-template-columns: 34px 1fr; } .rj-issue { grid-column: 2; text-align: left; } .rj-num { grid-column: 2; } }
  </style>
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page tp-anime">
      <x-hero tag="Audiences" title="Rôle du jour" subtitle="Les audiences de la journée à l'écran : salle, heure, composition, affaires par rubrique et suite donnée à chacune à mesure que le greffier la note. La page se met à jour toute seule." icon="fa-solid fa-gavel">
        <x-slot:extra><span id="rj-resume"><i class="fa-solid fa-spinner fa-spin"></i> Chargement…</span></x-slot:extra>
        <x-slot:actions>
          <button class="el-btn el-btn--accent" type="button" id="rj-plein"><i class="fa-solid fa-expand"></i> Plein écran</button>
        </x-slot:actions>
      </x-hero>

      <div class="el-card st-filtres-masque">
        <div class="st-filtres" style="grid-template-columns: 1fr minmax(0, 320px); align-items: center">
          <div class="rj-nav">
            <button class="el-btn" type="button" id="rj-prec" title="Jour précédent"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="jour" id="rj-jour">…</div>
            <button class="el-btn" type="button" id="rj-suiv" title="Jour suivant"><i class="fa-solid fa-chevron-right"></i></button>
            <button class="el-btn" type="button" id="rj-auj"><i class="fa-solid fa-calendar-day"></i> Aujourd'hui</button>
            <input class="form-control" type="date" id="rj-date" style="max-width:170px">
          </div>
          <div class="st-champ"><label for="rj-chambre">Chambre</label>
            <select class="form-select" id="rj-chambre"><option value="">Toutes les chambres</option>@foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select></div>
        </div>
      </div>

      <div id="rj-liste"><div class="st-squelette"></div></div>
      <p class="st-note" id="rj-maj"></p>
    </div>
  </div>
@endsection

@section('script')
<script>
(function () {
	const URL_D = "{{ route('audiences.jour.donnees') }}";
	const esc = (x) => String(x == null ? '' : x).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const ETATS = { a_tenir: 'À tenir', en_cours: 'En cours', terminee: 'Terminée' };
	let jour = null, D = null, minuteur = null;
	const $id = (i) => document.getElementById(i);

	function charger() {
		const p = new URLSearchParams(); if (jour) { p.set('jour', jour); } if ($id('rj-chambre').value) { p.set('chambre', $id('rj-chambre').value); }
		fetch(URL_D + '?' + p, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { if (!r.ok) { throw 0; } return r.json(); }).then(function (d) { D = d; jour = d.jour; afficher(); })
			.catch(function () { $id('rj-liste').innerHTML = '<div class="st-vide">Impossible de charger le rôle. Nouvelle tentative dans un instant…</div>'; });
	}
	var ouverts = null;        // volets dépliés (identifiants de rôle) ; calculé au premier affichage du jour, puis conservé à chaque actualisation
	function ouvertsParDefaut(roles) {
		const ids = roles.filter(function (r) { return r.etat === 'en_cours'; }).map(function (r) { return r.id; });
		if (!ids.length) { const prochain = roles.find(function (r) { return r.etat === 'a_tenir'; }); if (prochain) { ids.push(prochain.id); } }
		if (roles.length === 1) { return [roles[0].id]; }
		return ids;
	}
	function afficher() {
		$id('rj-jour').textContent = D.libelle; $id('rj-date').value = D.jour;
		$id('rj-resume').innerHTML = '<i class="fa-solid fa-calendar-day"></i> ' + D.totaux.roles + ' audience(s) · ' + D.totaux.affaires + ' affaire(s) · ' + D.totaux.traitees + ' traitée(s)';
		$id('rj-maj').textContent = 'Mis à jour à ' + D.maj + (D.aujourdhui ? ' · actualisation automatique toutes les 30 secondes' : '');
		if (!D.roles.length) { $id('rj-liste').innerHTML = '<div class="st-carte st-vide"><i class="fa-solid fa-calendar-xmark" style="font-size:34px;color:#f08a24"></i><p>Aucun rôle publié pour ce jour' + ($id('rj-chambre').value ? ' dans cette chambre' : '') + '.</p></div>'; return; }
		if (ouverts === null || ouverts.jour !== D.jour) { ouverts = { jour: D.jour, ids: new Set(ouvertsParDefaut(D.roles)) }; }
		const bandeau = D.roles.length > 1 ? '<div class="rj-bandeau">' + D.roles.map(function (r) {
			return '<button type="button" class="rj-pastille" data-aller="' + r.id + '"><i class="pt ' + r.etat + '"></i>' + esc(r.heure || '—') + ' · ' + esc(r.chambre) + ' <small>' + r.traitees + '/' + r.total + '</small></button>';
		}).join('') + '<span class="rj-outils"><button type="button" class="el-btn" id="rj-deplier"><i class="fa-solid fa-angles-down"></i> Tout déplier</button><button type="button" class="el-btn" id="rj-replier"><i class="fa-solid fa-angles-up"></i> Tout replier</button></span></div>' : '';
		$id('rj-liste').innerHTML = bandeau + D.roles.map(function (r) {
			const pct = r.total ? Math.round(r.traitees / r.total * 100) : 0;
			const compo = [r.president ? 'Président : ' + r.president : '', r.assesseurs ? 'Assesseurs : ' + r.assesseurs : '', r.procureur ? 'Ministère public : ' + r.procureur : '', r.greffier ? 'Greffier : ' + r.greffier : ''].filter(Boolean);
			return '<div class="rj-role' + (ouverts.ids.has(r.id) ? '' : ' is-ferme') + '" id="rj-r' + r.id + '" data-id="' + r.id + '"><div class="rj-tete" data-basculer="' + r.id + '"><div class="rj-heure">' + esc(r.heure || '—') + '</div><div class="rj-titre"><b>' + esc(r.chambre) + (r.intitule ? ' · ' + esc(r.intitule) : '') + '</b><span>' + esc(r.type) + (r.salle ? ' · Salle ' + esc(r.salle) : '') + ' · ' + r.traitees + '/' + r.total + ' traitée(s)<em class="rj-mini" title="' + pct + ' %"><i style="width:' + pct + '%"></i></em></span></div>' +
				'<span class="rj-etat rj-etat--' + r.etat + '">' + ETATS[r.etat] + '</span>' + (r.url ? '<a class="el-btn" href="' + r.url + '" style="background:#fff" data-ouvrir><i class="fa-regular fa-folder-open"></i> Ouvrir</a>' : '') + '<i class="fa-solid fa-chevron-down rj-chevron"></i></div>' +
				'<div class="rj-corps">' + (compo.length ? '<div class="rj-compo">' + compo.map(function (c) { return '<span>' + esc(c) + '</span>'; }).join('') + '</div>' : '') +
				r.rubriques.map(function (rub) { return '<div class="rj-rub"><h4>' + esc(rub.libelle) + '</h4>' + rub.lignes.map(function (l) {
					const issue = l.issue_libelle ? '<i class="' + (l.issue === 'renvoi' ? 'rj-renvoi' : '') + '">' + esc(l.issue_libelle) + (l.renvoi_au ? ' au ' + esc(l.renvoi_au) : '') + '</i>' : '<i class="rj-attente">À appeler</i>';
					return '<div class="rj-ligne' + (l.issue ? ' is-fait' : '') + '"><div class="rj-ordre">' + l.ordre + '</div><div class="rj-num">' + esc(l.numero) + '</div><div class="rj-parties"><b>' + esc(l.parties) + '</b><span>' + esc(l.objet) + '</span></div><div class="rj-issue">' + issue + '</div></div>';
				}).join('') + '</div>'; }).join('') + '</div></div>';
		}).join('');
	}
	document.addEventListener('click', function (e) {
		if (e.target.closest('[data-ouvrir]')) { return; }
		const t = e.target.closest('[data-basculer]');
		if (t) { const id = +t.dataset.basculer, carte = $id('rj-r' + id); if (ouverts.ids.has(id)) { ouverts.ids.delete(id); } else { ouverts.ids.add(id); } carte.classList.toggle('is-ferme', !ouverts.ids.has(id)); return; }
		const p = e.target.closest('[data-aller]');
		if (p) { const id = +p.dataset.aller; ouverts.ids.add(id); const carte = $id('rj-r' + id); carte.classList.remove('is-ferme'); carte.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
		if (e.target.closest('#rj-deplier')) { D.roles.forEach(function (r) { ouverts.ids.add(r.id); }); document.querySelectorAll('.rj-role').forEach(function (c) { c.classList.remove('is-ferme'); }); }
		if (e.target.closest('#rj-replier')) { ouverts.ids.clear(); document.querySelectorAll('.rj-role').forEach(function (c) { c.classList.add('is-ferme'); }); }
	});
	function planifier() { clearInterval(minuteur); minuteur = setInterval(function () { if (!document.hidden && D && D.aujourdhui) { charger(); } }, 30000); }
	document.addEventListener('DOMContentLoaded', function () {
		$id('rj-prec').onclick = function () { jour = D.precedent; charger(); }; $id('rj-suiv').onclick = function () { jour = D.suivant; charger(); }; $id('rj-auj').onclick = function () { jour = null; charger(); };
		$id('rj-date').onchange = function () { if (this.value) { jour = this.value; charger(); } };
		$('#rj-chambre').on('change', charger);
		$id('rj-plein').onclick = function () { document.body.classList.toggle('rj-plein'); if (document.body.classList.contains('rj-plein') && document.documentElement.requestFullscreen) { document.documentElement.requestFullscreen().catch(function () {}); } else if (document.fullscreenElement) { document.exitFullscreen(); } };
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { document.body.classList.remove('rj-plein'); } });
		charger(); planifier();
	});
})();
</script>
@endsection
