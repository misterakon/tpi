            <tr id="row-{{ $l->id }}">
              <td>
                <div class="el-person__name" title="{{ $l->libelle() }}">{{ $l->libelle() }}</div>
                <div class="el-person__sub">{{ \App\Models\Avocat::TYPES[$l->type] ?? $l->type }}</div>
              </td>
              <td>
                <div class="el-person__name" title="{{ $l->barreau_numero }}">{{ $l->barreau_numero ?: '—' }}</div>
                <div class="el-person__sub" title="{{ $l->cabinet }}">{{ $l->cabinet }}</div>
              </td>
              <td>
                <div class="el-person__name">{{ $l->telephone ?: '—' }}</div>
                <div class="el-person__sub" title="{{ $l->email }}">{{ $l->email }}</div>
              </td>
              <td data-order="{{ $l->parties_count }}">{{ $l->parties_count }}</td>
              <td data-search="{{ $l->a_completer ? 'completer' : '' }} {{ $l->actif ? '' : 'inactif' }}" data-order="{{ (int) $l->actif }}">
                @if ($l->a_completer)<x-chip tone="alert" label="À compléter" class="el-chip--libre" />
                @else<x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" />@endif
              </td>
              @canany(['avocats.modifier', 'avocats.supprimer'])
                <td>
                  <div style="display:flex; gap:8px">
                    @can('avocats.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_avocat({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                    @endcan
                    @can('avocats.supprimer')
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_avocat({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                    @endcan
                  </div>
                </td>
              @endcanany
            </tr>
