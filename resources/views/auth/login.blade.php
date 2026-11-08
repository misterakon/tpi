@extends('layout.auth')
@section('title', 'Connexion')

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

  <form class="theme-form ea-login" method="POST" action="{{ route('login') }}"
        data-ajax
        data-loading="Vérification de vos accès…"
        data-loading2="Envoi du code par e-mail…"
        data-success="Code envoyé"
        data-btn-loading="Connexion…">
    @csrf
    <img class="ea-logo" src="{{ asset('assets/images/logo/logo_tpi.png') }}" alt="{{ config('app.name') }}">
    {{-- <h4 class="text-center font-bold ea-title">{{ config('app.name') }}</h4> --}}
    <p class="text-center mt-4" style="color: #000">Veuillez entrer votre e-mail et votre mot de passe</p>

    <div class="form-group">
      <label class="col-form-label" for="email">E-mail</label>
      <div class="ea-champ">
        <i class="fa-solid fa-envelope ea-ico" aria-hidden="true"></i>
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
      </div>
      @error('email')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group">
      <label class="col-form-label" for="password">Mot de passe</label>
      <div class="ea-champ ea-champ--mdp">
        <i class="fa-solid fa-lock ea-ico" aria-hidden="true"></i>
        <input
          class="form-control @error('password') is-invalid @enderror"
          id="password"
          type="password"
          name="password"
          required
          autocomplete="current-password"
          placeholder="*********">
        {{-- Gestionnaire écrit directement dans le bouton : il fonctionne même quand le formulaire est affiché sans rechargement de la page --}}
        <button type="button" class="ea-oeil" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher / masquer"
          onclick="var i=document.getElementById('password'),v=i.type==='password';i.type=v?'text':'password';this.setAttribute('aria-pressed',v?'true':'false');this.setAttribute('aria-label',v?'Masquer le mot de passe':'Afficher le mot de passe');this.querySelector('i').className=v?'fa-solid fa-eye-slash':'fa-solid fa-eye';i.focus();">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
      </div>
      @error('password')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group mt-4">
      <a class="link" href="{{ route('password.request') }}" data-swap>Mot de passe oublié ?</a><br>

      <div class="text-center">
        <button class="btn btn-primary btn-block w-auto px-6 py-2 text-white mt-[24px] text-center" type="submit">
          SE CONNECTER
        </button>
      </div>
      <p class="text-center mt-4 mb-0 ea-aide">
        Un code de vérification vous sera envoyé par email.
      </p>
    </div>
  </form>
@endsection
