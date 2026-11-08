{{-- Une personne poursuivie (prévenu ou inculpé) ou une victime du formulaire pénal et d'instruction. Variables : $i, $role (prevenu | inculpe | victime), $p, $nationalites, $erreurs --}}
@php
  $nature = $p['nature'] ?? 'homme';
  $physique = in_array($nature, ['homme', 'femme'], true);
  $poursuivi = in_array($role, ['prevenu', 'inculpe'], true);
  $nom = "parties[$i]";
  $erreur = fn ($champ) => $erreurs->first("parties.$i.$champ");
  $natures = ['homme' => 'Homme', 'femme' => 'Femme', 'morale' => 'Personne morale'];
@endphp
<div class="aff-partie aff-partie--{{ $poursuivi ? 'defendeur' : 'demandeur' }}" data-type="{{ $physique ? 'physique' : 'autre' }}" data-indice="{{ $i }}">
  <input type="hidden" name="{{ $nom }}[role]" value="{{ $role }}">

  <div class="aff-partie__tete">
    <span class="aff-partie__titre">{{ \App\Models\Partie::ROLES[$role] ?? '__TITRE__' }} n° <span class="aff-partie__num"></span></span>
    <button type="button" class="aff-retirer" onclick="retirer_partie_poursuite(this)" title="Retirer cette personne" aria-label="Retirer cette personne"><i class="fa-regular fa-trash-can"></i></button>
  </div>

  <div class="el-formgrid">
    <div class="el-c3">
      <label class="form-label"><b>Nature</b></label>
      <select class="form-select" name="{{ $nom }}[nature]" data-sans-recherche onchange="changer_nature_poursuite(this)">
        @foreach ($natures as $code => $libelle)
          <option value="{{ $code }}" @selected($nature === $code)>{{ $libelle }}</option>
        @endforeach
      </select>
    </div>

    <div class="el-c9 aff-autre">
      <label class="form-label"><b>Dénomination</b></label>
      <input class="form-control @if ($erreur('denomination')) is-invalid @endif" name="{{ $nom }}[denomination]" type="text" maxlength="250" value="{{ $p['denomination'] ?? '' }}" placeholder="Ex. : Société ABC SARL">
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
      <input class="form-control" name="{{ $nom }}[naissance_annee]" type="number" min="1800" max="{{ date('Y') }}" value="{{ $p['naissance_annee'] ?? '' }}" placeholder="« né vers 1990 »">
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
      <input class="form-control" name="{{ $nom }}[profession]" type="text" maxlength="120" value="{{ $p['profession'] ?? '' }}" placeholder="Ex. : Maçon">
    </div>
    <div class="el-c8 aff-domicile">
      <label class="form-label"><b>Domicile ou siège</b></label>
      <input class="form-control" name="{{ $nom }}[domicile]" type="text" maxlength="250" value="{{ $p['domicile'] ?? '' }}" placeholder="Ex. : Bingerville, quartier Adjamé">
    </div>

    @if ($poursuivi)
      <div class="el-c4">
        <label class="form-label"><b>Mandat de dépôt</b> <span class="el-muted">(si détenu)</span></label>
        <input class="form-control @if ($erreur('mandat_depot_date')) is-invalid @endif" name="{{ $nom }}[mandat_depot_date]" type="date" max="{{ date('Y-m-d') }}" value="{{ $p['mandat_depot_date'] ?? '' }}">
        @if ($erreur('mandat_depot_date')) <div class="aff-erreur">{{ $erreur('mandat_depot_date') }}</div> @endif
      </div>
      <div class="el-c4">
        <label class="form-label"><b>Échéance du mandat</b> <span class="el-muted">(sinon calculée)</span></label>
        <input class="form-control @if ($erreur('echeance_detention')) is-invalid @endif" name="{{ $nom }}[echeance_detention]" type="date" value="{{ $p['echeance_detention'] ?? '' }}">
        @if ($erreur('echeance_detention')) <div class="aff-erreur">{{ $erreur('echeance_detention') }}</div> @endif
      </div>
      <div class="el-c4">
        <label class="form-label"><b>Mis en liberté le</b></label>
        <input class="form-control @if ($erreur('libere_le')) is-invalid @endif" name="{{ $nom }}[libere_le]" type="date" max="{{ date('Y-m-d') }}" value="{{ $p['libere_le'] ?? '' }}">
        @if ($erreur('libere_le')) <div class="aff-erreur">{{ $erreur('libere_le') }}</div> @endif
      </div>
      <div class="el-c6">
        <label class="form-label"><b>Lieu de détention</b> <span class="el-muted">(si détenu)</span></label>
        <input class="form-control" name="{{ $nom }}[lieu_detention]" type="text" maxlength="160" value="{{ $p['lieu_detention'] ?? '' }}" placeholder="Ex. : Maison d'arrêt et de correction d'Abidjan">
      </div>
      <div class="el-c6">
        <label class="form-label"><b>Avocat</b> <span class="el-muted">(facultatif)</span></label>
        @include('affaires.enrolement.champ_avocat')
      </div>
    @else
      <div class="el-c12">
        <label class="form-label"><b>Avocat</b> <span class="el-muted">(facultatif)</span></label>
        @include('affaires.enrolement.champ_avocat')
      </div>
    @endif
  </div>
</div>
