{{-- Une ligne de la liste des affaires. Variable : $l (Affaire). Rendue côté serveur par AffaireController::donnees. --}}
                <tr>
                  <td data-order="{{ $l->annee }}{{ str_pad($l->numero, 6, '0', STR_PAD_LEFT) }}"><a href="{{ route('affaires.liste.fiche', $l->id) }}"><b>{{ $l->numeroAffichage() }}</b></a>@if ($l->parties->contains(fn ($p) => $p->estDetenu()))<i class="fa-solid fa-lock aff-lock" title="Détenu"></i>@endif @can('dossiers.voir') @if ($l->pieces_count)<a class="dn-pj" href="{{ route('affaires.liste.fiche', $l->id) }}#dossier" title="Ouvrir le dossier numérique"><i class="fa-solid fa-paperclip"></i> {{ $l->pieces_count }}</a>@endif @endcan</td>
                  <td data-search="{{ $l->serie }}"><x-chip tone="info" :label="$l->serie" /></td>
                  <td data-order="{{ $l->date_rg->format('Y-m-d') }}{{ $l->created_at?->format('His') }}"><span class="aff-date-liste"><b>{{ $l->date_rg->format('d/m/Y') }}</b><small title="Saisie dans l'application">à {{ $l->created_at?->format('H:i') }}</small></span></td>
                  <td>
                    <div class="el-person__name" title="{{ $l->intitule() }}">{{ $l->intitule() }}</div>
                    <div class="el-person__sub" title="{{ $l->objet }}">{{ $l->objet }}</div>
                  </td>
                  <td>{{ $l->nature?->libelle }}</td>
                  <td title="{{ $l->chambre?->libelle }}">{{ $l->chambre?->libelle }}</td>
                  <td><x-chip :tone="$l->estAnnulee() ? 'danger' : 'success'" :label="\App\Models\Affaire::STATUTS[$l->statut] ?? $l->statut" /></td>
                </tr>
