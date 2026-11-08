@extends('layout.master')
@section('title', 'Suivi des alertes')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page al-page">

      <x-hero tag="Alertes" title="Suivi des alertes" subtitle="Toutes les situations qui demandent une action, des plus graves aux moins graves, par chambre. Une alerte se referme toute seule quand l'action est faite ; un retard expliqué peut être justifié pour suspendre les relances." icon="fa-solid fa-bell-concierge">
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('notifications.index') }}"><i class="fa-solid fa-bell"></i> Mes notifications</a>
          @can('regles_alerte.voir')<a class="el-btn el-btn--accent" href="{{ route('alertes.regles') }}"><i class="fa-solid fa-sliders"></i> Règles d'alerte</a>@endcan
        </x-slot:actions>
      </x-hero>

      {{-- État du moteur : il doit tourner chaque jour ; sinon aucune alerte ne part. --}}
      @if (! $derniere)
        <div class="al-moteur is-jamais"><i class="fa-solid fa-circle-info"></i><span>Le moteur d'alertes n'a encore jamais tourné. Lancez <b>php artisan alertes:evaluer</b>, puis vérifiez que le planificateur de tâches du serveur appelle <b>php artisan schedule:run</b> chaque minute.</span></div>
      @elseif ($derniere['retard_jours'] >= 2)
        <div class="al-moteur is-alerte"><i class="fa-solid fa-triangle-exclamation"></i><span><b>Le moteur d'alertes n'a pas tourné depuis {{ $derniere['retard_jours'] }} jours</b> (dernière évaluation : {{ $derniere['jour'] }}). Vérifiez le planificateur de tâches du serveur : sans lui, aucune alerte n'est envoyée.</span></div>
      @else
        <div class="al-moteur"><i class="fa-solid fa-circle-check"></i><span>Moteur d'alertes en marche : dernière évaluation le {{ $derniere['jour'] }}, {{ $derniere['declenchees'] }} règle{{ $derniere['declenchees'] > 1 ? 's' : '' }} déclenchée{{ $derniere['declenchees'] > 1 ? 's' : '' }}, {{ $derniere['envoyees'] }} alerte{{ $derniere['envoyees'] > 1 ? 's' : '' }} envoyée{{ $derniere['envoyees'] > 1 ? 's' : '' }}{{ $derniere['echecs'] ? ', '.$derniere['echecs'].' échec(s)' : '' }}.</span></div>
      @endif

      <div class="au-kpis au-kpis--compact" id="kpis-alertes" style="grid-template-columns: repeat(4, minmax(0, 1fr))"></div>

      <div class="au-cadre">
        <div class="au-compte-filtres" id="filtres-etat"></div>

        <div class="au-filtres-ligne">
          <div class="au-champ">
            <label for="f-chambre">Chambre</label>
            <select class="form-select" id="f-chambre" data-sans-recherche>
              <option value="">Toutes les chambres</option>
              @foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach
            </select>
          </div>
          <div class="au-champ">
            <label for="f-type">Type d'alerte</label>
            <select class="form-select" id="f-type" data-sans-recherche>
              <option value="">Tous les types</option>
              @foreach ($types as $cle => $libelle)<option value="{{ $cle }}">{{ $libelle }}</option>@endforeach
            </select>
          </div>
          <div class="au-champ au-champ--date">
            <label for="f-niveau">Gravité</label>
            <select class="form-select" id="f-niveau" data-sans-recherche>
              <option value="">Toutes</option><option value="critique">Critique</option><option value="attention">Attention</option><option value="info">Information</option>
            </select>
          </div>
          <div class="au-recherche"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="f-q" placeholder="Numéro d'affaire, audience…" autocomplete="off" maxlength="80"></div>
          <button type="button" class="au-lien" id="f-reinit" hidden><i class="fa-solid fa-rotate-left"></i> Réinitialiser</button>
        </div>

        <div class="au-fond"><div class="al-liste" id="liste-alertes"></div></div>
        <div class="au-pagination" id="pagination-alertes"></div>
      </div>

    </div>
  </div>
  @if ($peutJustifier)
    @include('alertes.form_justification', ['pauses' => $pauses])
  @endif
@endsection

