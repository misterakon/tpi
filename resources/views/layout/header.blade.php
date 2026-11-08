@php
    $moi = auth()->user();

    // Pastille avec les initiales (aucune photo dans l'application)
    $avatarInitiales = 'data:image/svg+xml;charset=UTF-8,'.rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect width="120" height="120" fill="#dbe7f7"/><text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="46" font-weight="700" fill="#12338f">'.e($moi->initiales()).'</text></svg>'
    );
@endphp

<style>
    /* Marque du tribunal dans l'en-tête : emblème + nom en gras (TPI orange, BINGERVILLE vert) */
    .page-header .header-logo-wrapper { width: auto !important; min-width: 0; }
    .page-header .header-logo-wrapper .logo-wrapper { display: flex; align-items: center; }
    .page-header .tpi-marque { display: flex; align-items: center; gap: 10px; text-decoration: none; transform: none !important; }
    .page-header .tpi-marque__logo { height: 46px; width: auto; margin: 0 !important; }
    .page-header .tpi-marque__nom { font-weight: 800; font-size: 18px; letter-spacing: .4px; white-space: nowrap; }
    .page-header .tpi-marque__tpi { color: #E8710A; }
    .page-header .tpi-marque__ville { color: #00794C; }
</style>

<div class="page-header">
    <div class="header-wrapper grid grid-cols-12 m-0">

        <div class="header-logo-wrapper hidden col-auto p-0 lg:block">
            <div class="logo-wrapper">
                <a class="tpi-marque" href="{{ route('dashboard') }}">
                    <img class="tpi-marque__logo" src="{{ asset('assets/images/logo/logo_tpi_embleme.png') }}" alt="">
                    <span class="tpi-marque__nom"><span class="tpi-marque__tpi">{{ config('tpi.sigle') }}</span> <span class="tpi-marque__ville">{{ config('tpi.ville') }}</span></span>
                </a>
            </div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>

        <div class="nav-right col-span-7 xxl:col-span-6 xl:col-span-7 md:col-span-11 float-right right-header p-0 ms-auto">
        <ul class="nav-menus">
            <li class="recherche-globale">
                <form method="get" action="{{ route('recherche') }}" role="search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" placeholder="Rechercher un dossier…" maxlength="120" autocomplete="off" aria-label="Rechercher"></form>
            </li>
            <li class="aide-bulle-aide"><button type="button" class="aide-btn-entete" id="aide-ouvrir" title="Aide de cette page (F1)" aria-label="Aide de cette page">?</button></li>
            <li class="fullscreen-body">
                <span>
                <svg id="maximize-screen">
                <use href="{{ asset('assets/svg/icon-sprite.svg#full-screen') }}"></use>
                </svg></span>
            </li>

            <li>
            <div class="mode">
                <svg>
                <use href="{{ asset('assets/svg/icon-sprite.svg#moon') }}"></use>
                </svg>
            </div>
            </li>

            @include('layout.cloche')

            <li class="profile-nav onhover-dropdown !py-0 !pe-0">
            <div class="flex profile-media items-center">
                <img src="{{ $avatarInitiales }}" alt="{{ $moi->name }}" width="35" height="35" style="object-fit: cover; border-radius: 50%;">
                <div class="profile-content"><span><b>{{ $moi->name }}</b></span>
                    <p class="mb-0" style="color:rgb(132, 248, 248)">{{ $moi->profil->nom }} <i class="align-middle fa-solid fa-angle-down"></i></p>
                </div>
            </div>
            <ul class="profile-dropdown onhover-show-div">
                <li><a class="flex items-center" href="{{ route('profile.edit') }}"><i data-feather="user"></i><span>Mon compte</span></a></li>
                <li>
                    <form method="POST" action="{{ route('verrouillage.lock') }}" class="w-full">
                        @csrf
                        <input type="hidden" name="retour" value="{{ url()->current() }}">
                        <a class="flex items-center" href="{{ route('verrouillage.lock') }}"
                           onclick="event.preventDefault(); this.closest('form').submit();">
                            <i data-feather="lock"></i><span>Verrouiller</span>
                        </a>
                    </form>
                </li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <a class="flex items-center" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); this.closest('form').submit();">
                            <i data-feather="log-in"></i><span>Déconnexion</span>
                        </a>
                    </form>
                </li>
            </ul>
            </li>
        </ul>
        </div>
    </div>
</div>
