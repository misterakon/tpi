@extends('layout.master')
@section('title', $modele->exists ? $modele->nom : 'Nouveau modèle de décision')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/modeles-decision.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/feuille-acte.css') }}">
@endsection

@section('main-content')
  @php
    $sousTitre = $lecture
        ? "Consultation : vous n'avez pas le droit de modifier ce modèle."
        : "Écrivez la qualité et le factum en insérant les variables et les blocs à droite. L'aperçu montre le résultat avec des données fictives.";
  @endphp
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Modèles de décision" :title="$modele->exists ? $modele->nom : 'Nouveau modèle'" :subtitle="$sousTitre" icon="fa-solid fa-file-signature">
        <x-slot:extra>
          @if ($version)<span><i class="fa-solid fa-code-branch"></i> Version {{ $version->numero }}</span>@endif
          <span><i class="fa-solid fa-circle-info"></i> {{ \App\Models\ModeleDecision::ETATS[$modele->etat] ?? 'Brouillon' }}</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('decisions.modeles') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
          <button class="el-btn el-btn--ghost" type="button" onclick="apercu_modele()"><i class="fa-regular fa-eye"></i> Aperçu</button>
          @if ($modele->exists)
            @can('modeles.creer')
              <button class="el-btn el-btn--ghost" type="button" onclick="dupliquer_ce_modele()"><i class="fa-regular fa-copy"></i> Dupliquer</button>
            @endcan
          @endif
          @unless ($lecture)
            <button class="el-btn el-btn--accent" type="submit" form="form-modele"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          @endunless
        </x-slot:actions>
      </x-hero>

      <form id="form-modele" method="post" action="{{ route('decisions.modeles.store') }}" autocomplete="off">
        @csrf
        <input type="hidden" name="id_" value="{{ $modele->id }}">

        <div class="mdl-grille">
          <div class="mdl-colonne">
            <fieldset @disabled($lecture) style="border:0; padding:0; margin:0; min-width:0; display:flex; flex-direction:column; gap:22px">

              <div class="el-card">
                <div class="el-card__head"><div><h2 class="el-card__title">Informations</h2><div class="el-card__sub">Le nom, la matière, et à quoi le modèle sert</div></div></div>
                <div class="el-card__body">
                  <div class="el-formgrid">
                    <div class="el-c8">
                      <label class="form-label" for="f_nom"><b>Nom du modèle</b></label>
                      <input class="form-control" id="f_nom" name="nom" type="text" maxlength="160" value="{{ old('nom', $modele->nom) }}" placeholder="Ex. : Jugement d'hérédité" required>
                    </div>
                    <div class="el-c4">
                      <label class="form-label" for="f_etat"><b>État</b></label>
                      <select class="form-select" id="f_etat" name="etat" data-sans-recherche>
                        @foreach (\App\Models\ModeleDecision::ETATS as $valeur => $texte)
                          <option value="{{ $valeur }}" @selected(old('etat', $modele->etat) === $valeur)>{{ $texte }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="el-c4">
                      <label class="form-label" for="f_matiere_id"><b>Matière</b> <span class="text-muted">(facultatif)</span></label>
                      <select class="form-select" id="f_matiere_id" name="matiere_id">
                        <option value="">—</option>
                        @foreach ($matieres as $id => $libelle)
                          <option value="{{ $id }}" @selected((int) old('matiere_id', $modele->matiere_id) === $id)>{{ $libelle }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="el-c8">
                      <label class="form-label" for="f_description"><b>Description</b> <span class="text-muted">(facultatif)</span></label>
                      <input class="form-control" id="f_description" name="description" type="text" maxlength="255" value="{{ old('description', $modele->description) }}" placeholder="Ex. : Détermination de la qualité des héritiers">
                    </div>
                    <div class="el-c12">
                      <label class="form-label" for="f_objets"><b>Objets du litige concernés</b> <span class="text-muted">(l'application propose ce modèle pour ces objets)</span></label>
                      <select class="form-select" id="f_objets" name="objets[]" multiple>
                        @foreach ($objets as $id => $libelle)
                          <option value="{{ $id }}" @selected(in_array($id, old('objets', $objetsChoisis)))>{{ $libelle }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="el-c12">
                      <label class="form-label" for="f_chambres"><b>Chambres concernées</b> <span class="text-muted">(facultatif)</span></label>
                      <select class="form-select" id="f_chambres" name="chambres[]" multiple>
                        @foreach ($chambres as $libelle)
                          <option value="{{ $libelle }}" @selected(in_array($libelle, old('chambres', $chambresChoisies)))>{{ $libelle }}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="el-card">
                <div class="el-card__head"><div><h2 class="el-card__title">Textes du modèle</h2><div class="el-card__sub">Un texte à trous : les variables s'écrivent <span class="mdl-vide">&#123;&#123; nom &#125;&#125;</span>, les blocs <span class="mdl-vide">&#123;&#123;&gt; code &#125;&#125;</span></div></div></div>
                <div class="mdl-onglets">
                  <button type="button" class="mdl-onglet actif" id="onglet-entete" onclick="afficher_onglet('entete')">Timbre</button>
                  <button type="button" class="mdl-onglet" id="onglet-qualite" onclick="afficher_onglet('qualite')">Qualité</button>
                  <button type="button" class="mdl-onglet" id="onglet-factum" onclick="afficher_onglet('factum')">Factum</button>
                </div>
                <div class="el-card__body" style="border-top:1px solid var(--el-line); padding-top:16px">
                  <div id="panneau-entete">
                    <p class="el-muted" style="font-size:13px; margin-bottom:8px">Le timbre : le bloc de la colonne de gauche de la première page (cour d'appel, tribunal, numéro du RG, chambre, type de décision, numéro, affaire et parties). Il s'écrit en gras, centré, dans la police et la taille réglées pour le timbre.</p>
                    <textarea class="form-control" id="f_entete" name="entete" data-riche data-alignement data-hauteur="260">{{ old('entete', $version?->entete) }}</textarea>
                  </div>
                  <div id="panneau-qualite" style="display:none">
                    <p class="el-muted" style="font-size:13px; margin-bottom:8px">La qualité : titre d'audience, composition du tribunal, identité des parties. Elle s'imprime en retrait, à droite du timbre, avec une ligne vide entre les paragraphes comme dans le document Word.</p>
                    <textarea class="form-control" id="f_qualite" name="qualite" data-riche data-alignement data-hauteur="420">{{ old('qualite', $version?->qualite) }}</textarea>
                  </div>
                  <div id="panneau-factum" style="display:none">
                    <p class="el-muted" style="font-size:13px; margin-bottom:8px">Le factum : visas, faits et prétentions, motifs, dispositif, clôture. Le juge le complète à la rédaction.</p>
                    <textarea class="form-control" id="f_factum" name="factum" data-riche data-alignement data-hauteur="420">{{ old('factum', $version?->factum) }}</textarea>
                  </div>
                  <div style="margin-top:14px">
                    <label class="form-label" for="f_note"><b>Note de version</b> <span class="text-muted">(facultatif : ce que vous avez changé)</span></label>
                    <input class="form-control" id="f_note" name="note" type="text" maxlength="255" value="{{ old('note') }}" placeholder="Ex. : Ajout du bloc de dépens">
                  </div>
                </div>
              </div>

              <div class="el-card">
                <div class="el-card__head">
                  <div><h2 class="el-card__title">Variables propres à ce modèle</h2><div class="el-card__sub">Ce que ce type de décision demande en plus du catalogue (par exemple le défunt, les enfants, l'acte de décès). L'exemple sert à l'aperçu.</div></div>
                  @unless ($lecture)<button class="el-btn el-btn--ghost el-btn--sm" type="button" onclick="ajouter_variable()"><i class="fa-solid fa-plus"></i> Ajouter</button>@endunless
                </div>
                <div class="el-card__body">
                  <div id="lignes-variables">
                    @foreach (old('variables', $propres) as $i => $v)
                      <div class="mdl-ligne">
                        <div><label class="form-label">Code</label><input class="form-control" name="variables[{{ $i }}][code]" value="{{ $v['code'] }}" maxlength="60" placeholder="defunt.nom" required></div>
                        <div><label class="form-label">Libellé</label><input class="form-control" name="variables[{{ $i }}][libelle]" value="{{ $v['libelle'] }}" maxlength="160" placeholder="Défunt : nom" required></div>
                        <div><label class="form-label">Type</label>
                          <select class="form-select" name="variables[{{ $i }}][type]" data-sans-recherche>
                            @foreach (\App\Support\Redaction\VariablesDecision::TYPES as $valeur => $texte)
                              <option value="{{ $valeur }}" @selected($v['type'] === $valeur)>{{ $texte }}</option>
                            @endforeach
                          </select></div>
                        <div><label class="form-label">Exemple (fictif)</label><input class="form-control" name="variables[{{ $i }}][exemple]" value="{{ $v['exemple'] ?? '' }}" maxlength="2000"></div>
                        <button class="el-iconbtn el-iconbtn--red" type="button" title="Retirer" aria-label="Retirer" onclick="retirer_variable(this)"><i class="fa-regular fa-trash-can"></i></button>
                      </div>
                    @endforeach
                  </div>
                  <small class="text-muted">Une personne : {"nom": "KOUAME Awa", "genre": "f"}. Une liste : un élément par ligne ou séparés par des points-virgules. Un point dans le code (defunt.nom) regroupe plusieurs données sous un même nom.</small>
                </div>
              </div>

            </fieldset>

            @if ($modele->exists)
              <div class="el-card">
                <div class="el-card__head"><div><h2 class="el-card__title">Versions</h2><div class="el-card__sub">Chaque enregistrement qui change les textes crée une version. On peut revenir à une ancienne, sans rien perdre.</div></div></div>
                <div class="el-tblwrap">
                  <table class="el-tbl el-tbl--fixe">
                    <colgroup><col style="width:90px"><col style="width:130px"><col style="width:190px"><col><col style="width:150px"></colgroup>
                    <thead><tr><th>Version</th><th>Date</th><th>Auteur</th><th>Note</th><th>Action</th></tr></thead>
                    <tbody>
                      @foreach ($modele->versions as $ver)
                        <tr>
                          <td><b>v{{ $ver->numero }}</b></td>
                          <td>{{ $ver->created_at?->format('d/m/Y H:i') }}</td>
                          <td>{{ $ver->auteur?->name ?? '—' }}</td>
                          <td><span title="{{ $ver->note }}">{{ $ver->note }}</span></td>
                          <td>
                            @if ($loop->first)
                              <x-chip tone="success" label="En service" />
                            @else
                              @can('modeles.modifier')
                                <button class="el-btn el-btn--ghost el-btn--sm" type="button" onclick="restaurer_version({{ $ver->numero }})"><i class="fa-solid fa-clock-rotate-left"></i> Restaurer</button>
                              @endcan
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endif
          </div>

          <aside class="mdl-colonne mdl-panneau">
            <div class="el-card">
              <div class="el-card__head"><div><h2 class="el-card__title">Insérer</h2><div class="el-card__sub">Un clic place l'élément à l'endroit du curseur, dans l'onglet affiché.</div></div></div>
              <div class="el-card__body">
                <label class="form-label" for="f_filtre"><b>Transformation</b></label>
                <select class="form-select" id="f_filtre" data-sans-recherche>
                  <option value="">Automatique selon le type</option>
                  <option value="-">Aucune (valeur telle quelle)</option>
                  @foreach ($filtres as $nom => $effet)
                    <option value="{{ $nom }}">{{ $nom }} : {{ \Illuminate\Support\Str::limit($effet, 40) }}</option>
                  @endforeach
                </select>
                <input class="form-control" id="f_filtre_arg" type="text" maxlength="40" placeholder="Argument éventuel (ex. demandeur)" style="margin-top:8px">

                <input class="form-control" id="f_filtre_recherche" type="search" placeholder="Chercher une variable ou un bloc" style="margin-top:12px" oninput="chercher_element(this.value)">

                @foreach ($catalogue as $groupe => $variables)
                  <div class="mdl-groupe">{{ $groupe }}</div>
                  <div class="mdl-liste">
                    @foreach ($variables as $v)
                      <button type="button" class="mdl-chip" data-code="{{ $v['code'] }}" data-type="{{ $v['type'] }}" data-texte="{{ mb_strtolower($v['libelle'].' '.$v['code']) }}" onclick="inserer_variable(this)" title="{{ $v['code'] }}">{{ $v['libelle'] }}</button>
                    @endforeach
                  </div>
                @endforeach

                <div class="mdl-groupe">Mise en page du timbre</div>
                <div class="mdl-liste">
                  @foreach ($elements as $code => $element)
                    <button type="button" class="mdl-chip" data-code="{{ $code }}" data-texte="{{ mb_strtolower($element['libelle'].' '.$code.' trait ligne') }}" onclick="inserer_bloc(this)" title="{{ $code }}">{{ $element['libelle'] }}</button>
                  @endforeach
                </div>

                <div class="mdl-groupe">Blocs de texte</div>
                <div class="mdl-liste">
                  @forelse ($blocs as $b)
                    <button type="button" class="mdl-chip" data-code="{{ $b->code }}" data-texte="{{ mb_strtolower($b->nom.' '.$b->code) }}" onclick="inserer_bloc(this)" title="{{ $b->code }}">{{ $b->nom }} <small>{{ \App\Models\BlocTexte::CATEGORIES[$b->categorie] ?? '' }}</small></button>
                  @empty
                    <span class="el-muted" style="font-size:13px">Aucun bloc : créez-en dans « Blocs de texte ».</span>
                  @endforelse
                </div>
              </div>
            </div>
          </aside>
        </div>
      </form>

      <form id="form-restauration" method="post" action="" style="display:none">@csrf</form>
      @if ($modele->exists)
        <form id="form-duplication" method="post" action="{{ route('decisions.modeles.dupliquer', $modele->id) }}" style="display:none">@csrf</form>
      @endif

    </div>
  </div>

  @include('decisions.modeles.form_apercu')

  @include('decisions.modeles.modele_variable')

  @include('decisions.modeles.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
  <script src="{{ asset('assets/js/feuille-acte.js') }}"></script>
@endsection
