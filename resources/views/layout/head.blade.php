<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Tableau de bord {{ config('app.name') }}">
    <link rel="icon" href="{{ asset(config('elouange.favicon')) }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset(config('elouange.favicon')) }}" type="image/x-icon">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <!-- Google font-->
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
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/slick.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/slick-theme.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/jquery.dataTables.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/animate.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/prism.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/sweetalert2.css') }}">
    <!-- Plugins css Ends-->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/header-design.css') }}?v={{ filemtime(public_path('assets/css/header-design.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/aide.css') }}?v={{ filemtime(public_path('assets/css/aide.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/transitions.css') }}?v={{ filemtime(public_path('assets/css/transitions.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/elouange-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vendors/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/select2-theme.css') }}?v={{ filemtime(public_path('assets/css/select2-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/police.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/alertes.css') }}?v={{ filemtime(public_path('assets/css/alertes.css')) }}">
    <link rel="preload" href="{{ asset('assets/fonts/inter/inter-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('assets/fonts/inter/inter-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @yield('css')
</head>
