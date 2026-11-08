{{-- Une partie (demandeur ou défendeur) du formulaire. Variables : $i (indice), $role, $p (valeurs saisies), $nationalites, $erreurs (bag) --}}
@php
  $nature = $p['nature'] ?? 'homme';
  $physique = in_array($nature, ['homme', 'femme'], true);
  $nom = "parties[$i]";
  $erreur = fn ($champ) => $erreurs->first("parties.$i.$champ");
  $icones = ['homme' => 'fa-person', 'femme' => 'fa-person-dress', 'couple' => 'fa-user-group', 'morale' => 'fa-building', 'etat' => 'fa-landmark', 'mp' => 'fa-scale-balanced'];
@endphp
<div class="aff-partie aff-partie--{{ $role }}" data-type="{{ $physique ? 'physique' : 'autre' }}" data-indice="{{ $i }}">
  <input type="hidden" name="{{ $nom }}[role]" value="{{ $role }}">

  <div class="aff-partie__tete">
    <span class="aff-partie__titre">{{ \App\Models\Partie::ROLES[$role] ?? '__TITRE__' }} n° <span class="aff-partie__num"></span></span>
    <button type="button" class="aff-retirer" onclick="retirer_partie(this)" title="Retirer cette partie" aria-label="Retirer cette partie"><i class="fa-regular fa-trash-can"></i></button>
  </div>

  <div class="el-formgrid">
    <div class="el-c3">
      <label class="form-label"><b>Nature de la partie</b></label>
      <select class="form-select" name="{{ $nom }}[nature]" data-sans-recherche onchange="changer_nature(this)">
        @foreach (\App\Models\Partie::NATURES as $code => $libelle)
          <option value="{{ $code }}" @selected($nature === $code)>{{ $libelle }}</option>
        @endforeach
      </select>
    </div>

    <div class="el-c9 aff-autre">
      <label class="form-label"><b>Dénomination</b></label>
      <input class="form-control @if ($erreur('denomination')) is-invalid @endif" name="{{ $nom }}[denomination]" type="text" maxlength="250" value="{{ $p['denomination'] ?? '' }}" placeholder="Ex. : Société ABC SARL, ou M. et Mme KONE">
      @if ($erreur('denomination')) <div class="aff-erreur">{{ $erreur('denomination') }}</div> @endif
    </div>

    <div class="el-c4 aff-physique">
      <label class="form-label"><b>Nom</b></label>
      <input class="form-control @if ($erreur('nom')) is-invalid @endif" name="{{ $nom }}[nom]" type="text" maxlength="160" value="{{ $p['nom'] ?? '' }}" placeholder="Ex. : KOUASSI">
      @if ($erreur('nom')) <div class="aff-erreur">{{ $erreur('nom') }}</div> @endif
    </div>
    <div class="el-c5 aff-physique">
      <label class="form-label"><b>Prénoms</b></label>
      <input class="form-control" name="{{ $nom }}[prenoms]" type="text" maxlength="200" value="{{ $p['prenoms'] ?? '' }}" placeholder="Ex. : Yao Marc">
    </div>

    <div class="el-c3 aff-physique">
      <label class="form-label"><b>Date de naissance</b></label>
      <input class="form-control" name="{{ $nom }}[naissance_date]" type="date" max="{{ date('Y-m-d') }}" value="{{ $p['naissance_date'] ?? '' }}">
    </div>
    <div class="el-c3 aff-physique">
      <label class="form-label"><b>Ou année seule</b></label>
      <input class="form-control" name="{{ $nom }}[naissance_annee]" type="number" min="1800" max="{{ date('Y') }}" value="{{ $p['naissance_annee'] ?? '' }}" placeholder="« né vers 1970 »">
    </div>
    <div class="el-c3 aff-physique">
      <label class="form-label"><b>Lieu de naissance</b></label>
      <input class="form-control" name="{{ $nom }}[lieu_naissance]" type="text" maxlength="120" value="{{ $p['lieu_naissance'] ?? '' }}" placeholder="Ex. : Bouaké">
    </div>
    <div class="el-c3 aff-physique">
      <label class="form-label"><b>Nationalité</b></label>
      <select class="form-select" name="{{ $nom }}[nationalite]">
        <option value="">Choisir…</option>
        @foreach ($nationalites as $n)
          <option value="{{ $n }}" @selected(($p['nationalite'] ?? '') === $n)>{{ $n }}</option>
        @endforeach
      </select>
    </div>

    <div class="el-c4 aff-physique">
      <label class="form-label"><b>Profession</b></label>
      <input class="form-control" name="{{ $nom }}[profession]" type="text" maxlength="120" value="{{ $p['profession'] ?? '' }}" placeholder="Ex. : Commerçant">
    </div>
    <div class="el-c8 aff-domicile">
      <label class="form-label"><b>Domicile ou siège</b></label>
      <input class="form-control" name="{{ $nom }}[domicile]" type="text" maxlength="250" value="{{ $p['domicile'] ?? '' }}" placeholder="Ex. : Bingerville, quartier Adjamé">
    </div>

    <div class="el-c12">
      <label class="form-label"><b>Avocat ou cabinet</b> <span class="el-muted">(facultatif)</span></label>
      @include('affaires.enrolement.champ_avocat')
    </div>

    {{-- « et 19 autres » : seulement pour un grand groupe, sur demande --}}
    <div class="el-c12 aff-autres" @if (empty($p['autres'])) data-ferme="1" @endif>
      <button type="button" class="aff-autres__lien" onclick="afficher_autres(this)">+ D'autres personnes sont dans la même situation (« et 19 autres »)</button>
      <div class="aff-autres__champ">
        <label class="form-label"><b>Nombre d'autres personnes</b> <span class="el-muted">(écrit « et 19 autres » dans les listes et les rôles)</span></label>
        <input class="form-control" name="{{ $nom }}[autres]" type="number" min="0" max="999" value="{{ $p['autres'] ?? '' }}" placeholder="Ex. : 19">
      </div>
    </div>
  </div>
</div>
