<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

  @include('layout.head')

  <body>

    {{-- Menu HORIZONTAL imposé : le thème mémorise un éventuel choix « vertical » (paramètre ?layout=… ou stockage du navigateur) ;
         on les neutralise avant le chargement de customizer.js, qui lit ces valeurs. --}}
    <script>
      try { localStorage.setItem('page-wrapper-cuba', 'horizontal-wrapper enterprice-type'); } catch (e) {}
      (function () {
        var u = new URL(window.location.href);
        if (u.searchParams.has('layout')) { u.searchParams.delete('layout'); window.history.replaceState(null, '', u.toString()); }
      })();
    </script>

    <!-- loader starts-->
    @include('layout.loader')
    <!-- loader ends-->

    <!-- tap on top starts-->
    <div class="tap-top"><i data-feather="chevrons-up"></i></div>
    <!-- tap on tap ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper horizontal-wrapper enterprice-type" id="pageWrapper">

      <!-- Page Header Start-->
      @include('layout.header')
      <!-- Page Header Ends -->

      <!-- Page Body Start-->
      <div class="page-body-wrapper">

        <!-- Page Sidebar Start-->
        @include('layout.sidebar')
        <!-- Page Sidebar Ends-->

        {{-- contenu de la page --}}
        @yield('main-content')

        <!-- footer start-->
        @include('layout.footer')

      </div>
    </div>

    @include('layout.script')

    @include('layout.aide')
    @yield('script')

    <!-- Notifications -->
    @include('layout.alert_message')
    <!-- Notifications end -->
  </body>

</html>
