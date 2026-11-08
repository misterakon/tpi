{{-- Onglet « Scellés » de la fiche d'une affaire pénale ou d'instruction. Variables : $affaire, $scelles --}}
<div class="el-card aff-carte">
  <div class="el-card__head">
    <div><h2 class="el-card__title">Scellés</h2><div class="el-card__sub">Pièces à conviction conservées au greffe : {{ $scelles->where('etat', 'conserve')->count() }} conservé(s) sur {{ $scelles->count() }}.</div></div>
    @can('scelles.creer')
      @unless ($affaire->estAnnulee())
        <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_scelle(null)"><i class="fa-solid fa-plus"></i> Ajouter un scellé</button>
      @endunless
    @endcan
  </div>
  <div class="el-card__body">
    @if ($scelles->isEmpty())
      <div class="aff-info"><i class="fa-solid fa-box-archive"></i><span>Aucun scellé enregistré pour cette affaire.</span></div>
    @else
      <div class="el-tblwrap">
        <table class="el-tbl el-tbl--fixe" id="tbl-scelles">
          <colgroup><col style="width:90px"><col><col style="width:150px"><col style="width:120px"><col style="width:180px"><col style="width:190px">@canany(['scelles.modifier', 'scelles.supprimer'])<col style="width:104px">@endcanany</colgroup>
          <thead><tr><th>N°</th><th>Désignation</th><th>Nature</th><th>Saisi le</th><th>Conservation</th><th>État</th>@canany(['scelles.modifier', 'scelles.supprimer'])<th>Actions</th>@endcanany</tr></thead>
          <tbody>
            @foreach ($scelles as $s)
              <tr id="scelle-{{ $s->id }}">
                <td><b>{{ $s->numero }}</b></td>
                <td>
                  <div class="el-person__name" title="{{ $s->designation }}">{{ $s->designation }}</div>
                  @if ($s->observations)<div class="el-person__sub" title="{{ $s->observations }}">{{ $s->observations }}</div>@endif
                </td>
                <td>{{ \App\Models\Scelle::NATURES[$s->nature] ?? $s->nature }}</td>
                <td>{{ $s->date_saisie?->format('d/m/Y') ?? '—' }}</td>
                <td title="{{ $s->lieu_conservation }}">{{ $s->lieu_conservation ?: '—' }}</td>
                <td>
                  <x-chip :tone="$s->estConserve() ? 'success' : 'neutral'" :label="\App\Models\Scelle::ETATS[$s->etat] ?? $s->etat" class="el-chip--libre" />
                  @if ($s->date_etat)<div class="el-person__sub">le {{ $s->date_etat->format('d/m/Y') }}</div>@endif
                </td>
                @canany(['scelles.modifier', 'scelles.supprimer'])
                  <td>
                    <div style="display:flex; gap:8px">
                      @can('scelles.modifier')
                        @unless ($affaire->estAnnulee())
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" data-scelle="{{ json_encode($s->only(['id', 'numero', 'designation', 'nature', 'lieu_conservation', 'etat', 'observations']) + ['date_saisie' => $s->date_saisie?->format('Y-m-d'), 'date_etat' => $s->date_etat?->format('Y-m-d')]) }}" onclick="ouvrir_scelle(JSON.parse(this.getAttribute('data-scelle')))"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endunless
                      @endcan
                      @can('scelles.supprimer')
                        <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="supprimer_scelle({{ $s->id }})"><i class="fa-regular fa-trash-can"></i></button>
                      @endcan
                    </div>
                  </td>
                @endcanany
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
