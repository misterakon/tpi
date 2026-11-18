@extends('layout.master')
@section('title', 'Composition des chambres')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <style>
    /* ---------- Sections : une carte repliable par section ---------- */
    .comp-section { overflow: hidden; }
    .comp-section__tete { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 16px 22px; cursor: pointer; user-select: none; transition: background .15s; }
    .comp-section__tete:hover { background: #F4F9F6; }
    .comp-section__tete:focus-visible { outline: 3px solid #F97316; outline-offset: -3px; }
    .comp-section__id { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .comp-section__icone { display: flex !important; width: 42px; height: 42px; flex: none; border-radius: 13px; align-items: center; justify-content: center; color: #fff; font-size: 17px; background: linear-gradient(135deg, #065F46, #10B981); box-shadow: 0 8px 18px rgba(16, 185, 129, .25); }
    .comp-section__titre { margin: 0; font: 700 17px/1.25 var(--police-app); color: #0B3D2A; }
    .comp-section__sous { margin-top: 3px; display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; font: 500 12.5px var(--police-app); color: var(--el-mute); }
    .comp-chevron { display: flex !important; flex: none; width: 34px; height: 34px; align-items: center; justify-content: center; border-radius: 50%; background: #EEF5F1; color: #00794C; transition: transform .25s; }
    .comp-section.is-plie .comp-chevron { transform: rotate(-90deg); }
    .comp-section__corps { display: grid; grid-template-rows: 1fr; transition: grid-template-rows .28s ease; border-top: 1px solid var(--el-line); background: #F6F9F7; }
    .comp-section.is-plie .comp-section__corps { grid-template-rows: 0fr; border-top-color: transparent; }
    .comp-section__interieur { min-height: 0; overflow: hidden; }
    /* Deux chambres par ligne (une seule sur petit écran) */
    .comp-grille { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; padding: 20px 22px 24px; align-items: start; }
    @media (max-width: 991px) { .comp-grille { grid-template-columns: 1fr; } }

    /* ---------- Chambre : carte verticale (bandeau, président, effectifs, horaires en pastilles) ---------- */
    .comp-carte { position: relative; display: flex; flex-direction: column; gap: 14px; padding: 0 20px 14px 26px; background: #fff; border: 1px solid #DCE8E1; border-radius: 16px; box-shadow: 0 8px 22px rgba(15, 42, 31, .12), 0 2px 5px rgba(15, 42, 31, .08); overflow: hidden; transition: transform .18s, box-shadow .18s; }
    .comp-carte::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 5px; background: linear-gradient(180deg, #00794C, #10B981); }
    .comp-carte--manque::before { background: linear-gradient(180deg, #D9660A, #F59E0B); }
    .comp-carte:hover { transform: translateY(-3px); box-shadow: 0 18px 38px rgba(15, 42, 31, .18), 0 4px 8px rgba(15, 42, 31, .10); }
    .comp-carte.is-ciblee { box-shadow: 0 0 0 3px rgba(247, 127, 0, .45), 0 8px 22px rgba(15, 42, 31, .12); }

    /* Bandeau d'en-tête teinté */
    .comp-carte__haut { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin: 0 -20px 0 -26px; padding: 14px 20px 12px 26px; background: linear-gradient(90deg, #E7F5EE, #F7FBF9); border-bottom: 1px solid #DCE8E1; }
    .comp-carte--manque .comp-carte__haut { background: linear-gradient(90deg, #FFF1DE, #FFFAF3); border-bottom-color: #F6DDB8; }
    .comp-carte__nom { margin: 0; font: 700 16px/1.25 var(--police-app); color: #0B3D2A; }
    .comp-carte__sous { margin-top: 3px; font: 400 12.5px/1.35 var(--police-app); color: #4F6B5E; }

    .comp-carte__corps { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 18px; }
    .comp-tete { display: flex; align-items: center; gap: 12px; min-height: 44px; min-width: 0; flex: 1 1 220px; }
    .comp-tete__avatars { display: flex !important; flex: none; }
    .comp-tete__avatars button { padding: 0; border: 0; background: none; cursor: pointer; margin-left: -10px; border-radius: 50%; }
    .comp-tete__avatars button:first-child { margin-left: 0; }
    .comp-tete__avatars .el-avatar { border: 2px solid #fff; transition: transform .15s; }
    .comp-tete__avatars button:hover .el-avatar { transform: translateY(-2px); }
    .comp-tete__role { font: 600 11px/1 var(--police-app); letter-spacing: .06em; text-transform: uppercase; color: var(--el-mute); }
    .comp-tete__noms { font: 600 14px/1.35 var(--police-app); color: var(--el-ink); }
    .comp-tete__noms small { font: 500 12px var(--police-app); color: var(--el-mute); }
    .comp-vide-tete { font: 500 13px var(--police-app); color: #9A3412; }

    /* Un nom qui ouvre la fiche de la personne */
    .comp-lien { padding: 0; border: 0; background: none; font: inherit; color: inherit; text-align: left; cursor: pointer; text-decoration: underline dotted transparent; text-underline-offset: 3px; transition: color .12s, text-decoration-color .12s; }
    .comp-lien:hover, .comp-lien:focus-visible { color: #00794C; text-decoration-color: currentColor; }

    /* Effectifs : trois pastilles colorées */
    .comp-eff { display: flex; flex-wrap: wrap; gap: 6px; }
    .comp-eff span { display: inline-flex !important; align-items: baseline; gap: 5px; padding: 5px 11px; border-radius: 999px; font: 500 12px var(--police-app); white-space: nowrap; }
    .comp-eff b { font: 700 15px/1 var(--police-app); }
    .comp-eff .e-tit { background: #E4F5EC; color: #0B5A3A; }
    .comp-eff .e-sup { background: #FFEEDB; color: #A8470A; }
    .comp-eff .e-gre { background: #E2F1F6; color: #155E75; }

    .comp-manques { display: flex; flex-wrap: wrap; gap: 6px; }

    /* Horaires d'audience : pastilles qui passent à la ligne */
    .comp-hz-liste { display: flex; flex-wrap: wrap; gap: 6px; padding-top: 12px; border-top: 1px solid #E6EEE9; }
    .comp-hz { display: inline-flex !important; align-items: center; gap: 7px; max-width: 100%; padding: 5px 12px; border: 1px solid #D3E8DD; border-radius: 999px; background: #F1F8F4; font: 500 12px/1.3 var(--police-app); color: #44594F; }
    .comp-hz i { flex: none; font-size: 12px; color: #00794C; }
    .comp-hz b { flex: none; font-weight: 700; color: #0B5A3A; }
    .comp-hz span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .comp-hz--plus { background: #fff; border-style: dashed; color: var(--el-mute); }
    .comp-hz--vide { background: #fff; border-style: dashed; color: var(--el-mute); }

    .comp-carte__pied { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .comp-avenir { font: 500 12px var(--police-app); color: var(--el-mute); white-space: nowrap; }
    .comp-composer { display: inline-flex; align-items: center; gap: 7px; margin-left: auto; padding: 6px 14px; border: 1.5px solid #F77F00; border-radius: 10px; background: #fff; color: #C2570C; font: 600 12.5px var(--police-app); cursor: pointer; transition: background .15s, color .15s; }
    .comp-composer:hover, .comp-composer:focus-visible { background: #F77F00; color: #fff; }

    /* ---------- Filtres ---------- */
    .comp-filtres { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 22px; }
    .comp-filtres__pills { padding: 18px 20px 20px; }
    .comp-plier { display: inline-flex !important; gap: 8px; }
    .comp-section .el-avatar, .comp-pers .el-avatar, .fiche-tete .el-avatar { display: inline-flex !important; align-items: center; justify-content: center; }
    .comp-detail .el-chip, .comp-carte .el-chip, .comp-section__sous .el-chip, .fiche-chips .el-chip { display: inline-flex !important; }

    /* ---------- Grande fenêtre « Composer » (centrée, comme les autres) ---------- */
    #modal-composition .modal-dialog { width: min(1180px, 96vw); max-width: none; }
    #modal-composition .modal-content { overflow: hidden; border: 0; border-radius: 18px; }
    .comp-modal__tete { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 18px 24px; color: #fff; background: linear-gradient(135deg, #00613D, #00A36A); }
    .comp-modal__sec { font: 600 11.5px/1 var(--police-app); letter-spacing: .07em; text-transform: uppercase; opacity: .85; }
    .comp-modal__titre { margin: 6px 0 0; font: 700 21px/1.2 var(--police-app); color: #fff; }
    .comp-modal__actions { display: flex; align-items: center; gap: 10px; flex: none; }
    .comp-modal__corps { min-height: 320px; padding: 22px 24px 26px; background: #F4F7F5; }
    .comp-charge { padding: 60px 0; text-align: center; color: var(--el-mute); font: 500 14px var(--police-app); }
    /* Les fenêtres de saisie s'ouvrent par-dessus la grande fenêtre */
    #modal-affectation, #modal-remplacement, #modal-creneau, #modal-agent-fiche { z-index: 1070; }
    .modal-backdrop ~ .modal-backdrop { z-index: 1065; }
    #modal-composition .modal-content { transition: filter .2s; }
    #modal-composition.comp-attenue .modal-content { filter: brightness(.62); pointer-events: none; }
    .swal2-container { z-index: 1090 !important; }

    /* Contenu : colonne de synthèse à gauche, composition à droite */
    .comp-detail { display: grid; grid-template-columns: 270px minmax(0, 1fr); gap: 22px; align-items: start; }
    .comp-detail [hidden] { display: none !important; }
    .comp-lateral { display: flex; flex-direction: column; gap: 14px; }
    .comp-stats { display: grid; gap: 8px; }
    .comp-stat { display: flex; align-items: center; gap: 14px; padding: 12px 16px; background: #fff; border: 1px solid var(--el-line); border-radius: 14px; }
    .comp-stat b { min-width: 36px; font: 700 26px/1 var(--police-app); color: #0B3D2A; }
    .comp-stat span { font: 500 13px var(--police-app); color: var(--el-mute); }
    .comp-lateral__rub { font: 700 11.5px/1 var(--police-app); letter-spacing: .07em; text-transform: uppercase; color: #00794C; }
    .comp-formations { display: flex; flex-direction: column; gap: 6px; }
    .comp-formations .el-pill { justify-content: flex-start; width: 100%; text-align: left; }
    .comp-aide { margin: 0; font: 400 12.5px/1.55 var(--police-app); color: var(--el-mute); }
    @media (max-width: 900px) {
      .comp-detail { grid-template-columns: 1fr; }
      .comp-stats { grid-template-columns: repeat(3, 1fr); }
    }
    .comp-attrib { padding: 12px 16px; background: #fff; border: 1px solid var(--el-line); border-radius: 14px; }
    .comp-attrib summary { cursor: pointer; font: 600 13.5px var(--police-app); color: #0B3D2A; list-style-position: inside; }
    .comp-attrib summary i { color: #00794C; margin-right: 6px; }
    .comp-attrib[open] summary { margin-bottom: 10px; }
    .comp-attrib .el-riche { max-height: 240px; overflow-y: auto; padding-right: 4px; font-size: 13px; line-height: 1.55; }
    .comp-formation { overflow: hidden; }
    .comp-formation__tete { padding: 16px 20px 0; }
    .comp-formation__tete h3 { margin: 0 0 8px; display: flex; align-items: center; gap: 8px; font: 700 16px var(--police-app); color: #0B3D2A; }
    .comp-formation .comp-attrib { margin: 0 20px; }
    .comp-onglets { display: flex; width: fit-content; max-width: calc(100% - 40px); gap: 4px; margin: 16px 20px 0; padding: 5px; background: #EDF1EF; border-radius: 14px; }
    .comp-onglet { display: inline-flex !important; align-items: center; gap: 9px; padding: 9px 18px; border: 0; border-radius: 10px; background: transparent; font: 600 13.5px var(--police-app); color: #5B6B63; cursor: pointer; transition: background .18s, color .18s, box-shadow .18s; }
    .comp-onglet i { color: #8A9A92; transition: color .18s; }
    .comp-onglet:hover { color: #0B3D2A; }
    .comp-onglet.is-actif { background: #fff; color: #0B3D2A; box-shadow: 0 2px 8px rgba(15, 42, 31, .12); }
    .comp-onglet.is-actif i { color: #00794C; }
    .comp-onglet__nb { min-width: 22px; padding: 2px 7px; border-radius: 999px; background: #DCE5E0; text-align: center; font: 700 11.5px var(--police-app); color: #3B4A42; transition: background .18s, color .18s; }
    .comp-onglet.is-actif .comp-onglet__nb { background: #00794C; color: #fff; }
    .comp-pane { display: flex; flex-direction: column; gap: 16px; padding: 16px 20px 20px; }
    .comp-pane__barre { display: flex; justify-content: flex-end; }
    .comp-groupe { display: flex; flex-direction: column; gap: 8px; }
    .comp-groupe__titre { display: flex; align-items: center; gap: 8px; font: 700 11.5px/1 var(--police-app); letter-spacing: .07em; text-transform: uppercase; color: #00794C; }
    .comp-groupe__titre span { padding: 3px 8px; border-radius: 999px; background: #EEF5F1; letter-spacing: 0; }
    .comp-pers { display: grid; grid-template-columns: 38px minmax(0, 1fr) auto auto auto; align-items: center; gap: 12px; padding: 10px 12px; background: #fff; border: 1px solid var(--el-line); border-radius: 12px; transition: box-shadow .15s; }
    .comp-pers:hover { box-shadow: 0 6px 16px rgba(15, 42, 91, .08); }
    .comp-pers__nom { font: 600 14px/1.3 var(--police-app); color: var(--el-ink); }
    .comp-pers__sub { font: 400 12.5px/1.35 var(--police-app); color: var(--el-mute); }
    .comp-pers__statut { display: flex; flex-wrap: wrap; gap: 5px; justify-content: flex-end; }
    .comp-pers__periode { font: 500 12.5px var(--police-app); color: var(--el-mute); white-space: nowrap; }
    .comp-pers__actions { display: flex; gap: 6px; }
    .comp-aud { display: flex; align-items: center; gap: 14px; padding: 12px 14px; background: #fff; border: 1px solid var(--el-line); border-radius: 12px; }
    .comp-aud.is-termine { opacity: .6; }
    .comp-aud__icone { display: flex !important; width: 38px; height: 38px; flex: none; align-items: center; justify-content: center; border-radius: 12px; background: #EAF5EF; color: #00794C; }
    .comp-aud__corps { flex: 1; min-width: 0; }
    .comp-aud__type { font: 600 11.5px/1.2 var(--police-app); letter-spacing: .05em; text-transform: uppercase; color: var(--el-mute); }
    .comp-aud__quand { margin-top: 2px; font: 600 14px/1.35 var(--police-app); color: var(--el-ink); }
    .comp-aud__sub { margin-top: 2px; font: 400 12.5px/1.35 var(--police-app); color: var(--el-mute); }
    .comp-vide { padding: 26px 0; text-align: center; font: 500 14px var(--police-app); color: var(--el-mute); }
    @media (max-width: 760px) {
      .comp-pers { grid-template-columns: 38px minmax(0, 1fr); }
      .comp-pers__statut, .comp-pers__periode, .comp-pers__actions { grid-column: 2; justify-content: flex-start; }
    }

    /* ---------- Fiche d'une personne ---------- */
    .fiche-tete { display: flex; align-items: center; gap: 16px; margin-bottom: 14px; padding: 16px 18px; background: linear-gradient(135deg, #EEF7F2, #fff); border: 1px solid var(--el-line); border-radius: 16px; }
    .fiche-tete .el-avatar { flex: none; }
    .fiche-nom { font: 700 18px/1.25 var(--police-app); color: var(--el-ink); }
    .fiche-sub { margin-top: 3px; font: 500 13.5px var(--police-app); color: var(--el-mute); }
    .fiche-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
    .fiche-contacts { display: flex; flex-wrap: wrap; gap: 8px 10px; margin-bottom: 4px; }
    .fiche-ct { display: inline-flex !important; align-items: center; gap: 8px; padding: 7px 12px; background: #F4F8F6; border-radius: 999px; font: 500 13px var(--police-app); color: var(--el-ink); word-break: break-word; }
    .fiche-ct i { color: #00794C; font-size: 12px; }
    .fiche-rub { display: flex; align-items: center; gap: 10px; margin: 20px 0 10px; font: 700 11.5px/1 var(--police-app); letter-spacing: .08em; text-transform: uppercase; color: #00794C; }
    .fiche-rub::after { content: ""; flex: 1; height: 1px; background: var(--el-line); }
    .fiche-chambre { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 14px; margin-bottom: 8px; border: 1px solid var(--el-line); border-radius: 12px; }
    .fiche-chambre__nom { font: 700 14px/1.3 var(--police-app); color: var(--el-ink); }
    .fiche-roles { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
    .fiche-role { padding: 4px 10px; border-radius: 999px; background: #EAF5EF; color: #0B5A3A; font: 600 12px/1.3 var(--police-app); }
    .fiche-role small { font-weight: 500; opacity: .75; }
    .fiche-svc { display: flex; flex-wrap: wrap; gap: 8px; }
    .fiche-svc span { display: inline-flex !important; flex-direction: column; padding: 8px 12px; background: #FFF4E8; border-radius: 12px; font: 600 13px/1.3 var(--police-app); color: #8A3A00; }
    .fiche-svc small { font: 400 12px var(--police-app); color: #A8530F; }
    .fiche-rien { color: var(--el-mute); font: 500 13.5px var(--police-app); }
  </style>
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Affectations" title="Composition des chambres" subtitle="Qui siège dans chaque chambre aujourd'hui, et quand elle tient audience, d'après l'ordonnance en vigueur. Repérez ce qui manque, puis cliquez sur « Composer » pour modifier une chambre." icon="fa-solid fa-users-between-lines">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-days"></i> {{ $annee?->libelle ?? 'Aucune année' }}</span>
          <span><i class="fa-solid fa-scale-balanced"></i> {{ $resume['chambres'] }} chambre(s)</span>
          <x-ordonnance-en-vigueur :annee="$annee?->id" />
        </x-slot:extra>
      </x-hero>

      <div class="el-kpis">
        <div class="el-kpi">
          <span class="el-kpi__icon el-tone-blue"><i class="fa-solid fa-scale-balanced"></i></span>
          <span><div class="el-kpi__value">{{ $resume['chambres'] }}</div><div class="el-kpi__label">Chambres</div></span>
        </div>
        <div class="el-kpi">
          <span class="el-kpi__icon el-tone-green"><i class="fa-solid fa-circle-check"></i></span>
          <span><div class="el-kpi__value">{{ $resume['completes'] }}</div><div class="el-kpi__label">Composition complète</div></span>
        </div>
        <div class="el-kpi">
          <span class="el-kpi__icon el-tone-amber"><i class="fa-solid fa-triangle-exclamation"></i></span>
          <span><div class="el-kpi__value">{{ $resume['a_completer'] }}</div><div class="el-kpi__label">À compléter</div></span>
        </div>
        <div class="el-kpi">
          <span class="el-kpi__icon el-tone-purple"><i class="fa-solid fa-users"></i></span>
          <span><div class="el-kpi__value">{{ $resume['affectations'] }}</div><div class="el-kpi__label">Affectations en cours</div></span>
        </div>
      </div>

      {{-- Filtres : année, recherche, interrupteur « à compléter », sections --}}
      <div class="el-card">
        <div class="el-toolbar">
          <div class="comp-filtres">
            <form method="GET" action="{{ route('organisation.composition') }}" style="display:flex; align-items:center; gap:10px; min-width:260px">
              <label class="el-muted" for="filtre-annee" style="margin:0; white-space:nowrap">Année judiciaire</label>
              <select class="form-select" id="filtre-annee" name="annee" onchange="this.form.submit()" aria-label="Année judiciaire">
                @foreach ($annees as $a)
                  <option value="{{ $a->id }}" @selected($annee?->id === $a->id)>{{ $a->libelle }}@if ($a->courante) (en cours)@endif</option>
                @endforeach
              </select>
            </form>
            <label class="el-switch el-switch--lg" for="seulement-manques">
              <input type="checkbox" id="seulement-manques">
              <span class="el-switch__piste"><i></i></span>
              <span class="el-switch__texte">À compléter seulement</span>
            </label>
          </div>
          <label class="el-search" style="max-width:320px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Chambre, personne, salle…" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>
        <div class="comp-filtres__pills" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-section="">Toutes les sections</button>
            @foreach ($sections->filter(fn ($s) => $fiches->contains(fn ($f) => $f['chambre']->section_id === $s->id)) as $s)
              <button type="button" class="el-pill" data-section="s{{ $s->id }}">{{ $s->libelle }}</button>
            @endforeach
          </div>
          <span class="comp-plier">
            <button type="button" class="el-btn el-btn--ghost el-btn--sm" id="tout-deplier"><i class="fa-solid fa-angles-down"></i> Tout déplier</button>
            <button type="button" class="el-btn el-btn--ghost el-btn--sm" id="tout-plier"><i class="fa-solid fa-angles-up"></i> Tout plier</button>
          </span>
        </div>
      </div>

      @foreach ($sections as $s)
        @php $deLaSection = $fiches->filter(fn ($f) => $f['chambre']->section_id === $s->id); @endphp
        @continue($deLaSection->isEmpty())

        <section class="el-card comp-section" data-section="s{{ $s->id }}">
          <div class="comp-section__tete" role="button" tabindex="0" aria-expanded="true" aria-controls="corps-s{{ $s->id }}" title="Plier ou déplier la section">
            <div class="comp-section__id">
              <span class="comp-section__icone"><i class="fa-solid fa-landmark"></i></span>
              <div style="min-width:0">
                <h2 class="comp-section__titre">{{ $s->libelle }}</h2>
                <div class="comp-section__sous">
                  <span>{{ $deLaSection->count() }} chambre(s)</span>
                  @if ($deLaSection->where('manques', '!=', [])->count())
                    <x-chip tone="warning" :label="$deLaSection->where('manques', '!=', [])->count().' à compléter'" />
                  @else
                    <x-chip tone="success" label="Toutes complètes" />
                  @endif
                </div>
              </div>
            </div>
            <span class="comp-chevron"><i class="fa-solid fa-chevron-down"></i></span>
          </div>

          <div class="comp-section__corps" id="corps-s{{ $s->id }}">
            <div class="comp-section__interieur">
              <div class="comp-grille">
                @foreach ($deLaSection as $f)
                  @php
                    $c = $f['chambre'];
                    $horaires = $f['audiences']->map(fn ($h) => $h['type'].' '.$h['quand'].' '.$h['salle'])->implode(' ');
                    $texte = $c->libelle.' '.$c->intitule.' '.$c->groupe.' '.$f['presidents']->pluck('nom')->implode(' ').' '.$horaires.' '.($f['manques'] ? 'à compléter '.implode(' ', $f['manques']) : 'complète');
                  @endphp
                  <article class="comp-carte {{ $f['manques'] ? 'comp-carte--manque' : '' }}" id="chambre-{{ $c->id }}" data-chambre="{{ $c->id }}" data-nom="{{ $c->libelleComplet() }}" data-section-nom="{{ $s->libelle }}" data-recherche="{{ $texte }}" data-manque="{{ $f['manques'] ? 1 : 0 }}">

                    <div class="comp-carte__haut">
                      <div>
                        <h3 class="comp-carte__nom">{{ $c->libelle }}</h3>
                        @if ($c->intitule || $c->groupe)<div class="comp-carte__sous">{{ $c->intitule ?: $c->groupe }}</div>@endif
                      </div>
                      <div style="display:flex; flex-direction:column; align-items:flex-end; gap:5px">
                        @if ($f['manques'])
                          <x-chip tone="warning" label="À compléter" />
                        @else
                          <x-chip tone="success" label="Complète" />
                        @endif
                        @unless ($c->actif)<x-chip tone="neutral" label="Inactive" />@endunless
                      </div>
                    </div>

                    <div class="comp-carte__corps">
                      <div class="comp-tete">
                        @if ($f['presidents']->isEmpty())
                          <span class="comp-vide-tete"><i class="fa-solid fa-user-slash"></i> Aucun président ni juge affecté</span>
                        @else
                          <span class="comp-tete__avatars">
                            @foreach ($f['presidents']->take(3) as $p)
                              <button type="button" onclick="voir_agent({{ $p['id'] }})" title="Fiche de {{ $p['nom'] }}" aria-label="Fiche de {{ $p['nom'] }}"><x-avatar :name="$p['nom']" :size="38" /></button>
                            @endforeach
                          </span>
                          <div style="min-width:0">
                            <div class="comp-tete__role">{{ $f['presidents']->count() > 1 ? $f['role_tete'].'s' : $f['role_tete'] }}</div>
                            <div class="comp-tete__noms">
                              <button type="button" class="comp-lien" onclick="voir_agent({{ $f['presidents']->first()['id'] }})">{{ $f['presidents']->first()['nom'] }}</button>
                              @foreach ($f['presidents']->slice(1, 1) as $p)
                                <br><button type="button" class="comp-lien" onclick="voir_agent({{ $p['id'] }})">{{ $p['nom'] }}</button>
                              @endforeach
                              @if ($f['presidents']->count() > 2)
                                <small>+ {{ $f['presidents']->count() - 2 }} autre(s) — <button type="button" class="comp-lien" onclick="ouvrir_chambre({{ $c->id }})">voir tous</button></small>
                              @endif
                            </div>
                          </div>
                        @endif
                      </div>

                      <div class="comp-eff">
                        <span class="e-tit"><b>{{ $f['nb_titulaires'] }}</b> titulaires</span>
                        <span class="e-sup"><b>{{ $f['nb_suppleants'] }}</b> suppléants</span>
                        <span class="e-gre"><b>{{ $f['nb_greffiers'] }}</b> greffiers</span>
                      </div>
                    </div>

                    @if ($f['manques'])
                      <div class="comp-manques">
                        @foreach ($f['manques'] as $manque)<x-chip tone="alert" :label="ucfirst($manque)" />@endforeach
                      </div>
                    @endif

                    <div class="comp-hz-liste">
                      @forelse ($f['audiences']->take(4) as $h)
                        @php
                          $precision = collect([$h['type'] !== 'Audience' ? \Illuminate\Support\Str::before($h['type'], ' (') : null, $h['salle'] ? str_replace("Salle d'audience ", 'Salle ', $h['salle']) : null])->filter()->implode(' · ');
                        @endphp
                        <span class="comp-hz" title="{{ $h['type'] }} — {{ $h['quand'] }}{{ $h['salle'] ? ' · '.$h['salle'] : '' }}"><i class="fa-regular fa-clock"></i><b>{{ $h['court'] }}</b>@if ($precision)<span>{{ $precision }}</span>@endif</span>
                      @empty
                        <span class="comp-hz comp-hz--vide"><i class="fa-regular fa-clock"></i> Aucune audience enregistrée</span>
                      @endforelse
                      @if ($f['audiences']->count() > 4)<span class="comp-hz comp-hz--plus">+ {{ $f['audiences']->count() - 4 }} autre(s)</span>@endif
                    </div>

                    <div class="comp-carte__pied">
                      @if ($f['a_venir'])<span class="comp-avenir">+ {{ $f['a_venir'] }} affectation(s) à venir</span>@endif
                      <button type="button" class="comp-composer" onclick="ouvrir_chambre({{ $c->id }})"><i class="fa-solid fa-user-pen"></i> Composer</button>
                    </div>
                  </article>
                @endforeach
              </div>
            </div>
          </div>
        </section>
      @endforeach

      <div class="el-card" id="comp-vide" hidden>
        <div class="el-card__body"><span class="el-muted"><i class="fa-regular fa-folder-open"></i> Aucune chambre ne correspond à ces filtres.</span></div>
      </div>

      @if ($fiches->isEmpty())
        <div class="el-card"><div class="el-card__body"><span class="el-muted">Aucune chambre pour cette année judiciaire. Créez-les depuis « Chambres ».</span></div></div>
      @endif

    </div>
  </div>

  {{-- Grande fenêtre « Composer » : la composition détaillée de la chambre choisie --}}
  <div class="modal fade panel-modal" id="modal-composition" tabindex="-1" role="dialog" aria-labelledby="titre-composition" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="comp-modal__tete">
          <div style="min-width:0">
            <div class="comp-modal__sec" id="composition-section"></div>
            <h2 class="comp-modal__titre" id="titre-composition"></h2>
          </div>
          <div class="comp-modal__actions">
            @can('chambres.voir')
              <a class="el-btn el-btn--ghost el-btn--sm" href="{{ route('organisation.chambres', ['annee' => $annee?->id]) }}" title="Attributions et formations de la chambre"><i class="fa-solid fa-sitemap"></i> Structure</a>
            @endcan
            <button type="button" class="el-iconbtn el-iconbtn--teal" data-bs-dismiss="modal" title="Fermer" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
          </div>
        </div>
        <div class="comp-modal__corps" id="composition-corps"></div>
      </div>
    </div>
  </div>

  {{-- Fiche d'une personne --}}
  <div class="modal fade panel-modal" id="modal-agent-fiche" tabindex="-1" role="dialog" aria-labelledby="titre-agent-fiche" aria-hidden="true">
    <div class="modal-dialog" style="width:min(640px, 94vw)">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title" id="titre-agent-fiche">Fiche</h4>
          <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body dark-modal" id="agent-fiche-corps"></div>
        <div class="modal-footer">
          @can('personnel.modifier')
            <a class="btn btn-primary text-white" id="fiche-modifier" href="{{ route('organisation.personnel') }}" title="Ouvre le formulaire de cette personne (nom, fonction, contacts…)"><i class="fa-solid fa-user-pen"></i> Modifier cette personne</a>
          @endcan
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  @include('organisation.affectations.form_affectation')
  @include('organisation.affectations.form_remplacement')
  @include('organisation.affectations.js_script')
  @include('organisation.creneaux.form_creneau')
  @include('organisation.creneaux.js_script')
@endsection

@section('script')
  <script>
    (function () {
      const URL_DETAIL = @js(route('organisation.affectations', ':id'));
      const URL_FICHE = @js(route('organisation.personnel.fiche', ':id'));
      const URL_PERSONNEL = @js(route('organisation.personnel'));
      const ANNEE = @js($annee?->id);
      const OUVRIR = @js($ouvrir);
      const sansAccents = (t) => (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
      const esc = (t) => $('<div>').text(t == null ? '' : String(t)).html();
      let section = '';

      /* ---------- Sections repliables ---------- */
      const CLE = 'tpi_composition_sections_pliees';
      const lirePliees = () => { try { return JSON.parse(localStorage.getItem(CLE) || '[]'); } catch (e) { return []; } };
      const ecrirePliees = () => { try { localStorage.setItem(CLE, JSON.stringify($('.comp-section.is-plie').map((i, e) => $(e).data('section')).get())); } catch (e) { /* stockage indisponible : sans importance */ } };
      function plier($s, plie) {
        $s.toggleClass('is-plie', plie).find('.comp-section__tete').attr('aria-expanded', plie ? 'false' : 'true');
      }
      lirePliees().forEach((cle) => plier($('.comp-section[data-section="' + cle + '"]'), true));

      $('.comp-section__tete').on('click', function () { plier($(this).closest('.comp-section'), !$(this).closest('.comp-section').hasClass('is-plie')); ecrirePliees(); })
        .on('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); } });
      $('#tout-plier').on('click', function () { plier($('.comp-section'), true); ecrirePliees(); });
      $('#tout-deplier').on('click', function () { plier($('.comp-section'), false); ecrirePliees(); });

      /* ---------- Filtres ---------- */
      function filtrer() {
        const q = sansAccents($('#el-search').val().trim());
        const seulementManques = $('#seulement-manques').prop('checked');
        let total = 0;

        $('.comp-section').show();   // on réaffiche tout avant de recompter, sinon une section masquée ne reviendrait jamais
        $('.comp-carte').each(function () {
          const $c = $(this);
          const ok = (!q || sansAccents($c.data('recherche')).includes(q)) && (!seulementManques || $c.data('manque') == 1)
            && (!section || $c.closest('.comp-section').data('section') === section);
          $c.toggle(ok);
          if (ok) { total++; }
        });
        $('.comp-section').each(function () {
          const visible = $(this).find('.comp-carte').filter(function () { return this.style.display !== 'none'; }).length > 0;
          $(this).toggle(visible);
          if (visible && (q || seulementManques)) { plier($(this), false); }   // une recherche ouvre les sections qui ont un résultat
        });
        $('#comp-vide').prop('hidden', total > 0 || $('.comp-carte').length === 0);
      }
      $('#el-search').on('input', filtrer);
      $('#seulement-manques').on('change', filtrer);
      $('.el-pill[data-section]').on('click', function () {
        section = $(this).data('section') || '';
        $('.el-pill[data-section]').removeClass('is-active');
        $(this).addClass('is-active');
        filtrer();
      });

      /* ---------- Grande fenêtre « Composer » ---------- */
      const modalComposition = document.getElementById('modal-composition');
      const $corps = $('#composition-corps');
      let chambreOuverte = null;
      window.compositionModifiee = false;   // passe à vrai quand une suppression (sans rechargement) change la composition

      const adresse = (id) => { const p = new URLSearchParams(); if (ANNEE) { p.set('annee', ANNEE); } if (id) { p.set('ouvrir', id); } return location.pathname + (p.toString() ? '?' + p : ''); };

      window.ouvrir_chambre = function (id) {
        const $carte = $('#chambre-' + id);
        chambreOuverte = id;
        $('#composition-section').text($carte.data('section-nom') || '');
        $('#titre-composition').text($carte.data('nom') || 'Chambre');
        $corps.html('<div class="comp-charge"><i class="fa-solid fa-spinner fa-spin"></i> Chargement de la composition…</div>');
        history.replaceState(null, '', adresse(id));   // un enregistrement qui échoue revient ici, fenêtre rouverte
        $('#modal-agent-fiche').modal('hide');
        $('#modal-composition').modal('show');

        $.ajax({ url: URL_DETAIL.replace(':id', id), type: 'GET', dataType: 'html' })
          .done(function (html) { if (chambreOuverte === id) { $corps.html(html); window.restaurer_panneau(); } })
          .fail(function () { $corps.html('<div class="comp-charge">Impossible de charger la composition. Réessayez.</div>'); });
      };

      $('#modal-composition').on('hidden.bs.modal', function (e) {
        if (e.target !== this) { return; }
        const ciblee = chambreOuverte;
        chambreOuverte = null;
        history.replaceState(null, '', adresse(null));
        if (window.compositionModifiee) { location.href = adresse(null); return; }   // les chiffres des cartes sont à jour au rechargement
        if (ciblee) { $('#chambre-' + ciblee).addClass('is-ciblee'); setTimeout(() => $('#chambre-' + ciblee).removeClass('is-ciblee'), 1800); }
      });

      // Filet de sécurité : l'état de la page se déduit toujours des fenêtres réellement ouvertes, jamais d'un événement manqué.
      //  - une fenêtre ouverte par-dessus la grande fenêtre l'assombrit (et la rend inerte) ;
      //  - plus aucune fenêtre ouverte : plus de fond ni de blocage de la page.
      function verifier_fenetres() {
        const ouvertes = document.querySelectorAll('.modal.show').length;
        modalComposition.classList.toggle('comp-attenue', modalComposition.classList.contains('show') && ouvertes > 1);
        if (ouvertes === 0) {
          $('.modal-backdrop').remove();
          document.body.classList.remove('modal-open');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('padding-right');
        } else {
          document.body.classList.add('modal-open');
        }
      }
      const surveillant = new MutationObserver(verifier_fenetres);
      document.querySelectorAll('.modal').forEach(function (m) { surveillant.observe(m, { attributes: true, attributeFilter: ['class'] }); });

      /* ---------- Fenêtre : onglets « Qui siège » / « Quand elle siège » et choix de la formation ---------- */
      const memoire = (cle, valeur) => { try { if (valeur === undefined) { return sessionStorage.getItem(cle); } sessionStorage.setItem(cle, valeur); } catch (e) { return null; } };

      $(document).on('click', '.comp-onglet', function () {
        const $b = $(this), $f = $b.closest('.comp-formation');
        $f.find('.comp-onglet').removeClass('is-actif');
        $b.addClass('is-actif');
        $f.find('.comp-pane').prop('hidden', true).filter('[data-pane="' + $b.data('cible') + '"]').prop('hidden', false);
        memoire('tpi_comp_onglet_' + $b.closest('.comp-detail').data('chambre'), $b.data('cible'));   // après un enregistrement, on revient sur le même onglet
      });

      $(document).on('click', '.comp-formations .el-pill', function () {
        const $b = $(this), $d = $b.closest('.comp-detail');
        $d.find('.comp-formations .el-pill').removeClass('is-active');
        $b.addClass('is-active');
        $d.find('.comp-formation').prop('hidden', true).filter('[data-formation="' + $b.data('formation') + '"]').prop('hidden', false);
        memoire('tpi_comp_formation_' + $d.data('chambre'), $b.data('formation'));
      });

      window.restaurer_panneau = function () {
        const $d = $corps.find('.comp-detail');
        const chambre = $d.data('chambre');
        const formation = memoire('tpi_comp_formation_' + chambre);
        const onglet = memoire('tpi_comp_onglet_' + chambre);
        if (formation) { $d.find('.comp-formations .el-pill[data-formation="' + formation + '"]').trigger('click'); }
        if (onglet) { $d.find('.comp-formation:not([hidden]) .comp-onglet[data-cible="' + onglet + '"]').trigger('click'); }
      };

      /* ---------- Fiche d'une personne ---------- */
      window.voir_agent = function (id) {
        $('#titre-agent-fiche').text('Fiche');
        $('#fiche-modifier').attr('href', URL_PERSONNEL + '?modifier=' + id);   // la page « Personnel » ouvre directement le formulaire de cette personne
        $('#agent-fiche-corps').html('<div class="comp-charge"><i class="fa-solid fa-spinner fa-spin"></i> Chargement…</div>');
        $('#modal-agent-fiche').modal('show');

        $.getJSON(URL_FICHE.replace(':id', id)).done(function (a) {
          const initiales = a.nom.split(/\s+/).slice(0, 2).map((m) => m.charAt(0)).join('').toUpperCase();
          let h = '<div class="fiche-tete"><span class="el-avatar el-hue-' + (id % 6) + '" style="--s:64px">' + esc(initiales) + '</span><div>'
            + '<div class="fiche-nom">' + esc(a.nom) + '</div><div class="fiche-sub">' + esc(a.fonction) + '</div>'
            + '<div class="fiche-chips"><span class="el-chip el-chip--info"><i></i>' + esc(a.categorie) + '</span>'
            + '<span class="el-chip el-chip--' + (a.actif ? 'success' : 'neutral') + '"><i></i>' + (a.actif ? 'En poste' : 'Hors poste') + '</span></div></div></div>';

          const contacts = [['fa-id-badge', a.matricule ? 'Matricule ' + a.matricule : null], ['fa-phone', a.telephone], ['fa-envelope', a.email], ['fa-user-lock', a.compte ? 'Compte : ' + a.compte : null]].filter((l) => l[1]);
          if (contacts.length) { h += '<div class="fiche-contacts">' + contacts.map((l) => '<span class="fiche-ct"><i class="fa-solid ' + l[0] + '"></i>' + esc(l[1]) + '</span>').join('') + '</div>'; }

          // Une ligne par chambre (une personne peut y avoir plusieurs rôles ou formations)
          h += '<div class="fiche-rub">Siège actuellement</div>';
          if (a.affectations.length) {
            const groupes = {}, ordre = [];
            a.affectations.forEach((x) => {
              if (!groupes[x.chambre_id]) { groupes[x.chambre_id] = { nom: x.chambre, roles: [], vus: {} }; ordre.push(x.chambre_id); }
              const cle = (x.formation || '') + '|' + x.role + '|' + x.chef_section + '|' + x.debut;
              if (!groupes[x.chambre_id].vus[cle]) { groupes[x.chambre_id].vus[cle] = true; groupes[x.chambre_id].roles.push(x); }   // deux lignes identiques n'en font qu'une
            });
            h += ordre.map((cid) => {
              const g = groupes[cid];
              const ouvrable = $('#chambre-' + cid).length > 0;
              return '<div class="fiche-chambre"><div><div class="fiche-chambre__nom">' + esc(g.nom) + '</div><div class="fiche-roles">'
                + g.roles.map((x) => '<span class="fiche-role">' + (x.formation ? esc(x.formation) + ' · ' : '') + esc(x.role) + (x.chef_section ? ' · Chef de section' : '')
                  + (x.debut ? ' <small>depuis le ' + esc(x.debut) + '</small>' : '') + '</span>').join('')
                + '</div></div>'
                + (ouvrable ? '<button type="button" class="el-btn el-btn--ghost el-btn--sm" onclick="ouvrir_chambre(' + cid + ')">Ouvrir</button>' : '')
                + '</div>';
            }).join('');
          } else {
            h += '<div class="fiche-rien">Aucune affectation en cours dans une chambre.</div>';
          }

          h += '<div class="fiche-rub">Responsabilités</div>';
          h += a.services.length
            ? '<div class="fiche-svc">' + a.services.map((x) => '<span>' + esc(x.service) + (x.fonction ? '<small>' + esc(x.fonction) + '</small>' : '') + '</span>').join('') + '</div>'
            : '<div class="fiche-rien">Aucune responsabilité (service, comité, délégation) pour l\'année en cours.</div>';

          $('#titre-agent-fiche').text(a.nom);
          $('#agent-fiche-corps').html(h);
        }).fail(function () { $('#agent-fiche-corps').html('<div class="comp-charge">Impossible de charger la fiche.</div>'); });
      };

      /* ---------- À l'ouverture : rouvrir la chambre indiquée (après un enregistrement) ---------- */
      if (OUVRIR) {
        plier($('#chambre-' + OUVRIR).closest('.comp-section'), false);
        $('#chambre-' + OUVRIR)[0]?.scrollIntoView({ block: 'center' });
        window.ouvrir_chambre(OUVRIR);
      }
    })();
  </script>
@endsection
