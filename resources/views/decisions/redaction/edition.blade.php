@extends('layout.master')
@section('title', $decision->reference)

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/feuille-acte.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/redaction-decision.css') }}">
@endsection

@section('main-content')
  @php
    $valeurs = old('valeurs', $decision->valeurs ?? []);
    $groupes = collect($champs)->groupBy('groupe');
    $numeroTexte = $decision->numeroAffichage() ? 'n° '.$decision->numeroAffichage().' ' : '';
    $sousTitre = match (true) {
        $decision->statut === 'delivree' => 'Décision '.$numeroTexte.'signée le '.$decision->date_signature?->format('d/m/Y').' et délivrée : le texte est figé.',
        $decision->estSignee() => 'Décision '.$numeroTexte.'signée le '.$decision->date_signature?->format('d/m/Y').' : le texte est figé, elle peut être délivrée.',
        $decision->estValidee() => 'Décision validée le '.$decision->validee_le?->format('d/m/Y à H:i').($decision->validePar ? ' par '.$decision->validePar->name : '').' : le texte est figé, elle attend la signature.',
        $decision->estRedigee() => 'Décision rédigée le '.$decision->redigee_le?->format('d/m/Y').' : elle attend la validation.',
        default => 'Remplissez les champs à gauche ; la décision se met à jour à droite sur une page A4. Enregistrez régulièrement, marquez la décision comme rédigée, puis faites-la valider.',
    };
  @endphp
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Rédaction" :title="$decision->reference" :subtitle="$sousTitre" icon="fa-solid fa-pen-nib">
        <x-slot:extra>
          <span><i class="fa-solid fa-file-signature"></i> {{ $decision->modele->nom }} (version {{ $decision->numeroVersion() }})</span>
          <span><i class="fa-solid fa-circle-info"></i> {{ \App\Models\Decision::STATUTS[$decision->statut] ?? $decision->statut }}</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('decisions.redaction') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
          <a class="el-btn el-btn--ghost" href="{{ route('decisions.redaction.imprimer', $decision->id) }}" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Imprimer</a>
          @unless ($lecture)
            @if ($decision->chambre_id)
              <button class="el-btn el-btn--ghost" type="button" onclick="recomposer_tribunal()" title="Reprendre le président, les assesseurs et le greffier de l'ordonnance"><i class="fa-solid fa-users"></i> Composition de l'ordonnance</button>
            @endif
            <button class="el-btn el-btn--ghost" type="button" onclick="ouvrir_retouche()"><i class="fa-solid fa-pen-to-square"></i> Retoucher le texte</button>
            <button class="el-btn el-btn--ghost" type="button" id="btn-exemples" onclick="basculer_exemples()"><i class="fa-solid fa-wand-magic-sparkles"></i> <span>Exemples</span></button>
            @if ($peutValider)
              <button class="el-btn el-btn--valider" type="button" onclick="valider_decision()"><i class="fa-solid fa-circle-check"></i> Valider</button>
            @endif
            <button class="el-btn el-btn--accent" type="submit" form="form-decision"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
            @if ($decision->statut === 'brouillon')
              <button class="el-btn el-btn--ghost" type="button" onclick="marquer_redigee()" title="La rédaction est terminée : la décision attend la validation"><i class="fa-solid fa-flag"></i> Marquer comme rédigée</button>
            @endif
          @endunless
          @if ($decision->estRedigee() && $decision->numero === null && $peutNumeroter && $momentNumero === 'redaction')
            <button class="el-btn el-btn--accent" type="button" onclick="numeroter_decision()"><i class="fa-solid fa-hashtag"></i> Attribuer le numéro</button>
          @endif
          @if ($decision->estRedigee() && $peutValider)
            <button class="el-btn el-btn--ghost" type="button" onclick="renvoyer_decision()"><i class="fa-solid fa-rotate-left"></i> Renvoyer en rédaction</button>
          @endif
          @if ($decision->estValidee() && ! $decision->estSignee())
            @can('redaction.creer')
              <button class="el-btn el-btn--ghost" type="button" onclick="corriger_decision()"><i class="fa-solid fa-pen-ruler"></i> Corriger</button>
            @endcan
            @if ($peutSigner)
              <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_signature()"><i class="fa-solid fa-signature"></i> Signer</button>
            @endif
          @endif
          @if ($decision->estSignee() && $peutDelivrer)
            <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_delivrance()"><i class="fa-solid fa-paper-plane"></i> Délivrer</button>
          @endif
        </x-slot:actions>
      </x-hero>

      <div class="red-grille">

        <div class="red-colonne">
          @include('decisions.redaction.suivi')
          <form id="form-decision" method="post" action="{{ route('decisions.redaction.store', $decision->id) }}" autocomplete="off">
            @csrf
            <div class="el-card">
              <div class="el-card__head"><div><h2 class="el-card__title">Informations</h2><div class="el-card__sub">Seuls les champs que le texte du modèle utilise sont demandés ; ce qui est déjà connu est rempli d'avance.</div></div></div>
              <div class="el-card__body">
                @forelse ($groupes as $groupe => $liste)
                  @php
                    $remplis = $liste->filter(fn ($c) => ! in_array($valeurs[$c['code']] ?? null, [null, '', []], true))->count();
                    $ouvert = $loop->first || $remplis < $liste->count();
                  @endphp
                  <details class="red-bloc" @if ($ouvert) open @endif>
                    <summary>
                      <span class="red-bloc__titre">{{ $groupe }}</span>
                      <span class="red-bloc__etat {{ $remplis === $liste->count() ? 'red-bloc__etat--ok' : '' }}">{{ $remplis }} / {{ $liste->count() }}</span>
                    </summary>
                    <div class="red-lignes">
                      @if ($loop->first)
                        <div class="red-champ red-champ--large">
                          @include('decisions.redaction.reference')
                        </div>
                        <div class="red-champ red-champ--large">
                          @include('decisions.redaction.numero')
                        </div>
                      @endif
                      @if ($groupe === 'Composition du tribunal')
                        {{-- Une rangée par personne : président, premier assesseur, second assesseur, greffier --}}
                        @unless ($lecture)
                          <div class="red-champ--large red-composition-outils">
                            <button type="button" class="red-vider" onclick="vider_composition()"><i class="fa-regular fa-trash-can"></i> Vider la composition</button>
                          </div>
                        @endunless
                        @foreach ($liste->groupBy(fn ($c) => \Illuminate\Support\Str::beforeLast($c['code'], '.')) as $rangee)
                          <div class="red-rangee">
                            <div class="red-rangee__titre">{{ explode(' : ', $rangee->first()['libelle'])[0] }}</div>
                            <div class="red-rangee__champs">
                              @foreach ($rangee as $champ)
                                @include('decisions.redaction.champ', ['champ' => $champ, 'valeurs' => $valeurs, 'lecture' => $lecture, 'court' => true])
                              @endforeach
                            </div>
                          </div>
                        @endforeach
                      @else
                        @foreach ($liste as $champ)
                          @include('decisions.redaction.champ', ['champ' => $champ, 'valeurs' => $valeurs, 'lecture' => $lecture])
                        @endforeach
                      @endif
                    </div>
                  </details>
                @empty
                  <div class="red-champ">
                    @include('decisions.redaction.reference')
                  </div>
                  <div class="red-champ">
                    @include('decisions.redaction.numero')
                  </div>
                  <p class="el-muted">Le texte de ce modèle ne contient aucune variable : rien à saisir.</p>
                @endforelse

                @unless ($lecture)
                  <div class="red-pied">
                    <button class="el-btn el-btn--accent" type="submit"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                  </div>
                @endunless
              </div>
            </div>
          </form>

          <form id="form-action" method="post" action="" style="display:none">@csrf</form>
        </div>

        <aside class="red-colonne red-apercu">
          <div class="el-card">
            <div class="el-card__head">
              <div><h2 class="el-card__title">La décision</h2><div class="el-card__sub">Page A4, comme dans le document Word</div></div>
              <span class="acte-zoom" title="Zoom (Ctrl + molette)">
                <button type="button" onclick="zoom_apercu(-1)" aria-label="Réduire">&minus;</button>
                <output id="apercu-zoom">125 %</output>
                <button type="button" onclick="zoom_apercu(1)" aria-label="Agrandir">+</button>
                <button type="button" class="large" onclick="zoom_apercu(0)">Largeur</button>
                <button type="button" class="large" onclick="zoom_apercu(100)">100 %</button>
                <button type="button" class="large" onclick="zoom_apercu(125)">125 %</button>
              </span>
            </div>
            <div class="el-card__body">
              <div class="red-etat" id="apercu-etat"></div>
              <div id="apercu-feuilles"></div>
            </div>
          </div>
        </aside>

      </div>
    </div>
  </div>

  @unless ($lecture)
    @include('decisions.redaction.form_retouche')
  @endunless
  @if ($peutSigner && $decision->statut === 'validee')
    @include('decisions.redaction.form_signature')
  @endif
  @if ($peutDelivrer && $decision->estSignee())
    @include('decisions.redaction.form_delivrance')
  @endif
  @include('decisions.redaction.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
  <script src="{{ asset('assets/js/feuille-acte.js') }}"></script>
@endsection
