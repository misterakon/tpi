            @php $a = $l->affaire; @endphp
            <tr id="row-{{ $l->id }}">
              <td data-order="{{ $a->date_rg?->format('Y-m-d') }}"><b>{{ $a->numeroAffichage() }}</b><div class="el-person__sub">{{ $a->chambre?->libelle }}</div></td>
              <td>
                <div class="el-person__name">{{ \App\Support\Audiences\SuiviAffaires::parties($a) }}</div>
                <div class="el-person__sub">{{ $a->objet }}</div>
              </td>
              <td>{{ $l->juge?->nomComplet() ?? '—' }}</td>
              <td data-order="{{ $l->date_reception->format('Y-m-d') }}">{{ $l->date_reception->format('d/m/Y') }}</td>
              <td data-order="{{ $l->date_premiere_audience?->format('Y-m-d') }}">{{ $l->date_premiere_audience?->format('d/m/Y') ?? '—' }}</td>
              <td data-search="{{ $l->statut }}">
                @if ($l->statut === 'cloturee')
                  <x-chip tone="success" label="N° {{ $l->numero_ordonnance_cloture }} du {{ $l->date_cloture->format('d/m/Y') }}" class="el-chip--libre" />
                  <div class="el-person__sub">versée à {{ $l->chambreJugement?->libelle }}, audience du {{ $l->date_audience_jugement?->format('d/m/Y') }}</div>
                @elseif ($l->statut === 'retiree')
                  <x-chip tone="neutral" label="Retirée" class="el-chip--libre" />
                  <div class="el-person__sub" title="{{ $l->motif_retrait }}">{{ $l->motif_retrait }}</div>
                @else
                  <x-chip :tone="$l->estLongue() ? 'danger' : 'info'" :label="$l->estLongue() ? 'En cours, longue' : 'En cours'" class="el-chip--libre" />
                @endif
              </td>
              <td data-order="{{ $l->dureeJours() ?? -1 }}">
                @if ($l->dureeJours() !== null)<b>{{ $l->dureeJours() }} j</b>@if ($l->estOuverte())<div class="el-person__sub">depuis la 1re audience</div>@endif @else — @endif
              </td>
              <td>
                <div style="display:flex; gap:6px; flex-wrap:nowrap">
                  <a class="el-iconbtn el-iconbtn--blue" href="{{ route('affaires.liste.fiche', $a->id) }}" title="Ouvrir la fiche de l'affaire" aria-label="Fiche de l'affaire"><i class="fa-regular fa-folder-open"></i></a>
                  @if ($l->estOuverte())
                    @can('mise_en_etat.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Corriger" aria-label="Corriger" onclick="ouvrir_mee({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                      <button class="el-iconbtn el-iconbtn--green" type="button" title="Clôturer et verser au rôle" aria-label="Clôturer" onclick="ouvrir_cloture_mee({{ $l->id }}, @js($a->numeroAffichage()))"><i class="fa-solid fa-flag-checkered"></i></button>
                    @endcan
                    @can('mise_en_etat.annuler')
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Retirer de la mise en état" aria-label="Retirer" onclick="ouvrir_retrait_mee({{ $l->id }}, @js($a->numeroAffichage()))"><i class="fa-solid fa-ban"></i></button>
                    @endcan
                  @endif
                </div>
              </td>
            </tr>
