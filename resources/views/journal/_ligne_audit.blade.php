            @php
              [$libelle, $ton] = \App\Support\JournalLibelles::audit($l->action);
              $champs = collect(array_keys(($l->avant ?? []) + ($l->apres ?? [])));
              $texte = fn ($v) => is_scalar($v) || $v === null ? (is_bool($v) ? ($v ? 'oui' : 'non') : (string) $v) : json_encode($v, JSON_UNESCAPED_UNICODE);
            @endphp
            <tr>
              <td class="el-num" style="white-space:nowrap">{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
              <td><x-chip :tone="$ton" :label="$libelle" /></td>
              <td>
                @if ($l->user)
                  <div class="el-person__name">{{ $l->user->name }}</div>
                @else
                  <span class="el-muted">Système</span>
                @endif
                @if ($l->ip)<div class="el-person__sub">{{ $l->ip }}</div>@endif
              </td>
              <td>{{ $l->libelle ?: '—' }}</td>
              <td>
                @if ($champs->isNotEmpty())
                  <details class="el-detail">
                    <summary>{{ $champs->count() }} champ(s)</summary>
                    <table class="el-detail__tbl">
                      <thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead>
                      <tbody>
                        @foreach ($champs as $c)
                          <tr>
                            <td>{{ \App\Support\JournalLibelles::champ($c) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($texte($l->avant[$c] ?? null) ?: '—', 160) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($texte($l->apres[$c] ?? null) ?: '—', 160) }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </details>
                @else
                  <span class="el-muted">—</span>
                @endif
              </td>
            </tr>
