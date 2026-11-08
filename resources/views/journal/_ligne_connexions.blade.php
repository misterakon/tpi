            @php [$libelle, $ton] = \App\Support\JournalLibelles::connexion($l->evenement); @endphp
            <tr>
              <td class="el-num" style="white-space:nowrap">{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
              <td>
                <x-chip :tone="$ton" :label="$libelle" />
                @if ($l->motif)<div class="el-person__sub" style="margin-top:4px">{{ $l->motif }}</div>@endif
              </td>
              <td>
                @if ($l->user)
                  <div class="el-person__name">{{ $l->user->name }}</div>
                  <div class="el-person__sub">{{ $l->user->email }}</div>
                @elseif ($l->email_saisi)
                  <div class="el-person__name">{{ $l->email_saisi }}</div>
                  <div class="el-person__sub">adresse saisie (aucun compte)</div>
                @else
                  <span class="el-muted">—</span>
                @endif
              </td>
              <td class="el-num">{{ $l->ip ?? '—' }}</td>
              <td title="{{ $l->navigateur }}" style="max-width:260px"><span class="el-coupe">{{ $l->navigateur ?: '—' }}</span></td>
            </tr>
