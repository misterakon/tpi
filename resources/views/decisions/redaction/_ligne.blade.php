            <tr id="row-{{ $l->id }}">
              <td>
                <div class="el-person__name" title="{{ $l->reference }}">{{ $l->reference }}@if ($l->numeroAffichage()) <span class="el-muted" style="font-weight:600">· N° {{ $l->numeroAffichage() }}</span>@endif</div>
                <div class="el-person__sub" title="{{ $l->modele?->nom }}">{{ $l->modele?->nom }}</div>
              </td>
              <td data-order="{{ $l->version_numero }}">v{{ $l->version_numero }}</td>
              <td data-order="{{ $l->statut }}">
                <x-chip :tone="['brouillon' => 'alert', 'redigee' => 'info', 'validee' => 'success', 'signee' => 'purple', 'delivree' => 'gold'][$l->statut] ?? 'neutral'" :label="\App\Models\Decision::STATUTS[$l->statut] ?? $l->statut" />
              </td>
              <td data-order="{{ $l->updated_at?->timestamp }}">{{ $l->updated_at?->format('d/m/Y H:i') }}</td>
              <td>
                <div style="display:flex; gap:8px">
                  <a class="el-iconbtn el-iconbtn--blue" href="{{ route('decisions.redaction.edition', $l->id) }}" title="{{ $l->estValidee() ? 'Consulter' : 'Rédiger' }}" aria-label="Ouvrir"><i class="fa-regular {{ $l->estValidee() ? 'fa-eye' : 'fa-pen-to-square' }}"></i></a>
                  <a class="el-iconbtn el-iconbtn--teal" href="{{ route('decisions.redaction.imprimer', $l->id) }}" target="_blank" rel="noopener" title="Imprimer ou enregistrer en PDF" aria-label="Imprimer"><i class="fa-solid fa-print"></i></a>
                  @can('redaction.annuler')
                    @unless ($l->estValidee())
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer le brouillon" aria-label="Supprimer" onclick="delete_decision({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                    @endunless
                  @endcan
                </div>
              </td>
            </tr>
