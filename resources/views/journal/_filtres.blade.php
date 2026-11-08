{{-- Barre de filtres d'un journal (GET). Variables : $route, $filtres, $types (clé => libellé), $libelleType, $avecResultat, $placeholder --}}
<form method="GET" action="{{ route($route) }}" class="el-filtres" role="search">
  <label class="el-filtres__champ"><span>Du</span>
    <input class="form-control" type="date" name="du" value="{{ $filtres['du'] ?? '' }}"></label>
  <label class="el-filtres__champ"><span>Au</span>
    <input class="form-control" type="date" name="au" value="{{ $filtres['au'] ?? '' }}"></label>
  <label class="el-filtres__champ"><span>{{ $libelleType }}</span>
    <select class="form-select" name="type">
      <option value="">Tous</option>
      @foreach ($types as $code => $texte)
        <option value="{{ $code }}" @selected(($filtres['type'] ?? '') === $code)>{{ $texte }}</option>
      @endforeach
    </select></label>
  @if ($avecResultat)
    <label class="el-filtres__champ"><span>Résultat</span>
      <select class="form-select" name="resultat">
        <option value="">Tous</option>
        <option value="succes" @selected(($filtres['resultat'] ?? '') === 'succes')>Réussis</option>
        <option value="echec" @selected(($filtres['resultat'] ?? '') === 'echec')>Échecs</option>
      </select></label>
  @endif
  <label class="el-filtres__champ el-filtres__champ--large"><span>Recherche</span>
    <input class="form-control" type="search" name="q" value="{{ $filtres['q'] ?? '' }}" maxlength="100" placeholder="{{ $placeholder }}" autocomplete="off"></label>
  <div class="el-filtres__actions">
    <button type="submit" class="el-btn el-btn--primary"><i class="fa-solid fa-magnifying-glass"></i> Filtrer</button>
    @if ($filtres)
      <a class="el-btn el-btn--ghost" href="{{ route($route) }}"><i class="fa-solid fa-xmark"></i> Effacer</a>
    @endif
  </div>
</form>
@if ($errors->any())
  <div class="el-reglage__erreur" style="padding:0 24px 12px"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>
@endif
