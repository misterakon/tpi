            <tr id="row-{{ $l->id }}">
              <td>
                <div class="el-person__name" title="{{ $l->nom }}">{{ $l->nom }}</div>
                <div class="el-person__sub" title="{{ \App\Support\TexteRiche::texte($l->contenu) }}">{{ \App\Support\TexteRiche::texte($l->contenu) }}</div>
              </td>
              <td><code title="{{ $l->code }}">{{ $l->code }}</code></td>
              <td><x-chip tone="info" :label="\App\Models\BlocTexte::CATEGORIES[$l->categorie] ?? $l->categorie" class="el-chip--libre" /></td>
              @php $nb = count($usages[$l->id] ?? []); @endphp
              <td data-order="{{ $nb }}">
                @if ($nb)
                  <button type="button" class="el-chip el-chip--info bloc-usage" data-bloc="{{ $l->nom }}" data-modeles='@json($usages[$l->id])' onclick="voir_modeles(this)" title="Voir les modèles"><i></i>{{ $nb }} {{ $nb > 1 ? 'modèles' : 'modèle' }}</button>
                @else
                  <span class="el-chip el-chip--neutral bloc-usage" title="Aucun modèle n’utilise ce bloc."><i></i>Aucun</span>
                @endif
              </td>
              <td data-order="{{ (int) $l->actif }}"><x-chip :tone="$l->actif ? 'success' : 'neutral'" :label="$l->actif ? 'Actif' : 'Inactif'" /></td>
              @canany(['blocs.modifier', 'blocs.supprimer'])
                <td>
                  <div style="display:flex; gap:8px">
                    @can('blocs.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_bloc({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                    @endcan
                    @can('blocs.supprimer')
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_bloc({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                    @endcan
                  </div>
                </td>
              @endcanany
            </tr>
