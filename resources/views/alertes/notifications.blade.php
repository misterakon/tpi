@extends('layout.master')
@section('title', 'Mes notifications')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page al-page">

      <x-hero tag="Alertes" title="Mes notifications" subtitle="Ce qui demande votre action : une affaire à mettre au rôle, des suites à noter, une décision à rédiger, un délai qui approche. Chaque alerte dit quoi faire et mène à l'écran où le faire ; elle se referme toute seule quand l'action est faite." icon="fa-solid fa-bell">
        <x-slot:actions>
          <form method="post" action="{{ route('notifications.tout_lire') }}" id="form-tout-lire" hidden>@csrf<button type="submit" class="el-btn el-btn--ghost"><i class="fa-solid fa-check-double"></i> Tout marquer comme lu</button></form>
          @can('alertes.voir')<a class="el-btn el-btn--accent" href="{{ route('alertes.suivi') }}"><i class="fa-solid fa-bell-concierge"></i> Suivi des alertes</a>@endcan
        </x-slot:actions>
      </x-hero>

      <div class="au-kpis au-kpis--compact" id="kpis-notifs" style="grid-template-columns: repeat(4, minmax(0, 1fr))"></div>

      <div class="au-cadre">
        <div class="au-compte-filtres" id="filtres-notifs"></div>
        <div class="au-fond"><div class="al-liste" id="liste-notifs"></div></div>
        <div class="au-pagination" id="pagination-notifs"></div>
      </div>

    </div>
  </div>
  @if ($peutJustifier)
    @include('alertes.form_justification', ['pauses' => \App\Http\Controllers\AlertesController::PAUSES])
  @endif
@endsection

@section('script')
  @include('alertes.js_commun', ['peutJustifier' => $peutJustifier])
  <script type="text/javascript">
  (function () {
    'use strict';
    const NOTIFS = @js($notifications);
    const PAR_PAGE = 8;
    const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let filtre = 'toutes', page = 1;

    const dans = {
      toutes: function () { return true; }, non_lues: function (n) { return !n.lue; }, critiques: function (n) { return n.niveau === 'critique' && n.statut !== 'resolue'; },
      attention: function (n) { return n.niveau === 'attention' && n.statut !== 'resolue'; }, info: function (n) { return n.niveau === 'info' && n.statut !== 'resolue'; }, refermees: function (n) { return n.statut === 'resolue'; },
    };
    const ETIQUETTES = { toutes: 'Toutes', non_lues: 'Non lues', critiques: 'Critiques', attention: 'Attention', info: 'Information', refermees: 'Refermées' };

    function kpis() {
      const c = function (f) { return NOTIFS.filter(f).length; };
      const cartes = [
        ['orange', 'fa-envelope', 'Non lues', c(dans.non_lues), 'fa-bell', 'à ouvrir'],
        ['rouge', 'fa-triangle-exclamation', 'Critiques en cours', c(dans.critiques), 'fa-fire', 'à traiter en priorité'],
        ['sarcelle', 'fa-clock', 'En retard', c(function (n) { return n.retard > 0 && n.statut === 'ouverte'; }), 'fa-calendar-xmark', 'échéance dépassée'],
        ['vert', 'fa-circle-check', 'Refermées', c(dans.refermees), 'fa-check', 'action faite'],
      ];
      $('#kpis-notifs').html(cartes.map(function (k) {
        return '<div class="au-kpi au-kpi--' + k[0] + '"><div class="au-kpi__icone"><i class="fa-solid ' + k[1] + '"></i></div><span class="au-kpi__label">' + AL.esc(k[2]) + '</span><div><div class="au-kpi__valeur"><span>' + k[3] + '</span></div><div class="au-chip"><i class="fa-solid ' + k[4] + '"></i><span>' + AL.esc(k[5]) + '</span></div></div></div>';
      }).join(''));
      $('#form-tout-lire').prop('hidden', c(dans.non_lues) === 0);
    }

    function filtres() {
      $('#filtres-notifs').html(Object.keys(ETIQUETTES).map(function (k) {
        return '<button type="button" class="au-filtre-etat ' + (k === 'critiques' ? 'au-filtre-etat--alerte ' : '') + (filtre === k ? 'is-actif' : '') + '" data-f="' + k + '">' + ETIQUETTES[k] + '<b>' + NOTIFS.filter(dans[k]).length + '</b></button>';
      }).join(''));
    }

    function afficher() {
      const liste = NOTIFS.filter(dans[filtre]);
      page = Math.min(Math.max(1, page), Math.max(1, Math.ceil(liste.length / PAR_PAGE)));
      const debut = (page - 1) * PAR_PAGE;
      $('#liste-notifs').html(liste.length ? liste.slice(debut, debut + PAR_PAGE).map(function (n) {
        return AL.carte({ id: n.alerte_id, type: n.type, type_libelle: n.type_libelle, niveau: n.niveau, regle: n.regle, titre: n.titre, action: n.action, echeance: n.echeance, retard: n.retard, statut: n.statut, justifiee_jusqu: n.justifiee_jusqu, justification: n.justification, chambre: '' },
          { lue: n.lue, nouvelle: !n.lue, ouvrir: n.ouvrir, lire: n.lue ? null : n.id, orphelin: n.sans_destinataire });
      }).join('') : '<div class="al-vide"><i class="fa-solid fa-bell-slash"></i>' + (NOTIFS.length ? 'Aucune notification dans cette vue.' : 'Aucune notification pour le moment. Vous serez prévenu ici dès qu\'une action vous attend.') + '</div>');
      AL.pagination('#pagination-notifs', liste.length, page, PAR_PAGE, ['notification', 'notifications']);
    }

    document.addEventListener('DOMContentLoaded', function () {
      kpis(); filtres(); afficher();
      $('#filtres-notifs').on('click', '.au-filtre-etat', function () { filtre = this.getAttribute('data-f'); page = 1; filtres(); afficher(); });
      $('#pagination-notifs').on('click', 'button:not(:disabled)', function () { page = parseInt(this.getAttribute('data-p'), 10) || 1; afficher(); });
      $('#liste-notifs').on('click', '[data-act="lire"]', function () {
        const id = this.getAttribute('data-notif'), n = NOTIFS.find(function (x) { return x.id === id; });
        fetch(@js(route('notifications.lire', '00000000-0000-0000-0000-000000000000')).replace('00000000-0000-0000-0000-000000000000', id), { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { if (r.ok && n) { n.lue = true; kpis(); filtres(); afficher(); } });
      }).on('click', '[data-act="justifier"]', function () {
        const id = +this.getAttribute('data-alerte'), n = NOTIFS.find(function (x) { return x.alerte_id === id; });
        AL.justifier(id, n ? n.titre : '', n ? n.justification : '', n ? n.titre : '');
      });
      AL.apresJustification(function (a) { NOTIFS.forEach(function (n) { if (n.alerte_id === a.id) { n.statut = a.statut; n.justifiee_jusqu = a.justifiee_jusqu; n.justification = a.justification; n.retard = a.retard; } }); kpis(); filtres(); afficher(); });
    });
  })();
  </script>
@endsection
