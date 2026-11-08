            @php $a = $l['a']; $d = $l['dossier']; @endphp
            <tr>
              <td data-order="{{ $a->date_rg?->format('Y-m-d') }}"><b>{{ $a->numeroAffichage() }}</b><div class="el-person__sub">du {{ $a->date_rg?->format('d/m/Y') }}</div></td>
              <td>{{ $a->chambre?->libelle }}</td>
              <td><div class="el-person__name">{{ $l['inculpes']->map->libelle()->implode(' ; ') ?: '—' }}</div><div class="el-person__sub">{{ $a->objet }}</div></td>
              <td>{{ $a->modeSaisine?->libelle }}<div class="el-person__sub">{{ $d?->date_requisitoire ? 'réquisitoire du '.$d->date_requisitoire->format('d/m/Y') : 'réquisitoire à saisir' }}</div></td>
              <td data-order="{{ $l['detenus'] }}">@if ($l['detenus'])<x-chip tone="danger" :label="$l['detenus'].' détenu(s)'" class="el-chip--libre" />@else — @endif</td>
              <td data-search="{{ $d?->date_cloture ? 'clos' : 'en_cours' }}">
                @if ($d?->date_cloture)<x-chip tone="success" label="Clôturé" class="el-chip--libre" /><div class="el-person__sub">le {{ $d->date_cloture->format('d/m/Y') }}</div>
                @else<x-chip tone="info" label="En cours" class="el-chip--libre" /><div class="el-person__sub">{{ $a->date_rg ? $a->date_rg->diffInDays(today()).' j depuis la saisine' : '' }}</div>@endif
              </td>
              <td><div style="display:flex; gap:6px"><button class="el-iconbtn el-iconbtn--blue" type="button" title="{{ $peutModifier ? 'Compléter le dossier' : 'Voir le dossier' }}" aria-label="Dossier" onclick="ouvrir_dossier({{ $a->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                <a class="el-iconbtn el-iconbtn--blue" href="{{ route('affaires.liste.fiche', $a->id) }}" title="Fiche de l'affaire" aria-label="Fiche"><i class="fa-regular fa-folder-open"></i></a></div></td>
            </tr>
