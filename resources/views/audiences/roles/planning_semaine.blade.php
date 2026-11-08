{{-- Une semaine du planning des salles (renvoyée seule quand on change de semaine sans recharger la page). Variables : $lundi, $jours, $roles, $prevues, $lignes, $enConflit, $fermetures, $precedent, $suivant --}}
<div class="rl-planning__barre">
  <a class="rl-nav" href="{{ route('audiences.roles.planning', ['du' => $precedent]) }}" data-semaine="{{ $precedent }}" title="Semaine précédente" aria-label="Semaine précédente"><i class="fa-solid fa-chevron-left"></i></a>
  <div class="rl-planning__semaine">
    <b>Semaine du {{ $lundi->copy()->locale('fr')->translatedFormat('j F') }} au {{ $lundi->copy()->addDays(5)->locale('fr')->translatedFormat('j F Y') }}</b>
    <span class="rl-planning__puces">
      <span class="rl-puce-info rl-puce-info--vert"><i class="fa-solid fa-gavel"></i> {{ $roles->count() }} rôle{{ $roles->count() > 1 ? 's' : '' }}</span>
      <span class="rl-puce-info {{ count($enConflit) ? 'rl-puce-info--rouge' : 'rl-puce-info--gris' }}"><i class="fa-solid fa-triangle-exclamation"></i> {{ count($enConflit) }} conflit{{ count($enConflit) > 1 ? 's' : '' }}</span>
      @if (count($avertis))<span class="rl-puce-info rl-puce-info--orange"><i class="fa-solid fa-user-clock"></i> {{ count($avertis) }} avertissement{{ count($avertis) > 1 ? 's' : '' }}</span>@endif
      @can('roles.creer')<span class="rl-puce-info rl-puce-info--orange"><i class="fa-solid fa-hourglass-start"></i> {{ $prevues->count() }} à préparer</span>@endcan
    </span>
  </div>
  <div class="rl-planning__droite">
    @unless ($lundi->isSameWeek(today()))
      <a class="rl-nav rl-nav--texte" href="{{ route('audiences.roles.planning') }}" data-semaine="{{ today()->startOfWeek()->toDateString() }}"><i class="fa-solid fa-rotate-left"></i> Cette semaine</a>
    @endunless
    <label class="rl-aller" title="Aller à une date">
      <i class="fa-solid fa-calendar-day"></i>
      <input type="date" id="planning-date" value="{{ $lundi->toDateString() }}" aria-label="Aller à la semaine d'une date">
    </label>
    <a class="rl-nav" href="{{ route('audiences.roles.planning', ['du' => $suivant]) }}" data-semaine="{{ $suivant }}" title="Semaine suivante" aria-label="Semaine suivante"><i class="fa-solid fa-chevron-right"></i></a>
  </div>
</div>

<table class="rl-grille">
  <colgroup><col style="width:190px">@foreach ($jours as $j)<col>@endforeach</colgroup>
  <thead>
    <tr>
      <th class="rl-grille__coin"><i class="fa-solid fa-building-columns"></i> Salle</th>
      @foreach ($jours as $j)
        @php $f = $fermetures[$j->toDateString()]; @endphp
        <th class="{{ $j->isToday() ? 'is-aujourdhui' : '' }} {{ $f ? 'is-ferme' : '' }}">
          <span class="rl-grille__j">{{ ucfirst($j->copy()->locale('fr')->translatedFormat('l')) }}</span>
          <span class="rl-grille__d">{{ $j->format('d/m') }}</span>
          @if ($f)<span class="rl-grille__f" title="{{ $f->libelle }}"><i class="fa-solid fa-calendar-xmark"></i> {{ \Illuminate\Support\Str::limit($f->libelle, 20) }}</span>@endif
        </th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach ($lignes as $ligne)
      <tr>
        <th class="rl-grille__salle">
          <div class="rl-salle">
            <span class="rl-salle__txt"><b>{{ $ligne['salle']?->nom ?? 'Sans salle' }}</b><small>{{ $ligne['salle']?->typeLibelle() ?? 'Salle à fixer' }}</small></span>
          </div>
        </th>
        @foreach ($jours as $j)
          @php
            $d = $j->toDateString();
            $rolesJour = $roles->filter(fn ($r) => $r->date_audience->toDateString() === $d && $r->salle_id === $ligne['id']);
            $prevuesJour = $prevues->filter(fn ($p) => $p['date']->toDateString() === $d && $p['salle_id'] === $ligne['id']);
          @endphp
          <td class="{{ $j->isToday() ? 'is-aujourdhui' : '' }} {{ $fermetures[$d] ? 'is-ferme' : '' }}">
            @foreach ($rolesJour as $r)
              <a href="{{ route('audiences.roles.show', $r->id) }}" class="rl-carte {{ in_array($r->id, $enConflit, true) ? 'rl-carte--conflit' : ($r->estPublie() ? 'rl-carte--publie' : 'rl-carte--brouillon') }}">
                <span class="rl-carte__heure">{{ $r->heureCourte() ?? '—' }}</span>
                <span class="rl-carte__texte"><b>{{ $r->chambre->libelle }}</b><small>{{ $r->lignes_count }} affaire{{ $r->lignes_count > 1 ? 's' : '' }} · {{ $r->estPublie() ? 'publié' : 'en préparation' }}</small></span>
                @if (in_array($r->id, $enConflit, true))<i class="fa-solid fa-triangle-exclamation" title="Conflit de salle"></i>@elseif (isset($avertis[$r->id]))<i class="fa-solid fa-user-clock rl-carte__avert" title="{{ $avertis[$r->id] }}"></i>@endif
              </a>
            @endforeach
            @foreach ($prevuesJour as $p)
              <button type="button" class="rl-carte rl-carte--prevue" onclick="ouvrir_role({{ $p['chambre']->id }}, '{{ $d }}')" title="Créer le rôle">
                <span class="rl-carte__heure">{{ $p['heure'] ? str_replace(':', 'h', $p['heure']) : '—' }}</span>
                <span class="rl-carte__texte"><b>{{ $p['chambre']->libelle }}</b><small>rôle à créer</small></span>
                <i class="fa-solid fa-plus"></i>
              </button>
            @endforeach
          </td>
        @endforeach
      </tr>
    @endforeach
  </tbody>
</table>

<div class="rl-legende">
  <span><i class="rl-pastille rl-pastille--publie"></i> Publié</span>
  <span><i class="rl-pastille rl-pastille--brouillon"></i> En préparation</span>
  <span><i class="rl-pastille rl-pastille--prevue"></i> Audience habituelle sans rôle</span>
  <span><i class="rl-pastille rl-pastille--conflit"></i> Conflit de salle</span>
  <span><i class="rl-pastille rl-pastille--ferme"></i> Jour fermé</span>
</div>
