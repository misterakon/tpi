@extends('layout.master')
@section('title', 'Statistiques civiles')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Statistiques" title="Statistiques civiles" subtitle="Stock, affaires nouvelles, à juger et vidées par catégorie, comme dans les classeurs du tribunal. Chaque chiffre se calcule tout seul à partir des affaires et des décisions : aucune ressaisie." icon="fa-solid fa-chart-line">
        <x-slot:extra>
          <span id="st-resume"><i class="fa-solid fa-spinner fa-spin"></i> Calcul en cours…</span>
        </x-slot:extra>
        @if ($peutExporter)
          <x-slot:actions>
            @can('stats_registres.voir')<a class="el-btn" href="{{ route('statistiques.registres') }}"><i class="fa-solid fa-book-open"></i> Registres du tribunal</a>@endcan
            <a class="el-btn el-btn--accent" id="st-xlsx" href="#"><i class="fa-solid fa-file-excel"></i> Excel</a>
            <a class="el-btn st-btn-pdf" id="st-pdf" href="#"><i class="fa-solid fa-file-pdf"></i> PDF</a>
          </x-slot:actions>
        @endif
      </x-hero>

      <div class="el-card">
        <form class="st-filtres" id="st-filtres" onsubmit="return false">
          <div class="st-champ"><label for="f-depuis">De l'année</label><select class="form-select" id="f-depuis"></select></div>
          <div class="st-champ"><label for="f-jusqua">À l'année</label><select class="form-select" id="f-jusqua"></select></div>
          <div class="st-champ"><label for="f-mois">Année du détail mensuel</label><select class="form-select" id="f-mois"></select></div>
          <div class="st-champ"><label for="f-chambre">Chambre</label>
            <select class="form-select" id="f-chambre"><option value="">Toutes les chambres</option>@foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select>
          </div>
        </form>
      </div>

      <div class="st-tuiles" id="st-tuiles"><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div><div class="st-squelette"></div></div>

      <div class="st-onglets" role="tablist" id="st-onglets">
        <button type="button" class="st-onglet is-actif" data-panneau="vue">Vue d'ensemble</button>
        <button type="button" class="st-onglet" data-panneau="annuel">Récapitulatif annuel</button>
        <button type="button" class="st-onglet" data-panneau="mensuel">Détail mensuel</button>
        <button type="button" class="st-onglet" data-panneau="etat_civil">État civil</button>
        <button type="button" class="st-onglet" data-panneau="matrimonial">Matrimonial et adoption</button>
        <button type="button" class="st-onglet" data-panneau="durees">Durées</button>
        <button type="button" class="st-onglet" data-panneau="qualifier">À compléter <span class="st-pastille" id="st-nb-qualifier" hidden></span></button>
      </div>

      <section class="st-panneau" id="p-vue">
        <div class="st-grille">
          <div class="st-carte"><h3>Nouvelles et vidées par année <small>le stock suit la ligne verte</small></h3><div id="g-annees"></div></div>
          <div class="st-carte"><h3>Stock par catégorie <small id="g-stock-annee"></small></h3><div id="g-categories"></div></div>
        </div>
        <div class="st-carte" style="margin-top:14px"><h3>Mois de <span id="g-mois-annee"></span> <small>nouvelles, vidées et stock en fin de mois</small></h3><div id="g-mois"></div></div>
      </section>
      <section class="st-panneau" id="p-annuel" hidden></section>
      <section class="st-panneau" id="p-mensuel" hidden></section>
      <section class="st-panneau" id="p-etat_civil" hidden></section>
      <section class="st-panneau" id="p-matrimonial" hidden></section>
      <section class="st-panneau" id="p-durees" hidden></section>
      <section class="st-panneau" id="p-qualifier" hidden></section>

    </div>
  </div>

  {{-- Les affaires derrière un chiffre --}}
  <div class="modal fade panel-modal" id="modal-detail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header"><h4 class="modal-title" id="t-detail">Affaires</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <div class="modal-body dark-modal" id="c-detail"></div>
    </div></div>
  </div>

  @if ($peutQualifier)
    <div class="modal fade panel-modal" id="modal-qualifier" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
      <div class="modal-dialog aff-modal"><div class="modal-content">
        <div class="modal-header"><h4 class="modal-title" id="t-qualifier">Préciser l'affaire</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <form id="f-qualifier" autocomplete="off">
          <input type="hidden" name="affaire_id" id="q_affaire">
          <div class="modal-body dark-modal"><div class="el-formgrid">
            <div class="el-c6"><label class="form-label"><b>Demande</b></label><select class="form-select" name="resultat" id="q_resultat" data-sans-recherche><option value="">Non précisé</option>@foreach ($resultats as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
            <div class="el-c6"><label class="form-label"><b>Sous-catégorie</b></label>
              <select class="form-select" name="sous_categorie" id="q_sous"><option value="">Déduite de l'objet</option>
                <optgroup label="État civil">@foreach ($sousCategories['etat_civil'] as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</optgroup>
                <optgroup label="Matrimonial et adoption">@foreach ($sousCategories['matrimoniale'] as $c => $l)@if ($c !== 'autre')<option value="{{ $c }}">{{ $l }}</option>@endif @endforeach</optgroup>
              </select></div>
            <div class="el-c6"><label class="form-label"><b>Filles adoptées</b></label><input class="form-control" type="number" min="0" max="50" name="adoption_filles" id="q_filles"></div>
            <div class="el-c6"><label class="form-label"><b>Garçons adoptés</b></label><input class="form-control" type="number" min="0" max="50" name="adoption_garcons" id="q_garcons"></div>
            <div class="el-c6"><label class="form-label"><b>Caractère de la décision</b></label><select class="form-select" name="caractere" id="q_caractere" data-sans-recherche><option value="">Non précisé</option>@foreach ($caracteres as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
            <div class="el-c6"><label class="form-label"><b>Observations</b></label><input class="form-control" type="text" name="observations" id="q_obs" maxlength="300"></div>
            <div class="el-c6"><label class="form-label"><b>Adoptant ivoirien</b></label><select class="form-select" name="adoptant_ivoirien" id="q_aiv" data-sans-recherche><option value="">Sans objet</option>@foreach ($adoptants as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
            <div class="el-c6"><label class="form-label"><b>Adoptant étranger</b></label><select class="form-select" name="adoptant_etranger" id="q_aet" data-sans-recherche><option value="">Sans objet</option>@foreach ($adoptants as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
            <div class="el-c12"><div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>Sortie saisie à la main, seulement si l'application ne la connaît pas (par exemple un PV de conciliation totale). Sinon, laissez vide : elle vient de la décision ou de la feuille d'audience.</span></div></div>
            <div class="el-c6"><label class="form-label"><b>Type de sortie</b></label><select class="form-select" name="type_sortie" id="q_type" data-sans-recherche><option value="">Automatique</option>@foreach ($sorties as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
            <div class="el-c6"><label class="form-label"><b>Date de sortie</b></label><input class="form-control" type="date" name="date_sortie" id="q_date" max="{{ date('Y-m-d') }}"></div>
            <div class="el-c12"><div class="aff-erreur" id="q_erreur" hidden></div></div>
          </div></div>
          <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">Enregistrer</button></div>
        </form>
      </div></div>
    </div>
  @endif
@endsection

@section('script')
  <script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
  @include('statistiques.civil.js_script')
@endsection
