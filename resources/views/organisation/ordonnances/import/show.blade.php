@extends('layout.master')
@section('title', 'Contrôle du fichier')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/import-ordonnance.css') }}?v={{ filemtime(public_path('assets/css/import-ordonnance.css')) }}">
@endsection

@section('main-content')
  @php
    $k = $import->controle ?? [];
    $erreurs = collect($k['erreurs'] ?? []);
    $avert = collect($k['avertissements'] ?? []);
    $noms = collect($k['noms'] ?? []);
    $comptes = $k['comptes'] ?? [];
    $d = $import->donnees ?? [];

    // Étape en cours : 2 contrôle (erreurs), 3 noms à confirmer, 4 comparaison (prête).
    $etape = ! $import->enCours() ? 6 : ($erreurs->isNotEmpty() ? 2 : ($noms->isNotEmpty() ? 3 : 4));
    $annulable = \App\Support\Organisation\Import\Applicateur::annulable($import);
    $bilan = $import->changements ?? [];
    $motsBloc = \App\Support\Organisation\Import\Comparateur::BLOCS;
    $etapes = [['Charger', 'Fichier déposé'], ['Contrôler', 'Erreurs et avertissements'], ['Rapprocher', 'Noms à confirmer'], ['Comparer', 'Ce qui va changer'], ['Appliquer', 'Enregistrer']];

    $blocs = ['sections' => 'Sections', 'salles' => 'Salles', 'agents' => 'Agents', 'chambres' => 'Chambres', 'formations' => 'Formations', 'composition' => 'Composition', 'audiences' => 'Audiences', 'services' => 'Services', 'responsables' => 'Responsables', 'membres_exterieurs' => 'Membres extérieurs', 'dates_limites' => 'Dates limites', 'articles' => 'Articles'];
    $icBloc = ['sections' => 'fa-layer-group', 'salles' => 'fa-door-open', 'agents' => 'fa-users', 'chambres' => 'fa-building-columns', 'formations' => 'fa-sitemap', 'composition' => 'fa-people-group', 'audiences' => 'fa-calendar-days', 'services' => 'fa-briefcase', 'responsables' => 'fa-user-tie', 'membres_exterieurs' => 'fa-handshake', 'membres' => 'fa-handshake', 'dates_limites' => 'fa-hourglass-end', 'articles' => 'fa-scroll', 'annee' => 'fa-calendar-check'];
    $icEtape = ['fa-cloud-arrow-up', 'fa-magnifying-glass-chart', 'fa-user-check', 'fa-code-compare', 'fa-circle-check'];
    $parFeuille = fn ($liste) => $liste->groupBy('feuille');
    $nbErr = $parFeuille($erreurs)->map->count();
    $nbAv = $parFeuille($avert)->map->count();
  @endphp
  <div class="page-body io-page" id="io-racine">
    <div class="el-page tp-anime">

      <x-hero tag="Organisation" title="Contrôle du fichier" subtitle="{{ $import->nom_fichier }} : le fichier est lu et contrôlé, rien n'est modifié dans l'organisation." icon="fa-solid fa-magnifying-glass-chart">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-days"></i> Année {{ $d['annee']['libelle'] ?? '—' }}@if (! empty($d['annee']['nouvelle'])) (nouvelle)@endif</span>
          <span><i class="fa-solid fa-user"></i> Chargé par {{ $import->auteur?->name ?? '—' }} le {{ $import->created_at?->format('d/m/Y H:i') }}</span>
          <span><i class="fa-solid fa-layer-group"></i> {{ $import->mode === 'complet' ? 'Ordonnance complète' : 'Mise à jour' }}</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" data-io-nav href="{{ route('organisation.ordonnances.import') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </x-slot:actions>
      </x-hero>

      @include('organisation.ordonnances._onglets', ['actif' => 'import'])

      @if (! empty($k['autre_base']) && $import->enCours())
        <div class="aff-info" style="margin-bottom:16px"><i class="fa-solid fa-shuffle"></i><span><b>Fichier d'une autre base.</b> Ses N° ne correspondent pas à cette base : ils sont ignorés et tout est rapproché par les <b>noms</b> (chambres, formations, services, salles, personnes). Les personnes absentes de cette base seraient <b>créées</b> : vérifiez bien l'écran de comparaison.</span></div>
      @endif

      @unless ($import->enCours())
        <div class="el-card" style="margin-bottom:20px">
          <div class="el-card__head"><div>
            <h2 class="el-card__title">
              @if ($import->statut === 'applique')<i class="fa-solid fa-circle-check" style="color:var(--io-vert)"></i> Appliqué le {{ $import->applique_le?->format('d/m/Y à H:i') }}
              @elseif ($import->statut === 'annule')<i class="fa-solid fa-rotate-left" style="color:var(--io-orange)"></i> Annulé le {{ $import->annule_le?->format('d/m/Y à H:i') }}
              @else<i class="fa-solid fa-ban" style="color:var(--io-gris)"></i> {{ \App\Models\ImportOrdonnance::STATUTS[$import->statut] ?? $import->statut }}@endif
            </h2>
            <div class="el-card__sub">Ce chargement ne se modifie plus.@if ($import->statut === 'applique') {{ $bilan['choisis'] ?? 0 }} changement(s) choisi(s) sur {{ $bilan['proposes'] ?? 0 }} proposé(s).@endif</div>
          </div></div>
          @if (! empty($bilan['resume']))
            <div class="el-card__body" style="padding-top:6px; padding-bottom:22px">
              <div class="io-blocs io-blocs--compact">
                @foreach ($bilan['resume'] as $bloc => $actions)
                  <div class="io-bloc">
                    <span class="io-bloc__icone"><i class="fa-solid {{ $icBloc[$bloc] ?? 'fa-table' }}"></i></span>
                    <span class="io-bloc__valeur">{{ array_sum($actions) }}</span>
                    <span class="io-bloc__libelle">{{ $motsBloc[$bloc] ?? $bloc }}</span>
                    <span class="io-bloc__etat" style="color:#3F4D66">@foreach (['creer' => 'ajouté', 'modifier' => 'modifié', 'retirer' => 'retiré'] as $a => $mot)@if (! empty($actions[$a])){{ $actions[$a] }} {{ $mot }}{{ $actions[$a] > 1 ? 's' : '' }} @endif @endforeach</span>
                  </div>
                @endforeach
              </div>
              @foreach ($bilan['ecartes'] ?? [] as $m)<div class="io-ligne io-ligne--orange" style="margin-top:8px"><span class="io-ligne__ou">Écarté</span><span>{{ $m }}</span></div>@endforeach
              @if ($annulable)
                <form method="post" action="{{ route('organisation.ordonnances.import.annuler', $import->id) }}" style="margin-top:18px" data-io-action data-io-confirm="Annuler cet import ? Ce qu'il a ajouté sera ôté, ce qu'il a modifié ou retiré retrouvera sa valeur d'avant." data-io-titre="Annulation de l'import" data-io-sous="L'organisation retrouve son état d'avant.">
                  @csrf
                  <button class="el-btn el-btn--ghost" type="submit"><i class="fa-solid fa-rotate-left"></i> Annuler cet import</button>
                  <span class="el-muted" style="margin-left:10px">Possible tant qu'aucun autre import n'a été appliqué après lui.</span>
                </form>
              @elseif ($import->statut === 'applique')
                <p class="el-muted" style="margin-top:14px"><i class="fa-solid fa-lock"></i> Un import plus récent a été appliqué : celui-ci ne peut plus être annulé seul.</p>
              @endif
            </div>
          @endif
        </div>
      @endunless

      {{-- Étapes --}}
      <div class="el-card" style="margin-bottom:20px">
        <div class="el-card__body" style="padding:18px 24px">
          <ol class="io-etapes" aria-label="Étapes du chargement">
            @foreach ($etapes as $i => [$lib, $aide])
              @php
                $n = $i + 1;
                $classe = $n < $etape ? 'is-fait' : ($n === $etape ? ($erreurs->isNotEmpty() && $n === 2 ? 'is-erreur' : 'is-actif') : '');
              @endphp
              <li class="io-etape {{ $classe }}">
                <span class="io-etape__rond">@if ($classe === 'is-fait')<i class="fa-solid fa-check"></i>@elseif ($classe === 'is-erreur')<i class="fa-solid fa-xmark"></i>@else<i class="fa-solid {{ $icEtape[$i] }}"></i>@endif</span>
                <span class="io-etape__libelle">{{ $lib }}</span>
                <span class="io-etape__aide">{{ $aide }}</span>
              </li>
            @endforeach
          </ol>
        </div>
      </div>

      {{-- Ce que contient le fichier --}}
      @if ($import->enCours())
      <div class="el-card" style="margin-bottom:20px">
        <div class="el-card__head"><div><h2 class="el-card__title">Ce que contient le fichier</h2><div class="el-card__sub">Nombre de lignes lues par feuille, et état du contrôle</div></div></div>
        <div class="el-card__body" style="padding-top:8px; padding-bottom:22px">
          <div class="io-blocs io-blocs--compact">
            @foreach ($blocs as $cle => $lib)
              @php
                $nb = $comptes[$cle] ?? null;
                $e = $nbErr[$lib] ?? 0; $a = $nbAv[$lib] ?? 0;
                [$ton, $texte, $icone] = $nb === null ? ['gris', 'non fourni', 'fa-minus'] : ($e ? ['rouge', $e.' erreur'.($e > 1 ? 's' : ''), 'fa-circle-xmark'] : ($a ? ['orange', $a.' avertissement'.($a > 1 ? 's' : ''), 'fa-triangle-exclamation'] : ['vert', 'conforme', 'fa-circle-check']));
              @endphp
              <div class="io-bloc io-bloc--{{ $ton }}">
                <span class="io-bloc__icone"><i class="fa-solid {{ $icBloc[$cle] ?? 'fa-table' }}"></i></span>
                <span class="io-bloc__valeur">{{ $nb ?? '—' }}</span>
                <span class="io-bloc__libelle">{{ $lib }}</span>
                <span class="io-bloc__etat"><i class="fa-solid {{ $icone }}"></i> {{ $texte }}</span>
              </div>
            @endforeach
          </div>
          @if (! empty($d['remarques']))<p class="el-muted" style="margin:14px 0 0"><i class="fa-solid fa-note-sticky"></i> {{ count($d['remarques']) }} remarque(s) libre(s) conservée(s) avec le fichier.</p>@endif
        </div>
      </div>

      @endif

      {{-- Erreurs --}}
      @if ($import->enCours())
      @if ($erreurs->isNotEmpty())
        <div class="el-card" style="margin-bottom:20px">
          <div class="el-card__head"><div><h2 class="el-card__title"><i class="fa-solid fa-circle-xmark" style="color:var(--io-rouge)"></i> À corriger dans le fichier ({{ $erreurs->count() }})</h2><div class="el-card__sub">Corrigez ces lignes dans Excel, enregistrez, puis rechargez le fichier. Tant qu'il y a des erreurs, rien ne peut être appliqué.</div></div></div>
          <div class="el-card__body" style="padding-top:8px; padding-bottom:20px">
            @foreach ($parFeuille($erreurs->take(150)) as $feuille => $lignes)
              <div class="io-groupe">
                <h3 class="io-groupe__titre">{{ $feuille }} <span class="io-pastille">{{ $lignes->count() }}</span></h3>
                @foreach ($lignes as $m)
                  <div class="io-ligne"><span class="io-ligne__ou">{{ $m['ligne'] ? 'Ligne '.$m['ligne'] : 'Général' }}</span><span>{{ $m['message'] }}</span></div>
                @endforeach
              </div>
            @endforeach
            @if ($erreurs->count() > 150)<p class="el-muted">… et {{ $erreurs->count() - 150 }} autre(s) erreur(s) : corrigez d'abord celles-ci.</p>@endif
          </div>
        </div>
      @endif

      @endif

      {{-- Noms à confirmer --}}
      @if ($noms->isNotEmpty() && $import->enCours())
        <div class="el-card" style="margin-bottom:20px">
          <div class="el-card__head"><div><h2 class="el-card__title"><i class="fa-solid fa-user-check" style="color:var(--io-orange)"></i> Noms à confirmer ({{ $noms->count() }})</h2><div class="el-card__sub">Ces noms ne correspondent pas avec certitude à un agent connu. Choisissez la bonne personne : votre choix est mémorisé pour les prochains fichiers.</div></div></div>
          <div class="el-card__body" style="padding-top:12px; padding-bottom:22px">
            <form method="post" action="{{ route('organisation.ordonnances.import.rapprocher', $import->id) }}" data-io-analyse>
              @csrf
              @foreach ($noms as $n)
                @php $deja = $import->rapprochements[$n['cle']] ?? null; @endphp
                <div class="io-nom">
                  <div>
                    <div class="io-nom__texte">« {{ $n['texte'] }} »</div>
                    <div class="io-nom__ou">{{ collect($n['emplacements'])->groupBy(0)->map(fn ($g, $f) => $f.' (ligne'.($g->count() > 1 ? 's ' : ' ').$g->pluck(1)->take(5)->implode(', ').($g->count() > 5 ? '…' : '').')')->implode(' · ') }}</div>
                    @if (! empty($n['propositions']))<div class="io-nom__prop"><i class="fa-solid fa-lightbulb"></i> Le plus proche : {{ $n['propositions'][0]['nom'] }} ({{ round($n['propositions'][0]['score'] * 100) }} %)</div>@endif
                  </div>
                  <div>
                    <select class="form-select" name="choix[{{ $n['cle'] }}]" aria-label="Choix pour {{ $n['texte'] }}">
                      <option value="">— Choisir —</option>
                      @if (! empty($n['propositions']))
                        <optgroup label="Agents proches">
                          @foreach ($n['propositions'] as $p)<option value="agent:{{ $p['id'] }}" @selected(($deja['action'] ?? '') === 'agent' && (int) ($deja['id'] ?? 0) === $p['id'])>{{ $p['nom'] }} ({{ round($p['score'] * 100) }} %){{ $p['homonyme'] ? ' : homonyme' : '' }}</option>@endforeach
                        </optgroup>
                      @endif
                      <optgroup label="Autre décision">
                        <option value="creer" @selected(($deja['action'] ?? '') === 'creer')>Créer un nouvel agent : {{ $n['texte'] }}</option>
                        <option value="ignorer" @selected(($deja['action'] ?? '') === 'ignorer')>Ignorer les lignes qui le citent</option>
                      </optgroup>
                      <optgroup label="Tous les agents">
                        @foreach ($agents as $a)<option value="agent:{{ $a['id'] }}">{{ $a['nom'] }}</option>@endforeach
                      </optgroup>
                    </select>
                  </div>
                </div>
              @endforeach
              <div class="io-actions"><span class="el-muted">Les noms laissés sans choix restent à confirmer.</span><button class="el-btn el-btn--accent" type="submit"><i class="fa-solid fa-check"></i> Enregistrer mes choix et recontrôler</button></div>
            </form>
          </div>
        </div>
      @endif

      {{-- Avertissements --}}
      @if ($import->enCours() && $avert->isNotEmpty())
        <div class="el-card" style="margin-bottom:20px">
          <div class="el-card__head"><div><h2 class="el-card__title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--io-orange)"></i> À lire ({{ $avert->count() }})</h2><div class="el-card__sub">Ces points ne bloquent pas le chargement, mais méritent un coup d'œil.</div></div></div>
          <div class="el-card__body" style="padding-top:8px; padding-bottom:20px">
            @foreach ($parFeuille($avert->take(150)) as $feuille => $lignes)
              <div class="io-groupe">
                <h3 class="io-groupe__titre">{{ $feuille }} <span class="io-pastille io-pastille--orange">{{ $lignes->count() }}</span></h3>
                @foreach ($lignes as $m)
                  <div class="io-ligne io-ligne--orange"><span class="io-ligne__ou">{{ $m['ligne'] ? 'Ligne '.$m['ligne'] : 'Général' }}</span><span>{{ $m['message'] }}</span></div>
                @endforeach
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if ($import->enCours() && $erreurs->isEmpty() && $noms->isEmpty())
        <div class="io-vide" style="margin-bottom:20px"><i class="fa-solid fa-circle-check"></i> Le fichier est conforme{{ $avert->isNotEmpty() ? ' (avec des points à lire ci-dessus)' : '' }}. Cliquez sur « Comparer avec l'organisation » pour voir, élément par élément, ce qui va changer avant d'appliquer quoi que ce soit.</div>
      @endif

      {{-- Actions --}}
      @if ($import->enCours())
        <div class="el-card">
          <div class="el-card__body io-actions" style="margin-top:0; padding:18px 24px">
            <form method="post" action="{{ route('organisation.ordonnances.import.mode', $import->id) }}" data-io-analyse style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
              @csrf
              <label class="form-label" for="mode" style="margin:0"><b>Mode</b></label>
              <select class="form-select" id="mode" name="mode" data-sans-recherche style="min-width:300px">
                @foreach ($modes as $cle => $lib)<option value="{{ $cle }}" @selected($import->mode === $cle)>{{ $lib }}</option>@endforeach
              </select>
              <button class="el-btn el-btn--ghost" type="submit">Changer</button>
            </form>
            <div style="display:flex; gap:10px; flex-wrap:wrap">
              <form method="post" action="{{ route('organisation.ordonnances.import.recontroler', $import->id) }}" data-io-analyse>@csrf<button class="el-btn el-btn--ghost" type="submit"><i class="fa-solid fa-rotate"></i> Recontrôler</button></form>
              <form method="post" action="{{ route('organisation.ordonnances.import.abandonner', $import->id) }}" data-io-action data-io-confirm="Abandonner ce chargement ? Rien n'a été modifié." data-io-titre="Abandon du chargement" data-io-sous="Le fichier est mis de côté.">@csrf<button class="el-btn el-btn--ghost" type="submit"><i class="fa-solid fa-xmark"></i> Abandonner</button></form>
              @if ($k['peut_continuer'] ?? false)
                <a class="el-btn el-btn--accent" data-io-nav data-io-titre="Comparaison avec l'organisation…" href="{{ route('organisation.ordonnances.import.comparer', $import->id) }}"><i class="fa-solid fa-code-compare"></i> Comparer avec l'organisation</a>
              @else
                <button class="el-btn el-btn--accent" type="button" disabled title="Corrigez les erreurs et confirmez les noms d'abord"><i class="fa-solid fa-code-compare"></i> Comparer avec l'organisation</button>
              @endif
            </div>
          </div>
        </div>
      @endif

    </div>
  </div>
  @include('organisation.ordonnances.import._chargeur')
@endsection

@section('script')
  <script src="{{ asset('assets/js/import-ordonnance.js') }}?v={{ filemtime(public_path('assets/js/import-ordonnance.js')) }}"></script>
@endsection
