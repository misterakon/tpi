            <tr id="row-{{ $l->id }}">
              <td>
                <div class="el-person__name" title="{{ $l->libelle }}">{{ $l->libelle }}</div>
                <div class="el-person__sub" title="{{ $l->chambre_libelle }}">{{ $l->chambre_libelle }}</div>
              </td>
              <td>
                <x-chip tone="info" :label="$l->matiere?->libelle" class="el-chip--libre" />
              </td>
              <td>
                {{ $l->nature?->libelle }}
              </td>
              <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
              @canany(['objets_litige.modifier', 'objets_litige.supprimer'])
                <td>
                  <div style="display:flex; gap:8px">
                    @can('objets_litige.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_objet_litige({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                    @endcan
                    @can('objets_litige.supprimer')
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_objet_litige({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                    @endcan
                  </div>
                </td>
              @endcanany
            </tr>
