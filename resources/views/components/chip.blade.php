@props(['tone' => 'neutral', 'label'])

{{-- Pastille d'état : tone = success | warning | danger | alert | info | purple | gold | neutral --}}
<span {{ $attributes->class(['el-chip', 'el-chip--'.$tone]) }}><i></i>{{ $label }}</span>
