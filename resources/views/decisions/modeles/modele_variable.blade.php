<template id="modele-variable">
  <div class="mdl-ligne">
    <div><label class="form-label">Code</label><input class="form-control" name="variables[__I__][code]" maxlength="60" placeholder="defunt.nom" required></div>
    <div><label class="form-label">Libellé</label><input class="form-control" name="variables[__I__][libelle]" maxlength="160" placeholder="Défunt : nom" required></div>
    <div><label class="form-label">Type</label>
      <select class="form-select" name="variables[__I__][type]" data-sans-recherche>
        @foreach (\App\Support\Redaction\VariablesDecision::TYPES as $valeur => $texte)
          <option value="{{ $valeur }}">{{ $texte }}</option>
        @endforeach
      </select></div>
    <div><label class="form-label">Exemple (fictif)</label><input class="form-control" name="variables[__I__][exemple]" maxlength="2000"></div>
    <button class="el-iconbtn el-iconbtn--red" type="button" title="Retirer" aria-label="Retirer" onclick="retirer_variable(this)"><i class="fa-regular fa-trash-can"></i></button>
  </div>
</template>
