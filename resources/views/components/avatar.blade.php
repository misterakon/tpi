@props(['name', 'size' => 44])

@php
    $mots = preg_split('/\s+/u', trim($name)) ?: [];
    $initiales = implode('', array_map(fn ($m) => mb_strtoupper(mb_substr($m, 0, 1)), array_slice($mots, 0, 2))) ?: '?';
    $teinte = crc32($name) % 6;   // une couleur stable par personne
@endphp

<span {{ $attributes->class(['el-avatar', 'el-hue-'.$teinte]) }} style="--s: {{ $size }}px" aria-hidden="true">{{ $initiales }}</span>
