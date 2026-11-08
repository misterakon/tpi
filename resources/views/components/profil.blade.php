@props(['profil'])

@php
    // Admin en orange ; les autres profils : une couleur stable choisie d'après leur identifiant.
    $tons = ['success', 'info', 'purple', 'gold', 'neutral'];
    $ton = $profil->estSysteme() ? 'alert' : $tons[$profil->id % count($tons)];
@endphp

<x-chip :tone="$ton" :label="$profil->nom" {{ $attributes }} />
