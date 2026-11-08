<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="{{ config('app.name') }}">
    <link rel="icon" href="{{ asset(config('elouange.favicon')) }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset(config('elouange.favicon')) }}" type="image/x-icon">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/fontawesome.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/icofont.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/themify.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/flag-icon.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/feather-icon.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/elouange-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/police.css') }}">
    <link rel="preload" href="{{ asset('assets/fonts/inter/inter-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('assets/fonts/inter/inter-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
  </head>
  <body>
    <div class="container">
      <div class="grid grid-cols-12 m-0">
        <div class="col-span-12 p-0">
          <div class="login-card login-dark">
            <div>
              <div>
                <a class="logo" href="{{ url('/') }}">
                  <img class="max-w-full h-auto for-light" src="{{ asset(config('elouange.logo')) }}" style="width: 50%" alt="{{ config('app.name') }}">
                  <img class="max-w-full h-auto for-dark" src="{{ asset(config('elouange.logo_sombre')) }}" style="width: 50%" alt="{{ config('app.name') }}">
                </a>
              </div>
              <div class="login-main">
                {{-- Message de statut (lien envoyé, mot de passe enregistré, code renvoyé…) --}}
                @if (session('status'))
                  <div class="ea-notice" role="status"><i class="fa-solid fa-circle-check"></i><span>{{ session('status') }}</span></div>
                @endif

                @yield('content')
              </div>
            </div>
          </div>
        </div>
      </div>

      <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
      <script src="{{ asset('assets/js/jquery-3.5.1.min.js') }}"></script>
      <script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
      <script src="{{ asset('assets/js/icons/feather-icon/feather-icon.js') }}"></script>
      <script src="{{ asset('assets/js/config.js') }}"></script>
      <script src="{{ asset('assets/js/modalpage/custom-modal.js') }}"></script>
      <script src="{{ asset('assets/js/tooltip-init.js') }}"></script>
      <script src="{{ asset('assets/js/script1.js') }}"></script>
      <script src="{{ asset('assets/js/script.js') }}"></script>
      <script src="{{ asset('assets/js/elouange-ui.js') }}"></script>
      <script src="{{ asset('assets/js/elouange-auth.js') }}?v={{ filemtime(public_path('assets/js/elouange-auth.js')) }}"></script>
      @yield('script')

    </div>
  </body>
</html>