@section('script')
  @include('alertes.js_commun', ['peutJustifier' => $peutJustifier])
  <script type="text/javascript">
  (function () {
    'use strict';
    const URL_DONNEES = @js(route('alertes.suivi.donnees'));
    const f = { etat: 'ouvertes', chambre: '', type: '', niveau: '', q: '', page: 1 };
    const ETATS = { ouvertes: 'Ouvertes', en_retard: 'En retard', justifiees: 'Justifiées', refermees: 'Refermées (30 jours)' };
    let compteurs = {}, requete = 0, minuteur = null, cache = [];

    function parametres() { const p = new URLSearchParams(); Object.keys(f).forEach(function (k) { if (f[k] !== '' && f[k] != null) { p.set(k, f[k]); } }); return p.toString(); }

    function kpis() {
      const cartes = [
        ['orange', 'fa-bell', 'Alertes ouvertes', compteurs.ouvertes || 0, 'fa-list-check', 'à traiter'],
        ['rouge', 'fa-clock', 'En retard', compteurs.en_retard || 0, 'fa-calendar-xmark', 'échéance dépassée'],
        ['ardoise', 'fa-fire', 'Critiques', compteurs.critiques || 0, 'fa-triangle-exclamation', 'priorité haute'],
        ['sarcelle', 'fa-user-clock', 'Retards justifiés', compteurs.justifiees || 0, 'fa-pause', 'relances suspendues'],
      ];
      $('#kpis-alertes').html(cartes.map(function (k) {
        return '<div class="au-kpi au-kpi--' + k[0] + '"><div class="au-kpi__icone"><i class="fa-solid ' + k[1] + '"></i></div><span class="au-kpi__label">' + AL.esc(k[2]) + '</span><div><div class="au-kpi__valeur"><span>' + k[3] + '</span></div><div class="au-chip"><i class="fa-solid ' + k[4] + '"></i><span>' + AL.esc(k[5]) + '</span></div></div></div>';
      }).join(''));
    }

    function filtresEtat() {
      const nb = { ouvertes: compteurs.ouvertes, en_retard: compteurs.en_retard, justifiees: compteurs.justifiees, refermees: compteurs.refermees };
      $('#filtres-etat').html(Object.keys(ETATS).map(function (k) { return '<button type="button" class="au-filtre-etat ' + (k === 'en_retard' ? 'au-filtre-etat--alerte ' : '') + (f.etat === k ? 'is-actif' : '') + '" data-e="' + k + '">' + ETATS[k] + '<b>' + (nb[k] || 0) + '</b></button>'; }).join(''));
      $('#f-reinit').prop('hidden', !(f.chambre || f.type || f.niveau || f.q));
    }

    function charger() {
      const numero = ++requete; $('.au-fond').addClass('is-charge');
      fetch(URL_DONNEES + '?' + parametres() + '&page=' + f.page, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { if (!r.ok) { throw new Error('Erreur'); } return r.json(); })
        .then(function (j) {
          if (numero !== requete) { return; }
          compteurs = j.compteurs; cache = j.lignes; kpis(); filtresEtat();
          $('#liste-alertes').html(j.lignes.length ? j.lignes.map(function (a) { return AL.carte(a, {}); }).join('') : '<div class="al-vide"><i class="fa-solid fa-circle-check"></i>Aucune alerte dans cette vue. Tout est à jour.</div>');
          AL.pagination('#pagination-alertes', j.total, j.page, j.par_page, ['alerte', 'alertes']);
        })
        .catch(function () { $('#liste-alertes').html('<div class="al-vide"><i class="fa-solid fa-triangle-exclamation"></i>Impossible de charger les alertes. Réessayez.</div>'); })
        .then(function () { if (numero === requete) { $('.au-fond').removeClass('is-charge'); } });
    }

    document.addEventListener('DOMContentLoaded', function () {
      charger();
      $('#filtres-etat').on('click', '.au-filtre-etat', function () { f.etat = this.getAttribute('data-e'); f.page = 1; charger(); });
      $('#f-chambre').on('change', function () { f.chambre = this.value; f.page = 1; charger(); });
      $('#f-type').on('change', function () { f.type = this.value; f.page = 1; charger(); });
      $('#f-niveau').on('change', function () { f.niveau = this.value; f.page = 1; charger(); });
      $('#f-q').on('input', function () { const v = this.value; clearTimeout(minuteur); minuteur = setTimeout(function () { f.q = v.trim(); f.page = 1; charger(); }, 300); });
      $('#pagination-alertes').on('click', 'button:not(:disabled)', function () { f.page = parseInt(this.getAttribute('data-p'), 10) || 1; charger(); });
      $('#f-reinit').on('click', function () { f.chambre = f.type = f.niveau = f.q = ''; f.page = 1; $('#f-chambre, #f-type, #f-niveau').val('').trigger('change.select2'); $('#f-q').val(''); charger(); });
      $('#liste-alertes').on('click', '[data-act="justifier"]', function () {
        const id = +this.getAttribute('data-alerte'), a = cache.find(function (x) { return x.id === id; });
        AL.justifier(id, a ? a.titre : '', a ? a.justification : '', a ? a.titre : '');
      });
      AL.apresJustification(function () { charger(); });
    });
  })();
  </script>
@endsection
