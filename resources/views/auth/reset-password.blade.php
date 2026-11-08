@extends('layout.auth')
@section('title', 'Nouveau mot de passe')

@section('content')

  {{-- Fond et décor : copiés dans la page (hors du cadre blanc) par le petit script ci-dessous --}}
  <template id="ea-fond">
    <div class="ea-bg" aria-hidden="true"></div>
  </template>
  <script>
    (function () {
      if (document.querySelector('body > .ea-bg')) return;
      var modele = document.getElementById('ea-fond');
      if (modele && document.body) {
        document.body.insertBefore(modele.content.cloneNode(true), document.body.firstChild);
      }
    })();
  </script>

  <form class="theme-form ea-login" method="POST" action="{{ route('password.store') }}"
        data-ajax
        data-loading="Enregistrement de votre mot de passe…"
        data-success="Mot de passe enregistré"
        data-btn-loading="Enregistrement…">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <img class="ea-logo" src="{{ asset('assets/images/logo/logo_tpi.png') }}" alt="{{ config('app.name') }}">

    <h4 class="text-center font-bold ea-title mt-4">Choisir un mot de passe</h4>
    <p class="text-center">Au moins 8 caractères.</p>

    <div class="form-group">
      <label class="col-form-label" for="email">E-mail</label>
      <input
        class="form-control @error('email') is-invalid @enderror"
        id="email"
        type="email"
        name="email"
        value="{{ old('email', $request->query('email')) }}"
        required
        autocomplete="username">
      @error('email')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group">
      <label class="col-form-label" for="password">Nouveau mot de passe</label>
      <div class="form-input relative">
        <input
          class="form-control @error('password') is-invalid @enderror"
          id="password"
          type="password"
          name="password"
          required
          autofocus
          autocomplete="new-password"
          placeholder="*********">
        <div class="show-hide"><span class="show"> </span></div>
      </div>
      @error('password')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group">
      <label class="col-form-label" for="password_confirmation">Confirmer le mot de passe</label>
      <input
        class="form-control"
        id="password_confirmation"
        type="password"
        name="password_confirmation"
        required
        autocomplete="new-password"
        placeholder="*********">
    </div>

    <div class="form-group mt-4 text-center">
      <button class="btn btn-primary btn-block w-auto px-6 py-2 text-white text-center" type="submit">ENREGISTRER</button>
    </div>
  </form>
@endsection
