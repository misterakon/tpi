@php $tons = ['a_signifier' => 'warning', 'en_cours' => 'info', 'expire' => 'success', 'recours' => 'danger', 'certifie' => 'success', 'sans_delai' => 'neutral']; @endphp
            @php $d = $l['d']; $e = $l['e']; $a = $d->affaire; @endphp
            <tr>
              <td data-order="{{ $d->date_signature?->format('Y-m-d') }}"><b>{{ $d->numeroAffichage() ?? '—' }}</b><div class="el-person__sub">{{ $d->date_signature?->format('d/m/Y') }}</div></td>
              <td><b>{{ $a?->numeroAffichage() }}</b><div class="el-person__sub">{{ $a?->chambre?->libelle }}</div></td>
              <td><div class="el-person__name">{{ $a ? \App\Support\Audiences\SuiviAffaires::parties($a) : '' }}</div><div class="el-person__sub">{{ $a?->objet }}</div></td>
              <td data-order="{{ $e['signification']?->format('Y-m-d') }}">{{ $e['signification']?->format('d/m/Y') ?? '—' }}<div class="el-person__sub">{{ $e['nature'] === 'opposition' ? 'opposition' : 'appel' }}{{ $e['delai'] ? ' · '.$e['delai']->libelleDelai() : '' }}</div></td>
              <td data-search="{{ $e['etat'] }}">
                <x-chip :tone="$tons[$e['etat']]" :label="\App\Support\Recours\DelaisRecours::ETATS[$e['etat']]" class="el-chip--libre" />
                @if ($e['certificat'])<div class="el-person__sub">n° {{ $e['certificat']->numeroAffichage() }} du {{ $e['certificat']->date_delivrance->format('d/m/Y') }}</div>@endif
                @foreach ($l['recours'] as $r)<div class="el-person__sub">{{ \App\Models\Recours::NATURES[$r->nature] }} du {{ $r->date_recours->format('d/m/Y') }} · {{ \App\Models\Recours::STATUTS[$r->statut] }}</div>@endforeach
              </td>
              <td data-order="{{ $e['echeance']?->format('Y-m-d') }}">
                @if ($e['echeance']) {{ $e['echeance']->format('d/m/Y') }}@if ($e['etat'] === 'en_cours')<div class="el-person__sub">{{ $e['jours_restants'] }} j restants</div>@endif @else — @endif
              </td>
              <td>
                <div style="display:flex; gap:6px; flex-wrap:nowrap">
                  @if ($e['etat'] !== 'certifie')
                    @can('recours.modifier')<button class="el-iconbtn el-iconbtn--blue" type="button" title="Enregistrer la signification" aria-label="Signification" onclick='ouvrir_signif({{ $d->id }}, @js($d->numeroAffichage()), @js($e["signification"]?->format("Y-m-d")), @js($e["nature"]))'><i class="fa-solid fa-envelope-open-text"></i></button>@endcan
                    @can('recours.creer')<button class="el-iconbtn el-iconbtn--red" type="button" title="Enregistrer un recours" aria-label="Recours" onclick='ouvrir_recours({{ $d->id }}, @js($d->numeroAffichage()))'><i class="fa-solid fa-gavel"></i></button>@endcan
                    @if ($peutCertifier && $e['certifiable'])<button class="el-iconbtn el-iconbtn--green" type="button" title="Délivrer le certificat" aria-label="Certificat" onclick='ouvrir_certificat({{ $d->id }}, @js($d->numeroAffichage()))'><i class="fa-solid fa-stamp"></i></button>@endif
                    @foreach ($l['recours']->where('statut', 'forme') as $r)
                      @can('recours.modifier')<button class="el-iconbtn el-iconbtn--blue" type="button" title="Transmettre à la Cour d'appel" aria-label="Transmettre" onclick='ouvrir_transmission({{ $r->id }})'><i class="fa-solid fa-paper-plane"></i></button>@endcan
                    @endforeach
                    @foreach ($l['recours']->where('statut', '!=', 'abandonne') as $r)
                      @can('recours.annuler')<button class="el-iconbtn el-iconbtn--red" type="button" title="Marquer le recours comme abandonné" aria-label="Abandon" onclick='abandonner_recours({{ $r->id }})'><i class="fa-solid fa-ban"></i></button>@endcan
                    @endforeach
                  @else
                    <a class="el-iconbtn el-iconbtn--green" href="{{ route('decisions.recours.imprimer', $e['certificat']->id) }}" target="_blank" title="Imprimer le certificat" aria-label="Imprimer"><i class="fa-solid fa-print"></i></a>
                  @endif
                </div>
              </td>
            </tr>
