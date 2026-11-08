{{-- Un champ du formulaire de la décision, adapté au type de la variable. Variables : $champ, $valeurs (valeurs actuelles, à plat), $lecture. --}}
@php
  $code = $champ['code'];
  $valeur = $valeurs[$code] ?? null;
  $nom = 'valeurs['.$code.']';
  $id = 'v_'.preg_replace('/[^a-z0-9]+/i', '_', $code);
  $court = $court ?? false;
  // Texte d'aide dans le champ : l'exemple fictif du modèle quand il est court, sinon une consigne selon le type.
  $exemple = $champ['exemple'] ?? null;
  $indice = (is_string($exemple) && $exemple !== '' && mb_strlen($exemple) <= 70 && ! in_array($champ['type'], ['liste', 'personne', 'personnes', 'genre', 'date', 'naissance'], true)) ? 'Ex. : '.$exemple : '';   // dans une rangée (composition) : étiquette courte, sans le nom de la personne
  $etiquette = $champ['libelle'];
  if ($court) {
      $etiquette = ucfirst(trim(\Illuminate\Support\Str::after($champ['libelle'], ' : ')));
      $etiquette = $etiquette === 'Nom' ? 'Nom et prénoms' : $etiquette;
  }
@endphp
<div class="red-champ {{ in_array($champ['type'], ['liste', 'personne', 'personnes'], true) ? 'red-champ--large' : '' }} {{ $code === 'demandeur.nom' ? 'red-champ--double' : '' }} {{ in_array($code, ['demandeur.domicile', 'demandeur.comparution'], true) ? 'red-champ--moitie' : '' }}" data-code="{{ $code }}" data-type="{{ $champ['type'] }}">
  @unless ($court && $champ['type'] === 'personnes')
    <label class="form-label" for="{{ $id }}"><b>{{ $etiquette }}</b></label>
  @endunless

  @switch($champ['type'])
    @case('date')
      <input class="form-control" id="{{ $id }}" name="{{ $nom }}" type="date" value="{{ $valeur }}" @disabled($lecture)>
      @break

    @case('nombre')
      <input class="form-control" id="{{ $id }}" name="{{ $nom }}" type="number" min="0" step="1" value="{{ $valeur }}" placeholder="{{ $indice ?: 'Un nombre' }}" @disabled($lecture)>
      @break

    @case('genre')
      <select class="form-select" id="{{ $id }}" name="{{ $nom }}" data-sans-recherche @disabled($lecture)>
        <option value="">Choisir…</option>
        <option value="m" @selected($valeur === 'm')>Masculin</option>
        <option value="f" @selected($valeur === 'f')>Féminin</option>
      </select>
      @break

    @case('liste')
      <textarea class="form-control" id="{{ $id }}" name="{{ $nom }}" rows="3" placeholder="Un élément par ligne" @disabled($lecture)>{{ is_array($valeur) ? implode("\n", $valeur) : $valeur }}</textarea>
      <span class="red-aide">Une personne ou un élément par ligne.</span>
      @break

    @case('naissance')
      @php
        $mode = is_array($valeur) ? ($valeur['mode'] ?? 'date') : 'date';
        $date = is_array($valeur) ? ($valeur['date'] ?? '') : '';
        $annee = is_array($valeur) ? ($valeur['annee'] ?? '') : '';
      @endphp
      <div class="red-naissance" data-mode="{{ $mode }}">
        <input type="hidden" name="{{ $nom }}[mode]" value="{{ $mode }}">
        @unless ($lecture)
          <button type="button" class="red-naissance__lien" onclick="basculer_naissance(this)" title="Si le jour de naissance n'est pas connu, saisir seulement l'année (« vers 1970 »)">{{ $mode === 'annee' ? 'Saisir la date exacte' : "Jour inconnu : saisir l'année seulement" }}</button>
        @endunless
        <input class="form-control" name="{{ $nom }}[date]" type="date" value="{{ $date }}" @if ($mode === 'annee') hidden @endif @disabled($lecture)>
        <input class="form-control" name="{{ $nom }}[annee]" type="number" min="1800" max="{{ date('Y') }}" step="1" value="{{ $annee }}" placeholder="Année, ex. : 1970" @if ($mode !== 'annee') hidden @endif @disabled($lecture)>
      </div>
      @break

    @case('personne')
      <div class="red-personne">
        <input class="form-control" name="{{ $nom }}[nom]" type="text" maxlength="200" placeholder="Nom et prénoms (ex. : KOUASSI Yao)" value="{{ $valeur['nom'] ?? '' }}" @disabled($lecture)>
        <select class="form-select" name="{{ $nom }}[genre]" data-sans-recherche @disabled($lecture)>
          <option value="">Genre</option>
          <option value="m" @selected(($valeur['genre'] ?? '') === 'm')>Masculin</option>
          <option value="f" @selected(($valeur['genre'] ?? '') === 'f')>Féminin</option>
        </select>
      </div>
      @break

    @case('personnes')
      @php
        $lignes = array_pad(array_values(is_array($valeur) ? $valeur : []), 8, []);   // jusqu'à 8 lignes ; les vides au-delà de la première libre sont masquées
        $remplies = count(array_filter($lignes, fn ($p) => ! empty($p['nom'])));
        $visibles = max(2, $remplies);
      @endphp
      <div class="red-personnes">
        @foreach ($lignes as $i => $p)
          <div class="red-personne" @if ($i >= $visibles) hidden @endif>
            <input class="form-control" name="{{ $nom }}[{{ $i }}][nom]" type="text" maxlength="200" placeholder="Nom et prénoms (ex. : KOUASSI Yao)" value="{{ $p['nom'] ?? '' }}" @disabled($lecture)>
            <select class="form-select" name="{{ $nom }}[{{ $i }}][genre]" data-sans-recherche @disabled($lecture)>
              <option value="">Genre</option>
              <option value="m" @selected(($p['genre'] ?? '') === 'm')>Masculin</option>
              <option value="f" @selected(($p['genre'] ?? '') === 'f')>Féminin</option>
            </select>
          </div>
        @endforeach
        @unless ($lecture)
          <button type="button" class="red-ajouter" onclick="ajouter_personne(this)" @if ($visibles >= 8) hidden @endif><i class="fa-solid fa-plus"></i> Ajouter une personne</button>
        @endunless
      </div>
      @break

    @default
      @if (! empty($champ['options']))
        <select class="form-select" id="{{ $id }}" name="{{ $nom }}" @if (count($champ['options']) <= 10) data-sans-recherche @endif @disabled($lecture)>
          <option value="">Choisir…</option>
          @foreach ($champ['options'] as $cle => $texte)
            @php $option = array_is_list($champ['options']) ? $texte : $cle; @endphp
            <option value="{{ $option }}" @selected($valeur === $option)>{{ $texte }}</option>
          @endforeach
          @if ($valeur && ! array_key_exists($valeur, array_is_list($champ['options']) ? array_flip($champ['options']) : $champ['options']))
            <option value="{{ $valeur }}" selected>{{ $valeur }}</option>
          @endif
        </select>
      @else
        <input class="form-control" id="{{ $id }}" name="{{ $nom }}" type="text" maxlength="500" value="{{ $valeur }}" placeholder="{{ $indice }}" @disabled($lecture)>
      @endif
  @endswitch
</div>
