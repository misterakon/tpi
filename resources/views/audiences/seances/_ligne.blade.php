@php $tons = ['prevue' => 'info', 'tenue' => 'success', 'annulee' => 'neutral']; @endphp
            @php $donnees = ['id' => $s->id, 'type' => $s->type, 'chambre_id' => $s->chambre_id, 'date' => $s->date_seance->format('Y-m-d'), 'heure' => $s->heure, 'lieu' => $s->lieu, 'juge_id' => $s->juge_id, 'greffier_id' => $s->greffier_id, 'observations' => $s->observations]; @endphp
            <tr>
              <td data-order="{{ $s->date_seance->format('Y-m-d') }}"><b>{{ $s->date_seance->format('d/m/Y') }}</b><div class="el-person__sub">{{ $s->heure }} {{ $s->lieu }}</div></td>
              <td><b>{{ \App\Models\SeanceHorsAudience::TYPES[$s->type] }}</b><div class="el-person__sub">{{ $s->chambre?->libelle ?? 'Toutes chambres' }}</div></td>
              <td>{{ $s->juge?->nomComplet() ?? '—' }}<div class="el-person__sub">{{ $s->greffier?->nomComplet() }}</div></td>
              <td>
                @forelse ($s->dossiers as $l)
                  <div class="el-person__sub" style="display:flex; gap:6px; align-items:center">
                    <span style="flex:1"><b>{{ $l->affaire?->numeroAffichage() }}</b> {{ $l->objet }}@if ($l->issue) <i>→ {{ $l->issue }}</i>@endif</span>
                    @can('seances.modifier')@if ($s->statut !== 'annulee')<button class="el-iconbtn el-iconbtn--red" type="button" title="Retirer ce dossier" aria-label="Retirer" onclick="retirer_dossier({{ $l->id }})"><i class="fa-solid fa-xmark"></i></button>@endif @endcan
                  </div>
                @empty — @endforelse
              </td>
              <td data-search="{{ $s->statut }}"><x-chip :tone="$tons[$s->statut]" :label="\App\Models\SeanceHorsAudience::STATUTS[$s->statut]" class="el-chip--libre" /></td>
              <td>
                <div style="display:flex; gap:6px; flex-wrap:nowrap">
                  @if ($s->statut !== 'annulee')
                    @can('seances.modifier')<button class="el-iconbtn el-iconbtn--blue" type="button" title="Ajouter un dossier" aria-label="Ajouter un dossier" onclick="ouvrir_dossier({{ $s->id }})"><i class="fa-solid fa-folder-plus"></i></button>@endcan
                  @endif
                  @if ($s->statut === 'prevue')
                    @can('seances.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Corriger" aria-label="Corriger" onclick='ouvrir_seance({{ Illuminate\Support\Js::from($donnees) }})'><i class="fa-regular fa-pen-to-square"></i></button>
                      <button class="el-iconbtn el-iconbtn--green" type="button" title="Déclarer tenue" aria-label="Tenue" onclick="changer_statut({{ $s->id }}, 'tenue')"><i class="fa-solid fa-check"></i></button>
                    @endcan
                    @can('seances.annuler')<button class="el-iconbtn el-iconbtn--red" type="button" title="Annuler la séance" aria-label="Annuler" onclick="changer_statut({{ $s->id }}, 'annulee')"><i class="fa-solid fa-ban"></i></button>@endcan
                  @endif
                </div>
              </td>
            </tr>
