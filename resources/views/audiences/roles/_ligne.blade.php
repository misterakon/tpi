            @php($conflit = $r->date_audience->gte(today()) && $r->conflits()->isNotEmpty())
            <tr>
              <td data-order="{{ $r->date_audience->format('Y-m-d') }}" data-search="{{ $r->date_audience->format('d/m/Y') }} {{ $r->date_audience->locale('fr')->translatedFormat('l F Y') }}">
                <div class="rl-date"><span class="rl-date__j"><b>{{ $r->date_audience->format('d') }}</b><small>{{ $r->date_audience->copy()->locale('fr')->translatedFormat('M') }}</small></span>
                  <span class="rl-date__t"><b>{{ ucfirst($r->date_audience->copy()->locale('fr')->translatedFormat('l')) }}</b><small>{{ $r->date_audience->format('Y') }}</small></span></div>
              </td>
              <td title="{{ $r->chambre->libelleComplet() }}"><div class="el-person__name">{{ $r->chambre->libelle }}</div><div class="el-person__sub">{{ $r->typeLibelle() }}{{ $r->chambre->intitule ? ' · '.$r->chambre->intitule : '' }}</div></td>
              <td>
                <div class="el-person__name" title="{{ $r->salle?->nom }}">{{ $r->salle?->nom ?? 'Salle à fixer' }}</div>
                <div class="el-person__sub">{{ $r->heureCourte() ?? 'Heure à fixer' }}@if ($conflit) · <span class="rl-alerte"><i class="fa-solid fa-triangle-exclamation"></i> conflit</span>@endif</div>
              </td>
              <td data-order="{{ $r->lignes_count }}"><b>{{ $r->lignes_count }}</b> <span class="el-muted">affaire{{ $r->lignes_count > 1 ? 's' : '' }}</span></td>
              <td data-search="{{ $r->statut === 'publie' ? 'publié' : 'brouillon préparation' }}">
                <x-chip :tone="$r->estPublie() ? 'success' : 'alert'" :label="$r->estPublie() ? 'Publié' : 'En préparation'" class="el-chip--libre" />
                <div class="el-person__sub">version {{ $r->version }}</div>
              </td>
              <td>
                <div class="rl-actions">
                  <a class="el-iconbtn el-iconbtn--blue" href="{{ route('audiences.roles.show', $r->id) }}" title="Ouvrir le rôle" aria-label="Ouvrir le rôle"><i class="fa-solid fa-folder-open"></i></a>
                  @can('roles.imprimer')
                    <a class="el-iconbtn el-iconbtn--teal" href="{{ route('audiences.roles.export', [$r->id, 'docx']) }}" title="Exporter en Word" aria-label="Exporter en Word"><i class="fa-solid fa-file-word"></i></a>
                    <a class="el-iconbtn el-iconbtn--red" href="{{ route('audiences.roles.export', [$r->id, 'pdf']) }}" title="Exporter en PDF" aria-label="Exporter en PDF"><i class="fa-solid fa-file-pdf"></i></a>
                  @endcan
                </div>
              </td>
            </tr>
