            <tr>
              <td><a href="{{ route('affaires.liste.fiche', $l->id) }}#dossier"><b>{{ $l->numeroAffichage() }}</b></a></td>
              <td>
                <div class="el-person__name" title="{{ $l->intitule() }}">{{ $l->intitule() }}</div>
                <div class="el-person__sub" title="{{ $l->objet }}">{{ $l->objet }}</div>
              </td>
              <td title="{{ $l->chambre?->libelle }}">{{ $l->chambre?->libelle }}</td>
              <td data-search="{{ $l->pieces_count ? 'avec' : 'vide' }}" data-order="{{ $l->pieces_count }}"><span class="dn-pj {{ $l->pieces_count ? '' : 'dn-pj--zero' }}"><i class="fa-solid fa-paperclip"></i> {{ $l->pieces_count }}</span></td>
              <td data-order="{{ $l->pieces_max_created_at }}">{{ $l->pieces_max_created_at ? \Illuminate\Support\Carbon::parse($l->pieces_max_created_at)->format('d/m/Y à H:i') : '—' }}</td>
              <td title="{{ $l->localisation_dossier }}">{{ $l->localisation_dossier ?: '—' }}</td>
              <td><a class="el-iconbtn el-iconbtn--blue" href="{{ route('affaires.liste.fiche', $l->id) }}#dossier" title="Ouvrir le dossier" aria-label="Ouvrir le dossier"><i class="fa-regular fa-folder-open"></i></a></td>
            </tr>
