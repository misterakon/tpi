{{-- Tableau des délais de procédure (inclus dans la page Délais du menu Alertes). Variable : $lignes --}}
<div class="el-card">
  <div class="el-toolbar">
    <span class="el-muted">Délais de procédure</span>
    <label class="el-search" style="max-width:300px">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
    </label>
  </div>

  <div class="el-tblwrap">
    <table class="el-tbl el-tbl--fixe" id="tbl-delais_procedure">
      <colgroup><col><col style="width:230px"><col style="width:140px"><col style="width:96px"><col style="width:112px"></colgroup>
      <thead>
        <tr><th>Règle</th><th>Délai</th><th>Validation</th><th>État</th>@canany(['delais_procedure.modifier', 'delais_procedure.supprimer'])<th>Actions</th>@endcanany</tr>
      </thead>
      <tbody>
        @foreach ($lignes as $l)
          <tr id="row-{{ $l->id }}">
            <td>
              <div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div>
              <div class="el-person__sub" title="{{ \App\Models\DelaiProcedure::ETAPES[$l->etape] ?? $l->etape }}">{{ \App\Models\DelaiProcedure::ETAPES[$l->etape] ?? $l->etape }}@if ($l->acteur) · {{ \App\Models\DelaiProcedure::ACTEURS[$l->acteur] ?? $l->acteur }}@endif</div>
            </td>
            <td>
              {{ $l->valeur }} {{ \App\Models\DelaiProcedure::UNITES[$l->unite] ?? $l->unite }}@if ($l->renvois_max !== null) · {{ $l->renvois_max }} renvoi(s) au plus @endif
            </td>
            <td>
              @if ($l->a_valider)<x-chip tone="alert" label="À valider" class="el-chip--libre" />@endif
            </td>
            <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
            @canany(['delais_procedure.modifier', 'delais_procedure.supprimer'])
              <td>
                <div style="display:flex; gap:8px">
                  @can('delais_procedure.modifier')
                    <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_delai_procedure({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                  @endcan
                  @can('delais_procedure.supprimer')
                    <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_delai_procedure({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                  @endcan
                </div>
              </td>
            @endcanany
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
