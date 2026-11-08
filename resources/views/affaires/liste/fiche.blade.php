@extends('layout.master')
@section('title', $affaire->numeroAffichage())

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/dossier-numerique.css') }}?v={{ filemtime(public_path('assets/css/dossier-numerique.css')) }}">
@endsection

@section('main-content')
  @php
    $def = \App\Support\RegistreAffaire::depuisSerie($affaire->serie);
    $icones = ['homme' => 'fa-person', 'femme' => 'fa-person-dress', 'couple' => 'fa-user-group', 'morale' => 'fa-building', 'etat' => 'fa-landmark', 'mp' => 'fa-scale-balanced'];
    // Deux côtés : à gauche ceux qui saisissent ou sont poursuivis, à droite les autres parties.
    $cotes = [
        ['roles' => ['demandeur', 'prevenu', 'inculpe'], 'couleur' => 'vert', 'vide' => 'Aucune partie de ce côté.'],
        ['roles' => ['defendeur', 'victime'], 'couleur' => 'orange', 'vide' => $affaire->serie === 'RG' ? 'Pas de défendeur (requête).' : 'Aucune victime ni partie civile.'],
    ];
  @endphp
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Affaires" :title="$affaire->numeroAffichage()" :subtitle="$affaire->intitule()" icon="fa-solid fa-folder-open">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-day"></i> Enregistrée le {{ $affaire->date_rg->format('d/m/Y') }}</span>
          <span><i class="fa-solid fa-circle-info"></i> {{ \App\Models\Affaire::STATUTS[$affaire->statut] ?? $affaire->statut }}</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('affaires.liste') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
          @unless ($affaire->estAnnulee())
            @can('affaires.modifier')
              <button class="el-btn el-btn--ghost" type="button" onclick="ouvrir_correction()" title="Corriger une erreur de saisie : objet, parties, date d'audience…"><i class="fa-solid fa-pen-to-square"></i> Corriger</button>
            @endcan
            @if ($affaire->serie === 'RG')
              @can('redaction.creer')
                <button class="el-btn el-btn--ghost" type="button" onclick="rediger_decision()"><i class="fa-solid fa-pen-nib"></i> Rédiger une décision</button>
              @endcan
            @endif
            @if ($affaire->numero_saisi && ! $affaire->jointe_a_id && $affaire->jointes->isEmpty())
              @can('affaires.corriger_numero')
                <button class="el-btn el-btn--ghost" type="button" onclick="corriger_numero()" title="Corriger une faute de frappe dans le numéro reporté du registre"><i class="fa-solid fa-hashtag"></i> Corriger le numéro</button>
              @endcan
            @endif
            @can('affaires.reattribuer')
              <button class="el-btn el-btn--ghost" type="button" onclick="reattribuer_affaire()" title="Envoyer l'affaire dans une autre chambre (décision de la Présidente)"><i class="fa-solid fa-right-left"></i> Réattribuer</button>
            @endcan
            @can('affaires.annuler')
              <button class="el-btn el-btn--danger" type="button" onclick="annuler_affaire()"><i class="fa-solid fa-ban"></i> Annuler l'affaire</button>
            @endcan
          @endunless
        </x-slot:actions>
      </x-hero>

      @if ($affaire->reprise_import_id)
        <div class="aff-info" style="margin-bottom:14px"><i class="fa-solid fa-file-import"></i><span>Affaire reprise d'un registre Excel{{ $affaire->ancien_numero ? ' (ancien numéro : '.$affaire->ancien_numero.')' : '' }}. La nature de chaque partie (homme, femme, société…) a été devinée d'après le nom : vérifiez-la et corrigez-la au besoin avec « Corriger ».</span></div>
      @endif

      @if ($affaire->numerosCorriges->isNotEmpty())
        <div class="aff-info" style="margin-bottom:14px"><i class="fa-solid fa-hashtag"></i><span>
          Numéro corrigé : @foreach ($affaire->numerosCorriges as $c)<b>{{ $c->ancien }}</b> devenu <b>{{ $c->nouveau }}</b> le {{ $c->created_at->format('d/m/Y') }} ({{ $c->motif }}){{ $loop->last ? '' : ' ; ' }}@endforeach
        </span></div>
      @endif

      @if ($affaire->estAnnulee())
        <div class="aff-bandeau-annulee">
          <b>Affaire annulée le {{ $affaire->annulee_le?->format('d/m/Y à H:i') }}.</b> Motif : {{ $affaire->motif_annulation }}.
          Le numéro {{ $affaire->numeroAffichage() }} reste attribué et ne sera pas réutilisé.
        </div>
      @endif

      @if ($dossier || $scelles !== null)
        <nav class="aff-onglets" role="tablist" aria-label="Sections de la fiche">
          <button type="button" role="tab" class="is-actif" aria-selected="true" data-onglet-bouton="infos" onclick="afficher_onglet('infos'); history.replaceState(null, '', location.pathname + location.search)"><i class="fa-solid fa-circle-info"></i> Informations</button>
          @if ($scelles !== null)
            <button type="button" role="tab" aria-selected="false" data-onglet-bouton="scelles" onclick="afficher_onglet('scelles'); history.replaceState(null, '', '#scelles')"><i class="fa-solid fa-box-archive"></i> Scellés <span class="aff-onglets__n">{{ $scelles->count() }}</span></button>
          @endif
          @if ($dossier)
          <button type="button" role="tab" aria-selected="false" data-onglet-bouton="dossier" onclick="afficher_onglet('dossier'); history.replaceState(null, '', '#dossier')"><i class="fa-solid fa-folder-open"></i> Dossier numérique <span class="aff-onglets__n">{{ count($dossier['pieces']) }}</span></button>
          @endif
        </nav>
      @endif

      <div data-onglet-contenu="infos">
      {{-- Carte d'identité de l'affaire --}}
      <div class="el-card aff-carte aff-identite">
        <div class="aff-identite__haut">
          <div class="aff-identite__numero">
            <div class="aff-identite__puce"><i class="fa-solid {{ $def['icone'] }}"></i></div>
            <div>
              <div class="aff-fiche__etiquette">{{ $def['libelle'] }}</div>
              <div class="aff-numero">{{ $affaire->numeroAffichage() }}</div>
            </div>
          </div>
          <div class="aff-identite__etat">
            <x-chip tone="info" :label="$affaire->serie" />
            <x-chip :tone="$affaire->estAnnulee() ? 'danger' : 'success'" :label="\App\Models\Affaire::STATUTS[$affaire->statut] ?? $affaire->statut" />
            @if ($affaire->parties->contains(fn ($p) => $p->estDetenu()))
              <x-chip tone="danger" label="Détenu" />
            @endif
          </div>
        </div>

        <div class="aff-objet">
          <i class="fa-solid fa-quote-left"></i>
          <div>
            <div class="aff-fiche__etiquette">{{ $def['objet'] }}</div>
            <div class="aff-objet__texte">{{ $affaire->objet }}</div>
            @if ($affaire->articles)
              <div class="aff-objet__articles"><b>Articles visés :</b> {{ $affaire->articles }}</div>
            @endif
            @if ($affaire->numero_parquet)
              <div class="aff-objet__articles"><b>N° du parquet :</b> {{ $affaire->numero_parquet }}</div>
            @endif
          </div>
        </div>

        <div class="aff-tuiles">
          <div class="aff-tuile">
            <div class="aff-tuile__icone">
              <i class="fa-regular fa-calendar"></i>
            </div>
            <div class="aff-tuile__texte">
              <span>Date d'enreg.</span>
              <b>{{ $affaire->date_rg->format('d/m/Y') }}</b>
            </div>
          </div>
          <div class="aff-tuile"><div class="aff-tuile__icone"><i class="fa-solid fa-inbox"></i></div><div class="aff-tuile__texte"><span>Mode de saisine</span><b>{{ $affaire->modeSaisine?->libelle ?? '—' }}</b></div></div>
          <div class="aff-tuile"><div class="aff-tuile__icone"><i class="fa-solid fa-layer-group"></i></div><div class="aff-tuile__texte"><span>Nature de l'affaire</span><b>{{ $affaire->nature?->libelle ?? '—' }}</b></div></div>
          <div class="aff-tuile"><div class="aff-tuile__icone"><i class="fa-solid fa-book"></i></div><div class="aff-tuile__texte"><span>Matière</span><b>{{ $affaire->matiere?->libelle ?? '—' }}</b></div></div>
          <div class="aff-tuile"><div class="aff-tuile__icone"><i class="fa-solid fa-building-columns"></i></div><div class="aff-tuile__texte"><span>{{ $affaire->serie === 'RG' || $affaire->serie === 'RP' ? 'Chambre' : 'Cabinet' }}</span><b>{{ $affaire->chambre?->libelle ?? '—' }}</b>@if ($affaire->chambre?->intitule)<small>{{ $affaire->chambre->intitule }}</small>@endif</div></div>
          <div class="aff-tuile"><div class="aff-tuile__icone"><i class="fa-regular fa-clock"></i></div><div class="aff-tuile__texte"><span>Première audience</span><b>{{ $affaire->date_premiere_audience?->format('d/m/Y') ?? 'Non fixée' }}@if ($affaire->heureAudience()) à {{ $affaire->heureAudience() }}@endif</b></div></div>
          <div class="aff-tuile"><div class="aff-tuile__icone"><i class="fa-regular fa-user"></i></div><div class="aff-tuile__texte"><span>Saisie par</span><b>{{ $affaire->auteur?->name ?? '—' }}</b></div></div>
        </div>

        @if ($affaire->derogation_motif)
          <div class="aff-derogation-fiche"><i class="fa-solid fa-triangle-exclamation"></i> <b>Dérogation à la date limite d'enrôlement</b> accordée par {{ $affaire->derogant?->name ?? '—' }} : {{ $affaire->derogation_motif }}</div>
        @endif
      </div>

      {{-- Les parties, côte à côte --}}
      <div class="aff-deux">
        @foreach ($cotes as $cote)
          <section class="aff-cote aff-cote--{{ $cote['couleur'] }}">
            @php $present = false; @endphp
            @foreach ($cote['roles'] as $role)
              @php $liste = $affaire->parties->where('role', $role); @endphp
              @if ($liste->isNotEmpty())
                @php $present = true; @endphp
                <header class="aff-cote__tete"><h2>{{ \App\Models\Partie::ROLES_PLURIEL[$role] }}</h2><span class="aff-cote__nombre">{{ $liste->count() }}</span></header>
                @foreach ($liste as $p)
                  <article class="aff-personne">
                    <div class="aff-personne__avatar"><i class="fa-solid {{ $icones[$p->nature] ?? 'fa-user' }}"></i></div>
                    <div class="aff-personne__corps">
                      <div class="aff-personne__nom"><b>{{ $p->libelle() }}</b> <span class="el-muted">({{ \App\Models\Partie::NATURES[$p->nature] ?? $p->nature }})</span></div>
                      @if ($p->estPersonnePhysique() && ($p->naissance() || $p->lieu_naissance))
                        <div class="aff-personne__ligne"><i class="fa-solid fa-cake-candles"></i> {{ $p->naissance() ? 'Né'.($p->nature === 'femme' ? 'e' : '').' '.$p->naissance() : '' }}{{ $p->lieu_naissance ? ' à '.$p->lieu_naissance : '' }}</div>
                      @endif
                      @if ($p->estPersonnePhysique() && ($p->nationalite || $p->profession))
                        <div class="aff-personne__ligne"><i class="fa-solid fa-id-card"></i> {{ $p->nationalite ? 'de nationalité '.$p->nationalite : '' }}{{ $p->nationalite && $p->profession ? ' · ' : '' }}{{ $p->profession }}</div>
                      @endif
                      @if ($p->domicile) <div class="aff-personne__ligne"><i class="fa-solid fa-house"></i> Domicile ou siège : {{ $p->domicile }}</div> @endif
                      @if ($p->avocat) <div class="aff-personne__ligne"><i class="fa-solid fa-user-tie"></i> Avocat : {{ $p->avocat }}</div> @endif
                      @if ($p->estDetenu())
                        <div class="aff-personne__ligne aff-detenu aff-detenu--{{ $p->niveauDetention() }}"><i class="fa-solid fa-lock"></i> Détenu : mandat de dépôt du {{ $p->mandat_depot_date->format('d/m/Y') }}{{ $p->lieu_detention ? ' · '.$p->lieu_detention : '' }}</div>
                        <div class="aff-personne__ligne aff-detenu aff-detenu--{{ $p->niveauDetention() }}"><i class="fa-solid fa-hourglass-half"></i> Échéance {{ $p->echeanceCalculee() ? '(calculée) ' : '' }}: {{ $p->echeance()->format('d/m/Y') }} — {{ $p->joursRestants() < 0 ? 'dépassée de '.abs($p->joursRestants()).' jour(s)' : ($p->joursRestants() === 0 ? "aujourd'hui" : 'dans '.$p->joursRestants().' jour(s)') }}</div>
                      @elseif ($p->libere_le)
                        <div class="aff-personne__ligne"><i class="fa-solid fa-lock-open"></i> Mis en liberté le {{ $p->libere_le->format('d/m/Y') }}</div>
                      @endif
                    </div>
                  </article>
                @endforeach
              @endif
            @endforeach
            @unless ($present)
              <div class="aff-cote__vide"><i class="fa-regular fa-circle"></i> {{ $cote['vide'] }}</div>
            @endunless
          </section>
        @endforeach
      </div>

      {{-- Jonction des causes et réattributions, côte à côte --}}
      @php
        $jonction = $affaire->principale || $affaire->jointes->isNotEmpty() || ($joignables->isNotEmpty() && ! $affaire->estAnnulee());
      @endphp
      @if ($jonction || $affaire->attributions->isNotEmpty())
        <div class="aff-deux">
          @if ($jonction)
            <div class="el-card aff-carte">
              <div class="el-card__head">
                <div><h2 class="el-card__title">Jonction des causes</h2><div class="el-card__sub">Affaires jugées ensemble, chacune gardant son numéro.</div></div>
                @unless ($affaire->estAnnulee())
                  @can('affaires.modifier')
                    @if ($affaire->principale)
                      <form action="{{ route('affaires.liste.disjoindre', $affaire->id) }}" method="post">@csrf<button class="el-btn el-btn--ghost" type="submit"><i class="fa-solid fa-link-slash"></i> Disjoindre</button></form>
                    @elseif ($joignables->isNotEmpty())
                      <button class="el-btn el-btn--ghost" type="button" onclick="joindre_affaire()"><i class="fa-solid fa-link"></i> Joindre une affaire</button>
                    @endif
                  @endcan
                @endunless
              </div>
              <div class="el-card__body">
                @if ($affaire->principale)
                  <div class="aff-partie-fiche">Jointe à l'affaire principale{{ $affaire->meme_numero ? ' (même numéro RG)' : '' }} <a href="{{ route('affaires.liste.fiche', $affaire->principale->id) }}"><b>{{ $affaire->principale->numeroAffichage() }}</b></a></div>
                @endif
                @foreach ($affaire->jointes as $j)
                  <div class="aff-partie-fiche"><a href="{{ route('affaires.liste.fiche', $j->id) }}"><b>{{ $j->numeroAffichage() }}</b></a> — {{ $j->intitule() }}<small>{{ $j->objet }}</small></div>
                @endforeach
                @if (! $affaire->principale && $affaire->jointes->isEmpty())
                  <p class="el-muted">Aucune affaire jointe.</p>
                @endif
              </div>
            </div>
          @endif

          @if ($affaire->attributions->isNotEmpty())
            <div class="el-card aff-carte">
              <div class="el-card__head"><div><h2 class="el-card__title">Réattributions</h2><div class="el-card__sub">Changements de chambre décidés par la Présidente.</div></div></div>
              <div class="el-card__body">
                @foreach ($affaire->attributions as $a)
                  <div class="aff-partie-fiche">
                    <b>{{ $a->avant?->libelle ?? '—' }} → {{ $a->apres?->libelle ?? '—' }}</b>
                    <small>{{ $a->created_at->format('d/m/Y à H:i') }} · {{ $a->auteur?->name ?? '—' }} · {{ $a->motif }}</small>
                  </div>
                @endforeach
              </div>
            </div>
          @endif
        </div>
      @endif

      </div>

      @if ($scelles !== null)
        <div data-onglet-contenu="scelles" hidden>
          @include('affaires.scelles.panneau', ['scelles' => $scelles])
        </div>
      @endif

      @if ($dossier)
        <div data-onglet-contenu="dossier" hidden>
          @include('affaires.dossier.panneau', ['dossier' => $dossier])
        </div>
      @endif

    </div>
  </div>

  @if ($dossier)
    @can('dossiers.creer') @unless ($affaire->estAnnulee()) @include('affaires.dossier.form_pieces', ['dossier' => $dossier]) @endunless @endcan
    @can('dossiers.modifier') @include('affaires.dossier.form_piece') @include('affaires.dossier.form_localisation') @endcan
    @can('dossiers.supprimer') @include('affaires.dossier.form_retrait') @endcan
    @include('affaires.dossier.js_script', ['dossier' => $dossier])
  @endif

  @if ($scelles !== null) @can('scelles.creer') @include('affaires.scelles.form_scelle') @endcan @endif

  @unless ($affaire->estAnnulee())
    @can('affaires.annuler') @include('affaires.liste.form_annuler') @endcan
    @if ($affaire->numero_saisi && ! $affaire->jointe_a_id && $affaire->jointes->isEmpty()) @can('affaires.corriger_numero') @include('affaires.liste.form_numero') @endcan @endif
    @can('affaires.reattribuer') @include('affaires.liste.form_reattribuer') @endcan
    @can('affaires.modifier') @if ($joignables->isNotEmpty() && ! $affaire->principale) @include('affaires.liste.form_joindre') @endif @endcan
    @if ($affaire->serie === 'RG') @can('redaction.creer') @include('affaires.liste.form_rediger') @endcan @endif
    @if ($correction)
      @include('affaires.liste.form_correction', ['correction' => $correction])
      @include($correction['def']['cle'] === 'civil' ? 'affaires.enrolement.js_script' : 'affaires.enrolement.js_script_poursuite', ['ouvrirAuto' => false])
    @endif
  @endunless
  @include('affaires.liste.js_script')
@endsection
