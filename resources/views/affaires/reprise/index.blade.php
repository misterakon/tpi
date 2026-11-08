@extends('layout.master')
@section('title', "Reprise de l'existant")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/reprise.css') }}?v={{ filemtime(public_path('assets/css/reprise.css')) }}">
@endsection

@section('main-content')
  <div class="page-body imp-page">
    <div class="el-page">

      <x-hero tag="Affaires" title="Reprise de l'existant" subtitle="Importez vos registres tenus sous Excel : téléchargez le modèle, remplissez-le, envoyez-le. Le fichier est contrôlé ligne par ligne avant tout enregistrement, et rien n'est importé s'il reste une erreur." icon="fa-solid fa-file-import">
        <x-slot:extra>
          <span><i class="fa-solid fa-shield-halved"></i> Tout ou rien</span>
          <span><i class="fa-solid fa-eye"></i> Prévisualisation ligne par ligne</span>
          <span><i class="fa-solid fa-clone"></i> Sans doublon</span>
          <span><i class="fa-solid fa-file-excel"></i> .xlsx jusqu'à {{ round($taille_max / 1048576, 1) }} Mo · {{ number_format($lignes_max, 0, ',', ' ') }} lignes</span>
        </x-slot:extra>
      </x-hero>

      @can('reprise.creer')
        {{-- ============ Étapes ============ --}}
        <div class="el-card imp-carte">
          <div class="imp-carte__corps" style="padding-top:18px; padding-bottom:18px;">
            <ol class="imp-stepper" id="imp_stepper" data-etape="1" aria-label="Étapes de la reprise">
              <li class="imp-step"><span class="imp-step__rond imp-centre"><span class="imp-step__num">1</span><i class="fa-solid fa-check imp-step__ok"></i></span><span class="imp-step__libelle">Modèle</span><span class="imp-step__aide">Télécharger et remplir</span></li>
              <li class="imp-step"><span class="imp-step__rond imp-centre"><span class="imp-step__num">2</span><i class="fa-solid fa-check imp-step__ok"></i></span><span class="imp-step__libelle">Fichier</span><span class="imp-step__aide">Choisir et analyser</span></li>
              <li class="imp-step"><span class="imp-step__rond imp-centre"><span class="imp-step__num">3</span><i class="fa-solid fa-check imp-step__ok"></i></span><span class="imp-step__libelle">Vérification</span><span class="imp-step__aide">Contrôler puis importer</span></li>
            </ol>
          </div>
        </div>

        {{-- ============ 1. Modèle ============ --}}
        <div class="el-card imp-carte">
          <div class="imp-carte__tete">
            <span class="imp-num imp-centre">1</span>
            <div><h2 class="imp-carte__titre">Télécharger le modèle</h2><p class="imp-carte__sous">Un classeur prêt à remplir, avec les chambres et les natures de votre application</p></div>
          </div>
          <div class="imp-carte__corps">
            <div class="imp-formulaire" style="margin-bottom:20px">
              <div class="imp-champ" style="flex:0 1 340px">
                <label class="imp-etiquette" for="imp-serie"><i class="fa-solid fa-book"></i> Registre à reprendre</label>
                <select class="form-select" id="imp-serie" data-sans-recherche>
                  <option value="RG">RG : affaires civiles</option>
                  <option value="RP">RP : affaires pénales</option>
                  <option value="RI">RI : instruction (adultes)</option>
                  <option value="RE">RE : instruction (enfants)</option>
                </select>
                <p class="imp-aide">Un modèle et un fichier par registre.</p>
              </div>
            </div>
            <div class="imp-modele">
              <div class="imp-modele__visuel imp-centre"><i class="fa-solid fa-file-excel"></i></div>
              <div class="imp-modele__corps">
                <p class="imp-modele__texte">Une ligne = une affaire. Le numéro s'écrit avec l'année (<b>1391/25</b>), les dates en <b>jj/mm/aaaa</b>, plusieurs parties se séparent par un <b>point-virgule</b>. La chambre et la nature s'écrivent exactement comme dans l'onglet « Listes ». <b>Les titres de la première ligne ne doivent pas être modifiés</b> : un fichier qui ne respecte pas le modèle est refusé.</p>
                <ul class="imp-onglets">
                  <li><i class="fa-solid fa-book-open"></i> Mode d'emploi</li>
                  <li><i class="fa-solid fa-table"></i> Registre à remplir</li>
                  <li><i class="fa-solid fa-lightbulb"></i> Exemple</li>
                  <li><i class="fa-solid fa-list-ul"></i> Listes (chambres, natures)</li>
                </ul>
              </div>
              <a class="el-btn el-btn--accent imp-btn-lg" id="imp-modele" href="{{ route('affaires.reprise.modele', 'RG') }}"><i class="fa-solid fa-download"></i> Télécharger le modèle</a>
            </div>
            <div class="imp-colonnes">
              @foreach ($colonnes as $serie => $cols)
                <div data-colonnes="{{ $serie }}" @if ($serie !== 'RG') hidden @endif>
                  <h6>Colonnes du modèle {{ $serie }} (* obligatoire)</h6>
                  <ul>@foreach ($cols as $c)<li class="{{ $c['obligatoire'] ? '' : 'imp-facultatif' }}" title="{{ $c['aide'] }}">{{ $c['titre'] }}{{ $c['obligatoire'] ? ' *' : '' }}</li>@endforeach</ul>
                </div>
              @endforeach
            </div>
          </div>
        </div>

        {{-- ============ 2. Fichier ============ --}}
        <div class="el-card imp-carte">
          <div class="imp-carte__tete">
            <span class="imp-num imp-centre">2</span>
            <div><h2 class="imp-carte__titre">Analyser votre fichier</h2><p class="imp-carte__sous">L'analyse ne modifie rien : vous verrez chaque ligne avec ses erreurs éventuelles</p></div>
          </div>
          <div class="imp-carte__corps">
            <span class="imp-etiquette"><i class="fa-solid fa-file-excel"></i> Fichier Excel du registre <b id="imp-serie-rappel" style="margin-left:4px">RG</b></span>
            <label class="imp-depot" id="imp-depot" for="imp-fichier">
              <input class="imp-depot__input" id="imp-fichier" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
              <span class="imp-depot__icone imp-centre"><i class="fa-solid fa-cloud-arrow-up" id="imp-depot-icone"></i></span>
              <span class="imp-depot__titre" id="imp-depot-titre">Glissez votre fichier ici ou <u>parcourez</u></span>
              <span class="imp-depot__sous" id="imp-depot-sous">Format .xlsx · {{ round($taille_max / 1048576, 1) }} Mo maximum · onglet « Registre » du modèle</span>
            </label>
            <div class="imp-actions">
              <button class="el-btn el-btn--accent imp-btn-lg" type="button" id="imp-analyser"><i class="fa-solid fa-magnifying-glass"></i> Analyser le fichier</button>
              <p class="imp-aide">L'import est « tout ou rien » : une seule ligne en erreur le bloque, rien n'est enregistré tant que le fichier n'est pas corrigé. Les numéros déjà présents dans l'application sont simplement ignorés.</p>
            </div>
          </div>
        </div>

        {{-- ============ Progression ============ --}}
        <div class="el-card imp-carte" id="imp-progression" hidden>
          <div class="imp-carte__tete">
            <span class="imp-num imp-num--orange imp-centre" style="background:#E8730C"><i class="fa-solid fa-gear fa-spin"></i></span>
            <div><h2 class="imp-carte__titre" id="imp-prog-titre">Opération en cours</h2><p class="imp-carte__sous">Ne fermez pas cette page pendant l'opération</p></div>
          </div>
          <div class="imp-carte__corps">
            <div class="imp-barre" role="progressbar" id="imp-prog-aria" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
              <div class="imp-barre__rempli" id="imp-prog-barre"></div>
              <div class="imp-barre__texte" id="imp-prog-pct">0 %</div>
            </div>
            <p class="imp-operation" aria-live="polite"><b>En cours :</b> <span id="imp-prog-libelle">Démarrage…</span><span class="imp-compteur" id="imp-prog-compteur"></span></p>
            <ul class="imp-etapes" id="imp-prog-etapes"></ul>
          </div>
        </div>

        {{-- ============ 3. Vérification ============ --}}
        <div class="el-card imp-carte" id="imp-resultat" hidden>
          <div class="imp-carte__tete">
            <span class="imp-num imp-centre">3</span>
            <div><h2 class="imp-carte__titre">Vérification du fichier</h2><p class="imp-carte__sous">Contrôlez le détail ligne par ligne avant d'importer</p></div>
            <div style="margin-left:auto; display:flex; gap:10px; flex-wrap:wrap">
              <a class="el-btn el-btn--ghost" id="imp-rapport" href="#" hidden><i class="fa-solid fa-file-excel"></i> Rapport des problèmes</a>
              @can('reprise.importer')
                <button class="el-btn el-btn--accent imp-btn-lg" type="button" id="imp-importer" disabled><i class="fa-solid fa-file-import"></i> Importer</button>
              @endcan
            </div>
          </div>
          <div class="imp-carte__corps">
            <div id="imp-verdict"></div>
            <div class="imp-kpis" id="imp-resume"></div>
            <div class="imp-barre-outils">
              <div class="imp-seg" role="group" aria-label="Filtrer les lignes">
                <button type="button" class="imp-seg__btn imp-actif" data-filtre="toutes"><i class="fa-solid fa-list"></i> Toutes <span class="imp-seg__nb" data-nb="toutes">0</span></button>
                <button type="button" class="imp-seg__btn" data-filtre="erreur"><i class="fa-solid fa-circle-xmark"></i> Erreurs <span class="imp-seg__nb" data-nb="erreur">0</span></button>
                <button type="button" class="imp-seg__btn" data-filtre="nouvelle"><i class="fa-solid fa-circle-plus"></i> Nouvelles <span class="imp-seg__nb" data-nb="nouvelle">0</span></button>
                <button type="button" class="imp-seg__btn" data-filtre="doublon"><i class="fa-solid fa-clone"></i> Doublons <span class="imp-seg__nb" data-nb="doublon">0</span></button>
              </div>
              @cannot('reprise.importer')
                <span class="el-muted">L'importation définitive est réservée aux profils qui ont le droit « Lancer l'importation ».</span>
              @endcannot
            </div>
            <div class="imp-table-cadre">
              <table class="imp-table">
                <thead><tr><th>Ligne</th><th>État</th><th>N°</th><th>Date</th><th>Objet</th><th>Parties</th><th>Chambre</th><th>Messages</th></tr></thead>
                <tbody id="imp-lignes"></tbody>
              </table>
            </div>
            <p class="imp-aide" id="imp-troncature" hidden>Affichage limité : les lignes en erreur sont montrées en priorité, les autres sont tronquées. Le rapport Excel contient tous les problèmes.</p>
          </div>
        </div>
      @endcan

      <div class="el-card" style="margin-top:22px">
        <div class="el-card__head"><div><h2 class="el-card__title">Historique des reprises</h2><div class="el-card__sub">Les 30 dernières. Un import interrompu se reprend ; un import terminé peut être annulé tant que ses affaires n'ont pas été travaillées.</div></div></div>
        <div class="el-card__body">
          @if ($imports->isEmpty())
            <div class="aff-info"><i class="fa-solid fa-clock-rotate-left"></i><span>Aucune reprise pour le moment.</span></div>
          @else
            <div class="el-tblwrap">
              <table class="el-tbl el-tbl--fixe" id="tbl-imports">
                <colgroup><col style="width:150px"><col><col style="width:90px"><col style="width:190px"><col style="width:170px"><col style="width:170px"></colgroup>
                <thead><tr><th>Date</th><th>Fichier</th><th>Registre</th><th>Résultat</th><th>État</th><th>Actions</th></tr></thead>
                <tbody>
                  @foreach ($imports as $i)
                    <tr id="imp-{{ $i->reference }}">
                      <td>{{ $i->created_at->format('d/m/Y à H:i') }}<div class="el-person__sub">{{ $i->auteur?->name ?? '—' }}</div></td>
                      <td><div class="el-person__name" title="{{ $i->nom_fichier }}">{{ $i->nom_fichier }}</div><div class="el-person__sub">{{ $i->total ? number_format($i->total, 0, ',', ' ').' lignes' : '' }}</div></td>
                      <td><x-chip tone="info" :label="$i->serie" /></td>
                      <td>{{ $i->statut === 'importe' || $i->statut === 'annule' ? number_format($i->importees, 0, ',', ' ').' affaire(s)' : ($i->total ? number_format($i->valides, 0, ',', ' ').' valides, '.$i->erreurs.' en erreur' : '—') }}@if ($i->doublons)<div class="el-person__sub">{{ $i->doublons }} doublon(s) ignoré(s)</div>@endif</td>
                      <td><x-chip :tone="['importe' => 'success', 'annule' => 'neutral', 'echec' => 'danger', 'pret' => 'alert'][$i->statut] ?? 'info'" :label="\App\Models\ImportReprise::STATUTS[$i->statut] ?? $i->statut" class="el-chip--libre" /></td>
                      <td>
                        <div style="display:flex; gap:8px; flex-wrap:wrap">
                          @if ($i->estOuvert())
                            @can('reprise.creer')<button class="el-iconbtn el-iconbtn--blue" type="button" title="Reprendre cet import" aria-label="Reprendre" onclick="imp_reprendre('{{ $i->reference }}')"><i class="fa-solid fa-play"></i></button>@endcan
                          @endif
                          @if ($i->erreurs || $i->doublons)<a class="el-iconbtn el-iconbtn--green" href="{{ route('affaires.reprise.rapport', $i->reference) }}" title="Rapport des problèmes (Excel)" aria-label="Rapport"><i class="fa-solid fa-file-excel"></i></a>@endif
                          @if ($i->statut !== 'annule')
                            @can('reprise.annuler')<button class="el-iconbtn el-iconbtn--red" type="button" title="{{ $i->statut === 'importe' ? 'Annuler cet import (retire les affaires reprises)' : 'Abandonner cet import' }}" aria-label="Annuler" onclick="imp_annuler('{{ $i->reference }}', {{ $i->statut === 'importe' ? 'true' : 'false' }})"><i class="fa-solid fa-rotate-left"></i></button>@endcan
                          @endif
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>

    </div>
  </div>

  @include('affaires.reprise.js_script')
@endsection
