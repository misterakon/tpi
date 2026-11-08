@extends('layout.master')
@section('title', 'Analyse et pilotage')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pilotage.css') }}?v={{ filemtime(public_path('assets/css/pilotage.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page al-page pi-page">

      <x-hero tag="Audiences" title="Analyse et pilotage" subtitle="La vue d'ensemble du tribunal : activité, délais, retards, alertes, avec l'analyse et les recommandations. Un indice de santé résume la situation ; l'analyse automatique explique ce qui le fait baisser, l'IA peut rédiger une note plus fine sur demande." icon="fa-solid fa-chart-line">
        <x-slot:actions>
          <div class="au-onglets" id="periodes" role="tablist" data-no-print>
            @foreach ($periodes as $cle => $libelle)<button type="button" role="tab" data-periode="{{ $cle }}" class="{{ $cle === '30j' ? 'is-actif' : '' }}">{{ $libelle }}</button>@endforeach
          </div>
          <a class="el-btn el-btn--ghost" id="btn-pdf" href="{{ route('audiences.pilotage.pdf') }}" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> Note PDF</a>
        </x-slot:actions>
      </x-hero>

      <div class="pi-loader" id="pi-loader" role="status" aria-live="polite" hidden>
        <div class="pi-loader__carte">
          <div class="pi-loader__anneau" aria-hidden="true"><i></i><i></i><i></i><span class="fa-solid fa-scale-balanced"></span></div>
          <b>Analyse en cours</b><small>Calcul des indicateurs du tribunal…</small>
          <div class="pi-loader__barre" aria-hidden="true"><i></i></div>
        </div>
      </div>

      <div class="pi-bandeau" id="bandeau"><span><i class="fa-solid fa-spinner fa-spin"></i> Calcul en cours…</span></div>

      <div class="pi-haut">
        <section class="pi-sante pi-sante--bon" id="sante" aria-label="Indice de santé"></section>
        <div class="pi-haut__droite">
        <div class="pi-kpis" id="kpis"></div>

      <div class="pi-graphiques">
        <section class="pi-carte">
          <div class="pi-carte__tete">
            <div><h3 id="titre-graphe">Affaires enrôlées et terminées</h3><small id="sous-graphe">Affaires enregistrées au tribunal et affaires jugées, radiées, jointes ou désistées, mois par mois</small></div>
            <div class="au-onglets" id="onglets-graphe" data-no-print>
              <button type="button" class="is-actif" data-g="flux">Enrôlées et terminées</button><button type="button" data-g="chambres">Par chambre</button>
            </div>
          </div>
          <div class="pi-graphe" id="graphe"></div>
        </section>
        <section class="pi-carte">
          <div class="pi-carte__tete">
            <div><h3 id="titre-donut">Suites notées</h3><small id="sous-donut">Ce qui s'est passé aux audiences de la période</small></div>
            <div class="au-onglets" id="onglets-donut" data-no-print>
              <button type="button" class="is-actif" data-d="suites">Suites</button><button type="button" data-d="alertes">Alertes</button>
            </div>
          </div>
          <div class="pi-graphe" id="donut"></div>
        </section>
      </div>
        </div>
      </div>

      {{-- Analyse et recommandations : automatique (toujours) et IA (sur demande). --}}
      <div class="au-cadre pi-analyse">
        <div class="pi-analyse__tete">
          <div><h3>Analyse et recommandations</h3><small id="sous-analyse">Rédigées d'après les chiffres ci-dessus.</small></div>
          <div class="au-onglets" id="onglets-analyse" data-no-print>
            <button type="button" class="is-actif" data-a="auto"><i class="fa-solid fa-gears"></i> Analyse automatique</button>
            <button type="button" data-a="ia"><i class="fa-solid fa-wand-magic-sparkles"></i> Analyse par l'IA</button>
          </div>
        </div>
        <div id="analyse-auto"></div>
        <div id="analyse-ia" hidden></div>
      </div>

      {{-- La situation de chaque chambre : la liste est coupée en deux blocs côte à côte (elle serait trop longue en un seul). --}}
      <div class="pi-carte__tete" style="padding:0 4px"><div><h3 style="margin:0;font:700 16px var(--police-app)">Situation par chambre</h3><small>Cliquez sur un titre de colonne pour trier. Survolez un titre pour sa définition.</small></div></div>
      <div class="pi-duo">
        <div class="au-cadre">
          <div class="au-fond"><div class="au-tbl-carte">
            <table class="au-tbl pi-tbl" id="tbl-a">
              
              <thead><tr><th data-tri="libelle">Chambre</th><th data-tri="stock" title="Affaires en cours aujourd'hui">En cours</th><th data-tri="entrees" title="Affaires enregistrées au tribunal sur la période">Enrôlées</th><th data-tri="sorties" title="Affaires jugées, radiées, jointes ou désistées">Terminées</th><th data-tri="taux_sortie" title="Affaires terminées pour 100 enrôlées">Taux</th><th data-tri="delai_moyen" title="De l'enrôlement à la fin de l'affaire">Délai</th><th data-tri="retards" title="Échéance dépassée">En retard</th><th data-tri="a_rediger" title="Affaires jugées sans décision validée">À rédiger</th><th data-tri="renvois_pct" title="Part des renvois parmi les suites notées">Renvois</th><th data-tri="alertes" title="Alertes ouvertes">Alertes</th></tr></thead>
              <tbody id="corps-a"></tbody>
            </table>
          </div></div>
        </div>
        <div class="au-cadre">
          <div class="au-fond"><div class="au-tbl-carte">
            <table class="au-tbl pi-tbl" id="tbl-b">
              
              <thead><tr><th data-tri="libelle">Chambre</th><th data-tri="stock" title="Affaires en cours aujourd'hui">En cours</th><th data-tri="entrees" title="Affaires enregistrées au tribunal sur la période">Enrôlées</th><th data-tri="sorties" title="Affaires jugées, radiées, jointes ou désistées">Terminées</th><th data-tri="taux_sortie" title="Affaires terminées pour 100 enrôlées">Taux</th><th data-tri="delai_moyen" title="De l'enrôlement à la fin de l'affaire">Délai</th><th data-tri="retards" title="Échéance dépassée">En retard</th><th data-tri="a_rediger" title="Affaires jugées sans décision validée">À rédiger</th><th data-tri="renvois_pct" title="Part des renvois parmi les suites notées">Renvois</th><th data-tri="alertes" title="Alertes ouvertes">Alertes</th></tr></thead>
              <tbody id="corps-b"></tbody>
            </table>
          </div></div>
        </div>
      </div>

      <div class="au-cadre">
        <div class="pi-carte__tete">
          <div><h3 style="margin:0;font:700 16px var(--police-app)">Alertes à traiter en priorité</h3><small>Les plus graves d'abord. Le détail et la justification d'un retard sont dans le suivi des alertes.</small></div>
          @can('alertes.voir')<a class="au-btn au-btn--clair" href="{{ route('alertes.suivi') }}" data-no-print><i class="fa-solid fa-bell-concierge"></i>Suivi des alertes</a>@endcan
        </div>
        <div class="pi-alertes-liste" id="alertes"></div>
      </div>

    </div>
  </div>
  <div class="modal fade panel-modal" id="modal-indice" tabindex="-1" role="dialog" aria-labelledby="titre-modal-indice" aria-hidden="true" data-keyboard="false">
    <div class="modal-dialog" style="max-width:760px;width:94%">
      <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title" id="titre-modal-indice">Comment est calculé l'indice de santé ?</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body dark-modal" id="corps-indice" style="max-height:70vh;overflow-y:auto"></div>
        <div class="modal-footer"><button class="btn btn-primary text-white" type="button" data-bs-dismiss="modal">Compris</button></div>
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
  @include('audiences.pilotage.js_script')
@endsection
