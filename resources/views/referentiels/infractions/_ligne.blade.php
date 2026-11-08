            <tr id="row-{{ $l->id }}">
              <td><div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div></td>
              <td>{{ $l->articles ?: '—' }}</td>
              <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
              @canany(['infractions.modifier', 'infractions.supprimer'])
                <td>
                  <div style="display:flex; gap:8px">
                    @can('infractions.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_infraction({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                    @endcan
                    @can('infractions.supprimer')
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_infraction({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                    @endcan
                  </div>
                </td>
              @endcanany
            </tr>
