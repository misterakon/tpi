{{-- Dossier numérique interactif d'une affaire : sommaire des pièces à gauche, visionneuse à droite. Variables : $affaire, $dossier (pieces, taille_max) --}}
@php
  $pieces = collect($dossier['pieces']);
  $parTypes = $pieces->groupBy('type');
@endphp
<div class="dn" id="dn">
  <aside class="dn-sommaire">
    <header class="dn-sommaire__tete">
      <div>
        <h3>Sommaire du dossier</h3>
        <small id="dn-compte">{{ $pieces->count() }} pièce{{ $pieces->count() > 1 ? 's' : '' }}</small>
      </div>
      @can('dossiers.creer')
        @unless ($affaire->estAnnulee())
          <button type="button" class="dn-bouton dn-bouton--plein" onclick="dn_ouvrir_ajout()"><i class="fa-solid fa-plus"></i> Ajouter</button>
        @endunless
      @endcan
    </header>

    <label class="dn-recherche"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="dn-recherche" placeholder="Chercher une pièce…" autocomplete="off" aria-label="Chercher une pièce"></label>

    @can('dossiers.creer')
      @unless ($affaire->estAnnulee())
        <div class="dn-depot" id="dn-depot" tabindex="0" role="button" onclick="dn_ouvrir_ajout()" onkeydown="if (event.key === 'Enter') { dn_ouvrir_ajout(); }">
          <i class="fa-solid fa-cloud-arrow-up"></i>
          <span><b>Glissez des fichiers ici</b> ou cliquez</span>
          <small>PDF, JPG, PNG, WebP · {{ $dossier['taille_max'] }} au plus par fichier</small>
        </div>
      @endunless
    @endcan

    <div class="dn-liste" id="dn-liste">
      @foreach (\App\Models\PieceDossier::TYPES as $type => [$libelle, $icone])
        @if ($parTypes->has($type))
          <section class="dn-groupe" data-groupe="{{ $type }}">
            <h4><i class="fa-solid {{ $icone }}"></i> {{ $libelle }} <span>{{ $parTypes[$type]->count() }}</span></h4>
            @foreach ($parTypes[$type] as $p)
              <button type="button" class="dn-piece" id="dossier-{{ $p['id'] }}" data-id="{{ $p['id'] }}" data-texte="{{ \Illuminate\Support\Str::lower($p['titre'].' '.$p['type_libelle'].' '.$p['nom']) }}" onclick="dn_selectionner({{ $p['id'] }})">
                <span class="dn-piece__vignette dn-piece__vignette--{{ $p['pdf'] ? 'pdf' : 'image' }}"><i class="fa-solid {{ $p['pdf'] ? 'fa-file-pdf' : 'fa-file-image' }}"></i></span>
                <span class="dn-piece__texte">
                  <b>{{ $p['titre'] }}</b>
                  <small>{{ $p['date'] ?? 'Sans date' }} · {{ $p['taille'] }}</small>
                </span>
              </button>
            @endforeach
          </section>
        @endif
      @endforeach
      @if ($pieces->isEmpty())
        <div class="dn-vide"><i class="fa-regular fa-folder-open"></i><p>Le dossier est vide.</p>@can('dossiers.creer')<small>Ajoutez la première pièce : assignation, conclusions, jugement…</small>@endcan</div>
      @endif
      <div class="dn-aucun" id="dn-aucun" hidden><i class="fa-regular fa-face-frown"></i> Aucune pièce ne correspond.</div>
    </div>

    <footer class="dn-papier">
      <i class="fa-solid fa-box-archive"></i>
      <div>
        <span>Dossier papier</span>
        <b>{{ $affaire->localisation_dossier ?: 'Emplacement non renseigné' }}</b>
      </div>
      @can('dossiers.modifier')
        <button type="button" class="dn-icone" onclick="dn_ouvrir_localisation()" title="Indiquer où se trouve le dossier papier" aria-label="Modifier l'emplacement du dossier papier"><i class="fa-regular fa-pen-to-square"></i></button>
      @endcan
    </footer>
  </aside>

  <div class="dn-vue" id="dn-vue">
    <div class="dn-barre" id="dn-barre" hidden>
      <div class="dn-barre__nav">
        <button type="button" class="dn-icone" onclick="dn_voisine(-1)" title="Pièce précédente (←)" aria-label="Pièce précédente"><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="dn-icone" onclick="dn_voisine(1)" title="Pièce suivante (→)" aria-label="Pièce suivante"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
      <div class="dn-barre__titre"><b id="dn-titre"></b><span id="dn-type"></span></div>
      <div class="dn-barre__outils">
        <span class="dn-outils-image" hidden>
          <button type="button" class="dn-icone" onclick="dn_zoom(-1)" title="Réduire" aria-label="Réduire"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
          <output id="dn-zoom">100 %</output>
          <button type="button" class="dn-icone" onclick="dn_zoom(1)" title="Agrandir" aria-label="Agrandir"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
          <button type="button" class="dn-icone" onclick="dn_zoom(0)" title="Ajuster à la fenêtre" aria-label="Ajuster"><i class="fa-solid fa-expand"></i></button>
          <button type="button" class="dn-icone" onclick="dn_pivoter()" title="Pivoter" aria-label="Pivoter"><i class="fa-solid fa-rotate-right"></i></button>
        </span>
        <button type="button" class="dn-icone" onclick="dn_plein_ecran()" title="Plein écran" aria-label="Plein écran"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
        <button type="button" class="dn-icone" onclick="dn_info()" title="Informations sur la pièce" aria-label="Informations"><i class="fa-solid fa-circle-info"></i></button>
        <a class="dn-icone" id="dn-nouvel-onglet" href="#" target="_blank" rel="noopener" title="Ouvrir dans un nouvel onglet" aria-label="Ouvrir dans un nouvel onglet"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
        @can('dossiers.telecharger')
          <a class="dn-icone" id="dn-telecharger" href="#" title="Télécharger" aria-label="Télécharger"><i class="fa-solid fa-download"></i></a>
        @endcan
        @can('dossiers.modifier')
          <button type="button" class="dn-icone" onclick="dn_ouvrir_modification()" title="Modifier le titre, le type ou la date" aria-label="Modifier"><i class="fa-regular fa-pen-to-square"></i></button>
        @endcan
        @can('dossiers.supprimer')
          <button type="button" class="dn-icone dn-icone--danger" onclick="dn_ouvrir_retrait()" title="Retirer du dossier" aria-label="Retirer du dossier"><i class="fa-regular fa-trash-can"></i></button>
        @endcan
      </div>
    </div>

    <div class="dn-scene" id="dn-scene">
      <div class="dn-accueil">
        <i class="fa-regular fa-folder-open"></i>
        <h3>{{ $pieces->isEmpty() ? 'Aucune pièce dans ce dossier' : 'Choisissez une pièce dans le sommaire' }}</h3>
        <p>{{ $pieces->isEmpty() ? 'Les pièces ajoutées s\'ouvriront ici, directement dans l\'application.' : 'Elle s\'ouvre ici, sans téléchargement. Les flèches du clavier passent d\'une pièce à l\'autre.' }}</p>
      </div>
    </div>

    <aside class="dn-info" id="dn-info" hidden>
      <h4>Informations</h4>
      <dl>
        <dt>Titre</dt><dd id="dn-i-titre"></dd>
        <dt>Type</dt><dd id="dn-i-type"></dd>
        <dt>Date de la pièce</dt><dd id="dn-i-date"></dd>
        <dt>Fichier</dt><dd id="dn-i-fichier"></dd>
        <dt>Taille</dt><dd id="dn-i-taille"></dd>
        <dt>Déposée</dt><dd id="dn-i-depot"></dd>
        <dt>Empreinte (SHA-256)</dt><dd id="dn-i-empreinte" class="dn-mono"></dd>
      </dl>
      @can('dossiers.modifier')
        <h4>Consultations</h4>
        <ul class="dn-consultations" id="dn-consultations"><li class="el-muted">…</li></ul>
      @endcan
    </aside>
  </div>
</div>
