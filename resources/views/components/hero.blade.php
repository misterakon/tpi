@props(['tag' => null, 'title', 'subtitle' => null, 'icon' => 'fa-solid fa-circle'])

{{-- Bannière de page : étiquette, titre, sous-titre, zone d'infos (slot « extra ») et boutons (slot « actions »). --}}
<section {{ $attributes->class(['el-hero']) }}>
  <span class="el-hero__dots" aria-hidden="true"></span>
  <i class="el-hero__mark {{ $icon }}" aria-hidden="true"></i>

  <div class="el-hero__body">
    <div class="el-hero__text">
      @if ($tag)
        <span class="el-tag" style="font-weight: bold; color:#000"><span class="el-tag__dot"></span>{{ $tag }}</span>
      @endif
      <h1 class="el-hero__title">{{ $title }}</h1>
      @if ($subtitle)
        <p class="el-hero__sub">{{ $subtitle }}</p>
      @endif
      @isset($extra)
        <div class="el-hero__extra">{{ $extra }}</div>
      @endisset
    </div>
  </div>

  @isset($actions)
    <div class="el-hero__actions">{{ $actions }}</div>
  @endisset
</section>
