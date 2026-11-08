@extends('layout.master')
@section('title', "Rôles d'audience")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/roles.css') }}?v={{ filemtime(public_path('assets/css/roles.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page tp-anime">

      <x-hero tag="Audiences" title="Rôles d'audience" subtitle="Le rôle de chaque chambre se compose tout seul : affaires en délibéré, affaires anciennes, affaires nouvelles. Vous le relisez, le publiez et l'exportez en Word ou en PDF, sur le modèle de la greffière en chef." icon="fa-solid fa-gavel">
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('audiences.roles.planning') }}"><i class="fa-solid fa-calendar-week"></i> Planning des salles</a>
          @can('roles.creer')
            <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_role(null, null)"><i class="fa-solid fa-plus"></i> Nouveau rôle</button>
          @endcan
        </x-slot:actions>
      </x-hero>

      <div class="rl-kpis">
        <div class="rl-kpi rl-kpi--vert"><span class="rl-kpi__icone"><i class="fa-solid fa-calendar-check"></i></span><div><b>{{ $compte['avenir'] }}</b><h5>audience{{ $compte['avenir'] > 1 ? 's' : '' }} à venir</h5></div></div>
        <div class="rl-kpi rl-kpi--orange"><span class="rl-kpi__icone"><i class="fa-solid fa-pen-ruler"></i></span><div><b>{{ $compte['brouillons'] }}</b><h5>en préparation</h5></div></div>
        <div class="rl-kpi rl-kpi--bleu"><span class="rl-kpi__icone"><i class="fa-solid fa-circle-check"></i></span><div><b>{{ $compte['publies'] }}</b><h5>publié{{ $compte['publies'] > 1 ? 's' : '' }}</h5></div></div>
        <div class="rl-kpi {{ $compte['conflits'] ? 'rl-kpi--rouge' : 'rl-kpi--gris' }}"><span class="rl-kpi__icone"><i class="fa-solid fa-triangle-exclamation"></i></span><div><b>{{ $compte['conflits'] }}</b><h5>conflit{{ $compte['conflits'] > 1 ? 's' : '' }} de salle</h5></div></div>
      </div>

      @if ($aPreparer->isNotEmpty())
        <section class="rl-preparer" aria-label="Audiences à préparer">
          <div class="rl-preparer__titre"><i class="fa-solid fa-wand-magic-sparkles"></i> Audiences des 14 prochains jours sans rôle</div>
          <p class="rl-preparer__aide">Ces chambres siègent aux jours indiqués (d'après leur calendrier d'audience) et leur rôle n'est pas encore créé. Cliquez sur une pastille pour le créer ; elle disparaît dès que le rôle existe.</p>
          <div class="rl-preparer__liste mt-2">
            @foreach ($aPreparer as $p)
              <button type="button" class="rl-puce" onclick="ouvrir_role({{ $p['chambre']->id }}, '{{ $p['date']->toDateString() }}')">
                <span class="rl-puce__jour"><b>{{ $p['date']->format('d') }}</b>{{ $p['date']->copy()->locale('fr')->translatedFormat('M') }}</span>
                <span class="rl-puce__texte"><b>{{ $p['chambre']->libelle }}</b><small>{{ $p['date']->copy()->locale('fr')->translatedFormat('l') }}{{ $p['heure'] ? ' · '.str_replace(':', 'h', $p['heure']) : '' }}</small></span>
                <i class="fa-solid fa-plus"></i>
              </button>
            @endforeach
          </div>
        </section>
      @endif

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <a class="el-pill {{ ! $statut ? 'is-active' : '' }}" href="{{ route('audiences.roles', array_filter(['chambre' => $chambreFiltre])) }}">Tous</a>
            <a class="el-pill {{ $statut === 'brouillon' ? 'is-active' : '' }}" href="{{ route('audiences.roles', array_filter(['statut' => 'brouillon', 'chambre' => $chambreFiltre])) }}">En préparation</a>
            <a class="el-pill {{ $statut === 'publie' ? 'is-active' : '' }}" href="{{ route('audiences.roles', array_filter(['statut' => 'publie', 'chambre' => $chambreFiltre])) }}">Publiés</a>
          </div>
          <div class="rl-outils">
            <form method="get" action="{{ route('audiences.roles') }}" class="rl-filtre">
              @if ($statut)<input type="hidden" name="statut" value="{{ $statut }}">@endif
              <select class="form-select" name="chambre" aria-label="Chambre" onchange="this.form.submit()">
                <option value="">Toutes les chambres</option>
                @foreach ($chambres as $c)<option value="{{ $c->id }}" @selected($chambreFiltre === $c->id)>{{ $c->libelle }}{{ $c->intitule ? ' – '.$c->intitule : '' }}</option>@endforeach
              </select>
            </form>
            <label class="el-search" style="max-width:260px">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="search" id="el-search" placeholder="Chambre, salle, date…" autocomplete="off" aria-label="Rechercher">
            </label>
          </div>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-roles" data-serveur="{{ route('audiences.roles.donnees', array_filter(['statut' => $statut, 'chambre' => $chambreFiltre])) }}">
            <colgroup><col style="width:150px"><col><col style="width:250px"><col style="width:100px"><col style="width:160px"><col style="width:140px"></colgroup>
            <thead>
              <tr><th>Audience</th><th>Chambre</th><th>Salle et heure</th><th>Affaires</th><th>État</th><th>Actions</th></tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @can('roles.creer')
    @include('audiences.roles.form_role', ['chambres' => $chambres, 'salles' => $salles, 'peutPenal' => $peutPenal])
  @endcan
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-roles', { order: [[0, 'desc']], noSort: [1, 2, 3, 5], search: '#el-search' });
  </script>
  @can('roles.creer')
    @include('audiences.roles.js_script', ['creneaux' => $creneaux])
  @endcan
@endsection
