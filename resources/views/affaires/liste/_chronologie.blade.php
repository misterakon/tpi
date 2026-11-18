{{-- Chronologie d'une affaire avec les durées entre les actions. Variable : $chronologie (voir App\Support\Durees::chronologie). Affichage seul. --}}
@php use App\Support\Durees; @endphp
<div class="el-card aff-carte" style="margin-bottom:18px">
  <div class="el-card__head">
    <div>
      <h2 class="el-card__title">Chronologie et durées</h2>
      <div class="el-card__sub">
        Les actions de l'affaire, avec le temps écoulé entre chacune.
        @if ($chronologie['total'] !== null)
          <b>{{ $chronologie['ouverte'] ? 'En cours depuis' : 'Durée totale' }} : {{ Durees::texte($chronologie['total']) }}</b>
        @endif
      </div>
    </div>
  </div>
  <div class="el-card__body">
    @if (count($chronologie['evenements']) <= 1)
      <span class="el-muted">Aucune autre action enregistrée pour le moment.</span>
    @else
      <div class="el-timeline">
        @foreach ($chronologie['evenements'] as $e)
          <div class="el-timeline__item" style="{{ $e['prevu'] ? 'opacity:.75' : '' }}">
            <span class="el-timeline__dot" @if ($e['prevu']) style="border-style:dashed" @endif></span>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
              <div class="el-timeline__text">
                <i class="fa-solid {{ $e['icone'] }}" style="color:#00794C; width:18px" aria-hidden="true"></i>
                <b>{{ $e['libelle'] }}</b>
                @if ($e['detail'])<span class="el-muted"> · {{ $e['detail'] }}</span>@endif
              </div>
              <div style="display:flex; gap:6px; flex-wrap:wrap">
                @if ($e['depuis_precedent'] !== null && $loop->index > 0)
                  <span title="Depuis l'action précédente"><x-chip :tone="$e['prevu'] ? 'neutral' : 'info'" :label="($e['prevu'] ? 'dans ' : '').Durees::texte($e['depuis_precedent'])" /></span>
                @endif
                @if ($loop->index > 0 && $e['depuis_enrolement'] !== null)
                  <span title="Depuis l'enregistrement"><x-chip tone="neutral" :label="'J + '.$e['depuis_enrolement']" /></span>
                @endif
              </div>
            </div>
            <div class="el-timeline__meta">{{ $e['date']->translatedFormat('l j F Y') }}</div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
