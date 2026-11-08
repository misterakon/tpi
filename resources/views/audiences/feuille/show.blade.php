@extends('layout.master')
@section('title', "Feuille d'audience")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  @php $jour = ucfirst($role->date_audience->copy()->locale('fr')->translatedFormat('l d F Y')); @endphp
  <div class="page-body">
    <div class="el-page au-page tp-anime">

      <x-hero tag="Feuille d'audience" :title="$role->chambre->libelle.' · '.$jour" :subtitle="($role->heureCourte() ? 'À '.$role->heureCourte() : 'Heure à fixer').' — '.($role->salle?->nom ?? 'salle à fixer').'. '.$role->typeLibelle().'.'" icon="fa-solid fa-clipboard-list">
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('audiences.feuille') }}"><i class="fa-solid fa-arrow-left"></i> Les audiences</a>
          @can('roles.voir')<a class="el-btn el-btn--ghost" href="{{ route('audiences.roles.show', $role->id) }}"><i class="fa-solid fa-list-ol"></i> Le rôle</a>@endcan
          @if ($peutModifier)
            <button class="el-btn el-btn--accent" type="button" id="btn-terminer" hidden><i class="fa-solid fa-flag-checkered"></i> Terminer l'audience</button>
            <button class="el-btn el-btn--ghost" type="button" id="btn-rouvrir" hidden><i class="fa-solid fa-lock-open"></i> Rouvrir pour corriger</button>
          @endif
        </x-slot:actions>
      </x-hero>

      <div class="au-suivi" id="suivi">
        <div class="au-suivi__avance">
          <div><b id="suivi-n">0 / 0</b><span>affaires traitées</span></div>
          <div class="au-progres mt-2" role="progressbar" aria-label="Avancement de l'audience"><i id="suivi-barre"></i></div>
        </div>
        <div class="au-suivi__compteurs" id="compteurs"></div>
        <div class="au-etat-audience"><i class="au-point-etat" id="point-etat"></i><span id="texte-etat"></span></div>
      </div>

      <div class="au-feuille">
        <div id="lignes"></div>

        <aside class="au-aside">
          <div class="au-panel">
            <div class="au-tete" style="margin-bottom:12px"><div><h5>Composition</h5><small>Reprise de la formation ; notez les empêchements</small></div></div>
            <div class="au-comp" id="composition"></div>
          </div>
          <div class="au-panel">
            <div class="au-tete" style="margin-bottom:10px"><div><h5>Observations</h5><small>Facultatif, enregistré à la clôture</small></div></div>
            <textarea class="au-note-audience" id="note-audience" maxlength="1000" placeholder="Incident d'audience, remarque générale…" @disabled(! $peutModifier)>{{ $role->note_audience }}</textarea>
          </div>
          <div class="au-conseil"><i class="fa-solid fa-lightbulb"></i><span><b>Une affaire sans suite</b> n'est pas perdue : elle reviendra parmi les anciennes du prochain rôle. Mais notez-la pour garder l'historique des renvois.</span></div>
        </aside>
      </div>

    </div>
  </div>
  <datalist id="dl-motifs">@foreach (\App\Models\RoleLigne::MOTIFS_RENVOI as $m)<option value="{{ $m }}">@endforeach</datalist>
@endsection

@section('script')
  @include('audiences.feuille.js_script', ['role' => $role, 'lignes' => $lignes, 'composition' => $composition, 'resume' => $resume, 'peutModifier' => $peutModifier])
@endsection
