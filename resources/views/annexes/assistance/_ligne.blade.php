            <tr>
              <td data-order="{{ $l->date_rg->format('Y-m-d') }}"><b>{{ $l->numero }}</b><div class="el-person__sub">{{ $l->date_rg->format('d/m/Y') }}</div></td>
              <td><div class="el-person__name">{{ $l->demandeur }}</div><div class="el-person__sub">{{ $l->objet }}</div></td>
              <td>{{ $l->nature ?: '—' }}</td>
              <td data-order="{{ $l->date_audience?->format('Y-m-d') }}">{{ $l->date_audience?->format('d/m/Y') ?? '—' }}</td>
              <td data-search="{{ $l->decision ?? 'a_traiter' }}">
                @if ($l->decision)<x-chip :tone="$l->decision === 'accord' ? 'success' : 'neutral'" :label="$decisions[$l->decision]" class="el-chip--libre" /><div class="el-person__sub">{{ $l->numero_decision ? 'n° '.$l->numero_decision.' · ' : '' }}{{ $l->date_decision?->format('d/m/Y') }}{{ $l->dureeJours() !== null ? ' · '.$l->dureeJours().' j' : '' }}</div>
                @else<x-chip tone="warning" label="À traiter" class="el-chip--libre" />@endif
              </td>
              <td>{{ $l->date_recours ? $l->date_recours->format('d/m/Y') : '—' }}@if ($l->date_transmission_recours)<div class="el-person__sub">transmis à la DACP le {{ $l->date_transmission_recours->format('d/m/Y') }}</div>@endif</td>
              <td><div style="display:flex; gap:6px">
                @if ($peutModifier)<button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="ouvrir_aj({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>@endif
                @if ($peutSupprimer)<button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="supprimer_aj({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>@endif
              </div></td>
            </tr>
