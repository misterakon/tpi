@extends('layout.master')
@section('title', "Aide et manuel d'utilisation")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  @php
    $parcours = [
      ['affaires', 'enroler-une-affaire', 'fa-solid fa-file-circle-plus', 'Enrôler', "Saisir l'affaire et ses parties", 'vert'],
      ['audiences', 'preparer-les-roles', 'fa-solid fa-calendar-check', 'Mettre au rôle', 'Composer et publier le rôle', 'bleu'],
      ['audiences', 'tenir-laudience', 'fa-solid fa-gavel', "Tenir l'audience", 'Noter renvoi, délibéré, jugement', 'orange'],
      ['decisions', 'rediger-une-decision', 'fa-solid fa-pen-nib', 'Rédiger', 'Du brouillon à la validation', 'violet'],
      ['decisions', 'signer-et-delivrer', 'fa-solid fa-stamp', 'Signer et délivrer', 'Numéro, signature, remise', 'vert'],
      ['decisions', 'recours-et-certificats', 'fa-solid fa-scale-balanced', 'Recours', 'Signification et certificat', 'orange'],
    ];
  @endphp
  <div class="page-body">
    <div class="el-page aide tp-anime">

      <x-hero tag="Aide" title="Manuel d'utilisation" subtitle="Tout pour bien utiliser l'application : pas à pas, règles à connaître et solutions aux blocages. Posez votre question, ou choisissez un chapitre." icon="fa-solid fa-life-ring">
        <x-slot:extra>
          <div class="aide-recherche" id="aide-recherche">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="aide-q" placeholder="Que cherchez-vous ? (ex. renvoi, certificat, mot de passe…)" autocomplete="off" aria-label="Rechercher dans l'aide">
            <kbd>/</kbd>
            <div class="aide-resultats" id="aide-resultats" role="listbox"></div>
          </div>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--accent" href="{{ route('aide.manuel') }}" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Manuel complet (imprimer / PDF)</a>
        </x-slot:actions>
      </x-hero>

      <section class="aide-parcours">
        <h2><i class="fa-solid fa-route" style="color:#f97316"></i> Le parcours d'un dossier, de l'enrôlement au recours</h2>
        <p>Cliquez sur une étape pour ouvrir l'explication.</p>
        <div class="aide-etapes">
          @foreach ($parcours as [$slug, $ancre, $icone, $titre, $sous, $couleur])
            @if (isset($chapitres[$slug]))
              <a class="aide-etape aide-c-{{ $couleur }}" href="{{ route('aide.chapitre', $slug) }}#{{ $ancre }}"><span class="aide-etape__pastille"><i class="{{ $icone }}"></i></span><b>{{ $titre }}</b><small>{{ $sous }}</small></a>
            @else
              <div class="aide-etape aide-c-{{ $couleur }}" style="opacity:.45"><span class="aide-etape__pastille"><i class="{{ $icone }}"></i></span><b>{{ $titre }}</b><small>{{ $sous }}</small></div>
            @endif
          @endforeach
        </div>
      </section>

      <div class="aide-grille">
        @foreach ($chapitres as $c)
          <a class="aide-carte aide-c-{{ $c['couleur'] }}" href="{{ route('aide.chapitre', $c['slug']) }}" style="animation-delay: {{ $loop->index * 50 }}ms">
            <div class="aide-carte__tete"><span class="aide-carte__ico"><i class="{{ $c['icone'] }}"></i></span><h3>{{ $c['titre'] }}</h3></div>
            <p>{{ $c['resume'] }}</p>
            <footer><span><i class="fa-regular fa-clock"></i> {{ $c['minutes'] }} min · {{ count($c['sections']) }} section{{ count($c['sections']) > 1 ? 's' : '' }}</span><span class="aide-aller">Ouvrir <i class="fa-solid fa-arrow-right"></i></span></footer>
          </a>
        @endforeach
      </div>

      <div class="aide-astuces">
        <div class="aide-astuce"><i class="fa-solid fa-circle-question"></i><div><b>Aide sur chaque page</b><span>Le bouton orange <kbd>?</kbd> de l'en-tête ouvre l'explication de la page où vous êtes, sans la quitter.</span></div></div>
        <div class="aide-astuce"><i class="fa-solid fa-keyboard"></i><div><b>Raccourcis</b><span><kbd>F1</kbd> ouvre l'aide de la page · <kbd>/</kbd> cherche dans le manuel · <kbd>Échap</kbd> la referme.</span></div></div>
        <div class="aide-astuce"><i class="fa-solid fa-shield-halved"></i><div><b>Adapté à vos droits</b><span>Vous ne voyez que les chapitres des modules auxquels votre profil donne accès.</span></div></div>
      </div>

    </div>
  </div>
@endsection

@section('script')
  <script>
  (function () {
    'use strict';
    const q = document.getElementById('aide-q'), boite = document.getElementById('aide-resultats');
    const URL_RECHERCHE = @js(route('aide.recherche'));
    let minuteur = null, requete = 0, actif = -1;
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function chercher() {
      const v = q.value.trim();
      if (v.length < 2) { boite.classList.remove('is-ouvert'); return; }
      const n = ++requete;
      fetch(URL_RECHERCHE + '?q=' + encodeURIComponent(v), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(j => {
        if (n !== requete) { return; }
        actif = -1;
        boite.innerHTML = j.resultats.length ? j.resultats.map(r => '<a class="aide-resultat aide-c-' + esc(r.couleur) + '" href="' + esc(r.url) + '"><span class="aide-resultat__ico"><i class="' + esc(r.icone) + '"></i></span><div><em>' + esc(r.chapitre) + '</em><b>' + esc(r.section) + '</b><small>' + r.extrait + '</small></div></a>').join('')
          : '<div class="aide-vide"><i class="fa-regular fa-face-frown" style="font-size:26px"></i><p>Aucun résultat pour « ' + esc(v) + ' ». Essayez un autre mot.</p></div>';
        boite.classList.add('is-ouvert');
      });
    }
    q.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(chercher, 220); });
    q.addEventListener('keydown', e => {
      const l = boite.querySelectorAll('.aide-resultat');
      if (!l.length) { return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); actif = (actif + (e.key === 'ArrowDown' ? 1 : -1) + l.length) % l.length; l.forEach((a, i) => a.classList.toggle('is-actif', i === actif)); l[actif].scrollIntoView({ block: 'nearest' }); }
      if (e.key === 'Enter' && actif >= 0) { e.preventDefault(); location.href = l[actif].href; }
      if (e.key === 'Escape') { boite.classList.remove('is-ouvert'); q.blur(); }
    });
    document.addEventListener('click', e => { if (!e.target.closest('#aide-recherche')) { boite.classList.remove('is-ouvert'); } });
    document.addEventListener('keydown', e => { if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); q.focus(); } });
  })();
  </script>
@endsection
