@extends('layout.auth')
@section('title', 'Mot de passe oublié')

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

  <form class="theme-form ea-login" method="POST" action="{{ route('password.email') }}"
        data-ajax
        data-loading="Envoi du lien…"
        data-btn-loading="Envoi…">
    @csrf
    <img class="ea-logo" src="{{ asset('assets/images/logo/logo_tpi.png') }}" alt="{{ config('app.name') }}">
    <h4 class="text-center font-bold ea-title mt-4">Mot de passe oublié</h4>
    <p class="text-center">Saisissez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>

    <div class="form-group">
      <label class="col-form-label" for="email">E-mail</label>
      <input
        class="form-control @error('email') is-invalid @enderror"
        id="email"
        type="email"
        name="email"
        value="{{ old('email') }}"
        required
        autofocus
        autocomplete="username"
        placeholder="nom@exemple.com">
      @error('email')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group mt-4 text-center">
      <button class="btn btn-primary btn-block w-auto px-6 py-2 text-white text-center" type="submit">ENVOYER LE LIEN</button>
      <p class="mt-4 mb-0"><a href="{{ route('login') }}" data-swap>Retour à la connexion</a></p>
    </div>
  </form>
@endsection
