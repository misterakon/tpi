@extends('layout.master')
@section('title', "Feuille d'audience")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page tp-anime">

      <x-hero tag="Audiences" title="Feuille d'audience" subtitle="Ouvrez l'audience d'un rôle publié, contrôlez la composition, puis notez l'issue de chaque affaire à mesure de l'appel : renvoi, délibéré, jugement, radiation, jonction ou désistement." icon="fa-solid fa-clipboard-list">
        <x-slot:actions>
          @can('roles.voir')<a class="el-btn el-btn--ghost" href="{{ route('audiences.roles') }}"><i class="fa-solid fa-list-ol"></i> Rôles d'audience</a>@endcan
        </x-slot:actions>
      </x-hero>

      <div class="au-kpis au-kpis--compact" id="kpis-feuille"></div>

      @if ($abPublier > 0)
        <div class="au-conseil au-conseil--bleu"><i class="fa-solid fa-circle-info"></i><span><b>{{ $abPublier }} rôle{{ $abPublier > 1 ? 's' : '' }} à venir {{ $abPublier > 1 ? 'ne sont pas encore publiés' : "n'est pas encore publié" }}.</b> La feuille se fait sur le rôle publié : publiez-le avant l'audience.
          @can('roles.voir')<a class="au-lien" href="{{ route('audiences.roles', ['statut' => 'brouillon']) }}">Voir les rôles en préparation</a>@endcan</span></div>
      @endif

      <div class="au-cadre">
        <div class="au-barre-onglets">
          <div class="au-onglets" role="tablist" id="onglets-feuille">
            <button type="button" class="is-actif" data-vue="aujourdhui" role="tab">Aujourd'hui <b id="n-aujourdhui"></b></button>
            <button type="button" data-vue="avenir" role="tab">À venir <b id="n-avenir"></b></button>
            <button type="button" data-vue="passees" role="tab">Tenues <b id="n-passees"></b></button>
            <button type="button" data-vue="toutes" role="tab">Toutes</button>
          </div>
          <div class="au-recherche"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="recherche-feuille" placeholder="Chambre, date, salle…" autocomplete="off"></div>
        </div>

        <div class="au-fond"><div class="au-grille" id="grille-feuille"></div></div>
        <div class="au-pagination" id="pagination-feuille"></div>
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script>
  (function () {
    'use strict';
    const AUDIENCES = @js($audiences);
    const URL_FEUILLE = @js(route('audiences.feuille.show', 0));
    const PAR_PAGE = 6;
    let vue = 'aujourdhui', page = 1;
    const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    const ETATS = { a_tenir: ['gris', 'fa-hourglass-start', 'À tenir'], en_cours: ['orange', 'fa-bolt', 'En cours'], terminee: ['vert', 'fa-check', 'Terminée'] };
    const dans = { aujourdhui: function (a) { return a.aujourdhui; }, avenir: function (a) { return !a.passee && !a.aujourdhui; }, passees: function (a) { return a.passee; }, toutes: function () { return true; } };

    function compteurs() {
      const kpiPct = function (p) { if (p == null || isNaN(p)) { return ''; } const v = Math.round(Math.max(0, Math.min(100, p))); return ' data-pct="' + v + '" style="--pct:' + v + '"'; };
      const c = function (f) { return AUDIENCES.filter(f).length; };
      $('#n-aujourdhui').text(c(dans.aujourdhui)); $('#n-avenir').text(c(dans.avenir)); $('#n-passees').text(c(dans.passees));
      const traitees = AUDIENCES.filter(dans.aujourdhui).reduce(function (a, x) { return a + x.traitees; }, 0), total = AUDIENCES.filter(dans.aujourdhui).reduce(function (a, x) { return a + x.total; }, 0);
      const cartes = [
        ['orange', 'fa-sun', "Audiences aujourd'hui", c(dans.aujourdhui), 'fa-bolt', c(function (a) { return a.aujourdhui && a.etat === 'en_cours'; }) + ' en cours'],
        ['vert', 'fa-list-check', "Affaires traitées aujourd'hui", traitees, 'fa-folder-open', 'sur ' + total + ' à l\'appel', (total > 0 ? traitees / total * 100 : null)],
        ['sarcelle', 'fa-calendar-week', 'Audiences à venir', c(dans.avenir), 'fa-calendar', 'dans les 45 prochains jours'],
        ['ardoise', 'fa-flag-checkered', 'Terminées (45 derniers jours)', c(function (a) { return a.etat === 'terminee'; }), 'fa-check', 'feuilles closes'],
      ];
      $('#kpis-feuille').css('grid-template-columns', 'repeat(4, minmax(0, 1fr))').html(cartes.map(function (k) {
        return '<div class="au-kpi au-kpi--' + k[0] + '"' + kpiPct(k[6]) + '><div class="au-kpi__icone"><i class="fa-solid ' + k[1] + '"></i></div><span class="au-kpi__label">' + esc(k[2]) + '</span><div><div class="au-kpi__valeur"><span class="au-compteur" data-valeur="' + k[3] + '">' + k[3] + '</span></div><div class="au-chip"><i class="fa-solid ' + k[4] + '"></i><span>' + esc(k[5]) + '</span></div></div></div>';
      }).join(''));
      if (!reduit) {
        document.querySelectorAll('.au-compteur').forEach(function (el) {
          const cible = parseInt(el.getAttribute('data-valeur'), 10) || 0; if (cible <= 0) { return; }
          let debut = null; el.textContent = '0'; setTimeout(function () { el.textContent = cible; }, 1200);
          window.requestAnimationFrame(function pas(t) { if (debut === null) { debut = t; } const p = Math.min(1, (t - debut) / 700); el.textContent = Math.round(cible * (1 - Math.pow(1 - p, 3))); if (p < 1) { window.requestAnimationFrame(pas); } });
        });
      }
    }

    function carte(a) {
      const aTerminer = a.etat !== 'terminee' && a.total > 0 && a.traitees === a.total;
      const e = aTerminer ? ['orange', 'fa-flag', 'À terminer'] : (a.passee && a.etat === 'a_tenir' ? ['rouge', 'fa-clock', 'En retard'] : ETATS[a.etat]), pct = a.total ? Math.round(a.traitees / a.total * 100) : 0, p = a.court.split(' ');
      const bouton = aTerminer ? '<span class="au-btn au-btn--orange"><i class="fa-solid fa-flag-checkered"></i>Terminer l\'audience</span>' : a.etat === 'terminee' ? '<span class="au-btn au-btn--clair"><i class="fa-solid fa-eye"></i>Consulter</span>' : (a.etat === 'en_cours' ? '<span class="au-btn au-btn--orange"><i class="fa-solid fa-play"></i>Continuer</span>' : '<span class="au-btn au-btn--vert"><i class="fa-solid fa-door-open"></i>Ouvrir la feuille</span>');
      const salle = a.salle || 'Salle à fixer';
      return '<a class="au-carte au-carte--' + a.etat + (a.aujourdhui ? ' is-aujourdhui' : '') + '" href="' + esc(URL_FEUILLE.replace(/\/0$/, '/' + a.id)) + '">'
        + '<div class="au-carte__haut"><div class="au-date ' + (a.aujourdhui ? 'au-date--orange' : (a.passee ? 'au-date--gris' : '')) + '"><span>' + esc(p[0] || '') + '</span><b>' + esc((p[1] || '').split('/')[0]) + '</b></div>'
        + '<div class="au-carte__titre"><b title="' + esc(a.chambre) + '">' + esc(a.chambre) + '</b><small>' + esc(a.jour) + (a.heure ? ' · ' + esc(a.heure) : '') + '</small></div></div>'
        + '<div class="au-carte__infos"><span class="au-badge au-badge--' + e[0] + '"><i class="fa-solid ' + e[1] + '"></i>' + e[2] + '</span><span class="au-info"><i class="fa-solid fa-scale-balanced"></i>' + esc(a.type) + '</span></div>'
        + '<div class="au-carte__salle"><i class="fa-solid fa-landmark"></i><span title="' + esc(salle) + '">' + esc(salle) + '</span></div>'
        + '<div class="au-progres" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"><i style="width:' + pct + '%"></i></div>'
        + '<div class="au-carte__bas"><span>' + a.traitees + ' / ' + a.total + ' affaire' + (a.total > 1 ? 's' : '') + ' traitée' + (a.total > 1 ? 's' : '') + '</span>' + bouton + '</div></a>';
    }

    function pagination(total, debut, fin) {
      const pages = Math.ceil(total / PAR_PAGE);
      if (!total) { $('#pagination-feuille').empty(); return; }
      let nav = '<button type="button" data-p="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="Page précédente"><i class="fa-solid fa-angle-left"></i></button>';
      for (let i = 1; i <= pages; i++) { nav += '<button type="button" data-p="' + i + '"' + (i === page ? ' class="is-actif" aria-current="page"' : '') + '>' + i + '</button>'; }
      nav += '<button type="button" data-p="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + ' aria-label="Page suivante"><i class="fa-solid fa-angle-right"></i></button>';
      $('#pagination-feuille').html('<span>' + debut + ' à ' + fin + ' sur ' + total + ' audience' + (total > 1 ? 's' : '') + '</span><nav>' + nav + '</nav>');
    }

    function afficher() {
      const q = ($('#recherche-feuille').val() || '').toLowerCase().trim();
      let liste = AUDIENCES.filter(dans[vue]).filter(function (a) { return !q || (a.chambre + ' ' + a.jour + ' ' + (a.salle || '') + ' ' + a.type + ' ' + (a.heure || '')).toLowerCase().indexOf(q) >= 0; });
      if (vue === 'passees') { liste = liste.slice().reverse(); }
      const pages = Math.max(1, Math.ceil(liste.length / PAR_PAGE));
      page = Math.min(Math.max(1, page), pages);
      const debut = (page - 1) * PAR_PAGE;
      $('#grille-feuille').html(liste.length ? liste.slice(debut, debut + PAR_PAGE).map(carte).join('') : '<div class="au-vide" style="grid-column:1/-1"><i class="fa-solid fa-clipboard-list"></i>' + (AUDIENCES.length ? 'Aucune audience dans cette vue.' : 'Aucun rôle publié pour le moment : publiez un rôle pour ouvrir son audience.') + '</div>');
      pagination(liste.length, debut + 1, Math.min(liste.length, debut + PAR_PAGE));
    }

    document.addEventListener('DOMContentLoaded', function () {
      if (!AUDIENCES.some(dans.aujourdhui)) { vue = AUDIENCES.some(dans.avenir) ? 'avenir' : 'toutes'; $('#onglets-feuille button').removeClass('is-actif').filter('[data-vue="' + vue + '"]').addClass('is-actif'); }
      compteurs(); afficher();
      $('#onglets-feuille').on('click', 'button', function () { vue = this.getAttribute('data-vue'); $('#onglets-feuille button').removeClass('is-actif'); $(this).addClass('is-actif'); page = 1; afficher(); });
      $('#recherche-feuille').on('input', function () { page = 1; afficher(); });
      $('#pagination-feuille').on('click', 'button:not(:disabled)', function () { page = parseInt(this.getAttribute('data-p'), 10) || 1; afficher(); });
    });
  })();
  </script>
@endsection
