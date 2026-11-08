@php $tons = ['condamne' => 'danger', 'relaxe' => 'success', 'acquitte' => 'success', 'dispense_de_peine' => 'warning']; @endphp
            <tr>
              <td data-order="{{ $l->date_decision->format('Y-m-d') }}"><b>{{ $l->date_decision->format('d/m/Y') }}</b><div class="el-person__sub">{{ $l->numero_decision ? 'n° '.$l->numero_decision : '' }}</div></td>
              <td><b>{{ $l->affaire->numeroAffichage() }}</b><div class="el-person__sub">{{ $l->affaire->chambre?->libelle }}</div></td>
              <td><div class="el-person__name">{{ $l->partie?->libelle() ?? \App\Support\Audiences\SuiviAffaires::parties($l->affaire) }}</div><div class="el-person__sub">{{ $l->affaire->objet }}</div></td>
              <td><x-chip :tone="$tons[$l->issue] ?? 'neutral'" :label="\App\Models\DecisionPenale::ISSUES[$l->issue]" class="el-chip--libre" /><div class="el-person__sub">{{ \App\Models\DecisionPenale::CONTRADICTIONS[$l->contradiction] }}</div></td>
              <td>{{ $l->peineEnClair() ?: '—' }}</td>
              <td data-search="{{ $l->minuteATransmettre() ? 'minute_a_transmettre' : 'minute_transmise' }}">
                @if ($l->minuteATransmettre())
                  <x-chip :tone="$l->minuteEnRetard() ? 'danger' : 'warning'" label="À transmettre" class="el-chip--libre" /><div class="el-person__sub">{{ $l->joursSansMinute() }} j depuis la décision</div>
                @else
                  <x-chip tone="success" label="Transmise" class="el-chip--libre" /><div class="el-person__sub">le {{ $l->date_transmission_minute->format('d/m/Y') }}{{ $l->minute_transmise_a ? ' à '.$l->minute_transmise_a : '' }}</div>
                @endif
              </td>
              <td>
                <div style="display:flex; gap:6px; flex-wrap:nowrap">
                  <a class="el-iconbtn el-iconbtn--blue" href="{{ route('affaires.liste.fiche', $l->affaire_id) }}" title="Fiche de l'affaire" aria-label="Fiche"><i class="fa-regular fa-folder-open"></i></a>
                  @can('repertoire_penal.modifier')
                    <button class="el-iconbtn el-iconbtn--blue" type="button" title="Corriger" aria-label="Corriger" onclick="ouvrir_decision({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                    @if ($l->minuteATransmettre())<button class="el-iconbtn el-iconbtn--green" type="button" title="Enregistrer la transmission de la minute" aria-label="Minute" onclick="ouvrir_minute({{ $l->id }})"><i class="fa-solid fa-file-export"></i></button>@endif
                  @endcan
                  @can('repertoire_penal.supprimer')<button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="supprimer_decision({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>@endcan
                </div>
              </td>
            </tr>
