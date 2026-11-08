<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset(config('elouange.favicon')) }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset(config('elouange.favicon')) }}" type="image/x-icon">
    <title>@yield('titre') | {{ config('app.name') }}</title>
    <!-- Font Awesome-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/fontawesome.css') }}">
    <!-- ico-font-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/icofont.css') }}">
    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/themify.css') }}">
    <!-- Flag icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/flag-icon.css') }}">
    <!-- Feather icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/feather-icon.css') }}">
    <!-- Plugins css start-->
    <!-- Plugins css Ends-->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/police.css') }}">
  </head>
  <body>
    <!-- tap on top starts-->
    <div class="tap-top"><i data-feather="chevrons-up"></i></div>
    <!-- tap on tap ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper compact-wrapper" id="pageWrapper">
      <!-- error start-->
      <div class="error-wrapper">
        <div class="container">
          <svg> 
            <use href="{{ asset('assets/svg/icon-sprite.svg#'.trim($__env->yieldContent('icone', 'error-500'))) }}"></use>
          </svg>
          <div class="grid grid-cols-12">
            <div class="col-start-4 md:col-start-0 col-span-6 md:col-span-12">
              <h3 class="[@media(max-width:575px)]:text-center">@yield('titre')</h3>
              <p class="sub-content [@media(max-width:575px)]:text-center [@media(max-width:767px)]:px-[15px]">@yield('message')</p>
              <p class="sub-content [@media(max-width:575px)]:text-center [@media(max-width:767px)]:px-[15px]" style="margin-top:8px;font-size:13px;opacity:.8;">@yield('aide', "Si le problème persiste, contactez l'administrateur de l'application.")</p>
            </div>
          </div>
          <div class="[@media(max-width:575px)]:text-center">
            <a class="btn btn-secondary btn-lg text-white hover:text-white !rounded-lg" href="javascript:history.back()" style="margin-right:8px;">RETOUR</a>
            <a class="btn btn-primary btn-lg text-white hover:text-white !rounded-lg" href="{{ route('dashboard') }}">TABLEAU DE BORD</a>
          </div>
        </div>
      </div>
      <!-- error end-->
    </div>
    <!-- latest jquery-->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery-3.5.1.min.js') }}"></script>
    <!-- feather icon js-->
    <script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
    <script src="{{ asset('assets/js/icons/feather-icon/feather-icon.js') }}"></script>
    <!-- scrollbar js-->
    <!-- Sidebar jquery-->
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('assets/js/modalpage/custom-modal.js') }}"> </script>
    <!-- Plugins JS start-->
    <!-- tooltip JS Ends-->
    <script src="{{ asset('assets/js/tooltip-init.js') }}"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="{{ asset('assets/js/script1.js') }}"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
  </body>
</html>