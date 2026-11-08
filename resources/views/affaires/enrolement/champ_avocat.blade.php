{{-- Choix de l'avocat dans le référentiel ; un nom absent de la liste se tape tel quel (fiche « à compléter » créée à l'enregistrement). Variables : $nom, $p --}}
@php
  $avocatsListe = \App\Models\Avocat::where('actif', true)->orderBy('nom')->orderBy('prenoms')->get()->map->libelle()->all();
  $avocatSaisi = trim((string) ($p['avocat'] ?? ''));
  if ($avocatSaisi !== '' && ! in_array($avocatSaisi, $avocatsListe, true)) { $avocatsListe[] = $avocatSaisi; }
@endphp
<select class="form-select" name="{{ $nom }}[avocat]" data-libre data-libre-texte="Nouvel avocat">
  <option value="">Aucun avocat</option>
  @foreach ($avocatsListe as $libelleAvocat)
    <option value="{{ $libelleAvocat }}" @selected($libelleAvocat === $avocatSaisi)>{{ $libelleAvocat }}</option>
  @endforeach
</select>
<small class="el-muted">Absent de la liste ? Ouvrez la liste, tapez son nom (ex. « Maître KOUA Awa »), puis choisissez la ligne « … (nouvel avocat) ».</small>
