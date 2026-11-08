            <tr id="row-{{ $l->id }}">
              <td data-order="{{ $l->annee }}{{ str_pad($l->numero, 6, '0', STR_PAD_LEFT) }}"><b>{{ $l->numeroAffichage() }}</b></td>
              <td title="{{ \App\Models\Requete::TYPES[$l->type] }}">{{ \App\Models\Requete::TYPES[$l->type] }}</td>
              <td>
                <div class="el-person__name" title="{{ implode(' ; ', $l->listeRequerants()) }}">{{ implode(' ; ', $l->listeRequerants()) }}</div>
                <div class="el-person__sub" title="{{ $l->objet }}">{{ $l->estHeredite() && $l->defunt_nom ? 'Succession de '.$l->defunt_nom.($l->defunt_date_deces ? ' († '.$l->defunt_date_deces->format('d/m/Y').')' : '').' — ' : '' }}{{ $l->objet }}</div>
              </td>
              <td data-order="{{ $l->date_depot->format('Y-m-d') }}">{{ $l->date_depot->format('d/m/Y') }}</td>
              <td data-search="{{ in_array($l->statut, ['traitee', 'classee']) ? 'close' : $l->statut }}">
                <x-chip :tone="['recue' => $l->enRetard() ? 'danger' : 'alert', 'repartie' => 'info', 'traitee' => 'success', 'classee' => 'neutral'][$l->statut]" :label="\App\Models\Requete::STATUTS[$l->statut]" class="el-chip--libre" />
                @if ($l->enRetard())<div class="el-person__sub aff-detenu--depassee">depuis {{ $l->joursSansRepartition() }} jours</div>
                @elseif ($l->reference_decision)<div class="el-person__sub" title="{{ $l->reference_decision }}">{{ $l->reference_decision }}@if ($l->signee_le) · signée le {{ $l->signee_le->format('d/m/Y') }} par {{ $l->signataire?->name }}@endif</div>@elseif ($l->signatureReservee() && $l->statut === 'repartie')<div class="el-person__sub"><i class="fa-solid fa-signature"></i> signature réservée</div>@endif
              </td>
              <td title="{{ $l->note_repartition }}">{{ $l->chambre?->libelle ?? '—' }}@if ($l->reparti_le)<div class="el-person__sub">répartie le {{ $l->reparti_le->format('d/m/Y') }}</div>@endif</td>
              <td>
                <div style="display:flex; gap:6px; flex-wrap:nowrap">
                  @if ($l->estOuverte())
                    @can('requetes.repartir')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="{{ $l->statut === 'recue' ? 'Répartir vers une formation' : 'Répartir à nouveau' }}" aria-label="Répartir" onclick="ouvrir_repartition({{ $l->id }}, @js($l->numeroAffichage()), {{ (int) $l->chambre_id }})"><i class="fa-solid fa-share-nodes"></i></button>
                    @endcan
                    @can('requetes.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Corriger" aria-label="Corriger" onclick="ouvrir_requete({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                      @if ($l->statut === 'repartie' && (! $l->signatureReservee() || auth()->user()->can('requetes.signer')))
                        <button class="el-iconbtn el-iconbtn--green" type="button" title="Marquer comme traitée" aria-label="Traitée" onclick="ouvrir_cloture({{ $l->id }}, @js($l->numeroAffichage()), 'traitee')"><i class="fa-solid fa-check"></i></button>
                      @endif
                    @endcan
                    @can('requetes.annuler')
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Classer sans suite" aria-label="Classer sans suite" onclick="ouvrir_cloture({{ $l->id }}, @js($l->numeroAffichage()), 'classee')"><i class="fa-solid fa-ban"></i></button>
                    @endcan
                  @endif
                </div>
              </td>
            </tr>
