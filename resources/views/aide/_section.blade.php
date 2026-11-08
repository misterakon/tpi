{{-- Une section du manuel. Variables : $s (section), $i (rang), $faq (bool : question dépliable), $c (chapitre) --}}
@if ($faq)
  <details class="aide-faq" id="{{ $s['ancre'] }}" @if ($i === 0 && ! empty($ouvrirPremiere)) open @endif>
    <summary><i class="q fa-solid fa-circle-question"></i><span>{{ $s['titre'] }}</span><i class="fl fa-solid fa-chevron-down"></i></summary>
    <div class="aide-texte">{!! $s['html'] !!}</div>
  </details>
@else
  <section class="aide-section" id="{{ $s['ancre'] }}" data-spy style="animation-delay: {{ min($i, 8) * 60 }}ms">
    <h2><span class="num">{{ $i + 1 }}</span><span>{{ $s['titre'] }}</span><span class="temps"><i class="fa-regular fa-clock"></i> {{ $s['duree'] }} min</span><button type="button" class="ancre" data-copier="{{ $s['ancre'] }}" title="Copier le lien de cette section" aria-label="Copier le lien"><i class="fa-solid fa-link"></i></button></h2>
    <div class="aide-texte">{!! $s['html'] !!}</div>
  </section>
@endif
