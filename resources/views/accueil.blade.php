@extends('layout.master')
@section('title', 'Tableau de bord')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pilotage.css') }}?v={{ filemtime(public_path('assets/css/pilotage.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/accueil.css') }}?v={{ filemtime(public_path('assets/css/accueil.css')) }}">
@endsection

{{-- Porte d'entrée de l'application. Chaque bloc n'apparaît que si l'utilisateur y a droit ; les chiffres suivent son périmètre (voir DonneesAccueil). --}}
@section('main-content')
  <div class="page-body">
    <div class="el-page au-page pi-page ac-page tp-anime">

      <div class="pi-bandeau" id="ac-bandeau"><span><i class="fa-solid fa-spinner fa-spin"></i> Chargement de vos chiffres…</span></div>

      <div class="ac-tuiles" id="ac-kpis">
        @for ($i = 0; $i < 4; $i++)<div class="ac-tuile ac-tuile--attente"><i class="ac-sq" style="width:55%"></i><i class="ac-sq" style="width:30%;height:26px"></i><i class="ac-sq" style="width:70%"></i></div>@endfor
      </div>

      <div class="ac-ligne ac-ligne--a" id="ac-ligne-a">
        <section class="pi-carte" id="ac-bloc-courbe" hidden>
          <div class="pi-carte__tete">
            <div><h3>Affaires enrôlées et terminées</h3><small>Mois par mois, dans votre périmètre</small></div>
            <div class="au-onglets" id="ac-mois" role="tablist" data-no-print>
              <button type="button" role="tab" data-mois="6">6 mois</button><button type="button" role="tab" data-mois="12" class="is-actif">12 mois</button>
            </div>
          </div>
          <div class="pi-graphe" id="ac-courbe"></div>
        </section>

        <section class="pi-carte ac-audiences" id="ac-bloc-audiences" hidden>
          <div class="pi-carte__tete"><div><h3>Prochaines audiences</h3><small id="ac-sous-audiences">Les rôles publiés</small></div><a class="ac-lien" id="ac-lien-roles" href="#">Tous les rôles</a></div>
          <div class="ac-defile" id="ac-audiences"></div>
        </section>
      </div>

      <div class="ac-ligne ac-ligne--b" id="ac-ligne-b">
        <section class="pi-carte ac-suites" id="ac-bloc-suites" hidden>
          <div class="pi-carte__tete"><div><h3>Suites notées</h3><small>Aux audiences, sur 30 jours</small></div></div>
          <div class="ac-suites__corps"><div id="ac-donut" class="ac-suites__anneau"></div><ul class="ac-suites__liste" id="ac-suites-liste"></ul></div>
        </section>

        <section class="pi-carte" id="ac-bloc-chambres" hidden>
          <div class="pi-carte__tete"><div><h3>Affaires en cours par chambre</h3><small>Dont en retard (en rouge)</small></div></div>
          <div class="pi-graphe" id="ac-barres"></div>
        </section>

        <section class="pi-carte ac-priorites" id="ac-bloc-retards" hidden>
          <div class="pi-carte__tete"><div><h3>À traiter en priorité</h3><small id="ac-sous-retards">Retards et échéances proches</small></div><a class="ac-lien" id="ac-lien-alertes" href="#">Suivi des alertes</a></div>
          <div class="ac-defil" id="ac-defil"><div class="ac-defil__piste" id="ac-retards" aria-live="off"></div></div>
        </section>
      </div>

      <section class="au-cadre" id="ac-bloc-recentes" hidden>
        <div class="ac-cadre__tete"><div><h3>Affaires récentes</h3><small>Les dernières affaires enrôlées</small></div><a class="ac-lien" id="ac-lien-affaires" href="#">Toutes les affaires</a></div>
        <div class="au-fond"><div class="ac-cartes" id="ac-recentes"></div></div>
      </section>

      <p class="ac-pied" id="ac-pied"></p>
    </div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/transitions.js') }}?v={{ filemtime(public_path('assets/js/transitions.js')) }}"></script>
  <script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
  @include('accueil_script')
@endsection
