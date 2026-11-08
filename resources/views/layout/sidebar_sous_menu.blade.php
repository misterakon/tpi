{{-- Sous-menu d'une entrée du menu : liens et groupes imbriqués (« submenu-title » du thème). Variables : $enfants, $actif, $lien, $contientActif --}}
<ul class="sidebar-submenu">
    @foreach ($enfants as $enfant)
        @if (isset($enfant['enfants']))
            <li>
                <a class="submenu-title {{ $contientActif($enfant) ? 'active' : '' }}" href="#">{{ $enfant['titre'] }}<span class="sub-arrow"><i class="fa-solid fa-angle-right"></i></span></a>
                @include('layout.sidebar_sous_menu', ['enfants' => collect($enfant['enfants'])])
            </li>
        @else
            <li><a class="{{ $actif($enfant) ? 'active' : '' }}" href="{{ $lien($enfant['route'] ?? null) }}">{{ $enfant['titre'] }}</a></li>
        @endif
    @endforeach
</ul>
