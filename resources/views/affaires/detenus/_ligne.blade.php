            @php($p = $l)
            @php($a = $l->affaire)
            <tr>
              <td data-order="{{ $a->annee }}{{ str_pad($a->numero, 6, '0', STR_PAD_LEFT) }}"><a href="{{ route('affaires.liste.fiche', $a->id) }}"><b>{{ $a->numeroAffichage() }}</b></a></td>
              <td>
                <div class="el-person__name" title="{{ $p->libelle() }}">{{ $p->libelle() }}</div>
                <div class="el-person__sub" title="{{ $p->identite() }}">{{ $p->identite() ?: ($a->objet) }}</div>
              </td>
              <td title="{{ $a->chambre?->libelle }}">{{ $a->chambre?->libelle }}</td>
              <td data-order="{{ $p->mandat_depot_date->format('Y-m-d') }}">{{ $p->mandat_depot_date->format('d/m/Y') }}</td>
              <td title="{{ $p->lieu_detention }}">{{ $p->lieu_detention ?: '—' }}</td>
              <td data-search="{{ $p->niveauDetention() }}" data-order="{{ $p->joursRestants() }}">
                <x-chip :tone="['depassee' => 'danger', 'proche' => 'alert', 'normal' => 'success'][$p->niveauDetention()]" :label="$p->echeance()->format('d/m/Y')" class="el-chip--libre" />
                <div class="el-person__sub aff-detenu--{{ $p->niveauDetention() }}">{{ $p->joursRestants() < 0 ? 'dépassée de '.abs($p->joursRestants()).' j' : ($p->joursRestants() === 0 ? "aujourd'hui" : 'dans '.$p->joursRestants().' j') }}{{ $p->echeanceCalculee() ? ' · calculée' : '' }}</div>
              </td>
              <td data-order="{{ $a->date_premiere_audience?->format('Y-m-d') }}">{{ $a->date_premiere_audience ? $a->date_premiere_audience->format('d/m/Y').($a->heureAudience() ? ' à '.$a->heureAudience() : '') : '—' }}</td>
            </tr>
